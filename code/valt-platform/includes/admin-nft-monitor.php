<?php
defined( 'ABSPATH' ) || exit;

/**
 * NFT Monitor admin page for Valt Platform v2.
 * Shows mint queue, event log, and retry controls.
 */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'valt-platform-docs',
		'NFT Monitor',
		'NFT Monitor',
		'manage_options',
		'valt-nft-monitor',
		'valt_render_nft_monitor_page'
	);
}, 21 );

// Handle retry action.
add_action( 'admin_init', function () {
	if ( ! isset( $_GET['valt_retry_mint'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'valt_retry_mint' );
	$song_id = (int) $_GET['valt_retry_mint'];
	update_post_meta( $song_id, 'valt_nft_status', 'pending' );
	delete_transient( "valt_nft_poll_count_{$song_id}" );
	wp_schedule_single_event( time() + 5, 'valt_mint_nft_async', [ $song_id ] );
	valt_log_event( 'mint_retry', "Admin retried mint for song {$song_id}" );
	wp_redirect( admin_url( 'admin.php?page=valt-nft-monitor&retried=' . $song_id ) );
	exit;
} );

function valt_render_nft_monitor_page(): void {
	$queue     = get_option( 'valt_nft_queue', [] );
	$event_log = get_option( 'valt_event_log', [] );
	$config    = valt_nmkr_config();

	if ( isset( $_GET['retried'] ) ) {
		echo '<div class="notice notice-success"><p>Mint retry scheduled for song #' . (int) $_GET['retried'] . '.</p></div>';
	}
	?>
	<div class="wrap">
		<h1>NFT Monitor</h1>
		<p>NMKR Mode: <strong><?php echo esc_html( $config['mode'] ); ?></strong> | Policy: <code><?php echo esc_html( $config['policy_id'] ?: 'Not set' ); ?></code></p>

		<h2>Mint Queue (<?php echo count( $queue ); ?>)</h2>
		<?php if ( empty( $queue ) ) : ?>
			<p>No pending mints.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr><th>Song</th><th>Artist</th><th>Wallet</th><th>Status</th><th>Queued</th><th>Action</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $queue as $song_id => $info ) :
					$song   = get_post( $song_id );
					$status = get_post_meta( $song_id, 'valt_nft_status', true );
					$artist_id = (int) get_post_meta( $song_id, 'artist', true );
					$artist = $artist_id ? get_the_title( $artist_id ) : '—';
				?>
					<tr>
						<td><a href="<?php echo get_edit_post_link( $song_id ); ?>"><?php echo esc_html( $song ? $song->post_title : "#{$song_id}" ); ?></a></td>
						<td><?php echo esc_html( $artist ); ?></td>
						<td><code style="font-size:11px;"><?php echo esc_html( substr( $info['wallet'] ?? '', 0, 20 ) . '...' ); ?></code></td>
						<td><span class="valt-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></span></td>
						<td><?php echo esc_html( $info['queued_at'] ?? '' ); ?></td>
						<td>
							<?php if ( $status === 'failed' ) : ?>
								<a href="<?php echo wp_nonce_url( admin_url( "admin.php?page=valt-nft-monitor&valt_retry_mint={$song_id}" ), 'valt_retry_mint' ); ?>" class="button button-small">Retry</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2>All Songs with NFT Status</h2>
		<?php
		$nft_songs = get_posts( [
			'post_type'      => 'song',
			'posts_per_page' => 50,
			'meta_query'     => [ [
				'key'     => 'valt_nft_status',
				'value'   => '',
				'compare' => '!=',
			] ],
			'orderby'        => 'modified',
			'order'          => 'DESC',
		] );
		if ( $nft_songs ) : ?>
			<table class="widefat striped">
				<thead>
					<tr><th>Song</th><th>Status</th><th>Mint Count</th><th>TX Hash</th><th>Asset ID</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $nft_songs as $song ) :
					$status  = get_post_meta( $song->ID, 'valt_nft_status', true );
					$tx      = get_post_meta( $song->ID, 'valt_nft_transaction_id', true );
					$asset   = get_post_meta( $song->ID, 'valt_nft_asset_id', true );
					$mints   = get_post_meta( $song->ID, 'valt_mint_count', true );
					$explorer = $config['mode'] === 'mainnet' ? 'https://cardanoscan.io/transaction/' : 'https://preprod.cardanoscan.io/transaction/';
				?>
					<tr>
						<td><a href="<?php echo get_edit_post_link( $song->ID ); ?>"><?php echo esc_html( $song->post_title ); ?></a></td>
						<td><strong><?php echo esc_html( $status ); ?></strong></td>
						<td><?php echo (int) $mints; ?></td>
						<td><?php if ( $tx ) : ?><a href="<?php echo esc_url( $explorer . $tx ); ?>" target="_blank"><code style="font-size:11px;"><?php echo esc_html( substr( $tx, 0, 16 ) . '...' ); ?></code></a><?php else : ?>—<?php endif; ?></td>
						<td><code style="font-size:11px;"><?php echo esc_html( $asset ? substr( $asset, 0, 24 ) . '...' : '—' ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p>No songs with NFT status yet.</p>
		<?php endif; ?>

		<h2>Event Log (Last 50)</h2>
		<?php $recent_log = array_slice( $event_log, 0, 50 ); ?>
		<?php if ( empty( $recent_log ) ) : ?>
			<p>No events logged yet.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr><th>Time</th><th>Type</th><th>Message</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $recent_log as $entry ) : ?>
					<tr>
						<td style="white-space:nowrap;"><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
						<td><code><?php echo esc_html( $entry['type'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
