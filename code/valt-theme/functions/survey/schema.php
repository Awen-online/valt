<?php
/**
 * Valt Feedback Survey — database schema.
 *
 * Ported from the proven Sync.Land survey system. Creates a single
 * {prefix}valt_survey_responses table via dbDelta, with version-based upgrades.
 * (Events are handled separately by the awen-client plugin, so no events table
 * is created here.)
 */

defined( 'ABSPATH' ) || exit;

define( 'VALT_SURVEY_DB_VERSION', '1.1' );

/**
 * Create or upgrade the survey responses table.
 */
function valt_survey_create_table() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$table           = $wpdb->prefix . 'valt_survey_responses';

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		session_id VARCHAR(64) NOT NULL,
		user_id BIGINT UNSIGNED DEFAULT NULL,
		nps_score TINYINT DEFAULT NULL,
		use_case VARCHAR(255) DEFAULT NULL,
		collect_ease TINYINT DEFAULT NULL,
		collect_value VARCHAR(50) DEFAULT NULL,
		fractional_sentiment TINYINT DEFAULT NULL,
		invest_effect VARCHAR(20) DEFAULT NULL,
		feature_request TEXT DEFAULT NULL,
		how_found_us VARCHAR(100) DEFAULT NULL,
		trigger_type VARCHAR(50) DEFAULT NULL,
		page_url VARCHAR(2048) DEFAULT NULL,
		ip_address VARCHAR(45) DEFAULT NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY nps_score (nps_score)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'valt_survey_db_version', VALT_SURVEY_DB_VERSION );
}

/**
 * Create/upgrade the table when the version is behind.
 */
function valt_survey_check_db() {
	if ( version_compare( get_option( 'valt_survey_db_version', '0' ), VALT_SURVEY_DB_VERSION, '<' ) ) {
		valt_survey_create_table();
	}
}
add_action( 'after_switch_theme', 'valt_survey_create_table' );
// Run on init (front-end + admin) so the table exists even when the survey is
// added to an already-active theme. The check is a cheap option read; the
// dbDelta only runs once, when the stored version is behind.
add_action( 'init', 'valt_survey_check_db' );
