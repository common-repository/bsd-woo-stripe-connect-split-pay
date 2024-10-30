<?php

namespace BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin;

if ( ! function_exists( 'BSD_Split_Pay_Stripe_Connect_Woo\Inc\Admin\bsd_scsp_options' ) ) {
	function bsd_scsp_options() {
		global $bsd_sca;
		$need_to_gray_tabs = ( $bsd_sca->is_stripe_enabled_and_configured() ) ? '' : 'grayed-tab';

?>
		<div class='wrap'>
			<h1><?php esc_html_e( 'Split Pay for Stripe Connect on WooCommerce', 'bsd-split-pay-stripe-connect-woo' ); ?></h1>
			<?php settings_errors(); ?>
			<div id='poststuff'>
				<div id='post-body' class='metabox-holder columns-1'>
					<!-- Content -->
					<div id='post-body-content'>
						<div class='inside' id='bsd-split-pay-stripe-connect-woo-settings'>
							<?php $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'main'; ?>
							<div class='nav-tab-wrapper bsd-split-pay-stripe-connect-woo-settings-header'>
								<a href='?page=bsd-split-pay-stripe-connect-woo-settings&tab=main' class="nav-tab <?php /* echo $need_to_gray_tabs; */ ?> <?php echo $active_tab == 'main' ? 'nav-tab-active' : ''; ?>">
									<?php esc_html_e( 'Transfer Settings', 'bsd-split-pay-stripe-connect-woo' ); ?>
								</a>

								<a href='?page=bsd-split-pay-stripe-connect-woo-settings&tab=transfers' class="nav-tab <?php /* echo $need_to_gray_tabs; */ ?> <?php echo $active_tab == 'transfers' ? 'nav-tab-active' : ''; ?>">
									<?php esc_html_e( 'Transfers', 'bsd-split-pay-stripe-connect-woo' ); ?>
								</a>

								<a href='?page=bsd-split-pay-stripe-connect-woo-settings&tab=bulk-editor' class="nav-tab <?php /* echo $need_to_gray_tabs; */ ?> <?php echo $active_tab == 'bulk-editor' ? 'nav-tab-active' : ''; ?>">
									<?php esc_html_e( 'Bulk Editor', 'bsd-split-pay-stripe-connect-woo' ); ?>
								</a>
								<a href='?page=bsd-split-pay-stripe-connect-woo-settings&tab=stripe-configuration' class="nav-tab <?php echo $active_tab == 'stripe-configuration' ? 'nav-tab-active' : ''; ?>">
									<?php esc_html_e( 'Stripe Configuration', 'bsd-split-pay-stripe-connect-woo' ); ?>
								</a>

								<a href='?page=bsd-split-pay-stripe-connect-woo-settings&tab=vendor-onboarding' class="nav-tab <?php /* echo $need_to_gray_tabs; */ ?> <?php echo $active_tab == 'vendor-onboarding' ? 'nav-tab-active' : ''; ?>">
									<?php esc_html_e( 'Vendor Onboarding', 'bsd-split-pay-stripe-connect-woo' ); ?>
								</a>
							</div>

							<!-- <form method='POST' action='admin-post.php'> -->
							<?php
							switch ( $active_tab ) {
								case 'main':
									echo "<form method='POST' action='options.php'>";
									include_once 'partials/tab-main.php';
									echo '</form>';
									break;
								case 'stripe-configuration':
									echo "<form method='POST' action='options.php'>";
									include_once 'partials/stripe-configuration.php';
									echo '</form>';
									break;
								case 'vendor-onboarding':
									echo '<div class="' . $need_to_gray_tabs . '">';
										echo "<form method='POST' action='options.php'>";
										include_once 'partials/tab-vendor-onboarding.php';
										echo '</form>';
									echo '</div>';
									break;
								case 'transfers':
									echo '<div class="' . $need_to_gray_tabs . '">';
									include_once 'partials/tab-transfers.php';
									echo '</div>';
									break;
								case 'bulk-editor':
									echo '<div class="' . $need_to_gray_tabs . '">';
									include_once 'partials/spp-bulk-editor-html.php';
									echo '</div>';
									break;
							}
							?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
