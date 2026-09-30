<?php
/**
 * Plugin Name: Valt — slug rescue redirects
 * Description: 301s hand-typed URL guesses onto the real pages.
 * Version:     1.0.0
 *
 * Why this exists
 * ---------------
 * On 2026-08-08 a visitor on an iPhone typed /forartist, then /forartists four
 * seconds later, hit two 404s, gave up, and browsed the homepage and FAQ
 * instead. They never reached /for-artists/ — the artist application page. One
 * lost signup, and the only reason we know is that the analytics beacon caught
 * the whole session.
 *
 * The referrer was empty on both hits, so nothing on the web links to those
 * paths; the URL was typed into the address bar. That is the failure mode this
 * file addresses. Launch copy prints "valt.digital/for-artists" as plain text
 * in places where it is not tappable (Instagram captions, graphics, anything
 * read aloud), and a hyphen is the first thing a person drops when retyping.
 *
 * Redirects are the right fix precisely BECAUSE there is no bad link to repair:
 * we cannot correct every place a URL gets retyped, so accept the near misses.
 *
 * Keep this list conservative. Every entry must be an unambiguous guess at one
 * real page — never a shortcut that could plausibly mean two different things.
 *
 * @package Valt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'template_redirect',
	function () {
		// Priority 1 so this runs ahead of anything that renders a page for an
		// unmatched path (awen-client's link-in-bio attaches at 5).
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
		$key  = strtolower( trim( (string) $path, '/' ) );
		if ( '' === $key ) {
			return;
		}

		/**
		 * Guess => real slug. Left side is matched after lowercasing and
		 * stripping slashes, so list each spelling once.
		 */
		$map = array(
			// The artist application — the one that actually cost us a signup.
			'forartist'     => 'for-artists',
			'forartists'    => 'for-artists',
			'for-artist'    => 'for-artists',
			'artists'       => 'for-artists',
			'artist'        => 'for-artists',
			'apply'         => 'for-artists',

			// Feedback.
			'feed-back'     => 'feedback',
			'giv-feedback'  => 'feedback',
			'givefeedback'  => 'feedback',

			// Odds and ends that read as one obvious destination.
			'faqs'          => 'faq',
			'discovery'     => 'discover',
			'contact-us'    => 'contact',

			// Terms moved from /terms-2/ to /terms/ on 2026-09-30 (the 2024 page was archived).
			'terms-2'       => 'terms',
			'terms-and-conditions' => 'terms',
			'privacy'       => 'privacy-policy',
		);

		if ( empty( $map[ $key ] ) ) {
			return;
		}

		$target = get_permalink( get_page_by_path( $map[ $key ] ) );
		if ( ! $target ) {
			return; // Destination moved or was renamed: better a 404 than a loop.
		}

		wp_safe_redirect( $target, 301 );
		exit;
	},
	1
);
