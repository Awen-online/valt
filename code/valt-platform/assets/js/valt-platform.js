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
							// Only show a fan count once there is one; "0 fans" reads as an empty room.
							+ ( ( a.fan_count > 0 || a.country ) ? '<span class="valt-song-grid__meta">' + [ a.fan_count > 0 ? a.fan_count + ( a.fan_count === 1 ? ' fan' : ' fans' ) : '', a.country ? escHtml( a.country ) : '' ].filter( Boolean ).join( ' &middot; ' ) + '</span>' : '' )
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
				// Collapse the (now-empty) vault door so it doesn't leave a blank box above the content.
				$section.find( '.valt-vault__door' ).slideUp( 400 );
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
					// Return visit: skip the animation and hide the empty door box outright.
					$( this ).find( '.valt-vault__door' ).hide();
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
					$wrap.find( '[data-follow-count]' ).text( r.data.count ).prop( 'hidden', ! r.data.count );
					var label = r.data.count === 1 ? 'follower' : 'followers';
					$wrap.find( '.valt-follow__label' ).text( label ).prop( 'hidden', ! r.data.count );
				}
			} ).always( function () { $btn.prop( 'disabled', false ); } );
		} );

	} );

} )( jQuery );

/**
 * Collect quantity picker. Quantity 1 follows the direct NMKR link as before; 2+ asks the
 * server to reserve that many editions of this song and opens NMKR's checkout for all of them.
 */
( function () {
	'use strict';

	function fmt( n ) { return ( Math.round( n * 100 ) / 100 ).toString(); }

	document.addEventListener( 'click', function ( e ) {
		var step = e.target.closest && e.target.closest( '.valt-qty__btn' );
		if ( step ) {
			var box = step.closest( '[data-valt-qty]' );
			var out = box.querySelector( '.valt-qty__val' );
			var max = parseInt( box.getAttribute( 'data-max' ), 10 ) || 1;
			var q   = Math.min( max, Math.max( 1, ( parseInt( out.textContent, 10 ) || 1 ) + parseInt( step.getAttribute( 'data-step' ), 10 ) ) );
			out.textContent = q;
			var price = parseFloat( box.getAttribute( 'data-price' ) ) || 0;
			box.querySelector( '.valt-qty__total' ).textContent = ( q > 1 ? q + ' × ' + fmt( price ) + ' = ' : '' ) + fmt( price * q ) + ' ADA';
			box.querySelector( '[data-step="-1"]' ).disabled = q <= 1;
			box.querySelector( '[data-step="1"]' ).disabled  = q >= max;
			var btn = box.parentNode.querySelector( '[data-valt-collect], [data-valt-anvil]' );
			if ( btn ) {
				btn.querySelector( '.valt-mint__btn-label' ).textContent = q > 1 ? 'Collect ' + q + ' editions' : btn.getAttribute( 'data-label1' );
			}
			return;
		}

		var collect = e.target.closest && e.target.closest( '[data-valt-collect]' );
		if ( ! collect ) return;
		var wrap = collect.parentNode;
		var qBox = wrap.querySelector( '[data-valt-qty] .valt-qty__val' );
		var qty  = qBox ? parseInt( qBox.textContent, 10 ) || 1 : 1;
		if ( qty <= 1 ) return; // direct single-edition link

		e.preventDefault();
		var msg = wrap.querySelector( '.valt-mint__msg' );
		if ( msg ) { msg.hidden = true; msg.textContent = ''; }
		if ( collect.getAttribute( 'aria-busy' ) === 'true' ) return;
		collect.setAttribute( 'aria-busy', 'true' );
		var label = collect.querySelector( '.valt-mint__btn-label' );
		var was   = label.textContent;
		label.textContent = 'Reserving ' + qty + ' editions…';

		// Open the tab now (inside the click) so popup blockers allow it; point it at NMKR once ready.
		var win = window.open( 'about:blank', '_blank' );
		var cfg = window.valtPlatform || {};
		fetch( ( cfg.restUrl || '/wp-json/valt/v1/' ) + 'collect', {
			method: 'POST',
			// collectToken is user-independent (see checkout.php), so it works whether or not the
			// visitor is connected; no cookies are needed for this call.
			headers: { 'Content-Type': 'application/json' },
			credentials: 'omit',
			body: JSON.stringify( { song_id: parseInt( collect.getAttribute( 'data-valt-collect' ), 10 ), qty: qty, nonce: cfg.collectToken || '' } )
		} ).then( function ( r ) {
			return r.json().then( function ( d ) { return { ok: r.ok, d: d }; } );
		} ).then( function ( res ) {
			if ( ! res.ok || ! res.d.url ) throw new Error( ( res.d && res.d.message ) || 'Checkout could not be started.' );
			if ( win && ! win.closed ) { win.location.href = res.d.url; } else { window.location.href = res.d.url; }
		} ).catch( function ( err ) {
			if ( win && ! win.closed ) win.close();
			if ( msg ) { msg.textContent = err.message; msg.hidden = false; }
		} ).then( function () {
			collect.removeAttribute( 'aria-busy' );
			label.textContent = was;
		} );
	} );
} )();

