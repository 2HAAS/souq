<?php
/**
 * SEO foundations: meta description, Open Graph / Twitter Card tags, and
 * WebSite structured data. Defers entirely to Yoast SEO or RankMath when
 * either is active rather than emitting competing tags — WooCommerce's own
 * WC_Structured_Data class already covers Product JSON-LD, so this class
 * does not duplicate that.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEO {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( self::seo_plugin_active() ) {
			return;
		}

		add_action( 'wp_head', array( $this, 'output_meta_description' ), 1 );
		add_action( 'wp_head', array( $this, 'output_social_tags' ), 2 );
		add_action( 'wp_head', array( $this, 'output_website_schema' ), 3 );
	}

	private static function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' );
	}

	private function get_description() {
		if ( is_singular() ) {
			global $post;
			if ( $post instanceof \WP_Post ) {
				if ( $post->post_excerpt ) {
					return wp_strip_all_tags( $post->post_excerpt );
				}
				if ( is_singular( 'product' ) ) {
					$product = wc_get_product( $post->ID );
					if ( $product && $product->get_short_description() ) {
						return wp_strip_all_tags( $product->get_short_description() );
					}
				}
				return wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
			}
		}

		if ( is_shop() ) {
			$shop_page = get_post( wc_get_page_id( 'shop' ) );
			if ( $shop_page && $shop_page->post_excerpt ) {
				return wp_strip_all_tags( $shop_page->post_excerpt );
			}
		}

		if ( is_product_category() || is_product_tag() ) {
			return wp_strip_all_tags( term_description() );
		}

		return get_bloginfo( 'description' );
	}

	private function get_image() {
		if ( is_singular( 'product' ) ) {
			global $post;
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$image_id = $product->get_image_id();
				if ( $image_id ) {
					$src = wp_get_attachment_image_url( $image_id, 'souq-product-gallery' );
					if ( $src ) {
						return $src;
					}
				}
			}
		}

		if ( is_singular() && has_post_thumbnail() ) {
			$src = get_the_post_thumbnail_url( null, 'large' );
			if ( $src ) {
				return $src;
			}
		}

		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$src = wp_get_attachment_image_url( $custom_logo_id, 'full' );
			if ( $src ) {
				return $src;
			}
		}

		return '';
	}

	public function output_meta_description() {
		$description = trim( $this->get_description() );
		if ( '' === $description ) {
			return;
		}
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_trim_words( $description, 35 ) ) );
	}

	public function output_social_tags() {
		$title       = wp_get_document_title();
		$description = wp_trim_words( trim( $this->get_description() ), 35 );
		$image       = $this->get_image();
		$url         = is_singular() ? get_permalink() : home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );
		$type        = is_singular( 'product' ) ? 'product' : ( is_singular() ? 'article' : 'website' );
		?>
		<meta property="og:type" content="<?php echo esc_attr( $type ); ?>">
		<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
		<meta property="og:description" content="<?php echo esc_attr( $description ); ?>">
		<meta property="og:url" content="<?php echo esc_url( $url ); ?>">
		<meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<?php if ( $image ) : ?>
			<meta property="og:image" content="<?php echo esc_url( $image ); ?>">
		<?php endif; ?>
		<meta name="twitter:card" content="<?php echo esc_attr( $image ? 'summary_large_image' : 'summary' ); ?>">
		<meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>">
		<meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>">
		<?php if ( is_singular( 'product' ) ) : ?>
			<?php
			global $post;
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				printf( '<meta property="product:price:amount" content="%s">' . "\n", esc_attr( $product->get_price() ) );
				printf( '<meta property="product:price:currency" content="%s">' . "\n", esc_attr( get_woocommerce_currency() ) );
			}
			?>
		<?php endif; ?>
		<?php
	}

	public function output_website_schema() {
		if ( ! is_front_page() ) {
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term}&post_type=product' ),
				),
				'query-input' => 'required name=search_term',
			),
		);

		printf( '<script type="application/ld+json">%s</script>' . "\n", wp_json_encode( $schema ) );
	}
}
