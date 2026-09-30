<?php
/**
 * Valt Feedback Survey — loader.
 *
 * Wires the survey subsystem into the theme: loads the modules, and enqueues
 * the front-end modal assets when the survey is enabled.
 *
 * Ported from the Sync.Land survey; bridged to Awen OS by the companion
 * mu-plugin `valt-survey-pulse.php` (a read-only pull endpoint the hub polls).
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/survey.php';
require_once __DIR__ . '/rest.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin.php';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}

	$settings = valt_survey_get_settings();
	if ( empty( $settings['survey_enabled'] ) ) {
		return;
	}

	$dir  = get_stylesheet_directory_uri();
	$path = get_stylesheet_directory();
	// Cache-bust on file change so deployed asset updates reach browsers without a theme version bump.
	$css_ver = file_exists( "$path/assets/css/survey.css" ) ? filemtime( "$path/assets/css/survey.css" ) : '1.0';
	$js_ver  = file_exists( "$path/assets/js/survey.js" ) ? filemtime( "$path/assets/js/survey.js" ) : '1.0';

	wp_enqueue_style( 'valt-survey', $dir . '/assets/css/survey.css', array(), $css_ver );
	wp_register_script( 'valt-survey', $dir . '/assets/js/survey.js', array(), $js_ver, true );
	wp_localize_script( 'valt-survey', 'VALTSurveyConfig', array(
		'api_url'      => rest_url( 'valt/v1' ),
		'nonce'        => wp_create_nonce( 'wp_rest' ),
		'visit_count'  => (int) $settings['visit_count'],
		'time_on_site' => (int) $settings['time_on_site'],
		'post_mint'    => ! empty( $settings['post_mint'] ),
	) );
	wp_enqueue_script( 'valt-survey' );
} );
