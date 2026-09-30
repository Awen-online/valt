<?php
/**
 * Plugin Name: Valt Platform
 * Plugin URI:  https://github.com/Awen-online/valt
 * Description: Superfan experience engine — token-gating, NMKR NFT minting, gamification, leaderboards, discovery, and proto-tokenomics for the Valt music platform on Cardano.
 * Version:     2.0.3
 * Author:      Awen
 * Text Domain: valt-platform
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

define( 'VALT_PLATFORM_VERSION', '2.0.3' );
define( 'VALT_PLATFORM_PATH', plugin_dir_path( __FILE__ ) );
define( 'VALT_PLATFORM_URL',  plugin_dir_url( __FILE__ ) );

// ── Core (v1.0 — existing) ──────────────────────────────────────────
require VALT_PLATFORM_PATH . 'includes/gating.php';
require VALT_PLATFORM_PATH . 'includes/rest-meta.php';
require VALT_PLATFORM_PATH . 'includes/artist-dashboard.php';
require VALT_PLATFORM_PATH . 'includes/shortcodes.php';
require VALT_PLATFORM_PATH . 'includes/media-gate.php';
require VALT_PLATFORM_PATH . 'includes/checkout.php';
require VALT_PLATFORM_PATH . 'includes/anvil.php';
require VALT_PLATFORM_PATH . 'includes/admin-meta.php';

// ── v2.0 — Foundation ────────────────────────────────────────────────
require VALT_PLATFORM_PATH . 'includes/helpers.php';
require VALT_PLATFORM_PATH . 'includes/db-schema.php';
require VALT_PLATFORM_PATH . 'includes/cron.php';
require VALT_PLATFORM_PATH . 'includes/nmkr.php';

// ── WP-CLI only: `wp valt mint-json` export for the self-mint fallback (code/valt-mint) ──
if ( defined( 'WP_CLI' ) && WP_CLI && file_exists( VALT_PLATFORM_PATH . 'includes/cli-mint-export.php' ) ) {
	require VALT_PLATFORM_PATH . 'includes/cli-mint-export.php';
}

// ── Optional modules (absent from the curated open-source build) ─────
// Loaded only when present, so the published subset activates cleanly.
foreach ( [
	'includes/admin-docs.php',
	'seed-data.php',
	'includes/gamification.php',
	'includes/leaderboard.php',
	'includes/discovery.php',
	'includes/campaigns.php',
	'includes/rest-api.php',
	'includes/ajax-handlers.php',
	'includes/shortcodes-new.php',
	'includes/admin-settings.php',
	'includes/admin-nft-monitor.php',
	'includes/admin-campaigns.php',
] as $valt_include ) {
	$valt_file = VALT_PLATFORM_PATH . $valt_include;
	if ( file_exists( $valt_file ) ) {
		require $valt_file;
	}
}

// ── Activation hook — create DB tables ───────────────────────────────
register_activation_hook( __FILE__, 'valt_create_tables' );

// ── Asset enqueuing ──────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {
	// Version assets by file mtime: browsers cache them for 30 days, and a fixed plugin version
	// meant updated JS/CSS never reached returning visitors (e.g. the Collect quantity picker).
	$ver = function ( $rel ) {
		$f = VALT_PLATFORM_PATH . $rel;
		return file_exists( $f ) ? (string) filemtime( $f ) : VALT_PLATFORM_VERSION;
	};

	wp_enqueue_style(
		'valt-platform',
		VALT_PLATFORM_URL . 'assets/css/valt-platform.css',
		[],
		$ver( 'assets/css/valt-platform.css' )
	);
	wp_enqueue_script(
		'valt-platform',
		VALT_PLATFORM_URL . 'assets/js/valt-platform.js',
		[ 'jquery' ],
		$ver( 'assets/js/valt-platform.js' ),
		true
	);

	$localize = [
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'restUrl'      => rest_url( 'valt/v1/' ),
		'nonce'        => wp_create_nonce( 'valt_platform' ),
		'restNonce'    => wp_create_nonce( 'wp_rest' ),
		'collectToken' => function_exists( 'valt_collect_token' ) ? valt_collect_token() : '',
		'isLoggedIn'   => is_user_logged_in(),
		'userId'       => get_current_user_id(),
	];
	wp_localize_script( 'valt-platform', 'valtPlatform', $localize );

	// wp_enqueue_media() is called by the artist dashboard itself (X-06): no media library on public pages.

	// Enqueue additional JS files if they exist.
	foreach ( [ 'valt-discovery', 'valt-leaderboard', 'valt-campaign', 'valt-mint' ] as $handle ) {
		$js_file = VALT_PLATFORM_PATH . "assets/js/{$handle}.js";
		if ( file_exists( $js_file ) ) {
			wp_enqueue_script( $handle, VALT_PLATFORM_URL . "assets/js/{$handle}.js", [ 'jquery', 'valt-platform' ], $ver( "assets/js/{$handle}.js" ), true );
		}
	}
} );
