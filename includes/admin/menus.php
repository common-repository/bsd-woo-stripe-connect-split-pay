<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin;

if ( ! function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin\bsd_scsp_admin_menus' ) ) {
	function bsd_scsp_admin_menus() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Split Pay Plugin', 'bsd-split-pay-stripe-connect-woo' ),
			esc_html__( 'Split Pay Plugin', 'bsd-split-pay-stripe-connect-woo' ),
			'edit_theme_options',
			'bsd-split-pay-stripe-connect-woo-settings',
			__NAMESPACE__ . '\\bsd_scsp_options'
		);
	}
}