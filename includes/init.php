<?php
/**
 * Check for db updates.
 */
namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc;

if ( ! function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\Inc\bsd_scsp_db_update' ) ) {
	/**
	 * @return void
	 */
	function bsd_scsp_db_update() {
		$bsd_split_pay_stripe_connect_woo_back_end = get_option( 'bsd_split_pay_stripe_connect_woo_back_end' );

		// Get legacy option - Added in v2.6.
		$old_option = get_option( 'bsd_split_pay_stripe_connect_woo_back_end' );

		if ( $old_option ) {
			$bsd_split_pay_stripe_connect_woo_back_end = $old_option;
		}


		if ( ! $bsd_split_pay_stripe_connect_woo_back_end ) {
			$opts = [
				'db_version' => '0.0.0',
			];

			add_option( 'bsd_split_pay_stripe_connect_woo_back_end', $opts );
		}

		if ( ! class_exists( 'BSD_Split_Pay_Stripe_Connect_Woo_Plugin_Updates' ) ) {
			require_once 'admin/plugin-updates/class-db-updates.php';
		}

		$bsd_scsp_plugin_updates = new Admin\BSD_Split_Pay_Stripe_Connect_Woo_Plugin_Updates();
		$bsd_scsp_plugin_updates->bsd_scsp_check_for_db_updates();
	}
}