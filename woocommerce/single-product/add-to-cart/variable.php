<?php
/**
 * Souq override of WooCommerce's variable-product add-to-cart template.
 *
 * Replaces the default <select> table with the apparel convention:
 * rectangular size buttons and square colour chips, each labelled with the
 * currently chosen value.
 *
 * Every attribute keeps its real, visually-hidden native <select> (rendered
 * inside a wrapper carrying WooCommerce's expected `.variations` class), so
 * wc-add-to-cart-variation.js still owns price, stock, gallery sync and
 * combination validity. Our buttons only set that select's value and fire a
 * change event (PRODUCT.md principle 4).
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

$attribute_keys  = array_keys( $attributes );
$variations_json = wp_json_encode( $available_variations );
$variations_attr = function_exists( 'wc_esc_json' ) ? wc_esc_json( $variations_json ) : _wp_specialchars( $variations_json, ENT_QUOTES, 'UTF-8', true );

do_action( 'woocommerce_before_add_to_cart_form' );
?>

<form class="variations_form cart souq-variations" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo absint( $product->get_id() ); ?>" data-product_variations="<?php echo $variations_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
	<?php do_action( 'woocommerce_before_variations_form' ); ?>

	<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
		<p class="stock out-of-stock"><?php echo esc_html( apply_filters( 'woocommerce_out_of_stock_message', __( 'This product is currently out of stock and unavailable.', 'woocommerce' ) ) ); ?></p>
	<?php else : ?>

		<div class="variations">
			<?php foreach ( $attributes as $attribute_name => $options ) : ?>
				<?php
				$attribute_id   = sanitize_title( $attribute_name );
				$select_id      = 'souq-select-' . $attribute_id;
				$is_taxonomy    = taxonomy_exists( $attribute_name );
				$use_swatches   = $is_taxonomy && class_exists( '\Souq\Attribute_Swatches' ) && \Souq\Attribute_Swatches::taxonomy_uses_swatches( $attribute_name );
				$selected_value = isset( $_REQUEST[ 'attribute_' . $attribute_id ] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					? wc_clean( wp_unslash( $_REQUEST[ 'attribute_' . $attribute_id ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					: $product->get_variation_default_attribute( $attribute_name );

				// Coarse first-paint availability: does this value appear in any
				// in-stock variation at all. WooCommerce's own JS refines this
				// per-combination as soon as another attribute is chosen.
				$available_values = array();
				foreach ( $available_variations as $variation ) {
					$key = 'attribute_' . $attribute_id;
					if ( isset( $variation['attributes'][ $key ] ) ) {
						$available_values[] = $variation['attributes'][ $key ];
					}
				}

				// $options arrives in term-name order. Global attributes are
				// re-read through wc_get_product_terms() so the buttons follow
				// the order set under Products > Attributes (as WooCommerce's
				// own dropdown does); custom attributes keep their typed order.
				$choices = array();
				if ( $is_taxonomy ) {
					$terms = wc_get_product_terms( $product->get_id(), $attribute_name, array( 'fields' => 'all' ) );
					foreach ( $terms as $term ) {
						if ( in_array( $term->slug, $options, true ) ) {
							$choices[] = array(
								'value' => $term->slug,
								'label' => $term->name,
								'term'  => $term,
							);
						}
					}
				} else {
					foreach ( $options as $option ) {
						$choices[] = array(
							'value' => $option,
							'label' => apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute_name, $product ),
							'term'  => null,
						);
					}
				}
				?>
				<div class="souq-attr souq-variation-row" data-attribute="<?php echo esc_attr( $attribute_id ); ?>">

					<div class="souq-attr__head">
						<span class="souq-attr__label">
							<?php echo wp_kses_post( wc_attribute_label( $attribute_name ) ); ?>
							<span class="souq-attr__value" data-souq-selected-label></span>
						</span>
					</div>

					<div class="<?php echo $use_swatches ? 'souq-swatches' : 'souq-sizes'; ?>">
						<?php foreach ( $choices as $choice ) : ?>
							<?php
							$option_slug  = $choice['value'];
							$term         = $choice['term'];
							$label        = $choice['label'];
							$is_selected  = ( (string) $selected_value === (string) $option_slug );
							$is_available = empty( $available_variations )
								|| in_array( $option_slug, $available_values, true )
								|| in_array( '', $available_values, true );
							$swatch_color = ( $use_swatches && $term ) ? \Souq\Attribute_Swatches::get_term_color( $term->term_id ) : '';
							?>
							<button
								type="button"
								class="<?php echo $use_swatches ? 'souq-swatch' : 'souq-size'; ?>"
								data-value="<?php echo esc_attr( $option_slug ); ?>"
								data-available="<?php echo $is_available ? 'true' : 'false'; ?>"
								data-label="<?php echo esc_attr( $label ); ?>"
								aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
								aria-label="<?php echo esc_attr( $is_available ? $label : $label . ' — ' . __( 'unavailable', 'souq' ) ); ?>"
								<?php if ( $swatch_color ) : ?>style="--souq-swatch-color: <?php echo esc_attr( $swatch_color ); ?>"<?php endif; ?>
							>
								<?php if ( $use_swatches ) : ?>
									<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
								<?php else : ?>
									<?php echo esc_html( $label ); ?>
								<?php endif; ?>
							</button>
						<?php endforeach; ?>
					</div>

					<?php
					echo '<select id="' . esc_attr( $select_id ) . '" class="souq-visually-hidden" name="attribute_' . esc_attr( $attribute_id ) . '" data-attribute_name="attribute_' . esc_attr( $attribute_id ) . '" tabindex="-1" aria-hidden="true">';
					echo '<option value="">' . esc_html__( 'Choose an option', 'woocommerce' ) . '</option>';
					foreach ( $choices as $choice ) {
						printf(
							'<option value="%1$s" %2$s>%3$s</option>',
							esc_attr( $choice['value'] ),
							selected( $selected_value, $choice['value'], false ),
							esc_html( $choice['label'] )
						);
					}
					echo '</select>';
					?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="souq-variations__foot">
			<?php echo wp_kses_post( apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations souq-btn--ghost" href="#">' . esc_html__( 'Clear selection', 'woocommerce' ) . '</a>' ) ); ?>
		</div>

		<div class="reset_variations_alert screen-reader-text" role="alert" aria-live="polite" aria-relevant="all"></div>

		<?php do_action( 'woocommerce_after_variations_table' ); ?>

		<div class="single_variation_wrap">
			<?php
			do_action( 'woocommerce_before_single_variation' );
			do_action( 'woocommerce_single_variation' );
			do_action( 'woocommerce_after_single_variation' );
			?>
		</div>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_variations_form' ); ?>
</form>

<?php
do_action( 'woocommerce_after_add_to_cart_form' );
