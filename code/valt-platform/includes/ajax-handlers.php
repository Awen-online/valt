<?php
defined( 'ABSPATH' ) || exit;

/**
 * AJAX handlers for Valt Platform v2.
 * All use nonce 'valt_platform' (existing pattern from v1).
 */

// ─── Mint Song NFT ───────────────────────────────────────────────────

add_action( 'wp_ajax_valt_mint_song_nft', function () {
	check_ajax_referer( 'valt_platform', 'nonce' );

	$song_id = (int) ( $_POST['song_id'] ?? 0 );
	$wallet  = sanitize_text_field( $_POST['wallet_address'] ?? '' );

	// Verify ownership.
	$artist = valt_get_current_artist();
	if ( ! $artist ) {
		wp_send_json_error( 'No linked artist profile.' );
	}

	$song = get_post( $song_id );
	if ( ! $song || (int) $song->post_author !== get_current_user_id() ) {
		wp_send_json_error( 'You do not own this song.' );
	}

	$result = valt_schedule_nft_mint( $song_id, $wallet );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( [ 'message' => 'NFT mint scheduled.', 'song_id' => $song_id ] );
} );

// ─── Set Song Price ──────────────────────────────────────────────────

add_action( 'wp_ajax_valt_set_song_price', function () {
	check_ajax_referer( 'valt_platform', 'nonce' );

	$song_id    = (int) ( $_POST['song_id'] ?? 0 );
	$price_usd  = (int) ( $_POST['price_usd'] ?? 0 );    // cents
	$price_ada  = sanitize_text_field( $_POST['price_ada'] ?? '' );
	$max_supply = (int) ( $_POST['max_supply'] ?? 0 );

	$song = get_post( $song_id );
	if ( ! $song || (int) $song->post_author !== get_current_user_id() ) {
		wp_send_json_error( 'You do not own this song.' );
	}

	update_post_meta( $song_id, 'valt_nft_price_usd', $price_usd );
	update_post_meta( $song_id, 'valt_nft_price_ada', $price_ada );
	update_post_meta( $song_id, 'valt_nft_max_supply', $max_supply );

	// Sync to Stripe if USD price set.
	$stripe_result = null;
	if ( $price_usd > 0 ) {
		$stripe_result = valt_sync_song_to_stripe( $song_id );
	}

	$response = [ 'message' => 'Pricing updated.' ];
	if ( is_wp_error( $stripe_result ) ) {
		$response['stripe_error'] = $stripe_result->get_error_message();
	}

	wp_send_json_success( $response );
} );

// ─── Upload Cover Art ────────────────────────────────────────────────

add_action( 'wp_ajax_valt_upload_cover_art', function () {
	check_ajax_referer( 'valt_platform', 'nonce' );

	$song_id  = (int) ( $_POST['song_id'] ?? 0 );
	$image_id = (int) ( $_POST['image_id'] ?? 0 );

	$song = get_post( $song_id );
	if ( ! $song || (int) $song->post_author !== get_current_user_id() ) {
		wp_send_json_error( 'You do not own this song.' );
	}

	if ( $image_id > 0 ) {
		update_post_meta( $song_id, 'valt_nft_image_id', $image_id );
		$url = wp_get_attachment_image_url( $image_id, 'medium' );
		wp_send_json_success( [ 'message' => 'Cover art saved.', 'image_url' => $url ] );
	} else {
		delete_post_meta( $song_id, 'valt_nft_image_id' );
		wp_send_json_success( [ 'message' => 'Cover art removed.' ] );
	}
} );

// ─── Create Campaign ─────────────────────────────────────────────────

add_action( 'wp_ajax_valt_create_campaign', function () {
	check_ajax_referer( 'valt_platform', 'nonce' );

	$album_id    = (int) ( $_POST['album_id'] ?? 0 );
	$goal        = (int) ( $_POST['goal'] ?? 0 );
	$deadline    = sanitize_text_field( $_POST['deadline'] ?? '' );
	$description = wp_kses_post( $_POST['description'] ?? '' );

	if ( ! $album_id || ! $goal || ! $deadline ) {
		wp_send_json_error( 'Album, goal, and deadline are required.' );
	}

	$album = get_post( $album_id );
	if ( ! $album || (int) $album->post_author !== get_current_user_id() ) {
		wp_send_json_error( 'You do not own this album.' );
	}

	if ( function_exists( 'valt_create_campaign' ) ) {
		$result = valt_create_campaign( $album_id, $goal, $deadline, $description );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
	} else {
		update_post_meta( $album_id, 'valt_campaign_active', 1 );
		update_post_meta( $album_id, 'valt_campaign_goal', $goal );
		update_post_meta( $album_id, 'valt_campaign_deadline', $deadline );
		update_post_meta( $album_id, 'valt_campaign_description', $description );
	}

	wp_send_json_success( [ 'message' => 'Campaign created.' ] );
} );

