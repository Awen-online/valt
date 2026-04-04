<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API endpoints for Valt Platform v2.
 * Namespace: /wp-json/valt/v1/
 */

add_action( 'rest_api_init', function () {

	// ── Discovery ────────────────────────────────────────────────────

	register_rest_route( 'valt/v1', '/discover/artists', [
		'methods'             => 'GET',
		'callback'            => 'valt_rest_discover_artists',
		'permission_callback' => '__return_true',
		'args'                => [
			'search'   => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
			'genre'    => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
			'country'  => [ 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field' ],
			'sort'     => [ 'type' => 'string',  'default' => 'trending', 'enum' => [ 'trending', 'newest', 'alphabetical', 'fans' ] ],
			'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 50 ],
		],
	] );

	register_rest_route( 'valt/v1', '/discover/genres', [
		'methods'             => 'GET',
		'callback'            => 'valt_rest_get_genres',
		'permission_callback' => '__return_true',
	] );

	register_rest_route( 'valt/v1', '/discover/trending', [
		'methods'             => 'GET',
		'callback'            => 'valt_rest_trending_artists',
		'permission_callback' => '__return_true',
		'args'                => [
			'limit' => [ 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 50 ],
		],
	] );

	// ── Leaderboard ──────────────────────────────────────────────────

	register_rest_route( 'valt/v1', '/leaderboard', [
		'methods'             => 'GET',
		'callback'            => 'valt_rest_leaderboard',
		'permission_callback' => '__return_true',
		'args'                => [
			'scope'     => [ 'type' => 'string',  'default' => 'global', 'enum' => [ 'global', 'artist', 'monthly' ] ],
			'artist_id' => [ 'type' => 'integer', 'default' => 0 ],
			'limit'     => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 100 ],
		],
	] );

	// ── User ─────────────────────────────────────────────────────────

	register_rest_route( 'valt/v1', '/user/points', [
		'methods'             => 'GET',
		'callback'            => 'valt_rest_user_points',
		'permission_callback' => 'is_user_logged_in',
	] );

	register_rest_route( 'valt/v1', '/user/badges', [
		'methods'             => 'GET',
		'callback'            => 'valt_rest_user_badges',
		'permission_callback' => 'is_user_logged_in',
	] );

	// ── Campaigns ────────────────────────────────────────────────────

	register_rest_route( 'valt/v1', '/campaigns', [
		'methods'             => 'GET',
		'callback'            => 'valt_rest_active_campaigns',
		'permission_callback' => '__return_true',
		'args'                => [
			'limit' => [ 'type' => 'integer', 'default' => 12, 'minimum' => 1, 'maximum' => 50 ],
		],
	] );

	register_rest_route( 'valt/v1', '/campaigns/(?P<album_id>\d+)', [
		'methods'             => 'GET',
		'callback'            => 'valt_rest_campaign_detail',
		'permission_callback' => '__return_true',
		'args'                => [
			'album_id' => [ 'type' => 'integer', 'required' => true ],
		],
	] );

	register_rest_route( 'valt/v1', '/campaigns/(?P<album_id>\d+)/pledge', [
		'methods'             => 'POST',
		'callback'            => 'valt_rest_pledge_campaign',
		'permission_callback' => 'is_user_logged_in',
		'args'                => [
			'album_id' => [ 'type' => 'integer', 'required' => true ],
			'points'   => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
		],
	] );

	// ── Stripe ───────────────────────────────────────────────────────

	register_rest_route( 'valt/v1', '/stripe/create-checkout', [
		'methods'             => 'POST',
		'callback'            => 'valt_rest_create_checkout',
		'permission_callback' => 'is_user_logged_in',
		'args'                => [
			'song_id'        => [ 'type' => 'integer', 'required' => true ],
			'wallet_address' => [ 'type' => 'string',  'default' => '' ],
		],
	] );

	register_rest_route( 'valt/v1', '/stripe/webhook', [
		'methods'             => 'POST',
		'callback'            => 'valt_rest_stripe_webhook',
		'permission_callback' => '__return_true', // Auth via Stripe signature.
	] );

	// ── NFT Status ───────────────────────────────────────────────────

	register_rest_route( 'valt/v1', '/nft/status/(?P<song_id>\d+)', [
		'methods'             => 'GET',
		'callback'            => 'valt_rest_nft_status',
		'permission_callback' => 'is_user_logged_in',
		'args'                => [
			'song_id' => [ 'type' => 'integer', 'required' => true ],
		],
	] );
} );

// ─── Callback Implementations ────────────────────────────────────────

function valt_rest_discover_artists( WP_REST_Request $request ): WP_REST_Response {
	if ( function_exists( 'valt_discover_artists' ) ) {
		$results = valt_discover_artists( $request->get_params() );
		return new WP_REST_Response( $results, 200 );
	}
	return new WP_REST_Response( [], 200 );
}

