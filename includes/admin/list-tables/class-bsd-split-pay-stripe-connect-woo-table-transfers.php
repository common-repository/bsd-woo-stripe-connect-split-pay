<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'BSD_SCSP_WP_List_Table' ) ) {
	include_once 'class-bsd-split-pay-stripe-connect-woo-wp-list-table.php';
}

if ( ! class_exists( 'BSD_SCSP_List_Table_Transfers' ) ) :

	class BSD_SCSP_List_Table_Transfers extends BSD_SCSP_WP_List_Table {

		public $cookie_name = 'transfer_log_per_page';

		public function __construct() {

			parent::__construct( array(
				'singular' => __( 'Transfer', 'bsd-split-pay-stripe-connect-woo' ),
				'plural'   => __( 'Transfers', 'bsd-split-pay-stripe-connect-woo' ),
				'ajax'     => false,
			) );

			add_action( 'admin_head', array( $this, 'admin_header' ) );
		}

		/**
		 *  Associative array of columns
		 *
		 * @version   1.0.0
		 */
		public function get_columns() {
			$columns = array(
				'wc_order_id'            => __( 'Order ID', 'bsd-split-pay-stripe-connect-woo' ),
				'charge_date'            => __( 'Date', 'bsd-split-pay-stripe-connect-woo' ),
				'transfer_type'          => __( 'Item', 'bsd-split-pay-stripe-connect-woo' ),
				'level'                  => __( 'Level', 'bsd-split-pay-stripe-connect-woo' ),
				'transfer_entered_value' => __( 'Value', 'bsd-split-pay-stripe-connect-woo' ),
				'entered_variable'       => __( 'Variable', 'bsd-split-pay-stripe-connect-woo' ),
				// 'charge_amount'          => __( 'Item Total', 'bsd-split-pay-stripe-connect-woo' ),
				'item_total'        => __( 'Item Total', 'bsd-split-pay-stripe-connect-woo' ),
				'item_transfer_amount'        => __( 'Item Transfer Amount', 'bsd-split-pay-stripe-connect-woo' ),
				'item_tax_total'        => __( 'Item Tax', 'bsd-split-pay-stripe-connect-woo' ),
				'tax_transfer_type'        => __( 'Tax Transfer Type', 'bsd-split-pay-stripe-connect-woo' ),
				'transfer_tax_value'        => __( 'Tax Transfer Amount', 'bsd-split-pay-stripe-connect-woo' ),
				'transfer_amount'        => __( 'Total Transfer Amount', 'bsd-split-pay-stripe-connect-woo' ),
				'transfer_destination'   => __( 'Connected Account', 'bsd-split-pay-stripe-connect-woo' ),
				'transfer_id'            => __( 'Stripe Transfer ID', 'bsd-split-pay-stripe-connect-woo' ),
				'charge_id'              => __( 'Stripe Charge ID', 'bsd-split-pay-stripe-connect-woo' ),
			);

			return $columns;
		}

		/**
		 * Style the columns
		 * TO DO: Not currently working
		 *
		 * @version   0.0.1
		 */
		function admin_header() {
			$page = ( isset( $_GET['page'] ) ) ? sanitize_text_field( $_GET['page'] ) : false;
			// error_log("The page is {$page}.");
	if ( 'my_list_test' != $page ) {
				return;
	}

			echo '<style type="text/css">';
			echo '.wp-list-table .column-id';
			echo '.wp-list-table .column-wc_order_id';
			echo '.wp-list-table .column-charge_date';
			echo '.wp-list-table .column-charge_amount';
			echo '.wp-list-table .column-transfer_amount';
			echo '.wp-list-table .column-transfer_destination';
			echo '.wp-list-table .column-charge_id';
			echo '.wp-list-table .column-transfer_id';

			echo '</style>';
		}

		/**
		 * Render a column when no column specific method exist.
		 *
		 * @version   1.0.0
		 */
		public function column_default( $item, $column_name ) {
	switch ( $column_name ) {
				case 'id':
						case 'wc_order_id':
						case 'charge_amount':
						case 'transfer_amount':
						case 'transfer_tax_value':
						case 'charge_id':
						case 'charge_date':
						case 'transfer_id':
						case 'transfer_destination':
						case 'transfer_type':
						case 'transfer_entered_value':
						case 'entered_variable':
						case 'level':
						case 'item_transfer_amount':
						case 'item_tax_total':
						case 'tax_transfer_type':
					return $item[ $column_name ];
						case 'item_total':
							return ( empty( $item[ $column_name ] ) ) ? 0 : $item[ $column_name ];

				default:
					return print_r( $item, true );
	}
		}

		/**
		 * Columns to make sortable.
		 *
		 * @version   1.0.0
		 */
		public function get_sortable_columns() {
			$sortable_columns = array(
				'id'                   => array( 'id', true ),
				'wc_order_id'          => array( 'wc_order_id', true ),
				'charge_date'          => array( 'charge_date', true ),
				'charge_amount'        => array( 'charge_amount', true ),
				'transfer_amount'      => array( 'transfer_amount', true ),
				'transfer_destination' => array( 'transfer_destination', true ),
				'charge_id'            => array( 'charge_id', true ),
				'transfer_id'          => array( 'transfer_id', true ),
			);

			return $sortable_columns;
		}

		/**
		 * Check if Stripe is in test mode or live mode
		 *
		 * @version   1.0.0
		 */
		public function get_stripe_mode() {
			$stripe_mode = 'live';

			$woo_stripe_settings = get_option( 'woocommerce_stripe_settings' );

			$stripe_mode = isset( $woo_stripe_settings['testmode'] ) && $woo_stripe_settings['testmode'] === 'yes' ? 'test' : 'live';

			return $stripe_mode;
		}

		/**
		 * Get count of items
		 *
		 * @version   1.0.0
		 */
		private function get_total_items( $stripe_mode ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'bsd_scsp_transfer_log';
			$search     = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : false;
			$sql        = "SELECT COUNT(id) FROM $table_name WHERE stripe_mode = %s";
	if ( ! empty( $search ) ) {
				$sql .= " and transfer_destination like '%" . $search . "%'";
	}
			$result = $wpdb->get_var( $wpdb->prepare( $sql, $stripe_mode ) );

			return $result;
		}

		/**
		 * Retrieve transfer data from the database
		 *
		 * @version   1.0.0
		 */
		public function get_transfers( $stripe_mode, $per_page, $paged ) {

			global $wpdb;

	if ( ! empty( $this->current_action() ) ) {
				setcookie( $this->cookie_name, $this->current_action(), time() + 86400 );
				$per_page = $this->current_action();
	} elseif ( isset( $_COOKIE[ $this->cookie_name ] ) && ! empty( $this->cookie_name ) ) {
					$per_page = $_COOKIE[ $this->cookie_name ];
}

			$table_name = $wpdb->prefix . BSD_TRANSFER_LOG_TABLE;

			$offset = $paged * $per_page;

			$orderby = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? sanitize_text_field( $_REQUEST['orderby'] ) : 'wc_order_id';
			$order   = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array(
				'asc',
				'desc',
			) ) ) ? sanitize_text_field( $_REQUEST['order'] ) : 'desc';

			$search = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : false;

			$sql = "SELECT
                  id
                  , wc_order_id
                  , charge_amount
                  , item_total
                  , transfer_amount
                  , transfer_tax_value
                  , item_tax_total
                  , tax_transfer_type
                  , charge_id
                  , charge_date
                  , transfer_id
                  , transfer_destination
                  , transfer_type
                  , transfer_entered_value
                  , transfer_type as entered_variable
                  , transfer_type as level
                  , (transfer_amount - transfer_tax_value) as item_transfer_amount
              FROM $table_name
              WHERE stripe_mode = %s";
	if ( ! empty( $search ) ) {
				$sql .= " and transfer_destination like '%" . $search . "%'";
	}

			$sql .= ' ORDER BY ' . sanitize_sql_orderby( $orderby . ' ' . $order ) . '
              LIMIT %d OFFSET %d';

			// Query output_type will be an associative array with ARRAY_A.
			$query_results = $wpdb->get_results( $wpdb->prepare( $sql, $stripe_mode, $per_page, $offset ), ARRAY_A );

			return $query_results;
		}

		public function get_bulk_actions() {

			return array(
				'10'  => 10,
				'25'  => 25,
				'50'  => 50,
				'100' => 100,
				'250' => 250,
			);
		}

		protected function bulk_actions( $which = '' ) {

			if ( $which == 'top' ) {
				return;
			}
			if ( isset( $_COOKIE[ $this->cookie_name ] ) && ! empty( $this->cookie_name ) ) {
				$current_action = $_COOKIE[ $this->cookie_name ];
			}
			if ( ! empty( $this->current_action() ) ) {
				$current_action = $this->current_action();
			}
			if ( is_null( $this->_actions ) ) {
				$this->_actions = $this->get_bulk_actions();

				/**
				 * Filters the items in the bulk actions menu of the list table.
				 *
				 * The dynamic portion of the hook name, `$this->screen->id`, refers
				 * to the ID of the current screen.
				 *
				 * @since 3.1.0
				 * @since 5.6.0 A bulk action can now contain an array of options in order to create an optgroup.
				 *
				 * @param array $actions An array of the available bulk actions.
				 */
				$this->_actions = apply_filters( "bulk_actions-{$this->screen->id}", $this->_actions ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

				$two = '';
			} else {
				$two = '2';
			}

			if ( empty( $this->_actions ) ) {
				return;
			}

			echo '<label for="bulk-action-selector-' . esc_attr( $which ) . '" class="screen-reader-text">' .
				/* translators: Hidden accessibility text. */
				__( 'Select bulk action', 'bsd-split-pay-stripe-connect-woo' ) .
				'</label>';
			echo '<select name="action' . $two . '" id="bulk-action-selector-' . esc_attr( $which ) . "\">\n";

			foreach ( $this->_actions as $key => $value ) {
				if ( is_array( $value ) ) {
					echo "\t" . '<optgroup label="' . esc_attr( $key ) . '">' . "\n";

					foreach ( $value as $name => $title ) {
						$class    = ( 'edit' === $name ) ? ' class="hide-if-no-js"' : '';
						$selected = ( $name == $current_action ) ? 'selected="selected"' : '';
						echo "\t\t" . '<option value="' . esc_attr( $name ) . '"' . $class . ' ' . $selected . '>' . $title . "</option>\n";
					}
					echo "\t" . "</optgroup>\n";
				} else {
					$class    = ( 'edit' === $key ) ? ' class="hide-if-no-js"' : '';
					$selected = ( $key == $current_action ) ? 'selected="selected"' : '';
					echo "\t" . '<option value="' . esc_attr( $key ) . '"' . $class . ' ' . $selected . '>' . $value . "</option>\n";
				}
			}

			echo "</select>\n";

			submit_button( __( 'Apply', 'bsd-split-pay-stripe-connect-woo' ), 'action', '', false, array( 'id' => "doaction$two" ) );
			echo "\n";
		}

		/**
		 * Text displayed when no customer data is available
		 *
		 * @version   1.0.0
		 */
		public function no_items() {
			_e( 'No transfer records available.', 'bsd-split-pay-stripe-connect-woo' );
		}

		/**
		 * Prints column headers, accounting for hidden and sortable columns.
		 */
		public function print_column_headers( $with_id = true ) {
			[ $columns, $hidden, $sortable, $primary ] = $this->get_column_info();

			$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
			$current_url = remove_query_arg( 'paged', $current_url );

			// When users click on a column header to sort by other columns.
			if ( isset( $_GET['orderby'] ) ) {
				$current_orderby = $_GET['orderby'];
				// In the initial view there's no orderby parameter.
			} else {
				$current_orderby = '';
			}

			// Not in the initial view and descending order.
			if ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) {
				$current_order = 'desc';
			} else {
				// The initial view is not always 'asc', we'll take care of this below.
				$current_order = 'asc';
			}

			if ( ! empty( $columns['cb'] ) ) {
				static $cb_counter = 1;
				$columns['cb']     = '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />
			<label for="cb-select-all-' . $cb_counter . '">' .
								'<span class="screen-reader-text">' .
								/* translators: Hidden accessibility text. */
								__( 'Select All', 'bsd-split-pay-stripe-connect-woo' ) .
								'</span>' .
								'</label>';
				++$cb_counter;
			}

			foreach ( $columns as $column_key => $column_display_name ) {
				$class          = array( 'manage-column', "column-$column_key" );
				$aria_sort_attr = '';
				$abbr_attr      = '';
				$order_text     = '';

				if ( in_array( $column_key, $hidden, true ) ) {
					$class[] = 'hidden';
				}

				if ( 'cb' === $column_key ) {
					$class[] = 'check-column';
				} elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ), true ) ) {
					$class[] = 'num';
				}

				if ( $column_key === $primary ) {
					$class[] = 'column-primary';
				}
				$class[] = 'center-column';
				if ( isset( $sortable[ $column_key ] ) ) {
					$orderby       = isset( $sortable[ $column_key ][0] ) ? $sortable[ $column_key ][0] : '';
					$desc_first    = isset( $sortable[ $column_key ][1] ) ? $sortable[ $column_key ][1] : false;
					$abbr          = isset( $sortable[ $column_key ][2] ) ? $sortable[ $column_key ][2] : '';
					$orderby_text  = isset( $sortable[ $column_key ][3] ) ? $sortable[ $column_key ][3] : '';
					$initial_order = isset( $sortable[ $column_key ][4] ) ? $sortable[ $column_key ][4] : '';

					/*
					* We're in the initial view and there's no $_GET['orderby'] then check if the
					* initial sorting information is set in the sortable columns and use that.
					*/
					if ( '' === $current_orderby && $initial_order ) {
						// Use the initially sorted column $orderby as current orderby.
						$current_orderby = $orderby;
						// Use the initially sorted column asc/desc order as initial order.
						$current_order = $initial_order;
					}

					/*
					* True in the initial view when an initial orderby is set via get_sortable_columns()
					* and true in the sorted views when the actual $_GET['orderby'] is equal to $orderby.
					*/
					if ( $current_orderby === $orderby ) {
						// The sorted column. The `aria-sort` attribute must be set only on the sorted column.
						if ( 'asc' === $current_order ) {
							$order          = 'desc';
							$aria_sort_attr = ' aria-sort="ascending"';
						} else {
							$order          = 'asc';
							$aria_sort_attr = ' aria-sort="descending"';
						}

						$class[] = 'sorted';
						$class[] = $current_order;
					} else {
						// The other sortable columns.
						$order = strtolower( $desc_first );

						if ( ! in_array( $order, array( 'desc', 'asc' ), true ) ) {
							$order = $desc_first ? 'desc' : 'asc';
						}

						$class[] = 'sortable';
						$class[] = 'desc' === $order ? 'asc' : 'desc';

						/* translators: Hidden accessibility text. */
						$asc_text = __( 'Sort ascending.', 'bsd-split-pay-stripe-connect-woo' );
						/* translators: Hidden accessibility text. */
						$desc_text  = __( 'Sort descending.', 'bsd-split-pay-stripe-connect-woo' );
						$order_text = 'asc' === $order ? $asc_text : $desc_text;
					}

					if ( '' !== $order_text ) {
						$order_text = ' <span class="screen-reader-text">' . $order_text . '</span>';
					}

					// Print an 'abbr' attribute if a value is provided via get_sortable_columns().
					$abbr_attr = $abbr ? ' abbr="' . esc_attr( $abbr ) . '"' : '';

					$column_display_name = sprintf(
						'<a href="%1$s">' .
						'<span>%2$s</span>' .
						'<span class="sorting-indicators">' .
						'<span class="sorting-indicator asc" aria-hidden="true"></span>' .
						'<span class="sorting-indicator desc" aria-hidden="true"></span>' .
						'</span>' .
						'%3$s' .
						'</a>',
						esc_url( add_query_arg( compact( 'orderby', 'order' ), $current_url ) ),
						$column_display_name,
						$order_text
					);
				}

				$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
				$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
				$id    = $with_id ? "id='$column_key'" : '';

				if ( ! empty( $class ) ) {
					$class = "class='" . implode( ' ', $class ) . "'";
				}

				echo "<$tag $scope $id $class $aria_sort_attr $abbr_attr>$column_display_name</$tag>";
			}
		}


		/**
		 * Handles data query and filter, sorting, and pagination.
		 *
		 * @version   1.0.0
		 */
		public function prepare_items() {
			$stripe_mode = $this->get_stripe_mode();

			$per_page = 10;
			if ( isset( $_COOKIE[ $this->cookie_name ] ) && ! empty( $this->cookie_name ) ) {
				$per_page = $_COOKIE[ $this->cookie_name ];
			}
			if ( ! empty( $this->current_action() ) ) {
				$per_page = $this->current_action();
			}
			$columns  = $this->get_columns();
			$hidden   = array();
			$sortable = $this->get_sortable_columns();

			$this->_column_headers = array( $columns, $hidden, $sortable );

			$total_items = $this->get_total_items( $stripe_mode );

			$paged = isset( $_REQUEST['paged'] ) ? max( 0, intval( $_REQUEST['paged'] ) - 1 ) : 0;

			$this->items = $this->get_transfers( $stripe_mode, $per_page, $paged );

			$this->set_pagination_args( array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			) );
		}

		protected function pagination( $which ) {
			/*
			if ($which == "top") {
				return;
			} */
			if ( empty( $this->_pagination_args ) ) {
				return;
			}

			$total_items     = $this->_pagination_args['total_items'];
			$total_pages     = $this->_pagination_args['total_pages'];
			$infinite_scroll = false;
			if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
				$infinite_scroll = $this->_pagination_args['infinite_scroll'];
			}

			if ( 'top' === $which && $total_pages > 1 ) {
				$this->screen->render_screen_reader_content( 'heading_pagination' );
			}

			$output = '<span class="displaying-num">' . sprintf(
				/* translators: %s: Number of items. */
				_n( '%s item', '%s items', $total_items, 'bsd-split-pay-stripe-connect-woo' ),
				number_format_i18n( $total_items )
			) . '</span>';

			$current              = $this->get_pagenum();
			$removable_query_args = wp_removable_query_args();

			$current_url = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

			$current_url = remove_query_arg( $removable_query_args, $current_url );

			$page_links = array();

			$total_pages_before = '<span class="paging-input">';
			$total_pages_after  = '</span></span>';

			$disable_first = false;
			$disable_last  = false;
			$disable_prev  = false;
			$disable_next  = false;

			if ( 1 == $current ) {
				$disable_first = true;
				$disable_prev  = true;
			}
			if ( $total_pages == $current ) {
				$disable_last = true;
				$disable_next = true;
			}

			if ( $disable_first ) {
				$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
			} else {
				$page_links[] = sprintf(
					"<a class='first-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
					esc_url( remove_query_arg( 'paged', $current_url ) ),
					__( 'First page', 'bsd-split-pay-stripe-connect-woo' ),
					'&laquo;'
				);
			}

			if ( $disable_prev ) {
				$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
			} else {
				$page_links[] = sprintf(
					"<a class='prev-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
					esc_url( add_query_arg( 'paged', max( 1, $current - 1 ), $current_url ) ),
					__( 'Previous page', 'bsd-split-pay-stripe-connect-woo' ),
					'&lsaquo;'
				);
			}

			if ( 'bottom' === $which ) {
				$html_current_page  = $current;
				$total_pages_before = '<span class="screen-reader-text">' . __( 'Current Page', 'bsd-split-pay-stripe-connect-woo' ) . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
			} else {
				$html_current_page = sprintf(
					"%s<input class='current-page' id='current-page-selector' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
					'<label for="current-page-selector" class="screen-reader-text">' . __( 'Current Page', 'bsd-split-pay-stripe-connect-woo' ) . '</label>',
					$current,
					strlen( $total_pages )
				);
			}
			$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
			$page_links[]     = $total_pages_before . sprintf(
				/* translators: 1: Current page, 2: Total pages. */
				_x( '%1$s of %2$s', 'paging', 'bsd-split-pay-stripe-connect-woo' ),
				$html_current_page,
				$html_total_pages
			) . $total_pages_after;

			if ( $disable_next ) {
				$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
			} else {
				$page_links[] = sprintf(
					"<a class='next-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
					esc_url( add_query_arg( 'paged', min( $total_pages, $current + 1 ), $current_url ) ),
					__( 'Next page', 'bsd-split-pay-stripe-connect-woo' ),
					'&rsaquo;'
				);
			}

			if ( $disable_last ) {
				$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
			} else {
				$page_links[] = sprintf(
					"<a class='last-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
					esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
					__( 'Last page', 'bsd-split-pay-stripe-connect-woo' ),
					'&raquo;'
				);
			}

			$pagination_links_class = 'pagination-links';
			if ( ! empty( $infinite_scroll ) ) {
				$pagination_links_class .= ' hide-if-js';
			}
			$output .= "\n<span class='$pagination_links_class'>" . implode( "\n", $page_links ) . '</span>';

			if ( $total_pages ) {
				$page_class = $total_pages < 2 ? ' one-page' : '';
			} else {
				$page_class = ' no-pages';
			}
			$this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

			echo $this->_pagination;
		}

		public function single_row_columns( $item ) {
			[ $columns, $hidden ] = $this->get_column_info();
			$order                = wc_get_order( $item['wc_order_id'] );

			if ( WC()->payment_gateways() ) {
				$payment_gateways = WC()->payment_gateways->payment_gateways();
			} else {
				$payment_gateways = array();
			}
			$stripe_mode    = $this->get_stripe_mode();
			$payment_method = $order->get_payment_method();

			$charge_url   = $payment_gateways[ $payment_method ]->get_transaction_url( $order );
			$account_url  = 'https://dashboard.stripe.com/' . $stripe_mode . '/connect/accounts/' . esc_attr( $item['transfer_destination'] );
			$transfer_url = 'https://dashboard.stripe.com/' . $stripe_mode . '/connect/transfers/' . esc_attr( $item['transfer_id'] );

			foreach ( $columns as $column_name => $column_display_name ) {
				switch ( $column_name ) {
					case 'id':
						?>
						<td class='bsd-scsp column-id center-column'>
							<?php echo esc_html( $item['id'] ); ?>
						</td>
						<?php
						break;
					case 'wc_order_id':
						?>
						<td class='bsd-scsp column-wc_order_id center-column'>
							<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?> "
								target="_blank"><?php echo esc_html( $item['wc_order_id'] ); ?></a>
						</td>
						<?php
						break;
					case 'column-charge_date':
						?>
						<td class='bsd-scsp column-charge_date center-column'>
							<?php echo esc_html( $item['charge_date'] ); ?>
						</td>
						<?php
						break;
					case 'charge_amount':
						?>
						<td class='bsd-scsp column-charge_amount center-column'>
							<?php echo wc_price( $item['charge_amount'] ); ?>
						</td>
						<?php
						break;
					case 'transfer_amount':
						?>
						<td class='bsd-scsp column-transfer_amount center-column'
							data-transfer_type="<?php echo ( isset( $item['transfer_type'] ) ) ? $item['transfer_type'] : ''; ?>">
							<?php echo wc_price( $item['transfer_amount'] ); ?>
						</td>
						<?php
						break;
					case 'transfer_tax_value':
						?>
						<td class='bsd-scsp column-transfer_tax_value center-column'
							data-transfer_type="<?php echo ( isset( $item['transfer_tax_value'] ) ) ? $item['transfer_tax_value'] : ''; ?>">
							<?php echo wc_price( $item['transfer_tax_value'] ); ?>
						</td>
						<?php
						break;

					case 'transfer_destination':
						?>
						<td class='bsd-scsp column-transfer_destination center-column'>
							<a href="<?php echo esc_url( $account_url ); ?>" target="_blank"
								title="<?php echo esc_attr( $item['transfer_destination'] ); ?>"><?php echo esc_attr( substr( $item['transfer_destination'], 0, 8 ) ) . '...'; ?><?php echo esc_attr( substr( $item['transfer_destination'], - 5 ) ); ?></a>

						</td>
						<?php
						break;
					case 'charge_id':
						?>
						<td class='bsd-scsp column-charge_id center-column'>
							<a href="<?php echo esc_url( $charge_url ); ?>" target="_blank"
								title="<?php echo esc_html( $item['charge_id'] ); ?>"><?php echo esc_html( substr( $item['charge_id'], 0, 5 ) ) . '...'; ?><?php echo esc_html( substr( $item['charge_id'], - 5 ) ); ?></a>
						</td>
						<?php
						break;
					case 'transfer_id':
						?>
						<td class='bsd-scsp column-transfer_id center-column'>
							<a href="<?php echo esc_url( $transfer_url ); ?>" target="_blank"
								title="<?php echo esc_html( $item['transfer_id'] ); ?>"><?php echo esc_html( substr( $item['transfer_id'], 0, 5 ) . '...' ); ?><?php echo esc_html( substr( $item['transfer_id'], - 5 ) ); ?></a>
						</td>
						<?php
						break;
					case 'transfer_type':
						?>
						<td class='bsd-scsp column-transfer_type center-column'>
							<?php
							if ( isset( $item['transfer_type'] ) ) {
								switch ( $item['transfer_type'] ) {
									case 1:
										// echo "Global percentage transfer";
										echo 'Subtotal';
										break;
									case 2:
										// echo "Global fixed transfer";
										echo 'Subtotal';
										break;
									case 3:
										// echo "Global shipping percentage transfer";
										echo 'Shipping';
										break;
									case 4:
										// echo "Global shipping fixed transfer";
										echo 'Shipping';
										break;
									case 5:
										// echo "Product-level transfer";
										echo 'Subtotal';
										break;
									case 6:
										// echo "Product-level shipping percentage transfer";
										echo 'Shipping';
										break;
									case 7:
										// echo "Product-level shipping fixed transfer";
										echo 'Shipping';
										break;
									case 8:
									case 9:
									case 10:
									case 11:
										// echo "Product-level shipping fixed transfer";
										echo 'Subtotal';
										break;
								}
							}
							?>
						</td>
						<?php
						break;

						case 'tax_transfer_type':
						?>
							<td class='bsd-scsp column-tax_transfer_type center-column'>
						<?php
						if ( isset( $item['tax_transfer_type'] ) ) {

							if ( isset( $item['transfer_type'] ) ) {
								if ( $item['transfer_type'] == 6 || $item['transfer_type'] == 7 ) {
									$item['tax_transfer_type'] = 'product_shipping';
								}
							}

							switch ( $item['tax_transfer_type'] ) {
								case 'all':
									echo esc_html__( 'All (100%)', 'bsd-split-pay-stripe-connect-woo' );
									break;
								case 'partial':
									echo esc_html__( 'Partial', 'bsd-split-pay-stripe-connect-woo' );
									break;
								case 'product_shipping':
									echo esc_html__( 'N/A', 'bsd-split-pay-stripe-connect-woo' );
									echo '<span class="tooltip woocommerce-help-tip">';
										echo __( '<span class="tooltiptext">Due to the way WooCommerce calculates tax on shipping, shipping tax transfers do not work with product-level settings configured. Read our <a href="https://docs.splitpayplugin.com/features/tax-handling" target="_blank">documentation</a> for details.</span>', 'bsd-split-pay-stripe-connect-woo' );
									echo '</span>';
									break;

								}
							}
						?>
							</td>
						<?php
							break;

					case 'transfer_entered_value':
						?>
						<td class='bsd-scsp column-entered_value center-column'>
							<?php
							if ( isset( $item['transfer_entered_value'] ) && ! empty( $item['transfer_entered_value'] ) ) {
								echo $item['transfer_entered_value'];
							}
							?>
						</td>
						<?php
						break;
					case 'entered_variable':
						?>
						<td class='bsd-scsp column-entered_variable center-column'>
							<?php
							if ( isset( $item['entered_variable'] ) ) {
								switch ( $item['entered_variable'] ) {
									case 1:
										// echo "Global percentage transfer";
										echo 'Percentage';
										break;
									case 2:
										// echo "Global fixed transfer";
										echo 'Fixed';
										break;
									case 3:
										// echo "Global shipping percentage transfer";
										echo 'Percentage';
										break;
									case 4:
										// echo "Global shipping fixed transfer";
										echo 'Fixed';
										break;
									case 5:
										// echo "Product-level transfer";
										echo 'Percentage';
										break;
									case 6:
										// echo "Product-level shipping percentage transfer";
										echo 'Percentage';
										break;
									case 7:
										// echo "Product-level shipping fixed transfer";
										echo 'Fixed';
										break;
									case 8:
										// echo "Product-level shipping fixed transfer";
										echo 'Percentage';
										break;
									case 9:
										// echo "Product-level shipping fixed transfer";
										echo 'Fixed';
										break;
									case 10:
										// echo "Product-level shipping fixed transfer";
										echo 'Percentage';
										break;
									case 11:
										// echo "Product-level shipping fixed transfer";
										echo 'Fixed';
										break;
								}
							}
							?>
						</td>
						<?php
						break;
					case 'level':
						?>
						<td class='bsd-scsp column-level center-column'>
							<?php
							if ( isset( $item['level'] ) ) {
								switch ( $item['level'] ) {
									case 1:
										// echo "Global percentage transfer";
										echo 'Global';
										break;
									case 2:
										// echo "Global fixed transfer";
										echo 'Global';
										break;
									case 3:
										// echo "Global shipping percentage transfer";
										echo 'Global';
										break;
									case 4:
										// echo "Global shipping fixed transfer";
										echo 'Global';
										break;
									case 5:
										// echo "Product-level transfer";
										echo 'Product';
										break;
									case 6:
										// echo "Product-level shipping percentage transfer";
										echo 'Product';
										break;
									case 7:
										// echo "Product-level shipping fixed transfer";
										echo 'Product';
										break;
									case 8:
									case 9:
										// echo "Product-level shipping fixed transfer";
										echo 'Product';
										break;
									case 10:
									case 11:
										// echo "Product-level shipping fixed transfer";
										echo 'Variable';
										break;
								}
							}
							?>
						</td>
						<?php
						break;

					default:
						echo '<td>';
						echo $this->column_default( $item, $column_name );
						echo '</td>';
				}
			}
		}
	}

endif; // class_exists check
