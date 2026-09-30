<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shared configuration getters and utility functions for Valt Platform v2.
 */

/**
 * Resolve a song's artist post ID robustly.
 *
 * Pods relationship meta ('artist') can come back as a non-scalar (array/object),
 * where a naive (int) cast collapses to 1 (= the default "Hello world!" post).
 * Mirrors single-song.php: raw meta → _pods_artist → podsrel, then validates the
 * result is actually an 'artist' post.
 */
function valt_resolve_artist_id( int $song_id ): int {
	$raw = get_post_meta( $song_id, 'artist', true );
	$id  = 0;
	if ( is_numeric( $raw ) ) {
		$id = (int) $raw;
	} elseif ( is_array( $raw ) && ! empty( $raw ) ) {
		$first = reset( $raw );
		$id = ( is_object( $first ) && isset( $first->ID ) ) ? (int) $first->ID : (int) $first;
	} elseif ( is_object( $raw ) && isset( $raw->ID ) ) {
		$id = (int) $raw->ID;
	}
	if ( ! $id ) {
		$pods = get_post_meta( $song_id, '_pods_artist', true );
		if ( is_array( $pods ) && ! empty( $pods ) ) {
			$id = (int) reset( $pods );
		}
	}
	if ( ! $id ) {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT related_item_id FROM {$wpdb->prefix}podsrel WHERE item_id = %d AND pod_id = (SELECT id FROM {$wpdb->prefix}posts WHERE post_name = 'song' AND post_type = '_pods_pod' LIMIT 1) LIMIT 1",
			$song_id
		) );
	}
	// Never return a non-artist (e.g. the default Hello-world post).
	if ( $id && get_post_type( $id ) !== 'artist' ) {
		$id = 0;
	}
	return $id;
}

/**
 * Get NMKR configuration for the active environment.
 */
/**
 * wp-config.php constant names that can pin each NMKR/IPFS secret, most
 * specific first. A mode-specific constant wins over the generic one.
 *
 * @param string $key  One of 'api_key', 'project_uid', 'pinata_jwt'.
 * @param string $mode 'preprod' or 'mainnet'.
 * @return string[]
 */
function valt_secret_constants( string $key, string $mode ): array {
	$up = strtoupper( $mode );
	switch ( $key ) {
		case 'api_key':
			return [ "VALT_NMKR_{$up}_API_KEY", 'VALT_NMKR_API_KEY' ];
		case 'project_uid':
			return [ "VALT_NMKR_{$up}_PROJECT_UID", 'VALT_NMKR_PROJECT_UID' ];
		case 'pinata_jwt':
			return [ 'VALT_PINATA_JWT' ];
	}
	return [];
}

/**
 * Whether any of the named wp-config.php constants pins this secret. When true
 * the value lives in wp-config.php, the database option is ignored, and the
 * settings field should be masked/disabled.
 *
 * @param string[] $constants
 */
function valt_secret_pinned( array $constants ): bool {
	foreach ( $constants as $c ) {
		if ( defined( $c ) && '' !== (string) constant( $c ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Resolve a secret. A wp-config.php constant is AUTHORITATIVE: when set it wins
 * over the database option, so mainnet-custody credentials can be moved out of
 * the options table (and out of any DB dump or the settings screen) entirely.
 * Falls back to the option only when no constant is defined, so existing
 * installs are unaffected until an operator moves the secret to wp-config.php.
 *
 * @param string[] $constants Constant names, most specific first.
 * @param string   $option    Database option name to fall back to.
 */
function valt_secret( array $constants, string $option ): string {
	foreach ( $constants as $c ) {
		if ( defined( $c ) && '' !== (string) constant( $c ) ) {
			return (string) constant( $c );
		}
	}
	return (string) get_option( $option, '' );
}

function valt_nmkr_config(): array {
	$mode = get_option( 'valt_nmkr_mode', 'preprod' );
	return [
		'mode'        => $mode,
		'api_key'     => valt_secret( valt_secret_constants( 'api_key', $mode ), "valt_nmkr_{$mode}_api_key" ),
		'project_uid' => valt_secret( valt_secret_constants( 'project_uid', $mode ), "valt_nmkr_{$mode}_project_uid" ),
		'policy_id'   => get_option( 'valt_nmkr_policy_id', '' ),
		'api_url'     => $mode === 'mainnet'
			? 'https://studio-api.nmkr.io/v2'
			: 'https://studio-api.preprod.nmkr.io/v2',
		'pinata_jwt'  => valt_secret( valt_secret_constants( 'pinata_jwt', $mode ), 'valt_pinata_jwt' ),
	];
}

/**
 * Feature flags — enable/disable major subsystems from Settings.
 */
function valt_feature_enabled( string $feature ): bool {
	$defaults = [
		'gamification' => false,
		'campaigns'    => false,
		'leaderboard'  => false,
		'discovery'    => true,
		'nmkr'         => true,
	];
	$flags = wp_parse_args( get_option( 'valt_feature_flags', [] ), $defaults );
	return ! empty( $flags[ $feature ] );
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
