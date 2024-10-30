<?php

// phpcs:ignoreFile
global $bsd_sca, $wpdb;
$table_prefix = $wpdb->prefix;
$table_name = $table_prefix . BSD_SCSP_CONNECTED_ID_TABLE;
$connected_account_query = "select * from " . $table_name . " ";
$connected_account_results = $wpdb->get_results( $connected_account_query, ARRAY_A );
$woocommerce_stripe_settings = get_option( "woocommerce_stripe_settings" );
$bsd_split_pay_stripe_connect_woo_stripe_connected_account = get_option( 'bsd_split_pay_stripe_connect_woo_stripe_connected_account' );
if ( empty( $bsd_sca ) ) {
    $get_accounts = [];
} else {
    $get_accounts = $bsd_sca->get_stored_accounts();
}
$need_to_gray_tabs = ( $bsd_sca->is_stripe_enabled_and_configured() ? '' : 'grayed-tab' );
$bsd_spscwt_type = get_option( 'bsd_spscwt_type' );
$bsd_split_pay_stripe_connect_woo_transfer_percentage = get_option( 'bsd_split_pay_stripe_connect_woo_transfer_percentage' );
$bsd_spscwt_amount = get_option( 'bsd_spscwt_amount' );
$upgrade_url = BSD_SCSP_PLUGIN_UPGRADE_URL;
if ( function_exists( 'bsdwcscsp_fs' ) ) {
    $upgrade_url = bsdwcscsp_fs()->get_upgrade_url();
}
?>
<div class='bsd-scsp-settings <?php 
echo $need_to_gray_tabs;
?>'>
    <input type='hidden' name='tab_name' value='main'>
	<?php 
settings_fields( 'bsd_scsp_options_main' );
$text = sprintf( esc_html__( 'The Connected Stripe Account ID should be the Account ID for one of your connected accounts. For questions about how to configure the settings below, please refer to our %sDocumentation%s.', 'bsd-split-pay-stripe-connect-woo' ), '<a href="https://docs.splitpayplugin.com/" target="_blank">', '</a>' );
echo '<p>' . wp_kses_post( $text ) . ' </p>';
/** table started */
?>
    
    <table class="form-table bsd-scsp-settings-table" role="presentation">
        <thead>
        <tr>
            <td scope="row">
                <strong><?php 
esc_html_e( 'Connected Stripe Account ID', 'bsd-split-pay-stripe-connect-woo' );
?></strong>
                <p class="bsd-scsp-helper-text">
					<?php 
printf(
    esc_html__( 'View my %1$sConnected Accounts%2$s. | Edit %3$sPlatform Account%4$s settings.', 'bsd-split-pay-stripe-connect-woo' ),
    '<a href="https://dashboard.stripe.com/connect/accounts/overview" target="_blank">',
    '</a>',
    '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' ) ) . '" target="_blank">',
    '</a>'
);
?>
                </p>

               
            </td>
            <td scope="row">
                <strong><?php 
esc_html_e( 'Global Transfer Value', 'bsd-split-pay-stripe-connect-woo' );
?></strong>
                <p class="bsd-scsp-helper-text"><?php 
echo __( "Used if a <a href='https://docs.splitpayplugin.com/how-to-split-payments/product-specific-split-transfers' target='_blank'>product-level transfer value</a> is not set.", 'bsd-split-pay-stripe-connect-woo' );
?></p>
            </td>
            <td scope="row">
                <strong><?php 
esc_html_e( 'Global Shipping Transfer Value', 'bsd-split-pay-stripe-connect-woo' );
?></strong>
                <p class="bsd-scsp-helper-text"><?php 
echo __( "Used if a <a href='https://docs.splitpayplugin.com/features/how-to-transfer-shipping-fees/product-level-shipping-transfers' target='_blank'>product-level shipping transfer value</a> is not set.", "bsd-split-pay-stripe-connect-woo" );
?></p>
            </td>
            <td scope="row"></td>
        </tr>
        </thead>
        <tbody>
		<?php 
