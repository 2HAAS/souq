<?php
/**
 * Site header.
 *
 * One template serves every layout preset. The three zones (navigation,
 * brand, actions) are always rendered and always contain the same controls;
 * the preset only changes their order and alignment, via CSS grid on
 * .souq-header__bar. That way switching layout can never hide a control from
 * a shopper, and there is a single markup path to maintain.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$config     = souq_header_config();
$cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;

$classes = array( 'souq-header', 'souq-header--' . $config['layout'] );

if ( $config['sticky'] ) {
	$classes[] = 'souq-header--sticky';
}

if ( $config['transparent'] ) {
	$classes[] = 'souq-header--transparent';
}

// Where the primary navigation is rendered. Minimal keeps it behind the menu
// button at every width; stacked gives it its own row beneath the brand. Both
// therefore omit it from the top row, so it is only ever emitted once and the
// header never carries two "Primary" nav landmarks.
$inline_nav  = ! in_array( $config['layout'], array( 'minimal', 'stacked' ), true );
$stacked_nav = 'stacked' === $config['layout'];
?>

<?php get_template_part( 'template-parts/header/announcement' ); ?>

<header class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="souq-header">
	<div class="souq-container souq-header__bar">

		<div class="souq-header__zone souq-header__zone--nav">
			<button type="button" class="souq-header__toggle" aria-expanded="false" aria-controls="souq-mobile-drawer">
				<?php echo souq_icon( 'menu', array( 'size' => '1.25rem' ) ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Open menu', 'souq' ); ?></span>
			</button>

			<?php if ( $inline_nav ) : ?>
				<nav class="souq-header__nav" aria-label="<?php esc_attr_e( 'Primary', 'souq' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'primary',
							'container'      => false,
							'menu_class'     => 'souq-nav',
							'depth'          => 1,
							'fallback_cb'    => 'souq_default_primary_menu',
						)
					);
					?>
				</nav>
			<?php endif; ?>
		</div>

		<div class="souq-header__zone souq-header__zone--brand">
			<?php souq_render_wordmark(); ?>
		</div>

		<div class="souq-header__zone souq-header__zone--actions">
			<button type="button" class="souq-header__action" id="souq-search-toggle" aria-expanded="false" aria-controls="souq-search-panel">
				<?php echo souq_icon( 'search', array( 'size' => '1.125rem' ) ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Search', 'souq' ); ?></span>
			</button>

			<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' ) ); ?>" class="souq-header__action souq-header__action--account">
				<?php echo souq_icon( 'user', array( 'size' => '1.125rem' ) ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Account', 'souq' ); ?></span>
			</a>

			<a href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/' ) ); ?>" class="souq-header__action souq-header__action--cart">
				<?php echo souq_icon( 'cart', array( 'size' => '1.125rem' ) ); ?>
				<span class="souq-cart-count" aria-hidden="<?php echo $cart_count > 0 ? 'false' : 'true'; ?>"><?php echo esc_html( $cart_count ); ?></span>
				<span class="screen-reader-text">
					<?php
					printf(
						/* translators: %d: number of items in the cart */
						esc_html( _n( 'Cart, %d item', 'Cart, %d items', (int) $cart_count, 'souq' ) ),
						(int) $cart_count
					);
					?>
				</span>
			</a>
		</div>

	</div>

	<?php if ( $stacked_nav ) : ?>
		<div class="souq-header__row souq-header__row--nav">
			<nav class="souq-header__nav souq-header__nav--stacked" aria-label="<?php esc_attr_e( 'Primary', 'souq' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'souq-nav',
						'depth'          => 1,
						'fallback_cb'    => 'souq_default_primary_menu',
					)
				);
				?>
			</nav>
		</div>
	<?php endif; ?>
</header>
