<?php
/**
 * Honest empty state for a homepage with no sections added yet, shown only
 * to logged-in editors so shoppers never see an empty storefront silently.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="souq-container souq-section souq-homepage-empty">
	<?php if ( current_user_can( 'edit_theme_options' ) ) : ?>
		<h1><?php esc_html_e( 'This homepage has no sections yet', 'souq' ); ?></h1>
		<p>
			<?php esc_html_e( 'Edit the "Home" page and insert Souq block patterns (Hero, Featured Categories, Curated Product Grid, Editorial Banner, Newsletter) to build this page — each one stays fully editable in place.', 'souq' ); ?>
		</p>
		<a class="souq-btn souq-btn--primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>">
			<?php esc_html_e( 'Edit Home Page', 'souq' ); ?>
		</a>
	<?php else : ?>
		<h1><?php esc_html_e( 'Welcome to Souq', 'souq' ); ?></h1>
		<p><?php esc_html_e( 'We\'re getting things ready. Please check back soon.', 'souq' ); ?></p>
	<?php endif; ?>
</div>