if ( empty( $connected_account_results ) ) {
    ?>
            <tr class="bsd_connect_acc_first_row bsd_connect_acc_id_row" data-crow_index="0">
                <td>
                    <div class="bsd_stripe_select_wrapper" style="max-width: 470px;min-width: 470px;">
                        <select name="bsd_split_pay_stripe_connect_woo_stripe_connected_account[0]"
                                id="sspscwsca_select_0" class="sspscwsca_select" style="width: 420px;">
							<?php 
    if ( !empty( $get_accounts ) ) {
        ?>
                                <option value=""><?php 
        esc_html_e( "Select", 'bsd-split-pay-stripe-connect-woo' );
        ?></option>
								<?php 
        foreach ( $get_accounts as $gak ) {
            $display_option = "";
            if ( !empty( $gak["bsd_account_name"] ) && isset( $gak["bsd_account_email"] ) && !empty( $gak["bsd_account_email"] ) ) {
                $display_option .= $gak["bsd_account_name"] . " - " . $gak["bsd_account_email"];
            } elseif ( !empty( $gak["bsd_account_name"] ) ) {
                $display_option .= $gak["bsd_account_name"];
            } else {
                $display_option .= $gak["bsd_account_id"];
            }
            ?>
                                        <option value="<?php 
            echo $gak["bsd_account_id"];
            ?>"><?php 
            echo $display_option;
            ?></option>
									<?php 
        }
    }
    ?>

                        </select>
                        <button class="copy_account_id" data-select_id="sspscwsca_select_0" type="button">
                            <img src="<?php 
    echo BSD_SCSP_PLUGIN_URI;
    ?>assets/copy-account-id.svg"
                                 alt="copy-account-id"
                                 title="<?php 
    esc_html_e( "Copy Account ID", "bsd-split-pay-stripe-connect-woo" );
    ?>"/>
                        </button>
                    </div>
                </td>

                <td>

                    <select name="bsd_spscwt_type[0]" class="bsd_spscwt_type" id="bsd_spscwt_type_0">
                        <option value="percentage"><?php 
    esc_html_e( "Percentage", "bsd-split-pay-stripe-connect-woo" );
    ?></option>
						<?php 
    ?>
                            <option value="amount"
                                    disabled="true"><?php 
    esc_html_e( "Fixed Amount", "bsd-split-pay-stripe-connect-woo" );
    ?></option>
							<?php 
    ?>
                    </select>

					<?php 
    ?>
                        <div class="percentage_wrapper">
                            <input type='number' min='0' max="100" step="1"
                                   id='bsd_split_pay_stripe_connect_woo_transfer_percentage_0'
                                   name='bsd_split_pay_stripe_connect_woo_transfer_percentage[0]' value=""
                                   placeholder="e.g. 10" class="bsd_spscwtp_input "/>

                        </div>


						<?php 
    ?>


					<?php 
    ?>
                        <p class="bsd-scsp-helper-text"><?php 
    esc_html_e( "For Fixed amount transfers, please", 'bsd-split-pay-stripe-connect-woo' );
    ?>
                            <a href="<?php 
    echo esc_url( $upgrade_url );
    ?>"><?php 
    esc_html_e( "Upgrade >", 'bsd-split-pay-stripe-connect-woo' );
    ?></a>.
                        </p>
					<?php 
    ?>

                </td>

                <td>
					<?php 
    ?>
                        <select class="bsd_spscwt_type bsd_shipping_type" disabled="true">
                            <option value="percentage"><?php 
    esc_html_e( "Percentage", "bsd-split-pay-stripe-connect-woo" );
    ?></option>
                            <option value="amount"><?php 
    esc_html_e( "Fixed Amount", "bsd-split-pay-stripe-connect-woo" );
    ?></option>
                        </select>
						<?php 
    ?>


					<?php 
    ?>
                        <div class="percentage_wrapper">
                            <input type='number' min='0' max="100" step="1" placeholder="e.g. 10"
                                   class="bsd_spscwtp_input" disabled="true"/>

                        </div>


						<?php 
    ?>


					<?php 
    ?>
                        <p class="bsd-scsp-helper-text"><?php 
    esc_html_e( "For Shipping transfers, please", 'bsd-split-pay-stripe-connect-woo' );
    ?>
                            <a href="<?php 
    echo esc_url( $upgrade_url );
    ?>"><?php 
    esc_html_e( "Upgrade >", 'bsd-split-pay-stripe-connect-woo' );
    ?></a>.
                        </p>
					<?php 
    ?>

                </td>

                <td>

                    <input type="hidden" name="bsd_connected_acc_action" value="add">


                </td>
            </tr>
		<?php 
} else {
    foreach ( $connected_account_results as $cark => $carv ) {
        if ( !\BSD_Split_Pay_Stripe_Connect_Woo\bsdwcscsp_fs()->can_use_premium_code() ) {
            if ( $cark > 0 ) {
                continue;
            }
        }
        ?>
                <tr class="<?php 
        echo ( $cark == 0 ? "bsd_connect_acc_first_row" : "" );
        ?> bsd_connect_acc_id_row"
                    data-crow_index="<?php 
        echo $cark;
        ?>">
                    <td>
                        <div class="bsd_stripe_select_wrapper" style="max-width: 470px; min-width: 470px;">
                            <select name="bsd_split_pay_stripe_connect_woo_stripe_connected_account[<?php 
        echo $cark;
        ?>]"
                                    id="sspscwsca_select_<?php 
        echo $cark;
        ?>" class="sspscwsca_select" style="width: 420px;">
								<?php 
        if ( !empty( $get_accounts ) ) {
            ?>
                                    <option value=""><?php 
            esc_html_e( "Select", 'bsd-split-pay-stripe-connect-woo' );
            ?></option>
									<?php 
            foreach ( $get_accounts as $gak ) {
                $display_option = "";
                if ( !empty( $gak["bsd_account_name"] ) && isset( $gak["bsd_account_email"] ) && !empty( $gak["bsd_account_email"] ) ) {
                    $display_option .= $gak["bsd_account_name"] . " - " . $gak["bsd_account_email"];
                } else {
                    if ( !empty( $gak["bsd_account_name"] ) ) {
                        $display_option .= $gak["bsd_account_name"];
                    } else {
                        $display_option .= $gak["bsd_account_id"];
                    }
                }
                ?>
                                        <option value="<?php 
                echo $gak["bsd_account_id"];
                ?>" <?php 
                echo ( $gak["bsd_account_id"] == $carv["bsd_connected_account_id"] ? "selected='selected'" : "" );
                ?>><?php 
                echo $display_option;
                ?></option>
										<?php 
            }
        }
        ?>

                            </select>
                            <button class="copy_account_id" data-select_id="sspscwsca_select_<?php 
        echo $cark;
        ?>"
                                    type="button">
                                <img src="<?php 
        echo BSD_SCSP_PLUGIN_URI;
        ?>assets/copy-account-id.svg"
                                     alt="copy-account-id"
                                     title="<?php 
        esc_html_e( "Copy Account ID", "bsd-split-pay-stripe-connect-woo" );
        ?>"/>
                            </button>
                        </div>
                    </td>

                    <td>
                        <select name="bsd_spscwt_type[<?php 
        echo $cark;
        ?>]" class="bsd_spscwt_type"
                                id="bsd_spscwt_type_<?php 
        echo $cark;
        ?>">
                            <option value="percentage" <?php 
        echo ( "percentage" == $carv["bsd_spscwt_type"] ? "selected='selected'" : "" );
        ?>><?php 
        esc_html_e( "Percentage", "bsd-split-pay-stripe-connect-woo" );
        ?></option>
							<?php 
        ?>
                                <option value="amount"
                                        disabled="true"><?php 
        esc_html_e( "Fixed Amount", "bsd-split-pay-stripe-connect-woo" );
        ?></option>
								<?php 
        ?>
                        </select>

						<?php 
        ?>
                            <div class="percentage_wrapper">
                                <input type='number' min='0' max="100" step="1"
                                       id='bsd_split_pay_stripe_connect_woo_transfer_percentage_<?php 
        echo $cark;
        ?>'
                                       name='bsd_split_pay_stripe_connect_woo_transfer_percentage[<?php 
        echo $cark;
        ?>]'
                                       value="<?php 
        echo $carv["bsd_spscwt_percentage_amount"];
        ?>"
                                       placeholder="e.g. 10" class="bsd_spscwtp_input"/>

                            </div>


							<?php 
        ?>



						<?php 
        ?>
                            <p class="bsd-scsp-helper-text"><?php 
        esc_html_e( "For Fixed amount transfers, please", 'bsd-split-pay-stripe-connect-woo' );
        ?>
                                <a href="<?php 
        echo esc_url( $upgrade_url );
        ?>"><?php 
        esc_html_e( "Upgrade >", 'bsd-split-pay-stripe-connect-woo' );
        ?></a>.
                            </p>
						<?php 
        ?>

                    </td>

                    <td>
						<?php 
        ?>
                            <select class="bsd_spscwt_type bsd_shipping_type" disabled="true">
                                <option value="percentage"><?php 
        echo __( "Percentage", 'bsd-split-pay-stripe-connect-woo' );
        ?></option>
                                <option value="amount"><?php 
        echo __( "Fixed Amount", 'bsd-split-pay-stripe-connect-woo' );
        ?></option>
                            </select>
							<?php 
        ?>
						<?php 
        ?>
                            <div class="percentage_wrapper">
                                <input type='number' min='0' max="100" step="1" disabled="true" placeholder="e.g. 10"
                                       class="bsd_spscwtp_input"/>
                            </div>
							<?php 
        ?>



						<?php 
        ?>
                            <p class="bsd-scsp-helper-text"><?php 
        esc_html_e( "For Shipping transfers, please", 'bsd-split-pay-stripe-connect-woo' );
        ?>
                                <a href="<?php 
        echo esc_url( $upgrade_url );
        ?>"><?php 
        esc_html_e( "Upgrade >", 'bsd-split-pay-stripe-connect-woo' );
        ?></a>.
                            </p>
						<?php 
        ?>

                    </td>

                    <td>
                        <input type="hidden" name="bsd_connected_acc_primary_id[<?php 
        echo $cark;
        ?>]"
                               value="<?php 
        echo $carv["bsd_connected_id"];
        ?>" class="bsd_connected_acc_primary_id">


						<?php 
        ?>


                    </td>
                </tr>
				<?php 
    }
}
?>

		<?php 
