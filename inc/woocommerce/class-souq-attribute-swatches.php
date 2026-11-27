<?php
/**
 * Lets admins assign a swatch color to product-attribute terms (e.g. the
 * "Color" attribute's "Rust" / "Ivory" terms) from wp-admin. This is what
 * makes the variation "buttons" genuinely customizable rather than a fixed
 * lookup table (user requirement: SKU/variation buttons must be editable).
 *
 * An attribute renders as circular color swatches once ANY of its terms has
 * a swatch color assigned; otherwise it renders as the default text pill.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Attribute_Swatches {

	private static $instance = null;
	const META_KEY = 'souq_swatch_color';
	const PRODUCT_MAP_KEY = '_souq_variation_image_map';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_term_fields' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_color_picker' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_map' ) );
	}

	public function enqueue_color_picker( $hook ) {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		if ( in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			wp_enqueue_style( 'wp-color-picker' );
		}
		wp_enqueue_media();
		wp_enqueue_script( 'souq-admin-swatch', SOUQ_URI . '/assets/js/admin-swatch.js', array( 'jquery', 'wp-color-picker', 'media-upload', 'media-views' ), SOUQ_VERSION, true );
	}

	public function add_product_data_tab( $tabs ) {
		$tabs['souq_variation_images'] = array(
			'label'    => __( 'Souq Variation Images', 'souq' ),
			'target'   => 'souq_variation_images_panel',
			'class'    => array( 'show_if_variable' ),
			'priority' => 62,
		);
		return $tabs;
	}

	public function render_product_data_panel() {
		global $post;
		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}

		$product = wc_get_product( $post->ID );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			echo '<div id="souq_variation_images_panel" class="panel woocommerce_options_panel hidden"><p>' . esc_html__( 'This panel is available for variable products only.', 'souq' ) . '</p></div>';
			return;
		}

		$map = self::get_product_map( $post->ID );
		$allowed = self::get_product_gallery_ids( $product );
		$rows = array();

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof \WC_Product_Attribute || ! $attribute->is_taxonomy() ) {
				continue;
			}
			$taxonomy = $attribute->get_name();
			if ( ! self::taxonomy_uses_swatches( $taxonomy ) ) {
				continue;
			}
			$terms = wc_get_product_terms( $post->ID, $taxonomy, array( 'fields' => 'all' ) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$rows[] = array(
					'taxonomy' => $taxonomy,
					'slug'     => $term->slug,
					'label'    => $term->name,
					'color'    => self::get_term_color( $term->term_id ),
					'image_id' => isset( $map[ $taxonomy ][ $term->slug ] ) ? (int) $map[ $taxonomy ][ $term->slug ] : 0,
				);
			}
		}

		echo '<div id="souq_variation_images_panel" class="panel woocommerce_options_panel hidden">';
		echo '<div class="options_group">';
		wp_nonce_field( 'souq_save_variation_image_map', '_souq_variation_image_map_nonce' );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No swatch colors are assigned to this variable product yet. Assign a swatch color to a product attribute term first.', 'souq' ) . '</p>';
			echo '</div></div>';
			return;
		}

		echo '<p class="description">' . esc_html__( 'Choose one image from this product’s gallery for a swatch. Leave a swatch unmapped to keep the gallery unchanged.', 'souq' ) . '</p>';

		foreach ( $rows as $row ) {
			$input_name = 'souq_variation_image_map[' . esc_attr( $row['taxonomy'] ) . '][' . esc_attr( $row['slug'] ) . ']';
			$image_id = $row['image_id'];
			echo '<div class="souq-variation-image-row" style="display:grid;grid-template-columns:minmax(160px,.7fr) 96px minmax(160px,1fr);gap:12px;align-items:center;padding:12px 9px;border-bottom:1px solid #ddd;">';
			echo '<strong>' . esc_html( wc_attribute_label( $row['taxonomy'] ) . ' — ' . $row['label'] ) . '</strong>';
			echo '<div class="souq-variation-image-preview">';
			if ( $image_id && in_array( $image_id, $allowed, true ) ) {
				echo wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'style' => 'width:80px;height:80px;object-fit:cover;' ) );
			} else {
				echo '<span class="souq-variation-image-placeholder" style="display:flex;width:80px;height:80px;align-items:center;justify-content:center;background:#f0f0f1;color:#646970;">—</span>';
			}
			echo '</div>';
			echo '<p style="margin:0;"><input type="hidden" name="' . $input_name . '" value="' . ( $image_id ? (int) $image_id : '' ) . '" class="souq-variation-image-id">';
			echo '<button type="button" class="button souq-choose-variation-image" data-allowed="' . esc_attr( wp_json_encode( $allowed ) ) . '"' . disabled( empty( $allowed ), true, false ) . '>' . esc_html__( 'Choose image', 'souq' ) . '</button> ';
			if ( empty( $allowed ) ) {
				echo '<span class="description">' . esc_html__( 'Add product images first.', 'souq' ) . '</span> ';
			}
			echo '<button type="button" class="button souq-clear-variation-image" style="' . ( $image_id ? '' : 'display:none;' ) . '">' . esc_html__( 'Remove', 'souq' ) . '</button></p>';
			echo '</div>';
		}

		echo '</div></div>';
	}

	public function save_product_map( $product = null, $post = null ) {
		if ( ! $product instanceof \WC_Product ) {
			$product_id = $post instanceof \WP_Post ? $post->ID : absint( $product );
			$product    = $product_id ? wc_get_product( $product_id ) : false;
		}
		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'variable' ) ) {
			return;
		}
		$post_id = $product->get_id();
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['_souq_variation_image_map_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_souq_variation_image_map_nonce'] ) ), 'souq_save_variation_image_map' ) ) {
			return;
		}

		$allowed = self::get_product_gallery_ids( $product );
		$posted = isset( $_POST['souq_variation_image_map'] ) && is_array( $_POST['souq_variation_image_map'] )
			? wp_unslash( $_POST['souq_variation_image_map'] )
			: array();
		$map = array();

		foreach ( $posted as $taxonomy => $slugs ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( ! is_array( $slugs ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			foreach ( $slugs as $slug => $image_id ) {
				$image_id = absint( $image_id );
				if ( $image_id && in_array( $image_id, $allowed, true ) ) {
					$map[ $taxonomy ][ sanitize_title( $slug ) ] = $image_id;
				}
			}
		}

		if ( empty( $map ) ) {
			$product->delete_meta_data( self::PRODUCT_MAP_KEY );
		} else {
			$product->update_meta_data( self::PRODUCT_MAP_KEY, $map );
		}
	}

	public static function get_product_gallery_ids( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return array();
		}
		$ids = array();
		if ( $product->get_image_id() ) {
			$ids[] = (int) $product->get_image_id();
		}
		foreach ( (array) $product->get_gallery_image_ids() as $image_id ) {
			$ids[] = (int) $image_id;
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	public static function get_product_map( $product_id ) {
		$map = get_post_meta( $product_id, self::PRODUCT_MAP_KEY, true );
		return is_array( $map ) ? $map : array();
	}

	public function register_term_fields() {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}

		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			add_action( "{$taxonomy}_add_form_fields", array( $this, 'add_field' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'edit_field' ) );
			add_action( "created_{$taxonomy}", array( $this, 'save_field' ) );
			add_action( "edited_{$taxonomy}", array( $this, 'save_field' ) );
		}
	}

	public function add_field() {
		?>
		<div class="form-field">
			<label for="souq_swatch_color"><?php esc_html_e( 'Swatch Color', 'souq' ); ?></label>
			<input type="text" name="souq_swatch_color" id="souq_swatch_color" value="" class="souq-color-field" placeholder="#8b4a38">
			<p><?php esc_html_e( 'Optional. Set this for any term to make the whole attribute render as color swatches instead of text pills on the product page.', 'souq' ); ?></p>
		</div>
		<?php
	}

	public function edit_field( $term ) {
		$value = get_term_meta( $term->term_id, self::META_KEY, true );
		?>
		<tr class="form-field">
			<th scope="row"><label for="souq_swatch_color"><?php esc_html_e( 'Swatch Color', 'souq' ); ?></label></th>
			<td>
				<input type="text" name="souq_swatch_color" id="souq_swatch_color" value="<?php echo esc_attr( $value ); ?>" class="souq-color-field" placeholder="#8b4a38">
				<p class="description"><?php esc_html_e( 'Optional. Set this for any term to make the whole attribute render as color swatches instead of text pills on the product page.', 'souq' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public function save_field( $term_id ) {
		if ( ! isset( $_POST['souq_swatch_color'] ) ) {
			return;
		}
		$value = sanitize_hex_color( wp_unslash( $_POST['souq_swatch_color'] ) );
		if ( $value ) {
			update_term_meta( $term_id, self::META_KEY, $value );
		} else {
			delete_term_meta( $term_id, self::META_KEY );
		}
	}

	public static function get_term_color( $term_id ) {
		return get_term_meta( $term_id, self::META_KEY, true );
	}

	/**
	 * Returns the first attribute taxonomy on this product that renders as
	 * swatches, or '' when none does. Used by the loop card to decide whether
	 * to show colour chips without hardcoding a "pa_color" assumption.
	 */
	public static function get_swatch_taxonomy_for( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof \WC_Product_Attribute || ! $attribute->is_taxonomy() ) {
				continue;
			}
			$taxonomy = $attribute->get_name();
			if ( self::taxonomy_uses_swatches( $taxonomy ) ) {
				return $taxonomy;
			}
		}

		return '';
	}

	/**
	 * Whether $taxonomy (e.g. "pa_color") should render as swatches: true
	 * once at least one of its terms has a swatch color assigned.
	 */
	public static function taxonomy_uses_swatches( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return false;
		}

		foreach ( $terms as $term_id ) {
			$color = get_term_meta( $term_id, self::META_KEY, true );
			if ( '' !== $color ) {
				return true;
			}
		}

		return false;
	}
}
