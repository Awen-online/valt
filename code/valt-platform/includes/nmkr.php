<?php
defined( 'ABSPATH' ) || exit;

/**
 * NMKR API integration for Valt Platform v2.
 *
 * Handles: IPFS upload (Pinata), CIP25 metadata, NFT minting, status polling.
 * Patterns ported from sync.land production code.
 */

// ─── IPFS Upload ─────────────────────────────────────────────────────

/**
 * Upload a WordPress attachment to IPFS via Pinata.
 *
 * @param int $attachment_id WP attachment ID.
 * @return string|WP_Error  IPFS CID on success, WP_Error on failure.
 */
function valt_upload_to_ipfs( int $attachment_id ) {
	$config = valt_nmkr_config();
	if ( empty( $config['pinata_jwt'] ) ) {
		return new WP_Error( 'valt_no_pinata', 'Pinata JWT is not configured.' );
	}

	$file_path = get_attached_file( $attachment_id );
	if ( ! $file_path || ! file_exists( $file_path ) ) {
		return new WP_Error( 'valt_file_missing', "Attachment {$attachment_id} file not found." );
	}

	$filename  = basename( $file_path );
	$mime_type = get_post_mime_type( $attachment_id ) ?: 'application/octet-stream';
	$boundary  = wp_generate_password( 24, false );

	// Build multipart body.
	$body  = "--{$boundary}\r\n";
	$body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
	$body .= "Content-Type: {$mime_type}\r\n\r\n";
	$body .= file_get_contents( $file_path ) . "\r\n";
	$body .= "--{$boundary}--\r\n";

	$response = wp_remote_post( 'https://api.pinata.cloud/pinning/pinFileToIPFS', [
		'timeout' => 120,
		'headers' => [
			'Content-Type'  => "multipart/form-data; boundary={$boundary}",
			'Authorization' => 'Bearer ' . $config['pinata_jwt'],
		],
		'body'    => $body,
	] );

	if ( is_wp_error( $response ) ) {
		valt_log_event( 'ipfs_error', "Pinata upload failed for attachment {$attachment_id}", [
			'error' => $response->get_error_message(),
		] );
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code !== 200 || empty( $data['IpfsHash'] ) ) {
		$msg = $data['error'] ?? "HTTP {$code}";
		valt_log_event( 'ipfs_error', "Pinata returned {$msg} for attachment {$attachment_id}" );
		return new WP_Error( 'valt_pinata_error', "Pinata upload failed: {$msg}" );
	}

	valt_log_event( 'ipfs_success', "Uploaded attachment {$attachment_id} to IPFS", [
		'cid' => $data['IpfsHash'],
	] );

	return $data['IpfsHash'];
}

// ─── CIP25 Metadata ──────────────────────────────────────────────────

/**
 * Build CIP-25 metadata for a song NFT.
 *
 * @param int    $song_id    Song post ID.
 * @param string $image_cid  IPFS CID for cover art.
 * @param string $audio_cid  IPFS CID for audio file (optional).
 * @return array CIP-25 compliant metadata structure.
 */
function valt_build_cip25_metadata( int $song_id, string $image_cid, string $audio_cid = '' ): array {
	$config     = valt_nmkr_config();
	$song       = get_post( $song_id );
	$artist_id  = (int) get_post_meta( $song_id, 'artist', true );
	$artist     = $artist_id ? get_post( $artist_id ) : null;
	$album_id   = (int) get_post_meta( $song_id, 'album', true );
	$album      = $album_id ? get_post( $album_id ) : null;
	$genre      = $artist_id ? get_post_meta( $artist_id, 'genre', true ) : '';
	$asset_name = valt_generate_asset_name( $song->post_title, $song_id );

	$metadata = [
		'name'        => $song->post_title,
		'image'       => 'ipfs://' . $image_cid,
		'mediaType'   => 'image/png',
		'description' => wp_strip_all_tags( $song->post_content ) ?: $song->post_title,
		'artist'      => $artist ? $artist->post_title : 'Unknown Artist',
		'platform'    => 'Valt',
		'website'     => home_url(),
	];

	if ( $album ) {
		$metadata['album'] = $album->post_title;
	}
	if ( $genre ) {
		$metadata['genre'] = $genre;
	}

	$duration = get_post_meta( $song_id, 'duration', true );
	if ( $duration ) {
		$metadata['duration'] = $duration;
	}

	$track = get_post_meta( $song_id, 'track_number', true );
	if ( $track ) {
		$metadata['track'] = (int) $track;
	}

	// Attach audio file if uploaded to IPFS.
	if ( $audio_cid ) {
		$metadata['files'] = [
			[
				'name'      => $song->post_title . '.mp3',
				'mediaType' => 'audio/mpeg',
				'src'       => 'ipfs://' . $audio_cid,
			],
		];
	}

	// NMKR prepends the project URL slug to the token name on-chain.
	// The CIP-25 metadata key must match the actual minted asset name.
	// Project slug is "valt" → on-chain name = "valt" + $asset_name.
	$onchain_name = 'valt' . $asset_name;

	return [
		'721' => [
			$config['policy_id'] => [
				$onchain_name => $metadata,
			],
		],
	];
}

