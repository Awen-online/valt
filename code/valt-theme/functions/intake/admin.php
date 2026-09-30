<?php
/**
 * Valt Artist Intake — WP-Admin (Tools → Valt Artist Leads).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page(
		'tools.php',
		'Valt Artist Leads',
		'Valt Artist Leads',
		'manage_options',
		'valt-artist-leads',
		'valt_intake_admin_page'
	);
} );

/**
 * Settings save + CSV download (self-nonced, outside the Settings API).
 */
function valt_intake_admin_actions() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['valt_intake_save'] ) && check_admin_referer( 'valt_intake_settings' ) ) {
		update_option( 'valt_intake_settings', array(
			'enabled'         => ! empty( $_POST['enabled'] ),
			'notify_email'    => sanitize_email( $_POST['notify_email'] ?? get_option( 'admin_email' ) ),
			'success_message' => sanitize_textarea_field( $_POST['success_message'] ?? '' ),
		) );
		add_settings_error( 'valt_intake', 'saved', 'Settings saved.', 'success' );
	}
	if ( isset( $_GET['valt_intake_export'] ) && check_admin_referer( 'valt_intake_export' ) ) {
		$res  = valt_intake_rest_export();
		$data = $res->get_data();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $data['filename'] );
		echo $data['csv']; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
}
add_action( 'admin_init', 'valt_intake_admin_actions' );

/**
 * Render the leads page.
 */
function valt_intake_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$table    = $wpdb->prefix . 'valt_artist_intake';
	$settings = valt_intake_get_settings();
	$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
	$per      = 50;
	$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per, ( $paged - 1 ) * $per ) ); // phpcs:ignore
	$export   = wp_nonce_url( admin_url( 'tools.php?page=valt-artist-leads&valt_intake_export=1' ), 'valt_intake_export' );
	$pages    = (int) ceil( max( 1, $total ) / $per );
	?>
	<div class="wrap">
		<h1>Valt Artist Leads</h1>
		<?php settings_errors( 'valt_intake' ); ?>

		<h2 class="title">
			Leads (<?php echo esc_html( $total ); ?>)
			<a href="<?php echo esc_url( $export ); ?>" class="button" style="margin-left:8px">Export CSV</a>
		</h2>
		<p class="description">Prospective artists who submitted the intake form. Drop <code>[valt_artist_intake]</code> on a page and link outreach emails there.</p>

		<?php if ( empty( $rows ) ) : ?>
			<p class="description">No leads yet.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>Date</th><th>Artist</th><th>Email</th><th>Genre</th><th>Location</th><th>Wallet</th><th>Links / notes</th><th>Via</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo esc_html( gmdate( 'Y-m-d', strtotime( $r->created_at ) ) ); ?></td>
						<td><strong><?php echo esc_html( $r->artist_name ); ?></strong></td>
						<td><a href="mailto:<?php echo esc_attr( $r->email ); ?>"><?php echo esc_html( $r->email ); ?></a></td>
						<td><?php echo esc_html( $r->genre ?: '—' ); ?></td>
						<td><?php echo esc_html( $r->location ?: '—' ); ?></td>
						<td><?php echo esc_html( $r->has_wallet ?: '—' ); ?></td>
						<td><?php echo $r->links ? esc_html( $r->links ) : ''; ?><?php echo $r->message ? '<br><span style="color:#666">' . esc_html( wp_trim_words( $r->message, 20 ) ) . '</span>' : ( $r->links ? '' : '&mdash;' ); ?></td>
						<td><?php echo esc_html( $r->how_found ?: ( $r->source ?: '—' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $pages > 1 ) : ?>
				<p><?php for ( $p = 1; $p <= $pages; $p++ ) {
					echo $p === $paged
						? '<strong style="margin-right:6px">' . esc_html( $p ) . '</strong>'
						: '<a style="margin-right:6px" href="' . esc_url( admin_url( 'tools.php?page=valt-artist-leads&paged=' . $p ) ) . '">' . esc_html( $p ) . '</a>';
				} ?></p>
			<?php endif; ?>
		<?php endif; ?>

		<h2 class="title">Settings</h2>
		<form method="post">
			<?php wp_nonce_field( 'valt_intake_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Form enabled</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> Accept new artist submissions</label></td></tr>
				<tr><th scope="row">Notify email</th><td><input type="email" name="notify_email" class="regular-text" value="<?php echo esc_attr( $settings['notify_email'] ); ?>"></td></tr>
				<tr><th scope="row">Success message</th><td><textarea name="success_message" class="large-text" rows="2"><?php echo esc_textarea( $settings['success_message'] ); ?></textarea></td></tr>
			</table>
			<p><button type="submit" name="valt_intake_save" class="button button-primary">Save settings</button></p>
		</form>
	</div>
	<?php
}
