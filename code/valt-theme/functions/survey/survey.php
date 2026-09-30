<?php
/**
 * Valt Feedback Survey — modal markup + shortcode.
 *
 * The modal is printed hidden in the footer; survey.js decides when to show it
 * (Nth visit, time on site, right after a mint, or the [valt_survey] shortcode).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Use-case options — "What brought you to Valt?" (multi-select).
 *
 * @return array<string,string>
 */
function valt_survey_use_case_options() {
	return array(
		'collect_songs'     => 'Collect songs I love',
		'own_nfts'          => 'Truly own the music I collect',
		'exclusive_content' => 'Unlock exclusive artist content',
		'discover'          => 'Discover new artists',
		'support_artists'   => 'Support artists directly',
		'artist_upload'     => "I'm an artist — publish my music",
		'cardano'           => 'Explore the Cardano ecosystem',
		'other'             => 'Other',
	);
}

/**
 * "How did you find Valt?" options (single choice).
 *
 * @return array<string,string>
 */
function valt_survey_how_found_options() {
	return array(
		'cardano_community' => 'Cardano community / X (Twitter)',
		'project_catalyst'  => 'Project Catalyst',
		'an_artist'         => 'An artist I follow',
		'friend'            => 'A friend',
		'search'            => 'Search engine',
		'other'             => 'Other',
	);
}

/**
 * "When you collect a song, what matters most?" (single choice).
 * Distilled from the tokenomics research Q12 — tells us whether a future
 * revenue share is a fan-side draw or purely an artist-side instrument.
 *
 * @return array<string,string>
 */
function valt_survey_collect_value_options() {
	return array(
		'own_forever'  => 'Owning it, for good',
		'exclusive'    => 'Unlocking exclusive content',
		'artist_paid'  => 'The artist earning more',
		'future_share' => 'Sharing in its future success',
		'being_early'  => 'Being early — and recognised for it',
	);
}

/**
 * "If collecting also earned a share of future success, you'd collect…"
 * Distilled from tokenomics research Q13 — tests whether financialising a
 * collectible grows or spoils the thing that made it good.
 *
 * @return array<string,string>
 */
function valt_survey_invest_effect_options() {
	return array(
		'more' => 'More — that sounds great',
		'same' => 'About the same',
		'less' => 'Less — I collect for the music, not returns',
	);
}

/**
 * Print the survey modal in the footer (hidden until survey.js shows it).
 */
function valt_survey_modal_output() {
	if ( is_admin() ) {
		return;
	}

	$settings = valt_survey_get_settings();
	if ( empty( $settings['survey_enabled'] ) ) {
		return;
	}

	// Respect a logged-in user's server-side dismissal.
	if ( is_user_logged_in() ) {
		$dismissed = get_user_meta( get_current_user_id(), 'valt_survey_dismissed', true );
		if ( $dismissed && ( time() - (int) $dismissed ) < ( 90 * DAY_IN_SECONDS ) ) {
			return;
		}
	}
	?>
	<div id="valt-survey-modal" class="valt-survey-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="Valt feedback survey">
		<div class="valt-survey-container">
			<button class="valt-survey-close" aria-label="Close survey">&times;</button>

			<div class="valt-survey-step" data-step="1">
				<h3>How likely are you to recommend Valt to a friend?</h3>
				<p class="valt-survey-subtitle">0 = Not at all likely &nbsp;&bull;&nbsp; 10 = Extremely likely</p>
				<div class="valt-nps-buttons">
					<?php for ( $i = 0; $i <= 10; $i++ ) : ?>
						<button type="button" class="valt-nps-btn" data-score="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></button>
					<?php endfor; ?>
				</div>
			</div>

			<div class="valt-survey-step" data-step="2" style="display:none;">
				<h3>What brought you to Valt?</h3>
				<p class="valt-survey-subtitle">Select all that apply</p>
				<div class="valt-use-case-grid">
					<?php foreach ( valt_survey_use_case_options() as $value => $label ) : ?>
						<label class="valt-checkbox-label">
							<input type="checkbox" name="use_case" value="<?php echo esc_attr( $value ); ?>">
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<button type="button" class="valt-survey-next">Next</button>
			</div>

			<div class="valt-survey-step" data-step="3" style="display:none;">
				<h3>How easy was collecting your first song?</h3>
				<p class="valt-survey-subtitle">1 = Confusing &nbsp;&bull;&nbsp; 5 = Effortless</p>
				<div class="valt-stars">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<button type="button" class="valt-star-btn" data-rating="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( $i . ( $i > 1 ? ' stars' : ' star' ) ); ?>">&#9733;</button>
					<?php endfor; ?>
				</div>
			</div>

			<div class="valt-survey-step" data-step="4" style="display:none;">
				<h3>What's the one feature you'd most like to see?</h3>
				<textarea class="valt-survey-textarea" name="feature_request" placeholder="Tell us what would make Valt better&hellip;" maxlength="5000"></textarea>
				<button type="button" class="valt-survey-next">Next</button>
			</div>

			<div class="valt-survey-step" data-step="5" style="display:none;">
				<h3>How did you find Valt?</h3>
				<div class="valt-radio-group">
					<?php foreach ( valt_survey_how_found_options() as $value => $label ) : ?>
						<label class="valt-radio-label">
							<input type="radio" name="how_found_us" value="<?php echo esc_attr( $value ); ?>">
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<button type="button" class="valt-survey-submit">Send feedback</button>
			</div>

			<div class="valt-survey-step valt-survey-thanks" data-step="done" style="display:none;">
				<h3>Thank you! &#127925;</h3>
				<p>You're early &mdash; your feedback shapes what Valt becomes.</p>
			</div>

			<div class="valt-survey-footer">
				<label class="valt-checkbox-label valt-dont-show">
					<input type="checkbox" id="valt-survey-dont-show">
					<span>Don't show this again</span>
				</label>
				<span class="valt-survey-step-indicator"></span>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'valt_survey_modal_output', 99 );

