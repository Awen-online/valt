<?php
/**
 * Single Song template — shortcode-driven, no Elementor.
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
$song_id   = get_the_ID();
$title     = get_the_title();
$artist_id = (int) get_post_meta( $song_id, 'artist', true );
$album_id  = (int) get_post_meta( $song_id, 'album', true );
$artist    = $artist_id ? get_post( $artist_id ) : null;
$album     = $album_id ? get_post( $album_id ) : null;
$duration  = get_post_meta( $song_id, 'duration', true );
$track     = get_post_meta( $song_id, 'track_number', true );
$image_id  = (int) get_post_meta( $song_id, 'valt_nft_image_id', true ) ?: get_post_thumbnail_id( $song_id );
$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
if ( ! $image_url && $artist_id ) {
	$image_url = get_the_post_thumbnail_url( $artist_id, 'large' );
}
?>

<div class="valt-site">
	<?php valt_render_nav(); ?>

	<section class="valt-song-hero" <?php if ( $image_url ) : ?>style="background-image:linear-gradient(rgba(0,0,0,0.5),rgba(0,0,0,0.85)),url('<?php echo esc_url( $image_url ); ?>');"<?php endif; ?>>
		<div class="valt-container valt-song-hero__inner">
			<?php if ( $image_url ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="valt-song-hero__art">
			<?php endif; ?>
			<div class="valt-song-hero__info">
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( $artist ) : ?>
					<p class="valt-song-hero__artist"><a href="<?php echo get_permalink( $artist_id ); ?>"><?php echo esc_html( $artist->post_title ); ?></a></p>
				<?php endif; ?>
				<div class="valt-song-hero__meta">
					<?php if ( $album ) : ?><span>Album: <a href="<?php echo get_permalink( $album_id ); ?>"><?php echo esc_html( $album->post_title ); ?></a></span><?php endif; ?>
					<?php if ( $track ) : ?><span>Track <?php echo (int) $track; ?></span><?php endif; ?>
					<?php if ( $duration ) : ?><span><?php echo esc_html( $duration ); ?></span><?php endif; ?>
				</div>
				<div class="valt-song-hero__badges">
					<?php echo do_shortcode( '[valt_release_status post_id="' . $song_id . '"]' ); ?>
					<?php echo do_shortcode( '[valt_nft_status song_id="' . $song_id . '"]' ); ?>
				</div>
			</div>
		</div>
	</section>

	<main class="valt-main">
		<div class="valt-container">

			<div class="valt-song-actions">
				<h2>Collect This Song</h2>
				<?php echo do_shortcode( '[valt_connect_mint song_id="' . $song_id . '"]' ); ?>
				<?php
				$price_usd = (int) get_post_meta( $song_id, 'valt_nft_price_usd', true );
				if ( $price_usd ) :
					echo do_shortcode( '[valt_checkout_button song_id="' . $song_id . '" label="Buy with Card"]' );
				endif;
				?>
			</div>

			<?php if ( $artist_id ) : ?>
			<div class="valt-section">
				<h2>More from <?php echo esc_html( $artist->post_title ); ?></h2>
				<?php echo do_shortcode( '[valt_song_grid artist_id="' . $artist_id . '" exclude="' . $song_id . '" limit="4"]' ); ?>
			</div>
			<?php endif; ?>

		</div>
	</main>

	<?php valt_render_footer(); ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
