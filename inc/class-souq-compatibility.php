<?php
/**
 * Compatibility with page builders that override theme templates.
 *
 * Elementor Pro's Theme Builder registers templates against "locations"
 * (header, footer, single, archive) and takes over rendering for any location
 * whose display conditions match. Those templates are stored as posts, so they
 * survive a theme switch: activating Souq on a site that was previously built
 * with Elementor leaves the old header and product layout in place, and the
 * theme appears not to have applied.
 *
 * That is Elementor working as designed, not a fault to route around silently.
 * So this class does two things: it reports exactly which templates are
 * overriding which locations, and it offers an explicit, opt-in way to hand a
 * location back to Souq. Nothing is disabled unless the merchant asks.
 *
 * @package Souq
 */

namespace Souq;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Compatibility {

	private static $instance = null;

	/** Option holding the locations the merchant has handed back to Souq. */
	const OPTION_RECLAIMED = 'souq_reclaimed_locations';

	/**
	 * Elementor theme-builder locations Souq is able to render itself,
	 * mapped to the label shown in the setup screen.
	 */
	const RECLAIMABLE = array(
		'header'  => 'Header',
		'footer'  => 'Footer',
		'single'  => 'Single product and single post layout',
		'archive' => 'Shop and category listings',
	);

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Runs regardless of whether Elementor is active; the filter simply
		// never fires without it.
		add_filter( 'elementor/theme/need_override_location', array( $this, 'maybe_reclaim_location' ), 20, 2 );
	}

	/**
	 * Hands a location back to Souq when the merchant has opted in.
	 *
	 * @param bool   $need_override Whether Elementor wants to take the location.
	 * @param string $location      Location slug (header, footer, single, archive).
	 * @return bool
	 */
	public function maybe_reclaim_location( $need_override, $location ) {
		if ( in_array( $location, self::get_reclaimed(), true ) ) {
			return false;
		}
		return $need_override;
	}

	/**
	 * @return string[] Locations the merchant has handed back to Souq.
	 */
	public static function get_reclaimed() {
		$saved = get_option( self::OPTION_RECLAIMED, array() );
		if ( ! is_array( $saved ) ) {
			return array();
		}
		return array_values( array_intersect( $saved, array_keys( self::RECLAIMABLE ) ) );
	}

	public static function set_reclaimed( array $locations ) {
		$clean = array_values( array_intersect( $locations, array_keys( self::RECLAIMABLE ) ) );
		update_option( self::OPTION_RECLAIMED, $clean );
		return $clean;
	}

	public static function elementor_pro_active() {
		return defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\ElementorPro\Plugin' );
	}

	/**
	 * Elementor theme-builder templates on this site, grouped by the location
	 * they render into.
	 *
	 * Elementor stores the template kind in `_elementor_template_type`
	 * (header, footer, product, archive, single-post, …). Those kinds are
	 * mapped onto the four locations Souq can render, so the report speaks in
	 * terms of what the merchant actually sees.
	 *
	 * @return array<string, array<int, array{id:int,title:string,type:string,edit:string}>>
	 */
	public static function get_overriding_templates() {
		if ( ! post_type_exists( 'elementor_library' ) ) {
			return array();
		}

		$type_to_location = array(
			'header'          => 'header',
			'footer'          => 'footer',
			'product'         => 'single',
			'single'          => 'single',
			'single-post'     => 'single',
			'single-page'     => 'single',
			'archive'         => 'archive',
			'product-archive' => 'archive',
			'search-results'  => 'archive',
		);

		$templates = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_elementor_template_type',
						'value'   => array_keys( $type_to_location ),
						'compare' => 'IN',
					),
				),
			)
		);

		$grouped = array();

		foreach ( $templates as $template ) {
			$type = (string) get_post_meta( $template->ID, '_elementor_template_type', true );

			if ( ! isset( $type_to_location[ $type ] ) ) {
				continue;
			}

			$location = $type_to_location[ $type ];

			$grouped[ $location ][] = array(
				'id'    => (int) $template->ID,
				'title' => $template->post_title ? $template->post_title : __( '(untitled template)', 'souq' ),
				'type'  => $type,
				'edit'  => (string) get_edit_post_link( $template->ID, 'raw' ),
			);
		}

		return $grouped;
	}

	/**
	 * Caching layers that will keep serving the previous design after a theme
	 * or template change until they are purged.
	 *
	 * @return string[] Human-readable names.
	 */
	public static function get_active_caches() {
		$found = array();

		$plugins = array(
			'WP Rocket'       => 'WP_ROCKET_VERSION',
			'LiteSpeed Cache' => 'LSCWP_V',
			'W3 Total Cache'  => 'W3TC',
			'WP Super Cache'  => 'WPCACHEHOME',
			'WP Fastest Cache' => 'WPFC_MAIN_PATH',
		);

		foreach ( $plugins as $name => $constant ) {
			if ( defined( $constant ) ) {
				$found[] = $name;
			}
		}

		if ( class_exists( '\Automattic\Jetpack\Boost\Jetpack_Boost' ) ) {
			$found[] = 'Jetpack Boost';
		}

		// A server-level page cache shows up in the response headers rather
		// than as a plugin, so name it when the SAPI hints at LiteSpeed.
		if ( ! in_array( 'LiteSpeed Cache', $found, true ) && isset( $_SERVER['SERVER_SOFTWARE'] ) && false !== stripos( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ), 'litespeed' ) ) {
			$found[] = 'LiteSpeed server cache';
		}

		return $found;
	}
}
