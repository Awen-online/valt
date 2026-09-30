<?php
defined( 'ABSPATH' ) || exit;

/**
 * Multi-edition collect: POST /wp-json/valt/v1/collect  { song_id, qty, nonce }
 *
 * Picks `qty` free editions OF THIS SONG (we do the "random" part, since the NMKR project is
 * shared across artists), creates an NMKR `nmkr_pay_specific` payment transaction for exactly
 * those editions, and returns NMKR's hosted checkout URL (?mtid=…). One checkout, one payment.
 *
 * NMKR requirement (found by testing, not documented): the project's price list must contain an
 * entry for every quantity offered (countNft = 1..VALT_COLLECT_MAX_QTY), or NMKR's checkout
 * crashes for that quantity. The buyer pays the sum of the per-edition prices.
 * Quantity 1 doesn't use this endpoint: it keeps the direct single-edition link.
 */

if ( ! defined( 'VALT_COLLECT_MAX_QTY' ) ) {
	define( 'VALT_COLLECT_MAX_QTY', 5 );
}

/**
 * Anti-CSRF token for the collect endpoint that does NOT depend on who is logged in.
 * WordPress nonces are bound to the user + session, and the REST API treats a cookie request
 * without X-WP-Nonce as logged out, so a connected (logged-in) visitor's nonce never verified.
 * Collecting doesn't need the user's identity, so sign a rotating value with the site salt.
 * Valid for the current and previous 12h window; safe to embed in cached pages.
 */
function valt_collect_token( int $offset = 0 ): string {
	$tick = (int) floor( time() / ( 12 * HOUR_IN_SECONDS ) ) - $offset;
	return substr( hash_hmac( 'sha256', 'valt_collect|' . $tick, wp_salt( 'nonce' ) ), 0, 20 );
}

function valt_collect_token_ok( string $t ): bool {
	return $t !== '' && ( hash_equals( valt_collect_token( 0 ), $t ) || hash_equals( valt_collect_token( 1 ), $t ) );
}

function valt_collect_client_ip(): string {
	if ( defined( 'VALT_TRUSTED_PROXY' ) && VALT_TRUSTED_PROXY && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$hops = array_map( 'trim', explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		$ip   = end( $hops );
	} else {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	}
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'valt/v1', '/collect', [
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // public: anyone may start a checkout; guarded by nonce + rate limit
		'args'                => [
			'song_id' => [ 'required' => true, 'type' => 'integer' ],
			'qty'     => [ 'required' => true, 'type' => 'integer' ],
			'nonce'   => [ 'required' => true, 'type' => 'string' ],
		],
		'callback'            => 'valt_collect_create_checkout',
	] );
} );

function valt_collect_create_checkout( WP_REST_Request $req ) {
	if ( ! valt_collect_token_ok( (string) $req['nonce'] ) ) {
		return new WP_Error( 'valt_bad_nonce', 'Please refresh the page and try again.', [ 'status' => 403 ] );
	}

	// Each call reserves editions on NMKR for ~20 minutes, so stop anyone tying up stock.
	$ip   = valt_collect_client_ip();
	$key  = 'valt_collect_rl_' . md5( $ip );
	$hits = (int) get_transient( $key );
	if ( $hits >= 6 ) {
		return new WP_Error( 'valt_rate_limited', 'Too many checkout attempts. Please wait a few minutes.', [ 'status' => 429 ] );
	}
	set_transient( $key, $hits + 1, 10 * MINUTE_IN_SECONDS );

	$sid = (int) $req['song_id'];
	$qty = (int) $req['qty'];
	if ( get_post_type( $sid ) !== 'song' || get_post_status( $sid ) !== 'publish' ) {
		return new WP_Error( 'valt_bad_song', 'Song not found.', [ 'status' => 404 ] );
	}
	if ( $qty < 2 || $qty > VALT_COLLECT_MAX_QTY ) {
		return new WP_Error( 'valt_bad_qty', 'Choose between 2 and ' . VALT_COLLECT_MAX_QTY . ' editions.', [ 'status' => 400 ] );
	}

	$config = valt_nmkr_config();
	$last   = null;
	// Two tries: if an edition was taken since the (cached) stock check, refresh and pick again.
	for ( $attempt = 0; $attempt < 2; $attempt++ ) {
		if ( $attempt ) {
			delete_transient( 'valt_song_inventory' );
		}
		$inv  = valt_song_inventory();
		$uids = $inv[ $sid ]['uids'] ?? [];
		if ( count( $uids ) < $qty ) {
			return new WP_Error( 'valt_not_enough', sprintf( 'Only %d edition%s left.', count( $uids ), count( $uids ) === 1 ? '' : 's' ), [ 'status' => 409, 'available' => count( $uids ) ] );
		}
		shuffle( $uids );
		$pick = array_slice( $uids, 0, $qty );

		$res = valt_nmkr_request( 'POST', 'CreatePaymentTransaction', [
			'projectUid'               => $config['project_uid'],
			'paymentTransactionType'   => 'nmkr_pay_specific',
			'customerIpAddress'        => $ip,
			'referer'                  => 'valt.digital',
			'customProperties'         => [ 'song_id' => (string) $sid, 'qty' => (string) $qty ],
			'paymentgatewayParameters' => [ 'mintNfts' => [ 'reserveNfts' => array_map( function ( $u ) {
				return [ 'nftUid' => $u, 'tokencount' => 1 ];
			}, $pick ) ] ],
		] );
		if ( ! is_wp_error( $res ) && ! empty( $res['nmkrPayUrl'] ) ) {
			valt_log_event( 'collect_checkout', "Checkout for {$qty} editions of song {$sid}", [ 'mtid' => $res['paymentTransactionUid'] ?? '' ] );
			return [
				'url'   => esc_url_raw( $res['nmkrPayUrl'] ),
				'qty'   => $qty,
				'price' => isset( $res['paymentgatewayResults']['priceInLovelace'] ) ? $res['paymentgatewayResults']['priceInLovelace'] / 1000000 : null,
			];
		}
		$last = $res;
	}

	valt_log_event( 'collect_error', "Checkout failed for {$qty} editions of song {$sid}", [ 'error' => is_wp_error( $last ) ? $last->get_error_message() : 'no nmkrPayUrl' ] );
	return new WP_Error( 'valt_checkout_failed', 'Checkout could not be started. Please try again in a moment.', [ 'status' => 502 ] );
}
