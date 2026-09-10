/**
 * Editor UI for the Souq Product Grid block.
 *
 * Written without JSX deliberately: the theme has no build step, so the
 * editor script must be directly loadable by the browser.
 */
( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var ServerSideRender = serverSideRender;

	var categories = ( window.souqProductGridData && window.souqProductGridData.categories ) || [
		{ label: __( 'All categories', 'souq' ), value: '' },
	];

	blocks.registerBlockType( 'souq/product-grid', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Grid settings', 'souq' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Heading', 'souq' ),
							help: __( 'Leave empty to show the grid with no heading.', 'souq' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Show', 'souq' ),
							value: attributes.source,
							options: [
								{ label: __( 'Most recent', 'souq' ), value: 'recent' },
								{ label: __( 'Featured', 'souq' ), value: 'featured' },
								{ label: __( 'On sale', 'souq' ), value: 'sale' },
								{ label: __( 'Best selling', 'souq' ), value: 'best_selling' },
								{ label: __( 'Recommended', 'souq' ), value: 'recommended' },
							],
							onChange: function ( value ) {
								setAttributes( { source: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Category', 'souq' ),
							value: attributes.category,
							options: categories,
							onChange: function ( value ) {
								setAttributes( { category: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Number of products', 'souq' ),
							value: attributes.count,
							min: 1,
							max: 24,
							onChange: function ( value ) {
								setAttributes( { count: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Columns', 'souq' ),
							help: __( '0 follows the sitewide setting in Customizer → Layout & Shape.', 'souq' ),
							value: attributes.columns,
							min: 0,
							max: 6,
							onChange: function ( value ) {
								setAttributes( { columns: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show “View all” link', 'souq' ),
							checked: !! attributes.showViewAll,
							onChange: function ( value ) {
								setAttributes( { showViewAll: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps ? blockEditor.useBlockProps() : {},
					el( ServerSideRender, {
						block: 'souq/product-grid',
						attributes: attributes,
					} )
				)
			);
		},

		// Server-rendered: nothing is stored in post content but the attributes.
		save: function () {
			return null;
		},
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
