<?php

/**
 * Plugin Name: Split Pay for Stripe Connect on WooCommerce
 * Description:       Split payments made in WooCommerce stores between a Stripe Connected Account and a Stripe Platform Account
 * Version:           3.5.1
 * Tested up to:      6.7
 * Author:            Gaucho Plugins
 * Author URI:        https://gauchoplugins.com
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0
 * Text Domain:       bsd-split-pay-stripe-connect-woo
 * Domain Path:       /languages
 *
 * WC requires at least: 3.7.1
 * WC tested up to: 5.2.2
 *
 * @package     bspscw
 */
namespace BSD_Split_Pay_Stripe_Connect_Woo;

// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) {
    die;
}
if ( !function_exists( 'add_action' ) ) {
    echo 'Hello! I\'m just a plugin. There\'s nothing I can do when called directly.';
    exit;
}
// Begin Freemius.
if ( function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\bsdwcscsp_fs' ) ) {
    bsdwcscsp_fs()->set_basename( false, __FILE__ );
} else {
    if ( !function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\bsdwcscsp_fs' ) ) {
        /** Create a helper function for easy SDK access. */
        function bsdwcscsp_fs() {
            global $bsdwcscsp_fs;
            if ( !isset( $bsdwcscsp_fs ) ) {
                // Include Freemius SDK.
                require_once __DIR__ . '/freemius/start.php';
                $bsdwcscsp_fs = fs_dynamic_init( array(
                    'id'             => '5491',
                    'slug'           => 'bsd-split-pay-stripe-connect-woo',
                    'premium_slug'   => 'split-pay-plugin-pro',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_87287c4bbc0e070483f667959b2a1',
                    'is_premium'     => false,
                    'premium_suffix' => 'PRO',
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'menu'           => array(
                        'first-path' => 'plugins.php',
                        'support'    => false,
                    ),
                    'is_live'        => true,
                ) );
            }
            return $bsdwcscsp_fs;
        }

        // Init Freemius.
        bsdwcscsp_fs();
        // Signal that SDK was initiated.
        do_action( 'bsdwcscsp_fs_loaded' );
    }
    // End Freemius.
    // Setup.
    if ( !defined( 'BSD_SCSP_PLUGIN_VER' ) ) {
        if ( SCRIPT_DEBUG ) {
            define( 'BSD_SCSP_PLUGIN_VER', time() );
        } else {
            define( 'BSD_SCSP_PLUGIN_VER', '3.5.1' );
        }
    }
    if ( !defined( 'BSD_SCSP_PLUGIN_MAIN_FILE_PATH' ) ) {
        define( 'BSD_SCSP_PLUGIN_MAIN_FILE_PATH', __FILE__ );
    }
    if ( !defined( 'BSD_SCSP_PLUGIN_DIR' ) ) {
        define( 'BSD_SCSP_PLUGIN_DIR', __DIR__ );
    }
    if ( !defined( 'BSD_SCSP_PLUGIN_URL' ) ) {
        define( 'BSD_SCSP_PLUGIN_URL', __FILE__ );
    }
    if ( !defined( 'BSD_SCSP_PLUGIN_URI' ) ) {
        define( 'BSD_SCSP_PLUGIN_URI', plugin_dir_url( __FILE__ ) );
    }
    if ( !defined( 'BSD_SCSP_PLUGIN_ASSETS' ) ) {
        define( 'BSD_SCSP_PLUGIN_ASSETS', plugin_dir_url( __FILE__ ) . 'assets' );
    }
    if ( !defined( 'BSD_SCSP_PLUGIN_BASE_NAME' ) ) {
        define( 'BSD_SCSP_PLUGIN_BASE_NAME', plugin_basename( __FILE__ ) );
    }
    if ( !defined( 'BSD_SCSP_PLUGIN_INCLUDES' ) ) {
        define( 'BSD_SCSP_PLUGIN_INCLUDES', BSD_SCSP_PLUGIN_DIR . '/includes' );
    }
    if ( !defined( 'BSD_SCSP_PLUGIN_UPGRADE_URL' ) ) {
        define( 'BSD_SCSP_PLUGIN_UPGRADE_URL', '?billing_cycle=annual&page=bsd-split-pay-stripe-connect-woo-pricing' );
    }
    if ( !defined( 'BSD_SCSP_STRP_ACCNT_TABLE' ) ) {
        define( 'BSD_SCSP_STRP_ACCNT_TABLE', 'bsd_strp_accnt' );
    }
    if ( !defined( 'SPP_ALLOWED_HTML_TAGS' ) ) {
        define( 'SPP_ALLOWED_HTML_TAGS', array(
            'a' => array(
                'href'   => array(),
                'target' => array(),
            ),
        ) );
    }
    define( 'BSD_SCSP_SCA_PER_PAGE', 100 );
    define( 'BSD_SCSP_TEST_CONNECT_ID', 'ca_NCjrUW8UGB4XZ2L6bRCi9vYD6pppYwlc' );
    define( 'BSD_SCSP_LIVE_CONNECT_ID', 'ca_NCjrUW8UGB4XZ2L6bRCi9vYD6pppYwlc' );
    if ( !defined( 'BSD_TRANSFER_LOG_TABLE' ) ) {
        define( 'BSD_TRANSFER_LOG_TABLE', 'bsd_scsp_transfer_log' );
    }
    if ( !defined( 'BSD_SCSP_CONNECTED_ID_TABLE' ) ) {
        define( 'BSD_SCSP_CONNECTED_ID_TABLE', 'bsd_strp_connected_id' );
    }
    function bsdwcscsp_fs_custom_icon() {
        return BSD_SCSP_PLUGIN_DIR . '/assets/bsd-split-pay-stripe-connect-woo-icon-300x300.png';
    }

    // Load plugin icon from custom path.
    bsdwcscsp_fs()->add_filter( 'plugin_icon', 'BSD_Split_Pay_Stripe_Connect_Woo\\bsdwcscsp_fs_custom_icon' );
    // Includes.
    include 'includes/init.php';
    include 'includes/admin/settings-api.php';
    include 'includes/admin/init.php';
    include 'includes/activate.php';
    include 'includes/class-bsd-split-pay-stripe-connect-woo.php';
    include 'includes/admin/menus.php';
    include 'includes/admin/options-page.php';
    include 'includes/admin/class-bsd-sca.php';
    // Hooks.
    register_activation_hook( __FILE__, 'BSD_Split_Pay_Stripe_Connect_Woo\\Inc\\bsd_scsp_activate_plugin' );
    add_action( 'init', 'BSD_Split_Pay_Stripe_Connect_Woo\\Inc\\bsd_scsp_db_update' );
    add_action( 'admin_init', 'BSD_Split_Pay_Stripe_Connect_Woo\\Inc\\Admin\\bsd_scsp_admin_init' );
    add_action( 'admin_menu', 'BSD_Split_Pay_Stripe_Connect_Woo\\Inc\\Admin\\bsd_scsp_admin_menus' );
    // Consider moving the following functions to a separate class.
    /**
     * Check for the existence of WooCommerce and any other requirements.
     */
    if ( !function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\bsd_scsp_check_requirements' ) ) {
        function bsd_scsp_check_requirements() {
            $plugin_dependency_active = is_plugin_active( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' );
            $wc_plugin_dependency_active = is_plugin_active( 'woocommerce/woocommerce.php' );
            if ( !$plugin_dependency_active ) {
                add_action( 'admin_notices', 'BSD_Split_Pay_Stripe_Connect_Woo\\bsd_scsp_missing_wc_notice' );
                return false;
            }
            if ( !$wc_plugin_dependency_active ) {
                add_action( 'admin_notices', 'BSD_Split_Pay_Stripe_Connect_Woo\\bsd_scsp_missing_wc_notice' );
                return false;
            }
            return true;
        }

    }
    global $bsd_sca, $bsd_stripe_account_tbl_ver;
    $bsd_stripe_account_tbl_ver = '1.0';
    /**
     * Begins execution of the plugin.
     */
    if ( !function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\run_BSD_Split_Pay_Stripe_Connect_Woo' ) ) {
        function run_BSD_Split_Pay_Stripe_Connect_Woo() {
            if ( bsd_scsp_check_requirements() ) {
                global $bsd_sca;
                $plugin = new Inc\BSD_SCSP_Main_Plugin();
                $plugin->run();
                $bsd_sca = new Inc\Admin\BSD_SCA();
                $bsd_sca->run();
            }
        }

    }
    run_BSD_Split_Pay_Stripe_Connect_Woo();
    /**
     * Display a message advising WooCommerce is required.
     */
    if ( !function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\bsd_scsp_missing_wc_notice' ) ) {
        function bsd_scsp_missing_wc_notice() {
            if ( is_multisite() ) {
                $woocommerce_link = '<a href="' . network_admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) . '" target="_blank">WooCommerce</a>';
                $woocommerce_sg_link = '<a href="' . network_admin_url( 'plugin-install.php?s=WooCommerce Stripe Payment Gateway&tab=search&type=term' ) . '" target="_blank">WooCommerce Stripe Payment Gateway</a>';
            } else {
                $woocommerce_link = '<a href="' . admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) . '" target="_blank">WooCommerce</a>';
                $woocommerce_sg_link = '<a href="' . admin_url( 'plugin-install.php?s=WooCommerce Stripe Payment Gateway&tab=search&type=term' ) . '" target="_blank">WooCommerce Stripe Payment Gateway</a>';
            }
            $class = 'notice notice-error';
            $message = sprintf( __( 'The Split Pay Plugin requires %1$s and %2$s plugins to be installed and active.', 'bsd-split-pay-stripe-connect-woo' ), $woocommerce_link, $woocommerce_sg_link );
            printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
        }

    }
}