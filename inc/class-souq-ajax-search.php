<?php
/**
 * Predictive product search: REST route consumed by assets/js/ajax-search.js.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ajax_Search {

	private static $instance = null;
	const MAX_RESULTS = 6;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	public function register_route() {
		register_rest_route(
			'souq/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'query' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function handle( \WP_REST_Request $request ) {
		$query = trim( (string) $request->get_param( 'query' ) );

		if ( '' === $query || ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array( 'products' => array() ) );
		}

		$wp_query = new \WP_Query(
			array(
				's'                   => $query,
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'posts_per_page'      => self::MAX_RESULTS,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$products = array();

		foreach ( $wp_query->posts as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}

			$terms    = get_the_terms( $post->ID, 'product_cat' );
			$category = ( is_array( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : '';

			$products[] = array(
				'id'          => $product->get_id(),
				'name'        => $product->get_name(),
				'url'         => get_permalink( $product->get_id() ),
				'thumbnail'   => self::get_thumbnail( $product ),
				'price_html'  => wp_strip_all_tags( $product->get_price_html() ),
				'category'    => $category,
			);
		}

		wp_reset_postdata();

		return rest_ensure_response( array( 'products' => $products ) );
	}

	private static function get_thumbnail( $product ) {
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$src = wp_get_attachment_image_url( $image_id, 'souq-search-thumb' );
			if ( $src ) {
				return $src;
			}
		}
		return wc_placeholder_img_src( 'souq-search-thumb' );
	}
}
