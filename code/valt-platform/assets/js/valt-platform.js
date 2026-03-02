/* global jQuery, wp, valtPlatform */

(function ( $ ) {
	'use strict';

	// -----------------------------------------------------------------------
	// Tab switching
	// -----------------------------------------------------------------------

	function initTabs() {
		var $dashboard = $( '.valt-dashboard' );
		if ( ! $dashboard.length ) return;

		$dashboard.on( 'click', '.valt-tab-btn', function () {
			var tab = $( this ).data( 'tab' );

			$dashboard.find( '.valt-tab-btn' )
				.removeClass( 'valt-tab-btn--active' )
				.attr( 'aria-selected', 'false' );

			$( this )
				.addClass( 'valt-tab-btn--active' )
				.attr( 'aria-selected', 'true' );

			$dashboard.find( '.valt-tab-panel' ).removeClass( 'valt-tab-panel--active' );
			$dashboard.find( '#valt-tab-' + tab ).addClass( 'valt-tab-panel--active' );
		} );
	}

	// -----------------------------------------------------------------------
	// Profile photo upload via wp.media()
	// -----------------------------------------------------------------------

	function initPhotoUpload() {
		if ( ! $( '#valt-upload-photo' ).length ) return;

		var photoFrame;

		$( '#valt-upload-photo' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( photoFrame ) {
				photoFrame.open();
				return;
			}

			photoFrame = wp.media( {
				title:    'Select Profile Photo',
				button:   { text: 'Use this photo' },
				multiple: false,
				library:  { type: 'image' },
			} );

			photoFrame.on( 'select', function () {
				var attachment = photoFrame.state().get( 'selection' ).first().toJSON();
				var thumbUrl   = ( attachment.sizes && attachment.sizes.thumbnail )
					? attachment.sizes.thumbnail.url
					: attachment.url;

				$( '#valt-photo-id' ).val( attachment.id );

				var $preview = $( '#valt-photo-preview' );
				if ( $preview.is( 'img' ) ) {
					$preview.attr( 'src', thumbUrl );
				} else {
					$preview.replaceWith(
						'<img id="valt-photo-preview" src="' + thumbUrl + '"'
						+ ' class="valt-photo-preview__img" alt="Profile Photo">'
					);
				}

				$( '#valt-remove-photo' ).show();
			} );

			photoFrame.open();
		} );

		$( document ).on( 'click', '#valt-remove-photo', function ( e ) {
			e.preventDefault();
			$( '#valt-photo-id' ).val( '' );

			var $preview = $( '#valt-photo-preview' );
			if ( $preview.is( 'img' ) ) {
				$preview.replaceWith(
					'<div id="valt-photo-preview" class="valt-photo-preview__placeholder">No photo</div>'
				);
			}

			$( this ).hide();
		} );
	}

	// -----------------------------------------------------------------------
	// Profile form AJAX save
	// -----------------------------------------------------------------------

	function initProfileForm() {
		$( '#valt-profile-form' ).on( 'submit', function ( e ) {
			e.preventDefault();

			var $form = $( this );
			var $btn  = $form.find( '[type="submit"]' );
			var $msg  = $( '#valt-profile-message' );

			$btn.prop( 'disabled', true ).text( 'Saving\u2026' );
			$msg.hide()
				.removeClass( 'valt-dashboard__notice--success valt-dashboard__notice--error' );

			var data = $form.serializeArray();
			data.push( { name: 'action', value: 'valt_save_artist_profile' } );
			data.push( { name: 'nonce',  value: valtPlatform.nonce } );

			$.post( valtPlatform.ajaxUrl, data )
				.done( function ( response ) {
					if ( response.success ) {
						$msg.addClass( 'valt-dashboard__notice--success' )
							.text( response.data )
							.show();
					} else {
						$msg.addClass( 'valt-dashboard__notice--error' )
							.text( response.data || 'An error occurred.' )
							.show();
					}
				} )
				.fail( function () {
					$msg.addClass( 'valt-dashboard__notice--error' )
						.text( 'Network error. Please try again.' )
						.show();
				} )
				.always( function () {
					$btn.prop( 'disabled', false ).text( 'Save Profile' );
				} );
		} );
	}

	// -----------------------------------------------------------------------
	// Audio file upload via wp.media()
	// -----------------------------------------------------------------------

	function initAudioUpload() {
		if ( ! $( '#valt-upload-audio' ).length ) return;

		var audioFrame;

		$( '#valt-upload-audio' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( audioFrame ) {
				audioFrame.open();
				return;
			}

			audioFrame = wp.media( {
				title:    'Select Audio File',
				button:   { text: 'Use this file' },
				multiple: false,
				library:  { type: 'audio' },
			} );

			audioFrame.on( 'select', function () {
				var attachment = audioFrame.state().get( 'selection' ).first().toJSON();
				$( '#valt-audio-id' ).val( attachment.id );
				$( '#valt-audio-filename' ).text( attachment.filename || attachment.title || 'Audio selected' );
			} );

			audioFrame.open();
		} );
	}

	// -----------------------------------------------------------------------
	// Add Release form AJAX submit
	// -----------------------------------------------------------------------

	function initReleaseForm() {
		$( '#valt-release-form' ).on( 'submit', function ( e ) {
			e.preventDefault();

			var $form = $( this );
			var $btn  = $form.find( '[type="submit"]' );
			var $msg  = $( '#valt-release-message' );

			$btn.prop( 'disabled', true ).text( 'Adding\u2026' );
			$msg.hide()
				.removeClass( 'valt-dashboard__notice--success valt-dashboard__notice--error' );

			var data = $form.serializeArray();
			data.push( { name: 'action', value: 'valt_add_release' } );
			data.push( { name: 'nonce',  value: valtPlatform.nonce } );

			$.post( valtPlatform.ajaxUrl, data )
				.done( function ( response ) {
					if ( response.success ) {
						$msg.addClass( 'valt-dashboard__notice--success' )
							.text( response.data.message )
							.show();

						appendReleaseRow( response.data );

						// Reset form and audio state
						$form[ 0 ].reset();
						$( '#valt-audio-id' ).val( '' );
						$( '#valt-audio-filename' ).text( 'No file selected' );
					} else {
						$msg.addClass( 'valt-dashboard__notice--error' )
							.text( response.data || 'An error occurred.' )
							.show();
					}
				} )
				.fail( function () {
					$msg.addClass( 'valt-dashboard__notice--error' )
						.text( 'Network error. Please try again.' )
						.show();
				} )
				.always( function () {
					$btn.prop( 'disabled', false ).text( 'Add Release' );
				} );
		} );
	}

	/**
	 * Insert a new row into the releases table, creating the table if needed.
	 */
	function appendReleaseRow( data ) {
		var $wrap  = $( '#valt-releases-table-wrap' );
		var $tbody = $wrap.find( '.valt-table tbody' );

		var row = '<tr>'
			+ '<td>' + escHtml( data.title )    + '</td>'
			+ '<td>' + escHtml( data.album )    + '</td>'
			+ '<td>' + escHtml( data.duration ) + '</td>'
			+ '<td><span class="valt-badge valt-badge--grey">Uploaded</span></td>'
			+ '<td>&mdash;</td>'
			+ '</tr>';

		if ( $tbody.length ) {
			$tbody.prepend( row );
		} else {
			// Replace "no releases" notice with a fresh table
			var tableHtml = '<table class="valt-table">'
				+ '<thead><tr>'
				+ '<th>Title</th><th>Album</th><th>Duration</th>'
				+ '<th>Status</th><th>Minted</th>'
				+ '</tr></thead>'
				+ '<tbody>' + row + '</tbody>'
				+ '</table>';

			$wrap.find( '.valt-notice' ).replaceWith( tableHtml );
		}
	}

	/** Escape a string for safe insertion into HTML. */
	function escHtml( str ) {
		return String( str )
			.replace( /&/g,  '&amp;'  )
			.replace( /</g,  '&lt;'   )
			.replace( />/g,  '&gt;'   )
			.replace( /"/g,  '&quot;' )
			.replace( /'/g,  '&#039;' );
	}

	// -----------------------------------------------------------------------
	// Boot
	// -----------------------------------------------------------------------

	$( document ).ready( function () {
		if ( ! $( '.valt-dashboard' ).length ) return;

		initTabs();
		initPhotoUpload();
		initProfileForm();
		initAudioUpload();
		initReleaseForm();
	} );

} )( jQuery );
