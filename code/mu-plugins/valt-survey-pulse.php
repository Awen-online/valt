<?php
/**
 * Plugin Name: Valt Survey Pulse (Awen OS bridge)
 * Description: Read-only endpoint exposing the Valt feedback-survey aggregate + raw rows to the Awen OS hub. Authed by the shared awen_client_api_key (the same secret the hub already holds for this site). No PII beyond what a response contains.
 * Version: 1.0.0
 *
 * Mirror of Sync.Land's survey-pulse bridge, namespaced for Valt. The hub PULLS
 * this (GET); Valt never pushes. Register this endpoint for the Valt node in the
 * awen-online hub so Pulse ingests it alongside Sync.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'valt/v1', '/survey-pulse', array(
		'methods'             => 'GET',
		'permission_callback' => function ( $request ) {
			$key    = $request->get_header( 'X-Awen-Hub-Key' );
			$stored = (string) get_option( 'awen_client_api_key', '' );
			return $key && $stored && hash_equals( $stored, (string) $key );
		},
		'callback'            => function () {
			global $wpdb;
			$table = $wpdb->prefix . 'valt_survey_responses';
			$out   = array( 'node_id' => get_option( 'awen_client_site_slug', 'valt' ), 'generated_at' => gmdate( 'c' ) );

			// Reuse the theme's aggregation helpers when the theme is loaded on REST requests.
			if ( function_exists( 'valt_survey_nps_stats' ) ) {
				$out['nps'] = valt_survey_nps_stats();
			}
			if ( function_exists( 'valt_survey_use_case_breakdown' ) ) {
				$out['use_cases'] = valt_survey_use_case_breakdown();
			}

			// Guard: the survey table may not exist yet on a fresh install.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB
			if ( $exists !== $table ) {
				$out['response_count'] = 0;
				$out['responses']      = array();
				return rest_ensure_response( $out );
			}

			$out['response_count']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
			$out['last_response_at'] = $wpdb->get_var( "SELECT MAX(created_at) FROM {$table}" ); // phpcs:ignore WordPress.DB

			// Raw per-response rows so the hub can ingest them into its survey store.
			$out['responses'] = $wpdb->get_results( // phpcs:ignore WordPress.DB
				"SELECT id, nps_score, use_case, collect_ease, feature_request, how_found_us, trigger_type, created_at
				 FROM {$table} ORDER BY id ASC LIMIT 2000",
				ARRAY_A
			);

			return rest_ensure_response( $out );
		},
	) );
} );
