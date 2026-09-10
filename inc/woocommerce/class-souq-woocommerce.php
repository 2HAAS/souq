<?php
/**
 * WooCommerce integration: layout wrapper, cart fragments, and disabling the
 * native "related products" / upsell blocks in favor of the custom
 * Recommended Products accordion (see class-souq-recommended-products.php)
 * so a product is never offered twice on the same page.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WooCommerce {

	private static $instance = null;

	/** @see toolbar_start() — whether the row has anything to show at all. */
	private $show_result_count = true;
	private $show_sorting      = true;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( ! class_exists( '\WooCommerce' ) ) {
			return;
		}

		// Replace WooCommerce's default content wrapper with our own markup
		// so every WC-rendered page (shop, cart, checkout, account) sits
		// inside the same .souq-container rhythm as custom templates.
		remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
		remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
		add_action( 'woocommerce_before_main_content', array( $this, 'content_wrapper_start' ), 10 );
		add_action( 'woocommerce_after_main_content', array( $this, 'content_wrapper_end' ), 10 );

		// One recommendation surface per product page: our custom accordion
		// replaces WooCommerce's native related-products and upsell blocks.
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );

		// Ratings/reviews have no defined UX yet (out of scope this pass);
		// drop the hook rather than ship a half-styled empty stars row.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );

		// The summary column is rebuilt for apparel: short description and
		// meta are folded into the accordion, so their default placements go.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );

		// Tabs are replaced by the in-panel accordion.
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );

		add_action( 'woocommerce_single_product_summary', array( $this, 'render_details_accordion' ), 45 );

		// The recommendations section beneath the product page — Customizer-
		// controlled (souq_show_recommendations and siblings, in the
		// Recommendations section). Fires after .souq-product__layout closes,
		// so it appears below the gallery/panel, not inside either column.
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_recommendations' ), 5 );

		// "Buy it now" is an express checkout: add to cart, then go straight
		// to checkout instead of the cart page.
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'buy_now_redirect' ) );

		add_action( 'template_redirect', array( $this, 'maybe_drop_product_breadcrumb' ) );

		// Breadcrumbs are a Customizer toggle (souq_archive_show_breadcrumbs);
		// the product page always drops its own regardless, handled above.
		if ( ! get_theme_mod( 'souq_archive_show_breadcrumbs', true ) ) {
			remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		}

		// Result count and sorting dropdown are independent Customizer toggles
		// (souq_archive_show_result_count / souq_archive_show_sorting) — either
		// can be hidden without the other. toolbar_start()/toolbar_end() below
		// only print the wrapping row at all if at least one of them will.
		$this->show_result_count = get_theme_mod( 'souq_archive_show_result_count', true );
		$this->show_sorting      = get_theme_mod( 'souq_archive_show_sorting', true );

		if ( ! $this->show_result_count ) {
			remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
		}
		if ( ! $this->show_sorting ) {
			remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
		}

		// The archive page heading ("Shop", or a category name) is a
		// Customizer alignment toggle (souq_archive_heading_align) — applied
		// as a body class since WooCommerce's own archive-product.php template
		// renders `.woocommerce-products-header` with no class filter to hook.
		if ( 'center' === get_theme_mod( 'souq_archive_heading_align', 'left' ) ) {
			add_filter( 'body_class', array( $this, 'add_centered_heading_body_class' ) );
		}

		// WooCommerce's "Enable debug mode" shipping setting prints a raw
		// "Customer matched zone ..." notice on any normal page load that
		// computes shipping (WC_Shipping::load_shipping_methods() adds it
		// with no capability check at all) — every shopper sees it, not just
		// admins. It's a setup-time diagnostic, not something a live store
		// should ever show a customer.
		//
		// This intercepts that one notice by content rather than forcing the
		// `woocommerce_shipping_debug_mode` option's value: an earlier
		// version filtered `get_option()` directly, which also corrupted
		// every *other* reader of that option outside wp-admin — WP-CLI,
		// migration scripts, anything checking the real setting — silently
		// showing them "off" while the row in the database still said "yes".
		// Filtering the notice text leaves the option's actual value alone
		// everywhere; only this one message is ever touched, and only on the
		// frontend.
		add_filter( 'woocommerce_add_message', array( $this, 'filter_out_shipping_debug_notice' ) );

		remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

		// The loop card is fully rendered by woocommerce/content-product.php.
		// Unhook WooCommerce's own default callbacks so they don't duplicate
		// the markup, but leave the action hooks themselves firing in the
		// template so third-party extensions still have their attachment
		// points (PRODUCT.md principle 4).
		remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
		remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
		remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_count_fragment' ) );
		add_filter( 'woocommerce_breadcrumb_defaults', array( $this, 'breadcrumb_defaults' ) );

		// Wrap the archive's result count and sorting control in one toolbar
		// row. By default they stack on the left, leaving most of the row
		// empty and squeezing the controls against the grid. Skipped entirely
		// when both are hidden, rather than printing an empty bordered strip.
		if ( $this->show_result_count || $this->show_sorting ) {
			add_action( 'woocommerce_before_shop_loop', array( $this, 'toolbar_start' ), 15 );
			add_action( 'woocommerce_before_shop_loop', array( $this, 'toolbar_end' ), 35 );
		}

		add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
	}

	public function content_wrapper_start() {
		// The single-product template manages its own full-bleed layout, so it
		// must not be boxed into the standard container.
		if ( function_exists( 'is_product' ) && is_product() ) {
			echo '<div class="souq-product-wrap">';
			return;
		}
		echo '<div class="souq-container souq-section">';
	}

	public function content_wrapper_end() {
		echo '</div>';
	}

	public function render_details_accordion() {
		get_template_part( 'template-parts/product/details-accordion' );
	}

	public function render_recommendations() {
		get_template_part( 'template-parts/product/recommended-products' );
	}

	public function toolbar_start() {
		echo '<div class="souq-toolbar">';
	}

	/**
	 * @see __construct() — filters `body_class`.
	 */
	public function add_centered_heading_body_class( $classes ) {
		$classes[] = 'souq-archive-heading-center';
		return $classes;
	}

	/**
	 * @see __construct() — filters `woocommerce_add_message`.
	 *
	 * Matched by the translated prefix rather than the full string, since the
	 * zone name is interpolated into the middle of it and the message itself
	 * is translatable.
	 */
	public function filter_out_shipping_debug_notice( $message ) {
		if ( is_admin() || ! is_string( $message ) ) {
			return $message;
		}

		// translators: %s: shipping zone name — this copy is WooCommerce's
		// own, kept identical so the prefix below actually matches it.
		$template = __( 'Customer matched zone "%s"', 'woocommerce' );
		$prefix   = substr( $template, 0, strpos( $template, '%s' ) );

		if ( $prefix && 0 === strpos( $message, $prefix ) ) {
			return '';
		}

		return $message;
	}

	public function toolbar_end() {
		echo '</div>';
	}

	/**
	 * Sends "Buy it now" straight to checkout. Falls through to WooCommerce's
	 * normal redirect for every other add-to-cart.
	 */
	public function buy_now_redirect( $url ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['souq_buy_now'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['souq_buy_now'] ) ) ) {
			$checkout = wc_get_checkout_url();
			if ( $checkout ) {
				return $checkout;
			}
		}
		return $url;
	}

	/**
	 * The product page presents the garment full-bleed with no chrome above
	 * it, so the breadcrumb is dropped there but kept on archive pages where
	 * it genuinely aids orientation.
	 */
	public function maybe_drop_product_breadcrumb() {
		if ( function_exists( 'is_product' ) && is_product() ) {
			remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		}
	}

	/**
	 * Updates the header cart-count bubble via WooCommerce's fragment
	 * refresh (wc-cart-fragments.js) after AJAX add-to-cart, no page reload.
	 */
	public function cart_count_fragment( $fragments ) {
		$count = WC()->cart->get_cart_contents_count();

		ob_start();
		?>
		<span class="souq-cart-count" aria-hidden="<?php echo $count > 0 ? 'false' : 'true'; ?>"><?php echo esc_html( $count ); ?></span>
		<?php
		$fragments['.souq-cart-count'] = ob_get_clean();

		return $fragments;
	}

	public function breadcrumb_defaults( $defaults ) {
		$defaults['wrap_before'] = '<nav class="souq-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'souq' ) . '">';
		$defaults['wrap_after']  = '</nav>';
		$defaults['delimiter']   = ' <span aria-hidden="true">/</span> ';
		return $defaults;
	}
}
