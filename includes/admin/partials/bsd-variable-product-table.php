<?php

if ( !defined( 'ABSPATH' ) ) {
    exit( 'Sorry!' );
}
global $bsd_sca;
$get_accounts = $bsd_sca->get_stored_accounts();
$_bsd_spscwt_product_connected_account = get_post_meta( $variation->ID, '_bsd_spscwt_product_connected_account', true );
$_bsd_spscwt_product_type = get_post_meta( $variation->ID, '_bsd_spscwt_product_type', true );
$_stripe_connect_split_pay_transfer_percentage = get_post_meta( $variation->ID, '_stripe_connect_split_pay_transfer_percentage', true );
$_bsd_spscwt_product_amount = get_post_meta( $variation->ID, '_bsd_spscwt_product_amount', true );
/* shipping */
$_bsd_spscwt_shipping_type = get_post_meta( $variation->ID, '_bsd_spscwt_shipping_type', true );
$_bsd_prod_shipping_percentage = get_post_meta( $variation->ID, '_bsd_prod_shipping_percentage', true );
$_bsd_prod_shipping_amount = get_post_meta( $variation->ID, '_bsd_prod_shipping_amount', true );
?>
    <div class="table-field-main-with-addbtn">
        <div class="table-field-main-max-height">
            <table class="table-field-main bsd_connect_acc_id_table_simple_prod ">
                <tbody>
                <tr class="bsd_connect_acc_id_row_simple_prod disabled">
                    <td>
                        <div class="table-field-row-cover">
                            <div class="table-field-row table-field-row-1">
                                <div class="table-field-label">
                                    <span class="table-field-label-th"><?php 
esc_html_e( 'Connected Stripe Account ID', 'bsd-split-pay-stripe-connect-woo' );
?></span>
                                </div>
                                <div class="table-field-row-inner">
                                    <div class="table-field-col-12">
                                            
                                            <span class="select2 select2-container select2-container--default" dir="ltr"
                                                  style="width: auto;">
                                                <span class="selection">
                                                    <span class="select2-selection select2-selection--single"
                                                          aria-haspopup="true" aria-expanded="false" tabindex="0"
                                                          aria-labelledby="select2-sspscwsca_select_0-container"
                                                          role="combobox">
                                                        <span class="select2-selection__rendered"
                                                              id="select2-sspscwsca_select_0-container" role="textbox"
                                                              aria-readonly="true" title="">
                                                            <span class="select2-selection__clear">×</span></span>
                                                                <span class="select2-selection__arrow"
                                                                      role="presentation">
                                                                    <b role="presentation"></b>
                                                                </span>
                                                            </span>
                                                        </span>
                                                    <span class="dropdown-wrapper" aria-hidden="true"></span>
                                                </span>
                                    </div>
                                </div>
                                <div class="table-icon">
                                    <button class="copy_account_id round-icon-del-copy round-icon-copy" type="button"
                                            data-select_id="sspscwsca_select_0">
                                        <img src="<?php 
echo BSD_SCSP_PLUGIN_URI;
?>assets/copy-account-id.svg"
                                             alt="copy-account-id" title="<?php 
esc_attr_e( 'Copy Account ID', 'bsd-split-pay-stripe-connect-woo' );
?>">
                                    </button>
                                </div>
                            </div>
                            <div class="table-field-row table-field-row-2">
                                <div class="table-field-label">
                                    <span class="table-field-label-th"><?php 
esc_html_e( 'Product-Specific Transfer Amount', 'bsd-split-pay-stripe-connect-woo' );
?></span>
                                    <p class="bsd-scsp-helper-text"><?php 
printf( esc_html__( 'Overrides the %1$s global transfer value%2$s settings.', 'bsd-split-pay-stripe-connect-woo' ), '<a href="' . esc_url( admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-account' ) ) . '" target="_blank">', '</a>' );
?></p>
                                </div>
                                <div class="table-field-row-inner">
                                    <div class="table-field-col-6">
                                        <select class="bsd_spscwt_type" id="bsd_spscwt_type_0">
                                            <option value="percentage" disabled="true"><?php 
esc_html_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
?></option>
                                            <option value="amount" disabled="true"><?php 
esc_html_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
?></option>
                                        </select>
                                    </div>
                                    <div class="table-field-col-6">
                                        <input type="number" min="0" max="100" step="1" value="" placeholder="e.g. 10"
                                               class="bsd_spscwtp_input">
                                    </div>
                                </div>
                            </div>
                            <div class="table-field-row table-field-row-3">
                                <div class="table-field-label">
                                    <span class="table-field-label-th"><?php 
esc_html_e( 'Product-Specific Shipping Transfer Value', 'bsd-split-pay-stripe-connect-woo' );
?></span>
                                    <p class="bsd-scsp-helper-text"><?php 
printf( esc_html__( 'Overrides the %1$s global shipping transfer value%2$s settings.', 'bsd-split-pay-stripe-connect-woo' ), '<a href="' . esc_url( admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-account' ) ) . '" target="_blank">', '</a>' );
?></p>
                                </div>
                                <div class="table-field-row-inner">
                                    <div class="table-field-col-6">
                                        <select class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_0">
                                            <option value="percentage" disabled="true"><?php 
esc_html_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
?></option>
                                            <option value="amount" disabled="true"><?php 
esc_html_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
?></option>
                                        </select>
                                    </div>
                                    <div class="table-field-col-6">
                                        <input type="number" min="0" max="100" step="1" value="" placeholder="e.g. 10"
                                               class="bsd_spscwtp_shipping_input">
                                    </div>
                                </div>
                                <div class="table-icon">
                                    <button class="remove_account round-icon-del-copy round-icon-del"
                                            data-select_row_id="0" type="button">
                                        <img src="<?php 
echo BSD_SCSP_PLUGIN_URI;
?>assets/remove-account-id.png"
                                             alt="remove-account-id" title="<?php 
esc_attr_e( 'Remove account', 'bsd-split-pay-stripe-connect-woo' );
?>">
                                    </button>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>

                <tr class="multi-account-upgrade-row text-center">
                    <td colspan="3">
                        <p class="bsd-scsp-helper-text bsd-ps-helper-text"><?php 
esc_html_e( "To split product-specific payments between multiple Stripe accounts, please", 'bsd-split-pay-stripe-connect-woo' );
?>
                            <a href="<?php 
echo esc_url( $upgrade_url );
?>"><?php 
esc_html_e( "Upgrade >", 'bsd-split-pay-stripe-connect-woo' );
?></a>.
                        </p>
                    </td>
                </tr>

                </tbody>
            </table>
        </div>
    </div>

	