<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shared configuration getters and utility functions for Valt Platform v2.
 */

/**
 * Get NMKR configuration for the active environment.
 */
function valt_nmkr_config(): array {
	$mode = get_option( 'valt_nmkr_mode', 'preprod' );
	return [
		'mode'        => $mode,
		'api_key'     => get_option( "valt_nmkr_{$mode}_api_key" )
			?: ( defined( 'VALT_NMKR_API_KEY' ) ? VALT_NMKR_API_KEY : '' ),
		'project_uid' => get_option( "valt_nmkr_{$mode}_project_uid" )
			?: ( defined( 'VALT_NMKR_PROJECT_UID' ) ? VALT_NMKR_PROJECT_UID : '' ),
		'policy_id'   => get_option( 'valt_nmkr_policy_id', '' ),
		'api_url'     => $mode === 'mainnet'
			? 'https://studio-api.nmkr.io/v2'
			: 'https://studio-api.preprod.nmkr.io/v2',
		'pinata_jwt'  => get_option( 'valt_pinata_jwt', '' )
			?: ( defined( 'VALT_PINATA_JWT' ) ? VALT_PINATA_JWT : '' ),
	];
}

/**
 * Get Stripe configuration for the active environment.
 */
function valt_stripe_config(): array {
	$mode = get_option( 'valt_stripe_mode', 'test' );
	return [
		'mode'            => $mode,
		'secret_key'      => defined( 'VALT_STRIPE_SECRET_KEY' ) ? VALT_STRIPE_SECRET_KEY : '',
		'publishable_key' => defined( 'VALT_STRIPE_PUBLISHABLE_KEY' ) ? VALT_STRIPE_PUBLISHABLE_KEY : '',
		'webhook_secret'  => defined( 'VALT_STRIPE_WEBHOOK_SECRET' ) ? VALT_STRIPE_WEBHOOK_SECRET : '',
	];
}

/**
 * Get gamification points config with defaults.
 */
function valt_points_config(): array {
	$defaults = [
		'nft_purchase'     => 100,
		'daily_login'      => 5,
		'profile_complete' => 25,
		'wallet_connect'   => 15,
		'content_view'     => 1,
		'share'            => 10,
		'campaign_back'    => 0, // 1:1 with pledged amount
		'badge_earned'     => 20,
	];
	return wp_parse_args( get_option( 'valt_points_config', [] ), $defaults );
}

/**
 * Get level thresholds with defaults.
 */
function valt_level_thresholds(): array {
	$defaults = [
		1 => [ 'name' => 'Listener',  'threshold' => 0 ],
		2 => [ 'name' => 'Fan',       'threshold' => 50 ],
		3 => [ 'name' => 'Superfan',  'threshold' => 200 ],
		4 => [ 'name' => 'Patron',    'threshold' => 500 ],
		5 => [ 'name' => 'Legend',     'threshold' => 1500 ],
	];
	return get_option( 'valt_level_thresholds', $defaults );
}

/**
 * Get badge definitions with defaults.
 */
function valt_badge_definitions(): array {
	$defaults = [
		'first_nft'        => [ 'name' => 'First NFT',        'desc' => 'Collected your first song NFT',           'icon' => 'star' ],
		'collector_5'      => [ 'name' => 'Collector',         'desc' => 'Own 5+ NFTs',                             'icon' => 'collection' ],
		'collector_25'     => [ 'name' => 'Super Collector',   'desc' => 'Own 25+ NFTs',                            'icon' => 'trophy' ],
		'early_supporter'  => [ 'name' => 'Early Supporter',   'desc' => 'Backed a campaign before 50% funded',     'icon' => 'seedling' ],
		'wallet_connected' => [ 'name' => 'Web3 Ready',        'desc' => 'Connected a Cardano wallet',              'icon' => 'wallet' ],
		'daily_streak_7'   => [ 'name' => 'Dedicated Fan',     'desc' => '7-day login streak',                      'icon' => 'fire' ],
		'multi_artist'     => [ 'name' => 'Music Explorer',    'desc' => 'Hold NFTs from 3+ artists',               'icon' => 'globe' ],
	];
	return get_option( 'valt_badge_definitions', $defaults );
}

/**
 * Make an HTTP request to the NMKR API.
 *
 * @param string $method  HTTP method (GET, POST, etc.)
 * @param string $path    API path (e.g., /MintAndSendSpecific/...)
 * @param array  $body    Request body for POST/PUT.
 * @return array|WP_Error Decoded JSON response or WP_Error.
 */
function valt_nmkr_request( string $method, string $path, array $body = [] ) {
	$config = valt_nmkr_config();
	if ( empty( $config['api_key'] ) ) {
		return new WP_Error( 'valt_nmkr_no_key', 'NMKR API key is not configured.' );
	}

	$url  = rtrim( $config['api_url'], '/' ) . '/' . ltrim( $path, '/' );
	$args = [
		'method'  => $method,
		'timeout' => 60,
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $config['api_key'],
		],
	];

	if ( ! empty( $body ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
		$args['body'] = wp_json_encode( $body );
	}

	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code < 200 || $code >= 300 ) {
		$msg = $data['message'] ?? $data['error'] ?? wp_remote_retrieve_body( $response );
		return new WP_Error( 'valt_nmkr_api_error', "NMKR API {$code}: {$msg}", [ 'status' => $code ] );
	}

	return $data;
}

/**
 * Log a Valt event (NFT lifecycle, errors, etc.) for the admin monitor.
 */
function valt_log_event( string $type, string $message, array $context = [] ): void {
	$log = get_option( 'valt_event_log', [] );
	array_unshift( $log, [
		'type'    => $type,
		'message' => $message,
		'context' => $context,
		'time'    => current_time( 'mysql' ),
	] );
	// Keep last 200 entries.
	$log = array_slice( $log, 0, 200 );
	update_option( 'valt_event_log', $log, false );
}

/**
 * Sanitize a Cardano wallet address (bech32 format).
 */
function valt_sanitize_wallet_address( string $address ): string {
	$address = trim( $address );
	// Cardano bech32 addresses start with addr (mainnet) or addr_test (testnet).
	if ( ! preg_match( '/^addr(_test)?1[a-z0-9]{50,120}$/', $address ) ) {
		return '';
	}
	return $address;
}

/**
 * Generate a URL-safe asset name from a song title for CIP25 minting.
 */
function valt_generate_asset_name( string $title, int $song_id ): string {
	$slug = sanitize_title( $title );
	$slug = preg_replace( '/[^a-z0-9]/', '', $slug );
	$slug = substr( $slug, 0, 20 );
	return $slug . $song_id;
}
