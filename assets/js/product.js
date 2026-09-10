/**
 * Single product page behaviour.
 *
 * The variation picker is a presentation layer over WooCommerce's own hidden
 * <select> elements: our buttons set the select's value and dispatch change,
 * and WooCommerce's wc-add-to-cart-variation.js remains the authority on
 * price, stock, gallery sync and which combinations exist.
 */
( function ( $ ) {
	'use strict';

	/* ---------------------------------------------------------------------
	   Variation picker
	   --------------------------------------------------------------------- */

	function initVariationPicker() {
		var form = document.querySelector( '.variations_form' );
		if ( ! form ) {
			return;
		}

		var rows = [];

		document.querySelectorAll( '.souq-variation-row' ).forEach( function ( row ) {
			var attribute = row.getAttribute( 'data-attribute' );
			var select = document.getElementById( 'souq-select-' + attribute );
			var valueLabel = row.querySelector( '[data-souq-selected-label]' );
			if ( ! select ) {
				return;
			}

			var buttons = Array.prototype.slice.call( row.querySelectorAll( '[data-value]' ) );

			buttons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					// WooCommerce prunes <option>s from the other attributes'
					// selects as combinations are ruled out. If this value no
					// longer has a matching option, setting it would silently
					// no-op, so treat it as unavailable instead.
					if ( button.getAttribute( 'data-available' ) === 'false' ) {
						return;
					}

					var isSelected = button.getAttribute( 'aria-pressed' ) === 'true';
					$( select ).val( isSelected ? '' : button.getAttribute( 'data-value' ) ).trigger( 'change' );
				} );
			} );

			// Reflect programmatic changes too (WooCommerce's "Clear" link,
			// deep links with attributes in the query string).
			$( select ).on( 'change', function () {
				var value = select.value;
				var matched = null;

				buttons.forEach( function ( button ) {
					var isMatch = value !== '' && button.getAttribute( 'data-value' ) === value;
					button.setAttribute( 'aria-pressed', String( isMatch ) );
					if ( isMatch ) {
						matched = button;
					}
				} );

				if ( valueLabel ) {
					valueLabel.textContent = matched ? matched.getAttribute( 'data-label' ) : '';
				}

				updateStickyVariant();
			} );

			rows.push( { select: select, buttons: buttons } );
		} );

		/**
		 * Re-derives availability from the live <option> list WooCommerce has
		 * just recalculated, replacing the coarse server-rendered hint.
		 */
		function syncAvailability() {
			rows.forEach( function ( row ) {
				var live = Array.prototype.map.call( row.select.options, function ( option ) {
					return option.value;
				} );

				row.buttons.forEach( function ( button ) {
					var value = button.getAttribute( 'data-value' );
					var offered = live.indexOf( value ) !== -1;
					var label = button.getAttribute( 'data-label' ) || button.textContent.trim();

					button.setAttribute( 'data-available', String( offered ) );
					button.setAttribute( 'aria-label', offered ? label : label + ' — unavailable' );
				} );
			} );
		}

		// Deferred a tick: WooCommerce's handler for this same event is what
		// filters the options, and bind order between the two isn't guaranteed.
		$( form ).on( 'update_variation_values', function () {
			window.setTimeout( syncAvailability, 0 );
		} );

		function updateStickyVariant() {
			var target = document.querySelector( '[data-souq-sticky-variant]' );
			if ( ! target ) {
				return;
			}
			var parts = rows
				.map( function ( row ) {
					var selected = row.buttons.filter( function ( b ) {
						return b.getAttribute( 'aria-pressed' ) === 'true';
					} )[ 0 ];
					return selected ? selected.getAttribute( 'data-label' ) : null;
				} )
				.filter( Boolean );

			target.textContent = parts.join( ' / ' );
		}
	}

	/* ---------------------------------------------------------------------
	   Information accordion
	   --------------------------------------------------------------------- */

	function initAccordions() {
		document.querySelectorAll( '[data-souq-accordion]' ).forEach( function ( accordion ) {
			accordion.querySelectorAll( '.souq-accordion__trigger' ).forEach( function ( trigger ) {
				var panel = document.getElementById( trigger.getAttribute( 'aria-controls' ) );
				if ( ! panel ) {
					return;
				}

				trigger.addEventListener( 'click', function () {
					var isOpen = trigger.getAttribute( 'aria-expanded' ) === 'true';
					trigger.setAttribute( 'aria-expanded', String( ! isOpen ) );
					panel.hidden = isOpen;
				} );
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	   Recommended products
	   --------------------------------------------------------------------- */

	function initRecommendations() {
		var items = document.querySelectorAll( '.souq-recommend__item' );
		if ( ! items.length ) {
			return;
		}

		function close( item ) {
			var header = item.querySelector( '.souq-recommend__header' );
			var panel = item.querySelector( '.souq-recommend__panel' );
			if ( header && panel ) {
				header.setAttribute( 'aria-expanded', 'false' );
				panel.hidden = true;
			}
		}

		/**
		 * A product open in one list must not also appear, collapsed, in any
		 * other list — or in a product-grid card — anywhere on the page:
		 * never show the same product twice in one view (PRODUCT.md
		 * principle 2). Matches any element carrying that product's
		 * data-souq-product-id, not just other recommend-list rows, since a
		 * "Recommended" Product Grid block (see class-souq-product-grid-
		 * block.php) can in principle share a page with a recommend list.
		 */
		function broadcast( productId, isOpen ) {
			document
				.querySelectorAll( '[data-souq-product-id="' + productId + '"]' )
				.forEach( function ( match ) {
					var header = match.querySelector( '.souq-recommend__header' );
					if ( header && header.getAttribute( 'aria-expanded' ) === 'true' ) {
						return; // Never hide the instance that is actually open.
					}
					match.classList.toggle( 'is-hidden-elsewhere', isOpen );
				} );
		}

		items.forEach( function ( item ) {
			var header = item.querySelector( '.souq-recommend__header' );
			var panel = item.querySelector( '.souq-recommend__panel' );
			var list = item.closest( '.souq-recommend__list' );
			var productId = item.getAttribute( 'data-souq-product-id' );

			if ( ! header || ! panel ) {
				return;
			}

			header.addEventListener( 'click', function () {
				var isOpen = header.getAttribute( 'aria-expanded' ) === 'true';

				if ( isOpen ) {
					close( item );
					broadcast( productId, false );
					return;
				}

				// One row open at a time within a list.
				list.querySelectorAll( '.souq-recommend__header[aria-expanded="true"]' ).forEach( function ( open ) {
					var openItem = open.closest( '.souq-recommend__item' );
					close( openItem );
					broadcast( openItem.getAttribute( 'data-souq-product-id' ), false );
				} );

				header.setAttribute( 'aria-expanded', 'true' );
				panel.hidden = false;
				broadcast( productId, true );
			} );
		} );
	}

	/* ---------------------------------------------------------------------
	   Sticky add-to-bag bar
	   --------------------------------------------------------------------- */

	function initStickyCta() {
		var bar = document.getElementById( 'souq-sticky-cta' );
		var addButton = document.querySelector( '.souq-product__summary .single_add_to_cart_button' );

		if ( ! bar || ! addButton || ! window.IntersectionObserver ) {
			return;
		}

		new IntersectionObserver(
			function ( entries ) {
				bar.classList.toggle( 'is-visible', ! entries[ 0 ].isIntersecting );
			},
			{ rootMargin: '0px 0px -10% 0px' }
		).observe( addButton );

		var barButton = bar.querySelector( '.souq-sticky-cta__button' );
		if ( ! barButton ) {
			return;
		}

		barButton.addEventListener( 'click', function () {
			// If every attribute is already chosen the bar can submit directly;
			// otherwise send the shopper up to make a selection rather than
			// failing silently.
			var form = document.querySelector( '.variations_form' );
			var variationId = form ? form.querySelector( 'input.variation_id' ) : null;

			if ( ! form || ( variationId && variationId.value && variationId.value !== '0' ) ) {
				addButton.click();
				return;
			}

			addButton.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			var firstUnset = document.querySelector( '.souq-variation-row' );
			if ( firstUnset ) {
				var focusable = firstUnset.querySelector( '[data-value]' );
				if ( focusable ) {
					focusable.focus( { preventScroll: true } );
				}
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initVariationPicker();
		initAccordions();
		initRecommendations();
		initStickyCta();
	} );
} )( window.jQuery );
