/**
 * Wires the native WP color picker onto the attribute-term "Swatch Color"
 * field (class-souq-attribute-swatches.php).
 */
jQuery( function ( $ ) {
	$( '.souq-color-field' ).wpColorPicker();

	$( '.souq-choose-variation-image' ).on( 'click', function () {
		var button = $( this );
		var row = button.closest( '.souq-variation-image-row' );
		var input = row.find( '.souq-variation-image-id' );
		var preview = row.find( '.souq-variation-image-preview' );
		var allowed = [];
		try {
			allowed = JSON.parse( button.attr( 'data-allowed' ) || '[]' );
		} catch ( error ) {
			allowed = [];
		}
		if ( ! allowed.length ) {
			return;
		}

		var frame = wp.media({
			title: 'Choose variation image',
			button: { text: 'Use this image' },
			multiple: false,
			library: { type: 'image', include: allowed },
		});

		frame.on( 'open', function () {
			var selection = frame.state().get( 'selection' );
			var currentId = parseInt( input.val(), 10 );
			if ( currentId && allowed.indexOf( currentId ) !== -1 ) {
				selection.add( wp.media.attachment( currentId ) );
			}
		});

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first();
			if ( ! attachment ) {
				return;
			}
			var id = parseInt( attachment.id, 10 );
			if ( allowed.indexOf( id ) === -1 ) {
				window.alert( 'Choose an image from this product’s gallery.' );
				return;
			}
			input.val( String( id ) );
			var sizes = attachment.attributes.sizes || {};
			var thumb = sizes.thumbnail && sizes.thumbnail.url ? sizes.thumbnail.url : attachment.attributes.url;
			preview.html( '<img src="' + thumb + '" alt="" style="width:80px;height:80px;object-fit:cover;">' );
			row.find( '.souq-clear-variation-image' ).show();
		});

		frame.open();
	});

	$( '.souq-clear-variation-image' ).on( 'click', function () {
		var row = $( this ).closest( '.souq-variation-image-row' );
		row.find( '.souq-variation-image-id' ).val( '' );
		row.find( '.souq-variation-image-preview' ).html(
			'<span class="souq-variation-image-placeholder" style="display:flex;width:80px;height:80px;align-items:center;justify-content:center;background:#f0f0f1;color:#646970;">—</span>'
		);
		$( this ).hide();
	} );
} );
