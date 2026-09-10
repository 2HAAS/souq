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

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_term_fields' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_color_picker' ) );
	}

	public function enqueue_color_picker( $hook ) {
		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'souq-admin-swatch', SOUQ_URI . '/assets/js/admin-swatch.js', array( 'wp-color-picker' ), SOUQ_VERSION, true );
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
