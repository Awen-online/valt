<?php
/**
 * Valt Feedback Survey — settings, session, aggregation helpers.
 *
 * Self-contained (no dependency on the FML analytics stack): the survey
 * generates its own session cookie and computes NPS / use-case aggregates
 * directly from {prefix}valt_survey_responses.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Survey settings, with defaults.
 */
function valt_survey_get_settings() {
	return wp_parse_args( get_option( 'valt_survey_settings', array() ), array(
		'survey_enabled'     => true,
		'visit_count'        => 3,     // show on the Nth visit
		'time_on_site'       => 300,   // …or after this many seconds
		'post_mint'          => true,  // …or right after a successful mint
	) );
}

/**
 * Current visitor session id. Reuses a lightweight first-party cookie; Valt has
 * no cart session, so we mint our own UUID the first time we need one.
 */
function valt_survey_get_session_id() {
	if ( ! empty( $_COOKIE['valt_session'] ) ) {
		return sanitize_text_field( wp_unslash( $_COOKIE['valt_session'] ) );
	}

	$session_id = wp_generate_uuid4();
	setcookie( 'valt_session', $session_id, time() + ( 7 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE['valt_session'] = $session_id;

	return $session_id;
}

/**
 * Client IP for rate-limiting.
 *
 * Uses REMOTE_ADDR — the only value the attacker cannot forge — by default.
 * Forwarded headers are honoured ONLY when the site declares it sits behind a
 * trusted reverse proxy/CDN via the VALT_TRUSTED_PROXY constant; otherwise they
 * are attacker-controlled and trusting them would defeat the rate limit. When
 * trusted, the right-most X-Forwarded-For hop is used, not the left-most
 * attacker-supplied entry. See M3 security assessment FIND-01.
 */
function valt_survey_client_ip() {
	if ( defined( 'VALT_TRUSTED_PROXY' ) && VALT_TRUSTED_PROXY ) {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = trim( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = array_map( 'trim', explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = end( $parts );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
}

/**
 * NPS statistics: average, promoter/passive/detractor split, and the -100..100
 * NPS index, plus the raw score distribution.
 */
function valt_survey_nps_stats() {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_survey_responses';

	$avg   = $wpdb->get_var( "SELECT AVG(nps_score) FROM {$table} WHERE nps_score IS NOT NULL" ); // phpcs:ignore WordPress.DB
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE nps_score IS NOT NULL" ); // phpcs:ignore WordPress.DB

	$distribution = $wpdb->get_results( // phpcs:ignore WordPress.DB
		"SELECT nps_score, COUNT(*) as count FROM {$table}
		 WHERE nps_score IS NOT NULL GROUP BY nps_score ORDER BY nps_score ASC"
	);

	$promoters = 0;
	$passives  = 0;
	$detractors = 0;
	foreach ( $distribution as $row ) {
		if ( $row->nps_score >= 9 ) {
			$promoters += $row->count;
		} elseif ( $row->nps_score >= 7 ) {
			$passives += $row->count;
		} else {
			$detractors += $row->count;
		}
	}

	return array(
		'average'      => null !== $avg ? round( (float) $avg, 1 ) : null,
		'total'        => $total,
		'nps'          => $total > 0 ? (int) round( ( ( $promoters - $detractors ) / $total ) * 100 ) : null,
		'promoters'    => $promoters,
		'passives'     => $passives,
		'detractors'   => $detractors,
		'distribution' => $distribution,
	);
}

/**
 * Count of each selected use-case (the multi-select is stored CSV).
 */
function valt_survey_use_case_breakdown() {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_survey_responses';

	$rows   = $wpdb->get_col( "SELECT use_case FROM {$table} WHERE use_case IS NOT NULL AND use_case != ''" ); // phpcs:ignore WordPress.DB
	$counts = array();
	foreach ( $rows as $row ) {
		foreach ( array_map( 'trim', explode( ',', $row ) ) as $case ) {
			if ( '' !== $case ) {
				$counts[ $case ] = isset( $counts[ $case ] ) ? $counts[ $case ] + 1 : 1;
			}
		}
	}
	arsort( $counts );
	return $counts;
}

/**
 * Count of each value for a single-choice column (whitelisted).
 *
 * @param string $column One of the allowed single-select columns.
 * @return array<string,int> value => count, descending.
 */
function valt_survey_single_breakdown( $column ) {
	$allowed = array( 'collect_value', 'invest_effect', 'how_found_us' );
	if ( ! in_array( $column, $allowed, true ) ) {
		return array();
	}
	global $wpdb;
	$table = $wpdb->prefix . 'valt_survey_responses';
	$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB
		"SELECT {$column} AS v, COUNT(*) AS c FROM {$table}
		 WHERE {$column} IS NOT NULL AND {$column} != '' GROUP BY {$column} ORDER BY c DESC"
	);
	$out = array();
	foreach ( (array) $rows as $row ) {
		$out[ $row->v ] = (int) $row->c;
	}
	return $out;
}

/**
 * Average fractional-ownership sentiment (1..5) and response count.
 *
 * @return array{average:?float,total:int}
 */
function valt_survey_fractional_stats() {
	global $wpdb;
	$table = $wpdb->prefix . 'valt_survey_responses';
	$avg   = $wpdb->get_var( "SELECT AVG(fractional_sentiment) FROM {$table} WHERE fractional_sentiment IS NOT NULL" ); // phpcs:ignore WordPress.DB
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE fractional_sentiment IS NOT NULL" ); // phpcs:ignore WordPress.DB
	return array(
		'average' => null !== $avg ? round( (float) $avg, 1 ) : null,
		'total'   => $total,
	);
}

/**
 * Paginated raw responses.
 */
function valt_survey_get_responses( $page = 1, $per_page = 50 ) {
	global $wpdb;
	$table    = $wpdb->prefix . 'valt_survey_responses';
	$page     = max( 1, (int) $page );
	$per_page = min( 100, max( 1, (int) $per_page ) );
	$offset   = ( $page - 1 ) * $per_page;

	$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
	$responses = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
		"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$per_page,
		$offset
	) );

	return array(
		'responses'   => $responses,
		'total'       => $total,
		'page'        => $page,
		'per_page'    => $per_page,
		'total_pages' => (int) ceil( $total / $per_page ),
	);
}
