<?php
/**
 * Valt Feedback Survey — REST routes (namespace valt/v1).
 *
 *   POST /valt/v1/survey           public submit (honeypot + rate limit; intentionally anonymous)
 *   GET  /valt/v1/survey-results   admin: NPS + use-cases + responses
 *   GET  /valt/v1/survey-export    admin: CSV
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'valt/v1', '/survey', array(
		'methods'             => 'POST',
		'callback'            => 'valt_survey_rest_submit',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'valt/v1', '/survey-results', array(
		'methods'             => 'GET',
		'callback'            => 'valt_survey_rest_results',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	) );

	register_rest_route( 'valt/v1', '/survey-export', array(
		'methods'             => 'GET',
		'callback'            => 'valt_survey_rest_export',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	) );
} );

/**
 * Handle a survey submission.
 */
function valt_survey_rest_submit( WP_REST_Request $request ) {
	$settings = valt_survey_get_settings();
	if ( empty( $settings['survey_enabled'] ) ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'survey_disabled' ), 403 );
	}

	$body = $request->get_json_params();
	if ( ! is_array( $body ) ) {
		$body = array();
	}

	// Honeypot: a real user never fills this. Pretend success.
	if ( ! empty( $body['website'] ) ) {
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	// Rate limit: one submission per session per hour, plus a site-wide hourly
	// cap independent of the (spoofable) IP/cookie so a flood is bounded even if
	// an attacker rotates the session. See M3 security assessment FIND-01.
	$session_id = ! empty( $_COOKIE['valt_session'] )
		? sanitize_text_field( wp_unslash( $_COOKIE['valt_session'] ) )
		: 'anon_' . valt_survey_client_ip();
	$rate_key = 'valt_survey_rate_' . md5( $session_id );
	if ( get_transient( $rate_key ) ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'rate_limited' ), 429 );
	}
	$global_max = (int) apply_filters( 'valt_survey_global_hourly_cap', 60 );
	$global_key = 'valt_survey_rate_global';
	if ( (int) get_transient( $global_key ) >= $global_max ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'rate_limited' ), 429 );
	}

	$nps = isset( $body['nps_score'] ) ? (int) $body['nps_score'] : null;
	if ( null !== $nps && ( $nps < 0 || $nps > 10 ) ) {
		$nps = null;
	}

	$ease = isset( $body['collect_ease'] ) ? (int) $body['collect_ease'] : null;
	if ( null !== $ease && ( $ease < 1 || $ease > 5 ) ) {
		$ease = null;
	}

	// Fractional-ownership sentiment: 1 (not for me) … 5 (love it).
	$fractional = isset( $body['fractional_sentiment'] ) ? (int) $body['fractional_sentiment'] : null;
	if ( null !== $fractional && ( $fractional < 1 || $fractional > 5 ) ) {
		$fractional = null;
	}

	$use_case = '';
	if ( isset( $body['use_case'] ) && is_array( $body['use_case'] ) ) {
		$use_case = implode( ',', array_map( 'sanitize_text_field', array_slice( $body['use_case'], 0, 10 ) ) );
	} elseif ( isset( $body['use_case'] ) ) {
		$use_case = sanitize_text_field( substr( (string) $body['use_case'], 0, 255 ) );
	}

	global $wpdb;
	$row = array(
		'session_id'      => $session_id,
		'user_id'         => is_user_logged_in() ? get_current_user_id() : null,
		'nps_score'            => $nps,
		'use_case'             => $use_case ?: null,
		'collect_ease'         => $ease,
		'collect_value'        => isset( $body['collect_value'] ) ? sanitize_text_field( substr( (string) $body['collect_value'], 0, 50 ) ) : null,
		'fractional_sentiment' => $fractional,
		'invest_effect'        => isset( $body['invest_effect'] ) ? sanitize_text_field( substr( (string) $body['invest_effect'], 0, 20 ) ) : null,
		'feature_request'      => isset( $body['feature_request'] ) ? sanitize_textarea_field( substr( (string) $body['feature_request'], 0, 5000 ) ) : null,
		'how_found_us'         => isset( $body['how_found_us'] ) ? sanitize_text_field( substr( (string) $body['how_found_us'], 0, 100 ) ) : null,
		'trigger_type'    => isset( $body['trigger_type'] ) ? sanitize_text_field( substr( (string) $body['trigger_type'], 0, 50 ) ) : null,
		'page_url'        => isset( $body['page_url'] ) ? esc_url_raw( substr( (string) $body['page_url'], 0, 2048 ) ) : null,
		'ip_address'      => valt_survey_client_ip(),
	);

	// Nothing meaningful answered? Don't store an empty row.
	if ( null === $nps && '' === (string) $use_case && null === $ease && null === $fractional
		&& empty( $row['collect_value'] ) && empty( $row['invest_effect'] )
		&& empty( $row['feature_request'] ) && empty( $row['how_found_us'] ) ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'empty' ), 400 );
	}

	if ( false === $wpdb->insert( $wpdb->prefix . 'valt_survey_responses', $row ) ) {
		return new WP_REST_Response( array( 'success' => false, 'error' => 'db_error' ), 500 );
	}

	set_transient( $rate_key, 1, HOUR_IN_SECONDS );
	set_transient( $global_key, (int) get_transient( $global_key ) + 1, HOUR_IN_SECONDS );
	if ( is_user_logged_in() ) {
		update_user_meta( get_current_user_id(), 'valt_survey_dismissed', time() );
	}

	return new WP_REST_Response( array( 'success' => true ), 200 );
}

/**
 * Admin: aggregates + paginated responses.
 */
function valt_survey_rest_results( WP_REST_Request $request ) {
	return new WP_REST_Response( array(
		'success'   => true,
		'nps'       => valt_survey_nps_stats(),
		'use_cases' => valt_survey_use_case_breakdown(),
		'responses' => valt_survey_get_responses(
			(int) $request->get_param( 'page' ) ?: 1,
			(int) $request->get_param( 'per_page' ) ?: 50
		),
	), 200 );
}

/**
 * Admin: CSV of all responses.
 */
function valt_survey_rest_export() {
	global $wpdb;
	$table   = $wpdb->prefix . 'valt_survey_responses';
	$columns = array( 'id', 'session_id', 'user_id', 'nps_score', 'use_case', 'collect_ease', 'collect_value', 'fractional_sentiment', 'invest_effect', 'feature_request', 'how_found_us', 'trigger_type', 'page_url', 'created_at' );

	$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 10000", ARRAY_A ); // phpcs:ignore WordPress.DB

	$csv = implode( ',', $columns ) . "\n";
	foreach ( (array) $rows as $row ) {
		$line = array();
		foreach ( $columns as $col ) {
			$val    = isset( $row[ $col ] ) ? (string) $row[ $col ] : '';
			$line[] = '"' . str_replace( '"', '""', $val ) . '"';
		}
		$csv .= implode( ',', $line ) . "\n";
	}

	return new WP_REST_Response( array(
		'success'  => true,
		'filename' => 'valt-survey-export-' . gmdate( 'Y-m-d' ) . '.csv',
		'csv'      => $csv,
		'count'    => count( (array) $rows ),
	), 200 );
}
