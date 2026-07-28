/**
 * Color picker + media uploader wiring for the attribute term "Swatch
 * Color" / "Swatch Image" fields added by APF_Term_Swatches.
 *
 * This admin-only screen already depends on jQuery via wp-color-picker
 * and wp.media, so it intentionally uses jQuery rather than the
 * dependency-free vanilla JS used on the storefront.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		$( '#apf-swatch-color' ).wpColorPicker();

		var frame = null;

		$( document ).on( 'click', '.apf-select-swatch-image', function ( event ) {
			event.preventDefault();

			var $button = $( this );
			var $wrapper = $button.closest( '.apf-term-swatch-field, .form-field' );
			var $input = $wrapper.find( '#apf-swatch-image' );
			var $preview = $wrapper.find( '.apf-swatch-image-preview' );
			var $remove = $wrapper.find( '.apf-remove-swatch-image' );

			frame = wp.media( {
				title: apfTermSwatchesI18n.selectImage,
				button: { text: apfTermSwatchesI18n.useImage },
				multiple: false,
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();

				$input.val( attachment.id );
				$preview.html( '<img src="' + attachment.url + '" alt="" />' );
				$remove.prop( 'hidden', false );
			} );

			frame.open();
		} );

		$( document ).on( 'click', '.apf-remove-swatch-image', function ( event ) {
			event.preventDefault();

			var $wrapper = $( this ).closest( '.apf-term-swatch-field, .form-field' );

			$wrapper.find( '#apf-swatch-image' ).val( '' );
			$wrapper.find( '.apf-swatch-image-preview' ).empty();
			$( this ).prop( 'hidden', true );
		} );
	} );
} )( jQuery );
