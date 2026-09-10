<?php
/**
 * Header: skip link, site header, search panel, mobile drawer.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link" href="#souq-main"><?php esc_html_e( 'Skip to content', 'souq' ); ?></a>

<?php get_template_part( 'template-parts/header/site-header' ); ?>
<?php get_template_part( 'template-parts/header/search-panel' ); ?>
<?php get_template_part( 'template-parts/header/mobile-drawer' ); ?>

<main id="souq-main" class="souq-main">
