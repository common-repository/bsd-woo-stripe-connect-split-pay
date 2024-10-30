<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! class_exists( 'BSD_Split_Pay_Stripe_Connect_Woo_Plugin_Updates' ) ) :

	class BSD_Split_Pay_Stripe_Connect_Woo_Plugin_Updates {

		private $option;

		private $options = [];

		public function __construct() {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			$db_option  = get_option( 'bsd_split_pay_stripe_connect_woo_back_end' );
			$db_version = null;

			if ( $db_option ) {
				$db_option  = maybe_unserialize( $db_option );
				$db_version = isset( $db_option['db_version'] ) ? $db_option['db_version'] : null;
			}

			// Get legacy options - Added in v2.6
			$legacy_option = null;
			if ( ! $db_version || $db_version == '0.0.0' ) {
				$legacy_option = get_option( 'bsd_wc_scsp_db_version' );
			}

			if ( $legacy_option ) {
				$this->options['db_version'] = $legacy_option;
			} else {
				$this->options['db_version'] = $db_version;
			}
		}

		public function bsd_scsp_check_for_db_updates() {
			$db_version = null;

			if ( ! $this->options['db_version'] ) {
				$db_version                  = '0.0.0';
				$this->options['db_version'] = $db_version;

				update_option( 'bsd_split_pay_stripe_connect_woo_back_end', $this->options );
			} else {
				$db_version = $this->options['db_version'];
			}

			$this->db_update_controller( $db_version );
		}

		private function update_db_version( $new_version ) {
			try {
				$this->options['db_version'] = $new_version;
				update_option( 'bsd_split_pay_stripe_connect_woo_back_end', $this->options );

				return true;
			} catch ( Exception $e ) {
				error_log( '[BSD Split Pay for Stripe Connect on Woo] Error updating database version.' );

				return false;
			}
		}

		private function db_update_controller( $db_version ) {
			global $wpdb;
			$table_prefix = $wpdb->prefix;

			if ( version_compare( $db_version, '1.0.0', '<' ) ) {
				include 'migrations/migration-100.php';

				if ( true === db_migration_100( $table_prefix ) ) {
					$this->update_db_version( '1.0.0' );
				} else {
					return;
				}
			}

			if ( version_compare( $db_version, '2.0.0', '<' ) ) {
				include 'migrations/migration-200.php';

				if ( true === db_migration_200( $table_prefix ) ) {
					$this->update_db_version( '2.0.0' );
				} else {
					return;
				}
			}

			// Insert next migration
		}

	}

endif; // class_exists check