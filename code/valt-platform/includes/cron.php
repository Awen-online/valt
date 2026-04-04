<?php
defined( 'ABSPATH' ) || exit;

/**
 * WP Cron schedules and hook registrations for Valt Platform v2.
 */

// Register custom cron interval.
add_filter( 'cron_schedules', function ( array $schedules ): array {
	$schedules['valt_every_5_minutes'] = [
		'interval' => 300,
		'display'  => 'Every 5 Minutes (Valt)',
	];
	return $schedules;
} );

// Schedule recurring NFT status poll if not already scheduled.
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'valt_poll_processing_nfts' ) ) {
		wp_schedule_event( time(), 'valt_every_5_minutes', 'valt_poll_processing_nfts' );
	}
} );

// Hook one-time async events to their handler functions (defined in nmkr.php and stripe.php).
add_action( 'valt_mint_nft_async', 'valt_do_mint_nft' );
add_action( 'valt_check_nft_status', 'valt_do_check_nft_status' );
add_action( 'valt_poll_processing_nfts', 'valt_do_poll_processing_nfts' );
add_action( 'valt_process_stripe_checkout_async', 'valt_do_process_stripe_checkout' );
