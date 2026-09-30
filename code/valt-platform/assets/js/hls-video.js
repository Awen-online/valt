/**
 * Adaptive holder-only video: <video data-valt-hls="…m3u8" data-valt-fallback="…mp4">.
 * Safari/iOS play HLS natively; elsewhere hls.js (loaded from jsDelivr alongside this file).
 * If neither works (or hls.js failed to load / hits a fatal error), fall back to the MP4.
 * Every playlist and segment request goes through the server-side holder gate.
 */
( function () {
	'use strict';

	function fallback( v ) {
		var f = v.getAttribute( 'data-valt-fallback' );
		if ( f && v.getAttribute( 'src' ) !== f ) { v.src = f; }
	}

	function setup( v ) {
		if ( v.__valtHls ) return;
		v.__valtHls = true;
		var src = v.getAttribute( 'data-valt-hls' );

		// hls.js first where it's supported (MSE gives it real bandwidth-based switching);
		// native HLS for Safari/iOS, which lack MSE on older iPhones.
		if ( window.Hls && window.Hls.isSupported() ) {
			var hls = new window.Hls( {
				capLevelToPlayerSize: true,   // don't pull 1080p into a small player
				startLevel: -1,               // estimate bandwidth, then pick
				maxBufferLength: 20
			} );
			hls.on( window.Hls.Events.ERROR, function ( _e, data ) {
				if ( ! data.fatal ) return;
				if ( data.type === window.Hls.ErrorTypes.NETWORK_ERROR && data.response && data.response.code === 403 ) {
					hls.destroy(); return;   // not a holder (or session ended): nothing to fall back to
				}
				if ( data.type === window.Hls.ErrorTypes.MEDIA_ERROR ) { hls.recoverMediaError(); return; }
				hls.destroy(); fallback( v );
			} );
			hls.loadSource( src );
			hls.attachMedia( v );
			v.__valtHlsInstance = hls;
		} else if ( v.canPlayType( 'application/vnd.apple.mpegurl' ) ) {
			v.src = src;
		} else {
			fallback( v );
		}
	}

	function init() {
		document.querySelectorAll( 'video[data-valt-hls]' ).forEach( setup );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', init ); else init();
	// If hls.js failed to load from the CDN, the else-branches above still give native HLS / MP4.
} )();
