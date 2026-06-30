<?php
defined( 'ABSPATH' ) || exit;

/**
 * New shortcodes for Valt Platform v2.
 * All follow existing pattern: ob_start() / HTML / ob_get_clean().
 */

// ─── 1. [valt_discover_artists] ─────────────────────────────────────

add_shortcode( 'valt_discover_artists', function ( $atts ) {
	$atts = shortcode_atts( [ 'per_page' => 12, 'show_filters' => 'yes' ], $atts );
	$genres    = valt_get_genres();
	$countries = valt_get_countries();

	ob_start(); ?>
	<div class="valt-discovery" data-per-page="<?php echo (int) $atts['per_page']; ?>">
		<?php if ( $atts['show_filters'] === 'yes' ) : ?>
		<div class="valt-discovery__filters">
			<input type="text" class="valt-discovery__search valt-form__input" placeholder="Search artists..." data-filter="search">
			<select class="valt-discovery__select valt-form__select" data-filter="genre">
				<option value="">All Genres</option>
				<?php foreach ( $genres as $g ) : ?><option value="<?php echo esc_attr( $g ); ?>"><?php echo esc_html( $g ); ?></option><?php endforeach; ?>
			</select>
			<select class="valt-discovery__select valt-form__select" data-filter="country">
				<option value="">All Countries</option>
				<?php foreach ( $countries as $c ) : ?><option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option><?php endforeach; ?>
			</select>
			<select class="valt-discovery__select valt-form__select" data-filter="sort">
				<option value="trending">Trending</option>
				<option value="newest">Newest</option>
				<option value="alphabetical">A–Z</option>
				<option value="fans">Most Fans</option>
			</select>
		</div>
		<?php endif; ?>
		<div class="valt-discovery__grid"></div>
		<div class="valt-discovery__load-more" style="display:none;">
			<button class="valt-btn valt-btn--secondary">Load More</button>
		</div>
	</div>
	<?php return ob_get_clean();
} );

// ─── 2. [valt_trending_artists] ─────────────────────────────────────

add_shortcode( 'valt_trending_artists', function ( $atts ) {
	$atts    = shortcode_atts( [ 'limit' => 10 ], $atts );
	$artists = valt_get_trending_artists( (int) $atts['limit'] );

	ob_start(); ?>
	<div class="valt-trending">
		<div class="valt-trending__scroll">
		<?php foreach ( $artists as $a ) : ?>
			<a href="<?php echo esc_url( $a['url'] ); ?>" class="valt-trending__card">
				<?php if ( $a['thumbnail_url'] ) : ?>
					<img src="<?php echo esc_url( $a['thumbnail_url'] ); ?>" alt="<?php echo esc_attr( $a['name'] ); ?>" class="valt-trending__img">
				<?php endif; ?>
				<div class="valt-trending__info">
					<strong><?php echo esc_html( $a['name'] ); ?></strong>
					<?php if ( $a['genre'] ) : ?><span class="valt-tag"><?php echo esc_html( $a['genre'] ); ?></span><?php endif; ?>
					<span class="valt-trending__fans"><?php echo (int) $a['fan_count']; ?> fans</span>
				</div>
			</a>
		<?php endforeach; ?>
		</div>
	</div>
	<?php return ob_get_clean();
} );

// ─── 2b. [valt_featured_artists] ────────────────────────────────────
// Artist-first hero band. Prefers artists flagged valt_featured; falls back
// to all artists. Optional genre/country filters let it become a focused
// showcase (e.g. genre="Afrobeats") once those artists are onboarded.
// Renders nothing when there are no matching artists, so the home stays clean.