?>
            <tr class=" multi-account-upgrade-row disabled">
                <td>
                    <div class="bsd_stripe_select_wrapper" style="min-width: 470px;">
                                    
                                    <span class="select2 select2-container select2-container--default" dir="ltr"
                                          data-select2-id="4" style="width: 205.6px;">
                                    <span class="selection">
                                        <span class="select2-selection select2-selection--single" role="combobox"
                                              aria-haspopup="true" aria-expanded="false" tabindex="0"
                                              aria-disabled="false"
                                              aria-labelledby="select2-sspscwsca_select_1-container">
                                        <span class="select2-selection__rendered"
                                              id="select2-sspscwsca_select_1-container" role="textbox"
                                              aria-readonly="true" title="">
                                        <span class="select2-selection__clear" title="Remove all items"
                                              data-select2-id="6">x</span></span>
                                        <span class="select2-selection__arrow" role="presentation"><b
                                                    role="presentation"></b></span>
                                        </span>
                                    </span>
                                    <span class="dropdown-wrapper" aria-hidden="true"></span></span>
                        <button class="copy_account_id" type="button">
                            <img src="<?php 
echo BSD_SCSP_PLUGIN_URI;
?>assets/copy-account-id.svg"
                                 alt="copy-account-id" title="<?php 
esc_html_e( "Copy Account ID", 'bsd-split-pay-stripe-connect-woo' );
?>"/>
                        </button>
                    </div>
                </td>
                <td>
                    <select class="bsd_spscwt_type">
                        <option value="percentage">Percentage</option>
                        <option value="amount" selected="selected">Fixed Amount</option>
                    </select>

                    <input type="number" placeholder="e.g. 20" class="bsd_spscwt_amount ">
                </td>
                <td>
                    <select class="bsd_spscwt_type">
                        <option value="percentage">Percentage</option>
                        <option value="amount" selected="selected">Fixed Amount</option>
                    </select>

                    <input type="number" placeholder="e.g. 20" class="bsd_spscwt_amount ">


                </td>
                <td>


                    <button class="remove_account" type="button">
                        <img src="<?php 
