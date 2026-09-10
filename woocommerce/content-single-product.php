<?php
/**
 * Souq override of WooCommerce's content-single-product.php.
 *
 * Two columns: the gallery runs flush to the left viewport edge so the
 * garment is presented at the largest possible size, and the purchase panel
 * sticks alongside it. Everything a shopper needs to decide (size, colour,
 * measurements, fabric, care) is in that right-hand column, progressively
 * disclosed through an accordion rather than buried in tabs below the fold.
 *
 * WooCommerce's standard action hooks still fire so extensions keep working
 * (PRODUCT.md principle 4); its own default callbacks for the pieces we
 * render ourselves are unhooked in class-souq-woocommerce.php.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'souq-product', $product ); ?>>

	<div class="souq-product__layout">

		<div class="souq-product__gallery">
			<?php do_action( 'woocommerce_before_single_product_summary' ); ?>
		</div>

		<div class="souq-product__panel">
			<div class="summary entry-summary souq-product__summary">
				<?php do_action( 'woocommerce_single_product_summary' ); ?>
			</div>
		</div>

	</div>

	<?php do_action( 'woocommerce_after_single_product_summary' ); ?>

</div>

<?php get_template_part( 'template-parts/product/sticky-cta' ); ?>

<?php do_action( 'woocommerce_after_single_product' ); ?>
