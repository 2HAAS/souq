<?php
/**
 * Announcement bar. Renders nothing unless a merchant has written something.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$config       = souq_header_config();
$announcements = $config['announcements'];

if ( empty( $announcements ) ) {
	return;
}
?>
<div class="souq-announcement">
	<div
		class="souq-announcement__viewport<?php echo $config['marquee'] ? ' souq-announcement__viewport--marquee' : ''; ?>"
		<?php if ( $config['marquee'] ) : ?>
			data-souq-announcement-marquee
			data-speed="<?php echo esc_attr( $config['marquee_speed'] ); ?>"
		<?php endif; ?>
		role="region"
		aria-label="<?php esc_attr_e( 'Store announcements', 'souq' ); ?>"
	>
		<div class="souq-announcement__track">
			<div class="souq-announcement__sequence">
				<div class="souq-announcement__group">
					<?php foreach ( $announcements as $announcement ) : ?>
						<?php if ( $announcement['url'] ) : ?>
							<a class="souq-announcement__item" href="<?php echo esc_url( $announcement['url'] ); ?>">
								<?php echo esc_html( $announcement['text'] ); ?>
							</a>
						<?php else : ?>
							<span class="souq-announcement__item">
								<?php echo esc_html( $announcement['text'] ); ?>
							</span>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</div>
