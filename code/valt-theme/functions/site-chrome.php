<?php
/**
 * Site chrome: navigation and footer rendered by PHP.
 * Replaces Elementor header/footer templates.
 */

/**
 * True when the platform runs against a Cardano test network (preprod/testnet)
 * rather than mainnet. Drives the "Testnet" UI labelling and auto-hides it once
 * the project switches to mainnet at public launch.
 */
function valt_is_testnet(): bool {
	if ( function_exists( 'valt_nmkr_config' ) ) {
		$cfg = valt_nmkr_config();
		if ( ! empty( $cfg['mode'] ) ) {
			return $cfg['mode'] !== 'mainnet';
		}
	}
	return get_option( 'valt_nmkr_mode', 'preprod' ) !== 'mainnet';
}

/**
 * Open Graph / Twitter Card meta for SEO + social sharing.
 * og:image is the page's featured image, falling back to the Valt logo — so the
 * homepage (whose featured image is the logo) shares as the branded Valt mark.
 */
function valt_render_meta_tags(): void {
	$site_name   = get_bloginfo( 'name' );
	$default_img = home_url( '/wp-content/uploads/2024/10/Valt-logo-1024x1024.png' );

	if ( is_front_page() || is_home() ) {
		$title = $site_name;
		$desc  = 'Own the music you love — discover and collect songs from independent artists on the Cardano blockchain.';
		$url   = home_url( '/' );
		$image = ( get_queried_object_id() && has_post_thumbnail( get_queried_object_id() ) )
			? get_the_post_thumbnail_url( get_queried_object_id(), 'large' ) : $default_img;
		$type  = 'website';
	} elseif ( is_singular() ) {
		$id    = get_queried_object_id();
		$title = get_the_title( $id );
		$desc  = wp_strip_all_tags( get_the_excerpt( $id ) ) ?: get_bloginfo( 'description' );
		$url   = get_permalink( $id );
		$image = has_post_thumbnail( $id ) ? get_the_post_thumbnail_url( $id, 'large' ) : $default_img;
		$type  = 'article';
	} else {
		$title = wp_get_document_title();
		$desc  = get_bloginfo( 'description' );
		$url   = home_url( '/' );
		$image = $default_img;
		$type  = 'website';
	}
	if ( ! $desc ) {
		$desc = 'Discover and collect music from independent artists on Cardano.';
	}

	$og = [
		'og:site_name'   => $site_name,
		'og:type'        => $type,
		'og:title'       => $title,
		'og:description' => $desc,
		'og:url'         => $url,
		'og:image'       => $image,
	];
	foreach ( $og as $prop => $content ) {
		echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $content ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta name="twitter:image" content="' . esc_attr( $image ) . '">' . "\n";
}
add_action( 'wp_head', 'valt_render_meta_tags', 5 );

/**
 * Render the site navigation bar.
 */
function valt_render_nav(): void {
	$home = home_url( '/' );

	// Check CardanoPress wallet connection.
	$connected = function_exists( 'cardanoPress' ) && cardanoPress()->userProfile()->isConnected();
	?>
	<nav class="valt-nav">
		<div class="valt-container valt-nav__inner">
			<a href="<?php echo esc_url( $home ); ?>" class="valt-nav__logo">
				<?php echo valt_svg_logo( 36 ); ?>
				<span>VALT</span>
				<?php if ( valt_is_testnet() ) : ?>
					<span class="valt-nav__testnet" title="Running on the Cardano pre-production testnet — collecting is for testing and uses test ADA, not real purchases.">Testnet</span>
				<?php endif; ?>
			</a>

			<div class="valt-nav__links" data-nav-menu>
				<a href="<?php echo esc_url( $home ); ?>">Home</a>
				<?php if ( function_exists( 'valt_feature_enabled' ) && valt_feature_enabled( 'discovery' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/discover/' ) ); ?>"><?php echo valt_svg_search( 16 ); ?> Discover</a>
				<?php endif; ?>
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( home_url( '/collection/' ) ); ?>"><?php echo valt_svg_collection( 16 ); ?> Collection</a>
				<?php endif; ?>
				<?php if ( function_exists( 'valt_feature_enabled' ) && valt_feature_enabled( 'leaderboard' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/leaderboard/' ) ); ?>"><?php echo valt_svg_trophy( 16 ); ?> Leaderboard</a>
				<?php endif; ?>
			</div>

			<div class="valt-nav__right">
				<?php if ( $connected ) : ?>
					<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="valt-nav__wallet-btn">
						<?php echo valt_svg_wallet( 18 ); ?>
						<span>Wallet</span>
						<span class="valt-nav__wallet-dot"></span>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="valt-nav__connect-btn">
						<?php echo valt_svg_wallet( 18 ); ?>
						<span>Connect</span>
					</a>
				<?php endif; ?>
				<button class="valt-nav__toggle" aria-label="Menu" data-nav-toggle>
					<span></span><span></span><span></span>
				</button>
			</div>
		</div>
	</nav>
	<?php
}

/**
 * Render the site footer.
 */
function valt_render_footer(): void {
	?>
	<?php // Sticky music player (FML Music Player plugin) — DISABLED for now (2026-06-25); flip the flag below to re-enable. ?>
	<?php if ( false && shortcode_exists( 'fml_music_player' ) ) : ?>
		<div class="valt-player-wrap">
			<?php echo do_shortcode( '[fml_music_player]' ); ?>
		</div>
	<?php endif; ?>

	<footer class="valt-footer">
		<div class="valt-container valt-footer__inner">
			<div class="valt-footer__brand">
				<strong>VALT</strong> &mdash; Artist portals on <span class="valt-cardano-mark"><img class="valt-cardano-wordmark" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/img/cardano-wordmark.png' ); ?>" alt="Cardano" width="88" height="20"></span>
			</div>
			<div class="valt-footer__links">
				<a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">FAQ</a>
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
				<a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms</a>
				<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy</a>
			</div>
			<div class="valt-footer__copy">
				&copy; 2026 Awen LLC. All rights reserved.
				<?php if ( valt_is_testnet() ) : ?>
					<span class="valt-footer__testnet">Running on the Cardano testnet (preprod) — collecting uses test ADA, not real purchases.</span>
				<?php endif; ?>
			</div>
		</div>
	</footer>
	<?php
}
