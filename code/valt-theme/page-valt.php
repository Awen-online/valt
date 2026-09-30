<?php
/**
 * Template Name: Valt Full Page
 *
 * Self-contained page template. Renders its own <html> shell.
 * Does NOT call get_header()/get_footer() to avoid Elementor templates.
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
$show_hero   = get_post_meta( get_the_ID(), '_valt_hero', true );
$hero_video  = get_post_meta( get_the_ID(), '_valt_hero_video', true );
$hero_image  = get_post_meta( get_the_ID(), '_valt_hero_image', true );
$hero_title  = get_post_meta( get_the_ID(), '_valt_hero_title', true );
$hero_sub    = get_post_meta( get_the_ID(), '_valt_hero_subtitle', true );
$hero_cta    = get_post_meta( get_the_ID(), '_valt_hero_cta', true );
$hero_cta_url = get_post_meta( get_the_ID(), '_valt_hero_cta_url', true );
$hero_cta2    = get_post_meta( get_the_ID(), '_valt_hero_cta2', true );
$hero_cta2_url = get_post_meta( get_the_ID(), '_valt_hero_cta2_url', true );
// Optional: small caps line above the title, and one-line subtexts that turn the CTAs into big cards.
$hero_kicker   = get_post_meta( get_the_ID(), '_valt_hero_kicker', true );
$hero_cta_sub  = get_post_meta( get_the_ID(), '_valt_hero_cta_sub', true );
$hero_cta2_sub = get_post_meta( get_the_ID(), '_valt_hero_cta2_sub', true );

// Hero background style — meta-driven, with a whitelisted ?hero= override for previewing.
$hero_style       = get_post_meta( get_the_ID(), '_valt_hero_style', true );
$valt_hero_styles = array( 'grooves', 'spotlight', 'aurora' );
if ( isset( $_GET['hero'] ) && in_array( wp_unslash( $_GET['hero'] ), $valt_hero_styles, true ) ) {
	$hero_style = sanitize_key( wp_unslash( $_GET['hero'] ) );
}
if ( ! in_array( $hero_style, $valt_hero_styles, true ) ) {
	$hero_style = 'grooves';
}
?>

<div class="valt-site">

	<?php valt_render_nav(); ?>

	<?php if ( $show_hero ) : ?>
	<section class="valt-hero valt-hero--<?php echo esc_attr( $hero_style ); ?><?php echo $hero_video ? ' valt-hero--video' : ''; ?>">
		<?php if ( $hero_video ) : ?>
			<video class="valt-hero__bg" autoplay muted loop playsinline aria-hidden="true">
				<source src="<?php echo esc_url( $hero_video ); ?>" type="video/mp4">
			</video>
			<script>/* Reduced motion: hold the first frame instead of looping. */(function(v){if(v&&window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches){v.removeAttribute('autoplay');v.pause();}})(document.currentScript&&document.currentScript.previousElementSibling);</script>
		<?php elseif ( $hero_image ) : ?>
			<div class="valt-hero__bg" style="background-image:url('<?php echo esc_url( $hero_image ); ?>');"></div>
		<?php endif; ?>
		<div class="valt-hero__overlay"></div>
		<div class="valt-hero__content">
			<div class="valt-hero__logo"><?php echo valt_svg_logo_animated( 120 ); ?></div>
			<?php if ( $hero_kicker ) : ?>
				<span class="valt-kicker"><?php echo esc_html( $hero_kicker ); ?></span>
			<?php endif; ?>
			<?php if ( $hero_title ) : ?>
				<h1 class="valt-hero__title<?php echo $hero_kicker ? ' valt-grad-text' : ''; ?>"><?php echo esc_html( $hero_title ); ?></h1>
			<?php endif; ?>
			<?php if ( $hero_sub ) : ?>
				<p class="valt-hero__subtitle"><?php echo esc_html( $hero_sub ); ?></p>
			<?php endif; ?>
			<?php if ( $hero_cta && $hero_cta_sub ) : ?>
				<div class="valt-cta-cards">
					<a href="<?php echo esc_url( $hero_cta_url ?: '#content' ); ?>" class="valt-cta-card valt-cta-card--primary">
						<svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/></svg>
						<span class="valt-cta-card__title"><?php echo esc_html( $hero_cta ); ?></span>
						<span class="valt-cta-card__sub"><?php echo esc_html( $hero_cta_sub ); ?></span>
					</a>
					<?php if ( $hero_cta2 ) : ?>
					<a href="<?php echo esc_url( $hero_cta2_url ?: '#content' ); ?>" class="valt-cta-card valt-cta-card--ghost">
						<svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
						<span class="valt-cta-card__title"><?php echo esc_html( $hero_cta2 ); ?></span>
						<?php if ( $hero_cta2_sub ) : ?><span class="valt-cta-card__sub"><?php echo esc_html( $hero_cta2_sub ); ?></span><?php endif; ?>
					</a>
					<?php endif; ?>
				</div>
			<?php elseif ( $hero_cta ) : ?>
				<a href="<?php echo esc_url( $hero_cta_url ?: '#content' ); ?>" class="valt-btn valt-btn--primary valt-hero__cta"><?php echo esc_html( $hero_cta ); ?></a>
					<?php if ( $hero_cta2 ) : ?><a href="<?php echo esc_url( $hero_cta2_url ?: '#content' ); ?>" class="valt-btn valt-btn--secondary valt-hero__cta valt-hero__cta--2"><?php echo esc_html( $hero_cta2 ); ?></a><?php endif; ?>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

	<main id="content" class="valt-main">
		<div class="valt-container">
			<?php
			while ( have_posts() ) :
				the_post();
				the_content();
			endwhile;
			?>
		</div>
	</main>

	<?php valt_render_footer(); ?>

</div>

<?php wp_footer(); ?>
</body>
</html>
