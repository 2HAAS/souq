<?php
/**
 * Souq override of the single-product gallery.
 *
 * Replaces WooCommerce's FlexSlider gallery with a purpose-built one. Three
 * reasons: FlexSlider laid every slide out stacked (so a shopper saw one
 * enormous image and no sign the other two existed), its thumbnail strip fell
 * far below the fold, and its variation handling only swaps the single main
 * image rather than moving through the set.
 *
 * Each slide carries its attachment ID so the variation form can jump to the
 * matching image when a colourway is chosen (see assets/js/gallery.js).
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

$images = souq_get_product_images( $product, 8 );

if ( empty( $images ) ) {
	printf(
		'<div class="souq-gallery souq-gallery--empty"><img src="%s" alt="%s" /></div>',
		esc_url( wc_placeholder_img_src( 'souq-product-gallery' ) ),
		esc_attr__( 'Awaiting product image', 'souq' )
	);
	return;
}

$total = count( $images );
?>
<div class="souq-gallery" data-souq-gallery>

	<div class="souq-gallery__stage">
		<?php foreach ( $images as $index => $image_id ) : ?>
			<figure
				class="souq-gallery__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
				data-index="<?php echo esc_attr( $index ); ?>"
				data-attachment-id="<?php echo esc_attr( $image_id ); ?>"
			>
				<?php
				echo wp_get_attachment_image(
					$image_id,
					'souq-product-gallery',
					false,
					array(
						'class'   => 'souq-gallery__image',
						'alt'     => esc_attr( $product->get_name() ),
						'loading' => 0 === $index ? 'eager' : 'lazy',
					)
				);
				?>
			</figure>
		<?php endforeach; ?>

		<?php if ( $total > 1 ) : ?>
			<button type="button" class="souq-gallery__nav souq-gallery__nav--prev" data-souq-gallery-prev>
				<?php echo souq_icon( 'chevron-left', array( 'size' => '1.25rem' ) ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Previous image', 'souq' ); ?></span>
			</button>

			<button type="button" class="souq-gallery__nav souq-gallery__nav--next" data-souq-gallery-next>
				<?php echo souq_icon( 'chevron-right', array( 'size' => '1.25rem' ) ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Next image', 'souq' ); ?></span>
			</button>

			<span class="souq-gallery__counter" data-souq-gallery-counter aria-hidden="true">1 / <?php echo (int) $total; ?></span>

			<p class="screen-reader-text" data-souq-gallery-live aria-live="polite"></p>
		<?php endif; ?>
	</div>

	<?php if ( $total > 1 ) : ?>
		<div class="souq-gallery__thumbs" role="tablist" aria-label="<?php esc_attr_e( 'Product images', 'souq' ); ?>">
			<?php foreach ( $images as $index => $image_id ) : ?>
				<button
					type="button"
					class="souq-gallery__thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
					role="tab"
					aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
					data-souq-gallery-thumb="<?php echo esc_attr( $index ); ?>"
				>
					<?php
					echo wp_get_attachment_image(
						$image_id,
						'souq-product-thumb',
						false,
						array(
							'alt'     => '',
							'loading' => 'lazy',
						)
					);
					?>
					<span class="screen-reader-text">
						<?php
						printf(
							/* translators: 1: image number, 2: total images */
							esc_html__( 'Show image %1$d of %2$d', 'souq' ),
							(int) $index + 1,
							(int) $total
						);
						?>
					</span>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

</div>
