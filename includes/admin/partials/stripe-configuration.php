<?php
// phpcs:ignoreFile

$woo_stripe_settings_url = admin_url("admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings");

$woocommerce_stripe_settings = get_option( "woocommerce_stripe_settings" );
if(!isset($woocommerce_stripe_settings["enabled"]) || $woocommerce_stripe_settings["enabled"] == "no"){
    ?>
        <p>
            <b>
                <?php
                    echo sprintf( __( "Please <a href='%s' target='_blank'>connect Stripe</a> in the WooCommerce Stripe Payment Gateway plugin settings.", 'bsd-split-pay-stripe-connect-woo' ), $woo_stripe_settings_url ); 
                ?>
            </b>
        </p>
    <?php
    
    return '';
}
$test_mode = $woocommerce_stripe_settings["testmode"];

$sps_test_webhook_update = get_option( "sps_test_webhook_update", false );
$sps_webhook_update = get_option( "sps_webhook_update", false );

$upgrade_url = BSD_SCSP_PLUGIN_UPGRADE_URL;

if ( function_exists( 'bsdwcscsp_fs' ) ) {
	$upgrade_url = bsdwcscsp_fs()->get_upgrade_url();
}

$stripe_test_api_public_key   = get_option( "stripe_test_api_public_key", false );
$stripe_test_api_secret_key   = get_option( "stripe_test_api_secret_key", false );

$stripe_api_public_key   = get_option( "stripe_api_public_key", false );
$stripe_api_secret_key   = get_option( "stripe_api_secret_key", false );
if(!empty($stripe_test_api_public_key)){
    // $total_string_length = max(strlen( $stripe_test_api_public_key ) - 70, 15);
    $total_string_length = 10;
    $stripe_test_api_public_key = substr($stripe_test_api_public_key, 0, 10) . str_repeat("*", $total_string_length) . substr($stripe_test_api_public_key, -2) ;
}
if(!empty($stripe_test_api_secret_key)){
    $total_string_length = 10;
    $stripe_test_api_secret_key = substr($stripe_test_api_secret_key, 0, 10) . str_repeat("*", $total_string_length) . substr($stripe_test_api_secret_key, -2) ;
}

