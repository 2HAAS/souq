/**
 * Image sliders: the product-card slider on listings and the gallery on the
 * product page.
 *
 * Both are progressive enhancement over markup that already shows the first
 * image, so with JavaScript off a shopper still sees the garment.
 */
( function () {
	'use strict';

	/**
	 * Minimal slider core shared by both surfaces. Slides are cross-faded via
	 * a class rather than translated, so nothing animates a layout property.
	 */
	function createSlider( options ) {
		var slides = options.slides;
		var total = slides.length;
		var index = 0;

		if ( total < 2 ) {
			return null;
		}

		function show( next, announce ) {
			index = ( next + total ) % total;

			slides.forEach( function ( slide, i ) {
				slide.classList.toggle( 'is-active', i === index );
			} );

			if ( options.onChange ) {
				options.onChange( index, total, announce );
			}
		}

		function next() {
			show( index + 1, true );
		}

		function prev() {
			show( index - 1, true );
		}

		if ( options.prevButton ) {
			options.prevButton.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				prev();
			} );
		}

		if ( options.nextButton ) {
			options.nextButton.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				e.stopPropagation();
				next();
			} );
		}

		// Touch: a horizontal drag of more than 40px pages the slider. The
		// threshold keeps a vertical page scroll from being read as a swipe.
		var startX = null;
		var startY = null;

		options.root.addEventListener(
			'touchstart',
			function ( e ) {
				startX = e.touches[ 0 ].clientX;
				startY = e.touches[ 0 ].clientY;
			},
			{ passive: true }
		);

		options.root.addEventListener(
			'touchend',
			function ( e ) {
				if ( startX === null ) {
					return;
				}
				var dx = e.changedTouches[ 0 ].clientX - startX;
				var dy = e.changedTouches[ 0 ].clientY - startY;

				if ( Math.abs( dx ) > 40 && Math.abs( dx ) > Math.abs( dy ) ) {
					if ( dx < 0 ) {
						next();
					} else {
						prev();
					}
				}
				startX = null;
				startY = null;
			},
			{ passive: true }
		);

		return {
			next: next,
			prev: prev,
			goTo: function ( i, announce ) {
				show( i, announce );
			},
			current: function () {
				return index;
			},
			total: total,
		};
	}

	/* ---------------------------------------------------------------------
	   Product card slider
	   --------------------------------------------------------------------- */

	function initCardSliders() {
		document.querySelectorAll( '[data-souq-card-slider]' ).forEach( function ( media ) {
			var slides = Array.prototype.slice.call( media.querySelectorAll( '.souq-card__image' ) );
			var counter = media.querySelector( '[data-souq-card-counter]' );

			var slider = createSlider( {
				root: media,
				slides: slides,
				prevButton: media.querySelector( '[data-souq-card-prev]' ),
				nextButton: media.querySelector( '[data-souq-card-next]' ),
				onChange: function ( i, total ) {
					if ( counter ) {
						counter.textContent = i + 1 + '/' + total;
					}
				},
			} );

			if ( ! slider ) {
				return;
			}

			// Arrow keys work once anything inside the card has focus, so the
			// slider is reachable without a pointer.
			var card = media.closest( '.souq-card' );
			if ( card ) {
				card.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'ArrowRight' ) {
						e.preventDefault();
						slider.next();
					} else if ( e.key === 'ArrowLeft' ) {
						e.preventDefault();
						slider.prev();
					}
				} );
			}
		} );
	}

	/* ---------------------------------------------------------------------
	   Product page gallery
	   --------------------------------------------------------------------- */

	function initGallery() {
		var gallery = document.querySelector( '[data-souq-gallery]' );
		if ( ! gallery ) {
			return;
		}

		var slides = Array.prototype.slice.call( gallery.querySelectorAll( '.souq-gallery__slide' ) );
		var thumbs = Array.prototype.slice.call( gallery.querySelectorAll( '[data-souq-gallery-thumb]' ) );
		var counter = gallery.querySelector( '[data-souq-gallery-counter]' );
		var live = gallery.querySelector( '[data-souq-gallery-live]' );

		var slider = createSlider( {
			root: gallery,
			slides: slides,
			prevButton: gallery.querySelector( '[data-souq-gallery-prev]' ),
			nextButton: gallery.querySelector( '[data-souq-gallery-next]' ),
			onChange: function ( i, total, announce ) {
				if ( counter ) {
					counter.textContent = i + 1 + ' / ' + total;
				}

				thumbs.forEach( function ( thumb, ti ) {
					thumb.classList.toggle( 'is-active', ti === i );
					thumb.setAttribute( 'aria-selected', String( ti === i ) );
				} );

				// Only announce deliberate navigation, not the initial paint
				// or a programmatic jump triggered by a variation change.
				if ( announce && live ) {
					live.textContent = 'Image ' + ( i + 1 ) + ' of ' + total;
				}
			},
		} );

		if ( ! slider ) {
			return;
		}

		thumbs.forEach( function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				slider.goTo( parseInt( thumb.getAttribute( 'data-souq-gallery-thumb' ), 10 ), true );
			} );
		} );

		gallery.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowRight' ) {
				e.preventDefault();
				slider.next();
			} else if ( e.key === 'ArrowLeft' ) {
				e.preventDefault();
				slider.prev();
			}
		} );

		// Variation sync. WooCommerce hands us the chosen variation; if its
		// image is one of our slides, move to it. jQuery is only present on
		// product pages, hence the guard.
		var $ = window.jQuery;
		if ( ! $ ) {
			return;
		}

		var form = document.querySelector( '.variations_form' );
		if ( ! form ) {
			return;
		}

		$( form ).on( 'found_variation', function ( event, variation ) {
			if ( ! variation || ! variation.image_id ) {
				return;
			}

			var target = slides.findIndex( function ( slide ) {
				return slide.getAttribute( 'data-attachment-id' ) === String( variation.image_id );
			} );

			if ( target !== -1 && target !== slider.current() ) {
				slider.goTo( target, false );
			}
		} );

		$( form ).on( 'reset_data', function () {
			slider.goTo( 0, false );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initCardSliders();
		initGallery();
	} );
} )();