// ─── Upload to NMKR (list for sale) ──────────────────────────────────

/**
 * Upload a song to the NMKR project so it appears on the payment gateway.
 * Does NOT mint — the buyer pays ADA via NMKR and NMKR mints + delivers.
 *
 * @param int $song_id Song post ID.
 * @return array|WP_Error { nft_uid, payment_url, message }
 */
function valt_upload_song_to_nmkr( int $song_id ) {
	$config = valt_nmkr_config();
	$song   = get_post( $song_id );
	if ( ! $song ) {
		return new WP_Error( 'valt_invalid_song', 'Song not found.' );
	}

	// Find cover art (same fallback chain as minting).
	$image_id = (int) get_post_meta( $song_id, 'valt_nft_image_id', true );
	if ( ! $image_id ) $image_id = (int) get_post_thumbnail_id( $song_id );
	if ( ! $image_id ) {
		$album_id = (int) get_post_meta( $song_id, 'album', true );
		if ( $album_id ) $image_id = (int) get_post_thumbnail_id( $album_id );
	}
	if ( ! $image_id ) {
		$artist_id = (int) get_post_meta( $song_id, 'artist', true );
		if ( $artist_id ) $image_id = (int) get_post_thumbnail_id( $artist_id );
	}
	if ( ! $image_id ) {
		return new WP_Error( 'valt_no_image', 'No cover art found. Upload album or artist artwork first.' );
	}

	// Build asset name and metadata.
	$asset_name = valt_generate_asset_name( $song->post_title, $song_id );
	$price_ada  = get_post_meta( $song_id, 'valt_nft_price_ada', true );
	$price_lovelace = $price_ada ? (int) ( (float) $price_ada * 1000000 ) : 5000000;

	// Build CIP-25 metadata (use empty IPFS hash — NMKR will set the image from upload).
	$cip25 = valt_build_cip25_metadata( $song_id, 'PLACEHOLDER', '' );

	// Build upload payload.
	$upload_body = [
		'tokenname'        => $asset_name,
		'displayname'      => $song->post_title,
		'metadataOverride' => wp_json_encode( $cip25 ),
		'priceInLovelace'  => $price_lovelace,
	];

	// Attach cover art as base64.
	$file_path = get_attached_file( $image_id );
	if ( $file_path && file_exists( $file_path ) ) {
		$mime = get_post_mime_type( $image_id ) ?: 'image/jpeg';
		$upload_body['previewImageNft'] = [
			'mimetype'       => $mime,
			'fileFromBase64' => base64_encode( file_get_contents( $file_path ) ),
		];
	} else {
		return new WP_Error( 'valt_file_missing', 'Cover art file not found on disk.' );
	}

	// Upload to NMKR.
	$result = valt_nmkr_request( 'POST', "UploadNft/{$config['project_uid']}", $upload_body );
	if ( is_wp_error( $result ) ) {
		valt_log_event( 'upload_error', "NMKR upload failed for song {$song_id}", [
			'error' => $result->get_error_message(),
		] );
		return $result;
	}

	$nft_uid = $result['nftUid'] ?? '';
	if ( empty( $nft_uid ) ) {
		return new WP_Error( 'valt_upload_failed', 'NMKR returned no nftUid.' );
	}

	// Store the UID and update status.
	update_post_meta( $song_id, 'valt_nft_uid', $nft_uid );
	update_post_meta( $song_id, 'valt_release_status', 2 ); // "In NFT Collection"
	if ( ! empty( $result['ipfsHashMainnft'] ) ) {
		update_post_meta( $song_id, 'valt_nft_ipfs_hash', $result['ipfsHashMainnft'] );
	}

	// Build payment URL.
	$project_clean = str_replace( '-', '', $config['project_uid'] );
	$nft_clean     = str_replace( '-', '', $nft_uid );
	$pay_base      = $config['mode'] === 'mainnet' ? 'https://pay.nmkr.io' : 'https://pay.preprod.nmkr.io';
	$payment_url   = "{$pay_base}/?p={$project_clean}&n={$nft_clean}";

	valt_log_event( 'nft_listed', "Song {$song_id} listed on NMKR for {$price_ada} ADA", [
		'nft_uid'     => $nft_uid,
		'payment_url' => $payment_url,
	] );

	return [
		'message'     => 'Song listed for sale on NMKR.',
		'nft_uid'     => $nft_uid,
		'payment_url' => $payment_url,
	];
}

