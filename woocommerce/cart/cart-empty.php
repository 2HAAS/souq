<?php
/**
 * Souq override of the empty-cart state.
 *
 * WooCommerce's default is a single grey notice, which reads as an error when
 * nothing has actually gone wrong. An empty cart is a normal, very common
 * state and its job is to send the shopper somewhere useful, so this offers
 * the catalogue and the main categories rather than just reporting emptiness.
 *
 * @package Souq
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_cart_is_empty' );

$categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => true,
		'number'     => 4,
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
	)
);
?>

<div class="souq-empty">
	<p class="souq-eyebrow"><?php esc_html_e( 'Your bag', 'souq' ); ?></p>
	<h1 class="souq-empty__title"><?php esc_html_e( 'Nothing here yet', 'souq' ); ?></h1>
	<p class="souq-empty__body">
		<?php esc_html_e( 'Pieces you add will be kept here while you browse.', 'souq' ); ?>
	</p>

	<p class="souq-empty__actions">
		<a class="souq-btn souq-btn--primary" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
			<?php esc_html_e( 'Browse the collection', 'souq' ); ?>
		</a>
	</p>

	<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
		<div class="souq-empty__links">
			<span class="souq-empty__links-label"><?php esc_html_e( 'Or start with', 'souq' ); ?></span>
			<ul>
				<?php foreach ( $categories as $category ) : ?>
					<?php
					$link = get_term_link( $category );
					if ( is_wp_error( $link ) ) {
						continue;
					}
					?>
					<li><a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $category->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
