<?php
/**
 * Souq theme bootstrap.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOUQ_VERSION', '0.1.0' );
define( 'SOUQ_DIR', get_template_directory() );
define( 'SOUQ_URI', get_template_directory_uri() );

require SOUQ_DIR . '/inc/setup.php';
require SOUQ_DIR . '/inc/class-souq-assets.php';
require SOUQ_DIR . '/inc/class-souq-icons.php';
require SOUQ_DIR . '/inc/class-souq-customizer.php';
require SOUQ_DIR . '/inc/class-souq-block-patterns.php';
require SOUQ_DIR . '/inc/blocks/class-souq-product-grid-block.php';
require SOUQ_DIR . '/inc/class-souq-onboarding.php';
require SOUQ_DIR . '/inc/class-souq-compatibility.php';
require SOUQ_DIR . '/inc/woocommerce/class-souq-woocommerce.php';
require SOUQ_DIR . '/inc/woocommerce/class-souq-attribute-swatches.php';
require SOUQ_DIR . '/inc/woocommerce/class-souq-recommended-products.php';
require SOUQ_DIR . '/inc/woocommerce/class-souq-size-charts.php';
require SOUQ_DIR . '/inc/class-souq-ajax-search.php';
require SOUQ_DIR . '/inc/class-souq-seo.php';

Souq\Assets::instance();
Souq\Customizer::instance();
Souq\Block_Patterns::instance();
Souq\Product_Grid_Block::instance();
Souq\Onboarding::instance();
Souq\Compatibility::instance();
Souq\WooCommerce::instance();
Souq\Attribute_Swatches::instance();
Souq\Recommended_Products::instance();
Souq\Size_Charts::instance();
Souq\Ajax_Search::instance();
Souq\SEO::instance();