function valt_rest_get_genres( WP_REST_Request $request ): WP_REST_Response {
	if ( function_exists( 'valt_get_genres' ) ) {
		return new WP_REST_Response( valt_get_genres(), 200 );
	}
	return new WP_REST_Response( [], 200 );
}

function valt_rest_trending_artists( WP_REST_Request $request ): WP_REST_Response {
	if ( function_exists( 'valt_get_trending_artists' ) ) {
		return new WP_REST_Response( valt_get_trending_artists( $request->get_param( 'limit' ) ), 200 );
	}
	return new WP_REST_Response( [], 200 );
}

function valt_rest_leaderboard( WP_REST_Request $request ): WP_REST_Response {
	if ( function_exists( 'valt_get_leaderboard' ) ) {
		$data = valt_get_leaderboard(
			$request->get_param( 'scope' ),
			$request->get_param( 'artist_id' ),
			$request->get_param( 'limit' )
		);
		return new WP_REST_Response( $data, 200 );
	}
	return new WP_REST_Response( [], 200 );
}

function valt_rest_user_points( WP_REST_Request $request ): WP_REST_Response {
	$user_id = get_current_user_id();
	$data    = [ 'points' => 0, 'level' => [] ];
	if ( function_exists( 'valt_get_user_points' ) ) {
		$data['points'] = valt_get_user_points( $user_id );
	}
	if ( function_exists( 'valt_get_user_level' ) ) {
		$data['level'] = valt_get_user_level( $user_id );
	}
	return new WP_REST_Response( $data, 200 );
}

function valt_rest_user_badges( WP_REST_Request $request ): WP_REST_Response {
	if ( function_exists( 'valt_get_user_badges' ) ) {
		return new WP_REST_Response( valt_get_user_badges( get_current_user_id() ), 200 );
	}
	return new WP_REST_Response( [], 200 );
}

function valt_rest_active_campaigns( WP_REST_Request $request ): WP_REST_Response {
	if ( function_exists( 'valt_get_active_campaigns' ) ) {
		return new WP_REST_Response( valt_get_active_campaigns( $request->get_param( 'limit' ) ), 200 );
	}
	return new WP_REST_Response( [], 200 );
}

function valt_rest_campaign_detail( WP_REST_Request $request ): WP_REST_Response {
	$album_id = (int) $request->get_param( 'album_id' );
	if ( function_exists( 'valt_get_campaign_progress' ) ) {
		$data = valt_get_campaign_progress( $album_id );
		if ( $data ) {
			return new WP_REST_Response( $data, 200 );
		}
	}
	return new WP_REST_Response( [ 'error' => 'Campaign not found.' ], 404 );
}

function valt_rest_pledge_campaign( WP_REST_Request $request ): WP_REST_Response {
	$album_id = (int) $request->get_param( 'album_id' );
	$points   = (int) $request->get_param( 'points' );
	$user_id  = get_current_user_id();

	if ( function_exists( 'valt_pledge_to_campaign' ) ) {
		$result = valt_pledge_to_campaign( $user_id, $album_id, $points );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
		}
		return new WP_REST_Response( [ 'total_pledged' => $result ], 200 );
	}
	return new WP_REST_Response( [ 'error' => 'Campaigns not available.' ], 501 );
}

function valt_rest_create_checkout( WP_REST_Request $request ): WP_REST_Response {
	$song_id = (int) $request->get_param( 'song_id' );
	$wallet  = sanitize_text_field( $request->get_param( 'wallet_address' ) );
	$user_id = get_current_user_id();

	$result = valt_create_checkout_session( $song_id, $user_id, $wallet );
	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
	}
	return new WP_REST_Response( $result, 200 );
}

function valt_rest_stripe_webhook( WP_REST_Request $request ): WP_REST_Response {
	$payload   = $request->get_body();
	$signature = $request->get_header( 'stripe-signature' );

	if ( ! $signature ) {
		return new WP_REST_Response( [ 'error' => 'Missing signature.' ], 400 );
	}

	$result = valt_handle_stripe_webhook( $payload, $signature );
	if ( is_wp_error( $result ) ) {
		return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 400 );
	}
	return new WP_REST_Response( [ 'received' => true ], 200 );
}

function valt_rest_nft_status( WP_REST_Request $request ): WP_REST_Response {
	$song_id = (int) $request->get_param( 'song_id' );
	return new WP_REST_Response( [
		'status'         => get_post_meta( $song_id, 'valt_nft_status', true ) ?: 'none',
		'transaction_id' => get_post_meta( $song_id, 'valt_nft_transaction_id', true ),
		'asset_id'       => get_post_meta( $song_id, 'valt_nft_asset_id', true ),
		'mint_count'     => (int) get_post_meta( $song_id, 'valt_mint_count', true ),
	], 200 );
}
