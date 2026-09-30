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
// ids="1,2,3" shows exactly those artists in that order (overrides featured).
// Renders nothing when there are no matching artists, so the home stays clean.

add_shortcode( 'valt_featured_artists', function ( $atts ) {
	$atts = shortcode_atts( [ 'limit' => 6, 'genre' => '', 'country' => '', 'ids' => '' ], $atts );

	$base = [
		'post_type'      => 'artist',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['limit'],
		'meta_query'     => [],
	];
	if ( $atts['genre'] )   $base['meta_query'][] = [ 'key' => 'genre',   'value' => $atts['genre'],   'compare' => '=' ];
	if ( $atts['country'] ) $base['meta_query'][] = [ 'key' => 'country', 'value' => $atts['country'], 'compare' => '=' ];

	// Curated list: show exactly these artists, in the given order.
	$ids = $atts['ids'] ? array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ) ) : [];
	if ( $ids ) {
		$base['post__in']       = $ids;
		$base['orderby']        = 'post__in';
		$base['posts_per_page'] = count( $ids );
		$query = new WP_Query( $base );
	} else {
		// Prefer explicitly-featured artists; fall back to any matching artist.
		$featured = $base;
		$featured['meta_query'][] = [ 'key' => 'valt_featured', 'value' => '1' ];
		$query = new WP_Query( $featured );
		if ( ! $query->have_posts() ) {
			$query = new WP_Query( $base );
		}
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
	$avail     = valt_song_stock( $song_id );
	// Songs switched to our own Anvil checkout collect in-page (wallet signs), not via NMKR Pay.
	$anvil     = function_exists( 'valt_song_uses_anvil' ) && valt_song_uses_anvil( $song_id );
	if ( $anvil ) {
		$max_supply = valt_anvil_cap( $song_id ); // this series' own edition count
		$mint_count = valt_anvil_sold( $song_id );
	}
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
		<?php elseif ( ( $max_supply > 0 && $mint_count >= $max_supply ) || valt_song_sold_out( $song_id ) ) : ?>
			<?php // Every edition is collected or claimed by a paid order: a finished state, not an error.
			$so_artist = function_exists( 'valt_resolve_artist_id' ) ? valt_resolve_artist_id( $song_id ) : 0; ?>
			<div class="valt-soldout">
				<div class="valt-soldout__head">
					<span class="valt-soldout__stamp">Sold out</span>
					<span class="valt-soldout__count"><?php echo $max_supply ? 'All ' . (int) $max_supply . ' editions claimed' : 'Every edition claimed'; ?></span>
				</div>
				<p class="valt-soldout__text">Thanks to everyone who collected <?php echo esc_html( get_the_title( $song_id ) ); ?>. The full song stays free to stream here<?php echo $so_artist ? ', and ' . esc_html( get_the_title( $so_artist ) ) . '&rsquo;s holders keep access to the Valt' : ''; ?>.</p>
				<div class="valt-soldout__actions">
					<?php if ( $so_artist ) : ?>
						<a href="<?php echo esc_url( get_permalink( $so_artist ) ); ?>" class="valt-btn valt-btn--secondary">Visit <?php echo esc_html( get_the_title( $so_artist ) ); ?>&rsquo;s page</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( home_url( '/discover/' ) ); ?>" class="valt-btn valt-btn--primary">Find songs still available</a>
				</div>
			</div>
		<?php elseif ( $avail !== null && $avail <= 0 ) : ?>
			<?php // Stock known to be zero but no edition cap recorded: collecting disabled for this song. ?>
			<span class="valt-badge valt-badge--grey">Not available to collect</span>
		<?php else : ?>
			<div class="valt-mint__prices">
				<?php if ( $price_ada ) : ?>
					<span class="valt-mint__price-ada"><?php echo esc_html( $price_ada ); ?> ADA</span>
				<?php endif; ?>
				<?php // Testnet ADA has no dollar value; showing one next to a "not real" disclaimer confuses. ?>
				<?php if ( $price_usd && $config['mode'] === 'mainnet' ) : ?>
					<span class="valt-mint__price-usd">~$<?php echo number_format( $price_usd / 100, 2 ); ?> USD</span>
				<?php elseif ( $config['mode'] !== 'mainnet' ) : ?>
					<span class="valt-mint__price-usd">test ADA</span>
				<?php endif; ?>
				<?php if ( $max_supply ) : ?>
					<?php // On testnet every mint so far is an Awen test collect: say so, so the count never reads as demand. ?>
					<span class="valt-mint__supply"><?php echo $mint_count > 0
						? ( $config['mode'] !== 'mainnet' ? $mint_count . ' / ' . $max_supply . ' minted on testnet (includes Awen test collects)' : $mint_count . ' / ' . $max_supply . ' collected' )
						: 'Edition of ' . $max_supply; ?></span>
				<?php endif; ?>
				<?php echo valt_scarcity_badge( $song_id ); ?>
			</div>

			<?php if ( $anvil || $nmkr_pay_url ) : ?>
				<?php
				// Quantity picker: only when the live stock is known and more than one edition is free.
				$qty_max = ( $avail !== null && get_post_status( $song_id ) === 'publish' && defined( 'VALT_COLLECT_MAX_QTY' ) ) ? min( VALT_COLLECT_MAX_QTY, (int) $avail ) : 1;
				$label1  = $anvil ? 'Collect' : ( $owned > 0 ? 'Collect another copy' : 'Collect with ADA' );
				?>
				<?php if ( $qty_max > 1 ) : ?>
				<div class="valt-qty" data-valt-qty data-max="<?php echo (int) $qty_max; ?>" data-price="<?php echo esc_attr( (float) $price_ada ); ?>">
					<span class="valt-qty__label" id="valt-qty-label-<?php echo $song_id; ?>">Editions</span>
					<div class="valt-qty__stepper" role="group" aria-labelledby="valt-qty-label-<?php echo $song_id; ?>">
						<button type="button" class="valt-qty__btn" data-step="-1" aria-label="One fewer edition" disabled>&minus;</button>
						<output class="valt-qty__val" aria-live="polite">1</output>
						<button type="button" class="valt-qty__btn" data-step="1" aria-label="One more edition">+</button>
					</div>
					<span class="valt-qty__total" aria-live="polite"><?php echo esc_html( $price_ada ); ?> ADA</span>
					<span class="valt-qty__max">up to <?php echo (int) $qty_max; ?> per order</span>
				</div>
				<?php endif; ?>
				<?php if ( $anvil ) : ?>
				<button type="button" class="valt-btn valt-btn--primary valt-btn--large valt-mint__btn"
					data-valt-anvil="<?php echo (int) $song_id; ?>" data-label1="<?php echo esc_attr( $label1 ); ?>">
					<?php echo valt_svg_wallet( 16 ); ?> <span class="valt-mint__btn-label"><?php echo esc_html( $label1 ); ?></span>
				</button>
				<div class="valt-mint__wallets" hidden></div>
				<?php else : ?>
				<a href="<?php echo esc_url( $nmkr_pay_url ); ?>" target="_blank" rel="noopener" class="valt-btn valt-btn--primary valt-btn--large valt-mint__btn"
					data-valt-collect="<?php echo (int) $song_id; ?>" data-label1="<?php echo esc_attr( $label1 ); ?>">
					<?php echo valt_svg_wallet( 16 ); ?> <span class="valt-mint__btn-label"><?php echo esc_html( $label1 ); ?></span>
				</a>
				<?php endif; ?>
				<p class="valt-mint__msg" role="alert" hidden></p>
				<div class="valt-mint__done-box" hidden></div>
				<p class="valt-mint__hint"><?php echo $anvil ? 'Pay with your Cardano wallet. Your edition is minted straight to your wallet in the same transaction.<br>Fees are about 1.2 test ADA per edition. Another 1.2 test ADA per edition travels with the token into your wallet (a Cardano minimum), so it stays yours.' : 'Pay with your Cardano wallet. The NFT is minted and delivered automatically.'; ?>
					<?php if ( $config['mode'] !== 'mainnet' ) : ?>
						<br>Testnet preview: you'll need a Preprod wallet and free test ADA. <a href="<?php echo esc_url( home_url( '/how-to-collect/' ) ); ?>">How to collect</a>
					<?php endif; ?>
				</p>
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

	ob_start(); ?>
	<div class="valt-connect-mint">
		<?php // Collect with ADA via the NMKR payment gateway — on-chain only. ?>
		<?php echo do_shortcode( '[valt_mint_button song_id="' . $song_id . '"]' ); ?>
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
			<?php echo do_shortcode( '[valt_connect_mint song_id="' . $song_id . '"]' ); ?>
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

// ─── 14b. Track data + play button (feeds the theme's sticky player) ─

/**
 * Everything the player needs to play one song, or null src when no audio is attached.
 * Audio source order: song_url (hosted file) then the audio_file attachment.
 */
function valt_track_data( $sid ) {
	$sid = (int) $sid;
	$aid = function_exists( 'valt_resolve_artist_id' ) ? valt_resolve_artist_id( $sid ) : 0;
	$src = trim( (string) get_post_meta( $sid, 'song_url', true ) );
	if ( ! $src ) {
		$att = (int) get_post_meta( $sid, 'audio_file', true );
		if ( $att ) $src = (string) wp_get_attachment_url( $att );
	}
	$img = (int) get_post_meta( $sid, 'valt_nft_image_id', true ) ?: get_post_thumbnail_id( $sid );
	$art = $img ? wp_get_attachment_image_url( $img, 'medium' ) : ( $aid ? get_the_post_thumbnail_url( $aid, 'medium' ) : '' );
	$genre = get_post_meta( $sid, 'genre', true ) ?: ( $aid ? get_post_meta( $aid, 'genre', true ) : '' );
	return [
		'id'         => $sid,
		'title'      => html_entity_decode( get_the_title( $sid ), ENT_QUOTES ),
		'url'        => get_permalink( $sid ),
		'artist'     => $aid ? html_entity_decode( get_the_title( $aid ), ENT_QUOTES ) : '',
		'artist_url' => $aid ? get_permalink( $aid ) : '',
		'art'        => $art ?: '',
		'src'        => $src,
		'genre'      => is_array( $genre ) ? implode( ', ', $genre ) : (string) $genre,
		'price_ada'  => (string) get_post_meta( $sid, 'valt_nft_price_ada', true ),
		'duration'   => (string) get_post_meta( $sid, 'duration', true ),
	];
}

/** Round gold play button carrying its track as JSON. Renders nothing when there's no audio. */
function valt_play_button( $track, $size = '', $label = '' ) {
	if ( empty( $track['src'] ) ) return '';
	$payload = wp_json_encode( array_intersect_key( $track, array_flip( [ 'id', 'title', 'url', 'artist', 'artist_url', 'art', 'src' ] ) ) );
	$label   = $label ?: 'Play ' . $track['title'] . ( $track['artist'] ? ' by ' . $track['artist'] : '' );
	return sprintf(
		'<button type="button" class="valt-play%s" data-valt-track="%s" aria-label="%s">'
		. '<svg class="valt-play__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>'
		. '<svg class="valt-play__pause" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>'
		. '</button>',
		$size ? ' valt-play--' . esc_attr( $size ) : '',
		esc_attr( $payload ),
		esc_attr( $label )
	);
}

if ( ! defined( 'VALT_SCARCITY_THRESHOLD' ) ) {
	define( 'VALT_SCARCITY_THRESHOLD', 5 ); // show "Only N left" at or below this many free editions
}

/**
 * True when a limited-edition song has no free editions left on NMKR (every edition is sold or
 * reserved by a paid order). False when stock is unknown, so an API hiccup never shows "Sold out".
 */
function valt_song_sold_out( int $song_id ): bool {
	if ( get_post_status( $song_id ) !== 'publish' ) return false;
	if ( (int) get_post_meta( $song_id, 'valt_nft_max_supply', true ) < 1 ) return false;
	$n = valt_song_stock( $song_id );
	return $n !== null && $n < 1;
}

/**
 * Editions collectible right now: from our Anvil ledger when the song is on Anvil checkout,
 * otherwise from NMKR's free inventory. Null when unknown.
 */
function valt_song_stock( int $song_id ): ?int {
	if ( function_exists( 'valt_anvil_available' ) ) {
		$a = valt_anvil_available( $song_id );
		if ( $a !== null ) return $a;
	}
	if ( ! function_exists( 'valt_song_inventory' ) ) return null;
	$inv = valt_song_inventory();
	return isset( $inv[ $song_id ] ) ? (int) $inv[ $song_id ]['count'] : null;
}

/**
 * "Only N left" / "Last one" badge from live NMKR stock (inventory is cached ~60s), or a
 * "Sold out" chip once every edition is claimed. Renders nothing when stock is unknown or plentiful.
 */
function valt_scarcity_badge( int $song_id ): string {
	if ( get_post_status( $song_id ) !== 'publish' ) return '';
	if ( valt_song_sold_out( $song_id ) ) {
		return '<span class="valt-scarcity valt-scarcity--out">Sold out</span>';
	}
	$n = valt_song_stock( $song_id );
	if ( $n === null ) return '';
	if ( $n < 1 || $n > VALT_SCARCITY_THRESHOLD ) return '';
	// Testnet: a plain count, no urgency (the collects so far are Awen tests, not demand).
	if ( function_exists( 'valt_nmkr_config' ) && valt_nmkr_config()['mode'] !== 'mainnet' ) {
		return '<span class="valt-scarcity valt-scarcity--calm">' . esc_html( $n === 1 ? '1 edition left' : "{$n} editions left" ) . '</span>';
	}
	$label = $n === 1 ? 'Last one' : "Only {$n} left";
	return '<span class="valt-scarcity' . ( $n <= 2 ? ' valt-scarcity--hot' : '' ) . '"><span class="valt-scarcity__dot" aria-hidden="true"></span>' . esc_html( $label ) . '</span>';
}

/** Genre pills from a comma list. */
function valt_genre_pills( $genre, $max = 3 ) {
	$parts = array_slice( array_filter( array_map( 'trim', explode( ',', (string) $genre ) ) ), 0, $max );
	if ( ! $parts ) return '';
	$out = '<div class="valt-pills">';
	foreach ( $parts as $g ) $out .= '<span class="valt-pill">' . esc_html( $g ) . '</span>';
	return $out . '</div>';
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
			$sid = get_the_ID(); $t = valt_track_data( $sid );
			$img = (int) get_post_meta( $sid, 'valt_nft_image_id', true ) ?: get_post_thumbnail_id( $sid );
			$aid = valt_resolve_artist_id( $sid );
			$img_url = $img ? wp_get_attachment_image_url( $img, 'large' ) : ( $aid ? get_the_post_thumbnail_url( $aid, 'large' ) : '' );
			$owned = valt_user_owns_song( get_the_title() );
		?>
		<?php // Card = <article>; the title link stretches over it so the play button can be a real sibling control. ?>
		<article class="valt-song-grid__item <?php echo $owned ? 'valt-song-grid__item--owned' : ''; ?>" data-valt-song="<?php echo (int) $sid; ?>">
			<div class="valt-song-grid__art">
				<?php if ( $img_url ) : ?><img src="<?php echo esc_url( $img_url ); ?>" alt="" loading="lazy">
				<?php else : ?><div class="valt-song-grid__placeholder"></div><?php endif; ?>
				<?php if ( $owned ) : ?>
					<span class="valt-song-grid__owned"><?php echo $owned; ?> owned</span>
				<?php else : ?>
					<?php echo valt_scarcity_badge( $sid ); ?>
				<?php endif; ?>
				<?php echo valt_play_button( $t ); ?>
			</div>
			<div class="valt-song-grid__info">
				<a href="<?php the_permalink(); ?>" class="valt-song-grid__link"><strong class="valt-song-grid__title"><?php the_title(); ?></strong></a>
				<?php if ( $t['artist'] ) : ?><span class="valt-song-grid__artist"><?php echo esc_html( $t['artist'] ); ?></span><?php endif; ?>
				<div class="valt-song-grid__foot">
					<?php echo valt_genre_pills( $t['genre'], 1 ); ?>
					<?php if ( $t['price_ada'] ) : ?><span class="valt-song-grid__price"><?php echo esc_html( $t['price_ada'] ); ?> ADA</span><?php endif; ?>
				</div>
			</div>
		</article>
		<?php endwhile; wp_reset_postdata(); ?>
	</div>
	<?php return ob_get_clean();
} );