// ─── Minting Flow (server-side, for free/promo mints) ────────────────

/**
 * Schedule an NFT mint for a song.
 *
 * @param int    $song_id        Song post ID.
 * @param string $wallet_address Recipient Cardano wallet address.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function valt_schedule_nft_mint( int $song_id, string $wallet_address ) {
	$song = get_post( $song_id );
	if ( ! $song || $song->post_type !== 'song' ) {
		return new WP_Error( 'valt_invalid_song', 'Invalid song ID.' );
	}

	$wallet_address = valt_sanitize_wallet_address( $wallet_address );
	if ( empty( $wallet_address ) ) {
		return new WP_Error( 'valt_invalid_wallet', 'Invalid Cardano wallet address.' );
	}

	// Check max supply.
	$max_supply = (int) get_post_meta( $song_id, 'valt_nft_max_supply', true );
	$mint_count = (int) get_post_meta( $song_id, 'valt_mint_count', true );
	if ( $max_supply > 0 && $mint_count >= $max_supply ) {
		return new WP_Error( 'valt_sold_out', 'This song has reached its maximum supply.' );
	}

	// Check not already pending/processing.
	$current_status = get_post_meta( $song_id, 'valt_nft_status', true );
	if ( in_array( $current_status, [ 'pending', 'processing' ], true ) ) {
		return new WP_Error( 'valt_already_minting', 'This song already has a mint in progress.' );
	}

	// Set status and wallet.
	update_post_meta( $song_id, 'valt_nft_status', 'pending' );
	update_post_meta( $song_id, 'valt_nft_wallet_address', $wallet_address );

	// Add to queue.
	$queue = get_option( 'valt_nft_queue', [] );
	$queue[ $song_id ] = [
		'wallet'     => $wallet_address,
		'queued_at'  => current_time( 'mysql' ),
	];
	update_option( 'valt_nft_queue', $queue, false );

	// Schedule async mint (5 second delay).
	wp_schedule_single_event( time() + 5, 'valt_mint_nft_async', [ $song_id ] );

	valt_log_event( 'mint_scheduled', "Mint scheduled for song {$song_id}", [
		'wallet' => $wallet_address,
	] );

	return true;
}

/**
 * Execute the NFT mint via NMKR API. Called by WP Cron.
 *
 * @param int $song_id Song post ID.
 */
