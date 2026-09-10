<?php
/**
 * Customizer: brand identity, colour, typography, layout.
 *
 * Everything a merchant can restyle is expressed as a CSS custom property
 * printed into wp_head, so templates never hardcode a brand decision
 * (PRODUCT.md principle 1) and a re-skin needs no markup changes.
 *
 * @package Souq
 */

namespace Souq {

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Customizer {

	private static $instance = null;

	/**
	 * Colour tokens: slug => default hex. Defaults mirror style.css so an
	 * unconfigured site matches DESIGN.md exactly.
	 */
	const COLOR_DEFAULTS = array(
		'accent'  => '#8b4a38',
		'ink'     => '#26221e',
		'ground'  => '#fdfaf3',
		'surface' => '#f6f1e6',
	);

	/**
	 * Curated typeface pairings. Each is a complete direction rather than two
	 * independent dropdowns, because display/body pairing is the decision that
	 * most easily goes wrong in untrained hands.
	 *
	 * key => [ label, display stack, body stack, Google Fonts families ]
	 */
	const PAIRINGS = array(
		'editorial'  => array(
			'label'   => 'Editorial (Cormorant + Jost)',
			'display' => '"Cormorant Garamond", Georgia, serif',
			'body'    => '"Jost", system-ui, sans-serif',
			'google'  => array( 'Cormorant Garamond:wght@300;400;500', 'Jost:wght@300;400;500' ),
		),
		'couture'    => array(
			'label'   => 'Couture (Playfair + Inter)',
			'display' => '"Playfair Display", Georgia, serif',
			'body'    => '"Inter", system-ui, sans-serif',
			'google'  => array( 'Playfair Display:wght@400;500', 'Inter:wght@300;400;500' ),
		),
		'heritage'   => array(
			'label'   => 'Heritage (EB Garamond + Work Sans)',
			'display' => '"EB Garamond", Georgia, serif',
			'body'    => '"Work Sans", system-ui, sans-serif',
			'google'  => array( 'EB Garamond:wght@400;500', 'Work Sans:wght@300;400;500' ),
		),
		'modern'     => array(
			'label'   => 'Modern (Jost only)',
			'display' => '"Jost", system-ui, sans-serif',
			'body'    => '"Jost", system-ui, sans-serif',
			'google'  => array( 'Jost:wght@300;400;500' ),
		),
		'system'     => array(
			'label'   => 'System (no web fonts)',
			'display' => 'Georgia, "Times New Roman", serif',
			'body'    => 'system-ui, -apple-system, "Segoe UI", sans-serif',
			'google'  => array(),
		),
	);

	const SHAPES = array(
		'square'  => array( 'label' => 'Square', 'sm' => '0px', 'md' => '0px' ),
		'subtle'  => array( 'label' => 'Subtle', 'sm' => '3px', 'md' => '4px' ),
		'rounded' => array( 'label' => 'Rounded', 'sm' => '6px', 'md' => '10px' ),
		'pill'    => array( 'label' => 'Pill', 'sm' => '6px', 'md' => '999px' ),
	);

	/**
	 * Header layout presets. The template renders the same three zones for
	 * every preset and only changes their order and alignment, so switching
	 * layout never changes what a shopper can reach, only where it sits.
	 */
	const HEADER_LAYOUTS = array(
		'centred' => array(
			'label'       => 'Centred logo (nav left, icons right)',
			'description' => 'Editorial fashion default. Navigation left, wordmark centred, actions right.',
		),
		'classic' => array(
			'label'       => 'Classic (logo left, nav centre)',
			'description' => 'Familiar retail arrangement. Wordmark left, navigation centred, actions right.',
		),
		'stacked' => array(
			'label'       => 'Stacked (logo above nav)',
			'description' => 'Wordmark centred on its own row with navigation centred beneath. Gives the brand the most presence.',
		),
		'minimal' => array(
			'label'       => 'Minimal (logo left, menu in drawer)',
			'description' => 'Wordmark left, actions right, all navigation behind the menu button at every screen size.',
		),
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'customize_register', array( $this, 'register' ) );
		add_action( 'wp_head', array( $this, 'output_tokens_css' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_fonts' ), 5 );
		add_action( 'customize_preview_init', array( $this, 'enqueue_preview_script' ) );
	}

	public function register( $wp_customize ) {
		$wp_customize->add_panel(
			'souq_theme_options',
			array(
				'title'    => __( 'Souq Theme Options', 'souq' ),
				'priority' => 25,
			)
		);

		$this->register_header( $wp_customize );
		$this->register_colors( $wp_customize );
		$this->register_typography( $wp_customize );
		$this->register_layout( $wp_customize );
		$this->register_shop( $wp_customize );
		$this->register_recommendations( $wp_customize );
		$this->register_social( $wp_customize );

		$section = $wp_customize->get_section( 'title_tagline' );
		if ( $section ) {
			$section->description = __(
				'Upload your logo and site icon here. With no logo set, Souq falls back to a typographic wordmark built from your site title.',
				'souq'
			);
		}

		// Live-update the wordmark fallback as the site title is typed.
		$title_setting = $wp_customize->get_setting( 'blogname' );
		if ( $title_setting ) {
			$title_setting->transport = 'postMessage';
		}
	}

	/**
	 * Header layout and behaviour.
	 *
	 * Layout changes need a real reload rather than postMessage: the preset
	 * reorders the rendered zones in PHP, so there is no CSS-only way to
	 * preview it accurately.
	 */
	private function register_header( $wp_customize ) {
		$wp_customize->add_section(
			'souq_header',
			array(
				'title'       => __( 'Header', 'souq' ),
				'panel'       => 'souq_theme_options',
				'priority'    => 5,
				'description' => __( 'Choose how the header is arranged. Every layout keeps the same navigation, search, account and cart, so switching never hides anything from shoppers.', 'souq' ),
			)
		);

		$wp_customize->add_setting(
			'souq_header_layout',
			array(
				'default'           => 'centred',
				'sanitize_callback' => array( $this, 'sanitize_header_layout' ),
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'souq_header_layout',
			array(
				'label'   => __( 'Header layout', 'souq' ),
				'section' => 'souq_header',
				'type'    => 'select',
				'choices' => wp_list_pluck( self::HEADER_LAYOUTS, 'label' ),
			)
		);

		$wp_customize->add_setting(
			'souq_header_sticky',
			array(
				'default'           => true,
				'sanitize_callback' => 'wp_validate_boolean',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'souq_header_sticky',
			array(
				'label'       => __( 'Keep the header visible when scrolling', 'souq' ),
				'section'     => 'souq_header',
				'type'        => 'checkbox',
			)
		);

		$wp_customize->add_setting(
			'souq_header_transparent',
			array(
				'default'           => true,
				'sanitize_callback' => 'wp_validate_boolean',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'souq_header_transparent',
			array(
				'label'       => __( 'Overlay the header on the homepage hero', 'souq' ),
				'description' => __( 'The header sits transparently over a full-bleed hero image and turns solid as soon as the page scrolls.', 'souq' ),
				'section'     => 'souq_header',
				'type'        => 'checkbox',
			)
		);

		$wp_customize->add_setting(
			'souq_header_announcement',
			array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'souq_header_announcement',
			array(
				'label'       => __( 'Announcement bar', 'souq' ),
				'description' => __( 'A single line above the header, for delivery or promotion notices. Leave empty to hide it.', 'souq' ),
				'section'     => 'souq_header',
				'type'        => 'text',
			)
		);

		$wp_customize->add_setting(
			'souq_header_announcement_url',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'souq_header_announcement_url',
			array(
				'label'   => __( 'Announcement link (optional)', 'souq' ),
				'section' => 'souq_header',
				'type'    => 'url',
			)
		);
	}

	private function register_colors( $wp_customize ) {
		$wp_customize->add_section(
			'souq_colors',
			array(
				'title'       => __( 'Colours', 'souq' ),
				'panel'       => 'souq_theme_options',
				'priority'    => 10,
				'description' => __( 'The interface stays quiet so product photography carries the colour. The accent is spent on primary actions and selected states only.', 'souq' ),
			)
		);

		$labels = array(
			'accent'  => __( 'Accent (buttons, selected states, sale badges)', 'souq' ),
			'ink'     => __( 'Text', 'souq' ),
			'ground'  => __( 'Page background', 'souq' ),
			'surface' => __( 'Alternate section background', 'souq' ),
		);

		foreach ( self::COLOR_DEFAULTS as $slug => $default ) {
			$setting_id = "souq_color_{$slug}";

			$wp_customize->add_setting(
				$setting_id,
				array(
					'default'           => $default,
					'sanitize_callback' => 'sanitize_hex_color',
					'transport'         => 'postMessage',
				)
			);

			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					$setting_id,
					array(
						'label'   => $labels[ $slug ],
						'section' => 'souq_colors',
					)
				)
			);
		}
	}

	private function register_typography( $wp_customize ) {
		$wp_customize->add_section(
			'souq_typography',
			array(
				'title'    => __( 'Typography', 'souq' ),
				'panel'    => 'souq_theme_options',
				'priority' => 20,
			)
		);

		$wp_customize->add_setting(
			'souq_font_pairing',
			array(
				'default'           => 'editorial',
				'sanitize_callback' => array( $this, 'sanitize_pairing' ),
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			'souq_font_pairing',
			array(
				'label'       => __( 'Typeface pairing', 'souq' ),
				'description' => __( 'Display face carries headings and product names; the sans carries UI, labels and prices.', 'souq' ),
				'section'     => 'souq_typography',
				'type'        => 'select',
				'choices'     => wp_list_pluck( self::PAIRINGS, 'label' ),
			)
		);

		$wp_customize->add_setting(
			'souq_label_tracking',
			array(
				'default'           => 12,
				'sanitize_callback' => 'absint',
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			'souq_label_tracking',
			array(
				'label'       => __( 'Label letter-spacing', 'souq' ),
				'description' => __( 'Applies to navigation, buttons and product titles. Higher is more editorial.', 'souq' ),
				'section'     => 'souq_typography',
				'type'        => 'range',
				'input_attrs' => array(
					'min'  => 0,
					'max'  => 24,
					'step' => 1,
				),
			)
		);
	}

	private function register_layout( $wp_customize ) {
		$wp_customize->add_section(
			'souq_layout',
			array(
				'title'    => __( 'Layout & Shape', 'souq' ),
				'panel'    => 'souq_theme_options',
				'priority' => 30,
			)
		);

		$wp_customize->add_setting(
			'souq_shape',
			array(
				'default'           => 'subtle',
				'sanitize_callback' => array( $this, 'sanitize_shape' ),
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			'souq_shape',
			array(
				'label'   => __( 'Corner style', 'souq' ),
				'section' => 'souq_layout',
				'type'    => 'select',
				'choices' => wp_list_pluck( self::SHAPES, 'label' ),
			)
		);

		$wp_customize->add_setting(
			'souq_grid_columns',
			array(
				'default'           => 4,
				'sanitize_callback' => array( $this, 'sanitize_columns' ),
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			'souq_grid_columns',
			array(
				'label'       => __( 'Products per row (desktop)', 'souq' ),
				'section'     => 'souq_layout',
				'type'        => 'select',
				'choices'     => array(
					3 => __( '3 — large imagery', 'souq' ),
					4 => __( '4 — balanced', 'souq' ),
					5 => __( '5 — dense catalogue', 'souq' ),
				),
			)
		);

		$wp_customize->add_setting(
			'souq_card_aspect',
			array(
				'default'           => '4 / 5',
				'sanitize_callback' => array( $this, 'sanitize_aspect' ),
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			'souq_card_aspect',
			array(
				'label'       => __( 'Product image shape', 'souq' ),
				'description' => __( 'Portrait suits full-length garment photography; square suits flat-lay.', 'souq' ),
				'section'     => 'souq_layout',
				'type'        => 'select',
				'choices'     => array(
					'4 / 5' => __( 'Portrait (4:5)', 'souq' ),
					'2 / 3' => __( 'Tall portrait (2:3)', 'souq' ),
					'1 / 1' => __( 'Square (1:1)', 'souq' ),
				),
			)
		);
	}

	/**
	 * Shop and category archive controls. These sit in `inc/woocommerce/
	 * class-souq-woocommerce.php`, not template markup, since they toggle
	 * WooCommerce's own hooks (`woocommerce_result_count`,
	 * `woocommerce_catalog_ordering`, `woocommerce_breadcrumb`) and a body
	 * class, rather than anything Souq renders itself.
	 *
	 * Alignment applies to the page heading — WooCommerce's own
	 * `.woocommerce-products-header` wrapping the "Shop"/category `<h1>` —
	 * not the result-count/sorting row beneath it. An earlier version of
	 * this control centred that row instead; that was a misread of what
	 * "woocommerce-products-header" actually names in WooCommerce's own
	 * markup (the title header, not the toolbar), fixed 2026-08-19.
	 */
	private function register_shop( $wp_customize ) {
		$wp_customize->add_section(
			'souq_shop',
			array(
				'title'    => __( 'Shop & Archive', 'souq' ),
				'panel'    => 'souq_theme_options',
				'priority' => 35,
			)
		);

		$wp_customize->add_setting(
			'souq_archive_heading_align',
			array(
				'default'           => 'left',
				'sanitize_callback' => array( __CLASS__, 'sanitize_heading_align' ),
			)
		);
		$wp_customize->add_control(
			'souq_archive_heading_align',
			array(
				'label'       => __( 'Archive page heading', 'souq' ),
				'description' => __( 'Aligns the “Shop” / category title (e.g. “1 Piece”) at the top of shop and category pages.', 'souq' ),
				'section'     => 'souq_shop',
				'type'        => 'select',
				'choices'     => array(
					'left'   => __( 'Left', 'souq' ),
					'center' => __( 'Centred', 'souq' ),
				),
			)
		);

		$wp_customize->add_setting(
			'souq_archive_show_result_count',
			array(
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		$wp_customize->add_control(
			'souq_archive_show_result_count',
			array(
				'label'       => __( 'Show result count', 'souq' ),
				'description' => __( 'The “Showing all 12 results” line above the product grid.', 'souq' ),
				'section'     => 'souq_shop',
				'type'        => 'checkbox',
			)
		);

		$wp_customize->add_setting(
			'souq_archive_show_sorting',
			array(
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		$wp_customize->add_control(
			'souq_archive_show_sorting',
			array(
				'label'   => __( 'Show sorting dropdown', 'souq' ),
				'section' => 'souq_shop',
				'type'    => 'checkbox',
			)
		);

		$wp_customize->add_setting(
			'souq_archive_show_breadcrumbs',
			array(
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		$wp_customize->add_control(
			'souq_archive_show_breadcrumbs',
			array(
				'label'       => __( 'Show breadcrumbs', 'souq' ),
				'description' => __( 'The “Home / Category” trail above shop and category archives. Product pages never show one, by design.', 'souq' ),
				'section'     => 'souq_shop',
				'type'        => 'checkbox',
			)
		);
	}

	public static function sanitize_heading_align( $value ) {
		return 'center' === $value ? 'center' : 'left';
	}

	/**
	 * The "You may also like" section beneath a single product. Sourced from
	 * `\Souq\Recommended_Products` — a merchant's own curated picks per
	 * product, topped up with same-category products when there aren't
	 * enough (or none) curated, so the section is never sparse on a product
	 * nobody has gotten to yet.
	 */
	private function register_recommendations( $wp_customize ) {
		$wp_customize->add_section(
			'souq_recommendations',
			array(
				'title'    => __( 'Recommendations', 'souq' ),
				'panel'    => 'souq_theme_options',
				'priority' => 45,
			)
		);

		$wp_customize->add_setting(
			'souq_show_recommendations',
			array(
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		$wp_customize->add_control(
			'souq_show_recommendations',
			array(
				'label'       => __( 'Show recommendations below the product', 'souq' ),
				'description' => __( 'Curate picks per product from the “Recommended Products” box on the product editor.', 'souq' ),
				'section'     => 'souq_recommendations',
				'type'        => 'checkbox',
			)
		);

		$wp_customize->add_setting(
			'souq_recommendations_heading',
			array(
				'default'           => __( 'You may also like', 'souq' ),
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		$wp_customize->add_control(
			'souq_recommendations_heading',
			array(
				'label'    => __( 'Section heading', 'souq' ),
				'section'  => 'souq_recommendations',
				'type'     => 'text',
				'active_callback' => function() use ( $wp_customize ) {
					return (bool) $wp_customize->get_setting( 'souq_show_recommendations' )->value();
				},
			)
		);

		$wp_customize->add_setting(
			'souq_recommendations_count',
			array(
				'default'           => 4,
				'sanitize_callback' => 'absint',
			)
		);
		$wp_customize->add_control(
			'souq_recommendations_count',
			array(
				'label'           => __( 'Number of products shown', 'souq' ),
				'section'         => 'souq_recommendations',
				'type'            => 'select',
				'choices'         => array(
					2 => '2',
					3 => '3',
					4 => '4',
					5 => '5',
					6 => '6',
					8 => '8',
				),
				'active_callback' => function() use ( $wp_customize ) {
					return (bool) $wp_customize->get_setting( 'souq_show_recommendations' )->value();
				},
			)
		);

		$wp_customize->add_setting(
			'souq_recommendations_fallback',
			array(
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		$wp_customize->add_control(
			'souq_recommendations_fallback',
			array(
				'label'           => __( 'Fill with same-category products when nothing is curated', 'souq' ),
				'description'     => __( 'Without this, the section only appears on products that have been manually curated.', 'souq' ),
				'section'         => 'souq_recommendations',
				'type'            => 'checkbox',
				'active_callback' => function() use ( $wp_customize ) {
					return (bool) $wp_customize->get_setting( 'souq_show_recommendations' )->value();
				},
			)
		);
	}

	private function register_social( $wp_customize ) {
		$wp_customize->add_section(
			'souq_social',
			array(
				'title'    => __( 'Social Links', 'souq' ),
				'panel'    => 'souq_theme_options',
				'priority' => 40,
			)
		);

		$networks = array(
			'instagram' => __( 'Instagram URL', 'souq' ),
			'facebook'  => __( 'Facebook URL', 'souq' ),
			'tiktok'    => __( 'TikTok URL', 'souq' ),
		);

		foreach ( $networks as $slug => $label ) {
			$wp_customize->add_setting(
				"souq_social_{$slug}",
				array(
					'default'           => '',
					'sanitize_callback' => 'esc_url_raw',
				)
			);
			$wp_customize->add_control(
				"souq_social_{$slug}",
				array(
					'label'   => $label,
					'section' => 'souq_social',
					'type'    => 'url',
				)
			);
		}
	}

	/* ---------------------------------------------------------------------
	   Sanitizers
	   --------------------------------------------------------------------- */

	public function sanitize_pairing( $value ) {
		return array_key_exists( $value, self::PAIRINGS ) ? $value : 'editorial';
	}

	public function sanitize_header_layout( $value ) {
		return array_key_exists( $value, self::HEADER_LAYOUTS ) ? $value : 'centred';
	}

	public function sanitize_shape( $value ) {
		return array_key_exists( $value, self::SHAPES ) ? $value : 'subtle';
	}

	public function sanitize_columns( $value ) {
		$value = absint( $value );
		return in_array( $value, array( 3, 4, 5 ), true ) ? $value : 4;
	}

	public function sanitize_aspect( $value ) {
		return in_array( $value, array( '4 / 5', '2 / 3', '1 / 1' ), true ) ? $value : '4 / 5';
	}

	/* ---------------------------------------------------------------------
	   Runtime token output
	   --------------------------------------------------------------------- */

	/**
	 * Prints CSS custom property overrides after the stylesheets have loaded
	 * (priority 20) so admin choices win the cascade without duplicating
	 * style.css's full token list.
	 */
	public function output_tokens_css() {
		$accent  = get_theme_mod( 'souq_color_accent', self::COLOR_DEFAULTS['accent'] );
		$ink     = get_theme_mod( 'souq_color_ink', self::COLOR_DEFAULTS['ink'] );
		$ground  = get_theme_mod( 'souq_color_ground', self::COLOR_DEFAULTS['ground'] );
		$surface = get_theme_mod( 'souq_color_surface', self::COLOR_DEFAULTS['surface'] );

		$pairing = self::PAIRINGS[ $this->sanitize_pairing( get_theme_mod( 'souq_font_pairing', 'editorial' ) ) ];
		$shape   = self::SHAPES[ $this->sanitize_shape( get_theme_mod( 'souq_shape', 'subtle' ) ) ];

		$tracking = absint( get_theme_mod( 'souq_label_tracking', 12 ) ) / 100;
		$columns  = $this->sanitize_columns( get_theme_mod( 'souq_grid_columns', 4 ) );
		$aspect   = $this->sanitize_aspect( get_theme_mod( 'souq_card_aspect', '4 / 5' ) );

		$css = sprintf(
			':root{--souq-color-accent:%1$s;--souq-color-accent-deep:%2$s;--souq-color-accent-soft:%3$s;--souq-color-accent-soft-deep:%4$s;--souq-color-ink:%5$s;--souq-color-ground:%6$s;--souq-color-surface:%7$s;--souq-color-muted:%8$s;--souq-color-hairline:%9$s;--souq-font-display:%10$s;--souq-font-body:%11$s;--souq-tracking-label:%12$sem;--souq-radius-sm:%13$s;--souq-radius-md:%14$s;--souq-grid-columns:%15$d;--souq-card-aspect:%16$s;}',
			self::css_color( $accent ),
			self::css_color( self::mix( $accent, $ink, 0.22 ) ),
			self::css_color( self::mix( $accent, $ground, 0.9 ) ),
			self::css_color( self::mix( $accent, $ground, 0.78 ) ),
			self::css_color( $ink ),
			self::css_color( $ground ),
			self::css_color( $surface ),
			// 0.34, not a lighter blend: at 0.42 the muted text measured
			// 3.9:1 against the ground and failed WCAG AA (needs 4.5:1).
			self::css_color( self::mix( $ink, $ground, 0.34 ) ),
			self::css_color( self::mix( $ink, $ground, 0.84 ) ),
			self::css_value( $pairing['display'] ),
			self::css_value( $pairing['body'] ),
			self::css_value( rtrim( rtrim( number_format( $tracking, 2, '.', '' ), '0' ), '.' ) ),
			self::css_value( $shape['sm'] ),
			self::css_value( $shape['md'] ),
			(int) $columns,
			self::css_value( $aspect )
		);

		printf( '<style id="souq-tokens">%s</style>', $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Sanitises a value for use inside a CSS declaration.
	 *
	 * Deliberately NOT esc_html(): that HTML-escapes the double quotes in a
	 * font stack into &quot;, which makes the whole declaration invalid and
	 * silently drops the site back to browser default fonts. Instead strip
	 * only the characters that could terminate the declaration or escape the
	 * <style> element.
	 */
	private static function css_value( $value ) {
		return trim( str_replace( array( '<', '>', '{', '}', ';', '\\' ), '', (string) $value ) );
	}

	/**
	 * Sanitises a colour, falling back to a safe literal rather than emitting
	 * an invalid value that would break the whole rule.
	 */
	private static function css_color( $value ) {
		$hex = sanitize_hex_color( (string) $value );
		return $hex ? $hex : 'inherit';
	}

	/**
	 * Blends $hex toward $toward by $amount (0-1) and returns hex.
	 *
	 * Deriving the muted text, hairline and soft-accent tints from the admin's
	 * own colours keeps a re-skin coherent: pick one accent and the quiet
	 * supporting tints follow, instead of exposing eight colour pickers.
	 */
	private static function mix( $hex, $toward, $amount ) {
		$a = self::to_rgb( $hex );
		$b = self::to_rgb( $toward );

		if ( ! $a || ! $b ) {
			return $hex;
		}

		$amount = max( 0, min( 1, $amount ) );

		return sprintf(
			'#%02x%02x%02x',
			(int) round( $a[0] + ( $b[0] - $a[0] ) * $amount ),
			(int) round( $a[1] + ( $b[1] - $a[1] ) * $amount ),
			(int) round( $a[2] + ( $b[2] - $a[2] ) * $amount )
		);
	}

	private static function to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return null;
		}
		return array_map( 'hexdec', str_split( $hex, 2 ) );
	}

	public function enqueue_fonts() {
		$pairing  = self::PAIRINGS[ $this->sanitize_pairing( get_theme_mod( 'souq_font_pairing', 'editorial' ) ) ];
		$families = $pairing['google'];

		if ( empty( $families ) ) {
			return;
		}

		$src = 'https://fonts.googleapis.com/css2?family=' .
			implode( '&family=', array_map( 'rawurlencode', $families ) ) .
			'&display=swap';

		wp_enqueue_style( 'souq-google-fonts', $src, array(), null );
	}

	public function enqueue_preview_script() {
		wp_enqueue_script(
			'souq-customizer-preview',
			SOUQ_URI . '/assets/js/customizer-preview.js',
			array( 'customize-preview' ),
			SOUQ_VERSION,
			true
		);
		wp_localize_script(
			'souq-customizer-preview',
			'souqCustomizerData',
			array(
				'pairings' => self::PAIRINGS,
				'shapes'   => self::SHAPES,
			)
		);
	}
}

} // namespace Souq

namespace {
	/**
	 * Read a brand colour token, respecting admin overrides. Global (not
	 * namespaced) because template files are plain procedural PHP.
	 */
	function souq_get_color( $slug ) {
		$defaults = \Souq\Customizer::COLOR_DEFAULTS;
		$default  = $defaults[ $slug ] ?? '#000000';
		return get_theme_mod( "souq_color_{$slug}", $default );
	}
}
