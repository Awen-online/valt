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

	// NMKR Pay link — per-NFT specific (user buys THIS song's NFT).
	$nft_uid     = get_post_meta( $song_id, 'valt_nft_uid', true );
	$config      = valt_nmkr_config();
	$project_uid = str_replace( '-', '', $config['project_uid'] );
	$nft_clean   = $nft_uid ? str_replace( '-', '', $nft_uid ) : '';
	$nmkr_base   = $config['mode'] === 'mainnet' ? 'https://pay.nmkr.io' : 'https://pay.preprod.nmkr.io';
	$nmkr_pay_url = ( $nft_clean && $project_uid ) ? "{$nmkr_base}/?p={$project_uid}&n={$nft_clean}" : '';

	ob_start(); ?>
	<div class="valt-mint" data-song-id="<?php echo $song_id; ?>">
		<?php if ( $status === 'minted' || $status === 'complete' ) : ?>
			<div class="valt-mint__done">
				<?php echo valt_svg_music( 20 ); ?>
				<span class="valt-badge valt-badge--gold">Collected</span>
				<span class="valt-mint__count"><?php echo $mint_count; ?> minted<?php echo $max_supply ? " / {$max_supply}" : ''; ?></span>
			</div>
		<?php elseif ( in_array( $status, [ 'pending', 'processing' ], true ) ) : ?>
			<div class="valt-mint__pending">
				<span class="valt-badge valt-badge--amber">Minting...</span>
				<p class="valt-mint__hint">Your NFT is being minted on Cardano. This can take a few minutes.</p>
			</div>
		<?php elseif ( $max_supply > 0 && $mint_count >= $max_supply ) : ?>
			<span class="valt-badge valt-badge--grey">Sold Out</span>
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
				<a href="<?php echo esc_url( $nmkr_pay_url ); ?>" target="_blank" rel="noopener" class="valt-btn valt-btn--primary valt-mint__btn">
					<?php echo valt_svg_wallet( 16 ); ?> Collect with ADA
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

	$artist_id = (int) get_post_meta( $song_id, 'artist', true );
	$artist    = $artist_id ? get_post( $artist_id ) : null;
	$image_id  = (int) get_post_meta( $song_id, 'valt_nft_image_id', true ) ?: get_post_thumbnail_id( $song_id );
	$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
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

// ─── 15. [valt_song_grid] ───────────────────────────────────────────

add_shortcode( 'valt_song_grid', function ( $atts ) {
	$atts = shortcode_atts( [ 'artist_id' => 0, 'album_id' => 0, 'limit' => 12, 'exclude' => '', 'columns' => 3 ], $atts );
	$qa = [ 'post_type' => 'song', 'post_status' => 'publish', 'posts_per_page' => (int) $atts['limit'], 'orderby' => 'date', 'order' => 'DESC', 'meta_query' => [] ];
	if ( (int) $atts['artist_id'] ) $qa['meta_query'][] = [ 'key' => 'artist', 'value' => (int) $atts['artist_id'] ];
	if ( (int) $atts['album_id'] )  $qa['meta_query'][] = [ 'key' => 'album',  'value' => (int) $atts['album_id'] ];
	if ( $atts['exclude'] ) $qa['post__not_in'] = array_map( 'intval', explode( ',', $atts['exclude'] ) );
	$songs = new WP_Query( $qa );
	if ( ! $songs->have_posts() ) return '<p>No songs found.</p>';
	ob_start(); ?>
	<div class="valt-song-grid valt-song-grid--cols-<?php echo (int) $atts['columns']; ?>">
		<?php while ( $songs->have_posts() ) : $songs->the_post();
			$sid = get_the_ID(); $aid = (int) get_post_meta( $sid, 'artist', true );
			$a = $aid ? get_post( $aid ) : null;
			$img = (int) get_post_meta( $sid, 'valt_nft_image_id', true ) ?: get_post_thumbnail_id( $sid );
			$img_url = $img ? wp_get_attachment_image_url( $img, 'medium' ) : ( $aid ? get_the_post_thumbnail_url( $aid, 'medium' ) : '' );
			$pusd = (int) get_post_meta( $sid, 'valt_nft_price_usd', true );
			$pada = get_post_meta( $sid, 'valt_nft_price_ada', true );
			$dur = get_post_meta( $sid, 'duration', true );
		?>
		<a href="<?php the_permalink(); ?>" class="valt-song-grid__item">
			<div class="valt-song-grid__art">
				<?php if ( $img_url ) : ?><img src="<?php echo esc_url( $img_url ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
				<?php else : ?><div class="valt-song-grid__placeholder"></div><?php endif; ?>
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

add_shortcode( 'valt_contact_form', function () {
	$sent = false; $error = '';
	if ( isset( $_POST['valt_contact_nonce'] ) && wp_verify_nonce( $_POST['valt_contact_nonce'], 'valt_contact' ) ) {
		$name = sanitize_text_field( $_POST['valt_name'] ?? '' );
		$email = sanitize_email( $_POST['valt_email'] ?? '' );
		$msg = sanitize_textarea_field( $_POST['valt_message'] ?? '' );
		if ( ! $name || ! $email || ! $msg ) { $error = 'All fields are required.'; }
		elseif ( ! is_email( $email ) ) { $error = 'Please enter a valid email.'; }
		else { $sent = wp_mail( 'cullah@awen.online', 'VALT Contact: ' . $name, "Name: {$name}\nEmail: {$email}\n\n{$msg}", [ 'Reply-To: ' . $email ] ); if ( ! $sent ) $error = 'Could not send. Try again.'; }
	}
	ob_start(); ?>
	<div class="valt-contact-form">
		<?php if ( $sent ) : ?><div class="valt-notice valt-notice--success">Message sent!</div>
		<?php else : ?>
			<?php if ( $error ) : ?><div class="valt-notice valt-notice--error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
			<form method="post" class="valt-form"><?php wp_nonce_field( 'valt_contact', 'valt_contact_nonce' ); ?>
				<div class="valt-form__group"><label class="valt-form__label">Name</label><input type="text" name="valt_name" class="valt-form__input" required></div>
				<div class="valt-form__group"><label class="valt-form__label">Email</label><input type="email" name="valt_email" class="valt-form__input" required></div>
				<div class="valt-form__group"><label class="valt-form__label">Message</label><textarea name="valt_message" class="valt-form__textarea" rows="5" required></textarea></div>
				<button type="submit" class="valt-btn valt-btn--primary">Send Message</button>
			</form>
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
