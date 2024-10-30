<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin;

if ( ! function_exists( 'db_migration_200' ) ) {
	function db_migration_200( $table_prefix ) {
		try {
			global $wpdb;

			// Create transfer log table
			if ( ! rename_legacy_transfer_log_table( $table_prefix ) ) {
				return false;
			}

			return true;
		} catch ( Exception $e ) {
			error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error executing database migration: ' . __FUNCTION__ );

			return false;
		}
	}
}

if ( ! function_exists( 'rename_legacy_transfer_log_table' ) ) {
	// Added to migrate older installations
	function rename_legacy_transfer_log_table( $table_prefix ) {
		global $wpdb;
		$old_table_name = $table_prefix . 'bsd_wp_scsp_transfer_log';
		$new_table_name = $table_prefix . 'bsd_scsp_transfer_log';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_table_name'" ) == $old_table_name ) {
			$sql = 'RENAME TABLE ' . $old_table_name . ' TO ' . $new_table_name . ';';

			$query_result = $wpdb->query( $sql );

			if ( $query_result === false ) {
				return false;
			}
		} else {
			$table_name = $table_prefix . 'bsd_scsp_transfer_log';

			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
				$sql = 'CREATE TABLE ' . $table_name . '(
            id INT NOT NULL AUTO_INCREMENT
            , wc_order_id BIGINT UNSIGNED
            , charge_amount decimal(10,2)
            , transfer_amount decimal(10,2)
            , charge_id VARCHAR(50)
            , charge_date DATETIME
            , charge_description VARCHAR(200)
            , transfer_id VARCHAR(50)
            , transfer_destination VARCHAR(50)
            , stripe_mode VARCHAR(5)
            , date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            , date_modified DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            , PRIMARY KEY (id)
          ) ENGINE=InnoDB ' . $wpdb->get_charset_collate() . ';';

				dbDelta( $sql );

				if ( $query_result === false ) {
					error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error creating database table: ' . $query_result );

					return false;
				}
			}

			return true;
		}

		// Delete legacy option - Added in v2.6
		delete_option( 'bsd_wc_scsp_db_version' );

		return true;
	}
}