<?php
/**
 * Single NFT collection item — Valt styled card with artist/song linking.
 */
if (empty($asset)) {
	return;
}

$meta     = $asset['onchain_metadata'] ?? [];
$policy   = $asset['policy_id'] ?? '';
$nft_name = $meta['name'] ?? '';
$artist_name = $meta['artist'] ?? '';
$album_name  = $meta['album'] ?? '';
$genre       = $meta['genre'] ?? '';
$platform    = $meta['platform'] ?? '';

// Try to match this NFT to a WordPress artist post.
$matched_artist = null;
if ( $artist_name ) {
	$found = get_posts( [
		'post_type'      => 'artist',
		'posts_per_page' => 1,
		'title'          => $artist_name,
		'post_status'    => 'publish',
	] );
	if ( $found ) {
		$matched_artist = $found[0];
	}
}

// Try to match to a song post by checking nft asset name.
$matched_song = null;
$asset_unit = $asset['asset'] ?? '';
if ( $asset_unit ) {
	// The asset name (hex after policy ID) maps to what we set as tokenname.
	$songs_with_uid = get_posts( [
		'post_type'      => 'song',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
		'meta_query'     => [ [
			'key'     => 'valt_nft_uid',
			'compare' => 'EXISTS',
		] ],
		'title'          => $nft_name,
	] );
	if ( $songs_with_uid ) {
		$matched_song = $songs_with_uid[0];
	}
}

$is_valt = $platform === 'Valt' || ( function_exists( 'valt_nmkr_config' ) && $policy === valt_nmkr_config()['policy_id'] );
?>

<div class="valt-nft-card <?php echo $is_valt ? 'valt-nft-card--valt' : ''; ?>">
	<div class="valt-nft-card__image">
		<?php cardanoPress()->template('part/asset-image', compact('asset')); ?>
	</div>
	<div class="valt-nft-card__body">
		<h3 class="valt-nft-card__name">
			<?php if ( $matched_song ) : ?>
				<a href="<?php echo get_permalink( $matched_song->ID ); ?>"><?php echo esc_html( $nft_name ); ?></a>
			<?php else : ?>
				<?php cardanoPress()->template('part/asset-name', compact('asset')); ?>
			<?php endif; ?>
		</h3>

		<?php if ( $matched_artist ) : ?>
			<a href="<?php echo get_permalink( $matched_artist->ID ); ?>" class="valt-nft-card__artist-link">
				<?php echo valt_svg_user( 14 ); ?> <?php echo esc_html( $matched_artist->post_title ); ?>
			</a>
		<?php elseif ( $artist_name ) : ?>
			<span class="valt-nft-card__artist-link"><?php echo esc_html( $artist_name ); ?></span>
		<?php endif; ?>

		<?php if ( $album_name ) : ?>
			<span class="valt-nft-card__album"><?php echo esc_html( $album_name ); ?></span>
		<?php endif; ?>

		<?php if ( $is_valt ) : ?>
			<span class="valt-badge valt-badge--gold valt-nft-card__badge">Valt</span>
		<?php endif; ?>

		<span class="valt-nft-card__qty">Qty: <?php echo esc_html($asset['quantity']); ?></span>

		<?php // Show remaining metadata fields (skip ones we display above) ?>
		<?php
		$skip_keys = ['name', 'image', 'arweaveId', 'files', 'music_metadata_version', 'artist', 'album', 'genre', 'platform', 'website', 'release'];
		$extra_meta = array_diff_key( $meta, array_flip( $skip_keys ) );
		if ( ! empty( $extra_meta ) ) : ?>
			<div class="valt-nft-card__meta">
				<?php foreach ( $extra_meta as $key => $value ) : ?>
					<div class="valt-nft-card__field">
						<span class="valt-nft-card__label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?></span>
						<span class="valt-nft-card__value">
							<?php echo is_array($value) ? esc_html(implode(', ', $value)) : esc_html($value); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
