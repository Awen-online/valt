<?php
/**
 * Single Artist template — shortcode-driven, no Elementor.
 */
get_header();
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
				<div class="valt-artist-hero__social">
					<?php if ( $social_x ) : ?><a href="https://x.com/<?php echo esc_attr( $social_x ); ?>" target="_blank" rel="noopener">X</a><?php endif; ?>
					<?php if ( $social_ig ) : ?><a href="https://instagram.com/<?php echo esc_attr( $social_ig ); ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
					<?php if ( $social_sp ) : ?><a href="<?php echo esc_url( $social_sp ); ?>" target="_blank" rel="noopener">Spotify</a><?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<main class="valt-main">
		<div class="valt-container">

			<h2>Releases</h2>
			<?php echo do_shortcode( '[valt_song_grid artist_id="' . $artist_id . '"]' ); ?>

			<?php if ( $policy_id ) : ?>
				<h2>Fan Club</h2>
				<?php echo do_shortcode( '[valt_artist_fans artist_id="' . $artist_id . '"]' ); ?>

				<div class="valt-section">
					<?php echo do_shortcode( '[valt_gated_content artist_id="' . $artist_id . '"]' );
					echo '<p>Welcome to the inner circle. Exclusive content coming soon.</p>';
					echo do_shortcode( '[/valt_gated_content]' ); ?>
				</div>
			<?php endif; ?>

		</div>
	</main>

	<?php valt_render_footer(); ?>
</div>

<?php get_footer(); ?>
