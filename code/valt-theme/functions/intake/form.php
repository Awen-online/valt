<?php
/**
 * Valt Artist Intake — settings, helpers, and the [valt_artist_intake] form.
 *
 * A simple, low-friction lead form for prospective artists: a page carrying the
 * shortcode is what outreach emails link to, so recipients have one clear action.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings, with defaults.
 */
function valt_intake_get_settings() {
	return wp_parse_args( get_option( 'valt_intake_settings', array() ), array(
		'enabled'         => true,
		'notify_email'    => get_option( 'admin_email' ),
		'success_message' => "You're on the list. We'll be in touch about bringing your music to Valt.",
	) );
}

/** How-did-you-hear options (shared with the survey vocabulary). */
function valt_intake_how_found_options() {
	return array(
		''                  => 'Select…',
		'cardano_community' => 'Cardano community / X (Twitter)',
		'newsletter'        => 'An Awen / Valt email',
		'an_artist'         => 'Another artist',
		'a_friend'          => 'A friend',
		'search'            => 'Search engine',
		'other'             => 'Other',
	);
}

/**
 * Client IP for rate-limiting.
 *
 * Uses REMOTE_ADDR — the only value the attacker cannot forge — by default.
 * Forwarded headers (X-Forwarded-For, CF-Connecting-IP, X-Real-IP) are honoured
 * ONLY when the site is explicitly declared to sit behind a trusted reverse
 * proxy/CDN via the VALT_TRUSTED_PROXY constant; otherwise they are attacker-
 * controlled and trusting them would defeat the rate limit. When trusted, the
 * right-most X-Forwarded-For hop (the one our proxy actually saw) is used, not
 * the left-most attacker-supplied entry. See M3 security assessment FIND-01.
 */
function valt_intake_client_ip() {
	if ( defined( 'VALT_TRUSTED_PROXY' ) && VALT_TRUSTED_PROXY ) {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = trim( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$parts = array_map( 'trim', explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = end( $parts );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
	}
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '0.0.0.0';
}

/**
 * [valt_artist_intake source="..."] — renders the intake form inline.
 */
function valt_intake_shortcode( $atts ) {
	$atts     = shortcode_atts( array( 'source' => '', 'heading' => 'Bring your work to Valt' ), $atts, 'valt_artist_intake' );
	$settings = valt_intake_get_settings();

	if ( empty( $settings['enabled'] ) ) {
		return '';
	}

	$found = valt_intake_how_found_options();

	ob_start();
	?>
	<form class="valt-intake" data-endpoint="<?php echo esc_url( rest_url( 'valt/v1/artist-intake' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
		<div class="valt-intake__head">
			<h3><?php echo esc_html( $atts['heading'] ); ?></h3>
			<p>Musicians, visual artists and other creators release limited editions on Valt. Fans collect them on Cardano and unlock your exclusive Valt. Tell us about you and we'll help you get set up.</p>
		</div>

		<input type="hidden" name="source" value="<?php echo esc_attr( $atts['source'] ); ?>">
		<!-- honeypot: real people leave this empty -->
		<div class="valt-intake__hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

		<div class="valt-intake__row">
			<label class="valt-intake__field">
				<span>Artist / project name <em>*</em></span>
				<input type="text" name="artist_name" required maxlength="200" autocomplete="organization">
			</label>
			<label class="valt-intake__field">
				<span>Email <em>*</em></span>
				<input type="email" name="email" required maxlength="200" autocomplete="email">
			</label>
		</div>

		<div class="valt-intake__row">
			<label class="valt-intake__field">
				<span>Genre or medium</span>
				<input type="text" name="genre" maxlength="120" placeholder="e.g. alt / indie, hip-hop, illustration">
			</label>
			<label class="valt-intake__field">
				<span>Where are you based?</span>
				<input type="text" name="location" maxlength="160" placeholder="City, country">
			</label>
		</div>

		<label class="valt-intake__field">
			<span>Links <small>(Spotify, Bandcamp, portfolio, socials)</small></span>
			<input type="text" name="links" maxlength="500" placeholder="Paste a link or two">
		</label>

		<label class="valt-intake__field">
			<span>Tell us about your work <small>(optional)</small></span>
			<textarea name="message" rows="3" maxlength="1500" placeholder="What should we know? What would you put behind your Valt?"></textarea>
		</label>

		<div class="valt-intake__row">
			<label class="valt-intake__field">
				<span>Do you have a Cardano wallet?</span>
				<select name="has_wallet">
					<option value="">Select…</option>
					<option value="yes">Yes</option>
					<option value="no">Not yet</option>
					<option value="unsure">Not sure what that is</option>
				</select>
			</label>
			<label class="valt-intake__field">
				<span>How did you hear about Valt?</span>
				<select name="how_found">
					<?php foreach ( $found as $v => $l ) : ?>
						<option value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<label class="valt-intake__consent">
			<input type="checkbox" name="consent" value="1" required>
			<span>It's okay to email me about joining Valt.</span>
		</label>

		<button type="submit" class="valt-intake__submit">Request an invite</button>
		<p class="valt-intake__msg" role="status" aria-live="polite"></p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'valt_artist_intake', 'valt_intake_shortcode' );

/**
 * [valt_artist_benefits] — the "Why release on Valt" value props as an icon card
 * grid. Kept as a shortcode so it can use the theme's SVG icon system and stay
 * out of the page's stored HTML.
 */
function valt_intake_benefits_shortcode() {
	$benefits = array(
		array(
			'icon'  => function_exists( 'valt_svg_music' ) ? valt_svg_music( 26 ) : '',
			'title' => 'Own your releases',
			'body'  => 'Release your songs or artwork as limited Cardano editions (via NMKR), with the details that matter carried on the token.',
		),
		array(
			'icon'  => function_exists( 'valt_svg_lock' ) ? valt_svg_lock( 26 ) : '',
			'title' => 'Token-gated Valts',
			'body'  => 'Give collectors exclusive content that unlocks from on-chain ownership, with no gatekeeper in the middle.',
		),
		array(
			'icon'  => function_exists( 'valt_svg_layers' ) ? valt_svg_layers( 26 ) : '',
			'title' => 'Multiple editions',
			'body'  => 'Release several collectible copies of a song or piece so more of your fans can own one.',
		),
		array(
			'icon'  => function_exists( 'valt_svg_heart' ) ? valt_svg_heart( 26 ) : '',
			'title' => 'Keep the relationship',
			'body'  => "You're building a direct line to your superfans, not renting one.",
		),
	);

	ob_start();
	?>
	<section class="valt-benefits" aria-label="Why release on Valt">
		<h2 class="valt-benefits__heading">Why release on Valt</h2>
		<div class="valt-benefits__grid">
			<?php foreach ( $benefits as $b ) : ?>
				<div class="valt-benefits__card">
					<span class="valt-benefits__icon" aria-hidden="true"><?php echo $b['icon']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<h3 class="valt-benefits__title"><?php echo esc_html( $b['title'] ); ?></h3>
					<p class="valt-benefits__body"><?php echo esc_html( $b['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'valt_artist_benefits', 'valt_intake_benefits_shortcode' );