if(!empty($stripe_api_public_key)){
    $total_string_length = 10;
    $stripe_api_public_key = substr($stripe_api_public_key, 0, 10) . str_repeat("*", $total_string_length) . substr($stripe_api_public_key, -2) ;
}
if(!empty($stripe_api_secret_key)){
    $total_string_length = 10;
    $stripe_api_secret_key = substr($stripe_api_secret_key, 0, 10) . str_repeat("*", $total_string_length) . substr($stripe_api_secret_key, -2) ;
}
if($test_mode == "yes"){
    $stripe_api_keys_link = "https://dashboard.stripe.com/test/apikeys";
}else{
    $stripe_api_keys_link = "https://dashboard.stripe.com/apikeys";
}
$tab_url = admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-settings&tab=main' );
?>
<div class='bsd-scsp-settings'>
    <input type='hidden' name='tab_name' value='stripe-configuration'>
	<?php
	    settings_fields( 'spp_stripe_configuration_options' );
	?>

    <h2 class="section_headings"><?php esc_html_e('Step 1 - Configure API Keys', 'bsd-split-pay-stripe-connect-woo' ); ?></h2>
    <p>
        <?php _e( 'The <a href="'.$stripe_api_keys_link.'" target="_blank">API keys</a> used for Transfers must be from the Stripe Platform account connected to the <a href="'.$woo_stripe_settings_url.'" target="_blank">WooCommerce Stripe Payment Gateway</a> plugin. Read our <a href="https://docs.splitpayplugin.com/getting-started/quick-start" target="_blank">documentation</a> for details.', 'bsd-split-pay-stripe-connect-woo' ); ?> 
    </p>
    
    <div class="form-table keys_table_wrapper">
                
        <table class="keys_table">
           
            <tr>
                <td>
                    <div class="form-block-row">
                        <label for="stripe_test_api_public_key"><?php esc_html_e('Stripe Test Publishable Key', 'bsd-split-pay-stripe-connect-woo' ); ?></label>
                        <div class="form-block-field">
                            <input type="text" name="stripe_test_api_public_key" id="stripe_test_api_public_key"
                                placeholder="<?php esc_html_e('Stripe Test Publishable Key', 'bsd-split-pay-stripe-connect-woo' ); ?>" value="<?php echo esc_attr($stripe_test_api_public_key); ?>"/>
                        </div>
                    </div>
                </td>
                <td>

                    <div class="form-block-row">
                        <label for="stripe_test_api_secret_key"><?php esc_html_e('Stripe Test Secret Key', 'bsd-split-pay-stripe-connect-woo' ); ?></label>
                        <div class="form-block-field">
                            <input type="text" name="stripe_test_api_secret_key" id="stripe_test_api_secret_key"
                                placeholder="<?php esc_html_e('Stripe Test Secret Key', 'bsd-split-pay-stripe-connect-woo' ); ?>" value="<?php echo esc_attr($stripe_test_api_secret_key); ?>"/>
                        </div>
                    </div>

                </td>
                
            </tr>
            <tr>
                <td>
                    <div class="form-block-row">
                        <label for="stripe_api_public_key"><?php esc_html_e('Stripe Live Publishable Key', 'bsd-split-pay-stripe-connect-woo' ); ?></label>
                        <div class="form-block-field">
                            <input type="text" name="stripe_api_public_key" id="stripe_api_public_key"
                                placeholder="<?php esc_html_e('Stripe Live Publishable Key', 'bsd-split-pay-stripe-connect-woo' ); ?>" value="<?php echo esc_attr($stripe_api_public_key); ?>"/>
                        </div>
                    </div>
                </td>
                <td>

                        <div class="form-block-row">
                            <label for="stripe_api_secret_key"><?php esc_html_e('Stripe Live Secret Key', 'bsd-split-pay-stripe-connect-woo' ); ?></label>
                            <div class="form-block-field">
                                <input type="text" name="stripe_api_secret_key" id="stripe_api_secret_key"
                                    placeholder="<?php esc_html_e('Stripe Live Secret Key', 'bsd-split-pay-stripe-connect-woo' ); ?>" value="<?php echo esc_attr($stripe_api_secret_key); ?>"/>
                            </div>
                        </div>

                </td>
            </tr>
        </table>

    </div>


    <div class="form-block-submit">
		<?php
		// do_settings_sections('bsd-split-pay-stripe-connect-woo');
		submit_button();
		?>
    </div>

    <?php 
        $is_all_sca_fetched = get_option( 'is_all_sca_fetched', false );
    ?>
    <h2 class="section_headings step2_heading"><?php esc_html_e('Step 2 - Sync Webhook for Transfer Events', 'bsd-split-pay-stripe-connect-woo' ); ?></h2>

    <table class="form-table sync_webhook_table" role="presentation">
        <tbody>

            <tr class="webhook_button_row_wrapper">
                <th scope="row">
                    <a href="javascript:;" id="sync_webhooks" class="button button-secondary bsd-spscws-btns">

                                <span>
                                    <?php esc_html_e( 'Sync Webhook', 'bsd-split-pay-stripe-connect-woo' ); ?>
                                </span>
                        <img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/loader.gif" alt="loader" class="bsd_loader"/>
                    </a>
                    
                </th>
                <td>
                    <p class="bsd-scsp-helper-text"><?php esc_html_e( 'Adds the Transfer events to the WooCommerce webhook endpoint automatically (WooCommerce Stripe Payment Gateway plugin).', 'bsd-split-pay-stripe-connect-woo' ) ?></p>
                </td>
            </tr>
            <tr class="webhook_response_status_upate <?php /* echo (empty($sps_test_webhook_update))? "bsd_hidden":""; */ ?>">
                <td colspan="2">
                    <p class="test_mode_message_wrapper">
                        <?php 
                            if($sps_test_webhook_update == "1"){
                                echo"✅ "; esc_html_e( 'Test Mode Webhook Configured', 'bsd-split-pay-stripe-connect-woo' );
                            }else{
                                echo "❌ "; esc_html_e( 'Reconfigure Test Mode Webhook', 'bsd-split-pay-stripe-connect-woo' );   
                            }
                        ?>
                    </p>
                    <p class="live_mode_message_wrapper">
                        <?php 
                            if($sps_webhook_update == "1"){
                                echo"✅ "; esc_html_e( 'Live Mode Webhook Configured', 'bsd-split-pay-stripe-connect-woo' );
                            }else{
                                echo "❌ "; esc_html_e( 'Reconfigure Live Mode Webhook', 'bsd-split-pay-stripe-connect-woo' );   
                            }
                        ?>
                    </p>
                </td>
                
            </tr>

        
        </tbody>
    </table>

    <h2 class="section_headings step3_heading"><?php esc_html_e('Step 3 - Sync Stripe Connected Accounts', 'bsd-split-pay-stripe-connect-woo' ); ?></h2>

    <table class="form-table sync_webhook_table" role="presentation">
        <tbody>

            <tr class="sync_account_btn_wrapper">
                <th scope="row">
                    <a href="javascript:;" id="sync_accounts_btn" class="button button-secondary bsd-spscws-btns">

                                <span id="sync_btn_text">
                                    <?php esc_html_e( 'Sync Stripe Connected Accounts', 'bsd-split-pay-stripe-connect-woo' ); ?>
                                </span>
                        <img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/loader.gif" alt="loader" class="bsd_loader"/>
                    </a>
                    <?php
                    if ( $is_all_sca_fetched ) {
                        ?>
                        <a href="javascript:;" id="csync_accounts_btn" class="button button-secondary bsd-spscws-btns">

                                        <span id="csync_btn_text">
                                            <?php esc_html_e( 'Clear Synced Stripe Data', 'bsd-split-pay-stripe-connect-woo' ); ?>
                                        </span>
                            <img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/loader.gif" alt="loader" class="bsd_loader"/>
                        </a>
                        
                        <?php
                    }
                    ?>
                </th>
                <style>
                    .bsd-spscws-btns {
                        margin-bottom: 10px !important;
                    }
                </style>
                <td>
                    <p class="bsd-scsp-helper-text"><?php esc_html_e( 'Syncing allows you to search Connected Stripe accounts by Account Name or ID. This may take more than 30 seconds. Do not refresh the page.', 'bsd-split-pay-stripe-connect-woo' ) ?></p>
                </td>
            </tr>
            
        </tbody>
    </table>

    <h2 class="section_headings step4_heading"><?php esc_html_e('Step 4 - Configure', 'bsd-split-pay-stripe-connect-woo' ); ?> <a href="<?php echo $tab_url; ?>" target="_blank"><?php esc_html_e('Transfer Settings', 'bsd-split-pay-stripe-connect-woo' ); ?></a></h2>
</div>
