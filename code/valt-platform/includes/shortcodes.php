<?php
defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// [valt_gated_content artist_id="" policy_id="" connect_message="" locked_message=""]
//
// Enclosing shortcode. Server-side policy check — non-holders never receive
// the inner HTML.
// ---------------------------------------------------------------------------

add_shortcode( 'valt_gated_content', function ( $atts, $content = '' ) {

	$atts = shortcode_atts( [
		'artist_id'       => '',
		'policy_id'       => '',
		'connect_message' => 'Connect your Cardano wallet to access this exclusive content.',
		'locked_message'  => 'You need to hold an NFT from this collection to unlock this content.',
	], $atts, 'valt_gated_content' );

	// Resolve policy ID from attribute or artist meta
	$policy_id = trim( (string) $atts['policy_id'] );
	if ( empty( $policy_id ) && ! empty( $atts['artist_id'] ) ) {
		$policy_id = (string) get_post_meta( (int) $atts['artist_id'], 'valt_policy_id', true );
	}

	if ( empty( $policy_id ) ) {
		return '<p class="valt-error">Gated zone not configured (no policy ID).</p>';
	}

	if ( ! function_exists( 'cardanoPress' ) ) {
		return '<p class="valt-error">CardanoPress is not active.</p>';
	}

	$profile = cardanoPress()->userProfile();

	// --- Not connected ---
	if ( ! $profile->isConnected() ) {
		ob_start();
		?>
		<div class="valt-gated valt-gated--disconnected">
			<div class="valt-gated__lock">&#128274;</div>
			<p class="valt-gated__message"><?php echo esc_html( $atts['connect_message'] ); ?></p>
			<?php cardanoPress()->template( 'part/modal-trigger' ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	// --- Connected but no stored assets (needs sync) ---
	$assets = $profile->storedAssets();
	if ( empty( $assets ) ) {
		ob_start();
		?>
		<div class="valt-gated valt-gated--needs-sync">
			<div class="valt-gated__lock">&#128274;</div>
			<p class="valt-gated__message">
				Your wallet is connected but your NFTs haven&rsquo;t been synced yet.
			</p>
			<a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="valt-btn">
				Sync Wallet
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	// --- Connected, no matching NFT ---
	if ( ! valt_user_holds_policy( $policy_id ) ) {
		ob_start();
		?>
		<div class="valt-gated valt-gated--locked">
			<div class="valt-gated__lock">&#128274;</div>
			<p class="valt-gated__message"><?php echo esc_html( $atts['locked_message'] ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	// --- NFT holder: render content ---
	return '<div class="valt-gated valt-gated--unlocked">' . do_shortcode( $content ) . '</div>';

} );

// ---------------------------------------------------------------------------
// [valt_connect_prompt text="Connect Wallet" message=""]
//
// Renders the CardanoPress modal trigger. Silent if already connected.
// ---------------------------------------------------------------------------

add_shortcode( 'valt_connect_prompt', function ( $atts ) {

	$atts = shortcode_atts( [
		'text'    => 'Connect Wallet',
		'message' => '',
	], $atts, 'valt_connect_prompt' );

	if ( ! function_exists( 'cardanoPress' ) ) {
		return '';
	}

	if ( cardanoPress()->userProfile()->isConnected() ) {
		return ''; // silent if already connected
	}

	ob_start();
	?>
	<div class="valt-connect-prompt">
		<?php if ( $atts['message'] ) : ?>
			<p class="valt-connect-prompt__message"><?php echo esc_html( $atts['message'] ); ?></p>
		<?php endif; ?>
		<?php cardanoPress()->template( 'part/modal-trigger' ); ?>
	</div>
	<?php
	return ob_get_clean();

} );

// ---------------------------------------------------------------------------
// [valt_artist_profile artist_id=""]
//
// Public artist card: photo, name, genre, country, bio. No gating.
// ---------------------------------------------------------------------------

add_shortcode( 'valt_artist_profile', function ( $atts ) {

	$atts = shortcode_atts( [ 'artist_id' => '' ], $atts, 'valt_artist_profile' );

	$artist_id = (int) $atts['artist_id'];
	if ( ! $artist_id ) {
		return '';
	}

	$artist = get_post( $artist_id );
	if ( ! $artist || $artist->post_type !== 'artist' ) {
		return '';
	}

	$photo   = get_the_post_thumbnail( $artist_id, 'medium', [ 'class' => 'valt-artist-profile__photo' ] );
	$name    = get_the_title( $artist_id );
	$bio     = get_post_meta( $artist_id, 'bio', true );
	$genre   = get_post_meta( $artist_id, 'genre', true );
	$country = get_post_meta( $artist_id, 'country', true );

	ob_start();
	?>
	<div class="valt-artist-profile">
		<?php if ( $photo ) : ?>
			<div class="valt-artist-profile__photo-wrap"><?php echo $photo; ?></div>
		<?php endif; ?>
		<div class="valt-artist-profile__info">
			<h2 class="valt-artist-profile__name"><?php echo esc_html( $name ); ?></h2>
			<?php if ( $genre || $country ) : ?>
				<div class="valt-artist-profile__meta">
					<?php if ( $genre ) : ?>
						<span class="valt-tag valt-tag--genre"><?php echo esc_html( $genre ); ?></span>
					<?php endif; ?>
					<?php if ( $country ) : ?>
						<span class="valt-tag valt-tag--country"><?php echo esc_html( $country ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $bio ) : ?>
				<div class="valt-artist-profile__bio"><?php echo wp_kses_post( $bio ); ?></div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();

} );

// ---------------------------------------------------------------------------
// [valt_artist_valt artist_id=""]
//
// Public artist header + gated fan-club zone. Admins place Elementor content
// between the opening and closing tags; this shortcode handles the gate logic.
// ---------------------------------------------------------------------------

add_shortcode( 'valt_artist_valt', function ( $atts, $content = '' ) {

	$atts = shortcode_atts( [ 'artist_id' => '' ], $atts, 'valt_artist_valt' );

	$artist_id = (int) $atts['artist_id'];
	if ( ! $artist_id ) {
		return '';
	}

	$public_html = do_shortcode( '[valt_artist_profile artist_id="' . $artist_id . '"]' );
	$policy_id   = get_post_meta( $artist_id, 'valt_policy_id', true );

	$gated_html = '';
	if ( $policy_id && trim( $content ) !== '' ) {
		$gated_html = do_shortcode(
			'[valt_gated_content artist_id="' . $artist_id . '"]' . $content . '[/valt_gated_content]'
		);
		$gated_html = '<div class="valt-artist-valt__zone">' . $gated_html . '</div>';
	}

	return '<div class="valt-artist-valt">' . $public_html . $gated_html . '</div>';

} );

// ---------------------------------------------------------------------------
// [valt_release_status post_id=""]
//
// Inline coloured badge showing the release status of a Song CPT.
// ---------------------------------------------------------------------------

add_shortcode( 'valt_release_status', function ( $atts ) {

	$atts = shortcode_atts( [ 'post_id' => '' ], $atts, 'valt_release_status' );

	$post_id = (int) $atts['post_id'];
	if ( ! $post_id ) {
		return '';
	}

	$status     = (int) get_post_meta( $post_id, 'valt_release_status', true ) ?: 1;
	$mint_count = (int) get_post_meta( $post_id, 'valt_mint_count', true );

	$badges = [
		1 => [ 'label' => 'Uploaded',         'class' => 'valt-badge--grey'  ],
		2 => [ 'label' => 'In NFT Collection', 'class' => 'valt-badge--amber' ],
		3 => [ 'label' => 'Minted',            'class' => 'valt-badge--gold'  ],
	];

	$badge = $badges[ $status ] ?? $badges[1];

	if ( $status === 3 && $mint_count > 0 ) {
		$badge['label'] = 'Minted (' . $mint_count . ' copies)';
	}

	return '<span class="valt-badge ' . esc_attr( $badge['class'] ) . '">'
		. esc_html( $badge['label'] )
		. '</span>';

} );

// ---------------------------------------------------------------------------
// [valt_artist_dashboard]
//
// Full artist management dashboard for logged-in users with a linked Artist CPT.
// ---------------------------------------------------------------------------

add_shortcode( 'valt_artist_dashboard', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="valt-notice">Please <a href="'
			. esc_url( wp_login_url( get_permalink() ) )
			. '">log in</a> to access your artist dashboard.</p>';
	}

	$artist = valt_get_current_artist();
	if ( ! $artist ) {
		return '<div class="valt-dashboard valt-dashboard--no-artist">'
			. '<p class="valt-notice">No artist profile is linked to your account. '
			. 'Please contact an administrator.</p>'
			. '</div>';
	}

	return valt_render_artist_dashboard( $artist );

} );
