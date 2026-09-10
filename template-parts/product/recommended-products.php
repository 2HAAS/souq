<?php
/**
 * Recommended products, shown beneath the product as an expandable list.
 * Renders nothing when there is nothing to show — no curated picks and
 * either no same-category fallback available or the merchant has turned it
 * off (see souq_show_recommendations / souq_recommendations_fallback in the
 * Customizer's Recommendations section).
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! get_theme_mod( 'souq_show_recommendations', true ) ) {
	return;
}

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

$count       = max( 1, (int) get_theme_mod( 'souq_recommendations_count', 4 ) );
$allow_fill  = (bool) get_theme_mod( 'souq_recommendations_fallback', true );
$heading     = get_theme_mod( 'souq_recommendations_heading', __( 'You may also like', 'souq' ) );

$recommended = class_exists( '\Souq\Recommended_Products' )
	? \Souq\Recommended_Products::get_for_display( $product->get_id(), $count, $allow_fill )
	: array();

if ( empty( $recommended ) ) {
	return;
}
?>
<section class="souq-recommend souq-container" aria-labelledby="souq-recommend-heading">
	<h2 id="souq-recommend-heading" class="souq-section-heading"><?php echo esc_html( $heading ); ?></h2>

	<ul class="souq-recommend__list">
		<?php foreach ( $recommended as $rec ) : ?>
			<?php
			$rec_id    = $rec->get_id();
			$panel_id  = 'souq-rec-panel-' . $rec_id;
			$excerpt   = $rec->get_short_description() ? $rec->get_short_description() : $rec->get_description();
			$image_id  = $rec->get_image_id();
			$thumb_url = $image_id ? wp_get_attachment_image_url( $image_id, 'souq-product-thumb' ) : wc_placeholder_img_src( 'souq-product-thumb' );
			$large_url = $image_id ? wp_get_attachment_image_url( $image_id, 'souq-product-card' ) : wc_placeholder_img_src( 'souq-product-card' );
			?>
			<li class="souq-recommend__item" data-souq-product-id="<?php echo esc_attr( $rec_id ); ?>">
				<button type="button" class="souq-recommend__header" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
					<img class="souq-recommend__thumb" src="<?php echo esc_url( $thumb_url ); ?>" alt="" width="48" height="60" loading="lazy">
					<span class="souq-recommend__name"><?php echo esc_html( $rec->get_name() ); ?></span>
					<span class="souq-recommend__price"><?php echo wp_kses_post( $rec->get_price_html() ); ?></span>
					<span class="souq-recommend__chevron"><?php echo souq_icon( 'chevron-down' ); ?></span>
				</button>

				<div class="souq-recommend__panel" id="<?php echo esc_attr( $panel_id ); ?>" hidden>
					<div class="souq-recommend__panel-inner">
						<img class="souq-recommend__panel-image" src="<?php echo esc_url( $large_url ); ?>" alt="" loading="lazy">
						<div>
							<?php if ( $excerpt ) : ?>
								<p class="souq-recommend__excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $excerpt ), 26 ) ); ?></p>
							<?php endif; ?>
							<a class="souq-btn souq-btn--outline" href="<?php echo esc_url( get_permalink( $rec_id ) ); ?>">
								<?php esc_html_e( 'View product', 'souq' ); ?>
							</a>
						</div>
					</div>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
