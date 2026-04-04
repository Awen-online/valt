<?php
/**
 * Template Name: Valt Full Page
 *
 * Shortcode-driven page template. No Elementor dependency.
 * Renders: nav → optional hero → post_content (shortcodes) → footer.
 */
get_header();

$show_hero   = get_post_meta( get_the_ID(), '_valt_hero', true );
$hero_video  = get_post_meta( get_the_ID(), '_valt_hero_video', true );
$hero_image  = get_post_meta( get_the_ID(), '_valt_hero_image', true );
$hero_title  = get_post_meta( get_the_ID(), '_valt_hero_title', true );
$hero_sub    = get_post_meta( get_the_ID(), '_valt_hero_subtitle', true );
$hero_cta    = get_post_meta( get_the_ID(), '_valt_hero_cta', true );
$hero_cta_url = get_post_meta( get_the_ID(), '_valt_hero_cta_url', true );
?>

<div class="valt-site">

	<?php valt_render_nav(); ?>

	<?php if ( $show_hero ) : ?>
	<section class="valt-hero">
		<?php if ( $hero_video ) : ?>
			<video class="valt-hero__bg" autoplay muted loop playsinline>
				<source src="<?php echo esc_url( $hero_video ); ?>" type="video/mp4">
			</video>
		<?php elseif ( $hero_image ) : ?>
			<div class="valt-hero__bg" style="background-image:url('<?php echo esc_url( $hero_image ); ?>');"></div>
		<?php endif; ?>
		<div class="valt-hero__overlay"></div>
		<div class="valt-hero__content">
			<?php if ( $hero_title ) : ?>
				<h1 class="valt-hero__title"><?php echo esc_html( $hero_title ); ?></h1>
			<?php endif; ?>
			<?php if ( $hero_sub ) : ?>
				<p class="valt-hero__subtitle"><?php echo esc_html( $hero_sub ); ?></p>
			<?php endif; ?>
			<?php if ( $hero_cta ) : ?>
				<a href="<?php echo esc_url( $hero_cta_url ?: '#content' ); ?>" class="valt-btn valt-btn--primary valt-hero__cta"><?php echo esc_html( $hero_cta ); ?></a>
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

<?php get_footer(); ?>
