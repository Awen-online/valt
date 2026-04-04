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
			<button class="valt-nav__toggle" aria-label="Menu" data-nav-toggle>
				<span></span><span></span><span></span>
			</button>
			<div class="valt-nav__links" data-nav-menu>
				<a href="<?php echo esc_url( $home ); ?>">Home</a>
				<?php if ( function_exists( 'valt_feature_enabled' ) && valt_feature_enabled( 'discovery' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/discover/' ) ); ?>">Discover</a>
				<?php endif; ?>
				<?php if ( function_exists( 'valt_feature_enabled' ) && valt_feature_enabled( 'leaderboard' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/leaderboard/' ) ); ?>">Leaderboard</a>
				<?php endif; ?>
				<?php if ( function_exists( 'valt_feature_enabled' ) && valt_feature_enabled( 'campaigns' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/campaigns/' ) ); ?>">Campaigns</a>
				<?php endif; ?>
				<?php if ( is_user_logged_in() ) : ?>
					<?php if ( function_exists( 'valt_feature_enabled' ) && valt_feature_enabled( 'gamification' ) ) : ?>
						<a href="<?php echo esc_url( home_url( '/fan-dashboard/' ) ); ?>">My Dashboard</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( home_url( '/collection/' ) ); ?>">My Collection</a>
				<?php endif; ?>
				<?php if ( $connected ) : ?>
					<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="valt-nav__wallet">Wallet</a>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="valt-btn valt-btn--small">Connect Wallet</a>
				<?php endif; ?>
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
