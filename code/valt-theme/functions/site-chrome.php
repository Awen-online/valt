<?php
/**
 * Site chrome: navigation and footer rendered by PHP.
 * Replaces Elementor header/footer templates.
 */

/**
 * Render the site navigation bar.
 */
function valt_render_nav(): void {
	$logo_url = get_stylesheet_directory_uri() . '/../../../uploads/2024/06/valt-dark-300x300.png';
	$home     = home_url( '/' );

	// Check CardanoPress wallet connection.
	$connected = function_exists( 'cardanoPress' ) && cardanoPress()->userProfile()->isConnected();
	?>
	<nav class="valt-nav">
		<div class="valt-container valt-nav__inner">
			<a href="<?php echo esc_url( $home ); ?>" class="valt-nav__logo">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="VALT" width="40" height="40">
				<span>VALT</span>
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
	<footer class="valt-footer">
		<div class="valt-container valt-footer__inner">
			<div class="valt-footer__brand">
				<strong>VALT</strong> &mdash; Digital Music Collectables
			</div>
			<div class="valt-footer__links">
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
				<a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms</a>
				<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy</a>
			</div>
			<div class="valt-footer__copy">
				&copy; 2026 Awen LLC. All rights reserved.
			</div>
		</div>
	</footer>
	<?php
}
