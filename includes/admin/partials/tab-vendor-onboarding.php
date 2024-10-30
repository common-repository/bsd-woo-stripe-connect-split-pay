<?php

// phpcs:ignoreFile
$woocommerce_stripe_settings = get_option( "woocommerce_stripe_settings" );
$upgrade_url = BSD_SCSP_PLUGIN_UPGRADE_URL;
if ( function_exists( 'bsdwcscsp_fs' ) ) {
    $upgrade_url = bsdwcscsp_fs()->get_upgrade_url();
}
$text = sprintf( esc_html__( 'You can onboard Vendors in the Stripe Dashboard. If you want to onboard Vendors through your WordPress site, please configure the settings below. Read our %sdocumentation%s for details.', 'bsd-split-pay-stripe-connect-woo' ), '<a href="https://docs.splitpayplugin.com/features/connecting-vendor-stripe-accounts" target="_blank">', '</a>' );
?>
<div class='bsd-scsp-settings'>
    <input type='hidden' name='tab_name' value='vendor_onboarding'>
	<?php 
settings_fields( 'spp_vendor_options' );
echo '<p>' . wp_kses_post( $text ) . ' </p>';
/** table started */
if ( empty( $woocommerce_stripe_settings ) ) {
    echo sprintf( __( "Please <a href='%s'>connect your Stripe Platform account</a>", 'bsd-split-pay-stripe-connect-woo' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' ) );
    return "";
}
$documentation_link = '';
$can_use_premium_code = \BSD_Split_Pay_Stripe_Connect_Woo\bsdwcscsp_fs()->can_use_premium_code();
$upgrade_text = sprintf( __( '<a href="%s" target="_blank">Upgrade ></a>', 'bsd-split-pay-stripe-connect-woo' ), $upgrade_url );
$vendor_onboading = get_option( "vendor_onboading", false );
$enable_title_description = get_option( "enable_title_description", false );
$is_disabled = "";
if ( !$can_use_premium_code ) {
    $is_disabled = "disabled";
}
$settings = [
    'vendor_onboading'         => [
        'label'              => __( 'Enable Vendor Onboarding.', 'bsd-split-pay-stripe-connect-woo' ),
        'description'        => sprintf( __( 'Please read our <a href="%s" target="_blank">documentation</a> for details.', 'bsd-split-pay-stripe-connect-woo' ), esc_url( 'https://docs.splitpayplugin.com/features/connecting-vendor-stripe-accounts' ) ),
        'checked'            => ( $can_use_premium_code && $vendor_onboading ? "checked = checked" : '' ),
        'disabled'           => ( !$can_use_premium_code ? "disabled" : '' ),
        'upgrade_text'       => ( !$can_use_premium_code ? $upgrade_text : '' ),
        'is_visible_in_free' => true,
        'class'              => '',
        'type'               => 'checkbox',
    ],
    'enable_title_description' => [
        'label'              => __( 'Enable Title & Description.', 'bsd-split-pay-stripe-connect-woo' ),
        'description'        => '',
        'checked'            => ( $can_use_premium_code && $enable_title_description ? "checked = checked" : '' ),
        'disabled'           => ( !$can_use_premium_code ? "disabled" : '' ),
        'upgrade_text'       => '',
        'is_visible_in_free' => false,
        'class'              => 'show_td_cb_row' . (( !$can_use_premium_code || !$vendor_onboading ? ' bsd_hidden' : '' )),
        'type'               => 'checkbox',
    ],
];
?>
        <div class="form-table sps_connect_accounts">
			<?php 
foreach ( $settings as $key => $setting ) {
    if ( $setting['is_visible_in_free'] ) {
        ?>
                <div class="form-block-row form-block-checkbox-row <?php 
        echo esc_attr( $setting['class'] );
        ?>">
                    <div class="form-block-checkbox">
                        <div class="form-block-checkbox">
                            <input type="checkbox" name="<?php 
        echo esc_attr( $key );
        ?>" id="<?php 
        echo esc_attr( $key );
        ?>"
                                   value="1" <?php 
        echo esc_attr( $setting['checked'] );
        ?>  <?php 
        echo esc_attr( $setting['disabled'] );
        ?>/>
                            <label><?php 
        echo "{$setting['label']} <i>{$setting['upgrade_text']}</i>";
        ?> </label>
                        </div>
                    </div>
                    <div class="form-block-nots">
						<?php 
        echo wp_kses( $setting['description'], SPP_ALLOWED_HTML_TAGS );
        ?>
                    </div>
                </div>
			<?php 
    }
}
?>
        </div>
        <?php 
?>
    <div class="form-block-submit">
		<?php 
submit_button();
?>
    </div>
</div>