add_shortcode( 'valt_featured_artists', function ( $atts ) {
	$atts = shortcode_atts( [ 'limit' => 6, 'genre' => '', 'country' => '' ], $atts );

	$base = [
		'post_type'      => 'artist',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['limit'],
		'meta_query'     => [],
	];
	if ( $atts['genre'] )   $base['meta_query'][] = [ 'key' => 'genre',   'value' => $atts['genre'],   'compare' => '=' ];
	if ( $atts['country'] ) $base['meta_query'][] = [ 'key' => 'country', 'value' => $atts['country'], 'compare' => '=' ];

	// Prefer explicitly-featured artists; fall back to any matching artist.
	$featured = $base;
	$featured['meta_query'][] = [ 'key' => 'valt_featured', 'value' => '1' ];
	$query = new WP_Query( $featured );
	if ( ! $query->have_posts() ) {
		$query = new WP_Query( $base );
	}
	if ( ! $query->have_posts() ) {
		return '';
	}

	ob_start(); ?>
	<div class="valt-featured">
		<?php foreach ( $query->posts as $post ) :
			$a = valt_format_artist_card( $post );
			$meta = trim( $a['genre'] . ( $a['genre'] && $a['country'] ? ' · ' : '' ) . $a['country'] );
		?>
			<a href="<?php echo esc_url( $a['url'] ); ?>" class="valt-featured__card">
				<div class="valt-featured__art">
					<?php if ( $a['thumbnail_url'] ) : ?>
						<img src="<?php echo esc_url( $a['thumbnail_url'] ); ?>" alt="<?php echo esc_attr( $a['name'] ); ?>" loading="lazy">
					<?php else : ?>
						<div class="valt-featured__placeholder"><?php echo valt_svg_user( 28 ); ?></div>
					<?php endif; ?>
				</div>
				<div class="valt-featured__info">
					<strong class="valt-featured__name"><?php echo esc_html( $a['name'] ); ?></strong>
					<?php if ( $meta ) : ?><span class="valt-tag"><?php echo esc_html( $meta ); ?></span><?php endif; ?>
					<?php if ( $a['bio'] ) : ?><span class="valt-featured__bio"><?php echo esc_html( $a['bio'] ); ?></span><?php endif; ?>
					<span class="valt-featured__cta">View artist &rarr;</span>
				</div>
			</a>
		<?php endforeach; wp_reset_postdata(); ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 3. [valt_leaderboard] ──────────────────────────────────────────

add_shortcode( 'valt_leaderboard', function ( $atts ) {
	if ( ! valt_feature_enabled( 'leaderboard' ) ) return '';
	$atts = shortcode_atts( [ 'scope' => 'global', 'artist_id' => 0, 'limit' => 50, 'period' => 'all' ], $atts );
	$scope = $atts['period'] === 'monthly' ? 'monthly' : $atts['scope'];
	$data  = valt_get_leaderboard( $scope, (int) $atts['artist_id'], (int) $atts['limit'] );

	ob_start(); ?>
	<div class="valt-leaderboard">
		<table class="valt-table">
			<thead><tr><th>#</th><th>Fan</th><th>Points</th><th>Level</th><th>Badges</th></tr></thead>
			<tbody>
			<?php foreach ( $data as $entry ) : ?>
				<tr<?php echo $entry['user_id'] === get_current_user_id() ? ' class="valt-leaderboard__me"' : ''; ?>>
					<td><strong><?php echo (int) $entry['rank']; ?></strong></td>
					<td>
						<img src="<?php echo esc_url( $entry['avatar_url'] ); ?>" alt="" class="valt-leaderboard__avatar">
						<?php echo esc_html( $entry['display_name'] ); ?>
					</td>
					<td><?php echo number_format( $entry['total_points'] ); ?></td>
					<td><span class="valt-badge valt-badge--level"><?php echo esc_html( $entry['level_name'] ); ?></span></td>
					<td><?php echo (int) $entry['badge_count']; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php return ob_get_clean();
} );

// ─── 4. [valt_user_points] ──────────────────────────────────────────

add_shortcode( 'valt_user_points', function () {
	if ( ! valt_feature_enabled( 'gamification' ) ) return '';
	if ( ! is_user_logged_in() ) {
		return '<p class="valt-gated valt-gated--disconnected">Log in to see your points.</p>';
	}
	$level = valt_get_user_level( get_current_user_id() );

	ob_start(); ?>
	<div class="valt-user-points">
		<div class="valt-user-points__total"><?php echo number_format( $level['points'] ); ?> <small>points</small></div>
		<div class="valt-user-points__level">
			<span class="valt-badge valt-badge--level"><?php echo esc_html( $level['name'] ); ?></span>
			<?php if ( $level['next_name'] ) : ?>
				<span class="valt-user-points__next">Next: <?php echo esc_html( $level['next_name'] ); ?> (<?php echo number_format( $level['next_threshold'] ); ?> pts)</span>
			<?php endif; ?>
		</div>
		<div class="valt-user-points__bar">
			<div class="valt-user-points__fill" style="width:<?php echo (int) ( $level['progress'] * 100 ); ?>%;"></div>
		</div>
	</div>
	<?php return ob_get_clean();
} );

// ─── 5. [valt_user_badges] ──────────────────────────────────────────

add_shortcode( 'valt_user_badges', function ( $atts ) {
	if ( ! valt_feature_enabled( 'gamification' ) ) return '';
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$atts   = shortcode_atts( [ 'artist_id' => 0 ], $atts );
	$earned = valt_get_user_badges( get_current_user_id(), (int) $atts['artist_id'] );
	$all    = valt_badge_definitions();
	$earned_slugs = array_column( $earned, 'slug' );

	ob_start(); ?>
	<div class="valt-badges-grid">
		<?php foreach ( $all as $slug => $def ) :
			$is_earned = in_array( $slug, $earned_slugs, true );
		?>
			<div class="valt-badge-card <?php echo $is_earned ? 'valt-badge-card--earned' : 'valt-badge-card--locked'; ?>">
				<div class="valt-badge-card__icon"><?php echo esc_html( $def['icon'] ?? '★' ); ?></div>
				<strong><?php echo esc_html( $def['name'] ); ?></strong>
				<small><?php echo esc_html( $def['desc'] ); ?></small>
			</div>
		<?php endforeach; ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 6. [valt_mint_button] ──────────────────────────────────────────

add_shortcode( 'valt_mint_button', function ( $atts ) {
	$atts    = shortcode_atts( [ 'song_id' => 0 ], $atts );
	$song_id = (int) $atts['song_id'];
	if ( ! $song_id ) return '';

	$status     = get_post_meta( $song_id, 'valt_nft_status', true );
	$price_ada  = get_post_meta( $song_id, 'valt_nft_price_ada', true );
	$price_usd  = (int) get_post_meta( $song_id, 'valt_nft_price_usd', true );
	$max_supply = (int) get_post_meta( $song_id, 'valt_nft_max_supply', true );
	$mint_count = (int) get_post_meta( $song_id, 'valt_mint_count', true );

	// Does the connected wallet already hold this song's NFT? (Per-user, unlike $status.)
	$owned = function_exists( 'valt_user_owns_song' ) ? valt_user_owns_song( get_the_title( $song_id ) ) : 0;

	// Live NMKR inventory for this song (cached). $avail = free editions collectible right now;
	// null = inventory unknown (no API/config) — in that case we don't disable collecting.
	$inv       = function_exists( 'valt_song_inventory' ) ? valt_song_inventory() : [];
	$avail     = array_key_exists( $song_id, $inv ) ? (int) $inv[ $song_id ]['count'] : null;
	$avail_uid = ( $avail && ! empty( $inv[ $song_id ]['uid'] ) ) ? $inv[ $song_id ]['uid'] : (string) get_post_meta( $song_id, 'valt_nft_uid', true );

	// NMKR Pay link — song-specific (this song's available edition) so the pay page shows the
	// right song; fall back to a project-level random buy only if there's no edition uid.
	$config       = valt_nmkr_config();
	$project_uid  = str_replace( '-', '', $config['project_uid'] );
	$nmkr_base    = $config['mode'] === 'mainnet' ? 'https://pay.nmkr.io' : 'https://pay.preprod.nmkr.io';
	$song_nft_uid = str_replace( '-', '', $avail_uid );
	if ( $project_uid && $song_nft_uid ) {
		$nmkr_pay_url = "{$nmkr_base}/?p={$project_uid}&n={$song_nft_uid}";
	} elseif ( $project_uid ) {
		$nmkr_pay_url = "{$nmkr_base}/?p={$project_uid}&c=1";
	} else {
		$nmkr_pay_url = '';
	}

	$nmkr_bundle_url; // Reserved for future multi-copy support.

	ob_start(); ?>
	<div class="valt-mint" data-song-id="<?php echo $song_id; ?>">
		<?php if ( $owned > 0 ) : ?>
			<?php // Wallet already holds this song. Show ownership, but still allow collecting more copies. ?>
			<div class="valt-mint__owned">
				<?php echo valt_svg_music( 18 ); ?>
				<span class="valt-badge valt-badge--gold">In your collection &middot; you own <?php echo (int) $owned; ?></span>
				<?php $owned_artist = function_exists( 'valt_resolve_artist_id' ) ? valt_resolve_artist_id( $song_id ) : 0; ?>
				<?php if ( $owned_artist ) : ?>
					<a href="<?php echo esc_url( get_permalink( $owned_artist ) ); ?>" class="valt-mint__valt-link">Open <?php echo esc_html( get_the_title( $owned_artist ) ); ?>&rsquo;s Valt &rarr;</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( in_array( $status, [ 'pending', 'processing' ], true ) ) : ?>
			<div class="valt-mint__pending">
				<span class="valt-badge valt-badge--amber">Minting...</span>
				<p class="valt-mint__hint">Your NFT is being minted on Cardano. This can take a few minutes.</p>
			</div>
		<?php elseif ( $max_supply > 0 && $mint_count >= $max_supply ) : ?>
			<span class="valt-badge valt-badge--grey">Sold Out</span>
		<?php elseif ( $avail !== null && $avail <= 0 ) : ?>
			<?php // No free editions available on NMKR — collecting disabled for this song. ?>
			<span class="valt-badge valt-badge--grey">Not available to collect</span>
		<?php else : ?>
			<div class="valt-mint__prices">
				<?php if ( $price_ada ) : ?>
					<span class="valt-mint__price-ada"><?php echo esc_html( $price_ada ); ?> ADA</span>
				<?php endif; ?>
				<?php if ( $price_usd ) : ?>
					<span class="valt-mint__price-usd">~$<?php echo number_format( $price_usd / 100, 2 ); ?> USD</span>
				<?php endif; ?>
				<?php if ( $max_supply ) : ?>
					<span class="valt-mint__supply"><?php echo $mint_count; ?> / <?php echo $max_supply; ?> collected</span>
				<?php endif; ?>
			</div>

			<?php if ( $nmkr_pay_url ) : ?>
				<a href="<?php echo esc_url( $nmkr_pay_url ); ?>" target="_blank" rel="noopener" class="valt-btn valt-btn--primary valt-btn--large valt-mint__btn">
					<?php echo valt_svg_wallet( 16 ); ?> <?php echo $owned > 0 ? 'Collect another copy' : 'Collect with ADA'; ?>
				</a>
				<p class="valt-mint__hint">Pay with your Cardano wallet. The NFT is minted and delivered automatically.</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 7. [valt_nft_status] ───────────────────────────────────────────

add_shortcode( 'valt_nft_status', function ( $atts ) {
	$atts    = shortcode_atts( [ 'song_id' => 0 ], $atts );
	$song_id = (int) $atts['song_id'];
	if ( ! $song_id ) return '';

	$status = get_post_meta( $song_id, 'valt_nft_status', true ) ?: 'none';
	$class_map = [
		'none'       => 'grey',
		'pending'    => 'grey',
		'processing' => 'amber',
		'minted'     => 'gold',
		'complete'   => 'gold',
		'failed'     => 'grey',
	];
	$class = $class_map[ $status ] ?? 'grey';

	return '<span class="valt-badge valt-badge--' . $class . '" data-nft-status="' . esc_attr( $song_id ) . '">' . esc_html( ucfirst( $status ) ) . '</span>';
} );

// ─── 8. [valt_checkout_button] ──────────────────────────────────────

add_shortcode( 'valt_checkout_button', function ( $atts ) {
	$atts    = shortcode_atts( [ 'song_id' => 0, 'label' => 'Buy with Card' ], $atts );
	$song_id = (int) $atts['song_id'];
	if ( ! $song_id ) return '';

	$price_usd = (int) get_post_meta( $song_id, 'valt_nft_price_usd', true );
	if ( ! $price_usd ) return '';

	// Detect connected wallet for auto NFT delivery.
	$wallet_addr = '';
	if ( function_exists( 'cardanoPress' ) && cardanoPress()->userProfile()->isConnected() ) {
		$wallet_addr = cardanoPress()->userProfile()->connectedWallet();
	}

	ob_start(); ?>
	<div class="valt-checkout" data-song-id="<?php echo $song_id; ?>">
		<button class="valt-btn valt-btn--secondary valt-checkout__btn" data-action="checkout">
			<?php echo esc_html( $atts['label'] ); ?> — $<?php echo number_format( $price_usd / 100, 2 ); ?>
		</button>
		<?php if ( $wallet_addr ) : ?>
			<input type="hidden" data-wallet value="<?php echo esc_attr( $wallet_addr ); ?>">
			<p class="valt-checkout__hint">NFT will be delivered to your connected wallet.</p>
		<?php else : ?>
			<div class="valt-checkout__wallet-row" style="margin-top:8px;">
				<input type="text" class="valt-form__input" placeholder="Wallet address for NFT delivery (optional)" data-wallet>
				<p class="valt-checkout__hint">Leave empty to receive a claim link instead.</p>
			</div>
		<?php endif; ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 9. [valt_campaign_card] ────────────────────────────────────────

add_shortcode( 'valt_campaign_card', function ( $atts ) {
	if ( ! valt_feature_enabled( 'campaigns' ) ) return '';
	$atts     = shortcode_atts( [ 'album_id' => 0 ], $atts );
	$album_id = (int) $atts['album_id'];
	$progress = valt_get_campaign_progress( $album_id );
	if ( ! $progress ) return '<p>No active campaign.</p>';

	ob_start(); ?>
	<div class="valt-campaign" data-album-id="<?php echo $album_id; ?>">
		<h3><?php echo esc_html( $progress['album_title'] ); ?></h3>
		<p class="valt-campaign__artist">by <?php echo esc_html( $progress['artist_name'] ); ?></p>
		<?php if ( $progress['description'] ) : ?><p><?php echo wp_kses_post( $progress['description'] ); ?></p><?php endif; ?>
		<div class="valt-campaign__bar">
			<div class="valt-campaign__fill" style="width:<?php echo (int) $progress['percent']; ?>%;"></div>
		</div>
		<div class="valt-campaign__stats">
			<span><strong><?php echo number_format( $progress['total_pledged'] ); ?></strong> / <?php echo number_format( $progress['goal'] ); ?> points</span>
			<span><?php echo (int) $progress['backer_count']; ?> backers</span>
			<span><?php echo (int) $progress['percent']; ?>% funded</span>
		</div>
		<?php if ( $progress['deadline'] ) : ?>
			<p class="valt-campaign__deadline">Deadline: <?php echo esc_html( date( 'M j, Y', strtotime( $progress['deadline'] ) ) ); ?></p>
		<?php endif; ?>
		<?php if ( is_user_logged_in() && $progress['is_active'] ) : ?>
			<div class="valt-campaign__pledge">
				<input type="number" class="valt-form__input" placeholder="Points to pledge" min="1" data-pledge-amount>
				<button class="valt-btn valt-btn--primary" data-action="pledge">Pledge</button>
			</div>
		<?php endif; ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 10. [valt_active_campaigns] ────────────────────────────────────

add_shortcode( 'valt_active_campaigns', function ( $atts ) {
	if ( ! valt_feature_enabled( 'campaigns' ) ) return '';
	$atts      = shortcode_atts( [ 'limit' => 12 ], $atts );
	$campaigns = valt_get_active_campaigns( (int) $atts['limit'] );
	if ( empty( $campaigns ) ) return '<p>No active campaigns.</p>';

	ob_start(); ?>
	<div class="valt-campaigns-grid">
		<?php foreach ( $campaigns as $c ) : ?>
			<?php echo do_shortcode( '[valt_campaign_card album_id="' . $c['album_id'] . '"]' ); ?>
		<?php endforeach; ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 11. [valt_fan_dashboard] ───────────────────────────────────────

add_shortcode( 'valt_fan_dashboard', function () {
	if ( ! valt_feature_enabled( 'gamification' ) ) return '';
	if ( ! is_user_logged_in() ) {
		return '<p class="valt-gated valt-gated--disconnected">Log in to see your fan dashboard.</p>';
	}

	$user_id = get_current_user_id();

	ob_start(); ?>
	<div class="valt-fan-dashboard">
		<div class="valt-tabs">
			<button class="valt-tab-btn valt-tab-btn--active" data-tab="overview">Overview</button>
			<button class="valt-tab-btn" data-tab="badges">Badges</button>
			<button class="valt-tab-btn" data-tab="pledges">My Pledges</button>
		</div>

		<div class="valt-tab-panel valt-tab-panel--active" data-panel="overview">
			<?php echo do_shortcode( '[valt_user_points]' ); ?>
			<div style="margin-top:16px;">
				<button class="valt-btn valt-btn--secondary" data-action="claim-daily">Claim Daily Points</button>
				<span class="valt-fan-dashboard__daily-msg" data-daily-msg></span>
			</div>
		</div>

		<div class="valt-tab-panel" data-panel="badges">
			<?php echo do_shortcode( '[valt_user_badges]' ); ?>
		</div>

		<div class="valt-tab-panel" data-panel="pledges">
			<?php
			$pledges = valt_get_user_pledges( $user_id );
			if ( empty( $pledges ) ) : ?>
				<p>You haven't backed any campaigns yet.</p>
			<?php else : ?>
				<table class="valt-table">
					<thead><tr><th>Album</th><th>Artist</th><th>Your Pledge</th><th>Progress</th></tr></thead>
					<tbody>
					<?php foreach ( $pledges as $p ) : ?>
						<tr>
							<td><?php echo esc_html( $p['album_title'] ); ?></td>
							<td><?php echo esc_html( $p['artist_name'] ); ?></td>
							<td><?php echo number_format( $p['user_pledged'] ); ?> pts</td>
							<td><?php echo (int) $p['percent']; ?>%</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
	<?php return ob_get_clean();
} );

// ─── 12. [valt_artist_fans] ─────────────────────────────────────────

add_shortcode( 'valt_artist_fans', function ( $atts ) {
	if ( ! valt_feature_enabled( 'leaderboard' ) ) return '';
	$atts      = shortcode_atts( [ 'artist_id' => 0, 'limit' => 10 ], $atts );
	$artist_id = (int) $atts['artist_id'];
	if ( ! $artist_id ) return '';

	$data = valt_get_leaderboard( 'artist', $artist_id, (int) $atts['limit'] );
	if ( empty( $data ) ) return '<p>No fans yet. Be the first!</p>';

	ob_start(); ?>
	<div class="valt-artist-fans">
		<h4>Top Fans</h4>
		<?php foreach ( $data as $entry ) : ?>
			<div class="valt-artist-fans__item">
				<img src="<?php echo esc_url( $entry['avatar_url'] ); ?>" alt="" class="valt-leaderboard__avatar">
				<span><?php echo esc_html( $entry['display_name'] ); ?></span>
				<span class="valt-badge valt-badge--level"><?php echo esc_html( $entry['level_name'] ); ?></span>
				<span><?php echo number_format( $entry['total_points'] ); ?> pts</span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 13. [valt_connect_mint] ────────────────────────────────────────

add_shortcode( 'valt_connect_mint', function ( $atts ) {
	$atts    = shortcode_atts( [ 'song_id' => 0 ], $atts );
	$song_id = (int) $atts['song_id'];
	if ( ! $song_id ) return '';

	$price_usd = (int) get_post_meta( $song_id, 'valt_nft_price_usd', true );
	$stripe_ok = function_exists( 'valt_feature_enabled' ) && valt_feature_enabled( 'stripe' ) && defined( 'VALT_STRIPE_SECRET_KEY' );

	ob_start(); ?>
	<div class="valt-connect-mint">
		<?php // Primary: Collect with ADA via NMKR payment gateway. ?>
		<?php echo do_shortcode( '[valt_mint_button song_id="' . $song_id . '"]' ); ?>

		<?php // Secondary: Pay with card via Stripe (only if Stripe is configured). ?>
		<?php if ( $price_usd && $stripe_ok ) : ?>
			<div class="valt-connect-mint__divider"><span>or</span></div>
			<?php echo do_shortcode( '[valt_checkout_button song_id="' . $song_id . '"]' ); ?>
		<?php endif; ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 14. [valt_song_card] ───────────────────────────────────────────

add_shortcode( 'valt_song_card', function ( $atts ) {
	$atts    = shortcode_atts( [ 'song_id' => 0 ], $atts );
	$song_id = (int) $atts['song_id'];
	if ( ! $song_id ) return '';

	$song      = get_post( $song_id );
	if ( ! $song ) return '';

	$artist_id = valt_resolve_artist_id( $song_id );
	$artist    = $artist_id ? get_post( $artist_id ) : null;
	$image_id  = (int) get_post_meta( $song_id, 'valt_nft_image_id', true ) ?: get_post_thumbnail_id( $song_id );
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
	$price_usd = (int) get_post_meta( $song_id, 'valt_nft_price_usd', true );

	ob_start(); ?>
	<div class="valt-song-card">
		<?php if ( $image_url ) : ?>
			<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $song->post_title ); ?>" class="valt-song-card__img">
		<?php endif; ?>
		<div class="valt-song-card__info">
			<h3><?php echo esc_html( $song->post_title ); ?></h3>
			<?php if ( $artist ) : ?><p class="valt-song-card__artist"><?php echo esc_html( $artist->post_title ); ?></p><?php endif; ?>
			<?php echo do_shortcode( '[valt_release_status post_id="' . $song_id . '"]' ); ?>
			<?php echo do_shortcode( '[valt_nft_status song_id="' . $song_id . '"]' ); ?>
		</div>
		<div class="valt-song-card__actions">
			<?php if ( $price_usd ) : ?>
				<?php echo do_shortcode( '[valt_checkout_button song_id="' . $song_id . '"]' ); ?>
			<?php else : ?>
				<?php echo do_shortcode( '[valt_connect_mint song_id="' . $song_id . '"]' ); ?>
			<?php endif; ?>
		</div>
	</div>
	<?php return ob_get_clean();
} );

// ─── Helper: count NFTs a user owns for a song ──────────────────────

function valt_user_owns_song( string $song_title ): int {
	if ( ! is_user_logged_in() || ! function_exists( 'cardanoPress' ) ) return 0;
	$profile = cardanoPress()->userProfile();
	if ( ! $profile->isConnected() ) return 0;

	$assets = $profile->storedAssets();
	$count  = 0;
	$clean  = strtolower( preg_replace( '/[^a-z0-9]/i', '', $song_title ) );

	foreach ( $assets as $asset ) {
		$meta = $asset['onchain_metadata'] ?? [];
		$name = $meta['name'] ?? '';
		// Match by on-chain metadata name.
		if ( $name && strtolower( $name ) === strtolower( $song_title ) ) {
			$count += (int) ( $asset['quantity'] ?? 1 );
			continue;
		}
		// Fallback: match by decoded asset name containing the song slug.
		$hex = $asset['asset_name'] ?? '';
		if ( $hex ) {
			$decoded = @hex2bin( $hex );
			if ( $decoded && stripos( $decoded, $clean ) !== false ) {
				$count += (int) ( $asset['quantity'] ?? 1 );
			}
		}
	}

	// Also check local registry.
	if ( $count === 0 ) {
		global $wpdb;
		$registry_name = $wpdb->get_var( $wpdb->prepare(
			"SELECT display_name FROM {$wpdb->prefix}valt_nft_registry WHERE display_name = %s LIMIT 1",
			$song_title
		) );
		// If in registry but not in wallet, count stays 0.
	}

	return $count;
}

// ─── 15. [valt_song_grid] ───────────────────────────────────────────

add_shortcode( 'valt_song_grid', function ( $atts ) {
	$atts = shortcode_atts( [ 'artist_id' => 0, 'album_id' => 0, 'limit' => 12, 'exclude' => '', 'columns' => 3, 'ids' => '' ], $atts );
	$qa = [ 'post_type' => 'song', 'post_status' => 'publish', 'posts_per_page' => (int) $atts['limit'], 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => [] ];
	// Curated list: show exactly these songs, in the given order.
	if ( $atts['ids'] ) {
		$ids = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ) );
		if ( $ids ) {
			$qa['post__in']       = $ids;
			$qa['orderby']        = 'post__in';
			$qa['posts_per_page'] = count( $ids );
			unset( $qa['order'] );
		}
	}
	if ( (int) $atts['artist_id'] ) $qa['meta_query'][] = [ 'key' => 'artist', 'value' => (int) $atts['artist_id'] ];
	if ( (int) $atts['album_id'] )  $qa['meta_query'][] = [ 'key' => 'album',  'value' => (int) $atts['album_id'] ];
	if ( $atts['exclude'] ) $qa['post__not_in'] = array_map( 'intval', explode( ',', $atts['exclude'] ) );
	$songs = new WP_Query( $qa );
	if ( ! $songs->have_posts() ) return '<p>No songs found.</p>';
	ob_start(); ?>
	<div class="valt-song-grid valt-song-grid--cols-<?php echo (int) $atts['columns']; ?>">
		<?php while ( $songs->have_posts() ) : $songs->the_post();
			$sid = get_the_ID(); $aid = valt_resolve_artist_id( $sid );
			$a = $aid ? get_post( $aid ) : null;
			$img = (int) get_post_meta( $sid, 'valt_nft_image_id', true ) ?: get_post_thumbnail_id( $sid );
			$img_url = $img ? wp_get_attachment_image_url( $img, 'large' ) : ( $aid ? get_the_post_thumbnail_url( $aid, 'large' ) : '' );
			$pusd = (int) get_post_meta( $sid, 'valt_nft_price_usd', true );
			$pada = get_post_meta( $sid, 'valt_nft_price_ada', true );
			$dur = get_post_meta( $sid, 'duration', true );
			$owned = valt_user_owns_song( get_the_title() );
		?>
		<a href="<?php the_permalink(); ?>" class="valt-song-grid__item <?php echo $owned ? 'valt-song-grid__item--owned' : ''; ?>">
			<div class="valt-song-grid__art">
				<?php if ( $img_url ) : ?><img src="<?php echo esc_url( $img_url ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
				<?php else : ?><div class="valt-song-grid__placeholder"></div><?php endif; ?>
				<?php if ( $owned ) : ?>
					<span class="valt-song-grid__owned"><?php echo $owned; ?> owned</span>
				<?php endif; ?>
			</div>
			<div class="valt-song-grid__info">
				<strong class="valt-song-grid__title"><?php the_title(); ?></strong>
				<?php if ( $a ) : ?><span class="valt-song-grid__artist"><?php echo esc_html( $a->post_title ); ?></span><?php endif; ?>
				<span class="valt-song-grid__meta">
					<?php if ( $dur ) echo esc_html( $dur ); ?>
					<?php if ( $pusd ) echo ' &middot; $' . number_format( $pusd / 100, 2 ); ?>
					<?php if ( $pada ) echo ' &middot; ' . esc_html( $pada ) . ' ADA'; ?>
				</span>
			</div>
		</a>
		<?php endwhile; wp_reset_postdata(); ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 16. [valt_contact_form] ────────────────────────────────────────

/**
 * Verify a Google reCAPTCHA v3 token. Returns true only when Google reports
 * success AND the spam score meets the configured threshold.
 */
if ( ! function_exists( 'valt_verify_recaptcha_v3' ) ) {
	function valt_verify_recaptcha_v3( $token, $secret, $threshold = 0.5 ) {
		$token = trim( (string) $token );
		if ( $token === '' ) return false;
		$resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
			'timeout' => 10,
			'body'    => [
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
			],
		] );
		if ( is_wp_error( $resp ) ) return false;
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $data['success'] ) ) return false;
		if ( isset( $data['score'] ) && (float) $data['score'] < (float) $threshold ) return false;
		return true;
	}
}

add_shortcode( 'valt_contact_form', function () {
	$site_key     = trim( (string) get_option( 'valt_recaptcha_v3_site_key', '' ) );
	$secret_key   = trim( (string) get_option( 'valt_recaptcha_v3_secret_key', '' ) );
	$threshold    = (float) get_option( 'valt_recaptcha_v3_threshold', 0.5 );
	$recaptcha_on = ( $site_key !== '' && $secret_key !== '' );

	$sent = false; $error = '';
	if ( isset( $_POST['valt_contact_nonce'] ) && wp_verify_nonce( $_POST['valt_contact_nonce'], 'valt_contact' ) ) {
		$name = sanitize_text_field( $_POST['valt_name'] ?? '' );
		$email = sanitize_email( $_POST['valt_email'] ?? '' );
		$msg = sanitize_textarea_field( $_POST['valt_message'] ?? '' );
		$honeypot = trim( (string) ( $_POST['valt_website'] ?? '' ) );

		if ( $honeypot !== '' ) { $sent = true; /* Bot tripped the honeypot — feign success, send nothing. */ }
		elseif ( ! $name || ! $email || ! $msg ) { $error = 'All fields are required.'; }
		elseif ( ! is_email( $email ) ) { $error = 'Please enter a valid email.'; }
		elseif ( $recaptcha_on && ! valt_verify_recaptcha_v3( $_POST['g-recaptcha-response'] ?? '', $secret_key, $threshold ) ) { $error = 'Spam check failed. Please try again.'; }
		else { $sent = wp_mail( 'cullah@awen.online', 'VALT Contact: ' . $name, "Name: {$name}\nEmail: {$email}\n\n{$msg}", [ 'Reply-To: ' . $email ] ); if ( ! $sent ) $error = 'Could not send. Try again.'; }
	}
	ob_start(); ?>
	<div class="valt-contact-form">
		<?php if ( $sent ) : ?><div class="valt-notice valt-notice--success">Message sent!</div>
		<?php else : ?>
			<?php if ( $error ) : ?><div class="valt-notice valt-notice--error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<form method="post" class="valt-form" id="valt-contact-form"><?php wp_nonce_field( 'valt_contact', 'valt_contact_nonce' ); ?>
				<div class="valt-form__group"><label class="valt-form__label">Name</label><input type="text" name="valt_name" class="valt-form__input" required></div>
				<div class="valt-form__group"><label class="valt-form__label">Email</label><input type="email" name="valt_email" class="valt-form__input" required></div>
				<div class="valt-form__group"><label class="valt-form__label">Message</label><textarea name="valt_message" class="valt-form__textarea" rows="5" required></textarea></div>
				<div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;width:0;overflow:hidden;"><label>Website</label><input type="text" name="valt_website" tabindex="-1" autocomplete="off"></div>
				<?php if ( $recaptcha_on ) : ?><input type="hidden" name="g-recaptcha-response" id="valt-recaptcha-token"><?php endif; ?>
				<button type="submit" class="valt-btn valt-btn--primary">Send Message</button>
			</form>
			<?php if ( $recaptcha_on ) : ?>
			<script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr( $site_key ); ?>"></script>
			<script>
			(function(){
				var f = document.getElementById('valt-contact-form');
				if ( ! f ) return;
				var submitting = false;
				f.addEventListener('submit', function(e){
					if ( submitting || ! window.grecaptcha ) return;
					e.preventDefault();
					grecaptcha.ready(function(){
						grecaptcha.execute('<?php echo esc_js( $site_key ); ?>', { action: 'contact' }).then(function(token){
							document.getElementById('valt-recaptcha-token').value = token;
							submitting = true;
							f.submit();
						});
					});
				});
			})();
			</script>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 18. [valt_follow_button] ───────────────────────────────────────

add_shortcode( 'valt_follow_button', function ( $atts ) {
	$atts      = shortcode_atts( [ 'artist_id' => 0 ], $atts );
	$artist_id = (int) $atts['artist_id'];
	if ( ! $artist_id ) return '';

	global $wpdb;
	$table = $wpdb->prefix . 'valt_follows';

	$count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table} WHERE artist_id = %d", $artist_id
	) );

	$is_following = false;
	if ( is_user_logged_in() ) {
		$is_following = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND artist_id = %d",
			get_current_user_id(), $artist_id
		) );
	}

	ob_start(); ?>
	<div class="valt-follow" data-artist-id="<?php echo $artist_id; ?>">
		<?php if ( is_user_logged_in() ) : ?>
			<button class="valt-btn <?php echo $is_following ? 'valt-btn--secondary valt-follow--active' : 'valt-btn--primary'; ?> valt-btn--small" data-action="follow">
				<?php if ( $is_following ) : ?>
					<?php echo valt_svg_user( 14 ); ?> Following
				<?php else : ?>
					<?php echo valt_svg_user( 14 ); ?> Follow
				<?php endif; ?>
			</button>
		<?php else : ?>
			<a href="<?php echo home_url( '/dashboard/' ); ?>" class="valt-btn valt-btn--secondary valt-btn--small">
				<?php echo valt_svg_wallet( 14 ); ?> Connect to Follow
			</a>
		<?php endif; ?>
		<span class="valt-follow__count" data-follow-count><?php echo $count; ?></span>
		<span class="valt-follow__label">follower<?php echo $count !== 1 ? 's' : ''; ?></span>
	</div>
	<?php return ob_get_clean();
} );
