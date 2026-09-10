/**
 * Debounced predictive search against the /souq/v1/search REST route.
 * Progressive enhancement: the search form still submits to the normal
 * WordPress search results page (?post_type=product&s=) with JS disabled.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const config = window.souqSearch;
		const input = document.getElementById( 'souq-search-input' );
		const results = document.getElementById( 'souq-search-results' );

		if ( ! config || ! input || ! results ) {
			return;
		}

		let debounceTimer = null;
		let activeController = null;
		let latestQuery = '';

		function escapeHtml( str ) {
			const div = document.createElement( 'div' );
			div.textContent = str;
			return div.innerHTML;
		}

		function renderLoading() {
			results.innerHTML =
				'<div class="souq-search-results__loading"><span class="souq-search-results__spinner" aria-hidden="true"></span>' +
				escapeHtml( config.strings.loading || '' ) +
				'</div>';
		}

		function renderEmpty() {
			results.innerHTML =
				'<div class="souq-search-results__empty">' +
				'<p>' + escapeHtml( config.strings.noResults ) + '</p>' +
				'</div>';
		}

		function renderResults( products, query ) {
			if ( ! products.length ) {
				renderEmpty();
				return;
			}

			const items = products
				.map( function ( product ) {
					return (
						'<li>' +
						'<a class="souq-search-result" href="' + product.url + '">' +
						'<img class="souq-search-result__thumb" src="' + product.thumbnail + '" alt="" loading="lazy" width="48" height="60">' +
						'<span class="souq-search-result__body">' +
						'<span class="souq-search-result__name">' + escapeHtml( product.name ) + '</span>' +
						'<span class="souq-search-result__meta">' + escapeHtml( product.category || '' ) + '</span>' +
						'</span>' +
						'<span class="souq-search-result__price">' + product.price_html + '</span>' +
						'</a>' +
						'</li>'
					);
				} )
				.join( '' );

			const viewAllUrl = new URL( window.location.origin + '/' );
			viewAllUrl.searchParams.set( 's', query );
			viewAllUrl.searchParams.set( 'post_type', 'product' );

			results.innerHTML =
				'<ul class="souq-search-results__list">' + items + '</ul>' +
				'<div class="souq-search-results__footer">' +
				'<a class="souq-btn souq-btn--secondary" href="' + viewAllUrl.toString() + '">' +
				escapeHtml( config.strings.viewAll ) +
				'</a></div>';
		}

		function search( query ) {
			if ( activeController ) {
				activeController.abort();
			}
			activeController = new AbortController();
			renderLoading();

			const url = new URL( config.endpoint );
			url.searchParams.set( 'query', query );

			fetch( url.toString(), {
				headers: { 'X-WP-Nonce': config.nonce },
				signal: activeController.signal,
			} )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Search request failed' );
					}
					return response.json();
				} )
				.then( function ( data ) {
					if ( query !== latestQuery ) {
						return;
					}
					renderResults( data.products || [], query );
				} )
				.catch( function ( error ) {
					if ( error.name !== 'AbortError' ) {
						renderEmpty();
					}
				} );
		}

		input.addEventListener( 'input', function () {
			const query = input.value.trim();
			latestQuery = query;

			clearTimeout( debounceTimer );

			if ( query.length < config.minChars ) {
				results.innerHTML = '';
				return;
			}

			debounceTimer = setTimeout( function () {
				search( query );
			}, config.debounce );
		} );
	} );
} )();