function valt_do_mint_nft( int $song_id ): void {
	$config = valt_nmkr_config();
	$wallet = get_post_meta( $song_id, 'valt_nft_wallet_address', true );

	if ( empty( $wallet ) ) {
		update_post_meta( $song_id, 'valt_nft_status', 'failed' );
		valt_log_event( 'mint_error', "No wallet address for song {$song_id}" );
		return;
	}

	// Upload cover art — fallback chain: song NFT image → song thumb → album thumb → artist thumb.
	$image_id = (int) get_post_meta( $song_id, 'valt_nft_image_id', true );
	if ( ! $image_id ) {
		$image_id = (int) get_post_thumbnail_id( $song_id );
	}
	if ( ! $image_id ) {
		$album_id = (int) get_post_meta( $song_id, 'album', true );
		if ( $album_id ) {
			$image_id = (int) get_post_thumbnail_id( $album_id );
		}
	}
	if ( ! $image_id ) {
		$artist_id = (int) get_post_meta( $song_id, 'artist', true );
		if ( $artist_id ) {
			$image_id = (int) get_post_thumbnail_id( $artist_id );
		}
	}

	if ( ! $image_id ) {
		update_post_meta( $song_id, 'valt_nft_status', 'failed' );
		valt_log_event( 'mint_error', "No cover art found for song {$song_id}" );
		return;
	}

	$image_cid = valt_upload_to_ipfs( $image_id );
	if ( is_wp_error( $image_cid ) ) {
		update_post_meta( $song_id, 'valt_nft_status', 'failed' );
		valt_log_event( 'mint_error', "IPFS image upload failed for song {$song_id}", [
			'error' => $image_cid->get_error_message(),
		] );
		return;
	}

	update_post_meta( $song_id, 'valt_nft_ipfs_hash', $image_cid );

	// Optionally upload audio to IPFS.
	$audio_id  = (int) get_post_meta( $song_id, 'audio_file', true );
	$audio_cid = '';
	if ( $audio_id ) {
		$result = valt_upload_to_ipfs( $audio_id );
		if ( ! is_wp_error( $result ) ) {
			$audio_cid = $result;
		}
	}

	// Build CIP25 metadata.
	$cip25      = valt_build_cip25_metadata( $song_id, $image_cid, $audio_cid );
	$asset_name = valt_generate_asset_name( get_the_title( $song_id ), $song_id );
	$policy_id  = $config['policy_id'];

	// Step 1: Upload NFT to NMKR project.
	$upload_body = [
		'tokenname'        => $asset_name,
		'displayname'      => get_the_title( $song_id ),
		'metadataOverride' => wp_json_encode( $cip25 ),
		'priceInLovelace'  => 5000000, // 5 ADA default
	];

	// Attach cover art as base64 (skip Pinata, use NMKR's IPFS).
	$file_path = get_attached_file( $image_id );
	if ( $file_path && file_exists( $file_path ) ) {
		$mime = get_post_mime_type( $image_id ) ?: 'image/jpeg';
		$upload_body['previewImageNft'] = [
			'mimetype'       => $mime,
			'fileFromBase64' => base64_encode( file_get_contents( $file_path ) ),
		];
	}

	$upload_result = valt_nmkr_request( 'POST', "UploadNft/{$config['project_uid']}", $upload_body );
	if ( is_wp_error( $upload_result ) ) {
		update_post_meta( $song_id, 'valt_nft_status', 'failed' );
		valt_log_event( 'mint_error', "NMKR upload failed for song {$song_id}", [
			'error' => $upload_result->get_error_message(),
		] );
		return;
	}

	$nft_uid = $upload_result['nftUid'] ?? '';
	if ( empty( $nft_uid ) ) {
		update_post_meta( $song_id, 'valt_nft_status', 'failed' );
		valt_log_event( 'mint_error', "NMKR upload returned no nftUid for song {$song_id}" );
		return;
	}

	update_post_meta( $song_id, 'valt_nft_uid', $nft_uid );
	if ( ! empty( $upload_result['ipfsHashMainnft'] ) ) {
		update_post_meta( $song_id, 'valt_nft_ipfs_hash', $upload_result['ipfsHashMainnft'] );
	}

	valt_log_event( 'nft_uploaded', "NFT uploaded to NMKR for song {$song_id}", [
		'nft_uid' => $nft_uid,
	] );

	// Step 2: Mint and send via GET request.
	// Format: /v2/MintAndSendSpecific/{projectUid}/{nftUid}/{tokencount}/{receiverAddress}
	$mint_result = valt_nmkr_request( 'GET', "MintAndSendSpecific/{$config['project_uid']}/{$nft_uid}/1/{$wallet}" );

	if ( is_wp_error( $mint_result ) ) {
		update_post_meta( $song_id, 'valt_nft_status', 'failed' );
		valt_log_event( 'mint_error', "NMKR mint failed for song {$song_id}", [
			'error' => $mint_result->get_error_message(),
		] );
		return;
	}

	update_post_meta( $song_id, 'valt_nft_status', 'processing' );

	valt_log_event( 'mint_submitted', "NMKR mint submitted for song {$song_id}", [
		'nft_uid' => $nft_uid,
	] );

	// Schedule first status check in 2 minutes.
	wp_schedule_single_event( time() + 120, 'valt_check_nft_status', [ $song_id ] );

	// Reset poll counter.
	set_transient( "valt_nft_poll_count_{$song_id}", 0, DAY_IN_SECONDS );
}

