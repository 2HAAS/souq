<?php
/**
 * "Product Grid" block.
 *
 * Server-rendered so it reuses woocommerce/content-product.php and therefore
 * looks identical to every other product grid on the site. Replaces the
 * WooCommerce Product Collection block here for two reasons: that block
 * ships its own card markup that has to be fought with overrides, and its
 * interactivity runtime issues a loopback request that a single-threaded
 * dev server cannot answer.
 *
 * Editable natively in the block editor: heading, source, category, count
 * and column count are block attributes.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Product_Grid_Block {

	private static $instance = null;
	const NAME = 'souq/product-grid';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			self::NAME,
			array(
				'api_version'     => 3,
				'title'           => __( 'Souq Product Grid', 'souq' ),
				'category'        => 'woocommerce',
				'icon'            => 'grid-view',
				'description'     => __( 'A grid of products using the storefront product card.', 'souq' ),
				'attributes'      => array(
					'heading'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'source'   => array(
						'type'    => 'string',
						'default' => 'recent',
					),
					'category' => array(
						'type'    => 'string',
						'default' => '',
					),
					'count'    => array(
						'type'    => 'number',
						'default' => 4,
					),
					'columns'  => array(
						'type'    => 'number',
						'default' => 0,
					),
					'showViewAll' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'supports'        => array(
					'align' => array( 'wide', 'full' ),
					'html'  => false,
				),
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Builds the query from the block's attributes.
	 */
	private function query_args( $attrs ) {
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 24, (int) $attrs['count'] ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'tax_query'           => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

		// Respect catalogue visibility so hidden products never leak into a grid.
		$args['tax_query'][] = array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => array( 'exclude-from-catalog' ),
			'operator' => 'NOT IN',
		);

		if ( ! empty( $attrs['category'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => sanitize_title( $attrs['category'] ),
			);
		}

		switch ( $attrs['source'] ) {
			case 'featured':
				$args['tax_query'][] = array(
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'featured',
				);
				break;

			case 'sale':
				$args['post__in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
				break;

			case 'best_selling':
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;

			case 'recommended':
				// Products opted into the sitewide recommended pool (see
				// class-souq-recommended-products.php), minus anything a
				// recommendation section elsewhere on this same page load has
				// already shown — the "never show a product twice in one
				// view" principle now spans both surfaces, not just the PDP
				// accordion instances it originally covered.
				$args['post__not_in'] = array_merge(
					$args['post__not_in'] ?? array(),
					class_exists( '\Souq\Recommended_Products' ) ? \Souq\Recommended_Products::get_shown_ids() : array()
				);
				$args['meta_query']   = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_souq_is_recommended',
						'value' => 'yes',
					),
				);
				$args['orderby'] = 'rand';
				break;

			case 'recent':
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
		}

		return $args;
	}

	public function render( $attrs, $content, $block ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$attrs = wp_parse_args(
			$attrs,
			array(
				'heading'     => '',
				'source'      => 'recent',
				'category'    => '',
				'count'       => 4,
				'columns'     => 0,
				'showViewAll' => false,
			)
		);

		$query = new \WP_Query( $this->query_args( $attrs ) );

		if ( 'recommended' === $attrs['source'] && $query->have_posts() && class_exists( '\Souq\Recommended_Products' ) ) {
			\Souq\Recommended_Products::mark_shown( wp_list_pluck( $query->posts, 'ID' ) );
		}

		if ( ! $query->have_posts() ) {
			// An empty grid is never rendered to shoppers; editors get told why.
			if ( ! current_user_can( 'edit_theme_options' ) ) {
				return '';
			}
			return sprintf(
				'<div class="souq-container souq-section"><p class="souq-grid-empty">%s</p></div>',
				esc_html__( 'Product Grid: no products match this block’s settings yet.', 'souq' )
			);
		}

		$columns = (int) $attrs['columns'];
		$style   = $columns > 0 ? sprintf( ' style="--souq-grid-columns:%d"', $columns ) : '';

		$view_all = '';
		if ( ! empty( $attrs['showViewAll'] ) ) {
			$url = ! empty( $attrs['category'] )
				? get_term_link( sanitize_title( $attrs['category'] ), 'product_cat' )
				: wc_get_page_permalink( 'shop' );

			if ( ! is_wp_error( $url ) ) {
				$view_all = sprintf(
					'<a class="souq-view-all" href="%s">%s</a>',
					esc_url( $url ),
					esc_html__( 'View all', 'souq' )
				);
			}
		}

		ob_start();

		echo '<section class="souq-section souq-product-section">';
		echo '<div class="souq-container">';

		if ( '' !== $attrs['heading'] || '' !== $view_all ) {
			echo '<div class="souq-product-section__head">';
			if ( '' !== $attrs['heading'] ) {
				printf( '<h2 class="souq-section-heading">%s</h2>', esc_html( $attrs['heading'] ) );
			}
			echo $view_all; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
		}

		printf( '<ul class="souq-grid products"%s>', $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		global $product, $post;
		$original_post = $post;

		// This section already owns an h2 when it has a heading, so cards drop
		// to h3 to keep the outline contiguous; without one they stay at h2.
		$previous_level = souq_card_heading_level();
		souq_card_heading_level( '' !== $attrs['heading'] ? 3 : 2 );

		while ( $query->have_posts() ) {
			$query->the_post();
			$product = wc_get_product( get_the_ID() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wc_get_template_part( 'content', 'product' );
		}

		souq_card_heading_level( $previous_level );

		$post = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_reset_postdata();

		echo '</ul>';
		echo '</div>';
		echo '</section>';

		return ob_get_clean();
	}

	public function enqueue_editor_assets() {
		wp_enqueue_script(
			'souq-product-grid-block',
			SOUQ_URI . '/assets/js/blocks/product-grid.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			SOUQ_VERSION,
			true
		);

		$categories = array( array( 'label' => __( 'All categories', 'souq' ), 'value' => '' ) );
		$terms      = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = array(
					'label' => $term->name,
					'value' => $term->slug,
				);
			}
		}

		wp_localize_script( 'souq-product-grid-block', 'souqProductGridData', array( 'categories' => $categories ) );
	}
}
