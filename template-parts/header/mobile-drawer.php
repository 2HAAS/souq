<?php
/**
 * Full-screen mobile nav drawer (DESIGN.md Navigation: "not an off-canvas
 * sliver"). Triggered by the header hamburger button.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="souq-drawer" id="souq-mobile-drawer" hidden>
	<div class="souq-drawer__head souq-container">
		<?php souq_render_wordmark(); ?>
		<button type="button" class="souq-drawer__close" id="souq-drawer-close">
			<?php echo souq_icon( 'close', array( 'size' => '1.5rem' ) ); ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Close menu', 'souq' ); ?></span>
		</button>
	</div>

	<nav class="souq-drawer__nav souq-container" aria-label="<?php esc_attr_e( 'Primary', 'souq' ); ?>">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'souq-drawer-nav',
				'fallback_cb'    => 'souq_default_primary_menu',
			)
		);
		?>
	</nav>

	<div class="souq-drawer__foot souq-container">
		<a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '#' ); ?>" class="souq-drawer__account-link">
			<?php echo souq_icon( 'user', array( 'size' => '1.125rem' ) ); ?>
			<?php esc_html_e( 'Account', 'souq' ); ?>
		</a>
	</div>
</div>
