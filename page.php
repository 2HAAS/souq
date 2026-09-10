<?php
/**
 * Generic page template.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="souq-container souq-section">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'souq-page' ); ?>>
			<h1><?php the_title(); ?></h1>
			<div class="souq-page__content"><?php the_content(); ?></div>
		</article>
		<?php
	endwhile;
	?>
</div>

<?php
get_footer();
