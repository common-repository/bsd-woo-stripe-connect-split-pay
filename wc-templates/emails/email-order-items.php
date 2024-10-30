<?php
/**
 * Email Order Items
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/email-order-items.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woo.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;
global $email_obj;
// \WC_Stripe_Logger::log( ': email-order-items-args ' . print_r( $args, true ) );
require_once BSD_SCSP_PLUGIN_INCLUDES . '/admin/list-tables/class-bsd-split-pay-stripe-connect-woo-table-transfers.php';
$bsd_scsp_list_table = new \BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin\BSD_SCSP_List_Table_Transfers();
$stripe_mode         = $bsd_scsp_list_table->get_stripe_mode();

$text_align  = is_rtl() ? 'right' : 'left';
$margin_side = is_rtl() ? 'left' : 'right';
foreach ( $items as $item_id => $item ) :
	$product       = $item->get_product();
	$sku           = '';
	$purchase_note = '';
	$image         = '';

	if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
		continue;
	}

	if ( is_object( $product ) ) {
		$sku           = $product->get_sku();
		$purchase_note = $product->get_purchase_note();
		$image         = $product->get_image( $image_size );
	}

	?>
	<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_order_item_class', 'order_item', $item, $order ) ); ?>">
		<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
		<?php

		// Show title/image etc.
		if ( $show_image ) {
			echo wp_kses_post( apply_filters( 'woocommerce_order_item_thumbnail', $image, $item ) );
		}

		// Product name.
		echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) );

		// SKU.
		if ( $show_sku && $sku ) {
			echo wp_kses_post( ' (#' . $sku . ')' );
		}

		// allow other plugins to add additional product information here.
		do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, $plain_text );

		wc_display_item_meta(
			$item,
			array(
				'label_before' => '<strong class="wc-item-meta-label" style="float: ' . esc_attr( $text_align ) . '; margin-' . esc_attr( $margin_side ) . ': .25em; clear: both">',
			)
		);

		// allow other plugins to add additional product information here.
		do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, $plain_text );

		?>
		</td>
		<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
			<?php
			$qty          = $item->get_quantity();
			$refunded_qty = $order->get_qty_refunded_for_item( $item_id );

			if ( $refunded_qty ) {
				$qty_display = '<del>' . esc_html( $qty ) . '</del> <ins>' . esc_html( $qty - ( $refunded_qty * -1 ) ) . '</ins>';
			} else {
				$qty_display = esc_html( $qty );
			}
			echo wp_kses_post( apply_filters( 'woocommerce_email_order_item_quantity', $qty_display, $item ) );
			?>
		</td>
		<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
			<?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?>
		</td>
		<?php if ( $sent_to_admin && isset( $email_obj->transfer_data ) ) { ?>
		<td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
			<?php echo '0.00'; ?>
		</td>
		<?php } ?>
	</tr>
	<?php
	\WC_Stripe_Logger::log( ': email-order-items-email_obj ' . print_r( $email_obj, true ) );
	if ( isset( $email_obj->transfer_data ) ) {

		$transfer_account = $email_obj->transfer_data;
		foreach ( $transfer_account['product_transfer'] as $transfer_detail ) {
			if ( $product->get_id() == $transfer_detail['product_id'] ) {
				$font_color = '';

				$transfer_url = '#';
				if ( isset( $transfer_detail['transfer']->id ) && ! empty( $transfer_detail['transfer']->id ) ) {
					$transfer_url = 'https://dashboard.stripe.com/' . $stripe_mode . '/connect/transfers/' . esc_attr( $transfer_detail['transfer']->id );
					}

				if ( $transfer_detail['transfer_type'] == 'percentage' ) {
					$transfer_type = esc_html__( 'Percentage:', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . $transfer_detail['transfer_percentage_or_fixed'] . '%';
					$account_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['transfer_value'] ) . '</a>';
				} else {
					$transfer_type = esc_html__( 'Fixed Amount :', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . wc_price( $transfer_detail['transfer_value'] );
					$account_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['transfer_value'] ) . '</a>';
					}
				if ( isset( $transfer_detail['status'] ) && $transfer_detail['status'] == 'failed' ) {
					$account_value = esc_html__( 'Failed', 'bsd-split-pay-stripe-connect-woo' );
					$font_color    = 'color: red;';
				}
				?>
					<tr>
						<td colspan="2" class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"> <?php esc_html_e( 'Transfer to', 'bsd-split-pay-stripe-connect-woo' ); ?> : <?php echo $transfer_detail['account_id']; ?></td>
						<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"><?php echo $transfer_type; ?></td>
						<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; <?php echo $font_color; ?>"><?php echo $account_value; ?></td>
					</tr>
				<?php
			}
		}
	}
	?>
	<?php

	if ( isset( $email_obj->transfer_data ) ) {

		$transfer_account = $email_obj->transfer_data;
		foreach ( $transfer_account['product_shipping_transfer'] as $transfer_detail ) {
			if ( $product->get_id() == $transfer_detail['product_id'] ) {
				$font_color = '';

				$transfer_url = '#';
				if ( isset( $transfer_detail['transfer']->id ) && ! empty( $transfer_detail['transfer']->id ) ) {
					$transfer_url = 'https://dashboard.stripe.com/' . $stripe_mode . '/connect/transfers/' . esc_attr( $transfer_detail['transfer']->id );
				}

				if ( $transfer_detail['transfer_type'] == 'percentage' ) {
					$transfer_type = esc_html__( 'Percentage:', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . $transfer_detail['transfer_percentage_or_fixed'] . '%';
					$account_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['transfer_value'] ) . '</a>';
				} else {
					$transfer_type = esc_html__( 'Fixed', 'bsd-split-pay-stripe-connect-woo' );
					$account_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['transfer_value'] ) . '</a>';
				}
				if ( isset( $transfer_detail['status'] ) && $transfer_detail['status'] == 'failed' ) {
					$account_value = esc_html__( 'Failed', 'bsd-split-pay-stripe-connect-woo' );
					$font_color    = 'color: red;';
				}

				?>
					<tr>
						<td colspan="2" class="td" style="text-align: right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"> <?php esc_html_e( 'Shipping Transfer to', 'bsd-split-pay-stripe-connect-woo' ); ?> : <?php echo $transfer_detail['account_id']; ?></td>
						<td class="td" style="text-align: right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"><?php echo $transfer_type; ?></td>
						<td class="td" style="text-align: right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; <?php echo $font_color; ?>"><?php echo $account_value; ?></td>
					</tr>
				<?php
			}
		}
	}

	if ( isset( $email_obj->transfer_data ) ) {

		$transfer_account = $email_obj->transfer_data;
		foreach ( $transfer_account['product_transfer'] as $transfer_detail ) {
			if ( $product->get_id() == $transfer_detail['product_id'] && isset( $transfer_detail['account_wise_tax'] ) && ! empty( $transfer_detail['account_wise_tax'] ) ) {
				$font_color = '';

				$transfer_url = '#';
				if ( isset( $transfer_detail['transfer']->id ) && ! empty( $transfer_detail['transfer']->id ) ) {
					$transfer_url = 'https://dashboard.stripe.com/' . $stripe_mode . '/connect/transfers/' . esc_attr( $transfer_detail['transfer']->id );
					}

				if ( $transfer_detail['transfer_type'] == 'percentage' ) {
					$transfer_type = esc_html__( 'Percentage:', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . $transfer_detail['transfer_percentage_or_fixed'] . '%';

					$account_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['account_wise_tax'] ) . '</a>';
					} else {
					$transfer_type = esc_html__( 'Fixed Amount :', 'bsd-split-pay-stripe-connect-woo' ) . '<br/>' . wc_price( $transfer_detail['account_wise_tax'] );

					$account_value = '<a href="' . esc_url( $transfer_url ) . '" target="_blank">' . wc_price( $transfer_detail['account_wise_tax'] ) . '</a>';
					}
				if ( isset( $transfer_detail['status'] ) && $transfer_detail['status'] == 'failed' ) {
					$account_value = esc_html__( 'Failed', 'bsd-split-pay-stripe-connect-woo' );
					$font_color    = 'color: red;';
					}
				?>
						<tr>
							<td colspan="2" class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"> <?php esc_html_e( 'Tax Transfer to', 'bsd-split-pay-stripe-connect-woo' ); ?> : <?php echo $transfer_detail['account_id']; ?></td>
							<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;"><?php echo $transfer_type; ?></td>
							<td class="td" style="text-align:right; background-color: #f7f7f7; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; <?php echo $font_color; ?>"><?php echo $account_value; ?></td>
						</tr>
					<?php
			}
		}
	}


	if ( $show_purchase_note && $purchase_note ) {
		?>
		<tr>
			<td colspan="3" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align:middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;">
				<?php
				echo wp_kses_post( wpautop( do_shortcode( $purchase_note ) ) );
				?>
			</td>
		</tr>
		<?php
	}
	?>

<?php endforeach; ?>