// ─── Pledge Points ───────────────────────────────────────────────────

add_action( 'wp_ajax_valt_pledge_points', function () {
	check_ajax_referer( 'valt_platform', 'nonce' );

	$album_id = (int) ( $_POST['album_id'] ?? 0 );
	$points   = (int) ( $_POST['points'] ?? 0 );

	if ( $points <= 0 ) {
		wp_send_json_error( 'Points must be positive.' );
	}

	if ( function_exists( 'valt_pledge_to_campaign' ) ) {
		$result = valt_pledge_to_campaign( get_current_user_id(), $album_id, $points );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( [ 'message' => "Pledged {$points} points.", 'total_pledged' => $result ] );
	} else {
		wp_send_json_error( 'Campaign system not available.' );
	}
} );

// ─── Claim Daily Points ──────────────────────────────────────────────

add_action( 'wp_ajax_valt_claim_daily_points', function () {
	check_ajax_referer( 'valt_platform', 'nonce' );

	$user_id       = get_current_user_id();
	$transient_key = "valt_daily_claimed_{$user_id}";

	if ( get_transient( $transient_key ) ) {
		wp_send_json_error( 'Already claimed today.' );
	}

	$config = valt_points_config();
	if ( function_exists( 'valt_award_points' ) ) {
		$total = valt_award_points( $user_id, 'daily_login', $config['daily_login'] );
		// Set transient until end of day.
		$seconds_until_midnight = strtotime( 'tomorrow' ) - time();
		set_transient( $transient_key, true, $seconds_until_midnight );
		wp_send_json_success( [ 'message' => "Earned {$config['daily_login']} points!", 'total' => $total ] );
	} else {
		wp_send_json_error( 'Gamification not available.' );
	}
} );

// ─── Save Social Links ──────────────────────────────────────────────

add_action( 'wp_ajax_valt_save_social_links', function () {
	check_ajax_referer( 'valt_platform', 'nonce' );

	$artist_id = (int) ( $_POST['artist_id'] ?? 0 );
	$artist    = valt_get_current_artist();

	if ( ! $artist || $artist->ID !== $artist_id ) {
		wp_send_json_error( 'Not your artist profile.' );
	}

	$fields = [
		'valt_social_x'         => sanitize_text_field( $_POST['x'] ?? '' ),
		'valt_social_instagram' => sanitize_text_field( $_POST['instagram'] ?? '' ),
		'valt_social_spotify'   => esc_url_raw( $_POST['spotify'] ?? '' ),
	];

	foreach ( $fields as $key => $value ) {
		update_post_meta( $artist_id, $key, $value );
	}

	wp_send_json_success( [ 'message' => 'Social links saved.' ] );
} );

// ─── Toggle Featured (Admin) ─────────────────────────────────────────

add_action( 'wp_ajax_valt_toggle_featured', function () {
	check_ajax_referer( 'valt_platform', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$artist_id = (int) ( $_POST['artist_id'] ?? 0 );
	$featured  = (int) ( $_POST['featured'] ?? 0 );

	update_post_meta( $artist_id, 'valt_featured', $featured ? 1 : 0 );
	wp_send_json_success( [ 'message' => $featured ? 'Artist featured.' : 'Artist unfeatured.' ] );
} );

// ─── Admin Award Points ──────────────────────────────────────────────

add_action( 'wp_ajax_valt_admin_award_points', function () {
	check_ajax_referer( 'valt_platform', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Insufficient permissions.' );
	}

	$user_id = (int) ( $_POST['user_id'] ?? 0 );
	$points  = (int) ( $_POST['points'] ?? 0 );
	$reason  = sanitize_text_field( $_POST['reason'] ?? 'admin_award' );

	if ( ! $user_id || ! $points ) {
		wp_send_json_error( 'User ID and points required.' );
	}

	if ( function_exists( 'valt_award_points' ) ) {
		$action = $points > 0 ? 'admin_award' : 'admin_deduct';
		$total  = valt_award_points( $user_id, $action, $points );
		wp_send_json_success( [ 'message' => "Points adjusted. New total: {$total}", 'total' => $total ] );
	} else {
		wp_send_json_error( 'Gamification not available.' );
	}
} );