// ─── 15a. [valt_spotlight] — a big featured release (homepage) ─────

/**
 * [valt_spotlight song_id="300" size="lg|md" kicker="New release" teaser="https://…mp4" blurb="…"]
 * lg = full-width feature; md = compact horizontal card. Either can take an optional muted, looping teaser clip.
 * Mentions the holder-only extra when the artist's Valt has exclusive content.
 */
add_shortcode( 'valt_spotlight', function ( $atts ) {
	$a   = shortcode_atts( [ 'song_id' => 0, 'size' => 'lg', 'kicker' => '', 'teaser' => '', 'blurb' => '' ], $atts );
	$sid = (int) $a['song_id'];
	if ( ! $sid || get_post_status( $sid ) !== 'publish' ) return '';
	$t    = valt_track_data( $sid );
	$aid  = valt_resolve_artist_id( $sid );
	$lg   = $a['size'] !== 'md';
	$img  = (int) get_post_meta( $sid, 'valt_nft_image_id', true ) ?: get_post_thumbnail_id( $sid );
	$art  = $img ? wp_get_attachment_image_url( $img, $lg ? 'full' : 'large' ) : $t['art'];
	$max  = (int) get_post_meta( $sid, 'valt_nft_max_supply', true );
	$blurb = $a['blurb'] ?: wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $sid ) ) ), $lg ? 34 : 18 );
	$has_exclusive = $aid && ( get_post_meta( $aid, 'valt_exclusive_content', true ) || has_shortcode( get_post_field( 'post_content', $sid ), 'valt_gated_content' ) );
	// Say what holders actually get, from the artist's Valt content heading (e.g. "(short film)").
	$excl_html  = $aid ? (string) get_post_meta( $aid, 'valt_exclusive_content', true ) : '';
	$excl_title = preg_match( '#<h4[^>]*>(.*?)</h4>#is', $excl_html, $mm ) ? strtolower( wp_strip_all_tags( html_entity_decode( $mm[1] ) ) ) : '';
	if ( strpos( $excl_title, 'film' ) !== false ) {
		$unlock_label = 'Holders unlock the short film';
	} elseif ( strpos( $excl_title, 'video' ) !== false || has_shortcode( get_post_field( 'post_content', $sid ), 'valt_gated_content' ) ) {
		$unlock_label = 'Holders unlock the official music video';
	} else {
		$unlock_label = 'Holders unlock exclusive content';
	}
	$testnet = function_exists( 'valt_nmkr_config' ) && valt_nmkr_config()['mode'] !== 'mainnet';

	ob_start(); ?>
	<article class="valt-spotlight valt-spotlight--<?php echo $lg ? 'lg' : 'md'; ?>" data-valt-song="<?php echo $sid; ?>">
		<div class="valt-spotlight__media">
			<?php if ( $art ) : /* Cover sits under any teaser, so reduced motion (teaser hidden) still shows the art. */ ?>
				<img src="<?php echo esc_url( $art ); ?>" alt="" loading="<?php echo $lg ? 'eager' : 'lazy'; ?>">
			<?php endif; ?>
			<?php if ( $a['teaser'] ) : ?>
				<video class="valt-spotlight__teaser" autoplay muted loop playsinline preload="metadata" poster="<?php echo esc_url( $art ); ?>" aria-hidden="true">
					<source src="<?php echo esc_url( $a['teaser'] ); ?>" type="video/mp4">
				</video>
			<?php endif; ?>
			<?php echo valt_play_button( $t, $lg ? 'xl' : '' ); ?>
		</div>
		<div class="valt-spotlight__body">
			<?php if ( $a['kicker'] ) : ?><span class="valt-kicker"><?php echo esc_html( $a['kicker'] ); ?></span><?php endif; ?>
			<h3 class="valt-spotlight__title"><a href="<?php echo esc_url( $t['url'] ); ?>"><?php echo esc_html( $t['title'] ); ?></a></h3>
			<?php if ( $t['artist'] ) : ?><a class="valt-spotlight__artist" href="<?php echo esc_url( $t['artist_url'] ); ?>"><?php echo esc_html( $t['artist'] ); ?></a><?php endif; ?>
			<?php echo valt_genre_pills( $t['genre'], $lg ? 3 : 2 ); ?>
			<?php if ( $blurb ) : ?><p class="valt-spotlight__blurb"><?php echo esc_html( $blurb ); ?></p><?php endif; ?>
			<?php if ( $has_exclusive ) : ?>
				<p class="valt-spotlight__unlock">
					<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/></svg>
					<?php echo esc_html( $unlock_label ); ?>
				</p>
			<?php endif; ?>
			<div class="valt-spotlight__meta">
				<?php if ( $t['price_ada'] ) : ?><span class="valt-spotlight__price"><?php echo esc_html( $t['price_ada'] ); ?> ADA<?php echo $testnet ? ' <small>test ADA</small>' : ''; ?></span><?php endif; ?>
				<?php if ( $max ) : ?><span>Edition of <?php echo $max; ?></span><?php endif; ?>
				<?php echo valt_scarcity_badge( $sid ); ?>
				<?php if ( $t['duration'] ) : ?><span><?php echo esc_html( $t['duration'] ); ?></span><?php endif; ?>
			</div>
			<div class="valt-spotlight__cta">
				<a class="valt-btn valt-btn--primary<?php echo $lg ? ' valt-btn--large' : ''; ?>" href="<?php echo esc_url( $t['url'] ); ?>"><?php echo valt_song_sold_out( $sid ) ? 'Listen to ' : 'Collect '; ?><?php echo esc_html( $t['title'] ); ?></a>
				<?php if ( $lg && $t['artist_url'] ) : ?><a class="valt-btn valt-btn--secondary" href="<?php echo esc_url( $t['artist_url'] ); ?>">Enter <?php echo esc_html( $t['artist'] ); ?>&rsquo;s Valt</a><?php endif; ?>
			</div>
		</div>
	</article>
	<?php return ob_get_clean();
} );

