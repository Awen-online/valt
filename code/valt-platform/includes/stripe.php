<?php
defined( 'ABSPATH' ) || exit;

/**
 * Stripe integration for Valt Platform v2.
 *
 * Handles: product sync, checkout session creation, webhook processing.
 * Uses raw Stripe API via wp_remote_* (no SDK dependency).
 */

// ─── Stripe API Helper ──────────────────────────────────────────────

/**
 * Make a request to the Stripe API.
 *
 * @param string $method HTTP method.
 * @param string $path   API path (e.g., /v1/checkout/sessions).
 * @param array  $body   Form-encoded body params.
 * @return array|WP_Error Decoded response or error.
 */
function valt_stripe_request( string $method, string $path, array $body = [] ) {
	$config = valt_stripe_config();
	if ( empty( $config['secret_key'] ) ) {
		return new WP_Error( 'valt_stripe_no_key', 'Stripe secret key is not configured.' );
	}

	$url  = 'https://api.stripe.com' . $path;
	$args = [
		'method'  => $method,
		'timeout' => 30,
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode( $config['secret_key'] . ':' ),
			'Content-Type'  => 'application/x-www-form-urlencoded',
		],
	];

	if ( ! empty( $body ) ) {
		$args['body'] = $body;
	}

	$response = wp_remote_request( $url, $args );
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	$code = wp_remote_retrieve_response_code( $response );

	if ( $code < 200 || $code >= 300 ) {
		$msg = $data['error']['message'] ?? "HTTP {$code}";
		return new WP_Error( 'valt_stripe_error', "Stripe: {$msg}", $data );
	}

	return $data;
}

// ─── Product Sync ────────────────────────────────────────────────────

/**
 * Create or update a Stripe Product + Price for a song.
 *
 * @param int $song_id Song post ID.
 * @return array|WP_Error Stripe product data or error.
 */
function valt_sync_song_to_stripe( int $song_id ) {
	$song      = get_post( $song_id );
	$price_usd = (int) get_post_meta( $song_id, 'valt_nft_price_usd', true );
	if ( $price_usd <= 0 ) {
		return new WP_Error( 'valt_no_price', 'Song has no USD price set.' );
	}

	$product_id = get_post_meta( $song_id, 'valt_stripe_product_id', true );

	if ( $product_id ) {
		// Update existing product.
		valt_stripe_request( 'POST', "/v1/products/{$product_id}", [
			'name'        => $song->post_title,
			'description' => 'Song NFT — ' . $song->post_title,
		] );
	} else {
		// Create product.
		$product = valt_stripe_request( 'POST', '/v1/products', [
			'name'                   => $song->post_title,
			'description'            => 'Song NFT — ' . $song->post_title,
			'metadata[song_id]'      => $song_id,
			'metadata[platform]'     => 'valt',
		] );
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		$product_id = $product['id'];
		update_post_meta( $song_id, 'valt_stripe_product_id', $product_id );
	}

	// Create new price (Stripe prices are immutable — always create fresh).
	$price = valt_stripe_request( 'POST', '/v1/prices', [
		'product'     => $product_id,
		'unit_amount' => $price_usd,
		'currency'    => 'usd',
	] );
	if ( is_wp_error( $price ) ) {
		return $price;
	}

	update_post_meta( $song_id, 'valt_stripe_price_id', $price['id'] );

	valt_log_event( 'stripe_sync', "Song {$song_id} synced to Stripe", [
		'product' => $product_id,
		'price'   => $price['id'],
	] );

	return [ 'product_id' => $product_id, 'price_id' => $price['id'] ];
}

// ─── Checkout Session ────────────────────────────────────────────────

/**
 * Create a Stripe Checkout Session for a song purchase.
 *
 * @param int    $song_id        Song post ID.
 * @param int    $user_id        WP user ID.
 * @param string $wallet_address Optional Cardano wallet for NFT delivery.
 * @return array|WP_Error Session data with checkout_url, or error.
 */
function valt_create_checkout_session( int $song_id, int $user_id, string $wallet_address = '' ) {
	$price_id = get_post_meta( $song_id, 'valt_stripe_price_id', true );
	if ( empty( $price_id ) ) {
		return new WP_Error( 'valt_no_stripe_price', 'Song is not yet synced to Stripe.' );
	}

	$params = [
		'mode'                        => 'payment',
		'line_items[0][price]'        => $price_id,
		'line_items[0][quantity]'     => 1,
		'success_url'                 => home_url( '/checkout/success/?session_id={CHECKOUT_SESSION_ID}' ),
		'cancel_url'                  => home_url( '/checkout/cancel/' ),
		'metadata[song_id]'           => $song_id,
		'metadata[user_id]'           => $user_id,
		'metadata[wallet_address]'    => $wallet_address,
		'metadata[platform]'          => 'valt',
	];

	$session = valt_stripe_request( 'POST', '/v1/checkout/sessions', $params );
	if ( is_wp_error( $session ) ) {
		return $session;
	}

	valt_log_event( 'stripe_checkout', "Checkout session created for song {$song_id}", [
		'session_id' => $session['id'],
		'user_id'    => $user_id,
	] );

	return [
		'checkout_url' => $session['url'],
		'session_id'   => $session['id'],
	];
}

