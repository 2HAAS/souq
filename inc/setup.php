<?php
/**
 * Core theme setup: supports, menus, image sizes, sidebars.
 *
 * @package Souq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guard: Souq requires WooCommerce. Without it the storefront has nothing to sell,
 * so we refuse to half-run rather than render broken commerce templates.
 */
function souq_requires_woocommerce_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			esc_html_e(
				'Souq is a WooCommerce storefront theme and requires the WooCommerce plugin to be installed and active.',
				'souq'
			);
			?>
		</p>
	</div>
	<?php
}

function souq_setup() {
	load_theme_textdomain( 'souq', SOUQ_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style' )
	);
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 72,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// WooCommerce declarations (design principle: respect the template/hook contract).
	add_theme_support( 'woocommerce' );

	// Deliberately NOT declaring wc-product-gallery-slider / -zoom / -lightbox.
	// Souq ships its own gallery (woocommerce/single-product/product-image.php)
	// because FlexSlider stacked every slide vertically, pushed the thumbnails
	// below the fold, and only ever swapped the single main image on a
	// variation change. Declaring those supports would load FlexSlider and
	// PhotoSwipe to fight our own markup.

	// Block editor. The palette, font families, font sizes and spacing scale
	// are declared in theme.json so WordPress's own editor and Site Editor
	// controls are the single source of truth; don't re-declare them here.
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor-style.css' );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Navigation', 'souq' ),
			'footer'  => __( 'Footer Navigation', 'souq' ),
			'account' => __( 'Account Menu', 'souq' ),
		)
	);

	// Editorial image roles. Named for purpose, not dimensions, so template code
	// stays readable when a size needs tuning. Apparel photography is portrait,
	// so the card/gallery crops are 4:5.
	add_image_size( 'souq-product-card', 800, 1000, true );
	add_image_size( 'souq-product-gallery', 1400, 1750, true );
	add_image_size( 'souq-product-thumb', 160, 200, true );
	add_image_size( 'souq-category-tile', 900, 1125, true );
	add_image_size( 'souq-hero', 2400, 1350, true );
	add_image_size( 'souq-banner', 2400, 900, true );
	add_image_size( 'souq-search-thumb', 96, 120, true );

	set_post_thumbnail_size( 800, 1000, true );
}
add_action( 'after_setup_theme', 'souq_setup' );

/**
 * Refuse to run WooCommerce-dependent bootstrap when the plugin is missing;
 * still register core theme supports so wp-admin itself isn't broken.
 */
function souq_check_woocommerce_dependency() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'souq_requires_woocommerce_notice' );
	}
}
add_action( 'admin_init', 'souq_check_woocommerce_dependency' );

/**
 * The block-based Checkout hardcodes billing email as required with no
 * filter to override it, so the Checkout page uses the classic
 * [woocommerce_checkout] shortcode instead, where this filter applies.
 *
 * This store only collects name, one free-text address field, and phone —
 * company, address line 2, city, state, postcode, country and email are
 * dropped entirely rather than just made optional. The store's base country
 * (Bangladesh) is used for every order since there is no country field.
 */
function souq_simplify_checkout_fields( $fields ) {
	foreach ( array( 'billing', 'shipping' ) as $group ) {
		foreach ( array( 'company', 'address_2', 'city', 'state', 'postcode', 'country', 'last_name' ) as $suffix ) {
			unset( $fields[ $group ][ "{$group}_{$suffix}" ] );
		}
	}
	unset( $fields['billing']['billing_email'] );

	foreach ( array( 'billing', 'shipping' ) as $group ) {
		if ( isset( $fields[ $group ][ "{$group}_first_name" ] ) ) {
			$fields[ $group ][ "{$group}_first_name" ]['label'] = __( 'Name', 'souq' );
		}

		if ( isset( $fields[ $group ][ "{$group}_address_1" ] ) ) {
			$fields[ $group ][ "{$group}_address_1" ]['type']  = 'textarea';
			$fields[ $group ][ "{$group}_address_1" ]['label'] = __( 'Address', 'souq' );
			$fields[ $group ][ "{$group}_address_1" ]['class'] = array( 'form-row-wide' );
			unset( $fields[ $group ][ "{$group}_address_1" ]['placeholder'] );
		}
	}

	if ( isset( $fields['billing']['billing_phone'] ) ) {
		$fields['billing']['billing_phone']['required'] = true;
	}

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'souq_simplify_checkout_fields' );

