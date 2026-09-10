<?php
/**
 * Floating add-to-bag bar. Appears once the real add-to-cart button scrolls
 * out of view, on desktop as well as mobile: the purchase panel is long once
 * the accordion is open, so the action must stay reachable.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
	return;
}

$image_id  = $product->get_image_id();
$thumb_url = $image_id ? wp_get_attachment_image_url( $image_id, 'souq-product-thumb' ) : '';
?>
<div class="souq-sticky-cta" id="souq-sticky-cta">
	<div class="souq-sticky-cta__row">
		<?php if ( $thumb_url ) : ?>
			<img class="souq-sticky-cta__thumb" src="<?php echo esc_url( $thumb_url ); ?>" alt="" width="44" height="54" loading="lazy">
		<?php endif; ?>

		<span class="souq-sticky-cta__text">
			<span class="souq-sticky-cta__name"><?php echo esc_html( $product->get_name() ); ?></span>
			<span class="souq-sticky-cta__variant" data-souq-sticky-variant></span>
		</span>

		<span class="souq-sticky-cta__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>

		<button type="button" class="souq-btn souq-btn--primary souq-sticky-cta__button">
			<?php esc_html_e( 'Add to bag', 'souq' ); ?>
		</button>
	</div>
</div>
