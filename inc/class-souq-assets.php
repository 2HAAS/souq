<?php
/**
 * Conditional CSS/JS enqueue. Only the sitewide base loads everywhere;
 * page-specific sheets load on the templates that use them, so a shopper
 * never downloads product-page CSS to read the homepage.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	private function style( $handle, $file, $deps ) {
		$path = SOUQ_DIR . '/assets/css/' . $file;
		wp_enqueue_style(
			$handle,
			SOUQ_URI . '/assets/css/' . $file,
			$deps,
			file_exists( $path ) ? (string) filemtime( $path ) : SOUQ_VERSION
		);
	}

	public function enqueue() {
		// style.css carries the theme header and the design tokens.
		wp_enqueue_style( 'souq-tokens', get_stylesheet_uri(), array(), SOUQ_VERSION );

		$this->style( 'souq-base', 'base.css', array( 'souq-tokens' ) );
		$this->style( 'souq-layout', 'layout.css', array( 'souq-base' ) );
		$this->style( 'souq-components', 'components.css', array( 'souq-base' ) );
		$this->style( 'souq-header-footer', 'header-footer.css', array( 'souq-components' ) );
		$this->style( 'souq-search', 'search.css', array( 'souq-components' ) );

		$is_woo = function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() );
		$is_product = function_exists( 'is_product' ) && is_product();

		// The card appears on the homepage, every shop surface and inside PDP
		// recommendations, so it loads alongside any of them.
		$needs_card = $is_woo || is_front_page() || is_home() || is_search();

		if ( $needs_card ) {
			$this->style( 'souq-product-card', 'product-card.css', array( 'souq-components' ) );
		}

		// One script drives both the card slider and the product gallery.
		if ( $needs_card || $is_product ) {
			wp_enqueue_script(
				'souq-gallery',
				SOUQ_URI . '/assets/js/gallery.js',
				array(),
				SOUQ_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}

		if ( $is_woo ) {
			$this->style( 'souq-woocommerce', 'woocommerce.css', array( 'souq-components' ) );
		}

		// Cart, checkout, order-received and account are a distinct surface
		// from the catalogue, and is_woocommerce() is false on all of them.
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			$this->style( 'souq-woocommerce', 'woocommerce.css', array( 'souq-components' ) );
			$this->style( 'souq-commerce', 'commerce.css', array( 'souq-woocommerce' ) );
		}

		if ( is_front_page() ) {
			$this->style( 'souq-homepage', 'homepage.css', array( 'souq-layout' ) );
		}

		if ( $is_product ) {
			$this->style( 'souq-product', 'product.css', array( 'souq-components' ) );
		}

		wp_enqueue_script(
			'souq-main',
			SOUQ_URI . '/assets/js/main.js',
			array(),
			SOUQ_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_localize_script(
			'souq-main',
			'souqConfig',
			array( 'restUrl' => esc_url_raw( rest_url( 'souq/v1/' ) ) )
		);

		wp_enqueue_script(
			'souq-ajax-search',
			SOUQ_URI . '/assets/js/ajax-search.js',
			array(),
			SOUQ_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_localize_script(
			'souq-ajax-search',
			'souqSearch',
			array(
				'endpoint' => rest_url( 'souq/v1/search' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'minChars' => 2,
				'debounce' => 250,
				'strings'  => array(
					'noResults' => __( 'No products found. Try a different search.', 'souq' ),
					'viewAll'   => __( 'View all results', 'souq' ),
					'loading'   => __( 'Searching…', 'souq' ),
				),
			)
		);

		if ( $is_product ) {
			wp_enqueue_script(
				'souq-product',
				SOUQ_URI . '/assets/js/product.js',
				array( 'jquery' ),
				SOUQ_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}
	}
}