/**
 * [valt_survey] — drops a marker that survey.js reads to force the modal open,
 * e.g. on a dedicated /feedback page linked from outreach emails.
 */
function valt_survey_shortcode() {
	return '<div id="valt-survey-trigger" data-trigger="manual" style="display:none;"></div>';
}
add_shortcode( 'valt_survey', 'valt_survey_shortcode' );

/**
 * [valt_survey_form] — the full survey rendered inline in the page body (all
 * questions on one page + a single submit), for a dedicated /feedback page.
 * survey.js sees the .valt-survey-inline form, wires it, and suppresses the
 * modal on that page so a visitor is never asked twice.
 */
function valt_survey_inline_form() {
	if ( is_admin() ) {
		return '';
	}
	$settings = valt_survey_get_settings();
	if ( empty( $settings['survey_enabled'] ) ) {
		return '';
	}

	ob_start();
	?>
	<form class="valt-survey-inline" novalidate>
		<div class="valt-survey-hp" aria-hidden="true">
			<label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
		</div>

		<div class="valt-survey-field">
			<h3>How likely are you to recommend Valt to a friend?</h3>
			<p class="valt-survey-subtitle">0 = Not at all likely &nbsp;&bull;&nbsp; 10 = Extremely likely</p>
			<div class="valt-nps-buttons">
				<?php for ( $i = 0; $i <= 10; $i++ ) : ?>
					<button type="button" class="valt-nps-btn" data-score="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></button>
				<?php endfor; ?>
			</div>
		</div>

		<div class="valt-survey-field">
			<h3>What brought you to Valt?</h3>
			<p class="valt-survey-subtitle">Select all that apply</p>
			<div class="valt-use-case-grid">
				<?php foreach ( valt_survey_use_case_options() as $value => $label ) : ?>
					<label class="valt-checkbox-label">
						<input type="checkbox" name="use_case" value="<?php echo esc_attr( $value ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="valt-survey-field">
			<h3>When you collect a song, what matters most to you?</h3>
			<p class="valt-survey-subtitle">Pick the one that fits best</p>
			<div class="valt-radio-group">
				<?php foreach ( valt_survey_collect_value_options() as $value => $label ) : ?>
					<label class="valt-radio-label">
						<input type="radio" name="collect_value" value="<?php echo esc_attr( $value ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="valt-survey-field">
			<h3>Now picture this&hellip;</h3>
			<p class="valt-survey-subtitle">The early fans who collect a song share a small slice of what it earns over time. How does that sit with you?</p>
			<div class="valt-scale-buttons" role="group" aria-label="Rate from not for me to love it">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<button type="button" class="valt-scale-btn" data-sentiment="<?php echo esc_attr( $i ); ?>"><?php echo esc_html( $i ); ?></button>
				<?php endfor; ?>
			</div>
			<div class="valt-scale-ends"><span>Not for me</span><span>Love it</span></div>
		</div>

		<div class="valt-survey-field">
			<h3>If collecting a song also earned you a small share of its future success, you'd collect&hellip;</h3>
			<div class="valt-radio-group">
				<?php foreach ( valt_survey_invest_effect_options() as $value => $label ) : ?>
					<label class="valt-radio-label">
						<input type="radio" name="invest_effect" value="<?php echo esc_attr( $value ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="valt-survey-field">
			<h3>Anything you'd love Valt to add or change?</h3>
			<p class="valt-survey-subtitle">Optional</p>
			<textarea class="valt-survey-textarea" name="feature_request" placeholder="Tell us what would make Valt better&hellip;" maxlength="5000"></textarea>
		</div>

		<div class="valt-survey-field">
			<h3>How did you find Valt?</h3>
			<div class="valt-radio-group">
				<?php foreach ( valt_survey_how_found_options() as $value => $label ) : ?>
					<label class="valt-radio-label">
						<input type="radio" name="how_found_us" value="<?php echo esc_attr( $value ); ?>">
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>

		<p class="valt-survey-inline-error" role="alert" hidden></p>
		<button type="submit" class="valt-survey-submit">Send feedback</button>

		<div class="valt-survey-inline-thanks" hidden>
			<h3>Thank you! &#127925;</h3>
			<p>You're early &mdash; your feedback shapes what Valt becomes.</p>
		</div>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'valt_survey_form', 'valt_survey_inline_form' );
