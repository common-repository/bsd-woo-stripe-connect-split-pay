<?php

if ( !function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\Inc\\Admin\\render_transfers_list_table' ) ) {
    function render_transfers_list_table() {
    }

}
if ( !function_exists( 'render_list_table_screenshot' ) ) {
    function render_list_table_screenshot() {
        $image_path = BSD_SCSP_PLUGIN_ASSETS . '/stripe-connect-transfers-table.png';
        $upgrade_url = '?billing_cycle=annual&page=bsd-split-pay-stripe-connect-woo-pricing';
        if ( function_exists( 'bsdwcscsp_fs' ) ) {
            $upgrade_url = bsdwcscsp_fs()->get_upgrade_url();
        }
        ?>
		<section>
			<h1><?php 
        esc_html_e( 'PRO Feature', 'bsd-split-pay-stripe-connect-woo' );
        ?></h1>
			<p>
				<?php 
        printf( esc_html__( 'To view transfer details in the WP dashboard or export transfers in CSV format, please %1$sUpgrade >%2$s', 'bsd-split-pay-stripe-connect-woo' ), '<a href="' . esc_url( $upgrade_url ) . '">', '</a>' );
        ?>
			</p>
			<p>
				<?php 
        esc_html_e( 'You can also view Transfers in the', 'bsd-split-pay-stripe-connect-woo' );
        ?> <a
						href="https://dashboard.stripe.com/connect/transfers"
						target="_blank"><?php 
        esc_html_e( 'Stripe Dashboard', 'bsd-split-pay-stripe-connect-woo' );
        ?></a>. <?php 
        esc_html_e( 'Be sure to switch to TEST mode or LIVE mode accordingly.', 'bsd-split-pay-stripe-connect-woo' );
        ?>
				<a href="<?php 
        echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' ) );
        ?>"
					target="_blank"><?php 
        esc_html_e( 'Change mode >', 'bsd-split-pay-stripe-connect-woo' );
        ?></a>
			</p>
			<span class="grayed-export-button-wrapper disabled">
				<a href="javascript:;"><?php 
        esc_html_e( 'Export to CSV', 'bsd-split-pay-stripe-connect-woo' );
        ?></a>
			</span>
		</section>
		<figure>
			<img src="<?php 
        echo esc_attr( $image_path );
        ?>"
				alt="<?php 
        esc_attr_e( 'Blurred example of Stripe Connect Transfers list table', 'bsd-split-pay-stripe-connect-woo' );
        ?>"
				style="width:100%">
		</figure>
		<?php 
    }

}
?>

<div class='bsd-scsp-settings'>
	<?php 
if ( function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\\bsdwcscsp_fs' ) ) {
    if ( BSD_Split_Pay_Stripe_Connect_Woo\bsdwcscsp_fs()->can_use_premium_code() ) {
        render_transfers_list_table();
    }
    if ( BSD_Split_Pay_Stripe_Connect_Woo\bsdwcscsp_fs()->is_not_paying() ) {
        render_list_table_screenshot();
    }
} else {
    render_list_table_screenshot();
}
?>

</div>
