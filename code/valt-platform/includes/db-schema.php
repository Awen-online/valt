<?php
defined( 'ABSPATH' ) || exit;

/**
 * Database schema for Valt Platform v2.
 *
 * Tables:
 *  - valt_points_ledger  (gamification points log)
 *  - valt_badges          (earned badge records)
 *  - valt_campaign_pledges (proto-tokenomics album pledges)
 */

function valt_create_tables(): void {
	global $wpdb;
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$wpdb->prefix}valt_points_ledger (
		id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id        BIGINT UNSIGNED NOT NULL,
		artist_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
		action         VARCHAR(50)     NOT NULL,
		points         INT             NOT NULL,
		reference_type VARCHAR(30)     DEFAULT NULL,
		reference_id   BIGINT UNSIGNED DEFAULT NULL,
		created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_user_artist (user_id, artist_id),
		KEY idx_action (action),
		KEY idx_created (created_at)
	) {$charset};

	CREATE TABLE {$wpdb->prefix}valt_badges (
		id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id     BIGINT UNSIGNED NOT NULL,
		badge_slug  VARCHAR(60)     NOT NULL,
		artist_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
		earned_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_user_badge_artist (user_id, badge_slug, artist_id),
		KEY idx_badge (badge_slug)
	) {$charset};

	CREATE TABLE {$wpdb->prefix}valt_campaign_pledges (
		id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id     BIGINT UNSIGNED NOT NULL,
		album_id    BIGINT UNSIGNED NOT NULL,
		points      INT UNSIGNED    NOT NULL,
		created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_album (album_id),
		KEY idx_user (user_id)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'valt_db_version', '2.0.0' );
}

/**
 * Check if tables need creation/update on admin init.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'valt_db_version' ) !== '2.0.0' ) {
		valt_create_tables();

		// Set default feature flags — campaigns/gamification/leaderboard OFF.
		if ( ! get_option( 'valt_feature_flags' ) ) {
			update_option( 'valt_feature_flags', [
				'nmkr'         => true,
				'stripe'       => true,
				'discovery'    => true,
				'leaderboard'  => false,
				'gamification' => false,
				'campaigns'    => false,
			] );
		}
	}
} );