// ─── 15b. [valt_tracklist] — compact playable rows (artist releases, "more from") ─

add_shortcode( 'valt_tracklist', function ( $atts ) {
	$atts = shortcode_atts( [ 'artist_id' => 0, 'limit' => 20, 'exclude' => '', 'ids' => '', 'play_all' => '1' ], $atts );
	$qa = [ 'post_type' => 'song', 'post_status' => 'publish', 'posts_per_page' => (int) $atts['limit'], 'orderby' => 'date', 'order' => 'ASC', 'meta_query' => [] ];
	if ( $atts['ids'] ) {
		$ids = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ) );
		if ( $ids ) { $qa['post__in'] = $ids; $qa['orderby'] = 'post__in'; unset( $qa['order'] ); }
	}
	if ( (int) $atts['artist_id'] ) $qa['meta_query'][] = [ 'key' => 'artist', 'value' => (int) $atts['artist_id'] ];
	if ( $atts['exclude'] ) $qa['post__not_in'] = array_map( 'intval', explode( ',', $atts['exclude'] ) );
	$songs = get_posts( $qa );
	if ( ! $songs ) return '<p class="valt-empty">No releases yet.</p>';

	$tracks   = array_map( function ( $p ) { return valt_track_data( $p->ID ); }, $songs );
	$playable = array_values( array_filter( $tracks, function ( $t ) { return $t['src']; } ) );
	$uid      = 'valt-tl-' . wp_generate_password( 6, false );

	ob_start(); ?>
	<?php if ( $atts['play_all'] && count( $playable ) > 1 ) : $first = $playable[0]; ?>
	<div class="valt-playall">
		<?php echo str_replace( 'data-valt-track=', 'data-valt-queue="#' . esc_attr( $uid ) . '" data-valt-track=', valt_play_button( $first, 'lg', 'Play all' ) ); ?>
		<div class="valt-playall__text">
			<span class="valt-playall__label">Play all</span>
			<span class="valt-playall__sub"><?php echo count( $playable ); ?> tracks<?php echo count( $playable ) < count( $tracks ) ? ' with previews' : ''; ?></span>
		</div>
	</div>
	<?php endif; ?>
	<ol class="valt-tracklist" id="<?php echo esc_attr( $uid ); ?>">
		<?php foreach ( $tracks as $i => $t ) : ?>
		<li class="valt-track" data-valt-song="<?php echo (int) $t['id']; ?>">
			<span class="valt-track__lead">
				<span class="valt-track__num"><?php echo $i + 1; ?></span>
				<?php echo valt_play_button( $t, 'sm' ); ?>
			</span>
			<?php if ( $t['art'] ) : ?><img class="valt-track__art" src="<?php echo esc_url( $t['art'] ); ?>" alt="" loading="lazy"><?php else : ?><span class="valt-track__art"></span><?php endif; ?>
			<span class="valt-track__main">
				<a class="valt-track__title" href="<?php echo esc_url( $t['url'] ); ?>"><?php echo esc_html( $t['title'] ); ?></a>
				<span class="valt-track__sub"><?php echo esc_html( trim( $t['artist'] . ( $t['duration'] ? ' · ' . $t['duration'] : '' ), ' ·' ) ); ?></span>
			</span>
			<span class="valt-track__pills"><?php echo valt_genre_pills( $t['genre'], 1 ); ?></span>
			<span class="valt-track__price"><?php echo $t['price_ada'] ? esc_html( $t['price_ada'] ) . ' ADA' : ''; ?></span>
		</li>
		<?php endforeach; ?>
	</ol>
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
		<?php // Hidden until there's at least one follower; "0 followers" reads as an empty room. ?>
		<span class="valt-follow__count" data-follow-count<?php echo $count ? '' : ' hidden'; ?>><?php echo $count; ?></span>
		<span class="valt-follow__label"<?php echo $count ? '' : ' hidden'; ?>>follower<?php echo $count !== 1 ? 's' : ''; ?></span>
	</div>
	<?php return ob_get_clean();
} );
