<?php
/**
 * NFT asset image — with fallback to local song/album/artist art.
 */

if (empty($source)) {
    $source = $asset['parsed_image'] ?? '';
}

if (empty($label)) {
    $label = $asset['packed_name'] ?? '';
}

// Fallback: if no parsed image, try to find local WordPress art.
if ( ! $source ) {
	$meta = $asset['onchain_metadata'] ?? [];
	$song_name = $meta['name'] ?? '';
	$artist_name = $meta['artist'] ?? '';

	// Try matching to a song post → use its featured image.
	if ( $song_name ) {
		$matched = get_posts( [ 'post_type' => 'song', 'posts_per_page' => 1, 'title' => $song_name, 'post_status' => 'publish' ] );
		if ( $matched ) {
			$thumb_id = get_post_thumbnail_id( $matched[0]->ID );
			if ( $thumb_id ) $source = wp_get_attachment_image_url( $thumb_id, 'medium' );

			// Try album thumbnail.
			if ( ! $source ) {
				$album_id = (int) get_post_meta( $matched[0]->ID, 'album', true );
				if ( $album_id ) $source = get_the_post_thumbnail_url( $album_id, 'medium' );
			}
		}
	}

	// Try matching artist → use artist thumbnail.
	if ( ! $source && $artist_name ) {
		$found = get_posts( [ 'post_type' => 'artist', 'posts_per_page' => 1, 'title' => $artist_name, 'post_status' => 'publish' ] );
		if ( $found ) $source = get_the_post_thumbnail_url( $found[0]->ID, 'medium' );
	}

	// Try local registry.
	if ( ! $source ) {
		$hex_name = $asset['asset_name'] ?? '';
		if ( $hex_name ) {
			$decoded = @hex2bin( $hex_name );
			if ( $decoded ) {
				global $wpdb;
				$reg = $wpdb->get_row( $wpdb->prepare(
					"SELECT song_id, image_url FROM {$wpdb->prefix}valt_nft_registry WHERE asset_name LIKE %s LIMIT 1",
					'%' . $wpdb->esc_like( $decoded ) . '%'
				), ARRAY_A );
				if ( $reg ) {
					if ( ! empty( $reg['image_url'] ) ) {
						$source = $reg['image_url'];
					} elseif ( ! empty( $reg['song_id'] ) ) {
						$thumb = get_post_thumbnail_id( (int) $reg['song_id'] );
						if ( $thumb ) $source = wp_get_attachment_image_url( $thumb, 'medium' );
					}
				}
			}
		}
	}
}

if (! $source && ! $label) {
    return;
}
?>

<div class="valt-nft-card__img-wrap">
	<?php if ($source) : ?>
		<img src="<?php echo esc_attr($source); ?>" alt="<?php echo esc_attr($label); ?>" class="valt-nft-card__img" loading="lazy">
	<?php else : ?>
		<div class="valt-nft-card__img-placeholder">
			<span><?php echo esc_html( $label ? mb_strtoupper( mb_substr( $label, 0, 1 ) ) : '?' ); ?></span>
		</div>
	<?php endif; ?>
</div>
