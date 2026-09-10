/**
 * Live-updates design tokens in the Customizer preview so colour, type,
 * shape and density changes are visible before publishing.
 *
 * Mirrors the derivation logic in Customizer::output_tokens_css(): the muted,
 * hairline and soft-accent tints are blended from the admin's own colours
 * rather than being separate controls.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.customize ) {
		return;
	}

	var root = document.documentElement;
	var data = window.souqCustomizerData || { pairings: {}, shapes: {} };

	function toRgb( hex ) {
		hex = String( hex ).replace( '#', '' );
		if ( hex.length === 3 ) {
			hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
		}
		if ( ! /^[0-9a-f]{6}$/i.test( hex ) ) {
			return null;
		}
		return [
			parseInt( hex.slice( 0, 2 ), 16 ),
			parseInt( hex.slice( 2, 4 ), 16 ),
			parseInt( hex.slice( 4, 6 ), 16 ),
		];
	}

	function mix( hex, toward, amount ) {
		var a = toRgb( hex );
		var b = toRgb( toward );
		if ( ! a || ! b ) {
			return hex;
		}
		var out = a.map( function ( channel, i ) {
			return Math.round( channel + ( b[ i ] - channel ) * amount );
		} );
		return (
			'#' +
			out
				.map( function ( c ) {
					return ( '0' + c.toString( 16 ) ).slice( -2 );
				} )
				.join( '' )
		);
	}

	function current( id, fallback ) {
		var setting = wp.customize( id );
		return setting ? setting.get() : fallback;
	}

	/** Recomputes every colour token from the three source colours. */
	function syncColors() {
		var accent = current( 'souq_color_accent', '#8b4a38' );
		var ink = current( 'souq_color_ink', '#26221e' );
		var ground = current( 'souq_color_ground', '#fdfaf3' );
		var surface = current( 'souq_color_surface', '#f6f1e6' );

		root.style.setProperty( '--souq-color-accent', accent );
		root.style.setProperty( '--souq-color-accent-deep', mix( accent, ink, 0.22 ) );
		root.style.setProperty( '--souq-color-accent-soft', mix( accent, ground, 0.9 ) );
		root.style.setProperty( '--souq-color-accent-soft-deep', mix( accent, ground, 0.78 ) );
		root.style.setProperty( '--souq-color-ink', ink );
		root.style.setProperty( '--souq-color-ground', ground );
		root.style.setProperty( '--souq-color-surface', surface );
		// Matches Customizer::output_tokens_css(): 0.42 measured 3.9:1 and
		// failed WCAG AA, so the muted tone is blended darker.
		root.style.setProperty( '--souq-color-muted', mix( ink, ground, 0.34 ) );
		root.style.setProperty( '--souq-color-hairline', mix( ink, ground, 0.84 ) );
	}

	[ 'souq_color_accent', 'souq_color_ink', 'souq_color_ground', 'souq_color_surface' ].forEach(
		function ( id ) {
			wp.customize( id, function ( value ) {
				value.bind( syncColors );
			} );
		}
	);

	wp.customize( 'souq_font_pairing', function ( value ) {
		value.bind( function ( key ) {
			var pairing = data.pairings[ key ];
			if ( ! pairing ) {
				return;
			}
			root.style.setProperty( '--souq-font-display', pairing.display );
			root.style.setProperty( '--souq-font-body', pairing.body );

			// Pull the webfont in on the fly so the preview isn't stuck on a
			// fallback until the page is republished and reloaded.
			if ( pairing.google && pairing.google.length ) {
				var id = 'souq-preview-fonts';
				var link = document.getElementById( id ) || document.createElement( 'link' );
				link.id = id;
				link.rel = 'stylesheet';
				link.href =
					'https://fonts.googleapis.com/css2?family=' +
					pairing.google.map( encodeURIComponent ).join( '&family=' ) +
					'&display=swap';
				if ( ! link.parentNode ) {
					document.head.appendChild( link );
				}
			}
		} );
	} );

	wp.customize( 'souq_shape', function ( value ) {
		value.bind( function ( key ) {
			var shape = data.shapes[ key ];
			if ( ! shape ) {
				return;
			}
			root.style.setProperty( '--souq-radius-sm', shape.sm );
			root.style.setProperty( '--souq-radius-md', shape.md );
		} );
	} );

	wp.customize( 'souq_label_tracking', function ( value ) {
		value.bind( function ( newValue ) {
			root.style.setProperty( '--souq-tracking-label', Number( newValue ) / 100 + 'em' );
		} );
	} );

	wp.customize( 'souq_grid_columns', function ( value ) {
		value.bind( function ( newValue ) {
			root.style.setProperty( '--souq-grid-columns', String( newValue ) );
		} );
	} );

	wp.customize( 'souq_card_aspect', function ( value ) {
		value.bind( function ( newValue ) {
			root.style.setProperty( '--souq-card-aspect', newValue );
		} );
	} );

	// Wordmark fallback tracks the site title as it's typed.
	wp.customize( 'blogname', function ( value ) {
		value.bind( function ( newValue ) {
			document.querySelectorAll( '.souq-wordmark__text' ).forEach( function ( el ) {
				el.textContent = newValue;
			} );
		} );
	} );
} )( window.wp );
