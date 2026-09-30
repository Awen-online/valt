/**
 * Valt player: one persistent bar at the bottom of the page.
 *
 * Any element with data-valt-track='{"id","title","url","artist","artist_url","art","src"}'
 * is a play control. Clicking one queues every playable track on the page (or, with
 * data-valt-queue="<selector>", every track inside that container) and starts from it.
 * The queue and position survive page navigation via sessionStorage, so the bar is
 * still there (paused, at the same spot) on the next page.
 */
( function () {
	'use strict';

	var KEY = 'valt_player_v1';
	var audio = new Audio();
	audio.preload = 'metadata';

	var state = { queue: [], index: -1 };
	var bar, el = {};
	var seeking = false;

	var ICON_PLAY  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>';
	var ICON_PAUSE = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>';
	var ICON_PREV  = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="5" width="2.5" height="14" rx="1"/><path d="M19 6.2v11.6a1 1 0 0 1-1.52.85L9.2 13.5a1.75 1.75 0 0 1 0-3l8.28-5.15A1 1 0 0 1 19 6.2z"/></svg>';
	var ICON_NEXT  = '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="16.5" y="5" width="2.5" height="14" rx="1"/><path d="M5 6.2v11.6a1 1 0 0 0 1.52.85l8.28-5.15a1.75 1.75 0 0 0 0-3L6.52 5.35A1 1 0 0 0 5 6.2z"/></svg>';
	var ICON_VOL   = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1z"/><path d="M15.5 8.5a5 5 0 0 1 0 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

	function parse( node ) {
		try { return JSON.parse( node.getAttribute( 'data-valt-track' ) ); } catch ( e ) { return null; }
	}
	function fmt( s ) {
		if ( ! isFinite( s ) || s < 0 ) s = 0;
		var m = Math.floor( s / 60 ), r = Math.floor( s % 60 );
		return m + ':' + ( r < 10 ? '0' : '' ) + r;
	}
	function esc( s ) {
		return String( s || '' ).replace( /[&<>"']/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]; } );
	}
	function current() { return state.queue[ state.index ] || null; }

	// ─── Bar markup ────────────────────────────────────────────────
	function build() {
		if ( bar ) return;
		bar = document.createElement( 'div' );
		bar.className = 'valt-player';
		bar.setAttribute( 'role', 'region' );
		bar.setAttribute( 'aria-label', 'Music player' );
		bar.innerHTML =
			'<div class="valt-player__controls">' +
				'<button type="button" class="valt-player__btn valt-player__prev" aria-label="Previous track">' + ICON_PREV + '</button>' +
				'<button type="button" class="valt-play valt-player__toggle" aria-label="Play">' + ICON_PLAY.replace( '<svg', '<svg class="valt-play__icon"' ) + ICON_PAUSE.replace( '<svg', '<svg class="valt-play__pause"' ) + '</button>' +
				'<button type="button" class="valt-player__btn valt-player__next" aria-label="Next track">' + ICON_NEXT + '</button>' +
			'</div>' +
			'<div class="valt-player__now">' +
				'<img class="valt-player__art" alt="">' +
				'<div class="valt-player__meta">' +
					'<div class="valt-player__line">' +
						'<span class="valt-eq" aria-hidden="true"><i></i><i></i><i></i></span>' +
						'<a class="valt-player__title" href="#"></a>' +
						'<a class="valt-player__artist" href="#"></a>' +
					'</div>' +
					'<div class="valt-player__bar">' +
						'<span class="valt-player__time valt-player__cur">0:00</span>' +
						'<input type="range" class="valt-range valt-player__seek" min="0" max="1000" value="0" step="1" aria-label="Seek">' +
						'<span class="valt-player__time valt-player__dur">0:00</span>' +
					'</div>' +
				'</div>' +
			'</div>' +
			'<div class="valt-player__side">' +
				'<span class="valt-player__vol-icon" aria-hidden="true" style="color:var(--valt-mid);display:inline-flex;width:18px">' + ICON_VOL + '</span>' +
				'<input type="range" class="valt-range valt-player__vol" min="0" max="100" value="100" aria-label="Volume">' +
				'<a class="valt-btn valt-btn--primary valt-player__collect" href="#">Collect</a>' +
			'</div>';
		document.body.appendChild( bar );

		el.toggle  = bar.querySelector( '.valt-player__toggle' );
		el.prev    = bar.querySelector( '.valt-player__prev' );
		el.next    = bar.querySelector( '.valt-player__next' );
		el.art     = bar.querySelector( '.valt-player__art' );
		el.title   = bar.querySelector( '.valt-player__title' );
		el.artist  = bar.querySelector( '.valt-player__artist' );
		el.seek    = bar.querySelector( '.valt-player__seek' );
		el.cur     = bar.querySelector( '.valt-player__cur' );
		el.dur     = bar.querySelector( '.valt-player__dur' );
		el.vol     = bar.querySelector( '.valt-player__vol' );
		el.collect = bar.querySelector( '.valt-player__collect' );

		el.toggle.addEventListener( 'click', toggle );
		el.prev.addEventListener( 'click', prev );
		el.next.addEventListener( 'click', next );
		el.seek.addEventListener( 'input', function () {
			seeking = true;
			paintRange( el.seek );
			if ( audio.duration ) el.cur.textContent = fmt( audio.duration * el.seek.value / 1000 );
		} );
		el.seek.addEventListener( 'change', function () {
			if ( audio.duration ) audio.currentTime = audio.duration * el.seek.value / 1000;
			seeking = false;
		} );
		el.vol.addEventListener( 'input', function () {
			audio.volume = el.vol.value / 100;
			paintRange( el.vol );
			save();
		} );
		paintRange( el.vol );
	}

	function paintRange( r ) {
		r.style.setProperty( '--p', ( ( r.value - r.min ) / ( r.max - r.min ) * 100 ) + '%' );
	}

	function open() {
		build();
		bar.classList.add( 'is-open' );
		document.body.classList.add( 'valt-player-open' );
	}

	// ─── Playback ──────────────────────────────────────────────────
	function load( i, autoplay, startAt ) {
		var t = state.queue[ i ];
		if ( ! t ) return;
		state.index = i;
		open();
		audio.src = t.src;
		if ( startAt ) {
			audio.addEventListener( 'loadedmetadata', function once() {
				audio.removeEventListener( 'loadedmetadata', once );
				try { audio.currentTime = startAt; } catch ( e ) {}
			} );
		}
		el.title.textContent  = t.title;
		el.title.href         = t.url || '#';
		el.artist.textContent = t.artist || '';
		el.artist.href        = t.artist_url || '#';
		el.collect.href       = t.url || '#';
		el.collect.setAttribute( 'aria-label', 'Collect ' + t.title );
		if ( t.art ) { el.art.src = t.art; el.art.style.visibility = ''; } else { el.art.removeAttribute( 'src' ); el.art.style.visibility = 'hidden'; }
		el.seek.value = 0; paintRange( el.seek );
		el.cur.textContent = fmt( startAt || 0 ); el.dur.textContent = t.duration || '0:00';
		el.prev.disabled = i <= 0 && ! startAt;
		el.next.disabled = i >= state.queue.length - 1;
		if ( autoplay ) play();
		mediaSession( t );
		sync();
		save();
	}

	function play() {
		var p = audio.play();
		if ( p && p.catch ) p.catch( function () { sync(); } );
	}
	function toggle() {
		if ( ! current() ) return;
		if ( audio.paused ) play(); else audio.pause();
	}
	function prev() {
		if ( audio.currentTime > 3 || state.index <= 0 ) { audio.currentTime = 0; return; }
		load( state.index - 1, true );
	}
	function next() {
		if ( state.index < state.queue.length - 1 ) load( state.index + 1, true );
	}

	// Reflect state on every play control + card on the page.
	function sync() {
		var t = current(), playing = ! audio.paused && ! audio.ended;
		if ( bar ) {
			el.toggle.classList.toggle( 'is-playing', playing );
			el.toggle.setAttribute( 'aria-label', playing ? 'Pause' : 'Play' );
			bar.classList.toggle( 'is-paused', ! playing );
		}
		document.querySelectorAll( '[data-valt-track]' ).forEach( function ( b ) {
			if ( b.hasAttribute( 'data-valt-queue' ) ) return; // "Play all" stays a play icon
			var d = parse( b ), on = !! ( t && d && d.id === t.id );
			b.classList.toggle( 'is-playing', on && playing );
			b.setAttribute( 'aria-pressed', on && playing ? 'true' : 'false' );
		} );
		document.querySelectorAll( '[data-valt-song]' ).forEach( function ( c ) {
			c.classList.toggle( 'is-current', !! ( t && +c.getAttribute( 'data-valt-song' ) === t.id ) );
		} );
	}

	function mediaSession( t ) {
		if ( ! ( 'mediaSession' in navigator ) || ! window.MediaMetadata ) return;
		navigator.mediaSession.metadata = new MediaMetadata( {
			title: t.title, artist: t.artist || '', album: 'Valt',
			artwork: t.art ? [ { src: t.art, sizes: '300x300' } ] : []
		} );
		try {
			navigator.mediaSession.setActionHandler( 'play', play );
			navigator.mediaSession.setActionHandler( 'pause', function () { audio.pause(); } );
			navigator.mediaSession.setActionHandler( 'previoustrack', prev );
			navigator.mediaSession.setActionHandler( 'nexttrack', next );
		} catch ( e ) {}
	}

	audio.addEventListener( 'play', sync );
	audio.addEventListener( 'pause', function () { sync(); save(); } );
	audio.addEventListener( 'ended', function () {
		if ( state.index < state.queue.length - 1 ) load( state.index + 1, true ); else sync();
	} );
	audio.addEventListener( 'loadedmetadata', function () { if ( el.dur ) el.dur.textContent = fmt( audio.duration ); } );
	audio.addEventListener( 'error', function () {
		// Skip a track whose file won't load rather than stalling the queue.
		if ( audio.src && state.index < state.queue.length - 1 ) load( state.index + 1, true ); else sync();
	} );
	var lastSave = 0;
	audio.addEventListener( 'timeupdate', function () {
		if ( ! el.seek || seeking || ! audio.duration ) return;
		el.seek.value = Math.round( audio.currentTime / audio.duration * 1000 );
		paintRange( el.seek );
		el.cur.textContent = fmt( audio.currentTime );
		if ( Date.now() - lastSave > 2000 ) { lastSave = Date.now(); save(); }
	} );

	// ─── Click → queue ─────────────────────────────────────────────
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest && e.target.closest( '[data-valt-track]' );
		if ( ! btn || ( bar && bar.contains( btn ) ) ) return;
		e.preventDefault();
		e.stopPropagation();
		var t = parse( btn );
		if ( ! t || ! t.src ) return;

		// Same track as the one loaded: pause / resume.
		var cur = current();
		if ( cur && cur.id === t.id && ! btn.hasAttribute( 'data-valt-queue' ) ) { toggle(); return; }

		var scopeSel = btn.getAttribute( 'data-valt-queue' );
		var scope = ( scopeSel && document.querySelector( scopeSel ) ) || document;
		var seen = {}, q = [];
		scope.querySelectorAll( '[data-valt-track]' ).forEach( function ( b ) {
			if ( b.hasAttribute( 'data-valt-queue' ) ) return;
			var d = parse( b );
			if ( d && d.src && ! seen[ d.id ] ) { seen[ d.id ] = 1; q.push( d ); }
		} );
		if ( ! seen[ t.id ] ) q.unshift( t );
		state.queue = q;
		var start = 0;
		q.forEach( function ( d, i ) { if ( d.id === t.id ) start = i; } );
		load( start, true );
	}, true );

	// ─── Persistence across pages ──────────────────────────────────
	function save() {
		if ( ! current() ) return;
		try {
			sessionStorage.setItem( KEY, JSON.stringify( {
				queue: state.queue, index: state.index,
				time: audio.currentTime || 0, vol: el.vol ? +el.vol.value : 100
			} ) );
		} catch ( e ) {}
	}
	window.addEventListener( 'pagehide', save );

	function restore() {
		var s;
		try { s = JSON.parse( sessionStorage.getItem( KEY ) || 'null' ); } catch ( e ) { s = null; }
		if ( ! s || ! s.queue || ! s.queue.length ) return;
		state.queue = s.queue;
		build();
		el.vol.value = s.vol != null ? s.vol : 100; audio.volume = el.vol.value / 100; paintRange( el.vol );
		// Browsers block autoplay on a fresh page, so come back paused at the same spot.
		load( Math.min( s.index || 0, s.queue.length - 1 ), false, s.time || 0 );
	}

	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', restore ); else restore();
} )();