// ─── Webhook Handler ─────────────────────────────────────────────────

/**
 * Verify and process a Stripe webhook event.
 *
 * @param string $payload   Raw request body.
 * @param string $signature Stripe-Signature header value.
 * @return true|WP_Error True on success, error on failure.
 */
function valt_handle_stripe_webhook( string $payload, string $signature ) {
	$config = valt_stripe_config();
	if ( empty( $config['webhook_secret'] ) ) {
		return new WP_Error( 'valt_no_webhook_secret', 'Webhook secret not configured.' );
	}

	// Verify signature.
	$parts     = [];
	foreach ( explode( ',', $signature ) as $part ) {
		[ $key, $val ] = explode( '=', $part, 2 );
		$parts[ trim( $key ) ] = trim( $val );
	}

	$timestamp = $parts['t'] ?? '';
	$sig       = $parts['v1'] ?? '';
	if ( ! $timestamp || ! $sig ) {
		return new WP_Error( 'valt_bad_signature', 'Missing signature components.' );
	}

	// Reject if timestamp is older than 5 minutes.
	if ( abs( time() - (int) $timestamp ) > 300 ) {
		return new WP_Error( 'valt_stale_webhook', 'Webhook timestamp too old.' );
	}

	$expected = hash_hmac( 'sha256', "{$timestamp}.{$payload}", $config['webhook_secret'] );
	if ( ! hash_equals( $expected, $sig ) ) {
		return new WP_Error( 'valt_invalid_signature', 'Stripe signature verification failed.' );
	}

	$event = json_decode( $payload, true );
	if ( empty( $event['type'] ) ) {
		return new WP_Error( 'valt_bad_event', 'Invalid event payload.' );
	}

	valt_log_event( 'stripe_webhook', "Received {$event['type']}", [
		'event_id' => $event['id'] ?? '',
	] );

	if ( $event['type'] === 'checkout.session.completed' ) {
		$session    = $event['data']['object'];
		$session_id = $session['id'];

		// Dedup check.
		if ( get_transient( "valt_processed_{$session_id}" ) ) {
			return true;
		}

		// Store session data for async processing.
		set_transient( "valt_stripe_session_{$session_id}", $session, HOUR_IN_SECONDS );

		// Schedule async processing.
		wp_schedule_single_event( time() + 1, 'valt_process_stripe_checkout_async', [ $session_id ] );
	}

	return true;
}

// ─── Async Checkout Processing ───────────────────────────────────────

/**
 * Process a completed Stripe checkout session. Called by WP Cron.
 *
 * @param string $session_id Stripe checkout session ID.
 */
function valt_do_process_stripe_checkout( string $session_id ): void {
	// Dedup.
	if ( get_transient( "valt_processed_{$session_id}" ) ) {
		return;
	}

	$session = get_transient( "valt_stripe_session_{$session_id}" );
	if ( ! $session ) {
		valt_log_event( 'stripe_error', "Session {$session_id} not found in transient." );
		return;
	}

	$metadata = $session['metadata'] ?? [];
	$song_id  = (int) ( $metadata['song_id'] ?? 0 );
	$user_id  = (int) ( $metadata['user_id'] ?? 0 );
	$wallet   = $metadata['wallet_address'] ?? '';

	if ( ! $song_id ) {
		valt_log_event( 'stripe_error', "No song_id in session {$session_id} metadata." );
		return;
	}

	valt_log_event( 'stripe_processing', "Processing checkout for song {$song_id}", [
		'session_id' => $session_id,
		'user_id'    => $user_id,
		'wallet'     => $wallet,
	] );

	// Award gamification points.
	if ( $user_id && function_exists( 'valt_award_points' ) ) {
		$artist_id = (int) get_post_meta( $song_id, 'artist', true );
		$points_config = valt_points_config();
		valt_award_points( $user_id, 'nft_purchase', $points_config['nft_purchase'], 'song', $song_id, $artist_id );
	}

	// Trigger NFT mint if wallet provided.
	if ( $wallet ) {
		$wallet = valt_sanitize_wallet_address( $wallet );
		if ( $wallet ) {
			valt_schedule_nft_mint( $song_id, $wallet );
		}
	}

	// Set dedup transient.
	set_transient( "valt_processed_{$session_id}", true, DAY_IN_SECONDS );

	/**
	 * Fires when a purchase is fully processed.
	 *
	 * @param int    $song_id    Song post ID.
	 * @param int    $user_id    WP user ID.
	 * @param string $session_id Stripe session ID.
	 */
	do_action( 'valt_purchase_complete', $song_id, $user_id, $session_id );
}
