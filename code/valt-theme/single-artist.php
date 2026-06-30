<?php
/**
 * Single Artist template — shortcode-driven, no Elementor.
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$artist_id = get_the_ID();
$name      = get_the_title();
$bio       = get_post_meta( $artist_id, 'bio', true );
$genre     = get_post_meta( $artist_id, 'genre', true );
$country   = get_post_meta( $artist_id, 'country', true );
$policy_id = get_post_meta( $artist_id, 'valt_policy_id', true );
$thumb_url = get_the_post_thumbnail_url( $artist_id, 'large' );
$social_x  = get_post_meta( $artist_id, 'valt_social_x', true );
$social_ig = get_post_meta( $artist_id, 'valt_social_instagram', true );
$social_sp = get_post_meta( $artist_id, 'valt_social_spotify', true );

// Check gate status.
$gate_state = 'no-policy'; // no-policy | disconnected | needs-sync | locked | unlocked

// Admin preview: ?valt_preview=unlocked or ?valt_preview=locked
if ( current_user_can( 'manage_options' ) && isset( $_GET['valt_preview'] ) ) {
	$gate_state = sanitize_text_field( $_GET['valt_preview'] );
} elseif ( $policy_id && function_exists( 'cardanoPress' ) ) {
	$profile = cardanoPress()->userProfile();
	if ( ! $profile->isConnected() ) {
		$gate_state = 'disconnected';
	} else {
		$assets = $profile->storedAssets();
		if ( empty( $assets ) ) {
			$gate_state = 'needs-sync';
		} else {
			// Shared policy across all artists — keep only THIS artist's NFTs so the
			// gate unlocks (and later displays) for the right artist only.
			$artist_assets = function_exists( 'valt_filter_assets_for_artist' )
				? valt_filter_assets_for_artist( $assets, $policy_id, $name )
				: $assets;
			$gate_state = ! empty( $artist_assets ) ? 'unlocked' : 'locked';
		}
	}
}
?>

<div class="valt-site">
	<?php valt_render_nav(); ?>

	<section class="valt-artist-hero" <?php if ( $thumb_url ) : ?>style="background-image:linear-gradient(rgba(0,0,0,0.6),rgba(0,0,0,0.8)),url('<?php echo esc_url( $thumb_url ); ?>');"<?php endif; ?>>
		<div class="valt-container valt-artist-hero__inner">
			<?php if ( $thumb_url ) : ?>
				<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" class="valt-artist-hero__photo">
			<?php endif; ?>
			<div class="valt-artist-hero__info">
				<h1><?php echo esc_html( $name ); ?></h1>
				<div class="valt-artist-hero__tags">
					<?php if ( $genre ) : ?><span class="valt-tag"><?php echo esc_html( $genre ); ?></span><?php endif; ?>
					<?php if ( $country ) : ?><span class="valt-tag"><?php echo esc_html( $country ); ?></span><?php endif; ?>
				</div>
				<?php if ( $bio ) : ?><p class="valt-artist-hero__bio"><?php echo wp_kses_post( $bio ); ?></p><?php endif; ?>
				<div class="valt-artist-hero__actions">
					<?php echo do_shortcode( '[valt_follow_button artist_id="' . $artist_id . '"]' ); ?>
					<div class="valt-artist-hero__social">
						<?php if ( $social_x ) : ?><a href="https://x.com/<?php echo esc_attr( $social_x ); ?>" target="_blank" rel="noopener">X</a><?php endif; ?>
						<?php if ( $social_ig ) : ?><a href="https://instagram.com/<?php echo esc_attr( $social_ig ); ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
						<?php if ( $social_sp ) : ?><a href="<?php echo esc_url( $social_sp ); ?>" target="_blank" rel="noopener">Spotify</a><?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<main class="valt-main">
		<div class="valt-container">

			<?php // ── THE VALT — token-gated section ────────────────────── ?>
			<?php if ( $policy_id ) : ?>
			<section class="valt-vault" data-state="<?php echo esc_attr( $gate_state ); ?>">
				<div class="valt-vault__header">
					<h2 class="valt-vault__title">The Valt</h2>
					<p class="valt-vault__sub">Exclusive content for NFT holders</p>
				</div>

				<div class="valt-vault__door">
					<?php // Door animation: spins when locked, opens when unlocked ?>
					<div class="valt-vault__door-inner">
						<?php echo valt_svg_logo_animated( 160 ); ?>
					</div>

					<?php if ( $gate_state === 'unlocked' ) : ?>
						<?php // ── UNLOCKED — click to reveal ── ?>
						<button class="valt-btn valt-btn--primary valt-btn--large valt-vault__open-btn" data-action="open-valt">
							<?php echo valt_svg_wallet( 18 ); ?> Open Valt
						</button>
					<?php elseif ( $gate_state === 'disconnected' ) : ?>
						<div class="valt-vault__status valt-vault__status--locked">
							<p>Connect your wallet to enter</p>
							<?php cardanoPress()->template( 'part/modal-trigger', [ 'text' => 'Connect Wallet' ] ); ?>
						</div>
					<?php elseif ( $gate_state === 'needs-sync' ) : ?>
						<div class="valt-vault__status valt-vault__status--locked">
							<p>Wallet connected — sync your NFTs</p>
							<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="valt-btn valt-btn--primary">Sync Wallet</a>
						</div>
					<?php else : ?>
						<?php // locked ?>
						<div class="valt-vault__status valt-vault__status--locked">
							<p>Collect an NFT to unlock this artist's Valt</p>
							<?php
							// Find a song to buy.
							$songs = get_posts( [ 'post_type' => 'song', 'posts_per_page' => 1, 'meta_query' => [ [ 'key' => 'artist', 'value' => $artist_id ] ] ] );
							if ( $songs ) : ?>
								<a href="<?php echo get_permalink( $songs[0]->ID ); ?>" class="valt-btn valt-btn--primary">
									<?php echo valt_svg_music( 16 ); ?> Collect a Song
								</a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $gate_state === 'unlocked' ) :
					// Only this artist's NFTs - already computed in the gate block above. Recompute as a
					// fallback for the admin ?valt_preview path, where the gate block did not run.
					if ( ! isset( $artist_assets ) ) {
						$artist_assets = ( function_exists( 'cardanoPress' ) && function_exists( 'valt_filter_assets_for_artist' ) )
							? valt_filter_assets_for_artist( cardanoPress()->userProfile()->storedAssets(), $policy_id, $name )
							: [];
					}
					$user_assets = $artist_assets;
				?>
				<div class="valt-vault__content" data-valt-content style="display:none;">
					<div class="valt-vault__inner">
						<h3>Welcome to <?php echo esc_html( $name ); ?>'s Valt</h3>
						<p>You hold the key. You own <?php echo count( $user_assets ); ?> song<?php echo count( $user_assets ) !== 1 ? 's' : ''; ?> from this artist.</p>

						<?php if ( ! empty( $user_assets ) ) : ?>
						<div class="valt-vault__collection">
							<h4><?php echo valt_svg_collection( 18 ); ?> Your <?php echo esc_html( $name ); ?> NFTs</h4>
							<div class="valt-collection-grid">
								<?php foreach ( $user_assets as $asset ) : ?>
									<?php cardanoPress()->template( 'part/collection-item', compact( 'asset' ) ); ?>
								<?php endforeach; ?>
							</div>
						</div>
						<?php endif; ?>

						<div class="valt-vault__grid">
							<div class="valt-card">
								<h4><?php echo valt_svg_music( 18 ); ?> Exclusive Tracks</h4>
								<p>Unreleased demos, acoustic versions, and studio sessions — coming soon.</p>
							</div>
							<div class="valt-card">
								<h4><?php echo valt_svg_user( 18 ); ?> Behind the Scenes</h4>
								<p>Studio photos, creative process, and direct messages from the artist.</p>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</section>
			<?php endif; ?>

			<h2>Releases</h2>
			<?php echo do_shortcode( '[valt_song_grid artist_id="' . $artist_id . '"]' ); ?>

		</div>
	</main>

	<?php valt_render_footer(); ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
