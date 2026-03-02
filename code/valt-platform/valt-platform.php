<?php
/**
 * Plugin Name: Valt Platform
 * Plugin URI:  https://github.com/mcculloughi/valt-theme
 * Description: Token-gating, Artist Valt, and Artist Dashboard for the Valt music platform.
 * Version:     1.0.0
 * Author:      Valt
 * Text Domain: valt-platform
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'VALT_PLATFORM_PATH', plugin_dir_path( __FILE__ ) );
define( 'VALT_PLATFORM_URL',  plugin_dir_url( __FILE__ ) );

require VALT_PLATFORM_PATH . 'includes/gating.php';
require VALT_PLATFORM_PATH . 'includes/rest-meta.php';
require VALT_PLATFORM_PATH . 'includes/artist-dashboard.php';
require VALT_PLATFORM_PATH . 'includes/shortcodes.php';
require VALT_PLATFORM_PATH . 'includes/admin-meta.php';

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'valt-platform',
		VALT_PLATFORM_URL . 'assets/css/valt-platform.css',
		[],
		'1.0.0'
	);
	wp_enqueue_script(
		'valt-platform',
		VALT_PLATFORM_URL . 'assets/js/valt-platform.js',
		[ 'jquery' ],
		'1.0.0',
		true
	);
	wp_localize_script( 'valt-platform', 'valtPlatform', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'valt_platform' ),
	] );
	wp_enqueue_media();
} );
