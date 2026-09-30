<?php
/**
 * Valt Feedback Survey — WP-Admin results & settings page.
 *
 * Tools → Valt Feedback: NPS summary, use-case breakdown, feature requests,
 * recent responses, a CSV export, and the enable/trigger settings.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page(
		'tools.php',
		'Valt Feedback',
		'Valt Feedback',
		'manage_options',
		'valt-feedback',
		'valt_survey_admin_page'
	);
} );

/**
 * Persist settings + handle CSV export (self-nonced, outside the Settings API).
 */
function valt_survey_admin_handle_actions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Save settings.
	if ( isset( $_POST['valt_survey_save'] ) && check_admin_referer( 'valt_survey_settings' ) ) {
		update_option( 'valt_survey_settings', array(
			'survey_enabled' => ! empty( $_POST['survey_enabled'] ),
			'visit_count'    => max( 1, (int) ( $_POST['visit_count'] ?? 3 ) ),
			'time_on_site'   => max( 30, (int) ( $_POST['time_on_site'] ?? 300 ) ),
			'post_mint'      => ! empty( $_POST['post_mint'] ),
		) );
		add_settings_error( 'valt_survey', 'saved', 'Settings saved.', 'success' );
	}

	// CSV export (streamed download).
	if ( isset( $_GET['valt_survey_export'] ) && check_admin_referer( 'valt_survey_export' ) ) {
		$res = valt_survey_rest_export();
		$data = $res->get_data();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $data['filename'] );
		echo $data['csv']; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
}
add_action( 'admin_init', 'valt_survey_admin_handle_actions' );

/**
 * Render the admin page.
 */
