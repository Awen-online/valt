<?php
/**
 * Valt Artist Intake — database schema.
 *
 * A single {prefix}valt_artist_intake table holding prospective-artist leads
 * captured from the public intake form. Created via dbDelta, version-gated.
 */

defined( 'ABSPATH' ) || exit;

define( 'VALT_INTAKE_DB_VERSION', '1.0' );

function valt_intake_create_table() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table           = $wpdb->prefix . 'valt_artist_intake';

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		artist_name VARCHAR(200) NOT NULL,
		email VARCHAR(200) NOT NULL,
		genre VARCHAR(120) DEFAULT NULL,
		location VARCHAR(160) DEFAULT NULL,
		links TEXT DEFAULT NULL,
		message TEXT DEFAULT NULL,
		has_wallet VARCHAR(20) DEFAULT NULL,
		how_found VARCHAR(120) DEFAULT NULL,
		status VARCHAR(30) NOT NULL DEFAULT 'new',
		source VARCHAR(120) DEFAULT NULL,
		page_url VARCHAR(2048) DEFAULT NULL,
		ip_address VARCHAR(45) DEFAULT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY email (email),
		KEY status (status)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	update_option( 'valt_intake_db_version', VALT_INTAKE_DB_VERSION );
}

function valt_intake_check_db() {
	if ( version_compare( get_option( 'valt_intake_db_version', '0' ), VALT_INTAKE_DB_VERSION, '<' ) ) {
		valt_intake_create_table();
	}
}
add_action( 'after_switch_theme', 'valt_intake_create_table' );
add_action( 'init', 'valt_intake_check_db' );
