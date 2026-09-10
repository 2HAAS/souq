<?php
/**
 * Announcement bar. Renders nothing unless a merchant has written something,
 * so an unconfigured site never shows an empty strip.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$config = souq_header_config();

if ( '' === $config['announcement'] ) {
	return;
}

$url = $config['announcement_url'];
?>
<div class="souq-announcement">
	<div class="souq-container">
		<?php if ( $url ) : ?>
			<a class="souq-announcement__text" href="<?php echo esc_url( $url ); ?>">
				<?php echo esc_html( $config['announcement'] ); ?>
			</a>
		<?php else : ?>
			<p class="souq-announcement__text"><?php echo esc_html( $config['announcement'] ); ?></p>
		<?php endif; ?>
	</div>
</div>