echo BSD_SCSP_PLUGIN_URI;
?>assets/remove-account-id.png"
                             alt="remove-account-id"
                             title="<?php 
esc_html_e( "Remove account", 'bsd-split-pay-stripe-connect-woo' );
?>"/>
                    </button>
                </td>
            </tr>
            <tr class="multi-account-upgrade-row text-center">
                <td colspan="4">
                    <p class="bsd-scsp-helper-text"><?php 
esc_html_e( "To split payments between multiple Stripe accounts, please", 'bsd-split-pay-stripe-connect-woo' );
?>
                        <a href="<?php 
echo esc_url( $upgrade_url );
?>"><?php 
esc_html_e( "Upgrade >", 'bsd-split-pay-stripe-connect-woo' );
?></a>.
                    </p>
                </td>
            </tr>
			<?php 
?>


        </tbody>
    </table>

    <input type="hidden" name="bsd_connected_acc_primary_remove_ids" id="bsd_connected_acc_primary_remove_ids"
           value=""/>

	<?php 
$documentation_link = '';
$can_use_premium_code = \BSD_Split_Pay_Stripe_Connect_Woo\bsdwcscsp_fs()->can_use_premium_code();
$upgrade_text = sprintf( __( '<a href="%s" target="_blank">Upgrade ></a>', 'bsd-split-pay-stripe-connect-woo' ), $upgrade_url );
$sending_meta = get_option( "sending_meta", false );
$transfer_email = get_option( "transfer_email", false );
$transfer_taxes = get_option( "transfer_taxes", false );
$tax_transfer_type = get_option( "tax_transfer_type", false );
$is_disabled = "";
if ( !$can_use_premium_code ) {
    $is_disabled = "disabled";
}
$tax_transfer_type_dropdown = '<select name="tax_transfer_type" id="tax_transfer_type" ' . $is_disabled . '>';
$selected = "";
if ( $tax_transfer_type == "all" ) {
    $selected = "selected='selected'";
}
$tax_transfer_type_dropdown .= '<option value="all" ' . $selected . '>' . esc_html__( "All (100%)", 'bsd-split-pay-stripe-connect-woo' ) . '</option>';
$selected = "";
if ( $tax_transfer_type == "partial" ) {
    $selected = "selected='selected'";
}
$tax_transfer_type_dropdown .= '<option value="partial" ' . $selected . '>' . esc_html__( "Partial", 'bsd-split-pay-stripe-connect-woo' ) . '</option>';
$tax_transfer_type_dropdown .= ' </select>';
$settings = [
    'transfer_taxes' => [
        'label'              => sprintf( __( 'Transfer %s Tax for each product to Connected Accounts.', 'bsd-split-pay-stripe-connect-woo' ), $tax_transfer_type_dropdown ),
        'description'        => sprintf( __( 'Please read our <a href="%s" target="_blank">documentation</a> for details.', 'bsd-split-pay-stripe-connect-woo' ), esc_url( 'https://docs.splitpayplugin.com/features/tax-handling/' ) ),
        'checked'            => ( $can_use_premium_code && $transfer_taxes ? "checked = checked" : '' ),
        'disabled'           => ( !$can_use_premium_code ? "disabled" : '' ),
        'upgrade_text'       => ( !$can_use_premium_code ? $upgrade_text : '' ),
        'is_visible_in_free' => true,
        'class'              => '',
        'type'               => 'checkbox',
    ],
    'transfer_email' => [
        'label'              => __( 'Enable a Transfer Confirmation email after transactions occur.', 'bsd-split-pay-stripe-connect-woo' ),
        'description'        => sprintf( __( 'Please read our <a href="%s" target="_blank">documentation</a> for details.', 'bsd-split-pay-stripe-connect-woo' ), esc_url( 'https://docs.splitpayplugin.com/features/transfer-confirmation-email/' ) ),
        'checked'            => ( $can_use_premium_code && $transfer_email ? "checked = checked" : '' ),
        'disabled'           => ( !$can_use_premium_code ? "disabled" : '' ),
        'upgrade_text'       => ( !$can_use_premium_code ? $upgrade_text : '' ),
        'is_visible_in_free' => true,
        'class'              => '',
        'type'               => 'checkbox',
    ],
    'sending_meta'   => [
        'label'              => __( 'Include Order Details in Stripe Transfer Metadata.', 'bsd-split-pay-stripe-connect-woo' ),
        'description'        => sprintf( __( 'Please read our <a href="%s" target="_blank">documentation</a> for details.', 'bsd-split-pay-stripe-connect-woo' ), 'https://docs.splitpayplugin.com/features/stripe-metadata' ),
        'checked'            => ( $can_use_premium_code && $sending_meta ? "checked = checked" : '' ),
        'disabled'           => ( !$can_use_premium_code ? "disabled" : '' ),
        'upgrade_text'       => ( !$can_use_premium_code ? $upgrade_text : '' ),
        'is_visible_in_free' => true,
        'class'              => '',
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
        echo $setting['class'];
        ?>">
                    <div class="form-block-checkbox">
                        <div class="form-block-checkbox">
                            <input type="checkbox" name="<?php 
        echo $key;
        ?>" id="<?php 
        echo $key;
        ?>"
                                   value="1" <?php 
        echo $setting['checked'];
        ?>  <?php 
        echo $setting['disabled'];
        ?>/>
                            <label><?php 
        echo "{$setting['label']} <i>{$setting['upgrade_text']}</i>";
        ?> </label>
                        </div>
                    </div>
                    <div class="form-block-nots">
						<?php 
        echo $setting['description'];
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
// do_settings_sections('bsd-split-pay-stripe-connect-woo');
submit_button();
?>
    </div>
</div>
