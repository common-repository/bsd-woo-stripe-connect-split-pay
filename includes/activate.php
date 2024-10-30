<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc;

if ( ! function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\Inc\bsd_scsp_activate_plugin' ) ) {
	function bsd_scsp_activate_plugin() {
		if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
			wp_die( __( 'You must update WordPress to use this plugin.', 'bsd-split-pay-stripe-connect-woo' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$table_prefix = $wpdb->prefix;
		$table_name   = $table_prefix . BSD_TRANSFER_LOG_TABLE;
		try {

			if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) != $table_name ) {
				// dbDelta( "SET sql_mode = '';" );
				$sql = 'CREATE TABLE ' . $table_name . '(
                        id INT NOT NULL AUTO_INCREMENT
                        , wc_order_id BIGINT UNSIGNED
                        , charge_amount decimal(10,2)
                        , transfer_amount decimal(10,2)
                        , transfer_tax_value decimal(10,2)
                        , transfer_entered_value decimal(10,2)
                        , item_total decimal(10,2)
                        , item_transfer_amount decimal(10,2)
                        , item_tax_total decimal(10,2)
                        , tax_transfer_type char(5)
                        , charge_id VARCHAR(50)
                        , charge_date DATETIME
                        , charge_description VARCHAR(200)
                        , transfer_id VARCHAR(50)
                        , transfer_destination VARCHAR(50)
                        , stripe_mode VARCHAR(5)
                        , transfer_type tinyint(2)
                        , date_created DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00"
                        , date_modified DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00"
                        , PRIMARY KEY (id)
              ) ENGINE=InnoDB ' . $wpdb->get_charset_collate() . ';';

				$is_table_created = dbDelta( $sql );
				update_option( 'bsd_log_table_created', '1.4' );
			}
		} catch ( \Exception $e ) {
			error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error creating database table: ' . $table_name );

		}

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $table_prefix . BSD_SCSP_STRP_ACCNT_TABLE;
		try {
			if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) != $table_name ) {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $table_name . " (
                        bsd_sat_id bigint(20) NOT NULL AUTO_INCREMENT,
                        bsd_account_id varchar(55) DEFAULT '' NOT NULL,
                        bsd_account_name varchar(55) DEFAULT '' NOT NULL,
                        bsd_account_email varchar(255) DEFAULT '' NOT NULL,
                        PRIMARY KEY  (bsd_sat_id)
                    ) $charset_collate;";

				$is_table_created = dbDelta( $sql );
				if ( ! empty( $is_table_created ) ) {
					update_option( 'spp_account_id_table', '1.1' );
				}
			}
		} catch ( \Exception $e ) {
			error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error creating database table: ' . $table_name );
		}

		$table_name = $table_prefix . BSD_SCSP_CONNECTED_ID_TABLE;
		try {
			if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) != $table_name ) {
				$sql = 'CREATE TABLE IF NOT EXISTS ' . $table_name . " (
                        bsd_connected_id bigint(20) NOT NULL AUTO_INCREMENT,
                        bsd_connected_account_id varchar(55) DEFAULT '' NOT NULL,
                        bsd_spscwt_type varchar(50) DEFAULT '' NOT NULL,
                        bsd_spscwt_percentage_amount double DEFAULT 0 NOT NULL,
                        bsd_global_shipping_type varchar(50) DEFAULT '' NOT NULL,
                        bsd_global_shipping_percentage_amount double DEFAULT 0 NOT NULL,
                        PRIMARY KEY  (bsd_connected_id)
                    ) $charset_collate;";

				$is_table_created = dbDelta( $sql );
				if ( ! empty( $is_table_created ) ) {
					update_option( 'bsd_connected_id_table_created', '1.1' );
				}
			}
		} catch ( \Exception $e ) {
			error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error creating database table: ' . $table_name );
		}

		$woocommerce_stripe_settings = get_option( 'woocommerce_stripe_settings' );

		if ( isset( $woocommerce_stripe_settings['enabled'] ) && $woocommerce_stripe_settings['enabled'] == 'yes' ) {
			$testmode = '';
			if ( isset( $woocommerce_stripe_settings['testmode'] ) ) {
				$testmode = $woocommerce_stripe_settings['testmode'];
			}

			$stripe_test_api_public_key = get_option( 'stripe_test_api_public_key', false );
			$stripe_test_api_secret_key = get_option( 'stripe_test_api_secret_key', false );

			$stripe_api_public_key = get_option( 'stripe_api_public_key', false );
			$stripe_api_secret_key = get_option( 'stripe_api_secret_key', false );
			error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] testmode: ' . print_r( $testmode, true ) );
			if ( $testmode == 'yes' ) {
				if ( ( empty( $stripe_test_api_public_key ) && empty( $stripe_test_api_secret_key ) ) ) {
					error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] testmode no keys found' );
					add_option( 'spp_setting_redirect', '1' );
				} else {
					error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] spp_plugin_wc_stripe_setting_redirect' );
					add_option( 'spp_setting_redirect', '2' );
				}
			} elseif ( ( empty( $stripe_api_public_key ) && empty( $stripe_api_secret_key ) ) ) {
				error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] live no keys found' );
				add_option( 'spp_setting_redirect', '1' );
			} else {
				add_option( 'spp_setting_redirect', '2' );
			}
		}
	}
}
