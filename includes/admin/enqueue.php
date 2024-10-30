<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin;

if (!function_exists('BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin\bsd_scsp_admin_enqueue')) {
    function bsd_scsp_admin_enqueue()
    {
        $wp_screen       = get_current_screen();
        $allowed_screens = [
            "woocommerce_page_bsd-split-pay-stripe-connect-woo-settings",
            "product",
            "toplevel_page_split-pay-stripe-connect",
        ];

        if ($wp_screen && in_array($wp_screen->id, $allowed_screens)) {
            wp_register_style('bsd_scsp_admin', plugins_url('/includes/admin/assets/css/bsd-split-pay-stripe-connect-woo-admin.css', BSD_SCSP_PLUGIN_URL), [], time());
            wp_enqueue_style('bsd_scsp_admin');

            wp_register_style('bsd_scsp_data_tables', plugins_url('/includes/vendor/jquery-data-tables/jquery.dataTables.min.css', BSD_SCSP_PLUGIN_URL));
            wp_enqueue_style('bsd_scsp_data_tables');

            wp_register_script('bsd_scsp_data_tables', plugins_url('/includes/vendor/jquery-data-tables/jquery.dataTables.min.js', BSD_SCSP_PLUGIN_URL), '1.0.0', true);
            wp_enqueue_script('bsd_scsp_data_tables');


            wp_register_style('bsd_select2_style', BSD_SCSP_PLUGIN_URI . "/includes/vendor/select2/select2.min.css", [], BSD_SCSP_PLUGIN_VER);
            wp_enqueue_style('bsd_select2_style');

            wp_register_script('bsd_select2_script', BSD_SCSP_PLUGIN_URI . "includes/vendor/select2/select2.min.js", ["jquery"], BSD_SCSP_PLUGIN_VER, true);
            wp_enqueue_script('bsd_select2_script');

            wp_register_script('bsd_plugin_admin_script', BSD_SCSP_PLUGIN_URI . "includes/admin/assets/js/bsd-spscwa.js", ["jquery"], time(), true);
            wp_enqueue_script('bsd_plugin_admin_script');

            wp_register_script('bsd_plugin_admin_build_script', BSD_SCSP_PLUGIN_URI . "includes/admin/assets/js/main.js", ["jquery"], time(), true);
            wp_enqueue_script('bsd_plugin_admin_build_script');

            $bsd_admin_plugin_vars = [
                "ajax_url"                 => admin_url('admin-ajax.php'),
                "can_use_premium_code"     => \BSD_Split_Pay_Stripe_Connect_Woo\bsdwcscsp_fs()->can_use_premium_code(),
                "bsd_scsp_plugin_uri"      => BSD_SCSP_PLUGIN_URI,
                "plugin_admin_setting_url" => admin_url('admin.php?page=bsd-split-pay-stripe-connect-woo-account'),
                'security' => wp_create_nonce('ajax-security')
            ];

            wp_localize_script('bsd_plugin_admin_script', "bsd_admin_plugin_vars", $bsd_admin_plugin_vars);
        }
    }
}
