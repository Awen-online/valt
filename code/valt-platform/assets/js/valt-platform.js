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
	// Boot (artist dashboard)
	// -----------------------------------------------------------------------

	$( document ).ready( function () {
		if ( $( '.valt-dashboard' ).length ) {
			initTabs();
			initPhotoUpload();
			initProfileForm();
			initAudioUpload();
			initReleaseForm();
		}

		// ── Site-wide: nav toggle ────────────────────────────────────
		$( '[data-nav-toggle]' ).on( 'click', function () {
			$( '[data-nav-menu]' ).toggleClass( 'is-open' );
		} );

		// ── Site-wide: generic tabs (leaderboard, fan dashboard, etc.) ─
		$( document ).on( 'click', '.valt-tab-btn', function () {
			var $btn   = $( this );
			var tab    = $btn.data( 'tab' );
			var $wrap  = $btn.closest( '.valt-tabs' ).parent();

			$btn.siblings( '.valt-tab-btn' ).removeClass( 'valt-tab-btn--active' );
			$btn.addClass( 'valt-tab-btn--active' );

			$wrap.find( '.valt-tab-panel' ).removeClass( 'valt-tab-panel--active' );
			$wrap.find( '[data-panel="' + tab + '"]' ).addClass( 'valt-tab-panel--active' );
		} );

		// ── Discovery: AJAX search/filter ────────────────────────────
		var discoveryTimer;
		$( '.valt-discovery' ).each( function () {
			var $disc    = $( this );
			var $grid    = $disc.find( '.valt-discovery__grid' );
			var perPage  = $disc.data( 'per-page' ) || 12;
			var page     = 1;

			function loadArtists( append ) {
				if ( ! append ) page = 1;
				var params = {
					search:   $disc.find( '[data-filter="search"]' ).val() || '',
					genre:    $disc.find( '[data-filter="genre"]' ).val() || '',
					country:  $disc.find( '[data-filter="country"]' ).val() || '',
					sort:     $disc.find( '[data-filter="sort"]' ).val() || 'trending',
					page:     page,
					per_page: perPage,
				};

				$.getJSON( valtPlatform.restUrl + 'discover/artists', params, function ( data ) {
					var artists = data.artists || data || [];
					var html = '';
					$.each( artists, function ( i, a ) {
						html += '<a href="' + escHtml( a.url ) + '" class="valt-song-grid__item">'
							+ '<div class="valt-song-grid__art">'
							+ ( a.thumbnail_url ? '<img src="' + escHtml( a.thumbnail_url ) + '" alt="' + escHtml( a.name ) + '" loading="lazy">' : '<div class="valt-song-grid__placeholder"></div>' )
							+ '</div>'
							+ '<div class="valt-song-grid__info">'
							+ '<strong class="valt-song-grid__title">' + escHtml( a.name ) + '</strong>'
							+ ( a.genre ? '<span class="valt-song-grid__artist">' + escHtml( a.genre ) + '</span>' : '' )
							+ '<span class="valt-song-grid__meta">' + ( a.fan_count || 0 ) + ' fans' + ( a.country ? ' &middot; ' + escHtml( a.country ) : '' ) + '</span>'
							+ '</div></a>';
					} );

					if ( append ) { $grid.append( html ); }
					else { $grid.html( html || '<p>No artists found.</p>' ); }

					var $more = $disc.find( '.valt-discovery__load-more' );
					$more.toggle( ( data.pages || 0 ) > page );
				} );
			}

			// Initial load.
			loadArtists();

			// Filter changes.
			$disc.find( '[data-filter]' ).on( 'change', function () { loadArtists(); } );
			$disc.find( '[data-filter="search"]' ).on( 'input', function () {
				clearTimeout( discoveryTimer );
				discoveryTimer = setTimeout( function () { loadArtists(); }, 400 );
			} );

			// Load more.
			$disc.find( '.valt-discovery__load-more' ).on( 'click', 'button', function () {
				page++;
				loadArtists( true );
			} );
		} );

		// ── Mint button ──────────────────────────────────────────────
		$( document ).on( 'click', '[data-action="mint"]', function () {
			var $wrap   = $( this ).closest( '.valt-mint' );
			var songId  = $wrap.data( 'song-id' );
			var wallet  = $wrap.find( '[data-wallet]' ).val();
			var $status = $wrap.find( '[data-mint-status]' );

			if ( ! wallet ) { $status.text( 'Please enter your wallet address.' ); return; }

			$( this ).prop( 'disabled', true ).text( 'Minting...' );
			$status.text( 'Scheduling mint...' );

			$.post( valtPlatform.ajaxUrl, {
				action: 'valt_mint_song_nft',
				nonce:  valtPlatform.nonce,
				song_id: songId,
				wallet_address: wallet,
			}, function ( r ) {
				$status.text( r.success ? 'Mint scheduled! Check back soon.' : ( r.data || 'Error' ) );
			} ).fail( function () { $status.text( 'Network error.' ); } );
		} );

		// ── Checkout button ──────────────────────────────────────────
		$( document ).on( 'click', '[data-action="checkout"]', function () {
			var $wrap  = $( this ).closest( '.valt-checkout' );
			var songId = $wrap.data( 'song-id' );
			var wallet = $wrap.find( '[data-wallet]' ).val() || '';

			$( this ).prop( 'disabled', true ).text( 'Redirecting...' );

			$.ajax( {
				url:  valtPlatform.restUrl + 'stripe/create-checkout',
				method: 'POST',
				data: JSON.stringify( { song_id: songId, wallet_address: wallet } ),
				contentType: 'application/json',
				beforeSend: function ( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', valtPlatform.restNonce ); },
				success: function ( data ) { window.location.href = data.checkout_url; },
				error: function ( xhr ) {
					var msg = xhr.responseJSON ? xhr.responseJSON.error : 'Error';
					alert( msg );
					$( this ).prop( 'disabled', false ).text( 'Buy Now' );
				},
			} );
		} );

		// ── Campaign pledge ──────────────────────────────────────────
		$( document ).on( 'click', '[data-action="pledge"]', function () {
			var $wrap   = $( this ).closest( '.valt-campaign' );
			var albumId = $wrap.data( 'album-id' );
			var pts     = parseInt( $wrap.find( '[data-pledge-amount]' ).val(), 10 );

			if ( ! pts || pts < 1 ) { alert( 'Enter a valid number of points.' ); return; }

			$( this ).prop( 'disabled', true ).text( 'Pledging...' );

			$.post( valtPlatform.ajaxUrl, {
				action: 'valt_pledge_points',
				nonce:  valtPlatform.nonce,
				album_id: albumId,
				points: pts,
			}, function ( r ) {
				if ( r.success ) {
					alert( r.data.message );
					location.reload();
				} else {
					alert( r.data || 'Error' );
				}
			} ).always( function () {
				$( '[data-action="pledge"]' ).prop( 'disabled', false ).text( 'Pledge' );
			} );
		} );

		// ── Daily points claim ───────────────────────────────────────
		$( document ).on( 'click', '[data-action="claim-daily"]', function () {
			var $btn = $( this );
			var $msg = $( '[data-daily-msg]' );
			$btn.prop( 'disabled', true );

			$.post( valtPlatform.ajaxUrl, {
				action: 'valt_claim_daily_points',
				nonce:  valtPlatform.nonce,
			}, function ( r ) {
				$msg.text( r.success ? r.data.message : ( r.data || 'Already claimed today.' ) );
			} ).fail( function () { $msg.text( 'Network error.' ); } );
		} );

		// ── Open Valt animation ──────────────────────────────────────
		$( document ).on( 'click', '[data-action="open-valt"]', function () {
			var $btn     = $( this );
			var $section = $btn.closest( '.valt-vault' );
			var $door    = $section.find( '.valt-vault__door-inner' );
			var $content = $section.find( '[data-valt-content]' );

			// Animate: button disappears, door shrinks + fades, content reveals
			$btn.fadeOut( 300 );

			$door.css( 'transition', 'transform 1s cubic-bezier(0.34,1.56,0.64,1), opacity 0.8s ease' );
			$door.css( { transform: 'scale(0.4)', opacity: '0.15' } );

			// Stop the spinning animation
			$door.find( '.valt-spokes' ).css( 'animation', 'none' );
			$door.find( '.valt-outer' ).css( 'animation', 'none' );
			$door.find( '.valt-groove' ).css( 'animation', 'none' );

			setTimeout( function () {
				$content.slideDown( 600, function () {
					// Scroll to the revealed content
					$( 'html, body' ).animate( { scrollTop: $content.offset().top - 100 }, 400 );
				} );
				// Remember it's been opened (skip animation next visit)
				try { localStorage.setItem( 'valt_opened_' + $section.data( 'state' ), '1' ); } catch(e) {}
			}, 600 );
		} );

		// Auto-open if previously opened (skip animation on return visits)
		$( '.valt-vault[data-state="unlocked"]' ).each( function () {
			var key = 'valt_opened_' + $( this ).data( 'state' );
			try {
				if ( localStorage.getItem( key ) ) {
					$( this ).find( '[data-action="open-valt"]' ).hide();
					$( this ).find( '.valt-vault__door-inner' ).css( { transform: 'scale(0.4)', opacity: '0.15' } );
					$( this ).find( '.valt-spokes, .valt-outer, .valt-groove' ).css( 'animation', 'none' );
					$( this ).find( '[data-valt-content]' ).show();
				}
			} catch(e) {}
		} );

		// ── Follow / Unfollow artist ─────────────────────────────────
		$( document ).on( 'click', '[data-action="follow"]', function () {
			var $btn   = $( this );
			var $wrap  = $btn.closest( '.valt-follow' );
			var artistId = $wrap.data( 'artist-id' );

			$btn.prop( 'disabled', true );

			$.post( valtPlatform.ajaxUrl, {
				action: 'valt_follow_artist',
				nonce:  valtPlatform.nonce,
				artist_id: artistId,
			}, function ( r ) {
				if ( r.success ) {
					var isFollowing = r.data.action === 'followed';
					$btn.toggleClass( 'valt-btn--primary', ! isFollowing )
						.toggleClass( 'valt-btn--secondary valt-follow--active', isFollowing )
						.html( ( isFollowing ? '\u2714 Following' : '+ Follow' ) );
					$wrap.find( '[data-follow-count]' ).text( r.data.count );
					var label = r.data.count === 1 ? 'follower' : 'followers';
					$wrap.find( '.valt-follow__label' ).text( label );
				}
			} ).always( function () { $btn.prop( 'disabled', false ); } );
		} );

	} );

} )( jQuery );
