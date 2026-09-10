/**
 * Header interactions: mobile drawer and search panel toggles, each with a
 * focus trap and Escape-to-close, shared through one small helper.
 */
( function () {
	'use strict';

	/**
	 * Wires open/close behavior for a dismissible panel: toggle button,
	 * close button(s), Escape key, and a focus trap while open.
	 */
	function createPanel( { toggle, panel, closeButtons, onOpen, onClose, trapFocus } ) {
		if ( ! toggle || ! panel ) {
			return null;
		}

		let isOpen = false;
		let lastFocused = null;

		function getFocusable() {
			return Array.from(
				panel.querySelectorAll(
					'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
				)
			);
		}

		function open() {
			if ( isOpen ) {
				return;
			}
			isOpen = true;
			lastFocused = document.activeElement;
			panel.hidden = false;
			toggle.setAttribute( 'aria-expanded', 'true' );
			document.addEventListener( 'keydown', onKeydown );
			if ( onOpen ) {
				onOpen();
			}
			const focusable = getFocusable();
			if ( trapFocus && focusable.length ) {
				focusable[ 0 ].focus();
			}
		}

		function close() {
			if ( ! isOpen ) {
				return;
			}
			isOpen = false;
			panel.hidden = true;
			toggle.setAttribute( 'aria-expanded', 'false' );
			document.removeEventListener( 'keydown', onKeydown );
			if ( onClose ) {
				onClose();
			}
			if ( lastFocused ) {
				lastFocused.focus();
			}
		}

		function onKeydown( event ) {
			if ( event.key === 'Escape' ) {
				close();
				return;
			}
			if ( event.key !== 'Tab' || ! trapFocus ) {
				return;
			}
			const focusable = getFocusable();
			if ( ! focusable.length ) {
				return;
			}
			const first = focusable[ 0 ];
			const last = focusable[ focusable.length - 1 ];
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}

		toggle.addEventListener( 'click', function () {
			isOpen ? close() : open();
		} );

		( closeButtons || [] ).forEach( function ( btn ) {
			if ( btn ) {
				btn.addEventListener( 'click', close );
			}
		} );

		return { open, close, isOpen: () => isOpen };
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		const drawerToggle = document.querySelector( '.souq-header__toggle' );
		const drawer = document.getElementById( 'souq-mobile-drawer' );
		const drawerClose = document.getElementById( 'souq-drawer-close' );
		const body = document.body;

		const drawerPanel = createPanel( {
			toggle: drawerToggle,
			panel: drawer,
			closeButtons: [ drawerClose ],
			trapFocus: true,
			onOpen: function () {
				body.style.overflow = 'hidden';
			},
			onClose: function () {
				body.style.overflow = '';
			},
		} );

		const searchToggle = document.getElementById( 'souq-search-toggle' );
		const searchPanel = document.getElementById( 'souq-search-panel' );
		const searchClose = document.getElementById( 'souq-search-close' );

		createPanel( {
			toggle: searchToggle,
			panel: searchPanel,
			closeButtons: [ searchClose ],
			trapFocus: true,
			onOpen: function () {
				if ( drawerPanel && drawerPanel.isOpen() ) {
					drawerPanel.close();
				}
				const input = document.getElementById( 'souq-search-input' );
				if ( input ) {
					input.focus();
				}
			},
		} );

		// Close the drawer if the viewport grows past the mobile breakpoint
		// while it's open, so it never gets stuck open on desktop after resize.
		const mq = window.matchMedia( '(min-width: 1024px)' );
		mq.addEventListener( 'change', function ( e ) {
			if ( e.matches && drawerPanel && drawerPanel.isOpen() ) {
				drawerPanel.close();
			}
		} );

		initNewsletterForms();
		initStickyHeader();
	} );

	/**
	 * The header carries no bottom hairline while the page is at the top, so
	 * it sits silently over full-bleed hero imagery; the rule fades in once
	 * content scrolls beneath it.
	 */
	function initStickyHeader() {
		const header = document.getElementById( 'souq-header' );
		if ( ! header ) {
			return;
		}

		// Publish the header's real height. The hero pulls itself up by this
		// amount to sit under an overlaid header, and the header's height is
		// not fixed: the stacked layout adds a second row, and the wordmark
		// scales with the chosen typeface. Using the static token here clipped
		// the top of the hero whenever the real header was taller.
		function syncHeight() {
			document.documentElement.style.setProperty(
				'--souq-header-actual',
				header.offsetHeight + 'px'
			);
		}

		syncHeight();

		if ( window.ResizeObserver ) {
			new ResizeObserver( syncHeight ).observe( header );
		} else {
			window.addEventListener( 'resize', syncHeight );
		}

		// Webfonts land after first paint and change the wordmark's height.
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( syncHeight );
		}

		// A zero-height sentinel above the header is cheaper and smoother than
		// listening to scroll and reading offsets on every frame.
		const sentinel = document.createElement( 'div' );
		sentinel.setAttribute( 'aria-hidden', 'true' );
		sentinel.style.cssText = 'position:absolute;top:0;left:0;width:1px;height:1px;pointer-events:none;';
		document.body.prepend( sentinel );

		if ( ! window.IntersectionObserver ) {
			header.classList.add( 'is-stuck' );
			return;
		}

		new IntersectionObserver(
			function ( entries ) {
				header.classList.toggle( 'is-stuck', ! entries[ 0 ].isIntersecting );
			},
			{ threshold: 0 }
		).observe( sentinel );
	}

	function initNewsletterForms() {
		document.querySelectorAll( '[data-souq-newsletter]' ).forEach( function ( form ) {
			const status = form.parentElement.querySelector( '[data-souq-newsletter-status]' );
			const button = form.querySelector( 'button[type="submit"]' );

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				const email = form.querySelector( 'input[type="email"]' ).value;

				if ( button ) {
					button.disabled = true;
				}
				if ( status ) {
					status.textContent = '';
					status.removeAttribute( 'data-state' );
				}

				const endpoint = ( window.souqConfig && window.souqConfig.restUrl ) ? window.souqConfig.restUrl + 'newsletter' : '/wp-json/souq/v1/newsletter';

				fetch( endpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( { email: email } ),
				} )
					.then( function ( response ) {
						return response.json().then( function ( data ) {
							return { ok: response.ok, data: data };
						} );
					} )
					.then( function ( result ) {
						if ( status ) {
							status.textContent = result.data.message || ( result.ok ? '' : 'Something went wrong.' );
							status.setAttribute( 'data-state', result.ok ? 'success' : 'error' );
						}
						if ( result.ok ) {
							form.reset();
						}
					} )
					.catch( function () {
						if ( status ) {
							status.textContent = 'Something went wrong. Please try again.';
							status.setAttribute( 'data-state', 'error' );
						}
					} )
					.finally( function () {
						if ( button ) {
							button.disabled = false;
						}
					} );
			} );
		} );
	}
} )();