// ─── Status Polling ──────────────────────────────────────────────────

/**
 * Check the minting status of a single song NFT. Called by WP Cron.
 *
 * @param int $song_id Song post ID.
 */
function valt_do_check_nft_status( int $song_id ): void {
	$nft_uid = get_post_meta( $song_id, 'valt_nft_uid', true );
	if ( empty( $nft_uid ) ) {
		return;
	}

	// Enforce max poll attempts.
	$poll_count = (int) get_transient( "valt_nft_poll_count_{$song_id}" );
	if ( $poll_count >= 20 ) {
		update_post_meta( $song_id, 'valt_nft_status', 'failed' );
		valt_log_event( 'mint_timeout', "Max poll attempts reached for song {$song_id}" );
		valt_remove_from_queue( $song_id );
		return;
	}
	set_transient( "valt_nft_poll_count_{$song_id}", $poll_count + 1, DAY_IN_SECONDS );

	$result = valt_nmkr_request( 'GET', "GetNftDetailsById/{$nft_uid}" );
	if ( is_wp_error( $result ) ) {
		// Reschedule and try again.
		wp_schedule_single_event( time() + 300, 'valt_check_nft_status', [ $song_id ] );
		return;
	}

	$state = strtolower( $result['state'] ?? $result['status'] ?? '' );

	if ( in_array( $state, [ 'sold', 'minted', 'finished' ], true ) ) {
		// Success!
		update_post_meta( $song_id, 'valt_nft_status', 'minted' );
		update_post_meta( $song_id, 'valt_release_status', 3 );

		$tx_id    = $result['txHash'] ?? $result['transactionId'] ?? '';
		$asset_id = $result['assetId'] ?? '';
		if ( $tx_id ) {
			update_post_meta( $song_id, 'valt_nft_transaction_id', $tx_id );
		}
		if ( $asset_id ) {
			update_post_meta( $song_id, 'valt_nft_asset_id', $asset_id );
		}

		// Increment mint count.
		$count = (int) get_post_meta( $song_id, 'valt_mint_count', true );
		update_post_meta( $song_id, 'valt_mint_count', $count + 1 );

		valt_log_event( 'mint_complete', "Song {$song_id} minted successfully", [
			'tx'       => $tx_id,
			'asset_id' => $asset_id,
		] );

		valt_remove_from_queue( $song_id );

		/**
		 * Fires when an NFT mint completes successfully.
		 *
		 * @param int $song_id The song post ID.
		 */
		do_action( 'valt_nft_minted', $song_id );

	} elseif ( in_array( $state, [ 'error', 'failed', 'invalid' ], true ) ) {
		update_post_meta( $song_id, 'valt_nft_status', 'failed' );
		valt_log_event( 'mint_failed', "NMKR reported failure for song {$song_id}", [
			'state' => $state,
		] );
		valt_remove_from_queue( $song_id );

	} else {
		// Still processing — reschedule at 5 minute interval.
		wp_schedule_single_event( time() + 300, 'valt_check_nft_status', [ $song_id ] );
	}
}

/**
 * Batch-poll all processing NFTs. Recurring cron callback (every 5 min).
 */
function valt_do_poll_processing_nfts(): void {
	$queue = get_option( 'valt_nft_queue', [] );
	if ( empty( $queue ) ) {
		return;
	}

	$count = 0;
	foreach ( array_keys( $queue ) as $song_id ) {
		$status = get_post_meta( $song_id, 'valt_nft_status', true );
		if ( $status === 'processing' ) {
			valt_do_check_nft_status( (int) $song_id );
			$count++;
			if ( $count >= 10 ) {
				break; // Safety: max 10 per batch.
			}
		}
	}
}

/**
 * Remove a song from the NFT queue.
 */
function valt_remove_from_queue( int $song_id ): void {
	$queue = get_option( 'valt_nft_queue', [] );
	unset( $queue[ $song_id ] );
	update_option( 'valt_nft_queue', $queue, false );
	delete_transient( "valt_nft_poll_count_{$song_id}" );
}
