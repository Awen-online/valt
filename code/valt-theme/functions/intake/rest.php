<?php
/**
 * Valt Artist Intake — REST routes (namespace valt/v1).
 *
 *   POST /valt/v1/artist-intake     public submit (honeypot + consent + rate limit; intentionally anonymous)
 *   GET  /valt/v1/artist-intake     admin: list
 *   GET  /valt/v1/artist-intake-csv admin: CSV
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'valt/v1', '/artist-intake', array(
		array(
			'methods'             => 'POST',
			'callback'            => 'valt_intake_rest_submit',
			'permission_callback' => '__return_true',
		),
		array(
			'methods'             => 'GET',
			'callback'            => 'valt_intake_rest_list',
			'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		),
	) );

	register_rest_route( 'valt/v1', '/artist-intake-csv', array(
		'methods'             => 'GET',
		'callback'            => 'valt_intake_rest_export',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
	) );
} );

/**
 * Handle a lead submission.
 */
function valt_intake_rest_submit( WP_REST_Request $request ) {
	$settings = valt_intake_get_settings();
	if ( empty( $settings['enabled'] ) ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'closed' ), 403 );
	}

	$body = $request->get_json_params();
	if ( ! is_array( $body ) ) {
		$body = $request->get_params();
	}

	// Honeypot — bots fill it; humans don't. Pretend success, store nothing.
	if ( ! empty( $body['website'] ) ) {
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	$name  = isset( $body['artist_name'] ) ? sanitize_text_field( substr( (string) $body['artist_name'], 0, 200 ) ) : '';
	$email = isset( $body['email'] ) ? sanitize_email( (string) $body['email'] ) : '';

	if ( '' === $name ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'name_required', 'message' => 'Please add your artist name.' ), 400 );
	}
	if ( ! is_email( $email ) ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'email_invalid', 'message' => 'Please enter a valid email.' ), 400 );
	}
	if ( empty( $body['consent'] ) ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'consent_required', 'message' => 'Please tick the consent box so we can reply.' ), 400 );
	}

	// Rate limit. Per-email (3/hr) and per-IP (5/hr) catch normal duplicates;
	// a site-wide hourly cap (independent of the spoofable IP and of the email)
	// bounds a flood even if an attacker rotates both — this is also what caps
	// outbound wp_mail() volume so a burst cannot email-bomb the team.
	// See M3 security assessment FIND-01.
	$ip           = valt_intake_client_ip();
	$global_max   = (int) apply_filters( 'valt_intake_global_hourly_cap', 30 );
	$global_key   = 'valt_intake_rl_global';
	if ( (int) get_transient( $global_key ) >= $global_max ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'rate_limited', 'message' => "Thanks — we've got a lot of interest right now. Please try again shortly." ), 429 );
	}
	foreach ( array( 'e_' . md5( $email ) => 3, 'i_' . md5( $ip ) => 5 ) as $key => $max ) {
		$k = 'valt_intake_rl_' . $key;
		$n = (int) get_transient( $k );
		if ( $n >= $max ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => 'rate_limited', 'message' => "Thanks — we've already got your details." ), 429 );
		}
		set_transient( $k, $n + 1, HOUR_IN_SECONDS );
	}
	set_transient( $global_key, (int) get_transient( $global_key ) + 1, HOUR_IN_SECONDS );

	global $wpdb;
	$row = array(
		'artist_name' => $name,
		'email'       => $email,
		'genre'       => isset( $body['genre'] ) ? sanitize_text_field( substr( (string) $body['genre'], 0, 120 ) ) : null,
		'location'    => isset( $body['location'] ) ? sanitize_text_field( substr( (string) $body['location'], 0, 160 ) ) : null,
		'links'       => isset( $body['links'] ) ? sanitize_textarea_field( substr( (string) $body['links'], 0, 500 ) ) : null,
		'message'     => isset( $body['message'] ) ? sanitize_textarea_field( substr( (string) $body['message'], 0, 1500 ) ) : null,
		'has_wallet'  => isset( $body['has_wallet'] ) ? sanitize_text_field( substr( (string) $body['has_wallet'], 0, 20 ) ) : null,
		'how_found'   => isset( $body['how_found'] ) ? sanitize_text_field( substr( (string) $body['how_found'], 0, 120 ) ) : null,
		'source'      => isset( $body['source'] ) ? sanitize_text_field( substr( (string) $body['source'], 0, 120 ) ) : null,
		'page_url'    => isset( $body['page_url'] ) ? esc_url_raw( substr( (string) $body['page_url'], 0, 2048 ) ) : null,
		'ip_address'  => $ip,
	);

	if ( false === $wpdb->insert( $wpdb->prefix . 'valt_artist_intake', $row ) ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'db_error', 'message' => 'Something went wrong — please try again.' ), 500 );
	}

	valt_intake_notify( $row );

	return new WP_REST_Response( array( 'success' => true, 'message' => $settings['success_message'] ), 200 );
}

/**
 * Email the team when a new artist applies.
 */
function valt_intake_notify( array $row ) {
	$settings = valt_intake_get_settings();
	$to       = $settings['notify_email'];
	if ( ! is_email( $to ) ) {
		return;
	}
	$subject = 'New Valt artist lead: ' . $row['artist_name'];
	$lines   = array(
		'Artist:   ' . $row['artist_name'],
		'Email:    ' . $row['email'],
		'Genre:    ' . ( $row['genre'] ?: '—' ),
		'Location: ' . ( $row['location'] ?: '—' ),
		'Links:    ' . ( $row['links'] ?: '—' ),
		'Wallet:   ' . ( $row['has_wallet'] ?: '—' ),
		'Heard via:' . ( $row['how_found'] ?: '—' ),
		'Source:   ' . ( $row['source'] ?: '—' ),
		'',
		'Message:',
		( $row['message'] ?: '—' ),
	);
	wp_mail( $to, $subject, implode( "\n", $lines ) );
}

/**
 * Admin: list leads (paginated).
 */
function valt_intake_rest_list( WP_REST_Request $request ) {
	global $wpdb;
	$table    = $wpdb->prefix . 'valt_artist_intake';
	$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
	$per      = min( 200, max( 1, (int) $request->get_param( 'per_page' ) ?: 50 ) );
	$offset   = ( $page - 1 ) * $per;
	$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per, $offset ) ); // phpcs:ignore
	return new WP_REST_Response( array( 'success' => true, 'total' => $total, 'rows' => $rows ), 200 );
}

/**
 * Admin: CSV export.
 */
function valt_intake_rest_export() {
	global $wpdb;
	$table   = $wpdb->prefix . 'valt_artist_intake';
	$columns = array( 'id', 'created_at', 'artist_name', 'email', 'genre', 'location', 'links', 'has_wallet', 'how_found', 'source', 'message', 'status' );
	$rows    = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 10000", ARRAY_A ); // phpcs:ignore

	$csv = implode( ',', $columns ) . "\n";
	foreach ( (array) $rows as $r ) {
		$line = array();
		foreach ( $columns as $c ) {
			$line[] = '"' . str_replace( '"', '""', (string) ( $r[ $c ] ?? '' ) ) . '"';
		}
		$csv .= implode( ',', $line ) . "\n";
	}
	return new WP_REST_Response( array( 'success' => true, 'filename' => 'valt-artist-intake-' . gmdate( 'Y-m-d' ) . '.csv', 'csv' => $csv, 'count' => count( (array) $rows ) ), 200 );
}
