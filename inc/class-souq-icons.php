<?php
/**
 * Inline SVG icon loader. Icons are Phosphor (Light weight), sourced from
 * https://icon-sets.iconify.design/ph/ and stored locally under assets/icons/
 * so the theme has zero runtime dependency on an external icon CDN.
 *
 * @package Souq
 */

namespace Souq {

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Icons {

	/**
	 * Returns raw inline SVG markup for an icon.
	 *
	 * @param string $name  Icon slug matching a file in assets/icons/{name}.svg.
	 * @param array  $args  {
	 *     @type string $label Accessible label. Omit for a purely decorative icon.
	 *     @type string $class Extra CSS class(es) on the <svg>.
	 *     @type string $size  CSS size value applied to width/height. Default '1em'.
	 * }
	 */
	public static function get( $name, $args = array() ) {
		static $cache = array();

		$name = sanitize_key( $name );
		if ( ! isset( $cache[ $name ] ) ) {
			$path = SOUQ_DIR . '/assets/icons/' . $name . '.svg';
			if ( ! file_exists( $path ) ) {
				$cache[ $name ] = '';
			} else {
				$cache[ $name ] = file_get_contents( $path );
			}
		}

		$svg = $cache[ $name ];
		if ( '' === $svg ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'label' => '',
				'class' => '',
				'size'  => '1em',
			)
		);

		$classes = trim( 'souq-icon souq-icon--' . $name . ' ' . $args['class'] );

		if ( '' !== $args['label'] ) {
			$a11y = sprintf( ' role="img" aria-label="%s"', esc_attr( $args['label'] ) );
		} else {
			$a11y = ' aria-hidden="true" focusable="false"';
		}

		// Inject class, sizing, and accessibility attributes onto the root <svg> tag
		// without a full DOM parse; the source files are trusted, theme-local assets.
		$svg = preg_replace(
			'/^<svg /',
			sprintf(
				'<svg class="%1$s" style="width:%2$s;height:%2$s"%3$s ',
				esc_attr( $classes ),
				esc_attr( $args['size'] ),
				$a11y
			),
			$svg,
			1
		);

		return $svg;
	}

	/**
	 * Echoes the icon. Output is theme-local trusted SVG assembled above with
	 * attributes already escaped, so no further escaping is applied here.
	 */
	public static function render( $name, $args = array() ) {
		echo self::get( $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

} // namespace Souq

namespace {
	/**
	 * Template-facing shorthand: souq_icon( 'cart', [ 'label' => __( 'Cart', 'souq' ) ] ).
	 */
	function souq_icon( $name, $args = array() ) {
		return \Souq\Icons::get( $name, $args );
	}
}
