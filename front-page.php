<?php
/**
 * Homepage: renders the assigned front page's block content directly, full
 * width. Every section is a Gutenberg block pattern an admin inserts,
 * reorders, edits, or removes from Appearance > Editor / Pages > Home —
 * genuinely modular per-section editing, not a fixed PHP template
 * (PRODUCT.md principle 1). Falls back to a starter prompt when no content
 * has been added yet, rather than rendering an empty homepage silently.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();

		if ( has_blocks() ) {
			the_content();
		} else {
			get_template_part( 'template-parts/homepage/empty-state' );
		}

	endwhile;
else :
	get_template_part( 'template-parts/homepage/empty-state' );
endif;

get_footer();
