<?php
/**
 * Site footer. Light on the page ground rather than a dark slab, so the
 * page ends quietly and the product photography stays the loudest thing
 * on screen.
 *
 * Columns are generic block-widget areas (`souq-footer-column-1..4`), not a
 * fixed "one menu column, one text column" shape — a merchant can put a
 * Navigation Menu block, a Paragraph/Custom HTML block for an introduction,
 * a second menu (Terms, Returns, anything), or several stacked in one
 * column, in any combination. Only columns that actually have content
 * render, and `.souq-footer__main` in header-footer.css uses flex-wrap
 * rather than a fixed grid track count, so any number of active columns —
 * one or all four, plus the optional brand and connect columns — lays out
 * responsively without the template knowing the count in advance.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$socials = array_filter(
	array(
		'instagram' => get_theme_mod( 'souq_social_instagram', '' ),
		'facebook'  => get_theme_mod( 'souq_social_facebook', '' ),
		'tiktok'    => get_theme_mod( 'souq_social_tiktok', '' ),
	)
);

$active_columns = array();
for ( $i = 1; $i <= SOUQ_FOOTER_MAX_COLUMNS; $i++ ) {
	$sidebar_id = 'souq-footer-column-' . $i;
	if ( is_active_sidebar( $sidebar_id ) ) {
		$active_columns[] = $sidebar_id;
	}
}

// Continuity for sites set up before columns existed: if nobody has put
// anything in the new columns yet, but a menu is still assigned to the
// classic "Footer Navigation" location, keep showing it rather than having
// the footer go quiet. The moment a merchant adds any widget to any column,
// this fallback steps aside — Navigation Menu blocks are now the flexible,
// multi-menu way to do the same thing.
$show_legacy_nav = empty( $active_columns ) && has_nav_menu( 'footer' );
?>
<footer class="souq-footer">
	<div class="souq-container">

		<div class="souq-footer__main">
			<div class="souq-footer__brand">
				<?php souq_render_wordmark( array( 'class' => 'souq-wordmark--footer' ) ); ?>
				<?php if ( get_bloginfo( 'description' ) ) : ?>
					<p class="souq-footer__tagline"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( $show_legacy_nav ) : ?>
				<nav class="souq-footer__col" aria-label="<?php esc_attr_e( 'Footer', 'souq' ); ?>">
					<h2 class="souq-footer__heading"><?php esc_html_e( 'Shop', 'souq' ); ?></h2>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => false,
							'menu_class'     => 'souq-footer-nav',
							'depth'          => 1,
						)
					);
					?>
				</nav>
			<?php endif; ?>

			<?php foreach ( $active_columns as $sidebar_id ) : ?>
				<div class="souq-footer__col"><?php dynamic_sidebar( $sidebar_id ); ?></div>
			<?php endforeach; ?>

			<?php if ( ! empty( $socials ) ) : ?>
				<div class="souq-footer__col souq-footer__col--connect">
					<h2 class="souq-footer__heading"><?php esc_html_e( 'Connect', 'souq' ); ?></h2>
					<div class="souq-footer__social">
						<?php foreach ( $socials as $network => $url ) : ?>
							<a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( ucfirst( $network ) ); ?>" rel="noopener">
								<?php echo souq_icon( $network, array( 'size' => '1.125rem' ) ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="souq-footer__bottom">
			<p class="souq-footer__copy">
				&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>
			</p>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'account',
					'container'      => false,
					'menu_class'     => 'souq-footer-legal',
					'depth'          => 1,
					'fallback_cb'    => '__return_empty_string',
				)
			);
			?>
		</div>

	</div>
</footer>
