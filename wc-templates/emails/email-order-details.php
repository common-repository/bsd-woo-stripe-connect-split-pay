<?php
/**
 * Order details table shown in emails.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-order-details.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woo.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;
global $email_obj;
$email_obj = $email;
require_once BSD_SCSP_PLUGIN_INCLUDES . '/admin/list-tables/class-bsd-split-pay-stripe-connect-woo-table-transfers.php';
$bsd_scsp_list_table = new \BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin\BSD_SCSP_List_Table_Transfers();
$stripe_mode         = $bsd_scsp_list_table->get_stripe_mode();

$text_align = is_rtl() ? 'right' : 'left';
if ( isset( $email_obj->transfer_data ) ) {
	do_action( 'woocommerce_email_header', $email_heading, $email );
}
do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<h2>
	<?php
	if ( $sent_to_admin ) {
		$before = '<a class="link" href="' . esc_url( $order->get_edit_order_url() ) . '">';
		$after  = '</a>';
	} else {
		$before = '';
		$after  = '';
	}
	/* translators: %s: Order ID. */
	echo wp_kses_post( $before . sprintf( __( '[Order #%s]', 'bsd-split-pay-stripe-connect-woo' ) . $after . ' (<time datetime="%s">%s</time>)', $order->get_order_number(), $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ) );
	?>
