<?php
defined( 'ABSPATH' ) || exit;

/**
 * Check whether the current logged-in user holds at least one asset
 * from the given CardanoPress NFT policy ID.
 *
 * Uses server-side stored assets — the wallet owner cannot spoof this.
 */
function valt_user_holds_policy( string $policy_id ): bool {
	if ( ! function_exists( 'cardanoPress' ) ) {
		return false;
	}

	$profile = cardanoPress()->userProfile();

	if ( ! $profile->isConnected() ) {
		return false;
	}

	$assets = $profile->storedAssets();

	if ( empty( $assets ) || ! is_array( $assets ) ) {
		return false;
	}

	foreach ( $assets as $asset ) {
		if ( ! empty( $asset['policy_id'] ) && $asset['policy_id'] === $policy_id ) {
			return true;
		}
	}

	return false;
}

/**
 * Return the Artist CPT post whose post_author matches the currently logged-in user.
 * Returns null if not logged in or no linked artist found.
 */
function valt_get_current_artist(): ?WP_Post {
	if ( ! is_user_logged_in() ) {
		return null;
	}

	$posts = get_posts( [
		'post_type'      => 'artist',
		'author'         => get_current_user_id(),
		'posts_per_page' => 1,
		'post_status'    => [ 'publish', 'draft' ],
	] );

	return $posts[0] ?? null;
}
