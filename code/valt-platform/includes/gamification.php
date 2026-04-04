<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gamification engine for Valt Platform v2.
 * Points, badges, levels.
 */

// ─── Points ──────────────────────────────────────────────────────────

/**
 * Award points to a user.
 *
 * @return int New total points for the user.
 */
function valt_award_points( int $user_id, string $action, int $points, string $ref_type = '', int $ref_id = 0, int $artist_id = 0 ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_points_ledger';

	$wpdb->insert( $table, [
		'user_id'        => $user_id,
		'artist_id'      => $artist_id,
		'action'         => $action,
		'points'         => $points,
		'reference_type' => $ref_type ?: null,
		'reference_id'   => $ref_id ?: null,
	], [ '%d', '%d', '%s', '%d', '%s', '%d' ] );

	// Check for badge triggers after awarding.
	valt_check_badge_triggers( $user_id, $action, $artist_id );

	return valt_get_user_points( $user_id );
}

/**
 * Deduct points from a user (e.g., campaign pledge).
 *
 * @return int|WP_Error New total or error if insufficient balance.
 */
function valt_deduct_points( int $user_id, int $points, string $action, string $ref_type = '', int $ref_id = 0, int $artist_id = 0 ) {
	$current = valt_get_user_points( $user_id );
	if ( $current < $points ) {
		return new WP_Error( 'valt_insufficient_points', "Not enough points. You have {$current}, need {$points}." );
	}

	return valt_award_points( $user_id, $action, -$points, $ref_type, $ref_id, $artist_id );
}

/**
 * Get total points for a user, optionally scoped to an artist.
 */
function valt_get_user_points( int $user_id, int $artist_id = 0 ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_points_ledger';

	if ( $artist_id > 0 ) {
		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d AND artist_id = %d",
			$user_id, $artist_id
		) );
	} else {
		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d",
			$user_id
		) );
	}

	return (int) $total;
}

/**
 * Get the user's current level based on total points.
 */
function valt_get_user_level( int $user_id ): array {
	$points     = valt_get_user_points( $user_id );
	$thresholds = valt_level_thresholds();
	$current    = $thresholds[1];
	$next       = null;

	foreach ( $thresholds as $num => $level ) {
		if ( $points >= $level['threshold'] ) {
			$current = array_merge( $level, [ 'level' => $num ] );
		} else {
			$next = $level;
			break;
		}
	}

	$progress = 0;
	if ( $next ) {
		$range    = $next['threshold'] - $current['threshold'];
		$progress = $range > 0 ? ( $points - $current['threshold'] ) / $range : 1;
	} else {
		$progress = 1; // Max level.
	}

	return [
		'level'          => $current['level'],
		'name'           => $current['name'],
		'points'         => $points,
		'next_threshold' => $next ? $next['threshold'] : null,
		'next_name'      => $next ? $next['name'] : null,
		'progress'       => round( $progress, 2 ),
	];
}

// ─── Badges ──────────────────────────────────────────────────────────

/**
 * Get all earned badges for a user.
 */
function valt_get_user_badges( int $user_id, int $artist_id = 0 ): array {
	global $wpdb;
	$table       = $wpdb->prefix . 'valt_badges';
	$definitions = valt_badge_definitions();

	if ( $artist_id > 0 ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT badge_slug, earned_at FROM {$table} WHERE user_id = %d AND artist_id = %d",
			$user_id, $artist_id
		), ARRAY_A );
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT badge_slug, artist_id, earned_at FROM {$table} WHERE user_id = %d",
			$user_id
		), ARRAY_A );
	}

	$earned = [];
	foreach ( $rows as $row ) {
		$slug = $row['badge_slug'];
		$def  = $definitions[ $slug ] ?? [ 'name' => $slug, 'desc' => '', 'icon' => 'star' ];
		$earned[] = array_merge( $def, [
			'slug'      => $slug,
			'earned_at' => $row['earned_at'],
			'artist_id' => $row['artist_id'] ?? 0,
		] );
	}

	return $earned;
}

/**
 * Award a badge if not already earned. Returns true if newly awarded.
 */
function valt_maybe_award_badge( int $user_id, string $badge_slug, int $artist_id = 0 ): bool {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_badges';

	// Check if already earned (unique constraint will also catch this).
	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND badge_slug = %s AND artist_id = %d",
		$user_id, $badge_slug, $artist_id
	) );

	if ( $exists ) {
		return false;
	}

	$wpdb->insert( $table, [
		'user_id'    => $user_id,
		'badge_slug' => $badge_slug,
		'artist_id'  => $artist_id,
	], [ '%d', '%s', '%d' ] );

	// Award bonus points for earning a badge.
	$config = valt_points_config();
	if ( $config['badge_earned'] > 0 ) {
		valt_award_points( $user_id, 'badge_earned', $config['badge_earned'], 'badge', 0, $artist_id );
	}

	valt_log_event( 'badge_earned', "User {$user_id} earned badge: {$badge_slug}", [
		'artist_id' => $artist_id,
	] );

	return true;
}

/**
 * Count how many times a user has performed a specific action.
 */
function valt_count_user_actions( int $user_id, string $action ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_points_ledger';
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND action = %s",
		$user_id, $action
	) );
}

/**
 * Count distinct artists a user has interacted with via purchases.
 */
function valt_count_user_distinct_artists( int $user_id ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_points_ledger';
	return (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT artist_id) FROM {$table} WHERE user_id = %d AND action = 'nft_purchase' AND artist_id > 0",
		$user_id
	) );
}

// ─── Badge Trigger Checks ────────────────────────────────────────────

/**
 * Check and award badges based on recent activity.
 */
function valt_check_badge_triggers( int $user_id, string $action, int $artist_id = 0 ): void {
	if ( $action === 'nft_purchase' ) {
		$count = valt_count_user_actions( $user_id, 'nft_purchase' );
		if ( $count >= 1 )  valt_maybe_award_badge( $user_id, 'first_nft' );
		if ( $count >= 5 )  valt_maybe_award_badge( $user_id, 'collector_5' );
		if ( $count >= 25 ) valt_maybe_award_badge( $user_id, 'collector_25' );

		$distinct = valt_count_user_distinct_artists( $user_id );
		if ( $distinct >= 3 ) valt_maybe_award_badge( $user_id, 'multi_artist' );
	}

	if ( $action === 'wallet_connect' ) {
		valt_maybe_award_badge( $user_id, 'wallet_connected' );
	}
}

// ─── Hook: Award wallet_connect points when CardanoPress connects ────

add_action( 'cardanopress_wallet_connected', function ( int $user_id ) {
	$config = valt_points_config();
	// Only award once.
	if ( valt_count_user_actions( $user_id, 'wallet_connect' ) === 0 ) {
		valt_award_points( $user_id, 'wallet_connect', $config['wallet_connect'] );
	}
} );

// ─── Hook: Award points on NFT mint completion ──────────────────────

add_action( 'valt_nft_minted', function ( int $song_id ) {
	// Points were already awarded at purchase time via Stripe processing.
	// This hook is for additional actions (e.g., updating fan counts).
	$artist_id = (int) get_post_meta( $song_id, 'artist', true );
	if ( $artist_id ) {
		// Refresh cached fan count.
		global $wpdb;
		$table = $wpdb->prefix . 'valt_points_ledger';
		$fans  = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE artist_id = %d AND action = 'nft_purchase'",
			$artist_id
		) );
		update_post_meta( $artist_id, 'valt_fan_count', $fans );
	}
} );
