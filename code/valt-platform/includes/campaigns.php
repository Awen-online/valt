<?php
defined( 'ABSPATH' ) || exit;

/**
 * Proto-tokenomics album campaigns for Valt Platform v2.
 * Fans pledge meta-points toward album funding goals.
 */

/**
 * Create or activate a campaign on an album.
 */
function valt_create_campaign( int $album_id, int $goal, string $deadline, string $description ): true|WP_Error {
	$album = get_post( $album_id );
	if ( ! $album || $album->post_type !== 'album' ) {
		return new WP_Error( 'valt_invalid_album', 'Invalid album.' );
	}

	if ( $goal <= 0 ) {
		return new WP_Error( 'valt_invalid_goal', 'Goal must be positive.' );
	}

	update_post_meta( $album_id, 'valt_campaign_active', 1 );
	update_post_meta( $album_id, 'valt_campaign_goal', $goal );
	update_post_meta( $album_id, 'valt_campaign_deadline', $deadline );
	update_post_meta( $album_id, 'valt_campaign_description', $description );

	valt_log_event( 'campaign_created', "Campaign created for album {$album_id}", [
		'goal'     => $goal,
		'deadline' => $deadline,
	] );

	return true;
}

/**
 * Pledge points to an album campaign.
 *
 * @return int|WP_Error Total points pledged to this campaign, or error.
 */
function valt_pledge_to_campaign( int $user_id, int $album_id, int $points ) {
	$active = (int) get_post_meta( $album_id, 'valt_campaign_active', true );
	if ( ! $active ) {
		return new WP_Error( 'valt_no_campaign', 'No active campaign for this album.' );
	}

	// Check deadline.
	$deadline = get_post_meta( $album_id, 'valt_campaign_deadline', true );
	if ( $deadline && strtotime( $deadline ) < time() ) {
		return new WP_Error( 'valt_campaign_ended', 'This campaign has ended.' );
	}

	// Deduct points from user.
	$artist_id = (int) get_post_meta( $album_id, 'artist', true );
	$result    = valt_deduct_points( $user_id, $points, 'campaign_back', 'album', $album_id, $artist_id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	// Record pledge.
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'valt_campaign_pledges',
		[
			'user_id'  => $user_id,
			'album_id' => $album_id,
			'points'   => $points,
		],
		[ '%d', '%d', '%d' ]
	);

	// Check for early_supporter badge.
	$progress = valt_get_campaign_progress( $album_id );
	if ( $progress && $progress['percent'] < 50 ) {
		valt_maybe_award_badge( $user_id, 'early_supporter', $artist_id );
	}

	valt_log_event( 'campaign_pledge', "User {$user_id} pledged {$points} to album {$album_id}" );

	return $progress ? $progress['total_pledged'] : $points;
}

/**
 * Get campaign progress for an album.
 */
function valt_get_campaign_progress( int $album_id ): ?array {
	$active = (int) get_post_meta( $album_id, 'valt_campaign_active', true );
	if ( ! $active ) {
		return null;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'valt_campaign_pledges';

	$total_pledged = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE album_id = %d",
		$album_id
	) );

	$backer_count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE album_id = %d",
		$album_id
	) );

	$goal     = (int) get_post_meta( $album_id, 'valt_campaign_goal', true );
	$deadline = get_post_meta( $album_id, 'valt_campaign_deadline', true );
	$percent  = $goal > 0 ? min( 100, round( ( $total_pledged / $goal ) * 100 ) ) : 0;

	$album    = get_post( $album_id );
	$artist_id = (int) get_post_meta( $album_id, 'artist', true );

	return [
		'album_id'      => $album_id,
		'album_title'   => $album ? $album->post_title : '',
		'artist_id'     => $artist_id,
		'artist_name'   => $artist_id ? get_the_title( $artist_id ) : '',
		'description'   => get_post_meta( $album_id, 'valt_campaign_description', true ),
		'goal'          => $goal,
		'total_pledged' => $total_pledged,
		'backer_count'  => $backer_count,
		'percent'       => $percent,
		'deadline'      => $deadline,
		'is_active'     => $deadline ? strtotime( $deadline ) > time() : true,
	];
}

/**
 * Get ranked list of backers for a campaign.
 */
function valt_get_campaign_backers( int $album_id, int $limit = 50 ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_campaign_pledges';

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT user_id, SUM(points) AS total_pledged, MIN(created_at) AS first_pledge
		 FROM {$table} WHERE album_id = %d
		 GROUP BY user_id ORDER BY total_pledged DESC LIMIT %d",
		$album_id, $limit
	), ARRAY_A );

	$backers = [];
	$rank    = 0;
	foreach ( $rows as $row ) {
		$rank++;
		$user = get_userdata( (int) $row['user_id'] );
		$backers[] = [
			'rank'          => $rank,
			'user_id'       => (int) $row['user_id'],
			'display_name'  => $user ? $user->display_name : "User #{$row['user_id']}",
			'avatar_url'    => get_avatar_url( (int) $row['user_id'], [ 'size' => 48 ] ),
			'total_pledged' => (int) $row['total_pledged'],
			'first_pledge'  => $row['first_pledge'],
		];
	}

	return $backers;
}

/**
 * Get all active campaigns.
 */
function valt_get_active_campaigns( int $limit = 12 ): array {
	$albums = get_posts( [
		'post_type'      => 'album',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'meta_query'     => [ [
			'key'   => 'valt_campaign_active',
			'value' => '1',
		] ],
	] );

	$campaigns = [];
	foreach ( $albums as $album ) {
		$progress = valt_get_campaign_progress( $album->ID );
		if ( $progress && $progress['is_active'] ) {
			$campaigns[] = $progress;
		}
	}

	return $campaigns;
}

/**
 * Get all campaigns a user has pledged to.
 */
function valt_get_user_pledges( int $user_id ): array {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_campaign_pledges';

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT album_id, SUM(points) AS total_pledged FROM {$table} WHERE user_id = %d GROUP BY album_id",
		$user_id
	), ARRAY_A );

	$pledges = [];
	foreach ( $rows as $row ) {
		$progress = valt_get_campaign_progress( (int) $row['album_id'] );
		if ( $progress ) {
			$progress['user_pledged'] = (int) $row['total_pledged'];
			$pledges[] = $progress;
		}
	}

	return $pledges;
}