function valt_survey_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings   = valt_survey_get_settings();
	$nps        = valt_survey_nps_stats();
	$use_cases  = valt_survey_use_case_breakdown();
	$data       = valt_survey_get_responses( max( 1, (int) ( $_GET['paged'] ?? 1 ) ), 50 );
	$labels     = valt_survey_use_case_options();
	$cv_labels  = valt_survey_collect_value_options();
	$ie_labels  = valt_survey_invest_effect_options();
	$fractional = valt_survey_fractional_stats();
	$cv_break   = valt_survey_single_breakdown( 'collect_value' );
	$ie_break   = valt_survey_single_breakdown( 'invest_effect' );
	$export_url = wp_nonce_url( admin_url( 'tools.php?page=valt-feedback&valt_survey_export=1' ), 'valt_survey_export' );
	?>
	<div class="wrap">
		<h1>Valt Feedback</h1>
		<?php settings_errors( 'valt_survey' ); ?>

		<h2 class="title">Net Promoter Score</h2>
		<table class="widefat striped" style="max-width:640px">
			<tbody>
				<tr><td><strong>NPS</strong></td><td><?php echo null === $nps['nps'] ? '&mdash;' : esc_html( $nps['nps'] ); ?> <span class="description">(-100 to 100)</span></td></tr>
				<tr><td>Average score</td><td><?php echo null === $nps['average'] ? '&mdash;' : esc_html( $nps['average'] ); ?> / 10</td></tr>
				<tr><td>Responses with a score</td><td><?php echo esc_html( $nps['total'] ); ?></td></tr>
				<tr><td>Promoters (9&ndash;10)</td><td><?php echo esc_html( $nps['promoters'] ); ?></td></tr>
				<tr><td>Passives (7&ndash;8)</td><td><?php echo esc_html( $nps['passives'] ); ?></td></tr>
				<tr><td>Detractors (0&ndash;6)</td><td><?php echo esc_html( $nps['detractors'] ); ?></td></tr>
			</tbody>
		</table>

		<h2 class="title">What brought people to Valt</h2>
		<?php if ( empty( $use_cases ) ) : ?>
			<p class="description">No responses yet.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:640px">
				<tbody>
				<?php foreach ( $use_cases as $key => $count ) : ?>
					<tr><td><?php echo esc_html( $labels[ $key ] ?? $key ); ?></td><td><?php echo esc_html( $count ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2 class="title">Product &amp; tokenomics signal</h2>
		<p class="description" style="max-width:640px">Distilled from the fractional-ownership research instrument &mdash; how fans on the platform actually feel about the whitepaper direction.</p>
		<table class="widefat striped" style="max-width:640px">
			<tbody>
				<tr>
					<td><strong>Fractional-ownership sentiment</strong><br><span class="description">Early fans sharing a slice of a song's earnings (1 = not for me &hellip; 5 = love it)</span></td>
					<td><?php echo null === $fractional['average'] ? '&mdash;' : esc_html( $fractional['average'] ); ?> / 5 <span class="description">(<?php echo esc_html( $fractional['total'] ); ?> answered)</span></td>
				</tr>
			</tbody>
		</table>

		<h3>When collecting, what matters most</h3>
		<?php if ( empty( $cv_break ) ) : ?>
			<p class="description">No responses yet.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:640px"><tbody>
			<?php foreach ( $cv_break as $key => $count ) : ?>
				<tr><td><?php echo esc_html( $cv_labels[ $key ] ?? $key ); ?></td><td><?php echo esc_html( $count ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h3>If collecting earned a share of future success, they'd collect&hellip;</h3>
		<?php if ( empty( $ie_break ) ) : ?>
			<p class="description">No responses yet.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:640px"><tbody>
			<?php foreach ( $ie_break as $key => $count ) : ?>
				<tr><td><?php echo esc_html( $ie_labels[ $key ] ?? $key ); ?></td><td><?php echo esc_html( $count ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2 class="title">
			Responses (<?php echo esc_html( $data['total'] ); ?>)
			<a href="<?php echo esc_url( $export_url ); ?>" class="button" style="margin-left:8px">Export CSV</a>
		</h2>
		<?php if ( empty( $data['responses'] ) ) : ?>
			<p class="description">No survey responses yet. They'll appear here as people respond.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr><th>Date</th><th>NPS</th><th>Values most</th><th>Fractional</th><th>Invest effect</th><th>Brought them</th><th>Feature request</th><th>Found via</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $data['responses'] as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r->created_at ); ?></td>
						<td><?php echo null === $r->nps_score ? '&mdash;' : esc_html( $r->nps_score ); ?></td>
						<td><?php echo isset( $r->collect_value ) && $r->collect_value ? esc_html( $cv_labels[ $r->collect_value ] ?? $r->collect_value ) : '&mdash;'; ?></td>
						<td><?php echo isset( $r->fractional_sentiment ) && null !== $r->fractional_sentiment ? esc_html( $r->fractional_sentiment ) . '/5' : '&mdash;'; ?></td>
						<td><?php echo isset( $r->invest_effect ) && $r->invest_effect ? esc_html( $ie_labels[ $r->invest_effect ] ?? $r->invest_effect ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( $r->use_case ?: '—' ); ?></td>
						<td><?php echo $r->feature_request ? esc_html( $r->feature_request ) : '&mdash;'; ?></td>
						<td><?php echo esc_html( $r->how_found_us ?: '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $data['total_pages'] > 1 ) : ?>
				<p>
					<?php
					for ( $p = 1; $p <= $data['total_pages']; $p++ ) {
						$url = admin_url( 'tools.php?page=valt-feedback&paged=' . $p );
						echo $p === $data['page']
							? '<strong style="margin-right:6px">' . esc_html( $p ) . '</strong>'
							: '<a href="' . esc_url( $url ) . '" style="margin-right:6px">' . esc_html( $p ) . '</a>';
					}
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<h2 class="title">Settings</h2>
		<form method="post">
			<?php wp_nonce_field( 'valt_survey_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Enabled</th>
					<td><label><input type="checkbox" name="survey_enabled" value="1" <?php checked( ! empty( $settings['survey_enabled'] ) ); ?>> Show the feedback survey on the site</label></td>
				</tr>
				<tr>
					<th scope="row">Show after N visits</th>
					<td><input type="number" name="visit_count" min="1" value="<?php echo esc_attr( $settings['visit_count'] ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th scope="row">…or after seconds on site</th>
					<td><input type="number" name="time_on_site" min="30" value="<?php echo esc_attr( $settings['time_on_site'] ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th scope="row">…or right after a mint</th>
					<td><label><input type="checkbox" name="post_mint" value="1" <?php checked( ! empty( $settings['post_mint'] ) ); ?>> Trigger when a collect/mint succeeds</label></td>
				</tr>
			</table>
			<p><button type="submit" name="valt_survey_save" class="button button-primary">Save settings</button></p>
		</form>
	</div>
	<?php
}
