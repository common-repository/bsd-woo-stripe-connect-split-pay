<?php
/**
 * Displays a table in the WC Settings page
 *
 */

$GLOBALS['hide_save_button'] = true;

?>
<div class="wrap">
    <h2>Dashboard</h2>
    <div id="col-container">
        <!-- Content goes here -->
		<?php

		$wp_stripe_connect = new Bsd_Woocommerce_Stripe_Connect_Split_Pay_Stripe();
		$postMetas         = $wp_stripe_connect->stp_retrieve_transfer_info( "stripeTransfer" );

		if ( isset( $postMetas ) ) { ?>
            <table id='example-dashboard' class='display' cellspacing='0' width='100%'>
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Customer Details', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
                    <th><?php esc_html_e( 'Connected Account', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
                </tr>
                </thead>
                <tbody>
				<?php
				foreach ( $postMetas as $value ) {
					$metaData = json_decode( $value->meta_value, true ); ?>
                    <tr>
                        <td>
                            <p>
                                <b><?php esc_html_e( 'order Id:', 'bsd-split-pay-stripe-connect-woo' ); ?></b> <?php echo esc_attr( $metaData['order_id'] ); ?>
                            </p>
                            <p>
                                <b><?php esc_html_e( 'Customer Name:', 'bsd-split-pay-stripe-connect-woo' ); ?></b> <?php echo esc_attr( $metaData['custName'] ); ?>
                            </p>
                            <p>
                                <b><?php esc_html_e( 'Customer Email:', 'bsd-split-pay-stripe-connect-woo' ); ?></b><?php echo esc_attr( $metaData['custEmail'] ); ?>
                            </p>
                            <p>
                                <b><?php esc_html_e( 'Total Amount Paid:', 'bsd-split-pay-stripe-connect-woo' ); ?></b> <?php esc_html_e( 'US$', 'bsd-split-pay-stripe-connect-woo' ); ?> <?php echo number_format( esc_attr( $metaData['totalPaid'] ) / 100, 2 ); ?>
                            </p>
                        </td>
                        <td>
                            <p>
                                <b><?php esc_html_e( 'Connected Account Id:', 'bsd-split-pay-stripe-connect-woo' ); ?></b><?php echo esc_attr( $metaData['destinationAcc'] ); ?>
                            </p>
                            <p><b>Amount Transferred to connected Account
                                    (<?php echo get_option( "transfer_percentage" ); ?>% of Total
                                    Amt.): </b>US$ <?php echo number_format( esc_attr( $metaData['tranferAmt'] ) / 100, 2 ); ?>
                            </p>
							<?php printf( '<p><b>%s (%s%% of Total Amt.):</b> US$ %s</p>', esc_html__( 'Amount Transferred to connected Account', 'bsd-split-pay-stripe-connect-woo' ), esc_html( get_option( 'transfer_percentage' ) ), number_format( esc_attr( $metaData['tranferAmt'] ) / 100, 2 ) ); ?>
                            <p></p>
                            <p></p>
                        </td>
                    </tr>
					<?php
				} ?>

                <tfoot>
                <tr>
                    <th><?php esc_html_e( 'Customer Details', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
                    <th><?php esc_html_e( 'Connected Account' . 'bsd-split-pay-stripe-connect-woo' ); ?></th>
                </tr>
                </tfoot>
                </tbody>
            </table>

			<?php exit;
		} else {
			echo "<h3>" . esc_html__( 'Please, configure your settings first.', 'bsd-split-pay-stripe-connect-woo' ) . "</h3>";
			echo "<br/>";
			echo esc_html__( 'Visit the settings Page.', 'bsd-split-pay-stripe-connect-woo' );
		}

		?>
    </div>
</div>