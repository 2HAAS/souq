<?php
/**
 * Souq override of the variation add-to-cart button row.
 *
 * Quantity plus a two-button CTA pair. "Add to bag" is the primary action;
 * "Buy it now" is a real express-checkout submit that skips the cart page,
 * which is the expected shortcut in apparel retail.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;
?>
<div class="woocommerce-variation-add-to-cart variations_button souq-buybox">

	<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

	<div class="souq-buybox__row">
		<?php
		do_action( 'woocommerce_before_add_to_cart_quantity' );

		woocommerce_quantity_input(
			array(
				'min_value'   => $product->get_min_purchase_quantity(),
				'max_value'   => $product->get_max_purchase_quantity(),
				'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			)
		);

		do_action( 'woocommerce_after_add_to_cart_quantity' );
		?>

		<button type="submit" class="single_add_to_cart_button button alt souq-btn souq-btn--primary">
			<?php echo souq_icon( 'cart' ); ?>
			<span><?php echo esc_html( $product->single_add_to_cart_text() ); ?></span>
		</button>
	</div>

	<button
		type="submit"
		name="souq_buy_now"
		value="1"
		class="souq-btn souq-btn--secondary souq-btn--full souq-buybox__now"
	>
		<?php esc_html_e( 'Buy it now', 'souq' ); ?>
	</button>

	<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

	<input type="hidden" name="add-to-cart" value="<?php echo absint( $product->get_id() ); ?>" />
	<input type="hidden" name="product_id" value="<?php echo absint( $product->get_id() ); ?>" />
	<input type="hidden" name="variation_id" class="variation_id" value="0" />
</div>
