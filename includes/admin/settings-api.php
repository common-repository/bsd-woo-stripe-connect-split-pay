<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin;

if ( ! function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin\bsd_scsp_settings_api' ) ) {
	function bsd_scsp_settings_api() {
		// BEGIN Replace legacy options - Added in v2.6
		$legacy_bsd_wc_scsp_stripe_connected_account = get_option( 'bsd_wc_scsp_stripe_connected_account' );
		$legacy_bsd_wc_scsp_transfer_percentage      = get_option( 'bsd_wc_scsp_transfer_percentage' );
		$legacy_bsd_wc_scsp_exclude_shipping         = get_option( 'bsd_wc_scsp_exclude_shipping' );
		$legacy_bsd_wc_scsp_exclude_tax              = get_option( 'bsd_wc_scsp_exclude_tax' );

		if ( $legacy_bsd_wc_scsp_stripe_connected_account ) {
			update_option( 'bsd_split_pay_stripe_connect_woo_stripe_connected_account', $legacy_bsd_wc_scsp_stripe_connected_account );
			delete_option( 'bsd_wc_scsp_stripe_connected_account' );
		}

		if ( $legacy_bsd_wc_scsp_transfer_percentage ) {
			update_option( 'bsd_split_pay_stripe_connect_woo_transfer_percentage', $legacy_bsd_wc_scsp_transfer_percentage );
			delete_option( 'bsd_wc_scsp_transfer_percentage' );
		}

		if ( $legacy_bsd_wc_scsp_exclude_shipping ) {
			$value = $legacy_bsd_wc_scsp_exclude_shipping == 'yes' ? true : false;
			update_option( 'bsd_split_pay_stripe_connect_woo_exclude_shipping', $value );
			delete_option( 'bsd_wc_scsp_exclude_shipping' );
		}

		if ( $legacy_bsd_wc_scsp_exclude_tax ) {
			$value = $legacy_bsd_wc_scsp_exclude_tax == 'yes' ? true : false;
			update_option( 'bsd_split_pay_stripe_connect_woo_exclude_tax', $value );
			delete_option( 'bsd_wc_scsp_exclude_tax' );
		}
		// END Replace legacy options

		// BEGIN Delete legacy Exclude Shipping and Exclude Tax settings - Added in 3.2
		delete_option( 'bsd_split_pay_stripe_connect_woo_exclude_shipping' );
		delete_option( 'bsd_split_pay_stripe_connect_woo_exclude_tax' );

		register_setting(
			'bsd_scsp_options_main',
			'bsd_trnsfr_shpng_fees',
			array(
				'type'              => 'boolean',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'spp_vendor_options',
			'vendor_onboading',
			array(
				'type'              => 'boolean',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'spp_vendor_options',
			'enable_title_description',
			array(
				'type'              => 'boolean',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'spp_vendor_options',
			'onboarding_title',
			array(
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'spp_vendor_options',
			'onboarding_description',
			array(
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'spp_stripe_configuration_options',
			'stripe_test_api_public_key',
			array(
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'spp_stripe_configuration_options',
			'stripe_test_api_secret_key',
			array(
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'spp_stripe_configuration_options',
			'stripe_api_public_key',
			array(
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'spp_stripe_configuration_options',
			'stripe_api_secret_key',
			array(
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}
}


if ( ! function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin\bsd_scsp_settings_int_sanitize' ) ) {
	function bsd_scsp_settings_int_sanitize( $input ) {
		if ( is_array( $input ) ) {
			$input['quantity'] = absint( $input['quantity'] );

			if ( $input['quantity'] > 100 ) {
				$input['quantity'] = 100;
			}
		} else {
			$input = floatval( $input );

			if ( $input > 100 ) {
				$input = 100;
			}
		}

		return $input;
	}
}
