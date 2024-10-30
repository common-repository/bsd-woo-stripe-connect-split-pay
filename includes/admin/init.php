<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin;

if ( ! function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin\bsd_scsp_admin_init' ) ) {
	function bsd_scsp_admin_init() {
		include( 'enqueue.php' );

		add_action( 'admin_enqueue_scripts', 'BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin\bsd_scsp_admin_enqueue' );

		bsd_scsp_settings_api();
	}
}