<?php

/**
 * @file
 * Search data html builder file.
 * @package     bspscw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Sorry!' );
}
global $post, $bsd_sca;
$get_accounts = $bsd_sca->get_stored_accounts();
$page         = 1;
$html         = $search = '';
$tax_query    = $categories = $pro_query = $products_id = $product_attribute_query = $pv_query = array();
$pages        = array();

if ( isset( $_POST['filter_type'] ) && ! empty( $_POST['filter_type'] ) ) {
	$filter_contain = $_POST['filter_contain'];
	$filter_value   = $_POST['filter_value'];

	foreach ( $_POST['filter_type'] as $key => $value ) {
		if ( $value == 'categories' && isset( $filter_contain[ $key ] ) && isset( $filter_value[ $key ] ) ) { // categories
			$categories[ $key ]['condition'] = str_replace(array( 'is_any_of', 'is_none_of' ), array(
				'IN',
				'NOT IN',
			), $filter_contain[ $key ]);
			$categories[ $key ]['value']     = $filter_value[ $key ];
		} elseif ( $value == 'post_title' ) { // product
			$pro_query[ $key ]['title']['condition'] = $filter_contain[ $key ];
			$pro_query[ $key ]['title']['value']     = $filter_value[ $key ];
		} elseif ( $value == 'pa' ) {
			$product_attributes = wc_get_attribute_taxonomies();
			if ( ! empty( $product_attributes ) ) {
				$i                                   = 0;
				$product_attribute_query['relation'] = 'OR';
				foreach ( $product_attributes as $pak ) {
					$product_attribute_query[ $i ]['key']     = 'attribute_pa_' . $pak->attribute_name;
					$product_attribute_query[ $i ]['value']   = 'white';
					$product_attribute_query[ $i ]['compare'] = 'LIKE';
					++$i;
				}

				$pv_args  = array(
					'post_type'      => array( 'product_variation' ),
					'post_status'    => 'publish',
					'posts_per_page' => 20,
					'paged'          => $page,
					'order'          => 'DESC',
					'orderby'        => 'ID',
					'fields'         => 'id=>parent',
					'meta_query'     => $product_attribute_query,
				);
				$pv_query = new WP_Query( $pv_args );
			}
		} else { // sku
			$pro_query[ $key ]['sku']['condition'] = $filter_contain[ $key ];
			$pro_query[ $key ]['sku']['value']     = $filter_value[ $key ];
		}
	}
}


if ( ! empty( $categories ) ) {
	foreach ( $categories as $key => $cat ) {
		$tax_query[ $key ]['relation'] = 'AND';
		$tax_query[ $key ][]           = array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_taxonomy_id ',
			'terms'    => $cat['value'],
			'operator' => $cat['condition'],
		);
	}
}

if ( isset( $_POST['paged'] ) && ! empty( $_POST['paged'] ) ) {
	$page = $_POST['paged'];
}


$product_args = array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 20,
	'paged'          => $page,
	'order'          => 'DESC',
	'orderby'        => 'ID',
);


if ( ! empty( $pro_query ) ) {
	$products_id = $this->get_product_title_sku_result( $pro_query );
	if ( ! empty( $products_id ) ) {
		$product_args['post__in'] = $products_id;
	}
}

if ( ! empty( $pv_query ) ) {
	$parent_ids = array();
	if ( isset( $pv_query->posts ) && ! empty( $pv_query->posts ) ) {
		foreach ( $pv_query->posts as $pvpk ) {
			$parent_ids[] = $pvpk->post_parent;
		}
	}
	if ( ! empty( $parent_ids ) ) {
		if ( isset( $product_args['post__in'] ) && ! empty( $product_args['post__in'] ) ) {
			$product_args['post__in'] = array_merge( $product_args['post__in'], $parent_ids );
		} else {
			$product_args['post__in'] = $parent_ids;
		}
	}
}

if ( ! empty( $tax_query ) ) {
	$product_args['tax_query'] = $tax_query;
}

$product_query = new WP_Query( $product_args );

if ( $product_query->have_posts() ) {
	ob_start();
?>
	<form name="bulk_editor" id="bulk-editor-save" method="post" action="">
		<div class="our-pro-bar">

			<button class="n-btn save-bulk-edit" type="submit"><?php echo esc_html( 'Save Changes', 'bsd-split-pay-stripe-connect-woo' ); ?></button>
			<?php
			$total_pages = $product_query->max_num_pages;
			$big         = 999999999; // need an unlikely integer

			if ( $total_pages > 1 ) {
				$current_page = max( 1, $page );

				$pages = paginate_links(array(
					'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
					'format'    => '?paged=%#%',
					'current'   => $current_page,
					'total'     => $total_pages,
					'prev_text' => esc_html( '«', 'bsd-split-pay-stripe-connect-woo' ),
					'next_text' => esc_html( '»', 'bsd-split-pay-stripe-connect-woo' ),
					'type'      => 'array',
				));
				if ( is_array( $pages ) ) {
					$paged = ( get_query_var( 'paged' ) == 0 ) ? 1 : get_query_var( 'paged' );
					echo '<ul class="pagination">';
					foreach ( $pages as $page ) {
						echo "<li>$page</li>";
					}
					echo '</ul>';
				}
			}
			$total_records = $product_query->found_posts;
			?>
			<div class="result-coutn"><?php echo esc_html( $total_records . ' records found', 'bsd-split-pay-stripe-connect-woo' ); ?></div>
		</div>
		<div class="our-pro-table__cover">
			<div class="loader-block result-loader">
				<div class="loader-pro"></div>
			</div>
			<table class="our-pro-table" cellspacing="0" cellpadding="0">
				<tr>
					<th class="product-th"><?php echo esc_html( 'Product', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
					<th class="attribute-th"><?php echo esc_html( 'Variation / Attribute', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
					<th class="connected-th"><?php echo esc_html( 'Connected Stripe Account', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
					<th class="transfer-th"><?php echo esc_html( 'Transfer Type', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
					<th class="value-th"><?php echo esc_html( 'Value', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
					<th class="shipping-th"><?php echo esc_html( 'Shipping Transfer Type', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
					<th class="value2-th"><?php echo esc_html( 'Value', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
					<th class="category-th"><?php echo esc_html( 'Category', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
					<th class="sku-th"><?php echo esc_html( 'SKU', 'bsd-split-pay-stripe-connect-woo' ); ?></th>
				</tr>
				<?php


				$odd_even = 1;
				while ( $product_query->have_posts() ) :
					$product_query->the_post();

					$post_id  = get_the_ID();
					$product  = wc_get_product( $post_id );
					$cat_name = array();
					$prod_cat = get_the_terms( $post_id, 'product_cat' );
					foreach ( $prod_cat as $cat ) {
						$cat_name[] = $cat->name;
					}
					$sku          = $product->get_sku();
					$product_type = $product->get_type();

					if ( $product_type == 'simple' ) {

						$_bsd_spscwt_product_connected_account = get_post_meta( $post_id, '_bsd_spscwt_product_connected_account', true );

						if ( ! empty( $_bsd_spscwt_product_connected_account ) ) {
							$_bsd_spscwt_product_type                      = get_post_meta( $post_id, '_bsd_spscwt_product_type', true );
							$_stripe_connect_split_pay_transfer_percentage = get_post_meta( $post_id, '_stripe_connect_split_pay_transfer_percentage', true );
							$_bsd_spscwt_product_amount                    = get_post_meta( $post_id, '_bsd_spscwt_product_amount', true );

							$_bsd_spscwt_shipping_type     = get_post_meta( $post_id, '_bsd_spscwt_shipping_type', true );
							$_bsd_prod_shipping_percentage = get_post_meta( $post_id, '_bsd_prod_shipping_percentage', true );
							$_bsd_prod_shipping_amount     = get_post_meta( $post_id, '_bsd_spscwt_shipping_amount', true );
							$last_row_counter              = 1;
							$total_rows_counter            = count( $_bsd_spscwt_product_connected_account );
							foreach ( $_bsd_spscwt_product_connected_account as $bsppcak => $bsppcav ) {
				?>
								<input type="hidden" name="product_type[<?php echo $post_id; ?>]" value="<?php echo $product_type; ?>" />

								<?php
								if ( $last_row_counter == 1 ) {
								?>
									<tr odd_even="<?php echo $odd_even; ?>" class="simple-product-tr <?php echo ( $total_rows_counter == $last_row_counter ) ? 'border-bottom-1' : ''; ?> <?php echo ( $odd_even % 2 == 1 ) ? 'odd ' : 'even'; ?>" >
										<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?> td-right-border variable-td-border-bottom" rowspan="<?php echo $total_rows_counter; ?>">
											<a href="<?php echo get_edit_post_link( $post_id ); ?>" target="_blank">
												<?php echo esc_html( get_the_title(), 'bsd-split-pay-stripe-connect-woo' ); ?>
											</a>
										</td>
										<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?> text-center variable-td-border-bottom" rowspan="<?php echo $total_rows_counter; ?>"><?php echo esc_html( 'Simple product', 'bsd-split-pay-stripe-connect-woo' ); ?></td>
										<td class="select_account_td_wrapper">
											<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<select name="_bsd_spscwt_product_connected_account[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]" id="sspscwsca_select_<?php echo $bsppcak; ?>" class="sspscwsca_select">
													<?php

													if ( ! empty( $get_accounts ) ) {
													?>
														<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
														<?php
														foreach ( $get_accounts as $gak ) {
														?>
															<option value="<?php echo $gak['bsd_account_id']; ?>" <?php echo ( $gak['bsd_account_id'] == $bsppcav ) ? "selected='selected'" : ''; ?>><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
															<?php
														}
													}
													?>

												</select>


												<div class="edit-form-btns select_account_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>
											<div class="selected_account_id_wrapper"><?php echo $bsppcav; ?></div>
										</td>

										<td class="prod_selected_tt_td_wrapper">
											<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<select name="_bsd_spscwt_product_type[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]" class="bsd_spscwt_type" id="bsd_spscwt_type_<?php echo $post_id . '_' . $bsppcak; ?>">
													<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
													<option value="percentage" <?php echo ( 'percentage' == $_bsd_spscwt_product_type[ $bsppcak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
													<option value="amount" <?php echo ( 'amount' == $_bsd_spscwt_product_type[ $bsppcak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
												</select>
												<div class="edit-form-btns prod_selected_tt_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>
											<div class="selecte_tt_wrapper">
												<?php
												if ( $_bsd_spscwt_product_type[ $bsppcak ] == 'percentage' ) {
													esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
												} elseif ( $_bsd_spscwt_product_type[ $bsppcak ] == 'amount' ) {
													esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
												}
												?>
											</div>
										</td>
										<td class="prod_tv_td_wrapper">
											<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="<?php echo $post_id . '_' . $bsppcak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_<?php echo $post_id . '_' . $bsppcak; ?>' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]' value="<?php echo $_stripe_connect_split_pay_transfer_percentage[ $bsppcak ]; ?>" placeholder="e.g. 10" class="bsd_spscwtp_input <?php echo ( 'amount' == $_bsd_spscwt_product_type[ $bsppcak ] ) ? 'bsd_hidden' : ''; ?>" />
												<input type='number' min='0' step=".01" id='bsd_spscwt_amount_<?php echo $post_id . '_' . $bsppcak; ?>' name='_bsd_spscwt_product_amount[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]' value="<?php echo $_bsd_spscwt_product_amount[ $bsppcak ]; ?>" placeholder="e.g. 20" class="bsd_spscwt_amount <?php echo ( 'percentage' == $_bsd_spscwt_product_type[ $bsppcak ] ) ? 'bsd_hidden' : ''; ?>" />
												<div class="edit-form-btns prod_tv_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>
											<div class="selected_transfer_value_wrapper">
												<?php
												if ( $_bsd_spscwt_product_type[ $bsppcak ] == 'percentage' ) {
													echo $_stripe_connect_split_pay_transfer_percentage[ $bsppcak ];
												} else {
													echo $_bsd_spscwt_product_amount[ $bsppcak ];
												}
												?>
											</div>
										</td>
										<td class="prod_selected_st_td_wrapper">

											<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<select name="bsd_spscwt_shipping_type[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_<?php echo $post_id . '_' . $bsppcak; ?>">
													<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
													<option value="percentage" <?php echo ( isset( $_bsd_spscwt_shipping_type[ $bsppcak ] ) && 'percentage' == $_bsd_spscwt_shipping_type[ $bsppcak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Percentage' ); ?></option>
													<option value="amount" <?php echo ( isset( $_bsd_spscwt_shipping_type[ $bsppcak ] ) && 'amount' == $_bsd_spscwt_shipping_type[ $bsppcak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Fixed Amount' ); ?></option>
												</select>

												<div class="edit-form-btns prod_selected_st_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>

											<div class="selected_shipping_type_wrapper">
												<?php
												if ( $_bsd_spscwt_shipping_type[ $bsppcak ] == 'percentage' ) {
													esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
												} elseif ( $_bsd_spscwt_shipping_type[ $bsppcak ] == 'amount' ) {
													esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
												}
												?>
											</div>
										</td>
										<td class="prod_stv_td_wrapper">

											<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="<?php echo $post_id . '_' . $bsppcak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_<?php echo $post_id . '_' . $bsppcak; ?>' name='bsd_prod_shipping_percentage[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]' value="<?php echo ( isset( $_bsd_prod_shipping_percentage[ $bsppcak ] ) ) ? $_bsd_prod_shipping_percentage[ $bsppcak ] : ''; ?>" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input <?php echo ( isset( $_bsd_spscwt_shipping_type[ $bsppcak ] ) && 'amount' == $_bsd_spscwt_shipping_type[ $bsppcak ] ) ? 'bsd_hidden' : ''; ?>" />

												<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_<?php echo $post_id . '_' . $bsppcak; ?>' name='bsd_prod_shipping_amount[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]' value="<?php echo ( isset( $_bsd_prod_shipping_amount[ $bsppcak ] ) ) ? $_bsd_prod_shipping_amount[ $bsppcak ] : ''; ?>" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount <?php echo ( ( empty( $_bsd_spscwt_shipping_type[ $bsppcak ] ) || 'percentage' == $_bsd_spscwt_shipping_type[ $bsppcak ] ) ) ? 'bsd_hidden' : ''; ?>" />
												<div class="edit-form-btns prod_stv_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>

											<div class="selected_shipping_digit_wrapper">
												<?php
												if ( $_bsd_spscwt_shipping_type[ $bsppcak ] == 'percentage' ) {
													echo $_bsd_prod_shipping_percentage[ $bsppcak ];
												} else {
													echo $_bsd_prod_shipping_amount[ $bsppcak ];
												}
												?>
											</div>
										</td>
										<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
										<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $sku, 'bsd-split-pay-stripe-connect-woo' ); ?></td>
									</tr>
									<?php
								} else {
								?>
									<tr odd_even="<?php echo $odd_even; ?>" class="simple-product-tr <?php echo ( $total_rows_counter == $last_row_counter ) ? 'border-bottom-1' : ''; ?> <?php echo ( $odd_even % 2 == 1 ) ? 'odd ' : 'even'; ?>">
										<!-- <td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?> text-center"><?php echo esc_html( 'Simple product', 'bsd-split-pay-stripe-connect-woo' ); ?></td> -->
										<td class="select_account_td_wrapper">
											<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<select name="_bsd_spscwt_product_connected_account[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]" id="sspscwsca_select_<?php echo $bsppcak; ?>" class="sspscwsca_select">
													<?php

													if ( ! empty( $get_accounts ) ) {
													?>
														<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
														<?php
														foreach ( $get_accounts as $gak ) {
														?>
															<option value="<?php echo $gak['bsd_account_id']; ?>" <?php echo ( $gak['bsd_account_id'] == $bsppcav ) ? "selected='selected'" : ''; ?>><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
															<?php
														}
													}
													?>

												</select>


												<div class="edit-form-btns select_account_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>
											<div class="selected_account_id_wrapper"><?php echo $bsppcav; ?></div>
										</td>

										<td class="prod_selected_tt_td_wrapper">
											<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<select name="_bsd_spscwt_product_type[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]" class="bsd_spscwt_type" id="bsd_spscwt_type_<?php echo $post_id . '_' . $bsppcak; ?>">
													<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
													<option value="percentage" <?php echo ( 'percentage' == $_bsd_spscwt_product_type[ $bsppcak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
													<option value="amount" <?php echo ( 'amount' == $_bsd_spscwt_product_type[ $bsppcak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
												</select>
												<div class="edit-form-btns prod_selected_tt_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>
											<div class="selecte_tt_wrapper">
												<?php
												if ( $_bsd_spscwt_product_type[ $bsppcak ] == 'percentage' ) {
													esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
												} elseif ( $_bsd_spscwt_product_type[ $bsppcak ] == 'amount' ) {
													esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
												}
												?>
											</div>
										</td>
										<td class="prod_tv_td_wrapper">
											<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="<?php echo $post_id . '_' . $bsppcak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_<?php echo $post_id . '_' . $bsppcak; ?>' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]' value="<?php echo $_stripe_connect_split_pay_transfer_percentage[ $bsppcak ]; ?>" placeholder="e.g. 10" class="bsd_spscwtp_input <?php echo ( 'amount' == $_bsd_spscwt_product_type[ $bsppcak ] ) ? 'bsd_hidden' : ''; ?>" />
												<input type='number' min='0' step=".01" id='bsd_spscwt_amount_<?php echo $post_id . '_' . $bsppcak; ?>' name='_bsd_spscwt_product_amount[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]' value="<?php echo $_bsd_spscwt_product_amount[ $bsppcak ]; ?>" placeholder="e.g. 20" class="bsd_spscwt_amount <?php echo ( 'percentage' == $_bsd_spscwt_product_type[ $bsppcak ] ) ? 'bsd_hidden' : ''; ?>" />
												<div class="edit-form-btns prod_tv_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>
											<div class="selected_transfer_value_wrapper">
												<?php
												if ( $_bsd_spscwt_product_type[ $bsppcak ] == 'percentage' ) {
													echo $_stripe_connect_split_pay_transfer_percentage[ $bsppcak ];
												} else {
													echo $_bsd_spscwt_product_amount[ $bsppcak ];
												}
												?>
											</div>
										</td>
										<td class="prod_selected_st_td_wrapper">

											<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<select name="bsd_spscwt_shipping_type[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_<?php echo $post_id . '_' . $bsppcak; ?>">
													<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
													<option value="percentage" <?php echo ( isset( $_bsd_spscwt_shipping_type[ $bsppcak ] ) && 'percentage' == $_bsd_spscwt_shipping_type[ $bsppcak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Percentage' ); ?></option>
													<option value="amount" <?php echo ( isset( $_bsd_spscwt_shipping_type[ $bsppcak ] ) && 'amount' == $_bsd_spscwt_shipping_type[ $bsppcak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Fixed Amount' ); ?></option>
												</select>

												<div class="edit-form-btns prod_selected_st_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>

											<div class="selected_shipping_type_wrapper">
												<?php
												if ( $_bsd_spscwt_shipping_type[ $bsppcak ] == 'percentage' ) {
													esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
												} elseif ( $_bsd_spscwt_shipping_type[ $bsppcak ] == 'amount' ) {
													esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
												}
												?>
											</div>
										</td>
										<td class="prod_stv_td_wrapper">

											<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="<?php echo $post_id . '_' . $bsppcak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_<?php echo $post_id . '_' . $bsppcak; ?>' name='bsd_prod_shipping_percentage[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]' value="<?php echo ( isset( $_bsd_prod_shipping_percentage[ $bsppcak ] ) ) ? $_bsd_prod_shipping_percentage[ $bsppcak ] : ''; ?>" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input <?php echo ( isset( $_bsd_spscwt_shipping_type[ $bsppcak ] ) && 'amount' == $_bsd_spscwt_shipping_type[ $bsppcak ] ) ? 'bsd_hidden' : ''; ?>" />

												<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_<?php echo $post_id . '_' . $bsppcak; ?>' name='bsd_prod_shipping_amount[<?php echo $post_id; ?>][<?php echo $bsppcak; ?>]' value="<?php echo ( isset( $_bsd_prod_shipping_amount[ $bsppcak ] ) ) ? $_bsd_prod_shipping_amount[ $bsppcak ] : ''; ?>" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount <?php echo ( ( empty( $_bsd_spscwt_shipping_type[ $bsppcak ] ) || 'percentage' == $_bsd_spscwt_shipping_type[ $bsppcak ] ) ) ? 'bsd_hidden' : ''; ?>" />
												<div class="edit-form-btns prod_stv_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>

											<div class="selected_shipping_digit_wrapper">
												<?php
												if ( $_bsd_spscwt_shipping_type[ $bsppcak ] == 'percentage' ) {
													echo $_bsd_prod_shipping_percentage[ $bsppcak ];
												} else {
													echo $_bsd_prod_shipping_amount[ $bsppcak ];
												}
												?>
											</div>
										</td>
										<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
										<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $sku, 'bsd-split-pay-stripe-connect-woo' ); ?></td>
									</tr>
									<?php
								}
								?>

								<?php
								++$last_row_counter;
							}
						}
						if ( empty( $_bsd_spscwt_product_connected_account ) ) {
							?>
							<input type="hidden" name="product_type[<?php echo $post_id; ?>]" value="<?php echo $product_type; ?>" />
							<tr odd_even="<?php echo $odd_even; ?>" class="simple-product-tr border-bottom-1 <?php echo ( $odd_even % 2 == 1 ) ? 'odd ' : 'even'; ?>">
								<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?> td-right-border variable-td-border-bottom" rowspan="1">
									<a href="<?php echo get_edit_post_link( $post_id ); ?>" target="_blank">
										<?php echo esc_html( get_the_title(), 'bsd-split-pay-stripe-connect-woo' ); ?>
									</a>
								</td>
								<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( 'Simple product', 'bsd-split-pay-stripe-connect-woo' ); ?></td>
								<td class="select_account_td_wrapper">
									<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<select name="_bsd_spscwt_product_connected_account[<?php echo $post_id; ?>][0]" id="sspscwsca_select_0" class="sspscwsca_select">
											<?php

											if ( ! empty( $get_accounts ) ) {
											?>
												<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
												<?php
												foreach ( $get_accounts as $gak ) {
												?>
													<option value="<?php echo $gak['bsd_account_id']; ?>"><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
													<?php
												}
											}
											?>

										</select>


										<div class="edit-form-btns select_account_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>
									<div class="selected_account_id_wrapper"></div>
								</td>

								<td class="prod_selected_tt_td_wrapper">
									<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<select name="_bsd_spscwt_product_type[<?php echo $post_id; ?>][0]" class="bsd_spscwt_type" id="bsd_spscwt_type_zero">
											<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
											<option value="percentage"><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
											<option value="amount"><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
										</select>
										<div class="edit-form-btns prod_selected_tt_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>
									<div class="selecte_tt_wrapper"></div>
								</td>
								<td class="prod_tv_td_wrapper">
									<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="zero"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_zero' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $post_id; ?>][0]' value="" placeholder="e.g. 10" class="bsd_spscwtp_input" />
										<input type='number' min='0' step=".01" id='bsd_spscwt_amount_zero' name='_bsd_spscwt_product_amount[<?php echo $post_id; ?>][0]' value="" placeholder="e.g. 20" class="bsd_spscwt_amount bsd_hidden" />
										<div class="edit-form-btns prod_tv_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>
									<div class="selected_transfer_value_wrapper"></div>
								</td>
								<td class="prod_selected_st_td_wrapper">

									<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<select name="bsd_spscwt_shipping_type[<?php echo $post_id; ?>][0]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_zero">
											<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
											<option value="percentage"><?php echo esc_html( 'Percentage' ); ?></option>
											<option value="amount"><?php echo esc_html( 'Fixed Amount' ); ?></option>
										</select>

										<div class="edit-form-btns prod_selected_st_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>

									<div class="selected_shipping_type_wrapper"></div>
								</td>
								<td class="prod_stv_td_wrapper">

									<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="zero"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_zero' name='bsd_prod_shipping_percentage[<?php echo $post_id; ?>][0]' value="" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input" />

										<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_zero' name='bsd_prod_shipping_amount[<?php echo $post_id; ?>][0]' value="" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount bsd_hidden" />
										<div class="edit-form-btns prod_stv_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>

									<div class="selected_shipping_digit_wrapper"></div>
								</td>
								<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
								<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $sku, 'bsd-split-pay-stripe-connect-woo' ); ?></td>
							</tr>
							<?php
						}
						/*
						if ( isset( $_POST['search_type'] ) && $_POST['search_type'] == 'show-all' && empty( $_bsd_spscwt_product_connected_account ) ) {
							?>
							<input type="hidden" name="product_type[<?php echo $post_id; ?>]" value="<?php echo $product_type; ?>" />
							<tr odd_even="<?php echo $odd_even; ?>" class="simple-product-tr border-bottom-1 <?php echo ( $odd_even % 2 == 1 ) ? 'odd ' : 'even'; ?>">
								<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?> td-right-border variable-td-border-bottom" rowspan="1">
									<a href="<?php echo get_edit_post_link( $post_id ); ?>" target="_blank">
										<?php echo esc_html( get_the_title(), 'bsd-split-pay-stripe-connect-woo' ); ?>
									</a>
								</td>
								<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( 'Simple product', 'bsd-split-pay-stripe-connect-woo' ); ?></td>
								<td class="select_account_td_wrapper">
									<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<select name="_bsd_spscwt_product_connected_account[<?php echo $post_id; ?>][0]" id="sspscwsca_select_0" class="sspscwsca_select">
											<?php

											if ( ! empty( $get_accounts ) ) {
											?>
												<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
												<?php
												foreach ( $get_accounts as $gak ) {
												?>
													<option value="<?php echo $gak['bsd_account_id']; ?>"><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
													<?php
												}
											}
											?>

										</select>


										<div class="edit-form-btns select_account_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>
									<div class="selected_account_id_wrapper"></div>
								</td>

								<td class="prod_selected_tt_td_wrapper">
									<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<select name="_bsd_spscwt_product_type[<?php echo $post_id; ?>][0]" class="bsd_spscwt_type" id="bsd_spscwt_type_zero">
											<option value="percentage"><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
											<option value="amount"><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
										</select>
										<div class="edit-form-btns prod_selected_tt_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>
									<div class="selecte_tt_wrapper"></div>
								</td>
								<td class="prod_tv_td_wrapper">
									<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="zero"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_zero' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $post_id; ?>][0]' value="" placeholder="e.g. 10" class="bsd_spscwtp_input" />
										<input type='number' min='0' step=".01" id='bsd_spscwt_amount_zero' name='_bsd_spscwt_product_amount[<?php echo $post_id; ?>][0]' value="" placeholder="e.g. 20" class="bsd_spscwt_amount bsd_hidden" />
										<div class="edit-form-btns prod_tv_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>
									<div class="selected_transfer_value_wrapper"></div>
								</td>
								<td class="prod_selected_st_td_wrapper">

									<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<select name="bsd_spscwt_shipping_type[<?php echo $post_id; ?>][0]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_zero">
											<option value="percentage"><?php echo esc_html( 'Percentage' ); ?></option>
											<option value="amount"><?php echo esc_html( 'Fixed Amount' ); ?></option>
										</select>

										<div class="edit-form-btns prod_selected_st_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>

									<div class="selected_shipping_type_wrapper"></div>
								</td>
								<td class="prod_stv_td_wrapper">

									<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="zero"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
									<div class="edit-form">
										<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_zero' name='bsd_prod_shipping_percentage[<?php echo $post_id; ?>][0]' value="" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input" />

										<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_zero' name='bsd_prod_shipping_amount[<?php echo $post_id; ?>][0]' value="" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount bsd_hidden" />
										<div class="edit-form-btns prod_stv_btns">
											<button class="edit-form-ok-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
											<button class="edit-form-cancle-btn" type="button">
												<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
											</button>
										</div>
									</div>

									<div class="selected_shipping_digit_wrapper"></div>
								</td>
								<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
								<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $sku, 'bsd-split-pay-stripe-connect-woo' ); ?></td>
							</tr>
							<?php
						} */
					}

					if ( $product_type == 'variable' ) {

						$variations        = $product->get_available_variations();
						$variation_counter = count( $variations );
						$first_td_counter  = 1;
						?>
						<?php if ( ! empty( $variations ) ) {
							$variation_td_counter = 1;
							$first_td_rowspan     = 0;
							foreach ( $variations as $variation ) {
								$variation_id              = $variation['variation_id'];
								$connected_account         = get_post_meta( $variation_id, '_bsd_spscwt_product_connected_account', true );
								$connected_account_counter = is_array( $connected_account ) ? count( $connected_account ) : 0;
								$first_td_rowspan          = $first_td_rowspan + $connected_account_counter;
							}

							foreach ( $variations as $variation ) {
								$variation_id = $variation['variation_id'];

								$connected_account                             = get_post_meta( $variation_id, '_bsd_spscwt_product_connected_account', true );
								$connected_product_type                        = get_post_meta( $variation_id, '_bsd_spscwt_product_type', true );
								$product_amount                                = get_post_meta( $variation_id, '_bsd_spscwt_product_amount', true );
								$_stripe_connect_split_pay_transfer_percentage = get_post_meta( $variation_id, '_stripe_connect_split_pay_transfer_percentage', true );

								/* shipping */
								$connected_shipping_type       = get_post_meta( $variation_id, '_bsd_spscwt_shipping_type', true );
								$shipping_amount               = get_post_meta( $variation_id, '_bsd_prod_shipping_amount', true );
								$_bsd_prod_shipping_percentage = get_post_meta( $variation_id, '_bsd_prod_shipping_percentage', true );

								$connected_account_counter    = is_array( $connected_account ) ? count( $connected_account ) : 0;
								$connected_account_td_counter = 1;
								$last_row_counter             = 1;
								if ( $connected_account_counter ) {
									foreach ( $connected_account as $cak => $cav ) {
						?>

										<input type="hidden" name="product_type[<?php echo $variation_id; ?>]" value="<?php echo $product_type; ?>" />

										<?php
										if ( $first_td_counter == 1 && $variation_td_counter == 1 && $connected_account_td_counter == 1 ) {
										?>
											<tr condition="<?php echo 'first_td_counter == 1 && variation_td_counter == 1 && connected_account_td_counter == 1'; ?>" product_amount="<?php echo json_encode( $product_amount ); ?>" odd_even="<?php echo $odd_even; ?>" connected_account_data="<?php echo json_encode( $connected_account ); ?>" variation_id="<?php echo $variation_id; ?>" first_td_counter="<?php echo $first_td_counter; ?>" variation_td_counter="<?php echo $variation_td_counter; ?>" connected_account_td_counter="<?php echo $connected_account_td_counter; ?>" class="variable-product-tr <?php echo ( $odd_even % 2 == 0 ) ? 'even' : 'odd'; ?> <?php echo ( $connected_account_counter == $last_row_counter ) ? 'border-bottom-1' : ''; ?>">
												<td rowspan="<?php echo $first_td_rowspan; ?>" class="variable-td-border-bottom <?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?> td-right-border">
													<a href="<?php echo get_edit_post_link( $post_id ); ?>" target="_blank"><?php echo esc_html( get_the_title(), 'bsd-split-pay-stripe-connect-woo' ); ?></a>
												</td>
												<td class="variable-td-border-bottom text-center <?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>" rowspan="<?php echo $connected_account_counter; ?>"><?php echo ucfirst( implode( '<br>', $variation['attributes'] ) ); ?></td>

												<td class="select_account_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_connected_account[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" id="sspscwsca_select_<?php echo $variation_id . '_' . $cak; ?>" class="sspscwsca_select">
															<?php

															if ( ! empty( $get_accounts ) ) {
															?>
																<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
																<?php
																foreach ( $get_accounts as $gak ) {
																?>
																	<option value="<?php echo $gak['bsd_account_id']; ?>" <?php echo ( $gak['bsd_account_id'] == $cav ) ? "selected='selected'" : ''; ?>><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
																	<?php
																}
															}
															?>

														</select>


														<div class="edit-form-btns select_account_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_account_id_wrapper"><?php echo $cav; ?></div>
												</td>

												<td class="prod_selected_tt_td_wrapper">

													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_type" id="bsd_spscwt_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="percentage" <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="amount" <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
														</select>
														<div class="edit-form-btns prod_selected_tt_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selecte_tt_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_product_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>

												<td class="prod_tv_td_wrapper">

													<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>" placeholder="e.g. 10" class="bsd_spscwtp_input <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_spscwt_amount_<?php echo $variation_id . '_' . $cak; ?>' name='_bsd_spscwt_product_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $product_amount[ $cak ]; ?>" placeholder="e.g. 20" class="bsd_spscwt_amount <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_tv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_transfer_value_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $product_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>

												<td class="prod_selected_st_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<select name="bsd_spscwt_shipping_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="percentage" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'percentage' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Percentage' ); ?></option>
															<option value="amount" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Fixed Amount' ); ?></option>
														</select>

														<div class="edit-form-btns prod_selected_st_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_type_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_shipping_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>

												<td class="prod_stv_td_wrapper">


													<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $_bsd_prod_shipping_percentage[ $cak ] ) ) ? $_bsd_prod_shipping_percentage[ $cak ] : ''; ?>" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $shipping_amount[ $cak ] ) ) ? $shipping_amount[ $cak ] : ''; ?>" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount <?php echo ( ( empty( $connected_shipping_type[ $cak ] ) || 'percentage' == $connected_shipping_type[ $cak ] ) ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_stv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_digit_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_bsd_prod_shipping_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $shipping_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>
												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $variation['sku'], 'bsd-split-pay-stripe-connect-woo' ); ?></td>
											</tr>
											<?php
										}
										?>

										<?php
										if ( $first_td_counter == 1 && $variation_td_counter > 1 && $connected_account_td_counter == 1 ) {
										?>
											<tr condition="<?php echo 'first_td_counter == 1 && variation_td_counter > 1 && connected_account_td_counter == 1'; ?>" product_amount="<?php echo json_encode( $product_amount ); ?>" odd_even="<?php echo $odd_even; ?>" connected_account_data="<?php echo json_encode( $connected_account ); ?>" variation_id="<?php echo $variation_id; ?>" first_td_counter="<?php echo $first_td_counter; ?>" variation_td_counter="<?php echo $variation_td_counter; ?>" connected_account_td_counter="<?php echo $connected_account_td_counter; ?>" class="variable-product-tr <?php echo ( $odd_even % 2 == 0 ) ? 'even' : 'odd'; ?> <?php echo ( $connected_account_counter == $last_row_counter ) ? 'border-bottom-1' : ''; ?>">
												<td rowspan="<?php echo $first_td_rowspan; ?>" class="variable-td-border-bottom <?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?> td-right-border">
													<a href="<?php echo get_edit_post_link( $post_id ); ?>" target="_blank"><?php echo esc_html( get_the_title(), 'bsd-split-pay-stripe-connect-woo' ); ?></a>
												</td>
												<td class="variable-td-border-bottom text-center <?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>" rowspan="<?php echo $connected_account_counter; ?>"><?php echo ucfirst( implode( '<br>', $variation['attributes'] ) ); ?></td>

												<td class="select_account_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_connected_account[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" id="sspscwsca_select_<?php echo $variation_id . '_' . $cak; ?>" class="sspscwsca_select">
															<?php

															if ( ! empty( $get_accounts ) ) {
															?>
																<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
																<?php
																foreach ( $get_accounts as $gak ) {
																?>
																	<option value="<?php echo $gak['bsd_account_id']; ?>" <?php echo ( $gak['bsd_account_id'] == $cav ) ? "selected='selected'" : ''; ?>><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
																	<?php
																}
															}
															?>

														</select>


														<div class="edit-form-btns select_account_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_account_id_wrapper"><?php echo $cav; ?></div>
												</td>

												<td class="prod_selected_tt_td_wrapper">

													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_type" id="bsd_spscwt_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="percentage" <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="amount" <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
														</select>
														<div class="edit-form-btns prod_selected_tt_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selecte_tt_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_product_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>

												<td class="prod_tv_td_wrapper">

													<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>" placeholder="e.g. 10" class="bsd_spscwtp_input <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_spscwt_amount_<?php echo $variation_id . '_' . $cak; ?>' name='_bsd_spscwt_product_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $product_amount[ $cak ]; ?>" placeholder="e.g. 20" class="bsd_spscwt_amount <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_tv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_transfer_value_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $product_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>

												<td class="prod_selected_st_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<select name="bsd_spscwt_shipping_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="percentage" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'percentage' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Percentage' ); ?></option>
															<option value="amount" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Fixed Amount' ); ?></option>
														</select>

														<div class="edit-form-btns prod_selected_st_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_type_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_shipping_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>

												<td class="prod_stv_td_wrapper">


													<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $_bsd_prod_shipping_percentage[ $cak ] ) ) ? $_bsd_prod_shipping_percentage[ $cak ] : ''; ?>" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $shipping_amount[ $cak ] ) ) ? $shipping_amount[ $cak ] : ''; ?>" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount <?php echo ( ( empty( $connected_shipping_type[ $cak ] ) || 'percentage' == $connected_shipping_type[ $bsppcak ] ) ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_stv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_digit_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_bsd_prod_shipping_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $shipping_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>
												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $variation['sku'], 'bsd-split-pay-stripe-connect-woo' ); ?></td>
											</tr>
											<?php
										}
										?>

										<?php
										if ( $first_td_counter > 1 && $variation_td_counter == 1 && $connected_account_td_counter > 1 ) {
										?>
											<tr condition="<?php echo 'first_td_counter > 1 && variation_td_counter == 1 && connected_account_td_counter > 1'; ?>" variation_id="<?php echo $variation_id; ?>" first_td_counter="<?php echo $first_td_counter; ?>" variation_td_counter="<?php echo $variation_td_counter; ?>" connected_account_td_counter="<?php echo $connected_account_td_counter; ?>" class="variable-product-tr <?php echo ( $odd_even % 2 == 0 ) ? 'even' : 'odd'; ?> <?php echo ( $connected_account_counter == $last_row_counter ) ? 'border-bottom-1' : ''; ?>">
												<td class="select_account_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="" title="" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_connected_account[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" id="sspscwsca_select_<?php echo $cak; ?>" class="sspscwsca_select">
															<?php

															if ( ! empty( $get_accounts ) ) {
															?>
																<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
																<?php
																foreach ( $get_accounts as $gak ) {
																?>
																	<option value="<?php echo $gak['bsd_account_id']; ?>" <?php echo ( $gak['bsd_account_id'] == $cav ) ? "selected='selected'" : ''; ?>><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
																	<?php
																}
															}
															?>

														</select>


														<div class="edit-form-btns select_account_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_account_id_wrapper"><?php echo $cav; ?></div>
												</td>
												<td class="prod_selected_tt_td_wrapper">

													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_type" id="bsd_spscwt_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="percentage" <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="amount" <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
														</select>
														<div class="edit-form-btns prod_selected_tt_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selecte_tt_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_product_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>

												<td class="prod_tv_td_wrapper">

													<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>" placeholder="e.g. 10" class="bsd_spscwtp_input <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_spscwt_amount_<?php echo $variation_id . '_' . $cak; ?>' name='_bsd_spscwt_product_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $product_amount[ $cak ]; ?>" placeholder="e.g. 20" class="bsd_spscwt_amount <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_tv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_transfer_value_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $product_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>

												<td class="prod_selected_st_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<select name="bsd_spscwt_shipping_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>  
															<option value="percentage" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'percentage' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Percentage' ); ?></option>
															<option value="amount" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Fixed Amount' ); ?></option>
														</select>

														<div class="edit-form-btns prod_selected_st_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_type_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_shipping_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>

												<td class="prod_stv_td_wrapper">


													<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $_bsd_prod_shipping_percentage[ $cak ] ) ) ? $_bsd_prod_shipping_percentage[ $cak ] : ''; ?>" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $shipping_amount[ $cak ] ) ) ? $shipping_amount[ $cak ] : ''; ?>" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount <?php echo ( ( empty( $connected_shipping_type[ $cak ] ) || 'percentage' == $connected_shipping_type[ $cak ] ) ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_stv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_digit_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_bsd_prod_shipping_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $shipping_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>

												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $variation['sku'], 'bsd-split-pay-stripe-connect-woo' ); ?></td>
											</tr>
											<?php
										}
										?>


										<?php
										if ( $first_td_counter > 1 && $variation_td_counter > 1 && $connected_account_td_counter == 1 ) {
										?>
											<tr condition="<?php echo 'first_td_counter > 1 && variation_td_counter > 1 && connected_account_td_counter == 1'; ?>" first_td_counter="<?php echo $first_td_counter; ?>" variation_td_counter="<?php echo $variation_td_counter; ?>" connected_account_td_counter="<?php echo $connected_account_td_counter; ?>" class="variable-product-tr <?php echo ( $odd_even % 2 == 0 ) ? 'even' : 'odd'; ?> <?php echo ( $connected_account_counter == $last_row_counter ) ? 'border-bottom-1' : ''; ?>">
												<td class="text-center variable-td-border-bottom <?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>" rowspan="<?php echo $connected_account_counter; ?>"><?php echo ucfirst( implode( '<br>', $variation['attributes'] ) ); ?></td>
												<td class="select_account_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="" title="" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_connected_account[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" id="sspscwsca_select_<?php echo $cak; ?>" class="sspscwsca_select">
															<?php

															if ( ! empty( $get_accounts ) ) {
															?>
																<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
																<?php
																foreach ( $get_accounts as $gak ) {
																?>
																	<option value="<?php echo $gak['bsd_account_id']; ?>" <?php echo ( $gak['bsd_account_id'] == $cav ) ? "selected='selected'" : ''; ?>><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
																	<?php
																}
															}
															?>

														</select>


														<div class="edit-form-btns select_account_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_account_id_wrapper"><?php echo $cav; ?></div>
												</td>
												<td class="prod_selected_tt_td_wrapper">

													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_type" id="bsd_spscwt_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="percentage" <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="amount" <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
														</select>
														<div class="edit-form-btns prod_selected_tt_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selecte_tt_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_product_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>
												<td class="prod_tv_td_wrapper">

													<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>" placeholder="e.g. 10" class="bsd_spscwtp_input <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_spscwt_amount_<?php echo $variation_id . '_' . $cak; ?>' name='_bsd_spscwt_product_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $product_amount[ $cak ]; ?>" placeholder="e.g. 20" class="bsd_spscwt_amount <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_tv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_transfer_value_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $product_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>

												<td class="prod_selected_st_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<select name="bsd_spscwt_shipping_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="percentage" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'percentage' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Percentage' ); ?></option>
															<option value="amount" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Fixed Amount' ); ?></option>
														</select>

														<div class="edit-form-btns prod_selected_st_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_type_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_shipping_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>

												<td class="prod_stv_td_wrapper">


													<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $_bsd_prod_shipping_percentage[ $cak ] ) ) ? $_bsd_prod_shipping_percentage[ $cak ] : ''; ?>" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $shipping_amount[ $cak ] ) ) ? $shipping_amount[ $cak ] : ''; ?>" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount <?php echo ( ( empty( $connected_shipping_type[ $cak ] ) || 'percentage' == $connected_shipping_type[ $cak ] ) ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_stv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_digit_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_bsd_prod_shipping_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $shipping_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>
												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $variation['sku'], 'bsd-split-pay-stripe-connect-woo' ); ?></td>
											</tr>
											<?php
										}
										?>

										<?php
										if ( $first_td_counter > 1 && $variation_td_counter > 1 && $connected_account_td_counter > 1 ) {
										?>
											<tr condition="<?php echo 'first_td_counter > 1 && variation_td_counter > 1 && connected_account_td_counter > 1'; ?>" first_td_counter="<?php echo $first_td_counter; ?>" variation_td_counter="<?php echo $variation_td_counter; ?>" connected_account_td_counter="<?php echo $connected_account_td_counter; ?>" class="variable-product-tr <?php echo ( $odd_even % 2 == 0 ) ? 'even' : 'odd'; ?> <?php echo ( $connected_account_counter == $last_row_counter ) ? 'border-bottom-1' : ''; ?>">
												<td class="select_account_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="" title="" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_connected_account[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" id="sspscwsca_select_<?php echo $cak; ?>" class="sspscwsca_select">
															<?php

															if ( ! empty( $get_accounts ) ) {
															?>
																<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
																<?php
																foreach ( $get_accounts as $gak ) {
																?>
																	<option value="<?php echo $gak['bsd_account_id']; ?>" <?php echo ( $gak['bsd_account_id'] == $cav ) ? "selected='selected'" : ''; ?>><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
																	<?php
																}
															}
															?>

														</select>


														<div class="edit-form-btns select_account_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_account_id_wrapper"><?php echo $cav; ?></div>
												</td>
												<td class="prod_selected_tt_td_wrapper">

													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<select name="_bsd_spscwt_product_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_type" id="bsd_spscwt_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="percentage" <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="amount" <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
														</select>
														<div class="edit-form-btns prod_selected_tt_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selecte_tt_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_product_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>
												<td class="prod_tv_td_wrapper">

													<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>" placeholder="e.g. 10" class="bsd_spscwtp_input <?php echo ( 'amount' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_spscwt_amount_<?php echo $variation_id . '_' . $cak; ?>' name='_bsd_spscwt_product_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo $product_amount[ $cak ]; ?>" placeholder="e.g. 20" class="bsd_spscwt_amount <?php echo ( 'percentage' == $connected_product_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_tv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>
													<div class="selected_transfer_value_wrapper">
														<?php
														if ( $connected_product_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_stripe_connect_split_pay_transfer_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $product_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>

												<td class="prod_selected_st_td_wrapper">
													<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<select name="bsd_spscwt_shipping_type[<?php echo $variation_id; ?>][<?php echo $cak; ?>]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_<?php echo $variation_id . '_' . $cak; ?>">
															<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
															<option value="percentage" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'percentage' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Percentage' ); ?></option>
															<option value="amount" <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? "selected='selected'" : ''; ?>><?php echo esc_html( 'Fixed Amount' ); ?></option>
														</select>

														<div class="edit-form-btns prod_selected_st_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_type_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
															esc_attr_e( 'Percentage', 'bsd-split-pay-stripe-connect-woo' );
														} elseif ( $connected_shipping_type[ $cak ] == 'amount' ) {
															esc_attr_e( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' );
														}
														?>
													</div>
												</td>

												<td class="prod_stv_td_wrapper">


													<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_' . $cak; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
													<div class="edit-form">
														<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_percentage[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $_bsd_prod_shipping_percentage[ $cak ] ) ) ? $_bsd_prod_shipping_percentage[ $cak ] : ''; ?>" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input <?php echo ( isset( $connected_shipping_type[ $cak ] ) && 'amount' == $connected_shipping_type[ $cak ] ) ? 'bsd_hidden' : ''; ?>" />
														<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_<?php echo $variation_id . '_' . $cak; ?>' name='bsd_prod_shipping_amount[<?php echo $variation_id; ?>][<?php echo $cak; ?>]' value="<?php echo ( isset( $shipping_amount[ $cak ] ) ) ? $shipping_amount[ $cak ] : ''; ?>" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount <?php echo ( ( empty( $connected_shipping_type[ $cak ] ) || 'percentage' == $connected_shipping_type[ $cak ] ) ) ? 'bsd_hidden' : ''; ?>" />
														<div class="edit-form-btns prod_stv_btns">
															<button class="edit-form-ok-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
															<button class="edit-form-cancle-btn" type="button">
																<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
															</button>
														</div>
													</div>

													<div class="selected_shipping_digit_wrapper">
														<?php
														if ( $connected_shipping_type[ $cak ] == 'percentage' ) {
														?>
															<?php echo $_bsd_prod_shipping_percentage[ $cak ]; ?>
															<?php
														} else {
														?>
															<?php echo $shipping_amount[ $cak ]; ?>
															<?php
														}
														?>
													</div>
												</td>
												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
												<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $variation['sku'], 'bsd-split-pay-stripe-connect-woo' ); ?></td>
											</tr>
											<?php
										}
										?>


										<?php
										++$first_td_counter;
										++$connected_account_td_counter;
										++$last_row_counter;
									}
								}
								if ( empty( $connected_account ) ) {
									?>
									<input type="hidden" name="product_type[<?php echo $variation_id; ?>]" value="<?php echo $product_type; ?>" />

									<tr condition="No Account found" variation_id="<?php echo $variation_id; ?>" class="variable-product-tr <?php echo ( $odd_even % 2 == 0 ) ? 'even' : 'odd'; ?> border-bottom-1">
										<td rowspan="1" class="variable-td-border-bottom <?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?> td-right-border">
											<a href="<?php echo get_edit_post_link( $post_id ); ?>" target="_blank"><?php echo esc_html( get_the_title(), 'bsd-split-pay-stripe-connect-woo' ); ?></a>
										</td>
										<td class="variable-td-border-bottom text-center <?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>" rowspan="1"><?php echo ucfirst( implode( '<br>', $variation['attributes'] ) ); ?></td>

										<td class="select_account_td_wrapper">
											<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
											<div class="edit-form">
												<select name="_bsd_spscwt_product_connected_account[<?php echo $variation_id; ?>][0]" id="sspscwsca_select_<?php echo $variation_id . '_0'; ?>" class="sspscwsca_select">
													<?php

													if ( ! empty( $get_accounts ) ) {
													?>
														<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
														<?php
														foreach ( $get_accounts as $gak ) {
														?>
															<option value="<?php echo $gak['bsd_account_id']; ?>"><?php echo empty( $gak['bsd_account_name'] ) ? $gak['bsd_account_id'] : $gak['bsd_account_name']; ?></option>
															<?php
														}
													}
													?>

												</select>


												<div class="edit-form-btns select_account_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>
											<div class="selected_account_id_wrapper"></div>
										</td>

										<td class="prod_selected_tt_td_wrapper">

											<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
											<div class="edit-form">
												<select name="_bsd_spscwt_product_type[<?php echo $variation_id; ?>][0]" class="bsd_spscwt_type" id="bsd_spscwt_type_<?php echo $variation_id . '_0'; ?>">
													<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
													<option value="percentage"><?php echo esc_html__( 'Percentage', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
													<option value="amount"><?php echo esc_html__( 'Fixed Amount', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
												</select>
												<div class="edit-form-btns prod_selected_tt_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>

											<div class="selecte_tt_wrapper"></div>
										</td>

										<td class="prod_tv_td_wrapper">

											<a href="javascript:void(0);" class="edit-item selected_tt_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_0'; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="Edit" title="Edit" /></a>
											<div class="edit-form">
												<input type='number' min='0' max="100" step=".01" id='bsd_split_pay_stripe_connect_woo_transfer_percentage_<?php echo $variation_id . '_0'; ?>' name='_stripe_connect_split_pay_transfer_percentage[<?php echo $variation_id; ?>][0]' value="" placeholder="e.g. 10" class="bsd_spscwtp_input" />
												<input type='number' min='0' step=".01" id='bsd_spscwt_amount_<?php echo $variation_id . '_0'; ?>' name='_bsd_spscwt_product_amount[<?php echo $variation_id; ?>][0]' value="" placeholder="e.g. 20" class="bsd_spscwt_amount bsd_hidden" />
												<div class="edit-form-btns prod_tv_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>
											<div class="selected_transfer_value_wrapper"></div>
										</td>

										<td class="prod_selected_st_td_wrapper">
											<a href="javascript:void(0);" class="edit-item"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<select name="bsd_spscwt_shipping_type[<?php echo $variation_id; ?>][0]" class="bsd_spscwt_shipping_type" id="bsd_spscwt_shipping_type_<?php echo $variation_id . '_0'; ?>">
													<option value=""><?php esc_attr_e( 'Select', 'bsd-split-pay-stripe-connect-woo' ); ?></option>
													<option value="percentage"><?php echo esc_html( 'Percentage' ); ?></option>
													<option value="amount"><?php echo esc_html( 'Fixed Amount' ); ?></option>
												</select>

												<div class="edit-form-btns prod_selected_st_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>

											<div class="selected_shipping_type_wrapper"></div>
										</td>

										<td class="prod_stv_td_wrapper">


											<a href="javascript:void(0);" class="edit-item selected_stv_value_wrapper" data-connected_account_index="<?php echo $variation_id . '_0'; ?>"><img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/edit.svg" alt="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" title="<?php esc_attr_e( 'Edit', 'bsd-split-pay-stripe-connect-woo' ); ?>" /></a>
											<div class="edit-form">
												<input type='number' min='0' max="100" step=".01" id='bsd_prod_shipping_percentage_<?php echo $variation_id . '_0'; ?>' name='bsd_prod_shipping_percentage[<?php echo $variation_id; ?>][0]' value="" placeholder="e.g. 10" class="bsd_spscwtp_shipping_input" />
												<input type='number' min='0' step=".01" id='bsd_prod_shipping_amount_<?php echo $variation_id . '_0'; ?>' name='bsd_prod_shipping_amount[<?php echo $variation_id; ?>][0]' value="" placeholder="e.g. 20" class="bsd_spscwt_shipping_amount bsd_hidden" />
												<div class="edit-form-btns prod_stv_btns">
													<button class="edit-form-ok-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/tick-s.svg" alt="tick" title="<?php esc_attr_e( 'Submit', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
													<button class="edit-form-cancle-btn" type="button">
														<img src="<?php echo BSD_SCSP_PLUGIN_URI; ?>assets/cross-s.svg" alt="cross" title="<?php esc_attr_e( 'Cancel', 'bsd-split-pay-stripe-connect-woo' ); ?>" />
													</button>
												</div>
											</div>

											<div class="selected_shipping_digit_wrapper"></div>
										</td>
										<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo implode( ', ', $cat_name ); ?></td>
										<td class="<?php echo ( $odd_even % 2 == 1 ) ? 'cream-dark' : 'pink-dark'; ?>"><?php echo esc_html( $variation['sku'], 'bsd-split-pay-stripe-connect-woo' ); ?></td>
									</tr>

									<?php
								}
								++$variation_td_counter;
							}

							?>

						<?php } ?>


					<?php } ?>
					<?php
					++$odd_even;
				endwhile;
				wp_reset_query();
				wp_reset_postdata();
				?>
			</table>

		</div>
		<div class="our-pro-bar">
			<button class="n-btn save-bulk-edit" type="submit"><?php echo esc_html( 'Save Changes', 'bsd-split-pay-stripe-connect-woo' ); ?></button>
			<?php
			if ( ! empty( $pages ) && is_array( $pages ) ) {
				$paged = ( get_query_var( 'paged' ) == 0 ) ? 1 : get_query_var( 'paged' );
				echo '<ul class="pagination">';
				foreach ( $pages as $page ) {
					echo "<li>$page</li>";
				}
				echo '</ul>';
			}
			?>
			<div class="result-coutn"><?php echo esc_html( $total_records . ' records found', 'bsd-split-pay-stripe-connect-woo' ); ?></div>
		</div>
	</form>
	<?php
} else { ?>
	<p>No Product Found!</p>
<?php }
$html .= ob_get_contents();
ob_end_clean();
wp_send_json_success( $html );
die();
