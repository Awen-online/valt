<?php
/**
 * Valt Artist Intake — loader.
 *
 * Loads the modules and enqueues the form assets only on pages that actually
 * contain the [valt_artist_intake] shortcode (keeps every other page clean).
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/form.php';
require_once __DIR__ . '/rest.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}
	// Only load assets where the shortcode is present.
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	if ( ! $post || ( ! has_shortcode( (string) $post->post_content, 'valt_artist_intake' )
		&& ! has_shortcode( (string) $post->post_content, 'valt_artist_benefits' ) ) ) {
		return;
	}

	$dir  = get_stylesheet_directory_uri();
	$path = get_stylesheet_directory();
	// Cache-bust on file change so deployed asset updates reach browsers without a theme version bump.
	$css_ver = file_exists( "$path/assets/css/intake.css" ) ? filemtime( "$path/assets/css/intake.css" ) : '1.0';
	$js_ver  = file_exists( "$path/assets/js/intake.js" ) ? filemtime( "$path/assets/js/intake.js" ) : '1.0';
	wp_enqueue_style( 'valt-intake', $dir . '/assets/css/intake.css', array(), $css_ver );
	wp_enqueue_script( 'valt-intake', $dir . '/assets/js/intake.js', array(), $js_ver, true );
} );
