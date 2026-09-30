<?php
defined( 'ABSPATH' ) || exit;

/**
 * Holder-only media hosting.
 *
 * Files live OUTSIDE the web root in  <parent of ABSPATH>/valt-private-media/<artist_id>/<file>
 * and are only served through  /?valt_media=<artist_id>/<file>  after the same per-artist
 * holder check the Valt gate uses. There is no public URL for the file itself.
 * Supports HTTP Range so video can seek.
 *
 *   [valt_private_video artist_id="299" file="london.mp4" poster="https://…jpg" title="…"]
 * renders a <video> pointing at the gated URL (use inside [valt_gated_content] so non-holders
 * never receive the tag at all).
 */

function valt_private_media_dir(): string {
	return trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'valt-private-media';
}

/** Does the current visitor hold one of this artist's editions? Admins always may. */
function valt_viewer_holds_artist( int $artist_id ): bool {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	if ( ! $artist_id || ! function_exists( 'cardanoPress' ) || ! function_exists( 'valt_filter_assets_for_artist' ) ) {
		return false;
	}
	$policy = (string) get_post_meta( $artist_id, 'valt_policy_id', true );
	$prof   = cardanoPress()->userProfile();
	if ( ! $policy || ! $prof->isConnected() ) {
		return false;
	}
	$assets = $prof->storedAssets();
	return ! empty( $assets ) && ! empty( valt_filter_assets_for_artist( $assets, $policy, get_the_title( $artist_id ) ) );
}

function valt_private_media_url( int $artist_id, string $file ): string {
	return add_query_arg( 'valt_media', rawurlencode( $artist_id . '/' . $file ), home_url( '/' ) );
}

add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['valt_media'] ) ) {
		return;
	}
	$req = rawurldecode( (string) wp_unslash( $_GET['valt_media'] ) );
	if ( ! preg_match( '#^(\d+)/([A-Za-z0-9._-]+)$#', $req, $m ) ) {
		status_header( 400 ); exit;
	}
	$artist_id = (int) $m[1];
	$base      = realpath( valt_private_media_dir() );
	$path      = $base ? realpath( $base . '/' . $artist_id . '/' . $m[2] ) : false;
	if ( ! $path || strpos( $path, $base . DIRECTORY_SEPARATOR ) !== 0 || ! is_file( $path ) ) {
		status_header( 404 ); exit;
	}
	if ( ! valt_viewer_holds_artist( $artist_id ) ) {
		status_header( 403 ); exit;
	}

	$size  = filesize( $path );
	$types = [ 'm3u8' => 'application/vnd.apple.mpegurl', 'ts' => 'video/mp2t', 'mp4' => 'video/mp4','m4v' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf' ];
	$ext   = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	$start = 0;
	$end   = $size - 1;

	if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $r ) ) {
		if ( $r[1] === '' && $r[2] !== '' ) {         // suffix range: last N bytes
			$start = max( 0, $size - (int) $r[2] );
		} else {
			$start = (int) $r[1];
			if ( $r[2] !== '' ) $end = min( (int) $r[2], $size - 1 );
		}
		if ( $start > $end || $start >= $size ) {
			status_header( 416 ); header( "Content-Range: bytes */{$size}" ); exit;
		}
		status_header( 206 );
		header( "Content-Range: bytes {$start}-{$end}/{$size}" );
	} else {
		status_header( 200 );
	}

	while ( ob_get_level() ) ob_end_clean();
	nocache_headers();
	header( 'Cache-Control: private, no-store' );
	header( 'Content-Type: ' . ( $types[ $ext ] ?? 'application/octet-stream' ) );
	header( 'Accept-Ranges: bytes' );
	header( 'Content-Length: ' . ( $end - $start + 1 ) );
	header( 'Content-Disposition: inline' );
	header( 'X-Robots-Tag: noindex' );
	if ( function_exists( 'session_write_close' ) ) session_write_close();
	set_time_limit( 0 );

	$fh = fopen( $path, 'rb' );
	fseek( $fh, $start );
	$left = $end - $start + 1;
	while ( $left > 0 && ! feof( $fh ) && ! connection_aborted() ) {
		$chunk = fread( $fh, min( 1048576, $left ) );
		echo $chunk;
		flush();
		$left -= strlen( $chunk );
	}
	fclose( $fh );
	exit;
}, 1 );

/*
 * [valt_private_video artist_id="299" file="london.mp4" hls="london.m3u8" poster="…" title="…"]
 * With hls=, the player streams adaptively (1080/720/480, built by tools/make-hls.sh):
 * Safari/iOS natively, others via hls.js. `file` stays as the fallback if HLS can't play.
 */
add_shortcode( 'valt_private_video', function ( $atts ) {
	$a = shortcode_atts( [ 'artist_id' => 0, 'file' => '', 'hls' => '', 'poster' => '', 'title' => '' ], $atts );
	$aid = (int) $a['artist_id'];
	if ( ! $aid || ( ! $a['file'] && ! $a['hls'] ) ) return '';

	$hls_attr = '';
	if ( $a['hls'] ) {
		$hls_attr = ' data-valt-hls="' . esc_url( valt_private_media_url( $aid, $a['hls'] ) ) . '"';
		// Only pages that actually render a holder video load these.
		wp_enqueue_script( 'hls-js', 'https://cdn.jsdelivr.net/npm/hls.js@1.6/dist/hls.light.min.js', [], null, true );
		wp_enqueue_script( 'valt-hls-video', VALT_PLATFORM_URL . 'assets/js/hls-video.js', [], (string) filemtime( VALT_PLATFORM_PATH . 'assets/js/hls-video.js' ), true );
	}
	$file_url = $a['file'] ? esc_url( valt_private_media_url( $aid, $a['file'] ) ) : '';
	return sprintf(
		'<div class="valt-video"><video controls playsinline preload="%s" controlsList="nodownload" oncontextmenu="return false"%s%s title="%s"></video></div>',
		$a['hls'] ? 'none' : 'metadata',
		$a['poster'] ? ' poster="' . esc_url( $a['poster'] ) . '"' : '',
		// With HLS the JS picks the source (fallback kept in data-); without it, plain src.
		$a['hls'] ? $hls_attr . ( $file_url ? ' data-valt-fallback="' . $file_url . '"' : '' ) : ' src="' . $file_url . '"',
		esc_attr( $a['title'] )
	);
} );
