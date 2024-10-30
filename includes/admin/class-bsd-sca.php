<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin;

use Exception;
use WP_Query;
if ( !defined( 'ABSPATH' ) ) {
    exit( 'Sorry!' );
}
// Exit if accessed directly
require_once BSD_SCSP_PLUGIN_DIR . '/includes/vendor/autoload.php';
if ( !class_exists( 'namespace BSD_Split_Pay_Stripe_Connect_Woo\\Inc\\Admin;
\\BSD_SCA' ) ) {
    class BSD_SCA {
        public $log_prefix = '[BSD Split Pay for Stripe Connect on Woo] ';

        public function run() {
            add_action( 'admin_init', array($this, 'bsd_connect_standard_account') );
            // add_action( 'admin_init', array( $this, 'spp_no_keys_redirect_rule' ) );
            add_action( 'admin_init', array($this, 'spp_redirect_to_settings') );
            add_action( 'admin_init', array($this, 'get_initial_accounts') );
            add_action( 'wp_ajax_fetch_accounts', array($this, 'fetch_accounts') );
            add_action( 'wp_ajax_clear_accounts', array($this, 'clear_accounts') );
            add_action( 'wp_ajax_add_custom_account', array($this, 'add_custom_account') );
            add_action( 'wp_loaded', array($this, 'create_connect_id_table') );
            add_action( 'admin_init', array($this, 'store_selected_accounts'), 10 );
            add_action( 'admin_init', array($this, 'spp_vendor_options_process'), 12 );
            add_action( 'plugins_loaded', array($this, 'update_connected_id_table') );
            add_action( 'plugins_loaded', array($this, 'update_log_table') );
            add_action( 'plugins_loaded', array($this, 'update_account_id_table') );
            add_action( 'admin_notices', array($this, 'sps_admin_notices') );
            add_action( 'wp_ajax_fetch_more_filter', array($this, 'add_more_bulk_editor_filter') );
            add_action( 'wp_ajax_fetch_search_result', array($this, 'product_search_data') );
            add_action( 'wp_ajax_save_product_bulk_edit', array($this, 'save_product_bulk_edit_data') );
            $transfer_email = get_option( 'transfer_email', false );
            if ( $transfer_email == '1' ) {
                add_filter(
                    'wc_get_template',
                    array($this, 'sps_get_woocommerce_template'),
                    10,
                    5
                );
                add_filter( 'woocommerce_email_classes', array($this, 'add_transfer_success_order_woocommerce_email') );
            }
            // Use the woocommerce email template under this plugin directory.
            add_filter(
                'pre_update_option',
                array($this, 'filter_masked_key'),
                10,
                3
            );
            $stripe_test_api_public_key = get_option( 'stripe_test_api_public_key', false );
            $stripe_test_api_secret_key = get_option( 'stripe_test_api_secret_key', false );
            $stripe_api_public_key = get_option( 'stripe_api_public_key', false );
            $stripe_api_secret_key = get_option( 'stripe_api_secret_key', false );
            if ( empty( $stripe_test_api_public_key ) && empty( $stripe_test_api_secret_key ) || empty( $stripe_api_public_key ) && empty( $stripe_api_secret_key ) ) {
                add_action( 'admin_notices', array($this, 'stripe_key_notice_cb') );
            }
            add_action( 'wp_ajax_sync_webhooks', array($this, 'sync_webhooks') );
            add_action( 'plugins_loaded', array($this, 'required_plugin_update_things') );
        }

        public function required_plugin_update_things() {
            if ( defined( 'WC_STRIPE_VERSION' ) ) {
                if ( !version_compare( WC_STRIPE_VERSION, '8.6.0', '>=' ) ) {
                    $spp_keys_copied = get_option( 'spp_keys_copied', false );
                    $woocommerce_stripe_settings = get_option( 'woocommerce_stripe_settings' );
                    if ( empty( $spp_keys_copied ) && !empty( $woocommerce_stripe_settings ) ) {
                        if ( isset( $woocommerce_stripe_settings['enabled'] ) && $woocommerce_stripe_settings['enabled'] == 'yes' ) {
                            update_option( 'stripe_test_api_public_key', $woocommerce_stripe_settings['test_publishable_key'] );
                            update_option( 'stripe_test_api_secret_key', $woocommerce_stripe_settings['test_secret_key'] );
                            update_option( 'stripe_api_public_key', $woocommerce_stripe_settings['publishable_key'] );
                            update_option( 'stripe_api_secret_key', $woocommerce_stripe_settings['secret_key'] );
                            update_option( 'spp_keys_copied', '1' );
                        }
                    }
                }
            }
        }

        public function sync_webhooks() {
            $send_ajax_data = array(
                'success' => false,
                'message' => 'Something Wrong!!!',
                'data'    => array(),
            );
            if ( isset( $_POST['security'] ) && wp_verify_nonce( $_POST['security'], 'ajax-security' ) ) {
                $woocommerce_stripe_settings = get_option( 'woocommerce_stripe_settings' );
                $errors = array();
                $successes = array();
                if ( isset( $woocommerce_stripe_settings['enabled'] ) && $woocommerce_stripe_settings['enabled'] == 'yes' ) {
                    $test_webhook_data = array();
                    $webhook_data = array();
                    if ( !empty( $woocommerce_stripe_settings['test_webhook_data'] ) ) {
                        $test_webhook_data = $woocommerce_stripe_settings['test_webhook_data'];
                        $test_secret_key = $woocommerce_stripe_settings['test_secret_key'];
                        if ( isset( $test_webhook_data['id'] ) && !empty( $test_webhook_data['id'] ) ) {
                            $stripe_key = get_option( 'stripe_test_api_secret_key', false );
                            try {
                                $wc_plugin_stripe = new \Stripe\StripeClient($test_secret_key);
                                $wc_plugin_account_obj = $wc_plugin_stripe->accounts->retrieve( null, array() );
                                $our_stripe_obj = new \Stripe\StripeClient($stripe_key);
                                $our_account_obj = $our_stripe_obj->accounts->retrieve( null, array() );
                                if ( $wc_plugin_account_obj && $our_account_obj ) {
                                    if ( $wc_plugin_account_obj->id != $our_account_obj->id ) {
                                        $woo_stripe_settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' );
                                        $errors['test_mode'][] = __( 'API keys do not match the account connected to the <a href="' . $woo_stripe_settings_url . '" target="_blank">WooCommerce Stripe Payment Gateway plugin</a>.', 'bsd-split-pay-stripe-connect-woo' );
                                        update_option( 'sps_test_webhook_update', '2' );
                                    }
                                }
                            } catch ( \Exception $e ) {
                                $error_message = $e->getMessage();
                                \WC_Stripe_Logger::log( $this->log_prefix . ': error_message ' . print_r( $error_message, true ) );
                                $errors['test_mode'][] = $error_message;
                            }
                            if ( !isset( $errors['test_mode'][0] ) ) {
                                try {
                                    $stripe = new \Stripe\StripeClient($stripe_key);
                                    $existing_webhook_response = $stripe->webhookEndpoints->retrieve( $test_webhook_data['id'], array() );
                                    $merged_events = array();
                                    if ( $existing_webhook_response ) {
                                        $existing_events = $existing_webhook_response->enabled_events;
                                        $created_exists = array_search( 'transfer.created', $existing_events );
                                        if ( $created_exists ) {
                                            unset($existing_events[$created_exists]);
                                        }
                                        $reversed_exists = array_search( 'transfer.reversed', $existing_events );
                                        if ( $reversed_exists ) {
                                            unset($existing_events[$reversed_exists]);
                                        }
                                        $updated_exists = array_search( 'transfer.updated', $existing_events );
                                        if ( $updated_exists ) {
                                            unset($existing_events[$updated_exists]);
                                        }
                                        $merged_events = array_merge( $existing_events, array('transfer.created', 'transfer.reversed', 'transfer.updated') );
                                    }
                                    $updated_response = $stripe->webhookEndpoints->update( $test_webhook_data['id'], array(
                                        'enabled_events' => $merged_events,
                                    ) );
                                    if ( $updated_response ) {
                                        $successes['test_mode'][] = $updated_response;
                                        update_option( 'sps_test_webhook_update', '1' );
                                    }
                                } catch ( \Exception $e ) {
                                    $error_message = $e->getMessage();
                                    \WC_Stripe_Logger::log( $this->log_prefix . ': error_message ' . print_r( $error_message, true ) );
                                    $errors['test_mode'][] = $error_message;
                                    update_option( 'sps_test_webhook_update', '2' );
                                }
                            }
                        }
                    }
                    if ( isset( $woocommerce_stripe_settings['webhook_data'] ) && !empty( $woocommerce_stripe_settings['webhook_data'] ) ) {
                        $webhook_data = $woocommerce_stripe_settings['webhook_data'];
                        $wc_stripe_secret_key = $woocommerce_stripe_settings['secret_key'];
                        if ( isset( $webhook_data['id'] ) && !empty( $webhook_data['id'] ) ) {
                            $stripe_key = get_option( 'stripe_api_secret_key', false );
                        }
                        try {
                            $wc_plugin_stripe = new \Stripe\StripeClient($wc_stripe_secret_key);
                            $wc_plugin_account_obj = $wc_plugin_stripe->accounts->retrieve( null, array() );
                            $our_stripe_obj = new \Stripe\StripeClient($stripe_key);
                            $our_account_obj = $our_stripe_obj->accounts->retrieve( null, array() );
                            if ( $wc_plugin_account_obj && $our_account_obj ) {
                                if ( $wc_plugin_account_obj->id != $our_account_obj->id ) {
                                    $woo_stripe_settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' );
                                    $errors['live_mode'][] = __( 'API keys do not match the account connected to the <a href="' . $woo_stripe_settings_url . '" target="_blank">WooCommerce Stripe Payment Gateway plugin</a>.', 'bsd-split-pay-stripe-connect-woo' );
                                    update_option( 'sps_webhook_update', '2' );
                                }
                            }
                        } catch ( \Exception $e ) {
                            $error_message = $e->getMessage();
                            \WC_Stripe_Logger::log( $this->log_prefix . ': error_message ' . print_r( $error_message, true ) );
                            $errors['live_mode'][] = $error_message;
                            update_option( 'sps_webhook_update', '2' );
                        }
                        if ( !isset( $errors['live_mode'][0] ) ) {
                            try {
                                $stripe = new \Stripe\StripeClient($stripe_key);
                                $existing_webhook_response = $stripe->webhookEndpoints->retrieve( $webhook_data['id'], array() );
                                $merged_events = array();
                                if ( $existing_webhook_response ) {
                                    $existing_events = $existing_webhook_response->enabled_events;
                                    $created_exists = array_search( 'transfer.created', $existing_events );
                                    if ( $created_exists ) {
                                        unset($existing_events[$created_exists]);
                                    }
                                    $reversed_exists = array_search( 'transfer.reversed', $existing_events );
                                    if ( $reversed_exists ) {
                                        unset($existing_events[$reversed_exists]);
                                    }
                                    $updated_exists = array_search( 'transfer.updated', $existing_events );
                                    if ( $updated_exists ) {
                                        unset($existing_events[$updated_exists]);
                                    }
                                    $merged_events = array_merge( $existing_events, array('transfer.created', 'transfer.reversed', 'transfer.updated') );
                                }
                                $updated_response = $stripe->webhookEndpoints->update( $webhook_data['id'], array(
                                    'enabled_events' => $merged_events,
                                ) );
                                if ( $updated_response ) {
                                    $successes['live_mode'][] = $updated_response;
                                    update_option( 'sps_webhook_update', '1' );
                                }
                            } catch ( \Exception $e ) {
                                $error_message = $e->getMessage();
                                \WC_Stripe_Logger::log( $this->log_prefix . ': error_message ' . print_r( $error_message, true ) );
                                $errors['live_mode'][] = $error_message;
                                update_option( 'sps_webhook_update', '2' );
                            }
                        }
                    }
                }
            }
            $send_ajax_data = array(
                'success' => true,
                'message' => '',
                'data'    => array(
                    'error_messages' => $errors,
                    'successes'      => $successes,
                ),
            );
            wp_send_json( $send_ajax_data );
            die;
        }

        public function stripe_key_notice_cb() {
            $woocommerce_stripe_settings = get_option( 'woocommerce_stripe_settings' );
            if ( isset( $woocommerce_stripe_settings['enabled'] ) && $woocommerce_stripe_settings['enabled'] == 'yes' ) {
                $test_mode = $woocommerce_stripe_settings['testmode'];
                if ( $test_mode == 'yes' ) {
                    $stripe_api_public_key = get_option( 'stripe_test_api_public_key', false );
                    $stripe_api_secret_key = get_option( 'stripe_test_api_secret_key', false );
                } else {
                    $stripe_api_public_key = get_option( 'stripe_api_public_key', false );
                    $stripe_api_secret_key = get_option( 'stripe_api_secret_key', false );
                }
                if ( empty( $stripe_api_public_key ) && empty( $stripe_api_secret_key ) ) {
                    ?>
						<div class="notice notice-warning">
							<p><?php 
                    _e( '<b>Split Pay Plugin</b>', 'bsd-split-pay-stripe-connect-woo' );
                    ?></p>
							<p>
							<?php 
                    $tab_url = admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-settings&tab=stripe-configuration' );
                    _e( ' In order for Transfers to work, please <a href="' . $tab_url . '">configure your Stripe API keys.</a> Read our <a href="https://docs.splitpayplugin.com/getting-started/quick-start" target="_blank">documentation</a> for details.', 'bsd-split-pay-stripe-connect-woo' );
                    ?></p>
						</div>
					<?php 
                }
            }
        }

        public function filter_masked_key( $value, $option, $old_value ) {
            if ( $option == 'stripe_test_api_public_key' || $option == 'stripe_test_api_secret_key' || $option == 'stripe_api_public_key' || $option == 'stripe_api_secret_key' ) {
                if ( strpos( $value, '******' ) !== false ) {
                    return $old_value;
                }
            }
            return $value;
        }

        public function spp_no_keys_redirect_rule() {
            if ( isset( $_GET['page'] ) && $_GET['page'] == 'bsd-split-pay-stripe-connect-woo-settings' && isset( $_GET['tab'] ) && $_GET['tab'] != 'stripe-configuration' ) {
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
                    if ( $testmode == 'yes' ) {
                        if ( empty( $stripe_test_api_public_key ) && empty( $stripe_test_api_secret_key ) ) {
                            wp_safe_redirect( admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-settings&tab=stripe-configuration' ) );
                            die;
                        }
                    } elseif ( empty( $stripe_api_public_key ) && empty( $stripe_api_secret_key ) ) {
                        wp_safe_redirect( admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-settings&tab=stripe-configuration' ) );
                        die;
                    }
                }
            }
        }

        public function spp_redirect_to_settings() {
            $spp_setting_redirect = get_option( 'spp_setting_redirect', false );
            error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] spp_setting_redirect' . print_r( $spp_setting_redirect, true ) );
            if ( $spp_setting_redirect == '1' ) {
                delete_option( 'spp_setting_redirect' );
                wp_safe_redirect( admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-settings&tab=stripe-configuration' ) );
                die;
            }
            if ( $spp_setting_redirect == '2' ) {
                delete_option( 'spp_setting_redirect' );
                $woo_stripe_settings_url = admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-settings&tab=main' );
                wp_safe_redirect( $woo_stripe_settings_url );
                die;
            }
        }

        /**
         * WC email classes filter callback.
         * Transfer orde email class added in email list.
         */
        public function add_transfer_success_order_woocommerce_email( $email_classes ) {
            require BSD_SCSP_PLUGIN_DIR . '/includes/admin/class-wc-transfer-order-email.php';
            // add the email class to the list of email classes that WooCommerce loads
            $email_classes['WC_Transfer_Order_Email'] = new WC_Transfer_Order_Email();
            return $email_classes;
        }

        /**
         * Change path for transfer email templates.
         */
        public function sps_get_woocommerce_template(
            $located,
            $template_name,
            $args,
            $template_path,
            $default_path
        ) {
            $plugin_dir_path = BSD_SCSP_PLUGIN_DIR . '/wc-templates/';
            if ( file_exists( $plugin_dir_path . $template_name ) ) {
                if ( isset( $args['email']->id ) && $args['email']->id == 'wc_tarnsfer_order' ) {
                    $located = $plugin_dir_path . $template_name;
                    \WC_Stripe_Logger::log( ': sps_get_woocommerce_template-located-email ' . print_r( $located, true ) );
                }
                if ( isset( $args['email_obj']->id ) && $args['email_obj']->id == 'wc_tarnsfer_order' ) {
                    $located = $plugin_dir_path . $template_name;
                    \WC_Stripe_Logger::log( ': sps_get_woocommerce_template-located-email_obj ' . print_r( $located, true ) );
                }
            }
            return $located;
        }

        public function save_product_bulk_edit_data() {
            if ( isset( $_POST['security'] ) && wp_verify_nonce( $_POST['security'], 'ajax-security' ) ) {
                if ( isset( $_POST['_bsd_spscwt_product_connected_account'] ) && !empty( $_POST['_bsd_spscwt_product_connected_account'] ) ) {
                    foreach ( $_POST['_bsd_spscwt_product_connected_account'] as $prod_id => $value_arr ) {
                        $product_type = $_POST['product_type'][$prod_id];
                        $_bsd_spscwt_product_type = $_POST['_bsd_spscwt_product_type'][$prod_id];
                        $transfer_percentage = $_POST['_stripe_connect_split_pay_transfer_percentage'][$prod_id];
                        $_bsd_spscwt_product_amount = $_POST['_bsd_spscwt_product_amount'][$prod_id];
                        /* Shipping */
                        $_bsd_spscwt_shipping_type = $_POST['bsd_spscwt_shipping_type'][$prod_id];
                        $transfer_shipping_percentage = $_POST['bsd_prod_shipping_percentage'][$prod_id];
                        $_bsd_spscwt_shipping_amount = $_POST['bsd_prod_shipping_amount'][$prod_id];
                        if ( !empty( $value_arr ) ) {
                            $prod_valid_connected_account = array();
                            foreach ( $value_arr as $vak => $vav ) {
                                /*
                                								if ( ! empty( $vav ) && $_bsd_spscwt_product_type[ $vak ] === 'percentage' && ! empty( $transfer_percentage[ $vak ] ) ) {
                                									$prod_valid_connected_account[ $vak ] = sanitize_text_field( $vav );
                                								} elseif ( ! empty( $vav ) && $_bsd_spscwt_product_type[ $vak ] === 'amount' && ! empty( $_bsd_spscwt_product_amount[ $vak ] ) ) {
                                									$prod_valid_connected_account[ $vak ] = sanitize_text_field( $vav );
                                								} */
                                $prod_valid_connected_account[$vak] = sanitize_text_field( $vav );
                                if ( isset( $transfer_percentage[$vak] ) && !empty( $transfer_percentage[$vak] ) ) {
                                    $transfer_percentage[$vak] = sanitize_text_field( $transfer_percentage[$vak] );
                                } elseif ( isset( $transfer_percentage[$vak] ) && empty( $transfer_percentage[$vak] ) ) {
                                    $transfer_percentage[$vak] = sanitize_text_field( $_bsd_spscwt_product_amount[$vak] );
                                }
                                if ( isset( $_bsd_spscwt_product_amount[$vak] ) && !empty( $_bsd_spscwt_product_amount[$vak] ) ) {
                                    $_bsd_spscwt_product_amount[$vak] = sanitize_text_field( $_bsd_spscwt_product_amount[$vak] );
                                } elseif ( isset( $_bsd_spscwt_product_amount[$vak] ) && empty( $_bsd_spscwt_product_amount[$vak] ) ) {
                                    $_bsd_spscwt_product_amount[$vak] = sanitize_text_field( $transfer_percentage[$vak] );
                                }
                                if ( isset( $transfer_shipping_percentage[$vak] ) && !empty( $transfer_shipping_percentage[$vak] ) ) {
                                    $transfer_shipping_percentage[$vak] = sanitize_text_field( $transfer_shipping_percentage[$vak] );
                                } elseif ( isset( $transfer_shipping_percentage[$vak] ) && empty( $transfer_shipping_percentage[$vak] ) ) {
                                    $transfer_shipping_percentage[$vak] = sanitize_text_field( $_bsd_spscwt_shipping_amount[$vak] );
                                }
                                if ( isset( $_bsd_spscwt_shipping_amount[$vak] ) && !empty( $_bsd_spscwt_shipping_amount[$vak] ) ) {
                                    $_bsd_spscwt_shipping_amount[$vak] = sanitize_text_field( $_bsd_spscwt_shipping_amount[$vak] );
                                } elseif ( isset( $_bsd_spscwt_shipping_amount[$vak] ) && empty( $_bsd_spscwt_shipping_amount[$vak] ) ) {
                                    $_bsd_spscwt_shipping_amount[$vak] = sanitize_text_field( $transfer_shipping_percentage[$vak] );
                                }
                            }
                            if ( !empty( $prod_valid_connected_account ) ) {
                                update_post_meta( $prod_id, '_bsd_spscwt_product_connected_account', $prod_valid_connected_account );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_type', $_bsd_spscwt_product_type );
                                update_post_meta( $prod_id, '_stripe_connect_split_pay_transfer_percentage', $transfer_percentage );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_amount', $_bsd_spscwt_product_amount );
                                update_post_meta( $prod_id, '_bsd_spscwt_shipping_type', $_bsd_spscwt_shipping_type );
                                update_post_meta( $prod_id, '_bsd_prod_shipping_percentage', $transfer_shipping_percentage );
                                if ( $product_type == 'simple' ) {
                                    update_post_meta( $prod_id, '_bsd_spscwt_shipping_amount', $_bsd_spscwt_shipping_amount );
                                } elseif ( $product_type == 'variable' ) {
                                    update_post_meta( $prod_id, '_bsd_prod_shipping_amount', $_bsd_spscwt_shipping_amount );
                                }
                            }
                        }
                    }
                }
            }
            wp_send_json( array(
                'success' => true,
            ) );
            die;
        }

        public function add_more_bulk_editor_filter() {
            $data_id = $_POST['count'] + 1;
            $args = array(
                'taxonomy'   => 'product_cat',
                'orderby'    => 'name',
                'order'      => 'ASC',
                'hide_empty' => false,
            );
            $product_categories = get_terms( $args );
            $html = '';
            ob_start();
            ?>
			<div class="find-product-list__row more-<?php 
            echo $data_id;
            ?>">
				<div class="row">
					<div class="find-product-list__col find-product-list__col_1">
						<select name="filter_name[]" class="pwbe-filter-field pwbe-filter-name filter-name" data-id="<?php 
            echo $data_id;
            ?>">
							<option value="categories" data-type="categories"><?php 
            echo esc_html( 'Category', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
							<option value="post_title" data-type="string"><?php 
            echo esc_html( 'Product name', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
							<option value="sku" data-type="string"><?php 
            echo esc_html( 'SKU', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
						</select>
					</div>

					<div class="find-product-list__col find-product-list__col_2 category-fields category-fields-<?php 
            echo $data_id;
            ?>">
						<select name="filter_cat_type[]" class="pwbe-filter-field pwbe-filter-type filter-cat-type" data-id="<?php 
            echo $data_id;
            ?>">
							<option value="is any of"><?php 
            echo esc_html( 'is any of', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
							<option value="is none of"><?php 
            echo esc_html( 'is none of', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
						</select>
					</div>

					<div class="find-product-list__col find-product-list__col_2 product-fields product-fields-<?php 
            echo $data_id;
            ?>">
						<select name="filter_product_type[]" class="pwbe-filter-field pwbe-filter-type filter-product-type" data-id="<?php 
            echo $data_id;
            ?>">
							<option value="contains"><?php 
            echo esc_html( 'contains', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
							<option value="does not contain"><?php 
            echo esc_html( 'does not contain', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
							<option value="is"><?php 
            echo esc_html( 'is', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
							<option value="is not"><?php 
            echo esc_html( 'is not', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
							<option value="begins with"><?php 
            echo esc_html( 'begins with', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
							<option value="ends with"><?php 
            echo esc_html( 'ends with', 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
						</select>
					</div>

					<div class="find-product-list__col find-product-list__col_2 product-fields product-fields-<?php 
            echo $data_id;
            ?>">
						<input type="text" name="search_text[]" value="" data-id="<?php 
            echo $data_id;
            ?>" size="40" class="pro-filter-input" />
					</div>

					<?php 
            if ( isset( $product_categories ) && !empty( $product_categories ) ) {
                ?>
						<div class="find-product-list__col find-product-list__col_3 category-fields category-fields-<?php 
                echo $data_id;
                ?>">
							<select name="filter_categories[]" multiple="true" class="pwbe-filter-field pwbe-filter-name pwbe-filter-select filter-categories" data-id="<?php 
                echo $data_id;
                ?>">
								<?php 
                foreach ( $product_categories as $terms ) {
                    ?>
									<option value="<?php 
                    echo $terms->term_id;
                    ?>"><?php 
                    echo $terms->name;
                    ?></option>
								<?php 
                }
                ?>
							</select>
						</div>
					<?php 
            }
            ?>
					<div class="find-product-list__btns-pls-mns">
						<button class="find-plus add_more" data-id="<?php 
            echo $data_id;
            ?>">+</button>
						<button class="find-minus remove_more" data-id="<?php 
            echo $data_id;
            ?>">-</button>
					</div>
					<div class="find-product-list__btns">
						<button class="btn-link add_more" data-id="<?php 
            echo $data_id;
            ?>">
							+ <?php 
            echo esc_html( 'Add a filter', 'bsd-split-pay-stripe-connect-woo' );
            ?></button>
						<button class="btn-link remove_more" data-id="<?php 
            echo $data_id;
            ?>">
							- <?php 
            echo esc_html( 'Remove', 'bsd-split-pay-stripe-connect-woo' );
            ?></button>
					</div>
				</div>
			</div>
			<?php 
            $html .= ob_get_contents();
            ob_end_clean();
            wp_send_json_success( $html );
            die;
        }

        public function product_search_data() {
            if ( isset( $_POST['security'] ) && wp_verify_nonce( $_POST['security'], 'ajax-security' ) ) {
                require_once BSD_SCSP_PLUGIN_DIR . '/includes/admin/partials/spp-product-serach-data-html.php';
            }
        }

        public function get_product_title_sku_result( $pro_query = array() ) {
            global $wpdb;
            $common_where = '';
            $products_ids = $results = array();
            if ( !empty( $pro_query ) ) {
                foreach ( $pro_query as $key => $value ) {
                    if ( $value['title'] ) {
                        $field_name = 'p.post_title';
                    }
                    if ( $value['sku'] ) {
                        $field_name = 'pm.meta_key = "_sku" AND pm.meta_value';
                    }
                    foreach ( $value as $key2 => $data ) {
                        $value = $data['value'];
                        switch ( $data['condition'] ) {
                            case 'is':
                                $common_where .= " AND {$field_name} = '{$value}'";
                                break;
                            case 'is not':
                                $common_where .= " AND {$field_name} != '{$value}'";
                                break;
                            case 'contains':
                                $common_where .= " AND {$field_name} LIKE '%" . str_replace( '_', '\\_', $value ) . "%'";
                                break;
                            case 'does not contain':
                                $common_where .= " AND {$field_name} NOT LIKE '%" . str_replace( '_', '\\_', $value ) . "%'";
                                break;
                            case 'begins with':
                                $common_where .= " AND {$field_name} LIKE '" . str_replace( '_', '\\_', $value ) . "%'";
                                break;
                            case 'ends with':
                                $common_where .= " AND {$field_name} LIKE '%" . str_replace( '_', '\\_', $value ) . "'";
                                break;
                        }
                    }
                }
            }
            $query = "SELECT DISTINCT(p.ID) FROM {$wpdb->posts} p\n            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id\n            WHERE p.post_type = 'product' AND p.post_status = 'publish' {$common_where} ";
            $products_ids = $wpdb->get_results( $wpdb->prepare( $query ), ARRAY_A );
            foreach ( $products_ids as $id ) {
                $results[] = $id['ID'];
            }
            return $results;
        }

        public function sps_admin_notices() {
            if ( isset( $_GET['msg'] ) && $_GET['msg'] == 'ob_failed' ) {
                $stored_ob_failed = get_transient( 'ob_failed' );
                if ( !empty( $stored_ob_failed ) ) {
                    ?>
					<div class="notice notice-warning is-dismissible">
						<p><?php 
                    echo $stored_ob_failed;
                    ?> </p>
					</div>
					<?php 
                } else {
                    ?>
					<div class="notice notice-warning is-dismissible">
						<p>Account failed to connect. Please contact the site administrator for details. </p>
					</div>
					<?php 
                }
            }
            if ( isset( $_GET['msg'] ) && $_GET['msg'] == 'ob_success' ) {
                ?>
				<div class="notice notice-success is-dismissible">
					<p>Account successfully connected. </p>
				</div>
				<?php 
            }
        }

        public function sps_custom_menus() {
            if ( current_user_can( 'vendor_split_pay_plugin' ) && !current_user_can( 'administrator' ) ) {
                add_menu_page(
                    __( 'Stripe Connect', 'bsd-split-pay-stripe-connect-woo' ),
                    __( 'Stripe Connect', 'bsd-split-pay-stripe-connect-woo' ),
                    'spp_custom_menu',
                    'split-pay-stripe-connect',
                    array($this, 'sps_connect_menu_page_cb'),
                    'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" height="10" width="10" viewBox="0 0 384 512"><path fill="#ffffff" d="M155.3 154.6c0-22.3 18.6-30.9 48.4-30.9 43.4 0 98.5 13.3 141.9 36.7V26.1C298.3 7.2 251.1 0 203.8 0 88.1 0 11 60.4 11 161.4c0 157.9 216.8 132.3 216.8 200.4 0 26.4-22.9 34.9-54.7 34.9-47.2 0-108.2-19.5-156.1-45.5v128.5a396.1 396.1 0 0 0 156 32.4c118.6 0 200.3-51 200.3-153.6 0-170.2-218-139.7-218-203.9z"/></svg>' )
                );
                remove_menu_page( 'index.php' );
                remove_menu_page( 'profile.php' );
            }
        }

        public function sps_connect_menu_page_cb() {
            require_once BSD_SCSP_PLUGIN_DIR . '/includes/admin/partials/sps-connect-menu-page-html.php';
        }

        public function sps_woocommerce_disable_admin_bar( $show ) {
            if ( wc_current_user_has_role( 'vendor_split_pay_plugin' ) ) {
                $show = false;
            }
            return $show;
        }

        public function sps_woocommerce_login_redirect( $redirect, $user ) {
            $roles = $user->roles;
            $dashboard = admin_url( '/admin.php?page=split-pay-stripe-connect' );
            if ( in_array( 'vendor_split_pay_plugin', $roles ) ) {
                // Redirect administrators to the dashboard
                $redirect = $dashboard;
            }
            return $redirect;
        }

        public function sps_login_redirect( $redirect, $requested_redirect_to, $user ) {
            if ( !is_wp_error( $user ) ) {
                $roles = $user->roles;
                $dashboard = admin_url( '/admin.php?page=split-pay-stripe-connect' );
                if ( in_array( 'vendor_split_pay_plugin', $roles ) ) {
                    $redirect = $dashboard;
                }
            }
            return $redirect;
        }

        public function sps_add_roles() {
            $capabilities = array(
                'read'            => true,
                'level_0'         => true,
                'spp_custom_menu' => true,
            );
            add_role( 'vendor_split_pay_plugin', esc_html__( 'Vendor (Split Pay Plugin)', 'bsd-split-pay-stripe-connect-woo' ), $capabilities );
        }

        public function sps_register_form_fields() {
            ?>
			<p class="rvspp_wrapper">
				<input name="register_vendor_split_pay_plugin" type="checkbox" id="register_vendor_split_pay_plugin" value="1">
				<label for="register_vendor_split_pay_plugin">Register as a Vendor</label>
			</p>
			<style>
				.rvspp_wrapper {
					width: 100%;
				}
			</style>
			<?php 
        }

        public function sps_save_register_fields( $user_id ) {
            if ( isset( $_POST['register_vendor_split_pay_plugin'] ) ) {
                update_user_meta( $user_id, '_register_vendor_split_pay_plugin', $_POST['register_vendor_split_pay_plugin'] );
                $u = new \WP_User($user_id);
                // Remove role
                $u->remove_role( 'subscriber' );
                // Add role
                $u->add_role( 'vendor_split_pay_plugin' );
            }
        }

        private function stp_retrieve_keys() {
            // Get settings from WooCommerce Stripe Gateway plugin
            $data = null;
            $woocommerce_stripe_settings = get_option( 'woocommerce_stripe_settings' );
            if ( !$woocommerce_stripe_settings ) {
                return false;
            }
            $wc_stripe_test_mode = ( isset( $woocommerce_stripe_settings['testmode'] ) ? $woocommerce_stripe_settings['testmode'] : null );
            if ( $wc_stripe_test_mode == 'yes' ) {
                $stripe_test_api_secret_key = get_option( 'stripe_test_api_secret_key', false );
                $stripe_key = $stripe_test_api_secret_key;
            } else {
                $stripe_api_secret_key = get_option( 'stripe_api_secret_key', false );
                $stripe_key = $stripe_api_secret_key;
            }
            // Get our settings and theirs
            $data = array(
                'connectedKey'      => get_option( 'bsd_split_pay_stripe_connect_woo_stripe_connected_account' ),
                'bsd_spscwt_type'   => get_option( 'bsd_spscwt_type' ),
                'percentage'        => get_option( 'bsd_split_pay_stripe_connect_woo_transfer_percentage' ),
                'bsd_spscwt_amount' => get_option( 'bsd_spscwt_amount' ),
                'secret_key'        => $stripe_key,
            );
            return $data;
        }

        public function onboarding_account_cb() {
            global $wpdb;
            $current_user = wp_get_current_user();
            $data_keys = $this->stp_retrieve_keys();
            $stripe_key = $data_keys['secret_key'];
            if ( isset( $_GET['action'] ) && $_GET['action'] == 'onboarding' ) {
                try {
                    // $random_email = $this->get_random_email();
                    if ( $current_user ) {
                        $stripe = new \Stripe\StripeClient($stripe_key);
                        $account_create_response = $stripe->accounts->create( array(
                            'type' => 'standard',
                        ) );
                        if ( !empty( $account_create_response ) && isset( $account_create_response->id ) && !empty( $account_create_response->id ) ) {
                            $account_link_obj = $stripe->accountLinks->create( array(
                                'account'     => $account_create_response->id,
                                'refresh_url' => admin_url( '/admin.php?page=split-pay-stripe-connect&action=onboard_failure&acc_id=' . $account_create_response->id ),
                                'return_url'  => admin_url( '/admin.php?page=split-pay-stripe-connect&action=onboard_success&acc_id=' . $account_create_response->id ),
                                'type'        => 'account_onboarding',
                            ) );
                            if ( !empty( $account_link_obj ) && isset( $account_link_obj['url'] ) && !empty( $account_link_obj['url'] ) ) {
                                wp_redirect( $account_link_obj['url'] );
                                die;
                            }
                        }
                    }
                } catch ( \Exception $e ) {
                    $error_message = $e->getMessage();
                    \WC_Stripe_Logger::log( $this->log_prefix . ': error_message ' . print_r( $error_message, true ) );
                    set_transient( 'ob_failed', $error_message, 120 );
                    wp_redirect( admin_url( '/admin.php?page=split-pay-stripe-connect&msg=ob_failed' ) );
                    exit;
                }
            } elseif ( isset( $_GET['action'] ) && $_GET['action'] == 'onboard_success' ) {
                if ( isset( $_GET['acc_id'] ) && !empty( $_GET['acc_id'] ) ) {
                    try {
                        $stripe = new \Stripe\StripeClient($stripe_key);
                        $account_obj = $stripe->accounts->retrieve( $_GET['acc_id'], array() );
                        if ( isset( $account_obj->tos_acceptance->date ) && !empty( $account_obj->tos_acceptance->date ) ) {
                            $account_id = $_GET['acc_id'];
                            if ( isset( $account_obj->business_profile->name ) && !empty( $account_obj->business_profile->name ) ) {
                                $account_name = $account_obj->business_profile->name;
                            } else {
                                $account_name = $account_id;
                            }
                            $is_added = $wpdb->query( $wpdb->prepare( 'insert into ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . ' (bsd_account_id, bsd_account_name) values (%s, %s)', array($account_id, $account_name) ) );
                            update_user_meta( $current_user->ID, 'account_id', $account_id );
                            update_user_meta( $current_user->ID, 'is_onboard', '1' );
                            wp_redirect( admin_url( '/admin.php?page=split-pay-stripe-connect&msg=ob_success' ) );
                            exit;
                        }
                    } catch ( \Exception $e ) {
                        $error_message = $e->getMessage();
                        \WC_Stripe_Logger::log( $this->log_prefix . ': error_message ' . print_r( $error_message, true ) );
                        set_transient( 'ob_failed', $error_message, 120 );
                        wp_redirect( admin_url( '/admin.php?page=split-pay-stripe-connect&msg=ob_failed' ) );
                        exit;
                    }
                }
            } elseif ( isset( $_GET['action'] ) && $_GET['action'] == 'onboard_failure' ) {
                if ( isset( $_GET['acc_id'] ) && !empty( $_GET['acc_id'] ) ) {
                    $stripe = new \Stripe\StripeClient($stripe_key);
                    $account_obj = $stripe->accounts->retrieve( $_GET['acc_id'], array() );
                    if ( !empty( $account_obj ) ) {
                        try {
                            $stripe->accounts->delete( $_GET['acc_id'], array() );
                            update_user_meta( $current_user->ID, 'failed_account_id', $_GET['acc_id'] );
                            // update_user_meta($current_user->ID, "is_onboard", "0");
                            wp_redirect( admin_url( '/admin.php?page=split-pay-stripe-connect&msg=ob_failed' ) );
                            exit;
                        } catch ( \Exception $e ) {
                            $error = $e->getError();
                            $error_type = $error->type;
                            $error_message = $error->message;
                            $error_request_log_url = $error->request_log_url;
                            \WC_Stripe_Logger::log( $this->log_prefix . ': error_type ' . print_r( $error_type, true ) );
                            \WC_Stripe_Logger::log( $this->log_prefix . ': error_message ' . print_r( $error_message, true ) );
                            \WC_Stripe_Logger::log( $this->log_prefix . ': error_request_log_url ' . print_r( $error_request_log_url, true ) );
                            set_transient( 'ob_failed', $error_message, 120 );
                            wp_redirect( admin_url( '/admin.php?page=split-pay-stripe-connect&msg=ob_failed' ) );
                            exit;
                        }
                    }
                }
            } elseif ( isset( $_GET['action'] ) && $_GET['action'] == 'disconnect_onboarding' ) {
                $account_id = get_user_meta( $current_user->ID, 'account_id', true );
                $is_onboard = get_user_meta( $current_user->ID, 'is_onboard', true );
                if ( !empty( $account_id ) ) {
                    $stripe = new \Stripe\StripeClient($stripe_key);
                    try {
                        $wpdb->delete( $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE, array(
                            'bsd_account_id' => $account_id,
                        ), array('%s') );
                        update_user_meta( $current_user->ID, 'account_id', '' );
                        update_user_meta( $current_user->ID, 'is_onboard', '0' );
                        wp_redirect( admin_url( '/admin.php?page=split-pay-stripe-connect&msg=ob_disconnect_success' ) );
                        exit;
                    } catch ( \Exception $e ) {
                        $error = $e->getError();
                        $error_type = $error->type;
                        $error_message = $error->message;
                        $error_request_log_url = $error->request_log_url;
                        \WC_Stripe_Logger::log( $this->log_prefix . ': error_type ' . print_r( $error_type, true ) );
                        \WC_Stripe_Logger::log( $this->log_prefix . ': error_message ' . print_r( $error_message, true ) );
                        \WC_Stripe_Logger::log( $this->log_prefix . ': error_request_log_url ' . print_r( $error_request_log_url, true ) );
                        set_transient( 'ob_failed', $error_message, 120 );
                        wp_redirect( admin_url( '/admin.php?page=split-pay-stripe-connect&msg=ob_failed' ) );
                        exit;
                    }
                }
            }
        }

        public function get_random_email( $username_length = 6 ) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $randomName = '';
            for ($j = 0; $j < $username_length; $j++) {
                $randomName .= $characters[rand( 0, strlen( $characters ) - 1 )];
            }
            $fullAddress = $randomName . '@' . 'mailinator.com';
            return $fullAddress;
        }

        public function get_stripe_mode() {
            $stripe_mode = 'live';
            $woo_stripe_settings = get_option( 'woocommerce_stripe_settings' );
            if ( isset( $woo_stripe_settings['testmode'] ) ) {
                $stripe_mode = ( $woo_stripe_settings['testmode'] == 'yes' ? 'test' : 'live' );
            }
            return $stripe_mode;
        }

        public function export_to_csv() {
            if ( isset( $_GET['action'] ) && $_GET['action'] == 'export_to_csv' ) {
                if ( check_admin_referer( 'action=export_to_csv', 'security' ) ) {
                    global $wpdb;
                    $table_name = $wpdb->prefix . BSD_TRANSFER_LOG_TABLE;
                    $stripe_mode = $this->get_stripe_mode();
                    $sql = "SELECT COUNT(id) FROM {$table_name} WHERE stripe_mode = %s";
                    $result = $wpdb->get_var( $wpdb->prepare( $sql, $stripe_mode ) );
                    if ( !empty( $result ) ) {
                        $total_rows = $result;
                        $per_page = 50;
                        $total_pages = ceil( $total_rows / $per_page );
                        if ( !empty( $total_pages ) ) {
                            $this->download_send_headers( 'stripe_transfer_log_' . date( 'Y-m-d' ) . '.csv' );
                            for ($i = 0; $i <= $total_pages; $i++) {
                                $paged = $i;
                                $offset = $paged * $per_page;
                                $orderby = 'wc_order_id';
                                $order = 'desc';
                                $sql = "SELECT \n                                            wc_order_id, charge_date, charge_amount, transfer_amount,  transfer_destination, transfer_type, transfer_entered_value, transfer_type as entered_variable, transfer_type as level, transfer_id, charge_id\n                                        FROM {$table_name}\n                                        WHERE stripe_mode = %s\n                                        ORDER BY " . sanitize_sql_orderby( $orderby . ' ' . $order ) . '
                                        LIMIT %d OFFSET %d';
                                // Query output_type will be an associative array with ARRAY_A.
                                $query_results = $wpdb->get_results( $wpdb->prepare(
                                    $sql,
                                    $stripe_mode,
                                    $per_page,
                                    $offset
                                ), ARRAY_A );
                                if ( $paged == 0 ) {
                                    $column_header = array(
                                        'Order ID',
                                        'Date',
                                        'Order Total',
                                        'Transfer Amount',
                                        'Type',
                                        'Value',
                                        'Variable',
                                        'Level',
                                        'Connected Account',
                                        'Stripe Transfer ID',
                                        'Stripe Charge ID'
                                    );
                                    echo $this->array2csv( $query_results, $column_header, true );
                                } else {
                                    echo $this->array2csv( $query_results, array(), false );
                                }
                            }
                        }
                    }
                    die;
                    // wp_redirect(admin_url('admin.php?page=bsd-split-pay-stripe-connect-woo-settings&tab=transfers')); die;
                }
            }
        }

        public function array2csv( array &$array, $column_header, $is_header = false ) {
            if ( count( $array ) == 0 ) {
                return null;
            }
            ob_start();
            $df = fopen( 'php://output', 'w' );
            if ( $is_header ) {
                fputcsv( $df, $column_header );
            }
            foreach ( $array as $row ) {
                $transfer_type = '';
                switch ( $row['transfer_type'] ) {
                    case 1:
                    case 2:
                    case 5:
                    case 8:
                    case 9:
                    case 10:
                    case 11:
                        $transfer_type = 'Subtotal';
                        break;
                    case 3:
                    case 4:
                    case 6:
                    case 7:
                        $transfer_type = 'Shipping';
                        break;
                }
                $entered_variable = '';
                switch ( $row['entered_variable'] ) {
                    case 1:
                    case 3:
                    case 5:
                    case 6:
                    case 8:
                    case 10:
                        $entered_variable = 'Percentage';
                        break;
                    case 2:
                    case 4:
                    case 7:
                    case 9:
                    case 11:
                        $entered_variable = 'Fixed';
                        break;
                }
                $level = '';
                switch ( $row['level'] ) {
                    case 1:
                    case 2:
                    case 3:
                    case 4:
                        $level = 'Global';
                        break;
                    case 5:
                    case 6:
                    case 7:
                    case 8:
                    case 9:
                        $level = 'Product';
                        break;
                    case 10:
                    case 11:
                        $level = 'Variable';
                        break;
                }
                $refectored_row = array(
                    'wc_order_id'            => $row['wc_order_id'],
                    'charge_date'            => $row['charge_date'],
                    'charge_amount'          => $row['charge_amount'],
                    'transfer_amount'        => $row['transfer_amount'],
                    'transfer_type'          => $transfer_type,
                    'transfer_entered_value' => $row['transfer_entered_value'],
                    'entered_variable'       => $entered_variable,
                    'level'                  => $level,
                    'transfer_destination'   => $row['transfer_destination'],
                    'transfer_id'            => $row['transfer_id'],
                    'charge_id'              => $row['charge_id'],
                );
                fputcsv( $df, $refectored_row );
            }
            fclose( $df );
            return ob_get_clean();
        }

        public function download_send_headers( $filename ) {
            // disable caching
            $now = gmdate( 'D, d M Y H:i:s' );
            header( 'Expires: Tue, 03 Jul 2001 06:00:00 GMT' );
            header( 'Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate' );
            header( "Last-Modified: {$now} GMT" );
            // force download
            header( 'Content-Type: application/force-download' );
            header( 'Content-Type: application/octet-stream' );
            header( 'Content-Type: application/download' );
            // disposition / encoding on response body
            header( "Content-Disposition: attachment;filename={$filename}" );
            header( 'Content-Transfer-Encoding: binary' );
        }

        public function update_connected_id_table() {
            $bsd_connected_id_table_created = get_option( 'bsd_connected_id_table_created', false );
            if ( !empty( $bsd_connected_id_table_created ) && $bsd_connected_id_table_created == '1' ) {
                global $wpdb;
                $table_name = $wpdb->prefix . BSD_SCSP_CONNECTED_ID_TABLE;
                try {
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) == $table_name ) {
                        $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN `bsd_global_shipping_type` varchar(50) DEFAULT '' NOT NULL" );
                        $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN `bsd_global_shipping_percentage_amount` double DEFAULT 0 NOT NULL" );
                        $first_row = $wpdb->get_row( "SELECT * FROM {$table_name} order by bsd_connected_id asc limit 1", ARRAY_A );
                        if ( !empty( $first_row ) ) {
                            $wpdb->update(
                                $table_name,
                                array(
                                    'bsd_global_shipping_type'              => 'percentage',
                                    'bsd_global_shipping_percentage_amount' => 100,
                                ),
                                array(
                                    'bsd_connected_id' => $first_row['bsd_connected_id'],
                                ),
                                array('%s', '%d'),
                                array('%d')
                            );
                        }
                        update_option( 'bsd_connected_id_table_created', '1.1' );
                    }
                } catch ( \Exception $e ) {
                    error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error updating database table: ' . $table_name );
                }
            }
        }

        public function update_log_table() {
            $bsd_log_table_created = get_option( 'bsd_log_table_created', false );
            if ( empty( $bsd_log_table_created ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . BSD_TRANSFER_LOG_TABLE;
                try {
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) == $table_name ) {
                        $wpdb->query( 'ALTER TABLE ' . $table_name . ' ADD `transfer_type` tinyint(2) NOT NULL AFTER `stripe_mode`' );
                        update_option( 'bsd_log_table_created', '1.1' );
                    }
                } catch ( \Exception $e ) {
                    error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error updating database table: ' . $table_name );
                }
            }
            if ( !empty( $bsd_log_table_created ) && $bsd_log_table_created == '1.1' ) {
                global $wpdb;
                $table_name = $wpdb->prefix . BSD_TRANSFER_LOG_TABLE;
                try {
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) == $table_name ) {
                        $wpdb->query( 'ALTER TABLE ' . $table_name . ' ADD `transfer_entered_value` decimal(10,2) NULL AFTER `transfer_amount`' );
                        update_option( 'bsd_log_table_created', '1.2' );
                    }
                } catch ( \Exception $e ) {
                    error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error updating database table: ' . $table_name );
                }
            }
            if ( !empty( $bsd_log_table_created ) && $bsd_log_table_created == '1.2' ) {
                global $wpdb;
                $table_name = $wpdb->prefix . BSD_TRANSFER_LOG_TABLE;
                try {
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) == $table_name ) {
                        $wpdb->query( 'ALTER TABLE ' . $table_name . ' ADD `transfer_tax_value` decimal(10,2) NULL AFTER `transfer_amount`' );
                        update_option( 'bsd_log_table_created', '1.3' );
                    }
                } catch ( \Exception $e ) {
                    error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error updating database table: ' . $table_name );
                }
            }
            if ( !empty( $bsd_log_table_created ) && $bsd_log_table_created == '1.3' ) {
                global $wpdb;
                $table_name = $wpdb->prefix . BSD_TRANSFER_LOG_TABLE;
                try {
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) == $table_name ) {
                        $wpdb->query( 'ALTER TABLE ' . $table_name . ' 
						ADD column `item_total` decimal(10,2) NULL AFTER `transfer_entered_value`, 
						ADD column `item_transfer_amount` decimal(10,2) NULL AFTER `transfer_entered_value`, 
						ADD column `item_tax_total` decimal(10,2) NULL AFTER `transfer_entered_value`, 
						ADD column `tax_transfer_type` char(5) NOT NULL AFTER `transfer_entered_value`' );
                        update_option( 'bsd_log_table_created', '1.4' );
                    }
                } catch ( \Exception $e ) {
                    error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error updating database table: ' . $table_name );
                }
            }
        }

        public function update_account_id_table() {
            $spp_account_id_table = get_option( 'spp_account_id_table', false );
            if ( empty( $spp_account_id_table ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE;
                try {
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) == $table_name ) {
                        $wpdb->query( 'ALTER TABLE ' . $table_name . ' ADD `bsd_account_email` varchar(255) DEFAULT "" NOT NULL AFTER `bsd_account_name`' );
                        update_option( 'spp_account_id_table', '1.1' );
                    }
                } catch ( \Exception $e ) {
                    error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error updating database table: ' . $table_name );
                }
            }
        }

        public function create_connect_id_table() {
            $bsd_connected_id_table_created = get_option( 'bsd_connected_id_table_created', false );
            if ( empty( $bsd_connected_id_table_created ) ) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                global $wpdb;
                $table_prefix = $wpdb->prefix;
                $table_name = $table_prefix . BSD_SCSP_CONNECTED_ID_TABLE;
                $charset_collate = $wpdb->get_charset_collate();
                try {
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '" . $table_name . "'" ) != $table_name ) {
                        $sql = 'CREATE TABLE IF NOT EXISTS ' . $table_name . " (\n                                bsd_connected_id bigint(20) NOT NULL AUTO_INCREMENT,\n                                bsd_connected_account_id varchar(55) DEFAULT '' NOT NULL,\n                                bsd_spscwt_type varchar(50) DEFAULT '' NOT NULL,\n                                bsd_spscwt_percentage_amount double DEFAULT 0 NOT NULL,\n                                bsd_global_shipping_type varchar(50) DEFAULT '' NOT NULL,\n                                bsd_global_shipping_percentage_amount double DEFAULT 0 NOT NULL,\n                                PRIMARY KEY  (bsd_connected_id)\n                            ) {$charset_collate};";
                        $is_table_created = dbDelta( $sql );
                        if ( !empty( $is_table_created ) ) {
                            update_option( 'bsd_connected_id_table_created', '1' );
                        }
                        $bsd_split_pay_stripe_connect_woo_stripe_connected_account = get_option( 'bsd_split_pay_stripe_connect_woo_stripe_connected_account' );
                        $bsd_spscwt_type = get_option( 'bsd_spscwt_type' );
                        if ( !empty( $bsd_split_pay_stripe_connect_woo_stripe_connected_account ) && !empty( $bsd_spscwt_type ) ) {
                            $percentage_or_amount = 0;
                            if ( $bsd_spscwt_type == 'percentage' ) {
                                $bsd_split_pay_stripe_connect_woo_transfer_percentage = get_option( 'bsd_split_pay_stripe_connect_woo_transfer_percentage' );
                                $percentage_or_amount = $bsd_split_pay_stripe_connect_woo_transfer_percentage;
                            } else {
                                $bsd_spscwt_amount = get_option( 'bsd_spscwt_amount' );
                                $percentage_or_amount = $bsd_spscwt_amount;
                            }
                            $wpdb->insert( $table_name, array(
                                'bsd_connected_account_id'     => $bsd_split_pay_stripe_connect_woo_stripe_connected_account,
                                'bsd_spscwt_type'              => $bsd_spscwt_type,
                                'bsd_spscwt_percentage_amount' => $percentage_or_amount,
                            ), array('%s', '%s', '%d') );
                        }
                    }
                } catch ( \Exception $e ) {
                    error_log( '[BSD Split Pay for Stripe Connect on Woo Activation] Error creating database table: ' . $table_name );
                }
            }
        }

        public function store_selected_accounts() {
            if ( isset( $_POST ) && !empty( $_POST ) ) {
                if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bsd_scsp_options_main-options' ) ) {
                    if ( isset( $_POST['option_page'] ) && $_POST['option_page'] == 'bsd_scsp_options_main' ) {
                        global $wpdb;
                        $table_prefix = $wpdb->prefix;
                        $table_name = $table_prefix . BSD_SCSP_CONNECTED_ID_TABLE;
                        $insert_array = array();
                        if ( isset( $_POST['bsd_split_pay_stripe_connect_woo_stripe_connected_account'] ) && !empty( $_POST['bsd_split_pay_stripe_connect_woo_stripe_connected_account'] ) && isset( $_POST['bsd_spscwt_type'] ) && !empty( $_POST['bsd_spscwt_type'] ) ) {
                            foreach ( $_POST['bsd_split_pay_stripe_connect_woo_stripe_connected_account'] as $bspscwsca_key => $bspscwsca_value ) {
                                $insert_array[$bspscwsca_key]['account_id'] = sanitize_text_field( $bspscwsca_value );
                                if ( isset( $_POST['bsd_connected_acc_primary_id'][$bspscwsca_key] ) && !empty( $_POST['bsd_connected_acc_primary_id'][$bspscwsca_key] ) ) {
                                    $insert_array[$bspscwsca_key]['id'] = sanitize_text_field( $_POST['bsd_connected_acc_primary_id'][$bspscwsca_key] );
                                }
                                if ( !empty( $insert_array[$bspscwsca_key]['account_id'] ) ) {
                                    if ( isset( $_POST['bsd_spscwt_type'][$bspscwsca_key] ) && !empty( $_POST['bsd_spscwt_type'][$bspscwsca_key] ) ) {
                                        $insert_array[$bspscwsca_key]['bsd_spscwt_type'] = sanitize_text_field( $_POST['bsd_spscwt_type'][$bspscwsca_key] );
                                    }
                                    if ( $insert_array[$bspscwsca_key]['bsd_spscwt_type'] == 'percentage' ) {
                                        if ( isset( $_POST['bsd_split_pay_stripe_connect_woo_transfer_percentage'][$bspscwsca_key] ) ) {
                                            $percentage_or_amount = sanitize_text_field( $_POST['bsd_split_pay_stripe_connect_woo_transfer_percentage'][$bspscwsca_key] );
                                        }
                                    } elseif ( $insert_array[$bspscwsca_key]['bsd_spscwt_type'] == 'amount' ) {
                                        if ( isset( $_POST['bsd_spscwt_amount'][$bspscwsca_key] ) ) {
                                            $percentage_or_amount = sanitize_text_field( $_POST['bsd_spscwt_amount'][$bspscwsca_key] );
                                        }
                                    }
                                    if ( isset( $_POST['bsd_global_shipping_type'][$bspscwsca_key] ) && !empty( $_POST['bsd_global_shipping_type'][$bspscwsca_key] ) ) {
                                        $insert_array[$bspscwsca_key]['bsd_global_shipping_type'] = sanitize_text_field( $_POST['bsd_global_shipping_type'][$bspscwsca_key] );
                                    }
                                    if ( isset( $insert_array[$bspscwsca_key]['bsd_global_shipping_type'] ) && $insert_array[$bspscwsca_key]['bsd_global_shipping_type'] == 'percentage' ) {
                                        if ( isset( $_POST['bsd_global_shipping_percentage'][$bspscwsca_key] ) ) {
                                            $bsd_global_shipping_percentage_amount = sanitize_text_field( $_POST['bsd_global_shipping_percentage'][$bspscwsca_key] );
                                        }
                                    } elseif ( isset( $insert_array[$bspscwsca_key]['bsd_global_shipping_type'] ) && $insert_array[$bspscwsca_key]['bsd_global_shipping_type'] == 'amount' ) {
                                        if ( isset( $_POST['bsd_global_shipping_amount'][$bspscwsca_key] ) ) {
                                            $bsd_global_shipping_percentage_amount = sanitize_text_field( $_POST['bsd_global_shipping_amount'][$bspscwsca_key] );
                                        }
                                    }
                                    if ( empty( $percentage_or_amount ) && !empty( $bsd_global_shipping_percentage_amount ) ) {
                                        $insert_array[$bspscwsca_key]['percentage_or_amount'] = $percentage_or_amount;
                                        $insert_array[$bspscwsca_key]['bsd_global_shipping_percentage_amount'] = $bsd_global_shipping_percentage_amount;
                                    } elseif ( !empty( $percentage_or_amount ) && empty( $bsd_global_shipping_percentage_amount ) ) {
                                        $insert_array[$bspscwsca_key]['percentage_or_amount'] = $percentage_or_amount;
                                        $insert_array[$bspscwsca_key]['bsd_global_shipping_percentage_amount'] = $bsd_global_shipping_percentage_amount;
                                    } elseif ( !empty( $percentage_or_amount ) && !empty( $bsd_global_shipping_percentage_amount ) ) {
                                        $insert_array[$bspscwsca_key]['percentage_or_amount'] = $percentage_or_amount;
                                        $insert_array[$bspscwsca_key]['bsd_global_shipping_percentage_amount'] = $bsd_global_shipping_percentage_amount;
                                    }
                                }
                            }
                            if ( !empty( $insert_array ) ) {
                                foreach ( $insert_array as $irk ) {
                                    if ( isset( $irk['account_id'] ) && empty( $irk['account_id'] ) ) {
                                        if ( isset( $irk['id'] ) && !empty( $irk['id'] ) ) {
                                            $wpdb->delete( $table_name, array(
                                                'bsd_connected_id' => $irk['id'],
                                            ), array('%d') );
                                        }
                                    }
                                    if ( isset( $irk['account_id'] ) && !empty( $irk['account_id'] ) && isset( $irk['bsd_spscwt_type'] ) && !empty( $irk['bsd_spscwt_type'] ) && isset( $irk['percentage_or_amount'] ) ) {
                                        if ( isset( $irk['id'] ) && !empty( $irk['id'] ) ) {
                                            $wpdb->update(
                                                $table_name,
                                                array(
                                                    'bsd_connected_account_id'              => $irk['account_id'],
                                                    'bsd_spscwt_type'                       => $irk['bsd_spscwt_type'],
                                                    'bsd_spscwt_percentage_amount'          => $irk['percentage_or_amount'],
                                                    'bsd_global_shipping_type'              => ( isset( $irk['bsd_global_shipping_type'] ) ? $irk['bsd_global_shipping_type'] : '' ),
                                                    'bsd_global_shipping_percentage_amount' => ( isset( $irk['bsd_global_shipping_percentage_amount'] ) ? $irk['bsd_global_shipping_percentage_amount'] : '' ),
                                                ),
                                                array(
                                                    'bsd_connected_id' => $irk['id'],
                                                ),
                                                array('%s', '%s', '%f'),
                                                array('%d')
                                            );
                                        } else {
                                            $wpdb->insert( $table_name, array(
                                                'bsd_connected_account_id'              => $irk['account_id'],
                                                'bsd_spscwt_type'                       => $irk['bsd_spscwt_type'],
                                                'bsd_spscwt_percentage_amount'          => $irk['percentage_or_amount'],
                                                'bsd_global_shipping_type'              => ( isset( $irk['bsd_global_shipping_type'] ) ? $irk['bsd_global_shipping_type'] : '' ),
                                                'bsd_global_shipping_percentage_amount' => ( isset( $irk['bsd_global_shipping_percentage_amount'] ) ? $irk['bsd_global_shipping_percentage_amount'] : '' ),
                                            ), array('%s', '%s', '%f') );
                                        }
                                    }
                                }
                            }
                        }
                        if ( isset( $_POST['bsd_connected_acc_primary_remove_ids'] ) && !empty( $_POST['bsd_connected_acc_primary_remove_ids'] ) ) {
                            $remove_connected_ids = explode( ', ', $_POST['bsd_connected_acc_primary_remove_ids'] );
                            if ( !empty( $remove_connected_ids ) ) {
                                foreach ( $remove_connected_ids as $rcik ) {
                                    $wpdb->delete( $table_name, array(
                                        'bsd_connected_id' => $rcik,
                                    ), array('%d') );
                                }
                            }
                        }
                        if ( isset( $_POST['sending_meta'] ) && $_POST['sending_meta'] == '1' ) {
                            update_option( 'sending_meta', '1' );
                        } else {
                            update_option( 'sending_meta', '0' );
                        }
                        if ( isset( $_POST['transfer_taxes'] ) && $_POST['transfer_taxes'] == '1' ) {
                            update_option( 'transfer_taxes', '1' );
                        } else {
                            update_option( 'transfer_taxes', '0' );
                        }
                        if ( isset( $_POST['tax_transfer_type'] ) && !empty( $_POST['tax_transfer_type'] ) ) {
                            update_option( 'tax_transfer_type', $_POST['tax_transfer_type'] );
                        } else {
                            update_option( 'tax_transfer_type', '0' );
                        }
                        if ( isset( $_POST['transfer_email'] ) && $_POST['transfer_email'] == '1' ) {
                            update_option( 'transfer_email', '1' );
                        } else {
                            update_option( 'transfer_email', '0' );
                        }
                    }
                }
            }
        }

        public function spp_vendor_options_process() {
            if ( isset( $_POST ) && !empty( $_POST ) ) {
                if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'spp_vendor_options-options' ) ) {
                    if ( isset( $_POST['option_page'] ) && $_POST['option_page'] == 'spp_vendor_options' ) {
                        if ( isset( $_POST['vendor_onboading'] ) && $_POST['vendor_onboading'] == '1' ) {
                            update_option( 'vendor_onboading', '1' );
                        } else {
                            update_option( 'vendor_onboading', '0' );
                        }
                        if ( isset( $_POST['enable_title_description'] ) && $_POST['enable_title_description'] == '1' ) {
                            update_option( 'enable_title_description', '1' );
                        } else {
                            update_option( 'enable_title_description', '0' );
                        }
                        if ( isset( $_POST['onboarding_title'] ) && !empty( $_POST['onboarding_title'] ) ) {
                            update_option( 'onboarding_title', sanitize_text_field( $_POST['onboarding_title'] ) );
                        } else {
                            update_option( 'onboarding_title', '' );
                        }
                        if ( isset( $_POST['onboarding_description'] ) && !empty( $_POST['onboarding_description'] ) ) {
                            update_option( 'onboarding_description', sanitize_textarea_field( $_POST['onboarding_description'] ) );
                        } else {
                            update_option( 'onboarding_description', '' );
                        }
                    }
                }
            }
        }

        public function bsd_connect_standard_account() {
            if ( isset( $_GET['bsd_action'] ) && $_GET['bsd_action'] == 'connect_standard_account' ) {
                global $wpdb;
                $woocommerce_stripe_settings = get_option( 'woocommerce_stripe_settings' );
                $client_id = BSD_SCSP_TEST_CONNECT_ID;
                $data_keys = $this->stp_retrieve_keys();
                $secret_key = $data_keys['secret_key'];
                if ( isset( $woocommerce_stripe_settings['testmode'] ) && $woocommerce_stripe_settings['testmode'] == 'yes' ) {
                    // $secret_key = $woocommerce_stripe_settings['test_secret_key'];
                } else {
                    // $secret_key = $woocommerce_stripe_settings['secret_key'];
                    $client_id = BSD_SCSP_LIVE_CONNECT_ID;
                }
                if ( !empty( $woocommerce_stripe_settings ) && !empty( $secret_key ) ) {
                    $stripe = new \Stripe\StripeClient($secret_key);
                    $redirect_uri = site_url( '/' ) . '?bsd_action=get_code';
                    $url = 'https://connect.stripe.com/oauth/authorize?response_type=code';
                    $url = add_query_arg( 'client_id', $client_id, $url );
                    $url = add_query_arg( 'scope', 'read_write', $url );
                    $url = add_query_arg( 'redirect_uri', urlencode( $redirect_uri ), $url );
                    wp_redirect( $url );
                    exit;
                }
            }
        }

        public function get_initial_accounts() {
            global $wpdb;
            $account_results = $wpdb->get_results( 'select * from ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . ' limit 100', ARRAY_A );
            // $get_all_accounts = get_option("stripe_get_all_accounts", false);
            if ( empty( $account_results ) ) {
                $woocommerce_stripe_settings = get_option( 'woocommerce_stripe_settings' );
                if ( !empty( $woocommerce_stripe_settings ) ) {
                    if ( isset( $woocommerce_stripe_settings['testmode'] ) && $woocommerce_stripe_settings['testmode'] == 'yes' ) {
                        $secret_key = $woocommerce_stripe_settings['test_secret_key'];
                    } else {
                        $secret_key = $woocommerce_stripe_settings['secret_key'];
                    }
                    if ( !empty( $woocommerce_stripe_settings ) && !empty( $secret_key ) ) {
                        try {
                            $stripe = new \Stripe\StripeClient($secret_key);
                            $accounts = $stripe->accounts->all( array(
                                'limit' => BSD_SCSP_SCA_PER_PAGE,
                            ) );
                            if ( $accounts && is_array( $accounts->data ) ) {
                                $accIndex = 0;
                                foreach ( $accounts->data as $acc ) {
                                    $account_id = $acc->id;
                                    $account_name = ( isset( $acc->business_profile->name ) ? $acc->business_profile->name : $account_id );
                                    $account_email = ( !empty( $acc->email ) ? $acc->email : '' );
                                    $wpdb->query( $wpdb->prepare( 'insert into ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . ' (bsd_account_id, bsd_account_name, bsd_account_email) values (%s, %s, %s)', array($account_id, $account_name, $account_email) ) );
                                    ++$accIndex;
                                }
                                if ( isset( $account_id ) ) {
                                    update_option( 'last_fetched_account', $account_id );
                                }
                            }
                            if ( !$accounts->has_more ) {
                                update_option( 'is_all_sca_fetched', true );
                            }
                        } catch ( \Exception $e ) {
                            $_SESSION['ifae_notice'] = $e->getMessage();
                            add_action( 'admin_notices', array($this, 'initial_fetch_acc_exceptions') );
                        }
                        // update_option("stripe_get_all_accounts", "1");
                    }
                }
            }
        }

        public function initial_fetch_acc_exceptions() {
            if ( isset( $_SESSION['ifae_notice'] ) && !empty( $_SESSION['ifae_notice'] ) ) {
                echo '<div class="notice notice-error is-dismissible">
                    <p><strong>Split Pay for Stripe Connect on WooCommerce</strong>: ' . $_SESSION['ifae_notice'] . '</p>
                </div>';
            }
        }

        public function get_stored_accounts() {
            global $wpdb;
            $account_results = $wpdb->get_results( 'select * from ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . ' ', ARRAY_A );
            // $get_all_accounts = get_option("stripe_get_all_accounts", []);
            return $account_results;
        }

        public function fetch_accounts() {
            global $wpdb;
            $page = ( isset( $_POST['page'] ) && !empty( $_POST['page'] ) ? $_POST['page'] : 2 );
            $per_page = BSD_SCSP_SCA_PER_PAGE;
            $offset = $per_page * $page;
            $send_ajax_data = array(
                'is_finished'  => false,
                'current_page' => $page,
                'offset'       => $offset,
                'per_page'     => $per_page,
            );
            if ( isset( $_POST['security'] ) && wp_verify_nonce( $_POST['security'], 'ajax-security' ) ) {
                $woocommerce_stripe_settings = get_option( 'woocommerce_stripe_settings' );
                $data_keys = $this->stp_retrieve_keys();
                $secret_key = $data_keys['secret_key'];
                if ( !empty( $woocommerce_stripe_settings ) && !empty( $secret_key ) ) {
                    try {
                        $stripe = new \Stripe\StripeClient($secret_key);
                        $last_fetched_account = get_option( 'last_fetched_account', false );
                        $account_args = array(
                            'limit' => $per_page,
                        );
                        if ( $last_fetched_account ) {
                            $account_args['starting_after'] = $last_fetched_account;
                        }
                        $accounts = $stripe->accounts->all( $account_args );
                        if ( $accounts && is_array( $accounts->data ) ) {
                            $accIndex = 0;
                            $get_all_accounts = array();
                            $account_id = '';
                            foreach ( $accounts->data as $acc ) {
                                $account_id = $acc->id;
                                $account_name = ( isset( $acc->business_profile->name ) ? $acc->business_profile->name : $account_id );
                                $account_email = ( isset( $acc->email ) && !empty( $acc->email ) ? $acc->email : '' );
                                $row = $wpdb->get_row( $wpdb->prepare( 'select * from ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . '  where bsd_account_id = %s', $account_id ), ARRAY_A );
                                if ( empty( $row ) ) {
                                    $wpdb->query( $wpdb->prepare( 'insert into ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . ' (bsd_account_id, bsd_account_name, bsd_account_email) values (%s, %s, %s)', array($account_id, $account_name, $account_email) ) );
                                    $get_all_accounts[$accIndex]['account_id'] = $account_id;
                                    $get_all_accounts[$accIndex]['account_name'] = $account_name;
                                    ++$accIndex;
                                }
                            }
                            update_option( 'last_fetched_account', $account_id );
                        }
                        if ( $accounts->has_more ) {
                            $send_ajax_data = array(
                                'is_finished'     => false,
                                'current_page'    => $page + 1,
                                'offset'          => $page * $per_page,
                                'per_page'        => $per_page,
                                'new_account_ids' => $get_all_accounts,
                            );
                        } else {
                            $send_ajax_data = array(
                                'is_finished' => true,
                                'message'     => __( 'All accounts synchronized.', 'bsd-split-pay-stripe-connect-woo' ),
                            );
                            if ( !empty( $get_all_accounts ) ) {
                                $send_ajax_data['new_account_ids'] = $get_all_accounts;
                                update_option( 'is_all_sca_fetched', true );
                            }
                        }
                    } catch ( \Exception $e ) {
                        $send_ajax_data = array(
                            'is_finished' => true,
                            'message'     => $e->getMessage(),
                        );
                    }
                } else {
                    $send_ajax_data = array(
                        'is_finished' => true,
                    );
                }
            }
            wp_send_json( $send_ajax_data );
            die;
        }

        public function add_custom_account() {
            if ( isset( $_POST['security'] ) && wp_verify_nonce( $_POST['security'], 'ajax-security' ) ) {
                if ( isset( $_POST['account_id'] ) && !empty( $_POST['account_id'] ) ) {
                    $account_id = $_POST['account_id'];
                    global $wpdb;
                    $row = $wpdb->get_row( $wpdb->prepare( 'select * from ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . '  where bsd_account_id = %s', $account_id ), ARRAY_A );
                    if ( empty( $row ) ) {
                        $wpdb->query( $wpdb->prepare( 'insert into ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . ' (bsd_account_id) values (%s)', array($account_id) ) );
                    }
                    wp_send_json( array(
                        'success' => true,
                    ) );
                    die;
                }
            }
        }

        public function clear_accounts() {
            if ( isset( $_POST['security'] ) && wp_verify_nonce( $_POST['security'], 'ajax-security' ) ) {
                global $wpdb;
                $wpdb->query( 'delete from ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . ' ' );
                delete_option( 'last_fetched_account' );
                delete_option( 'is_all_sca_fetched' );
            }
            wp_send_json( array(
                'success' => true,
            ) );
            die;
        }

        public function create_table() {
            global $wpdb, $bsd_stripe_account_tbl_ver;
            $bsd_stripe_account_tbl_installed_ver = get_option( 'bsd_stripe_account_tbl_ver' );
            if ( $bsd_stripe_account_tbl_installed_ver != $bsd_stripe_account_tbl_ver ) {
                $charset_collate = $wpdb->get_charset_collate();
                $sql = 'CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . " (\n\t\t\t\t\tbsd_sat_id bigint(20) NOT NULL AUTO_INCREMENT,\n\t\t\t\t\tbsd_account_id varchar(55) DEFAULT '' NOT NULL,\n\t\t\t\t\tbsd_account_name varchar(55) DEFAULT '' NOT NULL,\n\t\t\t\t\tPRIMARY KEY  (bsd_sat_id)\n\t\t\t\t) {$charset_collate};";
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta( $sql );
                add_option( 'bsd_stripe_account_tbl_ver', $bsd_stripe_account_tbl_ver );
                $bsd_split_pay_stripe_connect_woo_stripe_connected_account = get_option( 'bsd_split_pay_stripe_connect_woo_stripe_connected_account' );
                $wpdb->query( $wpdb->prepare( 'insert into ' . $wpdb->prefix . BSD_SCSP_STRP_ACCNT_TABLE . ' (bsd_account_id) values (%s)', array($bsd_split_pay_stripe_connect_woo_stripe_connected_account) ) );
            }
        }

        public function is_stripe_enabled_and_configured() {
            $enabled_configured = true;
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
                if ( $testmode == 'yes' ) {
                    if ( empty( $stripe_test_api_public_key ) && empty( $stripe_test_api_secret_key ) ) {
                        $enabled_configured = false;
                    }
                } elseif ( $testmode == 'no' ) {
                    if ( empty( $stripe_api_public_key ) && empty( $stripe_api_secret_key ) ) {
                        $enabled_configured = false;
                    }
                }
            }
            return $enabled_configured;
        }

    }

}