/**
 * Anvil checkout (songs with valt_checkout = anvil): the fan's own wallet pays and receives the
 * edition in one transaction. Build on the server → wallet signs → server co-signs with the policy
 * key and submits. No redirect to a hosted checkout.
 */
( function () {
	'use strict';

	var NAMES = { typhon: 'typhoncip30' };

	function walletKeys() {
		var c = window.cardano || {};
		return Object.keys( c ).filter( function ( k ) {
			return c[ k ] && typeof c[ k ].enable === 'function' && k !== 'typhon';
		} );
	}

	/** The wallet CardanoPress connected, if it's installed. */
	function rememberedWallet() {
		var v = '';
		try { v = ( localStorage.getItem( '_x_connectedExtension' ) || '' ).replace( /"/g, '' ).toLowerCase(); } catch ( e ) {}
		v = NAMES[ v ] || v;
		return v && window.cardano && window.cardano[ v ] ? v : '';
	}

	function post( path, body ) {
		var cfg = window.valtPlatform || {};
		body.nonce = cfg.collectToken || '';
		return fetch( ( cfg.restUrl || '/wp-json/valt/v1/' ) + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'omit',
			body: JSON.stringify( body )
		} ).then( function ( r ) {
			return r.json().catch( function () { return {}; } ).then( function ( d ) {
				if ( ! r.ok ) throw new Error( d.message || 'Something went wrong. Please try again.' );
				return d;
			} );
		} );
	}

	function el( tag, attrs, text ) {
		var n = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) { n.setAttribute( k, attrs[ k ] ); } );
		if ( text ) n.textContent = text;
		return n;
	}

	/** Pick a wallet: the remembered one, the only one installed, or ask. Resolves to a key. */
	function chooseWallet( wrap ) {
		var k = rememberedWallet();
		if ( k ) return Promise.resolve( k );
		var keys = walletKeys();
		if ( keys.length === 1 ) return Promise.resolve( keys[ 0 ] );
		if ( ! keys.length ) {
			return Promise.reject( new Error( 'No Cardano wallet found. Install Eternl or Lace, switch it to the Preprod testnet, then try again.' ) );
		}
		var box = wrap.querySelector( '.valt-mint__wallets' );
		return new Promise( function ( resolve ) {
			box.innerHTML = '';
			box.appendChild( el( 'span', { 'class': 'valt-mint__wallets-label' }, 'Choose a wallet:' ) );
			keys.forEach( function ( key ) {
				var b = el( 'button', { type: 'button', 'class': 'valt-btn valt-btn--secondary valt-btn--small' }, window.cardano[ key ].name || key );
				b.addEventListener( 'click', function () { box.hidden = true; resolve( key ); } );
				box.appendChild( b );
			} );
			box.hidden = false;
		} );
	}

	/**
	 * Pending panel after submit: steps (signed, sent, confirming, ready), a live transaction link,
	 * and a poll of /anvil/status until the tx has a confirmation. Then "Open the Valt".
	 */
	function showPending( box, d ) {
		var n     = d.editions || 1;
		var what  = n > 1 ? n + ' editions' : 'your edition';
		box.innerHTML = '';
		box.classList.add( 'valt-collect-status' );
		box.setAttribute( 'aria-live', 'polite' );

		var head  = el( 'div', { 'class': 'valt-collect-status__head' } );
		var spin  = el( 'span', { 'class': 'valt-collect-status__spinner', 'aria-hidden': 'true' } );
		var title = el( 'strong', { 'class': 'valt-collect-status__title' }, 'Confirming on Cardano' );
		head.appendChild( spin );
		head.appendChild( title );
		box.appendChild( head );

		var sub = el( 'p', { 'class': 'valt-collect-status__sub' }, 'Your payment and ' + what + ' are in one transaction. It usually confirms in under a minute. You can keep this page open.' );
		box.appendChild( sub );

		var steps = el( 'ol', { 'class': 'valt-collect-status__steps' } );
		function step( t, state ) {
			var li = el( 'li', { 'class': 'is-' + state }, t );
			steps.appendChild( li );
			return li;
		}
		step( 'Signed in your wallet', 'done' );
		step( 'Sent to the network', 'done' );
		var sConfirm = step( 'Confirming on-chain', 'active' );
		var sReady   = step( ( n > 1 ? 'Editions' : 'Edition' ) + ' in your wallet', 'todo' );
		box.appendChild( steps );

		var links = el( 'p', { 'class': 'valt-mint__done-links' } );
		links.appendChild( el( 'a', { href: d.explorer, target: '_blank', rel: 'noopener' }, 'View transaction' ) );
		box.appendChild( links );
		box.hidden = false;

		var cfg   = window.valtPlatform || {};
		var base  = cfg.restUrl || '/wp-json/valt/v1/';
		var tries = 0;
		function finish() {
			box.classList.add( 'is-confirmed' );
			spin.remove();
			title.textContent = n > 1 ? 'Collected ' + n + ' editions' : 'Collected';
			sub.textContent = 'Confirmed on-chain. ' + ( n > 1 ? 'They are' : 'It is' ) + ' in your wallet now.';
			sConfirm.className = 'is-done';
			sReady.className = 'is-done';
			var acts = el( 'div', { 'class': 'valt-collect-status__actions' } );
			if ( d.valt_url ) {
				acts.appendChild( el( 'a', { href: d.valt_url, 'class': 'valt-btn valt-btn--primary' }, 'Open the Valt' ) );
			}
			// A deliberate second purchase: reload for fresh stock rather than re-arming the old button.
			var more = el( 'button', { type: 'button', 'class': 'valt-btn valt-btn--secondary' }, 'Collect more' );
			more.addEventListener( 'click', function () { window.location.reload(); } );
			acts.appendChild( more );
			box.appendChild( acts );
		}
		function slow() {
			sub.textContent = 'Still confirming. The network is busy, but nothing is lost: ' + what + ' will arrive in your wallet. You can check the transaction link, or come back to the Valt in a few minutes.';
			if ( d.valt_url ) box.appendChild( el( 'a', { href: d.valt_url, 'class': 'valt-btn valt-btn--secondary' }, 'Go to the Valt' ) );
		}
		( function poll() {
			tries++;
			fetch( base + 'anvil/status?tx=' + encodeURIComponent( d.tx_hash ), { credentials: 'omit' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( s ) {
					if ( s && s.confirmed ) return finish();
					if ( tries === 36 ) slow(); // ~3 minutes
					if ( tries < 120 ) setTimeout( poll, 5000 );
				} )
				.catch( function () { if ( tries < 120 ) setTimeout( poll, 5000 ); } );
		} )();
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '[data-valt-anvil]' );
		if ( ! btn || btn.getAttribute( 'aria-busy' ) === 'true' ) return;
		e.preventDefault();

		var wrap  = btn.parentNode;
		var qBox  = wrap.querySelector( '[data-valt-qty] .valt-qty__val' );
		var qty   = qBox ? parseInt( qBox.textContent, 10 ) || 1 : 1;
		var msg   = wrap.querySelector( '.valt-mint__msg' );
		var done  = wrap.querySelector( '.valt-mint__done-box' );
		var label = btn.querySelector( '.valt-mint__btn-label' );
		var was   = label.textContent;
		var songId = parseInt( btn.getAttribute( 'data-valt-anvil' ), 10 );
		var api, buildId;

		var qtyBtns = wrap.querySelectorAll( '[data-valt-qty] button' );
		var qtyWas  = [];
		function stage( t ) { label.textContent = t; }
		// Lock the button and the edition picker for the whole flow, so a second click or a
		// quantity change can't start another checkout while the wallet is open.
		function lock( on ) {
			btn.classList.toggle( 'is-busy', on );
			btn.setAttribute( 'aria-disabled', on ? 'true' : 'false' );
			Array.prototype.forEach.call( qtyBtns, function ( b, i ) {
				if ( on ) { qtyWas[ i ] = b.disabled; b.disabled = true; } else { b.disabled = !! qtyWas[ i ]; }
			} );
		}
		if ( msg ) { msg.hidden = true; msg.textContent = ''; }
		btn.setAttribute( 'aria-busy', 'true' );
		lock( true );
		stage( 'Connecting wallet…' );

		chooseWallet( wrap ).then( function ( key ) {
			return window.cardano[ key ].enable();
		} ).then( function ( a ) {
			api = a;
			return api.getNetworkId();
		} ).then( function ( net ) {
			if ( net !== 0 ) throw new Error( 'Switch your wallet to the Preprod testnet, then try again.' );
			return Promise.all( [ api.getChangeAddress(), api.getUtxos() ] );
		} ).then( function ( r ) {
			stage( 'Preparing transaction…' );
			return post( 'anvil/build', { song_id: songId, qty: qty, address: r[ 0 ], utxos: r[ 1 ] || [] } );
		} ).then( function ( b ) {
			buildId = b.build_id;
			stage( 'Confirm in your wallet…' );
			return api.signTx( b.tx, true ).catch( function () {
				post( 'anvil/cancel', { build_id: buildId } ).catch( function () {} ); // free the held editions now
				throw new Error( 'Signature declined, so nothing was charged. Press Collect to try again.' );
			} );
		} ).then( function ( witness ) {
			stage( 'Sending…' );
			return post( 'anvil/submit', { build_id: buildId, witness: witness } );
		} ).then( function ( d ) {
			// Submitted. The checkout is finished for the fan: swap the button and picker for a
			// pending panel (no second Collect), and only offer the Valt once it's on-chain.
			btn.hidden = true;
			var qtyBox = wrap.querySelector( '[data-valt-qty]' );
			if ( qtyBox ) qtyBox.hidden = true;
			var note = wrap.querySelector( '.valt-mint__hint' );
			if ( note ) note.hidden = true;
			showPending( done, d );
		} ).catch( function ( err ) {
			if ( msg ) { msg.textContent = ( err && err.message ) || 'Something went wrong. Please try again.'; msg.hidden = false; }
			stage( was );
			lock( false );
		} ).then( function () {
			btn.removeAttribute( 'aria-busy' );
		} );
	} );
} )();
