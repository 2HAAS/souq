<?php
/**
 * Product information accordion: the apparel-retail disclosure pattern.
 *
 * Description is open by default; everything else is collapsed. Sections with
 * no content are omitted entirely rather than opening onto an empty panel.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

if ( ! $product instanceof WC_Product ) {
	return;
}

$sections = array();

$description = $product->get_description();
if ( $description ) {
	$sections['description'] = array(
		'label'   => __( 'Description', 'souq' ),
		'open'    => true,
		'content' => '<div class="souq-prose">' . wp_kses_post( wpautop( $description ) ) . '</div>',
	);
}

// Product details: the attributes a shopper judges fabric and fit by, plus
// the SKU. Built from WooCommerce's own attribute data so it stays accurate.
$detail_rows = array();

foreach ( $product->get_attributes() as $attribute ) {
	if ( ! $attribute instanceof WC_Product_Attribute || ! $attribute->get_visible() ) {
		continue;
	}
	// Variation attributes are already choosable above; repeating them here
	// as static rows would just be noise.
	if ( $attribute->get_variation() ) {
		continue;
	}

	$name   = wc_attribute_label( $attribute->get_name() );
	$values = $attribute->is_taxonomy()
		? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) )
		: $attribute->get_options();

	if ( ! empty( $values ) ) {
		$detail_rows[ $name ] = implode( ', ', $values );
	}
}

$sku = $product->get_sku();
if ( $sku ) {
	$detail_rows[ __( 'SKU', 'souq' ) ] = $sku;
}

if ( ! empty( $detail_rows ) ) {
	$rows = '';
	foreach ( $detail_rows as $label => $value ) {
		$rows .= sprintf(
			'<div class="souq-spec"><dt class="souq-spec__label">%s</dt><dd class="souq-spec__value" data-souq-spec="%s">%s</dd></div>',
			esc_html( $label ),
			esc_attr( sanitize_title( $label ) ),
			esc_html( $value )
		);
	}

	$sections['details'] = array(
		'label'   => __( 'Product details', 'souq' ),
		'open'    => false,
		'content' => '<dl class="souq-specs">' . $rows . '</dl>',
	);
}

// Size guide, rendered inline rather than in a modal: a shopper comparing
// their measurements wants the table and the size buttons on screen together.
$chart = class_exists( '\Souq\Size_Charts' ) ? \Souq\Size_Charts::get_chart_for_product( $product->get_id() ) : null;

if ( $chart ) {
	ob_start();
	foreach ( $chart['sections'] as $section ) {
		echo '<div class="souq-measure-group">';
		if ( ! empty( $section['title'] ) ) {
			printf( '<p class="souq-measure-group__title">%s</p>', esc_html( $section['title'] ) );
		}
		echo '<div class="souq-measure-scroll"><table class="souq-measure-table"><thead><tr>';
		foreach ( $section['columns'] as $column ) {
			printf( '<th scope="col">%s</th>', esc_html( $column ) );
		}
		echo '</tr></thead><tbody>';
		foreach ( $section['rows'] as $row ) {
			echo '<tr>';
			foreach ( $row as $index => $cell ) {
				if ( 0 === $index ) {
					printf( '<th scope="row">%s</th>', esc_html( $cell ) );
				} else {
					printf( '<td>%s</td>', esc_html( $cell ) );
				}
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '</div>';
	}
	printf(
		'<p class="souq-measure-note">%s</p>',
		esc_html__( 'Measurements in inches, garment laid flat. Between sizes, we suggest sizing up.', 'souq' )
	);

	$sections['size-guide'] = array(
		'label'   => __( 'Size guide', 'souq' ),
		'open'    => false,
		'content' => ob_get_clean(),
	);
}

/**
 * Care instructions. Filterable so a shop can supply per-product care copy
 * without editing the template.
 *
 * @param string     $care    Care copy.
 * @param WC_Product $product Current product.
 */
$care = apply_filters(
	'souq_product_care_instructions',
	__( 'Gentle machine wash cold with like colours. Do not bleach. Dry in shade. Warm iron on reverse.', 'souq' ),
	$product
);

if ( $care ) {
	$sections['care'] = array(
		'label'   => __( 'Care', 'souq' ),
		'open'    => false,
		'content' => '<div class="souq-prose"><p>' . esc_html( $care ) . '</p></div>',
	);
}

if ( empty( $sections ) ) {
	return;
}
?>
<div class="souq-accordion" data-souq-accordion>
	<?php foreach ( $sections as $key => $section ) : ?>
		<?php $panel_id = 'souq-panel-' . sanitize_html_class( $key ); ?>
		<div class="souq-accordion__item">
			<h2 class="souq-accordion__heading">
				<button
					type="button"
					class="souq-accordion__trigger"
					aria-expanded="<?php echo $section['open'] ? 'true' : 'false'; ?>"
					aria-controls="<?php echo esc_attr( $panel_id ); ?>"
				>
					<span><?php echo esc_html( $section['label'] ); ?></span>
					<span class="souq-accordion__icon"><?php echo souq_icon( 'chevron-down' ); ?></span>
				</button>
			</h2>
			<div class="souq-accordion__panel" id="<?php echo esc_attr( $panel_id ); ?>" <?php echo $section['open'] ? '' : 'hidden'; ?>>
				<?php echo $section['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
