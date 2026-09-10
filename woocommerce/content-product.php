<?php
/**
 * Souq override of WooCommerce's loop product card.
 *
 * Card-less by design: the image sits directly on the page ground with no
 * border, fill, shadow or radius, so a wall of garments reads as a gallery
 * rather than a grid of boxes.
 *
 * The media area is a slider over the product's full image set (featured,
 * gallery, and each variation's own image), so a shopper can see the other
 * colourways without opening the product. Controls appear on hover and on
 * keyboard focus; with JavaScript off the first image simply shows.
 *
 * WooCommerce's default loop callbacks are unhooked in
 * class-souq-woocommerce.php, but the standard action hooks still fire here
 * so third-party extensions keep working (PRODUCT.md principle 4).
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}

$link      = get_permalink( $product->get_id() );
$images    = souq_get_product_images( $product, 5 );
$swatches  = souq_get_card_swatches( $product );
$heading   = 'h' . souq_card_heading_level();
$has_multi = count( $images ) > 1;
?>
<li <?php wc_product_class( 'souq-card', $product ); ?> data-souq-product-id="<?php echo esc_attr( $product->get_id() ); ?>">

	<?php do_action( 'woocommerce_before_shop_loop_item' ); ?>

	<div class="souq-card__media<?php echo $has_multi ? ' souq-card__media--slider' : ''; ?>" <?php echo $has_multi ? 'data-souq-card-slider' : ''; ?>>

		<a class="souq-card__link" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true">
			<?php if ( empty( $images ) ) : ?>
				<span class="souq-card__image souq-card__image--empty"></span>
			<?php else : ?>
				<?php foreach ( $images as $index => $image_id ) : ?>
					<?php
					echo wp_get_attachment_image(
						$image_id,
						'souq-product-card',
						false,
						array(
							'class'      => 'souq-card__image' . ( 0 === $index ? ' is-active' : '' ),
							'alt'        => '',
							'loading'    => 0 === $index ? 'lazy' : 'lazy',
							'data-index' => $index,
						)
					);
					?>
				<?php endforeach; ?>
			<?php endif; ?>
		</a>

		<?php if ( $has_multi ) : ?>
			<button type="button" class="souq-card__nav souq-card__nav--prev" data-souq-card-prev>
				<?php echo souq_icon( 'chevron-left' ); ?>
				<span class="screen-reader-text">
					<?php
					printf(
						/* translators: %s: product name */
						esc_html__( 'Previous image of %s', 'souq' ),
						esc_html( $product->get_name() )
					);
					?>
				</span>
			</button>

			<button type="button" class="souq-card__nav souq-card__nav--next" data-souq-card-next>
				<?php echo souq_icon( 'chevron-right' ); ?>
				<span class="screen-reader-text">
					<?php
					printf(
						/* translators: %s: product name */
						esc_html__( 'Next image of %s', 'souq' ),
						esc_html( $product->get_name() )
					);
					?>
				</span>
			</button>

			<span class="souq-card__counter" data-souq-card-counter aria-hidden="true">1/<?php echo count( $images ); ?></span>
		<?php endif; ?>

		<?php if ( $product->is_on_sale() ) : ?>
			<span class="souq-badge souq-badge--sale souq-card__badge"><?php esc_html_e( 'Sale', 'souq' ); ?></span>
		<?php elseif ( ! $product->is_in_stock() ) : ?>
			<span class="souq-badge souq-badge--out souq-card__badge"><?php esc_html_e( 'Sold out', 'souq' ); ?></span>
		<?php endif; ?>
	</div>

	<div class="souq-card__body">
		<<?php echo esc_html( $heading ); ?> class="souq-card__title">
			<a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		</<?php echo esc_html( $heading ); ?>>

		<div class="souq-card__meta">
			<span class="souq-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>

			<?php if ( ! empty( $swatches ) ) : ?>
				<span class="souq-card__swatches" aria-label="<?php esc_attr_e( 'Available colours', 'souq' ); ?>">
					<?php foreach ( $swatches as $swatch ) : ?>
						<span
							class="souq-card__swatch"
							style="--souq-swatch-color: <?php echo esc_attr( $swatch['color'] ); ?>"
							title="<?php echo esc_attr( $swatch['name'] ); ?>"
						></span>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>
		</div>
	</div>

	<?php do_action( 'woocommerce_after_shop_loop_item' ); ?>

</li>
