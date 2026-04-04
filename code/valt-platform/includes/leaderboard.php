<?php
defined( 'ABSPATH' ) || exit;

/**
 * Leaderboard queries for Valt Platform v2.
 */

/**
 * Get ranked leaderboard data.
 *
 * @param string $scope     'global', 'artist', or 'monthly'.
 * @param int    $artist_id Required when scope is 'artist'.
 * @param int    $limit     Max results.
 * @return array Ranked entries with user info, points, level, badge count.
 */
function valt_get_leaderboard( string $scope = 'global', int $artist_id = 0, int $limit = 50 ): array {
	global $wpdb;
	$table  = $wpdb->prefix . 'valt_points_ledger';
	$badges = $wpdb->prefix . 'valt_badges';
	$where  = '1=1';
	$params = [];

	if ( $scope === 'artist' && $artist_id > 0 ) {
		$where   .= ' AND p.artist_id = %d';
		$params[] = $artist_id;
	}

	if ( $scope === 'monthly' ) {
		$where   .= ' AND p.created_at >= %s';
		$params[] = gmdate( 'Y-m-01 00:00:00' );
	}

	$params[] = $limit;

	$sql = "SELECT
		p.user_id,
		SUM(p.points) AS total_points,
		(SELECT COUNT(*) FROM {$badges} b WHERE b.user_id = p.user_id) AS badge_count
		FROM {$table} p
		WHERE {$where}
		GROUP BY p.user_id
		ORDER BY total_points DESC
		LIMIT %d";

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );

	$results = [];
	$rank    = 0;
	foreach ( $rows as $row ) {
		$rank++;
		$user_id = (int) $row['user_id'];
		$user    = get_userdata( $user_id );
		$level   = valt_get_user_level( $user_id );

		$results[] = [
			'rank'         => $rank,
			'user_id'      => $user_id,
			'display_name' => $user ? $user->display_name : "User #{$user_id}",
			'avatar_url'   => get_avatar_url( $user_id, [ 'size' => 64 ] ),
			'total_points' => (int) $row['total_points'],
			'level'        => $level['level'],
			'level_name'   => $level['name'],
			'badge_count'  => (int) $row['badge_count'],
		];
	}

	return $results;
}