/**
 * Newsletter subscribers: private CPT so merchants can view/export signups
 * from the homepage newsletter pattern's REST endpoint (class-souq-block-patterns.php)
 * without standing up a mailing-list service dependency in the foundation pass.
 */
function souq_register_subscriber_post_type() {
	register_post_type(
		'souq_subscriber',
		array(
			'label'           => __( 'Newsletter Subscribers', 'souq' ),
			'labels'          => array(
				'name'          => __( 'Newsletter Subscribers', 'souq' ),
				'singular_name' => __( 'Subscriber', 'souq' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=product',
			'supports'        => array( 'title' ),
			'capability_type' => 'page',
			'map_meta_cap'    => true,
		)
	);
}
add_action( 'init', 'souq_register_subscriber_post_type' );

/**
 * Register widget areas.
 *
 * The footer has SOUQ_FOOTER_MAX_COLUMNS generic columns rather than a fixed
 * "menu column" and "widget column" — each is a normal block-widget area, so
 * a merchant can drop a Navigation Menu block (the primary menu again, a
 * second Terms/Returns menu, anything), a Paragraph/Custom HTML block for an
 * introduction, a Social Icons block, or several of those stacked in one
 * column. All four are always registered so widgets already placed in column
 * 3 or 4 are never orphaned if the shop temporarily renders fewer (a column
 * with nothing in it simply doesn't render — see site-footer.php).
 */
const SOUQ_FOOTER_MAX_COLUMNS = 4;

function souq_widgets_init() {
	for ( $i = 1; $i <= SOUQ_FOOTER_MAX_COLUMNS; $i++ ) {
		register_sidebar(
			array(
				/* translators: %d: column number */
				'name'          => sprintf( __( 'Footer Column %d', 'souq' ), $i ),
				'id'            => 'souq-footer-column-' . $i,
				'description'   => __( 'Add a Navigation Menu, Paragraph, or any block. Appears as one column in the footer, alongside any other columns that also have content.', 'souq' ),
				'before_widget' => '<div class="souq-footer-widget">',
				'after_widget'  => '</div>',
				'before_title'  => '<h2 class="souq-footer__heading">',
				'after_title'   => '</h2>',
			)
		);
	}

	register_sidebar(
		array(
			'name'          => __( 'Shop Sidebar', 'souq' ),
			'id'            => 'shop-sidebar',
			'before_widget' => '<div class="souq-widget">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="souq-widget__title">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'souq_widgets_init' );

/**
 * Content width for oEmbeds and media.
 */
$GLOBALS['content_width'] = 1440;

/**
 * Fallback nav when no "Primary Navigation" menu is assigned yet, so the
 * header never ships visibly broken before a merchant configures a menu.
 */
function souq_default_primary_menu() {
	$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
	printf(
		'<ul class="souq-nav"><li><a href="%s">%s</a></li></ul>',
		esc_url( $shop_url ),
		esc_html__( 'Shop', 'souq' )
	);
}

/**
 * Resolved header configuration.
 *
 * One place for the header's presentation decisions so the template, the body
 * classes and the announcement bar cannot disagree with each other.
 *
 * @return array{layout:string, sticky:bool, transparent:bool, announcement:string, announcement_url:string}
 */
function souq_header_config() {
	$layout = get_theme_mod( 'souq_header_layout', 'centred' );
	if ( ! array_key_exists( $layout, \Souq\Customizer::HEADER_LAYOUTS ) ) {
		$layout = 'centred';
	}

	$announcement = trim( (string) get_theme_mod( 'souq_header_announcement', '' ) );

	// The overlay only makes sense over a full-bleed hero, which only the
	// front page has. Anywhere else it would float over body copy.
	$transparent = (bool) get_theme_mod( 'souq_header_transparent', true ) && is_front_page();

	return array(
		'layout'           => $layout,
		'sticky'           => (bool) get_theme_mod( 'souq_header_sticky', true ),
		'transparent'      => $transparent,
		'announcement'     => $announcement,
		'announcement_url' => (string) get_theme_mod( 'souq_header_announcement_url', '' ),
	);
}

/**
 * Reflects the header configuration on <body> so page-level layout (notably
 * the hero's pull-up under an overlaid header) can respond to it.
 */
function souq_header_body_class( $classes ) {
	$config = souq_header_config();

	$classes[] = 'souq-header-layout-' . $config['layout'];

	if ( $config['transparent'] ) {
		$classes[] = 'souq-has-transparent-header';
	}

	if ( ! $config['sticky'] ) {
		$classes[] = 'souq-header-static';
	}

	if ( '' !== $config['announcement'] ) {
		$classes[] = 'souq-has-announcement';
	}

	return $classes;
}
add_filter( 'body_class', 'souq_header_body_class' );

/**
 * The heading level product cards should use, so the document outline stays
 * contiguous.
 *
 * On an archive the page title is the h1 and cards are its direct sections,
 * so they are h2. Inside a homepage product section the section already owns
 * an h2, so cards drop to h3. Getting this wrong produces an h1 -> h3 skip,
 * which misreports the page structure to anyone navigating by heading.
 *
 * @param int|null $level Pass an int to set; omit to read.
 * @return int Current level, clamped to 2-6.
 */
function souq_card_heading_level( $level = null ) {
	static $current = 2;

	if ( null !== $level ) {
		$current = max( 2, min( 6, (int) $level ) );
	}

	return $current;
}

/**
 * The full set of images that represent a product, in display order:
 * featured image, then gallery, then any variation image not already covered.
 *
 * Variation images are folded in so a shopper browsing the grid can see the
 * other colourways without opening the product, which is the whole point of
 * the slider on the card.
 *
 * @param WC_Product $product Product to collect images for.
 * @param int        $limit   Maximum images to return.
 * @return int[] Attachment IDs.
 */
function souq_get_product_images( $product, $limit = 6 ) {
	if ( ! $product instanceof WC_Product ) {
		return array();
	}

	$ids = array();

	$featured = $product->get_image_id();
	if ( $featured ) {
		$ids[] = (int) $featured;
	}

	foreach ( (array) $product->get_gallery_image_ids() as $gallery_id ) {
		$ids[] = (int) $gallery_id;
	}

	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation ) {
				continue;
			}
			$variation_image = (int) $variation->get_image_id();
			// A variation with no image of its own inherits the parent's, which
			// is already first in the list.
			if ( $variation_image ) {
				$ids[] = $variation_image;
			}
		}
	}

	$ids = array_values( array_unique( array_filter( $ids ) ) );

	return array_slice( $ids, 0, max( 1, (int) $limit ) );
}

/**
 * Colour chips to show on a loop card: the swatch-enabled attribute's terms
 * that this product actually offers. Returns [] when the product has no
 * swatch attribute, so the card simply omits the row.
 *
 * @param WC_Product $product Product to inspect.
 * @return array<int, array{name:string, color:string}>
 */
function souq_get_card_swatches( $product ) {
	if ( ! class_exists( '\Souq\Attribute_Swatches' ) || ! $product instanceof WC_Product ) {
		return array();
	}

	$taxonomy = \Souq\Attribute_Swatches::get_swatch_taxonomy_for( $product );
	if ( '' === $taxonomy ) {
		return array();
	}

	$terms = wc_get_product_terms( $product->get_id(), $taxonomy, array( 'fields' => 'all' ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	$swatches = array();
	foreach ( $terms as $term ) {
		$color = \Souq\Attribute_Swatches::get_term_color( $term->term_id );
		if ( $color ) {
			$swatches[] = array(
				'name'  => $term->name,
				'color' => $color,
			);
		}
	}

	return $swatches;
}

/**
 * Renders the brand mark: the uploaded logo when set, otherwise a
 * typographic wordmark built from the site title. Both are links home
 * except on the front page, where a link to the current page is noise.
 *
 * @param array $args {
 *     @type string $class Extra class on the wrapper.
 *     @type bool   $link  Whether to wrap in a link. Default true.
 * }
 */
function souq_render_wordmark( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'class' => '',
			'link'  => true,
		)
	);

	$classes = trim( 'souq-wordmark ' . $args['class'] );

	if ( has_custom_logo() ) {
		$logo_id = get_theme_mod( 'custom_logo' );
		$inner   = wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'class' => 'souq-wordmark__image',
				'alt'   => get_bloginfo( 'name' ),
			)
		);
	} else {
		$inner = '<span class="souq-wordmark__text">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
	}

	if ( ! $args['link'] ) {
		printf( '<span class="%s">%s</span>', esc_attr( $classes ), $inner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return;
	}

	printf(
		'<a class="%1$s" href="%2$s" rel="home">%3$s</a>',
		esc_attr( $classes ),
		esc_url( home_url( '/' ) ),
		$inner // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}
