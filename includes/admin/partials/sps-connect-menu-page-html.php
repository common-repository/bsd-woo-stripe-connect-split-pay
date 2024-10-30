<?php
$current_user = wp_get_current_user();

$vendor_onboading         = get_option( 'vendor_onboading', false );
$enable_title_description = get_option( 'enable_title_description', false );
$onboarding_title         = get_option( 'onboarding_title', false );
$onboarding_description   = get_option( 'onboarding_description', false );

update_user_meta( $current_user->ID, 'is_onboard', '0' );
?>
<div class="wrap">
	<?php
	if ( $enable_title_description && ! empty( $onboarding_title ) ) {
		?>
		<h1><?php esc_html_e( $onboarding_title, 'bsd-split-pay-stripe-connect-woo' ); ?></h1>
		<?php
	} else {
		?>
		<h1><?php esc_html_e( 'Stripe Connect Settings', 'bsd-split-pay-stripe-connect-woo' ); ?></h1>
		<?php
	}
	?>
	<div class="connect_disconnect_wrapper">
		<div class="connect_disconnect_step_one_wrapper">
			<!-- <div class="cd_title"><?php esc_html_e( $onboarding_title, 'bsd-split-pay-stripe-connect-woo' ); ?></div> -->
			<?php
			if ( $enable_title_description && ! empty( $onboarding_description ) ) {
				?>
				<p><?php esc_html_e( $onboarding_description, 'bsd-split-pay-stripe-connect-woo' ); ?><p]>
				<?php
			} else {
				?>
				<p><?php esc_html_e( 'Please connect your Stripe account.', 'bsd-split-pay-stripe-connect-woo' ); ?><p]>
				<?php
			}
			?>
			<div class="cd_button_wrapper">
				<span class="cd_button_letter">S</span>

				<?php
				$is_onboard = get_user_meta( $current_user->ID, 'account_id', true );

				if ( ! empty( $is_onboard ) ) {
					?>
					<span class="cd_button">
						<a href="<?php echo admin_url( '/admin.php?page=split-pay-stripe-connect&action=disconnect_onboarding' ); ?>"><?php esc_html_e( 'Disconnect from Stripe', 'bsd-split-pay-stripe-connect-woo' ); ?></a>
					</span>
					<?php
				} else {
					?>
					<span class="cd_button">
						<a href="<?php echo admin_url( '/admin.php?page=split-pay-stripe-connect&action=onboarding' ); ?>"><?php esc_html_e( 'Connect with Stripe', 'bsd-split-pay-stripe-connect-woo' ); ?></a>
					</span>
					<?php
				}
				?>
			</div>
		</div>
	</div>
</div>
