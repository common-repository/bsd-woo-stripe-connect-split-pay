<?php

/**
 * Main plugin class
 *
 * @package     bspscw
 */
namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Exception;
if ( !defined( 'ABSPATH' ) ) {
    exit( 'Sorry!' );
}
// Exit if accessed directly
if ( !class_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\Inc\\BSD_SCSP_Main_Plugin' ) ) {
    class BSD_SCSP_Main_Plugin {
        public $log_prefix = '[BSD Split Pay for Stripe Connect on Woo] ';

        public function run() {
            add_action( 'woocommerce_api_wc_stripe', array($this, 'check_for_webhook'), 9 );
            if ( function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\bsdwcscsp_fs' ) ) {
                // Add account link to plugin links
                add_filter( 'plugin_action_links_' . BSD_SCSP_PLUGIN_BASE_NAME, array($this, 'bsd_scsp_plugin_links') );
            }
            // Add Documentation link to plugin row meta
            add_filter(
                'plugin_row_meta',
                array($this, 'plugin_row_meta'),
                10,
                2
            );
            // Add custom tab to Product Data metabox
            add_filter(
                'woocommerce_product_data_tabs',
                array($this, 'bsd_product_data_tab'),
                10,
                1
            );
            // Add fields to the custom data tab
            add_action( 'woocommerce_product_data_panels', array($this, 'bsd_product_data_fields') );
            if ( function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\bsdwcscsp_fs' ) ) {
            }
            add_action(
                'woocommerce_product_after_variable_attributes',
                array($this, 'bsd_variable_product_fields'),
                10,
                3
            );
            if ( function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\bsdwcscsp_fs' ) ) {
            }
            add_action( 'init', array($this, 'bsd_background_actions') );
            add_action( 'bsd_migrate_existing_split_type_values', array($this, 'bsd_migrate_existing_split_type_values_cb') );
            add_action( 'before_woocommerce_init', array($this, 'spp_hpos_declare_compatibility') );
        }

        public function spp_hpos_declare_compatibility() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BSD_SCSP_PLUGIN_MAIN_FILE_PATH, true );
            }
        }

        public function fee( $atts ) {
            $atts = shortcode_atts( array(
                'percent'       => '',
                'min_fee'       => '',
                'max_fee'       => '',
                'product_price' => '',
            ), $atts, 'fee' );
            $calculated_fee = 0;
            if ( $atts['percent'] ) {
                $calculated_fee = $atts['product_price'] * (floatval( $atts['percent'] ) / 100);
            }
            if ( $atts['min_fee'] && $calculated_fee < $atts['min_fee'] ) {
                $calculated_fee = $atts['min_fee'];
            }
            if ( $atts['max_fee'] && $calculated_fee > $atts['max_fee'] ) {
                $calculated_fee = $atts['max_fee'];
            }
            return $calculated_fee;
        }

        public function get_chosen_shipping_methods( $calculated_shipping_packages = array() ) {
            $chosen_methods = array();
            // Get chosen methods for each package to get our totals.
            foreach ( $calculated_shipping_packages as $key => $package ) {
                $chosen_method = wc_get_chosen_shipping_method_for_package( $key, $package );
                if ( $chosen_method ) {
                    $chosen_methods[$key] = $package['rates'][$chosen_method];
                }
            }
            return $chosen_methods;
        }

        public function bsd_get_all_shipping_zones() {
            $data_store = \WC_Data_Store::load( 'shipping-zone' );
            $raw_zones = $data_store->get_zones();
            foreach ( $raw_zones as $raw_zone ) {
                $zones[] = new \WC_Shipping_Zone($raw_zone);
            }
            return $zones;
        }

        public function bsd_background_actions() {
            if ( class_exists( 'WooCommerce' ) ) {
                include_once WC_ABSPATH . 'packages/action-scheduler/action-scheduler.php';
                $bsd_migration = get_option( 'bsd_migration', false );
                if ( empty( $bsd_migration ) && false === as_has_scheduled_action( 'bsd_migrate_existing_split_type_values' ) ) {
                    as_schedule_single_action(
                        strtotime( 'now' ),
                        'bsd_migrate_existing_split_type_values',
                        array(),
                        '',
                        true
                    );
                }
            }
        }

        public function bsd_migrate_existing_split_type_values_cb() {
            $bsd_migration = get_option( 'bsd_migration', false );
            // $bsd_migration = 0;
            if ( empty( $bsd_migration ) ) {
                global $wpdb;
                $table_prefix = $wpdb->prefix;
                $table_name = $table_prefix . BSD_SCSP_CONNECTED_ID_TABLE;
                $connected_account_query = 'select * from ' . $table_name . ' order by bsd_connected_id asc limit 1 ';
                $connected_account_results = $wpdb->get_results( $connected_account_query, ARRAY_A );
                $first_connected_acc_id = '';
                if ( !empty( $connected_account_results ) && isset( $connected_account_results[0]['bsd_connected_account_id'] ) ) {
                    $first_connected_acc_id = $connected_account_results[0]['bsd_connected_account_id'];
                }
                $bsd_last_page = get_option( 'bsd_last_page', false );
                // update_option("bsd_last_page", 0);
                $page = ( empty( $bsd_last_page ) ? 1 : $bsd_last_page );
                $args = array(
                    'post_type'      => array('product', 'product_variation'),
                    'post_status'    => 'publish',
                    'fields'         => 'ids',
                    'posts_per_page' => 50,
                    'paged'          => $page,
                    'meta_query'     => array(array(
                        'key'     => '_bsd_spscwt_product_type',
                        'compare' => 'EXISTS',
                    )),
                );
                $query = new \WP_Query($args);
                if ( !empty( $query->posts ) ) {
                    foreach ( $query->posts as $prod_id ) {
                        // $product = new \WC_product($prod_id);
                        $_bsd_spscwt_product_connected_account = get_post_meta( $prod_id, '_bsd_spscwt_product_connected_account', true );
                        if ( !empty( $_bsd_spscwt_product_connected_account ) ) {
                            continue;
                        }
                        $_bsd_spscwt_product_type = get_post_meta( $prod_id, '_bsd_spscwt_product_type', true );
                        if ( !empty( $_bsd_spscwt_product_type ) ) {
                            if ( $_bsd_spscwt_product_type == 'percentage' ) {
                                $_stripe_connect_split_pay_transfer_percentage = get_post_meta( $prod_id, '_stripe_connect_split_pay_transfer_percentage', true );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_type_ko', $_bsd_spscwt_product_type );
                                update_post_meta( $prod_id, '_stripe_connect_split_pay_transfer_percentage_ko', $_stripe_connect_split_pay_transfer_percentage );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_connected_account', array(
                                    0 => $first_connected_acc_id,
                                ) );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_type', array(
                                    0 => $_bsd_spscwt_product_type,
                                ) );
                                update_post_meta( $prod_id, '_stripe_connect_split_pay_transfer_percentage', array(
                                    0 => $_stripe_connect_split_pay_transfer_percentage,
                                ) );
                            } elseif ( $_bsd_spscwt_product_type == 'amount' ) {
                                $_bsd_spscwt_product_amount = get_post_meta( $prod_id, '_bsd_spscwt_product_amount', true );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_type_ko', $_bsd_spscwt_product_type );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_amount_ko', $_bsd_spscwt_product_amount );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_connected_account', array(
                                    0 => $first_connected_acc_id,
                                ) );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_type', array(
                                    0 => $_bsd_spscwt_product_type,
                                ) );
                                update_post_meta( $prod_id, '_bsd_spscwt_product_amount', array(
                                    0 => $_bsd_spscwt_product_amount,
                                ) );
                            }
                        }
                    }
                    $next_page = (int) $page + 1;
                    update_option( 'bsd_last_page', $next_page );
                } else {
                    update_option( 'bsd_migration', '1' );
                }
            }
        }

        /*
         * Add account link to plugin links
         *
         */
        public function bsd_scsp_plugin_links( $links ) {
            $plugin_links = array('<a href="' . admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-account' ) . '">' . __( 'Account', 'bsd-split-pay-stripe-connect-woo' ) . '</a>');
            return array_merge( $plugin_links, $links );
        }

        /*
         * Add Documentation link to plugin row meta
         *
         */
        public function plugin_row_meta( $plugin_meta, $plugin_file ) {
            if ( BSD_SCSP_PLUGIN_BASE_NAME === $plugin_file ) {
                $row_meta = array(
                    'docs' => '<a href="https://docs.splitpayplugin.com" aria-label="' . esc_attr( __( 'View Documentation', 'bsd-split-pay-stripe-connect-woo' ) ) . '" target="_blank">' . __( 'Documentation', 'bsd-split-pay-stripe-connect-woo' ) . '</a>',
                );
                $plugin_meta = array_merge( $plugin_meta, $row_meta );
            }
            return $plugin_meta;
        }

        /*
         * Add custom tab to Product Data metabox
         *
         */
        public function bsd_product_data_tab( $product_data_tabs ) {
            global $post;
            $product = wc_get_product( $post->ID );
            $product_types = wc_get_product_types();
            /* if ( $product->is_type('simple') || $product->is_type('variable') ) { */
            $product_data_tabs['stripe-connect-split-pay-tab'] = array(
                'label'  => __( 'Split Pay Plugin', 'bsd-split-pay-stripe-connect-woo' ),
                'target' => 'stripe_connect_split_pay_product_data',
                'class'  => array('show_if_simple'),
            );
            $product_data_tabs['stripe-connect-split-pay-tab'] = array(
                'label'  => __( 'Split Pay Plugin', 'bsd-split-pay-stripe-connect-woo' ),
                'target' => 'stripe_connect_split_pay_product_data',
                'class'  => array('show_if_simple'),
            );
            /*
            			}else{
            				return $product_data_tabs;
            			} */
            return $product_data_tabs;
        }

        /*
         * Add fields to the custom data tab
         *
         */
        public function bsd_product_data_fields( $product_data_tabs ) {
            ob_start();
            require BSD_SCSP_PLUGIN_DIR . '/includes/admin/partials/bsd-simple-product-table.php';
            $variable_product_data_html = ob_get_clean();
            echo $variable_product_data_html;
        }

        /*
         * Save fields of the custom data tab
         *
         */
        public function bsd_product_data_fields_save( $post_id ) {
            $_bsd_spscwt_product_connected_account = $_POST['_bsd_spscwt_product_connected_account'] ?? null;
            $_bsd_spscwt_product_type = $_POST['_bsd_spscwt_product_type'] ?? null;
            $transfer_percentage = $_POST['_stripe_connect_split_pay_transfer_percentage'] ?? null;
            $_bsd_spscwt_product_amount = $_POST['_bsd_spscwt_product_amount'] ?? null;
            /* Shipping */
            $_bsd_spscwt_shipping_type = $_POST['bsd_spscwt_shipping_type'] ?? null;
            $transfer_shipping_percentage = $_POST['bsd_prod_shipping_percentage'] ?? null;
            $_bsd_spscwt_shipping_amount = $_POST['bsd_prod_shipping_amount'] ?? null;
            $prod_valid_connected_account = array();
            if ( !empty( $_bsd_spscwt_product_connected_account ) ) {
                foreach ( $_bsd_spscwt_product_connected_account as $bspcak => $bspcav ) {
                    if ( !empty( $bspcav ) && $_bsd_spscwt_product_type[$bspcak] == 'percentage' && !empty( $transfer_percentage[$bspcak] ) ) {
                        $prod_valid_connected_account[$bspcak] = $bspcav;
                    } elseif ( !empty( $bspcav ) && $_bsd_spscwt_product_type[$bspcak] == 'amount' && !empty( $_bsd_spscwt_product_amount[$bspcak] ) ) {
                        $prod_valid_connected_account[$bspcak] = $bspcav;
                    }
                }
            }
            update_post_meta( $post_id, '_bsd_spscwt_product_connected_account', $prod_valid_connected_account );
            if ( empty( $_bsd_spscwt_product_type ) ) {
                delete_post_meta( $post_id, '_bsd_spscwt_product_type' );
                delete_post_meta( $post_id, '_stripe_connect_split_pay_transfer_percentage' );
                delete_post_meta( $post_id, '_bsd_spscwt_product_amount' );
            } else {
                update_post_meta( $post_id, '_bsd_spscwt_product_type', $_bsd_spscwt_product_type );
            }
            if ( $transfer_percentage === null ) {
                delete_post_meta( $post_id, '_stripe_connect_split_pay_transfer_percentage' );
            } else {
                update_post_meta( $post_id, '_stripe_connect_split_pay_transfer_percentage', $transfer_percentage );
            }
            if ( empty( $_bsd_spscwt_product_amount ) ) {
                delete_post_meta( $post_id, '_bsd_spscwt_product_amount' );
            } else {
                update_post_meta( $post_id, '_bsd_spscwt_product_amount', $_bsd_spscwt_product_amount );
            }
            /* Shipping */
            if ( empty( $_bsd_spscwt_shipping_type ) ) {
                delete_post_meta( $post_id, '_bsd_spscwt_shipping_type' );
                delete_post_meta( $post_id, '_bsd_prod_shipping_percentage' );
                delete_post_meta( $post_id, '_bsd_spscwt_shipping_amount' );
            } else {
                update_post_meta( $post_id, '_bsd_spscwt_shipping_type', $_bsd_spscwt_shipping_type );
            }
            if ( $transfer_shipping_percentage === null ) {
                delete_post_meta( $post_id, '_bsd_prod_shipping_percentage' );
            } else {
                update_post_meta( $post_id, '_bsd_prod_shipping_percentage', $transfer_shipping_percentage );
            }
            if ( empty( $_bsd_spscwt_shipping_amount ) ) {
                delete_post_meta( $post_id, '_bsd_spscwt_shipping_amount' );
            } else {
                update_post_meta( $post_id, '_bsd_spscwt_shipping_amount', $_bsd_spscwt_shipping_amount );
            }
        }

        /***************************************
         * BEGIN Modified WooCommerce Functions
         ***************************************/
        /**
         * Check incoming requests for Stripe Webhook data and process them.
         * WooCommerce version 4.0.0
         *
         * @version 2.0.0
         */
        public function check_for_webhook() {
            if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || !isset( $_GET['wc-api'] ) || 'wc_stripe' !== $_GET['wc-api'] ) {
                return;
            }
            $wc_stripe_webhook_handler = new \WC_Stripe_Webhook_Handler();
            // BSD Instantiate WC Stripe class
            $request_body = file_get_contents( 'php://input' );
            $request_headers = array_change_key_case( $wc_stripe_webhook_handler->get_request_headers(), CASE_UPPER );
            // New function call:
            if ( !$this->webhook_for_this_site( $request_body ) ) {
                status_header( 200 );
                exit;
            }
            // Validate it to make sure it is legit.
            if ( $this->is_valid_request( $request_headers, $request_body ) ) {
                $wc_stripe_webhook_handler->process_webhook( $request_body );
                \WC_Stripe_Logger::log( $this->log_prefix . ': after process webhooks ' . print_r( $request_body, true ) );
                $this->transfer_controller( $request_body );
                status_header( 200 );
                exit;
            } else {
                \WC_Stripe_Logger::log( 'Incoming webhook failed validation: ' . print_r( $request_body, true ) );
                status_header( 400 );
                exit;
            }
        }

        /**
         * Verify the incoming webhook notification to make sure it is legit.
         *
         * @since   4.0.0
         * WooCommerce @param string $request_headers The request headers from Stripe.
         *
         * @param string $request_body The request body from Stripe.
         *
         * @return bool
         *
         * @version 4.0.0
         * @version 1.0.0
         */
        public function is_valid_request( $request_headers = null, $request_body = null ) {
            if ( null === $request_headers || null === $request_body ) {
                return false;
            }
            if ( !empty( $request_headers['USER-AGENT'] ) && !preg_match( '/Stripe/', $request_headers['USER-AGENT'] ) ) {
                return false;
            }
            if ( !empty( $this->secret ) ) {
                // Check for a valid signature.
                $signature_format = '/^t=(?P<timestamp>\\d+)(?P<signatures>(,v\\d+=[a-z0-9]+){1,2})$/';
                if ( empty( $request_headers['STRIPE-SIGNATURE'] ) || !preg_match( $signature_format, $request_headers['STRIPE-SIGNATURE'], $matches ) ) {
                    return false;
                }
                // Verify the timestamp.
                $timestamp = intval( $matches['timestamp'] );
                if ( abs( $timestamp - time() ) > 5 * MINUTE_IN_SECONDS ) {
                    $first_time = abs( $timestamp - time() );
                    $second_time = 5 * MINUTE_IN_SECONDS;
                    return;
                }
                // Generate the expected signature.
                $signed_payload = $timestamp . '.' . $request_body;
                $expected_signature = hash_hmac( 'sha256', $signed_payload, $this->secret );
            }
            return true;
        }

        /***************************************
         * END Modified WooCommerce Functions
         ***************************************/
        private function calculate_transfer_amount( $order_id, $default_percentage, $data_keys ) {
            global $wpdb;
            $table_prefix = $wpdb->prefix;
            $table_name = $table_prefix . BSD_SCSP_CONNECTED_ID_TABLE;
            $total_transfer_amount = 0;
            $order = wc_get_order( $order_id );
            $ordered_items = $order->get_items();
            /** tax starts */
            $tax = new \WC_Tax();
            $product_wise_rates = array();
            $tax_transfer_type = get_option( 'tax_transfer_type', false );
            /** tax ends */
            /* shipping */
            $shipping_total = $order->get_shipping_total();
            $global_shipping_amount = $shipping_total;
            \WC_Stripe_Logger::log( $this->log_prefix . ': shipping_total ' . print_r( $shipping_total, true ) );
            $selected_shipping_method_id = '';
            foreach ( $order->get_shipping_methods() as $shipping_method ) {
                $selected_shipping_method_id = $shipping_method->get_method_id();
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ': selected_shipping_method_id ' . print_r( $selected_shipping_method_id, true ) );
            foreach ( $this->bsd_get_all_shipping_zones() as $zone ) {
                $zone_shipping_methods = $zone->get_shipping_methods();
                foreach ( $zone_shipping_methods as $index => $method ) {
                    if ( $method->id == $selected_shipping_method_id ) {
                        $shipping_data[$index] = $method;
                    }
                }
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ': shipping_data ' . print_r( $shipping_data, true ) );
            $applied_coupon_arry = array();
            $fixed_cart_discounted_amount = 0;
            $fixed_cart_percentage_discounted_amount = 0;
            \WC_Stripe_Logger::log( $this->log_prefix . ': get_coupons obj ' . print_r( $order->get_coupons(), true ) );
            foreach ( $order->get_coupons() as $coupon ) {
                $code = $coupon->get_code();
                $original_coupon_details = new \WC_Coupon($code);
                $product_ids = $original_coupon_details->get_product_ids();
                $discount_type = $original_coupon_details->get_discount_type();
                if ( isset( $coupon->get_data()['meta_data'][0]->get_data()['value'] ) && is_string( $coupon->get_data()['meta_data'][0]->get_data()['value'] ) ) {
                    $cpn_meta_data = json_decode( $coupon->get_data()['meta_data'][0]->get_data()['value'], true );
                    $applied_coupon_arry[] = array(
                        'code'            => ( isset( $cpn_meta_data[1] ) ? $cpn_meta_data[1] : '' ),
                        'discount_type'   => ( isset( $cpn_meta_data[2] ) ? $cpn_meta_data[2] : $discount_type ),
                        'discount'        => ( isset( $coupon->get_data()['discount'] ) ? $coupon->get_data()['discount'] : '' ),
                        'discount_amount' => ( isset( $cpn_meta_data[3] ) ? $cpn_meta_data[3] : '' ),
                        'product_ids'     => $product_ids,
                    );
                } else {
                    $applied_coupon_arry[] = array(
                        'code'            => ( isset( $coupon->get_data()['meta_data'][0]->get_data()['value']['code'] ) ? $coupon->get_data()['meta_data'][0]->get_data()['value']['code'] : '' ),
                        'discount_type'   => ( isset( $coupon->get_data()['meta_data'][0]->get_data()['value']['discount_type'] ) ? $coupon->get_data()['meta_data'][0]->get_data()['value']['discount_type'] : $discount_type ),
                        'discount'        => ( isset( $coupon->get_data()['discount'] ) ? $coupon->get_data()['discount'] : '' ),
                        'discount_amount' => ( isset( $coupon->get_data()['meta_data'][0]->get_data()['value']['amount'] ) ? $coupon->get_data()['meta_data'][0]->get_data()['value']['amount'] : '' ),
                        'product_ids'     => $product_ids,
                    );
                }
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ': applied_coupon_arry ' . print_r( $applied_coupon_arry, true ) );
            if ( !empty( $applied_coupon_arry ) ) {
                foreach ( $applied_coupon_arry as $acak ) {
                    if ( empty( $acak['product_ids'] ) && $acak['discount_type'] == 'fixed_cart' ) {
                        $fixed_cart_discounted_amount = $fixed_cart_discounted_amount + $acak['discount'];
                    } elseif ( empty( $acak['product_ids'] ) && $acak['discount_type'] == 'percent' ) {
                        $fixed_cart_percentage_discounted_amount = $acak['discount_amount'];
                    }
                }
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ': fixed_cart_discounted_amount ' . print_r( $fixed_cart_discounted_amount, true ) );
            $customer_id = $order->get_customer_id();
            $customer = new \WC_Customer($customer_id);
            $data_array = array();
            /** tax starts */
            $all_taxes_array = array();
            foreach ( $order->get_items( 'tax' ) as $tx_item_id => $tx_item ) {
                $tax_rate_id = $tx_item->get_rate_id();
                // Tax rate ID
                $all_taxes_array[$tax_rate_id]['tax_total'] = $tx_item->get_tax_total();
                $all_taxes_array[$tax_rate_id]['tax_ship_total'] = $tx_item->get_shipping_tax_total();
            }
            /** tax ends */
            \WC_Stripe_Logger::log( $this->log_prefix . ': all_taxes_array ' . print_r( $all_taxes_array, true ) );
            $product_wise_taxes = array();
            foreach ( $ordered_items as $item ) {
                $product = $item->get_product();
                $get_taxes = $item->get_taxes();
                \WC_Stripe_Logger::log( $this->log_prefix . ': get_taxes ' . print_r( $get_taxes, true ) );
                foreach ( $get_taxes['subtotal'] as $rt_id => $rt_tax ) {
                    $product_wise_taxes[$product->get_id()][] = $rt_tax;
                }
                \WC_Stripe_Logger::log( $this->log_prefix . ': product_wise_taxes ' . print_r( $product_wise_taxes, true ) );
                /** tax starts */
                $taxes = $tax->get_rates( $item->get_tax_class(), $customer );
                \WC_Stripe_Logger::log( $this->log_prefix . ': get_tax_class ' . print_r( $item->get_tax_class(), true ) );
                foreach ( $taxes as $txk => $txv ) {
                    $product_wise_rates[$product->get_id()]['tax_rate_id'] = $txk;
                }
                /** tax ends */
                \WC_Stripe_Logger::log( $this->log_prefix . ': product_wise_rates ' . print_r( $product_wise_rates, true ) );
                $shipping_class_id = '';
                $variation_id = $product_wise_shipping_cost = 0;
                $product_id = $item->get_product_id();
                if ( $product->is_type( 'variation' ) ) {
                    $variation_id = $product->get_id();
                    $product_id = $product->get_id();
                }
                $product_price = $product->get_price();
                $quantity = $item->get_quantity();
                /* shipping */
                $shipping_class_id = $product->get_shipping_class_id();
                \WC_Stripe_Logger::log( $this->log_prefix . ': shipping_class_id ' . print_r( $shipping_class_id, true ) );
                if ( isset( $shipping_class_id ) && !empty( $shipping_class_id ) ) {
                    foreach ( $shipping_data as $data ) {
                        if ( isset( $data->instance_settings['class_cost_' . $shipping_class_id] ) ) {
                            $class_cost = $data->instance_settings['class_cost_' . $shipping_class_id];
                            $class_cost_type = $data->instance_settings['type'];
                            \WC_Stripe_Logger::log( $this->log_prefix . ': class_cost ' . print_r( $class_cost, true ) );
                            \WC_Stripe_Logger::log( $this->log_prefix . ': class_cost_type ' . print_r( $class_cost_type, true ) );
                            if ( $class_cost_type == 'class' ) {
                                $qty_post = strpos( $class_cost, '[qty]' );
                                $fee_post = strpos( $class_cost, '[fee' );
                                if ( $qty_post !== false ) {
                                    $rate = substr( $class_cost, 0, 2 );
                                    $product_wise_shipping_cost = $rate * $quantity;
                                } elseif ( $fee_post !== false ) {
                                    add_shortcode( 'fee', array($this, 'fee') );
                                    $product_price = $product_price * $quantity;
                                    $rate = do_shortcode( str_replace( ']', ' product_price="' . $product_price . '" ]', $class_cost ) );
                                    remove_shortcode( 'fee', array($this, 'fee') );
                                    $product_wise_shipping_cost = $rate;
                                } elseif ( ctype_digit( $class_cost ) ) {
                                    $product_wise_shipping_cost = $class_cost;
                                }
                                $product_wise_shipping_cost = $product_wise_shipping_cost;
                            }
                        }
                    }
                }
                \WC_Stripe_Logger::log( $this->log_prefix . ': product_wise_shipping_cost ' . print_r( $product_wise_shipping_cost, true ) );
                $data_array[$product_id]['product_id'] = $product_id;
                $data_array[$product_id]['single_product_original_price'] = $product_price;
                $data_array[$product_id]['product_quantity'] = $quantity;
                /* shipping */
                $data_array[$product_id]['product_shipping_cost'] = $product_wise_shipping_cost;
                $data_array[$product_id]['product_original_shipping_cost'] = $product_wise_shipping_cost;
                $global_shipping_amount = $global_shipping_amount - $product_wise_shipping_cost;
                $product_discount = 0;
                if ( !empty( $applied_coupon_arry ) ) {
                    foreach ( $applied_coupon_arry as $acak ) {
                        if ( !empty( $acak['product_ids'] ) && in_array( $product_id, $acak['product_ids'] ) ) {
                            $product_discount = $acak['discount'];
                            break;
                        }
                        if ( !empty( $acak['product_ids'] ) && in_array( $variation_id, $acak['product_ids'] ) ) {
                            $product_discount = $acak['discount'];
                            break;
                        }
                    }
                }
                $data_array[$product_id]['product_discount'] = $product_discount;
                $data_array[$product_id]['product_price_wo_qty'] = $product_price;
                $product_price = $product_price * $quantity;
                $data_array[$product_id]['product_price_x_qty'] = $product_price;
                $data_array[$product_id]['product_discounted_price_x_qty'] = $product_price - $product_discount;
                if ( $product->is_type( 'simple' ) ) {
                    $_bsd_spscwt_product_connected_account = get_post_meta( $product_id, '_bsd_spscwt_product_connected_account', true );
                    $_bsd_spscwt_product_type = get_post_meta( $product_id, '_bsd_spscwt_product_type', true );
                    $product_transfer_percentage = get_post_meta( $product_id, '_stripe_connect_split_pay_transfer_percentage', true );
                    $_bsd_spscwt_product_amount = get_post_meta( $product_id, '_bsd_spscwt_product_amount', true );
                    /* shipping */
                    $_bsd_spscwt_shipping_type = get_post_meta( $product_id, '_bsd_spscwt_shipping_type', true );
                    $transfer_shipping_percentage = get_post_meta( $product_id, '_bsd_prod_shipping_percentage', true );
                    $_bsd_spscwt_shipping_amount = get_post_meta( $product_id, '_bsd_spscwt_shipping_amount', true );
                } elseif ( $product->is_type( 'variation' ) ) {
                    $variation_id = $product->get_id();
                    $_bsd_spscwt_product_connected_account = get_post_meta( $variation_id, '_bsd_spscwt_product_connected_account', true );
                    $_bsd_spscwt_product_type = get_post_meta( $variation_id, '_bsd_spscwt_product_type', true );
                    $product_transfer_percentage = get_post_meta( $variation_id, '_stripe_connect_split_pay_transfer_percentage', true );
                    $_bsd_spscwt_product_amount = get_post_meta( $variation_id, '_bsd_spscwt_product_amount', true );
                    \WC_Stripe_Logger::log( $this->log_prefix . ': variable product id ' . print_r( $variation_id, true ) );
                    /* shipping */
                    $_bsd_spscwt_shipping_type = get_post_meta( $variation_id, '_bsd_spscwt_shipping_type', true );
                    $transfer_shipping_percentage = get_post_meta( $variation_id, '_bsd_prod_shipping_percentage', true );
                    $_bsd_spscwt_shipping_amount = get_post_meta( $variation_id, '_bsd_prod_shipping_amount', true );
                } else {
                    $_bsd_spscwt_product_connected_account = get_post_meta( $product_id, '_bsd_spscwt_product_connected_account', true );
                    $_bsd_spscwt_product_type = get_post_meta( $product_id, '_bsd_spscwt_product_type', true );
                    $product_transfer_percentage = get_post_meta( $product_id, '_stripe_connect_split_pay_transfer_percentage', true );
                    $_bsd_spscwt_product_amount = get_post_meta( $product_id, '_bsd_spscwt_product_amount', true );
                    /* shipping */
                    $_bsd_spscwt_shipping_type = get_post_meta( $product_id, '_bsd_spscwt_shipping_type', true );
                    $transfer_shipping_percentage = get_post_meta( $product_id, '_bsd_prod_shipping_percentage', true );
                    $_bsd_spscwt_shipping_amount = get_post_meta( $product_id, '_bsd_spscwt_shipping_amount', true );
                }
                $data_array[$product_id]['product_type'] = $product->get_type();
                if ( !$product_price ) {
                    continue;
                }
                if ( empty( $_bsd_spscwt_product_connected_account ) ) {
                    $data_array[$product_id]['transfer_level'] = 'global';
                    if ( isset( $product_wise_taxes[$product_id][0] ) ) {
                        $data_array[$product_id]['global_tax'] = $data_array[$product_id]['global_tax'] + $product_wise_taxes[$product_id][0];
                        /** tax starts */
                    }
                } else {
                    $data_array[$product_id]['transfer_level'] = 'product';
                    // if($data_array[$product_id]["product_type"] == "simple"){
                    $data_array[$product_id]['transfer_accounts'] = $_bsd_spscwt_product_connected_account;
                    $data_array[$product_id]['transfer_type'] = $_bsd_spscwt_product_type;
                    $data_array[$product_id]['transfer_percentage'] = $product_transfer_percentage;
                    $data_array[$product_id]['transfer_amount'] = $_bsd_spscwt_product_amount;
                    /* shipping */
                    $data_array[$product_id]['transfer_shipping_type'] = $_bsd_spscwt_shipping_type;
                    $data_array[$product_id]['transfer_shipping_percentage'] = $transfer_shipping_percentage;
                    $data_array[$product_id]['transfer_shipping_amount'] = $_bsd_spscwt_shipping_amount;
                    $data_array[$product_id]['product_tax'] = $product_wise_taxes[$product_id];
                    /** tax starts */
                }
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ': data_array before calculation ' . print_r( $data_array, true ) );
            $total_global_transfer_amount = 0;
            $global_product_discounted_price_x_qty_total = 0;
            $global_product_price_x_qty = 0;
            $product_discounted_price_x_qty_total = 0;
            $global_product_qty_total = 0;
            $store_acc_wise_array = array();
            $store_acc_wise_shipping_array = array();
            $global_tax = 0;
            if ( !empty( $data_array ) ) {
                foreach ( $data_array as $dak => $dav ) {
                    if ( $dav['transfer_level'] == 'global' ) {
                        if ( !empty( $fixed_cart_percentage_discounted_amount ) ) {
                            $fcpdam = $fixed_cart_percentage_discounted_amount / 100;
                            $data_array[$dak]['fxd_crt_prcntg_dscunt_amt'] = $dav['product_price_x_qty'] * $fcpdam;
                            $data_array[$dak]['product_discounted_price_x_qty'] = $dav['product_discounted_price_x_qty'] - $data_array[$dak]['fxd_crt_prcntg_dscunt_amt'];
                            $global_product_discounted_price_x_qty_total += $data_array[$dak]['product_discounted_price_x_qty'];
                        } else {
                            $global_product_discounted_price_x_qty_total += $dav['product_discounted_price_x_qty'];
                        }
                        $global_product_price_x_qty += $dav['product_price_x_qty'];
                        $global_product_qty_total += $dav['product_quantity'];
                        // shipping
                        $global_shipping_amount = $global_shipping_amount + $data_array[$dak]['product_shipping_cost'];
                        /** tax start */
                        if ( !empty( $data_array[$dak]['global_tax'] ) ) {
                            \WC_Stripe_Logger::log( $this->log_prefix . ': data_array global_tax ' . print_r( $data_array[$dak]['global_tax'], true ) );
                            $global_tax = $global_tax + $data_array[$dak]['global_tax'];
                        }
                        $data_array['global_tax_total'] = $global_tax;
                        /** tax end */
                    } else {
                        if ( !empty( $fixed_cart_percentage_discounted_amount ) ) {
                            $fcpdam = $fixed_cart_percentage_discounted_amount / 100;
                            $data_array[$dak]['fxd_crt_prcntg_dscunt_amt'] = $dav['product_price_x_qty'] * $fcpdam;
                            $data_array[$dak]['product_discounted_price_x_qty'] = $dav['product_discounted_price_x_qty'] - $data_array[$dak]['fxd_crt_prcntg_dscunt_amt'];
                            $dav['product_discounted_price_x_qty'] = $data_array[$dak]['product_discounted_price_x_qty'];
                        }
                        /** tax start */
                        $tax = 0;
                        if ( !empty( $data_array[$dak]['product_tax'] ) ) {
                            \WC_Stripe_Logger::log( $this->log_prefix . ': data_array product_tax ' . print_r( $data_array[$dak]['product_tax'], true ) );
                            foreach ( $data_array[$dak]['product_tax'] as $gtk ) {
                                $tax = $tax + $gtk;
                            }
                        }
                        $data_array[$dak]['tax_total'] = $tax;
                        /** tax end */
                        if ( isset( $dav['transfer_accounts'] ) && !empty( $dav['transfer_accounts'] ) ) {
                            foreach ( $dav['transfer_accounts'] as $dtak => $dtav ) {
                                $account_wise_tax = 0;
                                if ( isset( $dav['transfer_type'][$dtak] ) ) {
                                    if ( $dav['transfer_type'][$dtak] == 'percentage' ) {
                                        $percentage_amount = $dav['transfer_percentage'][$dtak] / 100;
                                        $product_discounted_price_x_qty_total = $dav['product_discounted_price_x_qty'] * $percentage_amount;
                                        /** tax start */
                                        if ( $tax_transfer_type == 'partial' ) {
                                            $account_wise_tax = $data_array[$dak]['tax_total'] * $percentage_amount;
                                        }
                                        /** tax end */
                                        $data_array[$dak]['transfer_amount_account_wise'][$dtak] = array(
                                            'product_id'                           => $dav['product_id'],
                                            'connected_acc_id'                     => $dtav,
                                            'product_discounted_price_x_qty_total' => $product_discounted_price_x_qty_total,
                                            'transfer_type'                        => $dav['transfer_type'][$dtak],
                                            'transfer_percentage_or_amount'        => $dav['transfer_percentage'][$dtak],
                                            'product_type'                         => $dav['product_type'],
                                            'account_wise_tax'                     => $account_wise_tax,
                                            'tax_total'                            => $data_array[$dak]['tax_total'],
                                        );
                                        // $dav["product_discounted_price_x_qty"] = $dav["product_discounted_price_x_qty"] - $product_discounted_price_x_qty_total;
                                    } elseif ( $dav['transfer_type'][$dtak] == 'amount' ) {
                                        // $dav["product_discounted_price_x_qty"] = 0; // case-1 product amount is 0;
                                        // $dav["transfer_amount"][$dtak] = 100; // case-2 product amount is left to less transfer amount
                                        if ( $dav['product_discounted_price_x_qty'] > 0 ) {
                                            $transfer_amount_x_qty = $dav['transfer_amount'][$dtak] * $dav['product_quantity'];
                                            if ( $transfer_amount_x_qty > $dav['product_discounted_price_x_qty'] ) {
                                                $product_discounted_price_x_qty_total = $dav['product_discounted_price_x_qty'];
                                            } else {
                                                $product_discounted_price_x_qty_total = $transfer_amount_x_qty;
                                            }
                                            /** tax start */
                                            if ( $tax_transfer_type == 'partial' ) {
                                                $account_wise_tax = $dav['transfer_amount'][$dtak] / $dav['product_price_wo_qty'] * $data_array[$dak]['tax_total'];
                                            }
                                            /** tax end */
                                            $data_array[$dak]['transfer_amount_account_wise'][$dtak] = array(
                                                'product_id'                           => $dav['product_id'],
                                                'connected_acc_id'                     => $dtav,
                                                'product_discounted_price_x_qty_total' => $product_discounted_price_x_qty_total,
                                                'transfer_type'                        => $dav['transfer_type'][$dtak],
                                                'transfer_percentage_or_amount'        => $transfer_amount_x_qty,
                                                'product_type'                         => $dav['product_type'],
                                                'account_wise_tax'                     => $account_wise_tax,
                                                'tax_total'                            => $data_array[$dak]['tax_total'],
                                            );
                                        }
                                        // $dav["product_discounted_price_x_qty"] = $dav["product_discounted_price_x_qty"] - $product_discounted_price_x_qty_total;
                                    }
                                }
                                /* shipping */
                                if ( isset( $dav['transfer_shipping_type'][$dtak] ) && $data_array[$dak]['product_shipping_cost'] > 0 ) {
                                    if ( $dav['transfer_shipping_type'][$dtak] == 'percentage' && !empty( $dav['transfer_shipping_percentage'][$dtak] ) ) {
                                        $percentage_shipping_amount = $dav['transfer_shipping_percentage'][$dtak] / 100;
                                        $transfer_product_shipping_cost = $percentage_shipping_amount * $data_array[$dak]['product_original_shipping_cost'];
                                        if ( !empty( $transfer_product_shipping_cost ) && $transfer_product_shipping_cost < $data_array[$dak]['product_shipping_cost'] ) {
                                            $data_array[$dak]['transfer_shipping_amount_account_wise'][$dtak] = array(
                                                'product_id'                     => $dav['product_id'],
                                                'connected_acc_id'               => $dtav,
                                                'product_original_shipping_cost' => $data_array[$dak]['product_original_shipping_cost'],
                                                'transfer_type'                  => $dav['transfer_shipping_type'][$dtak],
                                                'transfer_percentage_or_amount'  => $dav['transfer_shipping_percentage'][$dtak],
                                                'transfer_calculate_amount'      => (float) number_format(
                                                    $transfer_product_shipping_cost,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                            );
                                        } elseif ( !empty( $transfer_product_shipping_cost ) && $transfer_product_shipping_cost > $data_array[$dak]['product_shipping_cost'] ) {
                                            $transfer_product_shipping_cost = $data_array[$dak]['product_shipping_cost'];
                                            $data_array[$dak]['transfer_shipping_amount_account_wise'][$dtak] = array(
                                                'product_id'                     => $dav['product_id'],
                                                'connected_acc_id'               => $dtav,
                                                'product_original_shipping_cost' => $data_array[$dak]['product_original_shipping_cost'],
                                                'transfer_type'                  => $dav['transfer_shipping_type'][$dtak],
                                                'transfer_percentage_or_amount'  => $dav['transfer_shipping_percentage'][$dtak],
                                                'transfer_calculate_amount'      => $transfer_product_shipping_cost,
                                            );
                                        }
                                        $data_array[$dak]['product_shipping_cost'] = $data_array[$dak]['product_shipping_cost'] - $transfer_product_shipping_cost;
                                    } elseif ( $dav['transfer_shipping_type'][$dtak] == 'amount' ) {
                                        if ( isset( $dav['transfer_shipping_amount'][$dtak] ) ) {
                                            $transfer_product_shipping_cost = $dav['transfer_shipping_amount'][$dtak];
                                            if ( !empty( $transfer_product_shipping_cost ) && $transfer_product_shipping_cost < $data_array[$dak]['product_shipping_cost'] ) {
                                                $data_array[$dak]['transfer_shipping_amount_account_wise'][$dtak] = array(
                                                    'product_id'                     => $dav['product_id'],
                                                    'connected_acc_id'               => $dtav,
                                                    'product_original_shipping_cost' => $data_array[$dak]['product_original_shipping_cost'],
                                                    'transfer_type'                  => $dav['transfer_shipping_type'][$dtak],
                                                    'transfer_percentage_or_amount'  => $dav['transfer_shipping_amount'][$dtak],
                                                    'transfer_calculate_amount'      => $transfer_product_shipping_cost,
                                                );
                                            } elseif ( !empty( $transfer_product_shipping_cost ) && $transfer_product_shipping_cost > $data_array[$dak]['product_shipping_cost'] ) {
                                                $transfer_product_shipping_cost = $data_array[$dak]['product_shipping_cost'];
                                                $data_array[$dak]['transfer_shipping_amount_account_wise'][$dtak] = array(
                                                    'product_id'                     => $dav['product_id'],
                                                    'connected_acc_id'               => $dtav,
                                                    'product_original_shipping_cost' => $data_array[$dak]['product_original_shipping_cost'],
                                                    'transfer_type'                  => $dav['transfer_shipping_type'][$dtak],
                                                    'transfer_percentage_or_amount'  => $dav['transfer_shipping_amount'][$dtak],
                                                    'transfer_calculate_amount'      => $transfer_product_shipping_cost,
                                                );
                                            }
                                            $data_array[$dak]['product_shipping_cost'] = $data_array[$dak]['product_shipping_cost'] - $transfer_product_shipping_cost;
                                        }
                                    }
                                }
                            }
                            $store_acc_wise_array[$dav['product_id']] = $data_array[$dak]['transfer_amount_account_wise'];
                            if ( $tax_transfer_type == 'all' ) {
                                $total_transfer_amount_per_product = 0;
                                foreach ( $store_acc_wise_array[$dav['product_id']] as $sawadpik ) {
                                    $total_transfer_amount_per_product = $total_transfer_amount_per_product + $sawadpik['product_discounted_price_x_qty_total'];
                                    \WC_Stripe_Logger::log( $this->log_prefix . ': sawadpik ' . print_r( $sawadpik, true ) );
                                }
                                \WC_Stripe_Logger::log( $this->log_prefix . ': total_transfer_amount_per_product ' . print_r( $total_transfer_amount_per_product, true ) );
                                foreach ( $store_acc_wise_array[$dav['product_id']] as $sawadpik => $sawadpiv ) {
                                    $partial_transfer_percentage = $sawadpiv['product_discounted_price_x_qty_total'] / $total_transfer_amount_per_product * 100;
                                    $divided_amount = $sawadpiv['tax_total'] / 100;
                                    $account_wise_tax = $divided_amount * $partial_transfer_percentage;
                                    $store_acc_wise_array[$dav['product_id']][$sawadpik]['partial_transfer_percentage'] = $partial_transfer_percentage;
                                    $store_acc_wise_array[$dav['product_id']][$sawadpik]['account_wise_tax'] = $account_wise_tax;
                                }
                            }
                            \WC_Stripe_Logger::log( $this->log_prefix . ': transfer_amount_account_wise ' . print_r( $store_acc_wise_array[$dav['product_id']], true ) );
                            // shipping
                            // $global_shipping_amount = $global_shipping_amount + $data_array[$dak]["product_shipping_cost"];
                            $store_acc_wise_shipping_array[$dav['product_id']] = ( isset( $data_array[$dak]['transfer_shipping_amount_account_wise'] ) ? $data_array[$dak]['transfer_shipping_amount_account_wise'] : array() );
                        }
                    }
                }
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ': data_array after calculation ' . print_r( $data_array, true ) );
            \WC_Stripe_Logger::log( $this->log_prefix . ': global_product_discounted_price_x_qty_total ' . print_r( $global_product_discounted_price_x_qty_total, true ) );
            if ( !empty( $fixed_cart_discounted_amount ) ) {
                $total_global_transfer_amount = $global_product_discounted_price_x_qty_total - $fixed_cart_discounted_amount;
                \WC_Stripe_Logger::log( $this->log_prefix . ': fixed_cart_discounted_amount ' . print_r( $fixed_cart_discounted_amount, true ) );
            } else {
                $total_global_transfer_amount = $global_product_discounted_price_x_qty_total;
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ': total_global_transfer_amount ' . print_r( $total_global_transfer_amount, true ) );
            \WC_Stripe_Logger::log( $this->log_prefix . ': data_keys["bsd_spscwt_type"] ' . print_r( $data_keys['bsd_spscwt_type'], true ) );
            \WC_Stripe_Logger::log( $this->log_prefix . ': default_percentage ' . print_r( $default_percentage, true ) );
            $total_transfer_amount = $total_global_transfer_amount;
            \WC_Stripe_Logger::log( $this->log_prefix . ': total_transfer_amount before subtotal ' . print_r( $total_transfer_amount, true ) );
            $subtotal = $order->get_subtotal();
            if ( $total_transfer_amount > $subtotal ) {
                $total_transfer_amount = $subtotal;
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ': total_transfer_amount after subtotal ' . print_r( $total_transfer_amount, true ) );
            \WC_Stripe_Logger::log( $this->log_prefix . ': store_acc_wise_array after subtotal ' . print_r( $store_acc_wise_array, true ) );
            \WC_Stripe_Logger::log( $this->log_prefix . ': global_shipping_amount ' . print_r( $global_shipping_amount, true ) );
            \WC_Stripe_Logger::log( $this->log_prefix . ': store_acc_wise_shipping_array ' . print_r( $store_acc_wise_shipping_array, true ) );
            $data = array();
            $data['total_global_transfer_amount'] = $total_transfer_amount;
            $data['total_product_transfer_amount'] = $store_acc_wise_array;
            $data['total_transfer_global_shipping_amount'] = $global_shipping_amount;
            $data['total_transfer_product_shipping_amount'] = $store_acc_wise_shipping_array;
            $data['all_taxes_array'] = $all_taxes_array;
            /** tax start */
            $data['data_array'] = $data_array;
            return $data;
        }

        /**
         * Get event meta data (payment_intent.succeeded)
         */
        private function get_event_meta( $request_body ) {
            $data = null;
            $event = json_decode( $request_body );
            $charge_description = '';
            $total_amount = '';
            $source_charge_id = '';
            $source_transaction = '';
            $wc_order_id = '';
            try {
                if ( $event->object == 'event' ) {
                    if ( $event->data->object->id ) {
                        $event = $this->get_payment_intent_object( $event->data->object->id );
                    }
                }
                \WC_Stripe_Logger::log( $this->log_prefix . ': event_data ' . print_r( $event, true ) );
                \WC_Stripe_Logger::log( $this->log_prefix . ': event->charges->data[0]->created ' . print_r( $event->charges->data[0]->created, true ) );
                \WC_Stripe_Logger::log( $this->log_prefix . ': event->data->object->charges->data[0]->created ' . print_r( $event->data->object->charges->data[0]->created, true ) );
                if ( $event->object == 'payment_intent' ) {
                    $charge_created = $event->created;
                    $charge_created = date( 'Y-m-d H:i:s', $charge_created );
                    if ( $event->amount ) {
                        $order_amount = $event->amount;
                        $order_amount = $order_amount / 100;
                    } else {
                        \WC_Stripe_Logger::log( $this->log_prefix . ': get_event_meta amount not found ' );
                        return null;
                    }
                    $charge_description = $event->description;
                    $total_amount = $event->amount;
                    $source_charge_id = $event->latest_charge;
                    $source_transaction = $event->source;
                    $wc_order_id = $event->metadata->order_id;
                } elseif ( $event->charges->data[0]->created ) {
                    $charge_created = $event->charges->data[0]->created;
                    $charge_created = date( 'Y-m-d H:i:s', $charge_created );
                    if ( $event->charges->data[0]->amount ) {
                        $order_amount = $event->charges->data[0]->amount;
                        $order_amount = $order_amount / 100;
                    } else {
                        \WC_Stripe_Logger::log( $this->log_prefix . ': get_event_meta amount not found ' );
                        return null;
                    }
                    $charge_description = $event->charges->data[0]->description;
                    $total_amount = $event->amount;
                    $source_charge_id = $event->charges->data[0]->id;
                    $source_transaction = $event->source;
                    $wc_order_id = $event->charges->data[0]->metadata->order_id;
                } elseif ( $event->data->object->charges->data[0]->created ) {
                    $charge_created = $event->data->object->charges->data[0]->created;
                    $charge_created = date( 'Y-m-d H:i:s', $charge_created );
                    if ( $event->data->object->charges->data[0]->amount ) {
                        $order_amount = $event->data->object->charges->data[0]->amount;
                        $order_amount = $order_amount / 100;
                    } else {
                        \WC_Stripe_Logger::log( $this->log_prefix . ': get_event_meta amount not found ' );
                        return null;
                    }
                    $charge_description = $event->data->object->charges->data[0]->description;
                    $total_amount = $event->data->object->amount;
                    $source_charge_id = $event->data->object->charges->data[0]->id;
                    $source_transaction = $event->data->object->source;
                    $wc_order_id = $event->data->object->charges->data[0]->metadata->order_id;
                } else {
                    \WC_Stripe_Logger::log( $this->log_prefix . ': get_event_meta created not found ' );
                    return null;
                }
                if ( $event->livemode && $event->livemode == 'true' ) {
                    $stripe_mode = 'live';
                } else {
                    $stripe_mode = 'test';
                }
                $data = array(
                    'charge_amount'      => $order_amount,
                    'charge_created'     => $charge_created,
                    'charge_description' => $charge_description,
                    'total_amount'       => $total_amount,
                    'source_charge_id'   => $source_charge_id,
                    'source_transaction' => $source_transaction,
                    'stripe_mode'        => $stripe_mode,
                    'wc_order_id'        => $wc_order_id,
                );
            } catch ( Exception $e ) {
                \WC_Stripe_Logger::log( $this->log_prefix . ': Exception in get_event_meta ' . print_r( $e, true ) );
            }
            return $data;
        }

        private function get_payment_intent_object( $id ) {
            $data_keys = $this->stp_retrieve_keys();
            if ( !$data_keys ) {
                $result_message = 'Missing WooCommerce Stripe settings';
                \WC_Stripe_Logger::log( $this->log_prefix . ': keys not found ' . print_r( $result_message, true ) );
                return null;
            }
            try {
                $stripe_test_mode = $data_keys['stripeInTestMode'] == 'yes';
                if ( $stripe_test_mode ) {
                    $stripe = new \Stripe\StripeClient($data_keys['secretTestKey']);
                } else {
                    $stripe = new \Stripe\StripeClient($data_keys['secretLiveKey']);
                }
                return $stripe->paymentIntents->retrieve( $id, array() );
            } catch ( Exception $e ) {
                \WC_Stripe_Logger::log( $this->log_prefix . ': get_payment_intent_object exception ' . print_r( $e, true ) );
            }
        }

        /**
         * Gets the Stripe event type
         */
        private function get_event_type( $request_body ) {
            $request_body = json_decode( $request_body );
            $event_type = ( $request_body->type ? strtolower( $request_body->type ) : null );
            return $event_type;
        }

        /**
         * Gets the Stripe source ID
         */
        private function get_source_id( $request_body ) {
            $event_type = $this->get_event_type( $request_body );
            $source_id = null;
            if ( !$event_type ) {
                return null;
            }
            $request_body = json_decode( $request_body );
            switch ( $event_type ) {
                case 'charge.succeeded':
                    $source_id = ( isset( $request_body->data->object->source->id ) ? $request_body->data->object->source->id : '' );
                    break;
                case 'payment_intent.succeeded':
                    $source_id = ( isset( $request_body->data->object->source ) ? $request_body->data->object->source : '' );
                    if ( empty( $source_id ) ) {
                        \WC_Stripe_Logger::log( $this->log_prefix . ' payment_method : ' . print_r( $request_body->data->object->payment_method, true ) );
                        $source_id = ( isset( $request_body->data->object->payment_method ) ? $request_body->data->object->payment_method : '' );
                    }
                    break;
                case 'source.chargeable':
                    $source_id = ( isset( $request_body->data->object->id ) ? $request_body->data->object->id : '' );
                    break;
            }
            return $source_id;
        }

        /**
         * Get data from the transfer request
         */
        private function get_transfer_meta( $transfer ) {
            try {
                $data = null;
                // Populate variables from objects. If any required value is null, exit
                if ( isset( $transfer['amount'] ) && $transfer->amount ) {
                    $transfer_amount = $transfer->amount;
                    $transfer_amount = $transfer_amount / 100;
                } else {
                    return null;
                }
                if ( isset( $transfer['id'] ) && $transfer->id ) {
                    $transfer_id = $transfer->id;
                } else {
                    return null;
                }
                if ( isset( $transfer['destination'] ) && $transfer->destination ) {
                    $transfer_destination = $transfer->destination;
                } else {
                    return null;
                }
                if ( isset( $transfer['source_transaction'] ) && $transfer->source_transaction ) {
                    $transfer_source_transaction = $transfer->source_transaction;
                } else {
                    return null;
                }
                $data = array(
                    'transfer_amount'             => $transfer_amount,
                    'transfer_id'                 => $transfer_id,
                    'transfer_destination'        => $transfer_destination,
                    'transfer_source_transaction' => $transfer_source_transaction,
                );
                return $data;
            } catch ( Exception $e ) {
                return null;
            }
        }

        /**
         * Retrieve keys and options
         */
        private function stp_retrieve_keys() {
            // Get settings from WooCommerce Stripe Gateway plugin
            $data = null;
            $woocommerce_stripe_settings = get_option( 'woocommerce_stripe_settings' );
            if ( !$woocommerce_stripe_settings ) {
                return false;
            }
            $wc_stripe_test_mode = ( isset( $woocommerce_stripe_settings['testmode'] ) ? $woocommerce_stripe_settings['testmode'] : null );
            $stripe_test_api_secret_key = get_option( 'stripe_test_api_secret_key', false );
            $stripe_api_secret_key = get_option( 'stripe_api_secret_key', false );
            // Get our settings and theirs
            $data = array(
                'connectedKey'      => get_option( 'bsd_split_pay_stripe_connect_woo_stripe_connected_account' ),
                'bsd_spscwt_type'   => get_option( 'bsd_spscwt_type' ),
                'percentage'        => get_option( 'bsd_split_pay_stripe_connect_woo_transfer_percentage' ),
                'bsd_spscwt_amount' => get_option( 'bsd_spscwt_amount' ),
                'secretTestKey'     => $stripe_test_api_secret_key,
                'secretLiveKey'     => $stripe_api_secret_key,
                'stripeInTestMode'  => $wc_stripe_test_mode,
            );
            return $data;
        }

        /**
         * Determine whether or not to initiate a transfer
         */
        private function transfer_controller( $request_body ) {
            require_once __DIR__ . '/vendor/autoload.php';
            $event_type = $this->get_event_type( $request_body );
            \WC_Stripe_Logger::log( $this->log_prefix . ': transfer_controller event_type ' . print_r( $event_type, true ) );
            if ( $event_type == 'payment_intent.succeeded' ) {
                [
                    $success,
                    $result_message,
                    $transfer,
                    $shipping_transfer,
                    $other_transfer_info
                ] = $this->transfer_to_account( $request_body );
                \WC_Stripe_Logger::log( $this->log_prefix . ': after success transfer_to_account ' . print_r( $success, true ) );
                if ( $success ) {
                    $this->log_transfer_request(
                        $request_body,
                        $transfer,
                        $shipping_transfer,
                        $other_transfer_info
                    );
                } else {
                    // TODO: Notify administrator. For now, error_log it
                    \WC_Stripe_Logger::log( $this->log_prefix . ': ' . print_r( $result_message, true ) );
                }
            }
        }

        /**
         * Transfer to connected account
         */
        private function transfer_to_account( $request_body ) {
            global $wpdb;
            $table_prefix = $wpdb->prefix;
            $table_name = $table_prefix . BSD_SCSP_CONNECTED_ID_TABLE;
            $result_message = null;
            $success = false;
            $transfer = $paymentIntent = array();
            $shipping_transfer = array();
            $other_transfer_info = array();
            $meta_data = array();
            $transfer_description = $cus_first_name = $cus_last_name = '';
            $sending_meta = get_option( 'sending_meta', false );
            $transfer_taxes = get_option( 'transfer_taxes', false );
            $tax_transfer_type = get_option( 'tax_transfer_type', false );
            $data_keys = $this->stp_retrieve_keys();
            if ( !$data_keys ) {
                $result_message = 'Missing WooCommerce Stripe settings';
                return array(
                    $success,
                    $result_message,
                    $transfer,
                    $shipping_transfer,
                    $other_transfer_info
                );
            }
            $event_data = $this->get_event_meta( $request_body );
            \WC_Stripe_Logger::log( $this->log_prefix . ': tax_transfer_type ==> ' . print_r( $tax_transfer_type, true ) );
            if ( $event_data ) {
                try {
                    $stripe_test_mode = $data_keys['stripeInTestMode'] == 'yes';
                    // $exclude_shipping = $data_keys['excludeShipping'] == '1' ? true : false;
                    // $exclude_tax = $data_keys['excludeTax'] == '1' ? true : false;
                    $default_percentage = $data_keys['percentage'] / 100;
                    // Order details
                    $order_id = $event_data['wc_order_id'];
                    $order = wc_get_order( $order_id );
                    $source_transaction = $event_data['source_charge_id'];
                    $total_amount = $event_data['charge_amount'];
                    /* Order Billing */
                    $cus_email = $order->get_billing_email();
                    // Customer billing information details
                    $cus_first_name = $order->get_billing_first_name();
                    $cus_last_name = $order->get_billing_last_name();
                    $transfer_description = $cus_first_name . ' ' . $cus_last_name . ' - Order ' . $order_id;
                    if ( $sending_meta == '1' ) {
                        $meta_data = array(
                            'Site Name'      => get_bloginfo( 'name' ),
                            'Site Url'       => get_bloginfo( 'url' ),
                            'Order Id'       => $order_id,
                            'Customer Name'  => $cus_first_name . ' ' . $cus_last_name,
                            'Customer Email' => $cus_email,
                        );
                    }
                    \WC_Stripe_Logger::log( $this->log_prefix . ': order_id ' . print_r( $order_id, true ) );
                    \WC_Stripe_Logger::log( $this->log_prefix . ': source_transaction ' . print_r( $source_transaction, true ) );
                    if ( !$order_id || !$source_transaction ) {
                        $result_message = 'Missing order data.';
                        return array(
                            $success,
                            $result_message,
                            $transfer,
                            $shipping_transfer,
                            $other_transfer_info
                        );
                    }
                    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) ) {
                        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
                            $is_transfer_process_done = $order->get_meta( 'is_transfer_process_done', true );
                        } else {
                            $is_transfer_process_done = get_post_meta( $order_id, 'is_transfer_process_done', true );
                        }
                    } else {
                        $is_transfer_process_done = get_post_meta( $order_id, 'is_transfer_process_done', true );
                    }
                    if ( !empty( $is_transfer_process_done ) ) {
                        $result_message = 'Transfer already done of this order ==> ' . $order_id;
                        \WC_Stripe_Logger::log( $this->log_prefix . ': Transfer already done of this order ==> ' . print_r( $order_id, true ) );
                        return array(
                            $success,
                            $result_message,
                            $transfer,
                            $shipping_transfer,
                            $other_transfer_info
                        );
                    }
                    $transfer_amount = $this->calculate_transfer_amount( $order_id, $default_percentage, $data_keys );
                    \WC_Stripe_Logger::log( $this->log_prefix . ': returned transfer_amount ' . print_r( $transfer_amount, true ) );
                    if ( empty( $transfer_amount['total_global_transfer_amount'] ) && empty( $transfer_amount['total_product_transfer_amount'] ) ) {
                        $result_message = 'Error calculating transfer amount.';
                        return array(
                            $success,
                            $result_message,
                            $transfer,
                            $shipping_transfer,
                            $other_transfer_info
                        );
                    }
                    if ( $stripe_test_mode ) {
                        \Stripe\Stripe::setApiKey( $data_keys['secretTestKey'] );
                    } else {
                        \Stripe\Stripe::setApiKey( $data_keys['secretLiveKey'] );
                    }
                    $woo_currency = get_woocommerce_currency();
                    $currency = ( $woo_currency ? $woo_currency : 'usd' );
                    $connected_accounts = $wpdb->get_results( 'select * from ' . $table_name . ' ', ARRAY_A );
                    $store_global_transfer_amount = $transfer_amount['total_global_transfer_amount'];
                    $global_tax_total = $transfer_amount['data_array']['global_tax_total'];
                    /** tax start */
                    \WC_Stripe_Logger::log( $this->log_prefix . ': store_global_transfer_amount ' . print_r( $store_global_transfer_amount, true ) );
                    \WC_Stripe_Logger::log( $this->log_prefix . ': global_tax_total ' . print_r( $global_tax_total, true ) );
                    /** tax start */
                    $all_taxes_array = $transfer_amount['all_taxes_array'];
                    /** tax start */
                    \WC_Stripe_Logger::log( $this->log_prefix . ': transfer started for order ==================> ' . print_r( $order_id, true ) );
                    $email_log_global_transfer = array();
                    /** global transfers */
                    $store_total_transffered_amount = 0;
                    $index = 0;
                    if ( !empty( $connected_accounts ) && !empty( $store_global_transfer_amount ) ) {
                        $total_global_connected_accounts = count( $connected_accounts );
                        foreach ( $connected_accounts as $cak ) {
                            if ( $cak['bsd_spscwt_type'] == 'percentage' ) {
                                $percentage_divided = $cak['bsd_spscwt_percentage_amount'] / 100;
                                $actual_transfer_amount = $transfer_amount['total_global_transfer_amount'] * $percentage_divided;
                                $store_total_transffered_amount = $store_total_transffered_amount + $actual_transfer_amount;
                            } else {
                                $store_total_transffered_amount = $store_total_transffered_amount + $cak['bsd_spscwt_percentage_amount'];
                            }
                        }
                        \WC_Stripe_Logger::log( $this->log_prefix . ': store_total_transffered_amount ' . print_r( $store_total_transffered_amount, true ) );
                        foreach ( $connected_accounts as $cak ) {
                            if ( !empty( $store_global_transfer_amount ) ) {
                                $account_wise_tax = 0;
                                if ( $cak['bsd_spscwt_type'] == 'percentage' ) {
                                    $percentage_divided = $cak['bsd_spscwt_percentage_amount'] / 100;
                                    $actual_transfer_amount = $transfer_amount['total_global_transfer_amount'] * $percentage_divided;
                                    /** tax start */
                                    if ( !empty( $global_tax_total ) && $transfer_taxes == '1' && $tax_transfer_type == 'partial' ) {
                                        $account_wise_tax = $global_tax_total * $percentage_divided;
                                        $meta_data['account_wise_tax'] = $account_wise_tax;
                                    } elseif ( !empty( $global_tax_total ) && $transfer_taxes == '1' && $tax_transfer_type == 'all' ) {
                                        if ( $total_global_connected_accounts == 1 ) {
                                            \WC_Stripe_Logger::log( $this->log_prefix . ': total_global_connected_accounts ' . print_r( $total_global_connected_accounts, true ) );
                                            \WC_Stripe_Logger::log( $this->log_prefix . ': total_global_connected_accounts global_tax_total ' . print_r( $global_tax_total, true ) );
                                            $account_wise_tax = $global_tax_total;
                                            $meta_data['account_wise_tax'] = $account_wise_tax;
                                        } else {
                                            $partial_transfer_percentage = $actual_transfer_amount / $store_total_transffered_amount * 100;
                                            \WC_Stripe_Logger::log( $this->log_prefix . ': global partial_transfer_percentage ' . print_r( $partial_transfer_percentage, true ) );
                                            $divided_amount = $global_tax_total / 100;
                                            $account_wise_tax = $divided_amount * $partial_transfer_percentage;
                                            $meta_data['account_wise_tax'] = $account_wise_tax;
                                        }
                                    }
                                    /** tax end */
                                    \WC_Stripe_Logger::log( $this->log_prefix . ': global account_wise_tax ' . print_r( $account_wise_tax, true ) );
                                    \WC_Stripe_Logger::log( $this->log_prefix . ': actual_transfer_amount ' . print_r( $actual_transfer_amount, true ) );
                                    if ( !empty( $actual_transfer_amount ) ) {
                                        if ( $transfer_taxes == '1' ) {
                                            $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $actual_transfer_amount + $account_wise_tax );
                                        } else {
                                            $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $actual_transfer_amount );
                                        }
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': actual_transfer_amount_into_hundred ' . print_r( $actual_transfer_amount_into_hundred, true ) );
                                        if ( $actual_transfer_amount < $store_global_transfer_amount ) {
                                            if ( $index == 0 ) {
                                                // $actual_transfer_amount_into_hundred = $actual_transfer_amount_into_hundred + bsd_wcsc_get_amount($store_product_transfer_amount);
                                                $actual_transfer_amount_into_hundred = $actual_transfer_amount_into_hundred;
                                            }
                                            try {
                                                $transfer[$index] = \Stripe\Transfer::create( array(
                                                    'amount'             => $actual_transfer_amount_into_hundred,
                                                    'currency'           => $currency,
                                                    'source_transaction' => $source_transaction,
                                                    'destination'        => $cak['bsd_connected_account_id'],
                                                    'description'        => $transfer_description,
                                                    'metadata'           => $meta_data,
                                                ) );
                                                $email_log_global_transfer[$index] = array(
                                                    'account_id'                   => $cak['bsd_connected_account_id'],
                                                    'transfer_type'                => $cak['bsd_spscwt_type'],
                                                    'transfer_percentage_or_fixed' => $cak['bsd_spscwt_percentage_amount'],
                                                    'transfer_value'               => $actual_transfer_amount,
                                                    'status'                       => 'success',
                                                    'transfer'                     => $transfer[$index],
                                                    'account_wise_tax'             => $account_wise_tax,
                                                );
                                                if ( !empty( $meta_data ) ) {
                                                    $destination_payment = $transfer[$index]->destination_payment;
                                                    $paymentIntent[$index] = \Stripe\Charge::update( $destination_payment, array(
                                                        'metadata' => $meta_data,
                                                    ), array(
                                                        'stripe_account' => $cak['bsd_connected_account_id'],
                                                    ) );
                                                }
                                                $other_transfer_info[$index]['transfer_type'] = '1';
                                                // 1 stands for global percentage transfer
                                                $other_transfer_info[$index]['entered_transfer_value'] = $cak['bsd_spscwt_percentage_amount'];
                                                $other_transfer_info[$index]['total_global_transfer_amount'] = $transfer_amount['total_global_transfer_amount'];
                                                $other_transfer_info[$index]['global_tax_total'] = $global_tax_total;
                                                if ( $transfer_taxes == '1' ) {
                                                    $other_transfer_info[$index]['account_wise_tax'] = $account_wise_tax;
                                                    $other_transfer_info[$index]['tax_transfer_type'] = $tax_transfer_type;
                                                } else {
                                                    $other_transfer_info[$index]['account_wise_tax'] = 0;
                                                }
                                                \WC_Stripe_Logger::log( $this->log_prefix . ': transfer ' . print_r( $transfer[$index], true ) );
                                                // \WC_Stripe_Logger::log($this->log_prefix . ': PaymentIntent Log ' . print_r($paymentIntent[$index], true));
                                                $store_global_transfer_amount = $store_global_transfer_amount - $actual_transfer_amount;
                                            } catch ( Exception $e ) {
                                                \WC_Stripe_Logger::log( $this->log_prefix . ': transfer didnt happend ' . print_r( $e, true ) );
                                                $email_log_global_transfer[$index] = array(
                                                    'account_id'                   => $cak['bsd_connected_account_id'],
                                                    'transfer_type'                => $cak['bsd_spscwt_type'],
                                                    'transfer_percentage_or_fixed' => $cak['bsd_spscwt_percentage_amount'],
                                                    'transfer_value'               => $actual_transfer_amount,
                                                    'status'                       => 'failed',
                                                    'transfer'                     => array(),
                                                );
                                            }
                                            ++$index;
                                        } else {
                                            break;
                                        }
                                    }
                                } else {
                                    /** tax start */
                                    if ( !empty( $global_tax_total ) && $transfer_taxes == '1' && $tax_transfer_type == 'partial' ) {
                                        $account_wise_tax = $cak['bsd_spscwt_percentage_amount'] / $store_global_transfer_amount * $global_tax_total;
                                        $meta_data['account_wise_tax'] = $account_wise_tax;
                                    } elseif ( !empty( $global_tax_total ) && $transfer_taxes == '1' && $tax_transfer_type == 'all' ) {
                                        $partial_transfer_percentage = $cak['bsd_spscwt_percentage_amount'] / $store_total_transffered_amount * 100;
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': global fixed partial_transfer_percentage ' . print_r( $partial_transfer_percentage, true ) );
                                        $divided_amount = $global_tax_total / 100;
                                        $account_wise_tax = $divided_amount * $partial_transfer_percentage;
                                        $meta_data['account_wise_tax'] = $account_wise_tax;
                                    }
                                    /** tax end */
                                    if ( $transfer_taxes == '1' ) {
                                        $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $cak['bsd_spscwt_percentage_amount'] + $account_wise_tax );
                                    } else {
                                        $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $cak['bsd_spscwt_percentage_amount'] );
                                    }
                                    \WC_Stripe_Logger::log( $this->log_prefix . ': cak["bsd_spscwt_percentage_amount"] ' . print_r( $cak['bsd_spscwt_percentage_amount'], true ) );
                                    \WC_Stripe_Logger::log( $this->log_prefix . ': fixed account_wise_tax ' . print_r( $account_wise_tax, true ) );
                                    \WC_Stripe_Logger::log( $this->log_prefix . ': actual_transfer_amount_into_hundred ' . print_r( $actual_transfer_amount_into_hundred, true ) );
                                    if ( !empty( $actual_transfer_amount_into_hundred ) ) {
                                        if ( $cak['bsd_spscwt_percentage_amount'] < $store_global_transfer_amount ) {
                                            if ( $index == 0 ) {
                                                // $actual_transfer_amount_into_hundred = $actual_transfer_amount_into_hundred + bsd_wcsc_get_amount($store_product_transfer_amount);
                                                $actual_transfer_amount_into_hundred = $actual_transfer_amount_into_hundred;
                                            }
                                            try {
                                                $transfer[$index] = \Stripe\Transfer::create( array(
                                                    'amount'             => $actual_transfer_amount_into_hundred,
                                                    'currency'           => $currency,
                                                    'source_transaction' => $source_transaction,
                                                    'destination'        => $cak['bsd_connected_account_id'],
                                                    'description'        => $transfer_description,
                                                    'metadata'           => $meta_data,
                                                ) );
                                                $email_log_global_transfer[$index] = array(
                                                    'account_id'                   => $cak['bsd_connected_account_id'],
                                                    'transfer_type'                => $cak['bsd_spscwt_type'],
                                                    'transfer_percentage_or_fixed' => $cak['bsd_spscwt_percentage_amount'],
                                                    'transfer_value'               => $cak['bsd_spscwt_percentage_amount'],
                                                    'status'                       => 'success',
                                                    'transfer'                     => $transfer[$index],
                                                    'account_wise_tax'             => $account_wise_tax,
                                                );
                                                if ( !empty( $meta_data ) ) {
                                                    $destination_payment = $transfer[$index]->destination_payment;
                                                    $paymentIntent[$index] = \Stripe\Charge::update( $destination_payment, array(
                                                        'metadata' => $meta_data,
                                                    ), array(
                                                        'stripe_account' => $cak['bsd_connected_account_id'],
                                                    ) );
                                                }
                                                $other_transfer_info[$index]['transfer_type'] = '2';
                                                // 2 stands for global fixed transfer
                                                $other_transfer_info[$index]['entered_transfer_value'] = $cak['bsd_spscwt_percentage_amount'];
                                                $other_transfer_info[$index]['total_global_transfer_amount'] = $transfer_amount['total_global_transfer_amount'];
                                                $other_transfer_info[$index]['global_tax_total'] = $global_tax_total;
                                                if ( $transfer_taxes == '1' ) {
                                                    $other_transfer_info[$index]['account_wise_tax'] = $account_wise_tax;
                                                    $other_transfer_info[$index]['tax_transfer_type'] = $tax_transfer_type;
                                                } else {
                                                    $other_transfer_info[$index]['account_wise_tax'] = 0;
                                                }
                                                \WC_Stripe_Logger::log( $this->log_prefix . ': transfer ' . print_r( $transfer[$index], true ) );
                                                // \WC_Stripe_Logger::log($this->log_prefix . ': PaymentIntent ' . print_r($paymentIntent[$index], true));
                                                $store_global_transfer_amount = $store_global_transfer_amount - $cak['bsd_spscwt_percentage_amount'];
                                            } catch ( Exception $e ) {
                                                $email_log_global_transfer[$index] = array(
                                                    'account_id'                   => $cak['bsd_connected_account_id'],
                                                    'transfer_type'                => $cak['bsd_spscwt_type'],
                                                    'transfer_percentage_or_fixed' => $cak['bsd_spscwt_percentage_amount'],
                                                    'transfer_value'               => $cak['bsd_spscwt_percentage_amount'],
                                                    'status'                       => 'failed',
                                                    'transfer'                     => array(),
                                                );
                                                \WC_Stripe_Logger::log( $this->log_prefix . ': transfer didn\'t happend ' . print_r( $e, true ) );
                                            }
                                            ++$index;
                                        } else {
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    /** product wise transfer product amount */
                    $email_log_product_transfer = array();
                    $store_product_transfer_amount = $transfer_amount['total_product_transfer_amount'];
                    \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - store_product_transfer_amount ' . print_r( $store_product_transfer_amount, true ) );
                    if ( !empty( $store_product_transfer_amount ) ) {
                        foreach ( $store_product_transfer_amount as $sptak => $sptav ) {
                            \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - sptak ' . print_r( $sptak, true ) );
                            \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - sptav ' . print_r( $sptav, true ) );
                            foreach ( $sptav as $sptavk => $sptavv ) {
                                \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - sptak ' . print_r( $sptak, true ) );
                                \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - sptav ' . print_r( $sptav, true ) );
                                \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - account_wise_tax ' . print_r( $sptavv['account_wise_tax'], true ) );
                                \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - actual_transfer_amount ' . print_r( $sptavv['product_discounted_price_x_qty_total'], true ) );
                                if ( $transfer_taxes == '1' ) {
                                    $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $sptavv['product_discounted_price_x_qty_total'] + $sptavv['account_wise_tax'] );
                                } else {
                                    $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $sptavv['product_discounted_price_x_qty_total'] );
                                }
                                \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - actual_transfer_amount_into_hundred ' . print_r( $actual_transfer_amount_into_hundred, true ) );
                                if ( !empty( $actual_transfer_amount_into_hundred ) ) {
                                    try {
                                        if ( !empty( $meta_data ) ) {
                                            $transfer_meta = array(
                                                'Site Name'                     => get_bloginfo( 'name' ),
                                                'Site Url'                      => get_bloginfo( 'url' ),
                                                'product_id'                    => $sptavv['product_id'],
                                                'transfer_type'                 => $sptavv['transfer_type'],
                                                'transfer_percentage_or_amount' => $sptavv['transfer_percentage_or_amount'],
                                                'Order Id'                      => $order_id,
                                                'Customer Name'                 => $cus_first_name . ' ' . $cus_last_name,
                                                'Customer Email'                => $cus_email,
                                                'account_wise_tax'              => $sptavv['account_wise_tax'],
                                            );
                                        }
                                        $transfer[$index] = \Stripe\Transfer::create( array(
                                            'amount'             => $actual_transfer_amount_into_hundred,
                                            'currency'           => $currency,
                                            'source_transaction' => $source_transaction,
                                            'destination'        => $sptavv['connected_acc_id'],
                                            'description'        => $transfer_description,
                                            'metadata'           => $transfer_meta,
                                        ) );
                                        $email_log_product_transfer[$index] = array(
                                            'product_id'                   => $sptavv['product_id'],
                                            'account_id'                   => $sptavv['connected_acc_id'],
                                            'transfer_type'                => $sptavv['transfer_type'],
                                            'transfer_percentage_or_fixed' => $sptavv['transfer_percentage_or_amount'],
                                            'transfer_value'               => $sptavv['product_discounted_price_x_qty_total'],
                                            'status'                       => 'success',
                                            'transfer'                     => $transfer[$index],
                                            'account_wise_tax'             => $sptavv['account_wise_tax'],
                                        );
                                        if ( !empty( $meta_data ) ) {
                                            $destination_payment = $transfer[$index]->destination_payment;
                                            $paymentIntent[$index] = \Stripe\Charge::update( $destination_payment, array(
                                                'metadata' => $meta_data,
                                            ), array(
                                                'stripe_account' => $sptavv['connected_acc_id'],
                                            ) );
                                        }
                                        if ( $sptavv['product_type'] == 'simple' ) {
                                            if ( $sptavv['transfer_type'] == 'percentage' ) {
                                                $other_transfer_info[$index]['transfer_type'] = '8';
                                                // 8 stands for simple product percentage transfer
                                            } else {
                                                $other_transfer_info[$index]['transfer_type'] = '9';
                                                // 9 stands for simple product fixed transfer
                                            }
                                        } elseif ( $sptavv['product_type'] == 'variation' ) {
                                            if ( $sptavv['transfer_type'] == 'percentage' ) {
                                                $other_transfer_info[$index]['transfer_type'] = '10';
                                                // 10 stands for variable product percentage transfer
                                            } else {
                                                $other_transfer_info[$index]['transfer_type'] = '11';
                                                // 11 stands for variable product fixed transfer
                                            }
                                        }
                                        $other_transfer_info[$index]['entered_transfer_value'] = $sptavv['transfer_percentage_or_amount'];
                                        if ( $transfer_taxes == '1' ) {
                                            $other_transfer_info[$index]['account_wise_tax'] = $sptavv['account_wise_tax'];
                                            $other_transfer_info[$index]['tax_transfer_type'] = $tax_transfer_type;
                                            $other_transfer_info[$index]['product_tax_total'] = $sptavv['tax_total'];
                                        } else {
                                            $other_transfer_info[$index]['account_wise_tax'] = 0;
                                        }
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - transfer ' . print_r( $transfer[$index], true ) );
                                        // \WC_Stripe_Logger::log($this->log_prefix . ': acc_wise - PaymentIntent ' . print_r($paymentIntent[$index], true));
                                    } catch ( Exception $e ) {
                                        $email_log_product_transfer[$index] = array(
                                            'product_id'                   => $sptavv['product_id'],
                                            'account_id'                   => $sptavv['connected_acc_id'],
                                            'transfer_type'                => $sptavv['transfer_type'],
                                            'transfer_percentage_or_fixed' => $sptavv['transfer_percentage_or_amount'],
                                            'transfer_value'               => $sptavv['product_discounted_price_x_qty_total'],
                                            'status'                       => 'failed',
                                            'transfer'                     => array(),
                                        );
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - transfer didn\'t happend ' . print_r( $e, true ) );
                                    }
                                    ++$index;
                                }
                            }
                        }
                    }
                    /** global shipping variables */
                    $email_log_global_shipping_transfer = array();
                    $total_transfer_global_shipping_amount = $transfer_amount['total_transfer_global_shipping_amount'];
                    $adjustable_transfer_global_shipping_amount = $transfer_amount['total_transfer_global_shipping_amount'];
                    \WC_Stripe_Logger::log( $this->log_prefix . ': total_transfer_global_shipping_amount ' . print_r( $total_transfer_global_shipping_amount, true ) );
                    /** global shipping transfer */
                    $store_total_transffered_amount = 0;
                    // $all_taxes_array
                    $total_shipping_tax = 0;
                    if ( !empty( $all_taxes_array ) ) {
                        foreach ( $all_taxes_array as $atak ) {
                            \WC_Stripe_Logger::log( $this->log_prefix . ': atak ' . print_r( $atak, true ) );
                            $total_shipping_tax = $total_shipping_tax + $atak['tax_ship_total'];
                        }
                    }
                    \WC_Stripe_Logger::log( $this->log_prefix . ': all_taxes_array ' . print_r( $all_taxes_array, true ) );
                    \WC_Stripe_Logger::log( $this->log_prefix . ': total_shipping_tax ' . print_r( $total_shipping_tax, true ) );
                    if ( !empty( $connected_accounts ) && !empty( $total_transfer_global_shipping_amount ) ) {
                        $shipping_index = $index;
                        $total_global_connected_accounts = count( $connected_accounts );
                        foreach ( $connected_accounts as $cak ) {
                            if ( $cak['bsd_global_shipping_type'] == 'percentage' ) {
                                $percentage_divided = $cak['bsd_global_shipping_percentage_amount'] / 100;
                                $actual_transfer_amount = $total_transfer_global_shipping_amount * $percentage_divided;
                                $store_total_transffered_amount = $store_total_transffered_amount + $actual_transfer_amount;
                            } else {
                                $store_total_transffered_amount = $store_total_transffered_amount + $cak['bsd_global_shipping_percentage_amount'];
                            }
                        }
                        foreach ( $connected_accounts as $cak ) {
                            if ( !empty( $adjustable_transfer_global_shipping_amount ) ) {
                                if ( isset( $cak['bsd_global_shipping_type'] ) && !empty( $cak['bsd_global_shipping_type'] ) && isset( $cak['bsd_global_shipping_percentage_amount'] ) && !empty( $cak['bsd_global_shipping_percentage_amount'] ) ) {
                                    \WC_Stripe_Logger::log( $this->log_prefix . ': shipping adjustable_transfer_global_shipping_amount ' . print_r( $adjustable_transfer_global_shipping_amount, true ) );
                                    $account_wise_tax = 0;
                                    if ( $cak['bsd_global_shipping_type'] == 'percentage' ) {
                                        $percentage_divided = $cak['bsd_global_shipping_percentage_amount'] / 100;
                                        $actual_transfer_amount = $total_transfer_global_shipping_amount * $percentage_divided;
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': shipping actual_transfer_amount ' . print_r( $actual_transfer_amount, true ) );
                                        /** tax start */
                                        if ( !empty( $total_shipping_tax ) && $transfer_taxes == '1' && $tax_transfer_type == 'partial' ) {
                                            $account_wise_tax = $total_shipping_tax * $percentage_divided;
                                            $meta_data['account_wise_tax'] = $account_wise_tax;
                                        } elseif ( !empty( $total_shipping_tax ) && $transfer_taxes == '1' && $tax_transfer_type == 'all' ) {
                                            if ( $total_global_connected_accounts == 1 ) {
                                                \WC_Stripe_Logger::log( $this->log_prefix . ': total_global_connected_accounts ' . print_r( $total_global_connected_accounts, true ) );
                                                \WC_Stripe_Logger::log( $this->log_prefix . ': total_global_connected_accounts total_shipping_tax ' . print_r( $total_shipping_tax, true ) );
                                                $account_wise_tax = $total_shipping_tax;
                                                $meta_data['account_wise_tax'] = $account_wise_tax;
                                            } else {
                                                $partial_transfer_percentage = $actual_transfer_amount / $store_total_transffered_amount * 100;
                                                \WC_Stripe_Logger::log( $this->log_prefix . ': global partial_transfer_percentage ' . print_r( $partial_transfer_percentage, true ) );
                                                $divided_amount = $total_shipping_tax / 100;
                                                $account_wise_tax = $divided_amount * $partial_transfer_percentage;
                                                $meta_data['account_wise_tax'] = $account_wise_tax;
                                            }
                                        }
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': shipping percentage account_wise_tax ' . print_r( $account_wise_tax, true ) );
                                        /** tax end */
                                        if ( !empty( $actual_transfer_amount ) ) {
                                            if ( $transfer_taxes == '1' ) {
                                                $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $actual_transfer_amount + $account_wise_tax );
                                            } else {
                                                $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $actual_transfer_amount );
                                            }
                                            \WC_Stripe_Logger::log( $this->log_prefix . ': shipping actual_transfer_amount_into_hundred ' . print_r( $actual_transfer_amount_into_hundred, true ) );
                                            if ( $actual_transfer_amount <= $adjustable_transfer_global_shipping_amount ) {
                                                try {
                                                    $shipping_transfer[$shipping_index] = \Stripe\Transfer::create( array(
                                                        'amount'             => $actual_transfer_amount_into_hundred,
                                                        'currency'           => $currency,
                                                        'source_transaction' => $source_transaction,
                                                        'destination'        => $cak['bsd_connected_account_id'],
                                                        'description'        => $transfer_description,
                                                        'metadata'           => $meta_data,
                                                    ) );
                                                    $email_log_global_shipping_transfer[$shipping_index] = array(
                                                        'account_id'                   => $cak['bsd_connected_account_id'],
                                                        'transfer_type'                => $cak['bsd_global_shipping_type'],
                                                        'transfer_percentage_or_fixed' => $cak['bsd_global_shipping_percentage_amount'],
                                                        'transfer_value'               => $actual_transfer_amount,
                                                        'status'                       => 'success',
                                                        'transfer'                     => $shipping_transfer[$shipping_index],
                                                        'account_wise_tax'             => $account_wise_tax,
                                                    );
                                                    if ( !empty( $meta_data ) ) {
                                                        $destination_payment = $shipping_transfer[$shipping_index]->destination_payment;
                                                        $paymentIntent[$shipping_index] = \Stripe\Charge::update( $destination_payment, array(
                                                            'metadata' => $meta_data,
                                                        ), array(
                                                            'stripe_account' => $cak['bsd_connected_account_id'],
                                                        ) );
                                                    }
                                                    $other_transfer_info[$shipping_index]['shipping_transfer_type'] = '3';
                                                    // 3 stands for global shipping percentage transfer
                                                    $other_transfer_info[$shipping_index]['entered_transfer_value'] = $cak['bsd_global_shipping_percentage_amount'];
                                                    $other_transfer_info[$shipping_index]['account_wise_tax'] = $account_wise_tax;
                                                    $other_transfer_info[$shipping_index]['total_transfer_global_shipping_amount'] = $total_transfer_global_shipping_amount;
                                                    $other_transfer_info[$shipping_index]['tax_transfer_type'] = $tax_transfer_type;
                                                    $other_transfer_info[$shipping_index]['total_shipping_tax'] = $total_shipping_tax;
                                                    \WC_Stripe_Logger::log( $this->log_prefix . ': shipping transfer ' . print_r( $shipping_transfer[$shipping_index], true ) );
                                                    // \WC_Stripe_Logger::log($this->log_prefix . ': shipping transfer PaymentIntent' . print_r($paymentIntent[$shipping_index], true));
                                                    $adjustable_transfer_global_shipping_amount = $adjustable_transfer_global_shipping_amount - $actual_transfer_amount;
                                                } catch ( Exception $e ) {
                                                    $email_log_global_shipping_transfer[$shipping_index] = array(
                                                        'account_id'                   => $cak['bsd_connected_account_id'],
                                                        'transfer_type'                => $cak['bsd_global_shipping_type'],
                                                        'transfer_percentage_or_fixed' => $cak['bsd_global_shipping_percentage_amount'],
                                                        'transfer_value'               => $actual_transfer_amount,
                                                        'status'                       => 'failed',
                                                        'transfer'                     => array(),
                                                    );
                                                    \WC_Stripe_Logger::log( $this->log_prefix . ': shipping didn\'t happend ' . print_r( $e, true ) );
                                                }
                                            }
                                        }
                                    } else {
                                        /** tax start */
                                        if ( !empty( $total_shipping_tax ) && $transfer_taxes == '1' && $tax_transfer_type == 'partial' ) {
                                            $account_wise_tax = $cak['bsd_global_shipping_percentage_amount'] / $store_total_transffered_amount * $total_shipping_tax;
                                            $meta_data['account_wise_tax'] = $account_wise_tax;
                                        } elseif ( !empty( $total_shipping_tax ) && $transfer_taxes == '1' && $tax_transfer_type == 'all' ) {
                                            $partial_transfer_percentage = $cak['bsd_global_shipping_percentage_amount'] / $store_total_transffered_amount * 100;
                                            \WC_Stripe_Logger::log( $this->log_prefix . ': global shipping fixed partial_transfer_percentage ' . print_r( $partial_transfer_percentage, true ) );
                                            $divided_amount = $total_shipping_tax / 100;
                                            $account_wise_tax = $divided_amount * $partial_transfer_percentage;
                                            $meta_data['account_wise_tax'] = $account_wise_tax;
                                        }
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': shipping fixed account_wise_tax ' . print_r( $account_wise_tax, true ) );
                                        /** tax end */
                                        if ( $transfer_taxes == '1' ) {
                                            $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $cak['bsd_global_shipping_percentage_amount'] + $account_wise_tax );
                                        } else {
                                            $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $cak['bsd_global_shipping_percentage_amount'] );
                                        }
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': cak["bsd_global_shipping_percentage_amount"] ' . print_r( $cak['bsd_global_shipping_percentage_amount'], true ) );
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': actual_transfer_amount_into_hundred ' . print_r( $actual_transfer_amount_into_hundred, true ) );
                                        if ( !empty( $actual_transfer_amount_into_hundred ) ) {
                                            \WC_Stripe_Logger::log( $this->log_prefix . ': actual_transfer_amount_into_hundred inner if ' . print_r( $actual_transfer_amount_into_hundred, true ) );
                                            if ( $cak['bsd_global_shipping_percentage_amount'] < $adjustable_transfer_global_shipping_amount ) {
                                                \WC_Stripe_Logger::log( $this->log_prefix . ': adjustable_transfer_global_shipping_amount inner if ' . print_r( $adjustable_transfer_global_shipping_amount, true ) );
                                                try {
                                                    $shipping_transfer[$shipping_index] = \Stripe\Transfer::create( array(
                                                        'amount'             => $actual_transfer_amount_into_hundred,
                                                        'currency'           => $currency,
                                                        'source_transaction' => $source_transaction,
                                                        'destination'        => $cak['bsd_connected_account_id'],
                                                        'description'        => $transfer_description,
                                                        'metadata'           => $meta_data,
                                                    ) );
                                                    $email_log_global_shipping_transfer[$shipping_index] = array(
                                                        'account_id'                   => $cak['bsd_connected_account_id'],
                                                        'transfer_type'                => $cak['bsd_global_shipping_type'],
                                                        'transfer_percentage_or_fixed' => $cak['bsd_global_shipping_percentage_amount'],
                                                        'transfer_value'               => $cak['bsd_global_shipping_percentage_amount'],
                                                        'status'                       => 'success',
                                                        'transfer'                     => $shipping_transfer[$shipping_index],
                                                        'account_wise_tax'             => $account_wise_tax,
                                                    );
                                                    if ( !empty( $meta_data ) ) {
                                                        $destination_payment = $shipping_transfer[$shipping_index]->destination_payment;
                                                        $paymentIntent[$shipping_index] = \Stripe\Charge::update( $destination_payment, array(
                                                            'metadata' => $meta_data,
                                                        ), array(
                                                            'stripe_account' => $cak['bsd_connected_account_id'],
                                                        ) );
                                                    }
                                                    $other_transfer_info[$shipping_index]['shipping_transfer_type'] = '4';
                                                    // 4 stands for global shipping fixed transfer
                                                    $other_transfer_info[$shipping_index]['entered_transfer_value'] = $cak['bsd_global_shipping_percentage_amount'];
                                                    $other_transfer_info[$shipping_index]['account_wise_tax'] = $account_wise_tax;
                                                    $other_transfer_info[$shipping_index]['total_transfer_global_shipping_amount'] = $total_transfer_global_shipping_amount;
                                                    $other_transfer_info[$shipping_index]['tax_transfer_type'] = $tax_transfer_type;
                                                    $other_transfer_info[$shipping_index]['total_shipping_tax'] = $total_shipping_tax;
                                                    \WC_Stripe_Logger::log( $this->log_prefix . ': shipping transfer ' . print_r( $shipping_transfer[$shipping_index], true ) );
                                                    // \WC_Stripe_Logger::log($this->log_prefix . ': shipping transfer PaymentIntent' . print_r($paymentIntent[$shipping_index], true));
                                                    $adjustable_transfer_global_shipping_amount = $adjustable_transfer_global_shipping_amount - $cak['bsd_global_shipping_percentage_amount'];
                                                } catch ( Exception $e ) {
                                                    $email_log_global_shipping_transfer[$shipping_index] = array(
                                                        'account_id'                   => $cak['bsd_connected_account_id'],
                                                        'transfer_type'                => $cak['bsd_global_shipping_type'],
                                                        'transfer_percentage_or_fixed' => $cak['bsd_global_shipping_percentage_amount'],
                                                        'transfer_value'               => $cak['bsd_global_shipping_percentage_amount'],
                                                        'status'                       => 'failed',
                                                        'transfer'                     => array(),
                                                    );
                                                    \WC_Stripe_Logger::log( $this->log_prefix . ': shipping transfer didn\'t happend ' . print_r( $e, true ) );
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            ++$shipping_index;
                        }
                    }
                    /** product shipping variables */
                    $email_log_product_shipping_transfer = array();
                    $total_transfer_product_shipping_amount = $transfer_amount['total_transfer_product_shipping_amount'];
                    \WC_Stripe_Logger::log( $this->log_prefix . ': acc_wise - total_transfer_product_shipping_amount ' . print_r( $total_transfer_product_shipping_amount, true ) );
                    /** product shipping variables */
                    if ( !empty( $total_transfer_product_shipping_amount ) ) {
                        foreach ( $total_transfer_product_shipping_amount as $sptak => $sptav ) {
                            \WC_Stripe_Logger::log( $this->log_prefix . ': shipping acc_wise - sptak ' . print_r( $sptak, true ) );
                            \WC_Stripe_Logger::log( $this->log_prefix . ': shipping acc_wise - sptav ' . print_r( $sptav, true ) );
                            foreach ( $sptav as $sptavk => $sptavv ) {
                                \WC_Stripe_Logger::log( $this->log_prefix . ': shipping acc_wise - sptak ' . print_r( $sptak, true ) );
                                \WC_Stripe_Logger::log( $this->log_prefix . ': shipping acc_wise - sptav ' . print_r( $sptav, true ) );
                                \WC_Stripe_Logger::log( $this->log_prefix . ': shipping acc_wise - actual_transfer_amount ' . print_r( $sptavv['transfer_calculate_amount'], true ) );
                                $actual_transfer_amount_into_hundred = bsd_wcsc_get_amount( $sptavv['transfer_calculate_amount'] );
                                \WC_Stripe_Logger::log( $this->log_prefix . ': shipping acc_wise - actual_transfer_amount_into_hundred ' . print_r( $actual_transfer_amount_into_hundred, true ) );
                                if ( !empty( $actual_transfer_amount_into_hundred ) ) {
                                    try {
                                        if ( !empty( $meta_data ) ) {
                                            $transfer_meta = array(
                                                'Site Name'                     => get_bloginfo( 'name' ),
                                                'Site Url'                      => get_bloginfo( 'url' ),
                                                'product_id'                    => $sptavv['product_id'],
                                                'transfer_type'                 => $sptavv['transfer_type'],
                                                'transfer_percentage_or_amount' => $sptavv['transfer_percentage_or_amount'],
                                                'Order Id'                      => $order_id,
                                                'Customer Name'                 => $cus_first_name . ' ' . $cus_last_name,
                                                'Customer Email'                => $cus_email,
                                            );
                                        }
                                        $shipping_transfer[$shipping_index] = \Stripe\Transfer::create( array(
                                            'amount'             => $actual_transfer_amount_into_hundred,
                                            'currency'           => $currency,
                                            'source_transaction' => $source_transaction,
                                            'destination'        => $sptavv['connected_acc_id'],
                                            'description'        => $transfer_description,
                                            'metadata'           => $transfer_meta,
                                        ) );
                                        $email_log_product_shipping_transfer[$shipping_index] = array(
                                            'product_id'                   => $sptavv['product_id'],
                                            'account_id'                   => $sptavv['connected_acc_id'],
                                            'transfer_type'                => $sptavv['transfer_type'],
                                            'transfer_percentage_or_fixed' => $sptavv['transfer_percentage_or_amount'],
                                            'transfer_value'               => $sptavv['transfer_calculate_amount'],
                                            'status'                       => 'success',
                                            'transfer'                     => $shipping_transfer[$shipping_index],
                                        );
                                        if ( !empty( $meta_data ) ) {
                                            $destination_payment = $shipping_transfer[$shipping_index]->destination_payment;
                                            $paymentIntent[$shipping_index] = \Stripe\Charge::update( $destination_payment, array(
                                                'metadata' => $meta_data,
                                            ), array(
                                                'stripe_account' => $sptavv['connected_acc_id'],
                                            ) );
                                        }
                                        if ( $sptavv['transfer_type'] == 'percentage' ) {
                                            $other_transfer_info[$shipping_index]['shipping_transfer_type'] = '6';
                                            // 6 stands for product shiping percentage transfer
                                        } else {
                                            $other_transfer_info[$shipping_index]['shipping_transfer_type'] = '7';
                                            // 7 stands for product shiping fixed transfer
                                        }
                                        $other_transfer_info[$shipping_index]['entered_transfer_value'] = $sptavv['transfer_percentage_or_amount'];
                                        $other_transfer_info[$shipping_index]['account_wise_tax'] = 0;
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': shipping acc_wise - transfer ' . print_r( $shipping_transfer[$shipping_index], true ) );
                                        // \WC_Stripe_Logger::log($this->log_prefix . ': shipping acc_wise - transfer PaymentIntent ' . print_r($paymentIntent[$shipping_index], true));
                                    } catch ( Exception $e ) {
                                        $email_log_product_shipping_transfer[$shipping_index] = array(
                                            'product_id'                   => $sptavv['product_id'],
                                            'account_id'                   => $sptavv['connected_acc_id'],
                                            'transfer_type'                => $sptavv['transfer_type'],
                                            'transfer_percentage_or_fixed' => $sptavv['transfer_percentage_or_amount'],
                                            'transfer_value'               => $sptavv['transfer_calculate_amount'],
                                            'status'                       => 'failed',
                                            'transfer'                     => array(),
                                        );
                                        \WC_Stripe_Logger::log( $this->log_prefix . ': shipping transfer didn\'t happend ' . print_r( $e, true ) );
                                    }
                                    ++$shipping_index;
                                }
                            }
                        }
                    }
                    \WC_Stripe_Logger::log( $this->log_prefix . ': transfer ended for order ==================> ' . print_r( $order_id, true ) );
                    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) ) {
                        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
                            $order->update_meta_data( 'is_transfer_process_done', 1 );
                        } else {
                            update_post_meta( $order_id, 'is_transfer_process_done', 1 );
                        }
                    } else {
                        update_post_meta( $order_id, 'is_transfer_process_done', 1 );
                    }
                    $success = true;
                    $result_message = 'Transfer successful';
                    $transfer_email = get_option( 'transfer_email', false );
                    if ( $transfer_email == '1' ) {
                        $data_email = array(
                            'global_transfer'           => $email_log_global_transfer,
                            'global_shippint_transfer'  => $email_log_global_shipping_transfer,
                            'product_transfer'          => $email_log_product_transfer,
                            'product_shipping_transfer' => $email_log_product_shipping_transfer,
                        );
                        \WC_Stripe_Logger::log( $this->log_prefix . ': data_email ' . print_r( $data_email, true ) );
                        WC()->mailer()->emails['WC_Transfer_Order_Email']->trigger( $order_id, $data_email );
                    }
                    \WC_Stripe_Logger::log( $this->log_prefix . ': transfer array for log ' . print_r( $transfer, true ) );
                    \WC_Stripe_Logger::log( $this->log_prefix . ': shipping_transfer array for log ' . print_r( $shipping_transfer, true ) );
                    \WC_Stripe_Logger::log( $this->log_prefix . ': other_transfer_info array for log ' . print_r( $other_transfer_info, true ) );
                    if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) ) {
                        if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
                            $order->save();
                        }
                    }
                    return array(
                        $success,
                        $result_message,
                        $transfer,
                        $shipping_transfer,
                        $other_transfer_info
                    );
                } catch ( \InvalidRequestException $e ) {
                    // Invalid request
                    $result_message = "Invalid request: {$e}";
                    \WC_Stripe_Logger::log( $this->log_prefix . ': transfer Invalid request' . print_r( $result_message, true ) );
                    return array(
                        $success,
                        $result_message,
                        $transfer,
                        $shipping_transfer,
                        $other_transfer_info
                    );
                } catch ( Exception $e ) {
                    if ( stristr( $e, 'no such destination' ) ) {
                        $account_number = substr( $e, strpos( $e, 'acct_' ) );
                        $account_number = explode( ' ', trim( $account_number ) );
                        $account_number = $account_number[0];
                        $result_message = "Ignored; cannot find account number: {$account_number}. Stripe replied 'No such destination.'";
                        \WC_Stripe_Logger::log( $this->log_prefix . ': ' . print_r( $result_message, true ) );
                        return array(
                            $success,
                            $result_message,
                            $transfer,
                            $shipping_transfer,
                            $other_transfer_info
                        );
                    } else {
                        $result_message = "Exception: {$e}";
                        \WC_Stripe_Logger::log( $this->log_prefix . ': ' . print_r( $result_message, true ) );
                        return array(
                            $success,
                            $result_message,
                            $transfer,
                            $shipping_transfer,
                            $other_transfer_info
                        );
                    }
                }
            } else {
                $result_message = 'Could not retreive order meta data.';
                \WC_Stripe_Logger::log( $this->log_prefix . ': ' . print_r( $result_message, true ) );
                return array(
                    $success,
                    $result_message,
                    $transfer,
                    $shipping_transfer,
                    $other_transfer_info
                );
            }
        }

        /**
         * Log the transfer request
         */
        private function log_transfer_request(
            $event,
            $transfer,
            $shipping_transfer,
            $other_transfer_info
        ) {
            $event_info = $this->get_event_meta( $event );
            global $wpdb;
            if ( !empty( $transfer ) ) {
                foreach ( $transfer as $trk => $trv ) {
                    \WC_Stripe_Logger::log( $this->log_prefix . '$trk --- : ' . print_r( $trk, true ) );
                    \WC_Stripe_Logger::log( $this->log_prefix . '$trv --- : ' . print_r( $trv, true ) );
                    \WC_Stripe_Logger::log( $this->log_prefix . '$other_transfer_info --- : ' . print_r( $other_transfer_info, true ) );
                    $transfer_info = $this->get_transfer_meta( $trv );
                    $transfer_type = 0;
                    $entered_transfer_value = 0;
                    $account_wise_tax = 0;
                    $item_total = 0;
                    $item_tax_total = 0;
                    $tax_transfer_type = '';
                    if ( isset( $other_transfer_info[$trk]['transfer_type'] ) && !empty( $other_transfer_info[$trk]['transfer_type'] ) ) {
                        $transfer_type = $other_transfer_info[$trk]['transfer_type'];
                    }
                    if ( isset( $other_transfer_info[$trk]['entered_transfer_value'] ) && !empty( $other_transfer_info[$trk]['entered_transfer_value'] ) ) {
                        $entered_transfer_value = $other_transfer_info[$trk]['entered_transfer_value'];
                    }
                    if ( isset( $other_transfer_info[$trk]['account_wise_tax'] ) && !empty( $other_transfer_info[$trk]['account_wise_tax'] ) ) {
                        $account_wise_tax = $other_transfer_info[$trk]['account_wise_tax'];
                    }
                    if ( isset( $other_transfer_info[$trk]['total_global_transfer_amount'] ) && !empty( $other_transfer_info[$trk]['total_global_transfer_amount'] ) ) {
                        $item_total = $other_transfer_info[$trk]['total_global_transfer_amount'];
                    }
                    if ( isset( $other_transfer_info[$trk]['global_tax_total'] ) && !empty( $other_transfer_info[$trk]['global_tax_total'] ) ) {
                        $item_tax_total = $other_transfer_info[$trk]['global_tax_total'];
                    }
                    if ( isset( $other_transfer_info[$trk]['tax_transfer_type'] ) && !empty( $other_transfer_info[$trk]['tax_transfer_type'] ) ) {
                        $tax_transfer_type = $other_transfer_info[$trk]['tax_transfer_type'];
                    }
                    if ( isset( $other_transfer_info[$trk]['product_tax_total'] ) && !empty( $other_transfer_info[$trk]['product_tax_total'] ) ) {
                        $item_tax_total = $other_transfer_info[$trk]['product_tax_total'];
                    }
                    if ( !$event_info || !$transfer_info ) {
                        return;
                    }
                    // If the transfer isn't related to this charge, exit
                    if ( $event_info['source_charge_id'] != $transfer_info['transfer_source_transaction'] ) {
                        return;
                    }
                    $table_prefix = $wpdb->prefix;
                    $table = $table_prefix . 'bsd_scsp_transfer_log';
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) == $table ) {
                        $data = array(
                            'wc_order_id'            => $event_info['wc_order_id'],
                            'charge_amount'          => $event_info['charge_amount'],
                            'transfer_amount'        => $transfer_info['transfer_amount'],
                            'transfer_entered_value' => $entered_transfer_value,
                            'charge_id'              => $event_info['source_charge_id'],
                            'charge_date'            => $event_info['charge_created'],
                            'charge_description'     => $event_info['charge_description'],
                            'transfer_id'            => $transfer_info['transfer_id'],
                            'transfer_destination'   => $transfer_info['transfer_destination'],
                            'stripe_mode'            => $event_info['stripe_mode'],
                            'transfer_type'          => $transfer_type,
                            'transfer_tax_value'     => $account_wise_tax,
                            'item_total'             => $item_total,
                            'item_tax_total'         => $item_tax_total,
                            'tax_transfer_type'      => $tax_transfer_type,
                        );
                        $wpdb->insert( $table, $data );
                    }
                }
            }
            if ( !empty( $shipping_transfer ) ) {
                \WC_Stripe_Logger::log( $this->log_prefix . '$shipping_transfer --- : ' . print_r( $shipping_transfer, true ) );
                foreach ( $shipping_transfer as $trk => $trv ) {
                    \WC_Stripe_Logger::log( $this->log_prefix . '$trv --- : ' . print_r( $trv, true ) );
                    \WC_Stripe_Logger::log( $this->log_prefix . '$other_transfer_info --- : ' . print_r( $other_transfer_info, true ) );
                    $transfer_info = $this->get_transfer_meta( $trv );
                    $transfer_type = 0;
                    $entered_transfer_value = 0;
                    $account_wise_tax = 0;
                    $item_total = 0;
                    $item_tax_total = 0;
                    $tax_transfer_type = '';
                    if ( isset( $other_transfer_info[$trk]['shipping_transfer_type'] ) && !empty( $other_transfer_info[$trk]['shipping_transfer_type'] ) ) {
                        $transfer_type = $other_transfer_info[$trk]['shipping_transfer_type'];
                    }
                    if ( isset( $other_transfer_info[$trk]['entered_transfer_value'] ) && !empty( $other_transfer_info[$trk]['entered_transfer_value'] ) ) {
                        $entered_transfer_value = $other_transfer_info[$trk]['entered_transfer_value'];
                    }
                    if ( isset( $other_transfer_info[$trk]['account_wise_tax'] ) && !empty( $other_transfer_info[$trk]['account_wise_tax'] ) ) {
                        $account_wise_tax = $other_transfer_info[$trk]['account_wise_tax'];
                    }
                    if ( isset( $other_transfer_info[$trk]['total_transfer_global_shipping_amount'] ) && !empty( $other_transfer_info[$trk]['total_transfer_global_shipping_amount'] ) ) {
                        $item_total = $other_transfer_info[$trk]['total_transfer_global_shipping_amount'];
                    }
                    if ( isset( $other_transfer_info[$trk]['total_shipping_tax'] ) && !empty( $other_transfer_info[$trk]['total_shipping_tax'] ) ) {
                        $item_tax_total = $other_transfer_info[$trk]['total_shipping_tax'];
                    }
                    if ( isset( $other_transfer_info[$trk]['tax_transfer_type'] ) && !empty( $other_transfer_info[$trk]['tax_transfer_type'] ) ) {
                        $tax_transfer_type = $other_transfer_info[$trk]['tax_transfer_type'];
                    }
                    if ( !$event_info || !$transfer_info ) {
                        return;
                    }
                    // If the transfer isn't related to this charge, exit
                    if ( $event_info['source_charge_id'] != $transfer_info['transfer_source_transaction'] ) {
                        return;
                    }
                    $table_prefix = $wpdb->prefix;
                    $table = $table_prefix . 'bsd_scsp_transfer_log';
                    if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) == $table ) {
                        $data = array(
                            'wc_order_id'            => $event_info['wc_order_id'],
                            'charge_amount'          => $event_info['charge_amount'],
                            'transfer_amount'        => $transfer_info['transfer_amount'],
                            'transfer_entered_value' => $entered_transfer_value,
                            'charge_id'              => $event_info['source_charge_id'],
                            'charge_date'            => $event_info['charge_created'],
                            'charge_description'     => $event_info['charge_description'],
                            'transfer_id'            => $transfer_info['transfer_id'],
                            'transfer_destination'   => $transfer_info['transfer_destination'],
                            'stripe_mode'            => $event_info['stripe_mode'],
                            'transfer_type'          => $transfer_type,
                            'transfer_tax_value'     => $account_wise_tax,
                            'item_total'             => $item_total,
                            'item_tax_total'         => $item_tax_total,
                            'tax_transfer_type'      => $tax_transfer_type,
                        );
                        $wpdb->insert( $table, $data );
                    }
                }
            }
        }

        /**
         * Check if the webhook is for this site
         */
        private function webhook_for_this_site( $request_body ) {
            // Look for source id
            $source_id = $this->get_source_id( $request_body );
            if ( empty( $source_id ) ) {
                \WC_Stripe_Logger::log( $this->log_prefix . ' source_id not found: ' . print_r( $source_id, true ) );
                return false;
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ' source_id : ' . print_r( $source_id, true ) );
            // Check with Woo to see if it's a valid order
            $order = \WC_Stripe_Helper::get_order_by_source_id( $source_id );
            // If yes return true
            if ( $order ) {
                return true;
            }
            \WC_Stripe_Logger::log( $this->log_prefix . ' get_order_by_source_id not found: ' . print_r( $source_id, true ) );
            // If no return false
            return false;
        }

        public function bsd_variable_product_fields( $loop, $variation_data, $variation ) {
            ob_start();
            require BSD_SCSP_PLUGIN_DIR . '/includes/admin/partials/bsd-variable-product-table.php';
            $variable_product_data_html = ob_get_clean();
            echo $variable_product_data_html;
        }

        public function bsd_variable_product_fields_save( $variation_id, $i ) {
            $_bsd_spscwt_product_connected_account = $_POST['_bsd_spscwt_product_connected_account'][$variation_id] ?? null;
            $_bsd_spscwt_product_type = $_POST['_bsd_spscwt_product_type'][$variation_id] ?? null;
            $transfer_percentage = $_POST['_stripe_connect_split_pay_transfer_percentage'][$variation_id] ?? null;
            $_bsd_spscwt_product_amount = $_POST['_bsd_spscwt_product_amount'][$variation_id] ?? null;
            /* shipping */
            $_bsd_spscwt_shipping_type = $_POST['bsd_spscwt_shipping_type'][$variation_id] ?? null;
            $_bsd_prod_shipping_percentage = $_POST['bsd_prod_shipping_percentage'][$variation_id] ?? null;
            $_bsd_prod_shipping_amount = $_POST['bsd_prod_shipping_amount'][$variation_id] ?? null;
            $prod_valid_connected_account = array();
            if ( !empty( $_bsd_spscwt_product_connected_account ) ) {
                foreach ( $_bsd_spscwt_product_connected_account as $bspcak => $bspcav ) {
                    if ( !empty( $bspcav ) && $_bsd_spscwt_product_type[$bspcak] == 'percentage' && !empty( $transfer_percentage[$bspcak] ) ) {
                        $prod_valid_connected_account[$bspcak] = $bspcav;
                    } elseif ( !empty( $bspcav ) && $_bsd_spscwt_product_type[$bspcak] == 'amount' && !empty( $_bsd_spscwt_product_amount[$bspcak] ) ) {
                        $prod_valid_connected_account[$bspcak] = $bspcav;
                    }
                }
            }
            update_post_meta( $variation_id, '_bsd_spscwt_product_connected_account', $prod_valid_connected_account );
            if ( empty( $_bsd_spscwt_product_type ) ) {
                delete_post_meta( $variation_id, '_bsd_spscwt_product_type' );
                delete_post_meta( $variation_id, '_stripe_connect_split_pay_transfer_percentage' );
                delete_post_meta( $variation_id, '_bsd_spscwt_product_amount' );
            } else {
                update_post_meta( $variation_id, '_bsd_spscwt_product_type', $_bsd_spscwt_product_type );
            }
            if ( $transfer_percentage === null ) {
                delete_post_meta( $variation_id, '_stripe_connect_split_pay_transfer_percentage' );
            } else {
                update_post_meta( $variation_id, '_stripe_connect_split_pay_transfer_percentage', $transfer_percentage );
            }
            if ( empty( $_bsd_spscwt_product_amount ) ) {
                delete_post_meta( $variation_id, '_bsd_spscwt_product_amount' );
            } else {
                update_post_meta( $variation_id, '_bsd_spscwt_product_amount', $_bsd_spscwt_product_amount );
            }
            /* shipping */
            if ( empty( $_bsd_spscwt_shipping_type ) ) {
                delete_post_meta( $variation_id, '_bsd_spscwt_shipping_type' );
                delete_post_meta( $variation_id, '_bsd_prod_shipping_percentage' );
                delete_post_meta( $variation_id, '_bsd_prod_shipping_amount' );
            } else {
                update_post_meta( $variation_id, '_bsd_spscwt_shipping_type', $_bsd_spscwt_shipping_type );
            }
            if ( $_bsd_prod_shipping_percentage === null ) {
                delete_post_meta( $variation_id, '_bsd_prod_shipping_percentage' );
            } else {
                update_post_meta( $variation_id, '_bsd_prod_shipping_percentage', $_bsd_prod_shipping_percentage );
            }
            if ( empty( $_bsd_prod_shipping_amount ) ) {
                delete_post_meta( $variation_id, '_bsd_prod_shipping_amount' );
            } else {
                update_post_meta( $variation_id, '_bsd_prod_shipping_amount', $_bsd_prod_shipping_amount );
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
// class_exists check
function bsd_wcsc_get_amount(  $total, $currency = ''  ) {
    $zero_decimals = array(
        'BIF',
        'CLP',
        'DJF',
        'GNF',
        'JPY',
        'KMF',
        'KRW',
        'MGA',
        'PYG',
        'RWF',
        'VND',
        'VUV',
        'XAF',
        'XOF',
        'XPF'
    );
    if ( empty( $currency ) ) {
        $currency = get_woocommerce_currency();
    }
    $currency = strtoupper( $currency );
    if ( !in_array( $currency, $zero_decimals ) ) {
        $total *= 100;
    }
    return round( $total );
}
