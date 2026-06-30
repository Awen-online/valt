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
 * Resolve the artist NAME a held NFT belongs to.
 *
 * All Valt songs are minted under one shared policy, so policy alone can't tell
 * which artist an NFT belongs to. We resolve via the on-chain CIP-25 metadata
 * 'artist' field, falling back to mapping the song title (metadata 'name') through
 * the local NFT registry. Returns '' when the artist can't be determined.
 *
 * @param array $asset A CardanoPress stored asset.
 * @return string Artist name, or '' if unknown.
 */
function valt_asset_artist_name( array $asset ): string {
	global $wpdb;
	$meta  = $asset['onchain_metadata'] ?? [];
	$table = "{$wpdb->prefix}valt_nft_registry";

	// 1) CIP-25 artist field set at mint time (only some NFTs carry it).
	if ( ! empty( $meta['artist'] ) && is_string( $meta['artist'] ) ) {
		return trim( $meta['artist'] );
	}

	// 2) Song title (metadata 'name') -> registry -> artist_name.
	if ( ! empty( $meta['name'] ) && is_string( $meta['name'] ) ) {
		$artist = $wpdb->get_var( $wpdb->prepare(
			"SELECT artist_name FROM {$table} WHERE display_name = %s AND artist_name <> '' LIMIT 1",
			$meta['name']
		) );
		if ( $artist ) {
			return trim( (string) $artist );
		}
	}

	// 3) Fallback for NFTs minted WITHOUT on-chain metadata: resolve from the deterministic
	//    on-chain token name. Normalize (drop the "valt" prefix(es), digits, punctuation) and
	//    match it against each registry song title, longest first to avoid short false hits.
	$hex     = $asset['asset_name'] ?? '';
	$decoded = $hex ? ( @hex2bin( $hex ) ?: '' ) : '';
	if ( $decoded ) {
		$norm = strtolower( preg_replace( '/[^a-z0-9]/i', '', $decoded ) ); // e.g. "valtvaltdeadend261"
		$rows = $wpdb->get_results( "SELECT display_name, artist_name FROM {$table} WHERE artist_name <> ''", ARRAY_A );
		if ( $rows ) {
			usort( $rows, function ( $a, $b ) {
				return strlen( $b['display_name'] ) <=> strlen( $a['display_name'] );
			} );
			foreach ( $rows as $r ) {
				$song_norm = strtolower( preg_replace( '/[^a-z0-9]/i', '', $r['display_name'] ) );
				if ( strlen( $song_norm ) >= 4 && strpos( $norm, $song_norm ) !== false ) {
					return trim( $r['artist_name'] );
				}
			}
		}
	}

	return '';
}

/**
 * Filter a list of stored assets to those belonging to a given artist (by policy + artist name).
 * Assets whose artist can't be resolved are kept (conservative — never hide an unknown holding);
 * assets resolved to a DIFFERENT artist are excluded.
 *
 * @param array  $assets       CardanoPress stored assets.
 * @param string $policy_id    The artist's policy id.
 * @param string $artist_name  The artist's display name.
 * @return array Filtered assets.
 */
function valt_filter_assets_for_artist( array $assets, string $policy_id, string $artist_name ): array {
	$out = [];
	foreach ( $assets as $asset ) {
		if ( ( $asset['policy_id'] ?? '' ) !== $policy_id ) {
			continue;
		}
		$resolved = valt_asset_artist_name( $asset );
		if ( $resolved !== '' && strcasecmp( $resolved, $artist_name ) !== 0 ) {
			continue; // Belongs to a different artist.
		}
		$out[] = $asset;
	}
	return $out;
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

/**
 * Testnet-only wallet enforcement.
 *
 * Valt runs on the Cardano pre-production testnet, so a MAINNET wallet can't
 * hold the testnet song NFTs that drive gating — connecting one is invalid.
 * On every load, disconnect any connected mainnet wallet. Only 'mainnet' is
 * rejected; testnet/preprod (and any other/unknown value) is left untouched,
 * so legitimate testnet users are never disconnected. Self-disables if the
 * platform is ever switched to mainnet.
 */
function valt_enforce_testnet_wallet(): void {
	if ( ! function_exists( 'cardanoPress' ) || ! is_user_logged_in() ) {
		return;
	}
	$cfg = function_exists( 'valt_nmkr_config' ) ? valt_nmkr_config() : [];
	if ( ! empty( $cfg['mode'] ) && 'mainnet' === $cfg['mode'] ) {
		return; // Platform is on mainnet — no restriction.
	}
	$profile = cardanoPress()->userProfile();
	if ( ! $profile || ! method_exists( $profile, 'connectedNetwork' ) || ! $profile->isConnected() ) {
		return;
	}
	if ( 'mainnet' === $profile->connectedNetwork() ) {
		$profile->saveWallet( '' );
		$profile->saveStake( '' );
		set_transient( 'valt_wallet_wrong_network_' . get_current_user_id(), 1, 120 );
	}
}
add_action( 'init', 'valt_enforce_testnet_wallet', 20 );
