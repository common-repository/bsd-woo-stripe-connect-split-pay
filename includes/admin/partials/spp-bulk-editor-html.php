<?php

if ( !defined( 'ABSPATH' ) ) {
    exit( 'Sorry!' );
}
global $post, $bsd_sca;
$args = array(
    'taxonomy'   => 'product_cat',
    'orderby'    => 'name',
    'order'      => 'ASC',
    'hide_empty' => false,
);
$product_categories = get_terms( $args );
$upgrade_url = BSD_SCSP_PLUGIN_UPGRADE_URL;
if ( function_exists( 'bsdwcscsp_fs' ) ) {
    $upgrade_url = bsdwcscsp_fs()->get_upgrade_url();
}
$image_path = BSD_SCSP_PLUGIN_ASSETS . '/spp-bulk-editor.png';
?>
	<section>
		<h1><?php 
echo esc_html( 'PRO Feature', 'bsd-split-pay-stripe-connect-woo' );
?></h1>
		<p class="bsd-scsp-helper-text bsd-ps-helper-text bigger-text"><?php 
esc_html_e( 'To edit product transfer settings in bulk, please', 'bsd-split-pay-stripe-connect-woo' );
?>
			<a href="<?php 
echo esc_url( $upgrade_url );
?>"><?php 
esc_html_e( 'Upgrade >', 'bsd-split-pay-stripe-connect-woo' );
?></a>.
		</p>
		<p class="bsd-scsp-helper-text bsd-ps-helper-text bigger-text"><?php 
esc_html_e( 'You can find more information about the Bulk Editor in our', 'bsd-split-pay-stripe-connect-woo' );
?>
			<a href="<?php 
echo esc_url( 'https://docs.splitpayplugin.com/features/bulk-editor' );
?>" target="_blank"><?php 
esc_html_e( 'documentation', 'bsd-split-pay-stripe-connect-woo' );
?></a>.
		</p>
	</section>
	<figure class="figure-wrapper">
		<img src="<?php 
echo esc_attr( $image_path );
?>" alt="Blurred example" style="width:100%">
	</figure>


	