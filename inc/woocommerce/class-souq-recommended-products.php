<?php
/**
 * Recommended Products.
 *
 * Two independent, complementary mechanisms, both feeding the same display
 * pipeline (see get_for_display()):
 *
 * 1. Per-product CURATION (`_souq_recommended_products`): "on THIS product's
 *    page, also suggest THESE specific other products." A deliberate,
 *    hand-picked list, always shown first.
 *
 * 2. Sitewide POOL MEMBERSHIP (`_souq_is_recommended`): a plain yes/no on
 *    every product, "should this product be eligible to be auto-suggested
 *    anywhere" (a PDP with no curation of its own, or a "Recommended
 *    products" grid on the homepage). Opt-in and off by default, so an
 *    empty pool never silently starts pulling every product in the catalog.
 *
 * Distinct from WooCommerce's native Upsells/Cross-sells (PRODUCT.md
 * principle 2 — a recommended product must never appear twice in the same
 * page view). That principle now spans more than one surface (a product
 * page's own recommendation list, and a homepage grid block both drawing on
 * the pool), so this class also tracks which product IDs have already been
 * shown once during the current request and excludes them from showing
 * again — see mark_shown() / get_shown_ids().
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Recommended_Products {

	private static $instance = null;
	const META_KEY      = '_souq_recommended_products';
	const FLAG_META_KEY = '_souq_is_recommended';

	/** @var int[] Product IDs already rendered by get_for_display() or the
	 *  Product Grid block's "recommended" source during this request. */
	private static $shown_ids = array();

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_product', array( $this, 'save' ) );
	}

	public function add_meta_box() {
		add_meta_box(
			'souq_recommended_products',
			__( 'Recommended Products', 'souq' ),
			array( $this, 'render' ),
			'product',
			'normal',
			'default'
		);
	}

	public function render( $post ) {
		wp_nonce_field( 'souq_save_recommended', 'souq_recommended_nonce' );

		$ids     = get_post_meta( $post->ID, self::META_KEY, true );
		$ids     = is_array( $ids ) ? $ids : array();
		$flagged = 'yes' === get_post_meta( $post->ID, self::FLAG_META_KEY, true );
		?>
		<p>
			<label>
				<input type="checkbox" name="souq_is_recommended" value="yes" <?php checked( $flagged ); ?>>
				<?php esc_html_e( 'Include this product in the sitewide recommended pool', 'souq' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Feeds two places: it can fill out another product’s recommendation list when that product has no (or too few) picks of its own below, and it is what a “Recommended” Product Grid block — e.g. on the homepage — shows. Off by default; nothing is auto-suggested until you opt products in.', 'souq' ); ?>
		</p>

		<hr>

		<p><?php esc_html_e( 'Recommend specific products on this product\'s own page, shown as an expandable list beneath its description. Takes priority over the sitewide pool above. If a shopper opens one of these products elsewhere on the page, it hides there automatically rather than appearing twice.', 'souq' ); ?></p>
		<select
			class="wc-product-search"
			multiple="multiple"
			style="width:100%;"
			name="souq_recommended_products[]"
			data-placeholder="<?php esc_attr_e( 'Search for products…', 'souq' ); ?>"
			data-action="woocommerce_json_search_products_and_variations"
			data-exclude="<?php echo esc_attr( $post->ID ); ?>"
		>
			<?php
			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					printf( '<option value="%1$d" selected="selected">%2$s</option>', $id, esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) );
				}
			}
			?>
		</select>
		<?php
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST['souq_recommended_nonce'] ) || ! wp_verify_nonce( $_POST['souq_recommended_nonce'], 'souq_save_recommended' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$ids = isset( $_POST['souq_recommended_products'] ) ? array_map( 'absint', (array) $_POST['souq_recommended_products'] ) : array();
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		update_post_meta( $post_id, self::META_KEY, $ids );

		$flagged = isset( $_POST['souq_is_recommended'] ) && 'yes' === $_POST['souq_is_recommended'];
		if ( $flagged ) {
			update_post_meta( $post_id, self::FLAG_META_KEY, 'yes' );
		} else {
			delete_post_meta( $post_id, self::FLAG_META_KEY );
		}
	}

	/**
	 * Returns published WC_Product objects recommended for $product_id,
	 * always excluding the product itself even if an admin mistakenly added it.
	 */
	public static function get_for( $product_id ) {
		$ids = get_post_meta( $product_id, self::META_KEY, true );
		$ids = is_array( $ids ) ? $ids : array();
		$ids = array_diff( array_unique( array_map( 'absint', $ids ) ), array( (int) $product_id ) );

		$products = array();
		foreach ( $ids as $id ) {
			if ( 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( $product ) {
				$products[] = $product;
			}
		}

		return $products;
	}

	/**
	 * Product IDs opted into the sitewide pool (`_souq_is_recommended`),
	 * excluding anything in $exclude and anything already shown elsewhere on
	 * this request (see get_shown_ids()) so the same item can't turn up
	 * twice across, say, a product page's own list and a homepage grid.
	 *
	 * @return int[]
	 */
	public static function get_pool_ids( $exclude = array(), $limit = -1 ) {
		$exclude = array_unique( array_merge( array_map( 'absint', $exclude ), self::$shown_ids ) );

		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'rand',
				'fields'         => 'ids',
				'post__not_in'   => $exclude,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::FLAG_META_KEY,
						'value' => 'yes',
					),
				),
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Marks product IDs as shown during this request, so a later call to
	 * get_for_display() or the Product Grid block's "recommended" source
	 * won't pick them again on the same page view.
	 */
	public static function mark_shown( array $ids ) {
		self::$shown_ids = array_unique( array_merge( self::$shown_ids, array_map( 'absint', $ids ) ) );
	}

	/**
	 * @return int[] Product IDs already shown this request.
	 */
	public static function get_shown_ids() {
		return self::$shown_ids;
	}

	/**
	 * Curated picks first, then the sitewide pool, then same-category
	 * published products, each step only filling what the previous one
	 * didn't — so the section is never sparse, or entirely missing, on a
	 * product nobody has curated recommendations for. Controlled by the
	 * Customizer's "Fill with same-category products" toggle for the last
	 * step; pass $allow_category_fallback = false to skip straight from the
	 * pool to nothing.
	 *
	 * @return WC_Product[]
	 */
	public static function get_for_display( $product_id, $count, $allow_category_fallback = true ) {
		$products = self::get_for( $product_id );
		$products = array_slice( $products, 0, $count );

		$exclude_ids = array( (int) $product_id );
		foreach ( $products as $existing ) {
			$exclude_ids[] = $existing->get_id();
		}

		$remaining = $count - count( $products );

		if ( $remaining > 0 ) {
			$pool_ids = self::get_pool_ids( $exclude_ids, $remaining );
			foreach ( $pool_ids as $pool_id ) {
				$pool_product = wc_get_product( $pool_id );
				if ( $pool_product ) {
					$products[]    = $pool_product;
					$exclude_ids[] = $pool_id;
				}
			}
			$remaining = $count - count( $products );
		}

		if ( $remaining > 0 && $allow_category_fallback ) {
			$term_ids = wc_get_product_term_ids( $product_id, 'product_cat' );

			if ( ! empty( $term_ids ) ) {
				$fallback_ids = get_posts(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => $remaining,
						'orderby'        => 'rand',
						'fields'         => 'ids',
						'post__not_in'   => array_merge( $exclude_ids, self::$shown_ids ),
						'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => 'product_cat',
								'field'    => 'term_id',
								'terms'    => $term_ids,
							),
						),
					)
				);

				foreach ( $fallback_ids as $fallback_id ) {
					$fallback_product = wc_get_product( $fallback_id );
					if ( $fallback_product ) {
						$products[] = $fallback_product;
					}
				}
			}
		}

		$shown_ids = array();
		foreach ( $products as $shown_product ) {
			$shown_ids[] = $shown_product->get_id();
		}
		self::mark_shown( $shown_ids );

		return $products;
	}
}
