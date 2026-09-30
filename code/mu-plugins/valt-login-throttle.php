<?php
/**
 * Plugin Name: Valt — login throttle
 * Description: Rate-limits failed wp-login attempts per IP to blunt brute-force / credential-stuffing ahead of the mainnet launch.
 * Version:     1.0.0
 *
 * Server-side perimeter control (mainnet prerequisite, M3 Security Audit 6.1 #4).
 * The host has no WAF in front of WordPress, so wp-login is otherwise unthrottled.
 * After too many failures from one IP within a window, further attempts from that
 * IP are refused for a lockout period, then the lock auto-expires. Correct
 * credentials are also refused while locked out (that is the point: it stops a
 * stuffing run that happens to guess right).
 *
 * Tunables (define in wp-config.php, or filter):
 *   VALT_LOGIN_MAX_FAILS  default 5    failures allowed before lockout
 *   VALT_LOGIN_WINDOW     default 900  seconds failures are counted over
 *   VALT_LOGIN_LOCKOUT    default 900  seconds an IP stays locked once tripped
 *   VALT_TRUSTED_PROXY    if defined, trust the right-most X-Forwarded-For hop
 *                         (set this once Cloudflare / a proxy sits in front).
 *
 * @package Valt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Real client IP. REMOTE_ADDR by default (the host passes the true client IP).
 * Only consults X-Forwarded-For when VALT_TRUSTED_PROXY is defined, taking the
 * right-most hop (the one the trusted proxy appended, which a client cannot forge
 * past the proxy). Mirrors the survey/intake IP handling shipped in M3.
 */
function valt_login_throttle_ip(): string {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	if ( defined( 'VALT_TRUSTED_PROXY' ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$hops      = array_map( 'trim', explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		$candidate = end( $hops );
		if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
			return $candidate;
		}
	}
	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : 'unknown';
}

function valt_login_throttle_key( string $ip ): string {
	return 'valt_login_fails_' . md5( $ip );
}

function valt_login_throttle_max(): int {
	$n = defined( 'VALT_LOGIN_MAX_FAILS' ) ? (int) VALT_LOGIN_MAX_FAILS : 5;
	return max( 1, (int) apply_filters( 'valt_login_max_fails', $n ) );
}

function valt_login_throttle_window(): int {
	$n = defined( 'VALT_LOGIN_WINDOW' ) ? (int) VALT_LOGIN_WINDOW : 900;
	return max( 60, (int) apply_filters( 'valt_login_window', $n ) );
}

function valt_login_throttle_lockout(): int {
	$n = defined( 'VALT_LOGIN_LOCKOUT' ) ? (int) VALT_LOGIN_LOCKOUT : 900;
	return max( 60, (int) apply_filters( 'valt_login_lockout', $n ) );
}

/**
 * Refuse the attempt (before or after the password check) once the IP is over
 * the limit. Runs late (priority 30) so it overrides a valid WP_User too.
 */
add_filter( 'authenticate', function ( $user, $username ) {
	if ( '' === (string) $username ) {
		return $user; // no attempt being made
	}
	$data  = get_transient( valt_login_throttle_key( valt_login_throttle_ip() ) );
	$fails = is_array( $data ) ? (int) ( $data['count'] ?? 0 ) : 0;
	if ( $fails >= valt_login_throttle_max() ) {
		return new WP_Error(
			'valt_login_locked',
			sprintf(
				/* translators: %d: minutes until retry */
				esc_html__( 'Too many failed attempts. Try again in about %d minutes.', 'valt' ),
				(int) round( valt_login_throttle_lockout() / 60 )
			)
		);
	}
	return $user;
}, 30, 2 );

/**
 * Count a failure. Skip failures we produced ourselves so a locked-out client
 * cannot keep extending its own lockout indefinitely.
 */
add_action( 'wp_login_failed', function ( $username, $error = null ) {
	if ( $error instanceof WP_Error && in_array( 'valt_login_locked', $error->get_error_codes(), true ) ) {
		return;
	}
	$key   = valt_login_throttle_key( valt_login_throttle_ip() );
	$data  = get_transient( $key );
	$count = ( is_array( $data ) ? (int) ( $data['count'] ?? 0 ) : 0 ) + 1;
	// Under the limit: expire on the counting window. Once tripped: hold for the lockout.
	$ttl = $count >= valt_login_throttle_max() ? valt_login_throttle_lockout() : valt_login_throttle_window();
	set_transient( $key, [ 'count' => $count ], $ttl );
}, 10, 2 );

/** Clear the counter on a successful login. */
add_action( 'wp_login', function () {
	delete_transient( valt_login_throttle_key( valt_login_throttle_ip() ) );
} );