</h2>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
		<thead>
			<tr>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Product', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Quantity', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Price', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
				<?php if ( $sent_to_admin && isset( $email_obj->transfer_data ) ) { ?>
				<th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><a href="<?php echo admin_url( 'admin.php?page=bsd-split-pay-stripe-connect-woo-settings&tab=transfers' ); ?>" target="_blank"><?php esc_html_e( 'Expected Transfer Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></a></th>
				<?php } ?>
			</tr>
		</thead>
		<tbody>
			<?php
			add_filter('woocommerce_email_order_items_args', function ( $args ) {
				global $email_obj;
				$args['email_obj'] = $email_obj;
				return $args;
			}, 21);
			echo wc_get_email_order_items( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$order,
				array(
					'show_sku'      => $sent_to_admin,
					'show_image'    => true,
					'image_size'    => array( 32, 32 ),
					'plain_text'    => false,
					'sent_to_admin' => $sent_to_admin,
					'email_obj' => $email,
				)
			);
			remove_filter( 'woocommerce_email_order_items_args', function () {}, 21 );
			// \WC_Stripe_Logger::log( ': email-order-details-email_obj ' . print_r( $email, true ) );
			?>
		</tbody>
		<tfoot>
			<?php
			$item_totals = $order->get_order_item_totals();
			if ( $item_totals ) {
				$i = 0;
				foreach ( $item_totals as $key => $total ) {

					++$i;
					?>
					<tr>
						<th class="td" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $text_align ); ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo wp_kses_post( $total['label'] ); ?></th>
						<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo wp_kses_post( $total['value'] ); ?></td>
						<?php if ( $sent_to_admin && isset( $email_obj->transfer_data ) ) { ?>
							<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; <?php echo ( 1 === $i ) ? 'border-top-width: 4px;' : ''; ?>"><?php echo '0.00'; ?></td>
						<?php } ?>
					</tr>					
					<?php

					if ( $key == 'cart_subtotal' && isset( $email_obj->transfer_data ) ) {
						$transfer_data = $email_obj->transfer_data;
						if ( isset( $transfer_data['global_transfer'] ) && ! empty( $transfer_data['global_transfer'] ) ) {
							foreach ( $transfer_data['global_transfer'] as $transfer_detail ) {
								$font_color = '';
								// \WC_Stripe_Logger::log( ': email-transfer_detail ' . print_r( $transfer_detail, true ) );
								$transfer_url = '#';
								if ( isset( $transfer_detail['transfer']->id ) && ! empty( $transfer_detail['transfer']->id ) ) {
									$transfer_url = 'https://dashboard.stripe.com/' . $stripe_mode . '/connect/transfers/' . esc_attr( $transfer_detail['transfer']->id );
								}

								if ( $transfer_detail['transfer_type'] == 'percentage' ) {
									$transfer_type  = esc_html__( 'Percentage:', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . $transfer_detail['transfer_percentage_or_fixed'] . '%';
									$transfer_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['transfer_value'] ) . '</a>';
								} else {
									$transfer_type  = esc_html__( 'Fixed Amount :', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . wc_price( $transfer_detail['transfer_value'] );
									$transfer_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['transfer_value'] ) . '</a>';
								}
								if ( isset( $transfer_detail['status'] ) && $transfer_detail['status'] == 'failed' ) {
									$transfer_value = esc_html__( 'Failed', 'bsd-split-pay-stripe-connect-woo' );
									$font_color     = 'color: red;';
								}


								?>
								<tr>
									<td colspan="2" class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"> <?php esc_html_e( 'Global Transfer to', 'bsd-split-pay-stripe-connect-woo' ); ?> : <?php echo $transfer_detail['account_id']; ?></td>
									<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"><?php echo $transfer_type; ?></td>
									<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; <?php echo $font_color; ?>"><?php echo $transfer_value; ?></td>
								</tr>								
							<?php }
						}
					}

					if ( $key == 'shipping' && isset( $email_obj->transfer_data ) ) {
						$transfer_data = $email_obj->transfer_data;
						if ( isset( $transfer_data['global_shippint_transfer'] ) && ! empty( $transfer_data['global_shippint_transfer'] ) ) {
							foreach ( $transfer_data['global_shippint_transfer'] as $transfer_detail ) {
								$font_color = '';
								// \WC_Stripe_Logger::log( ': email-transfer_detail ' . print_r( $transfer_detail, true ) );
								$transfer_url = '#';
								if ( isset( $transfer_detail['transfer']->id ) && ! empty( $transfer_detail['transfer']->id ) ) {
									$transfer_url = 'https://dashboard.stripe.com/' . $stripe_mode . '/connect/transfers/' . esc_attr( $transfer_detail['transfer']->id );
								}

								if ( $transfer_detail['transfer_type'] == 'percentage' ) {
									$transfer_type  = esc_html__( 'Percentage:', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . $transfer_detail['transfer_percentage_or_fixed'] . '%';
									$transfer_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['transfer_value'] ) . '</a>';
								} else {
									$transfer_type  = esc_html__( 'Fixed Amount :', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . wc_price( $transfer_detail['transfer_value'] );
									$transfer_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['transfer_value'] ) . '</a>';
								}
								if ( isset( $transfer_detail['status'] ) && $transfer_detail['status'] == 'failed' ) {
									$transfer_value = esc_html__( 'Failed', 'bsd-split-pay-stripe-connect-woo' );
									$font_color     = 'color: red;';
								}
								?>
								<tr>
									<td colspan="2" class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"> <?php esc_html_e( 'Shipping Transfer to', 'bsd-split-pay-stripe-connect-woo' ); ?> : <?php echo $transfer_detail['account_id']; ?></td>
									<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"><?php echo $transfer_type; ?></td>
									<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;<?php echo $font_color; ?>"><?php echo $transfer_value; ?></td>
								</tr>								
							<?php }
						}
					}


					if ( $key == 'cart_subtotal' && isset( $email_obj->transfer_data ) ) {
						$transfer_data = $email_obj->transfer_data;
						if ( isset( $transfer_data['global_transfer'] ) && ! empty( $transfer_data['global_transfer'] ) ) {
							foreach ( $transfer_data['global_transfer'] as $transfer_detail ) {
								if ( isset( $transfer_detail['account_wise_tax'] ) && ! empty( $transfer_detail['account_wise_tax'] ) ) {
									$font_color = '';
									// \WC_Stripe_Logger::log( ': email-transfer_detail ' . print_r( $transfer_detail, true ) );
									$transfer_url = '#';
								if ( isset( $transfer_detail['transfer']->id ) && ! empty( $transfer_detail['transfer']->id ) ) {
										$transfer_url = 'https://dashboard.stripe.com/' . $stripe_mode . '/connect/transfers/' . esc_attr( $transfer_detail['transfer']->id );
								}

								if ( $transfer_detail['transfer_type'] == 'percentage' ) {
										$transfer_type = esc_html__( 'Percentage:', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . $transfer_detail['transfer_percentage_or_fixed'] . '%';

										$transfer_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['account_wise_tax'] ) . '</a>';
								} else {
										$transfer_type = esc_html__( 'Fixed Amount :', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . wc_price( $transfer_detail['account_wise_tax'] );

										$transfer_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['account_wise_tax'] ) . '</a>';
								}
								if ( isset( $transfer_detail['status'] ) && $transfer_detail['status'] == 'failed' ) {
										$transfer_value = esc_html__( 'Failed', 'bsd-split-pay-stripe-connect-woo' );
										$font_color     = 'color: red;';
								}

								?>
										<tr>
											<td colspan="2" class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"> <?php esc_html_e( 'Global Transfer to', 'bsd-split-pay-stripe-connect-woo' ); ?> : <?php echo $transfer_detail['account_id']; ?></td>
											<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"><?php echo $transfer_type; ?></td>
											<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; <?php echo $font_color; ?>"><?php echo $transfer_value; ?></td>
										</tr>								
								<?php }
							}
						}
					}
}
			}
			if ( $order->get_customer_note() ) {
				?>
				<tr>
					<th class="td" scope="row" colspan="2" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e( 'Note:', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
					<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></td>
				</tr>
				<?php
			}
			?>
		</tfoot>
	</table>
</div>

<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );
if ( isset( $email_obj->transfer_data ) ) {
	do_action( 'woocommerce_email_footer', $email );
}
?>
