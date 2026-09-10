<?php
/**
 * Fallback template.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="souq-container souq-section">
	<?php if ( have_posts() ) : ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'souq-post' ); ?>>
				<h1><?php the_title(); ?></h1>
				<div class="souq-post__content"><?php the_content(); ?></div>
			</article>
			<?php
		endwhile;
		the_posts_pagination();
	else :
		?>
		<p><?php esc_html_e( 'Nothing found.', 'souq' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
