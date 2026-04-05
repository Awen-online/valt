<?php
defined( 'ABSPATH' ) || exit;

/**
 * NFT Monitor admin page for Valt Platform v2.
 * Tracks NMKR project status, minted NFTs, sales, and event log.
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

// Handle manual sync action.
add_action( 'admin_init', function () {
	if ( ! isset( $_GET['valt_sync_nmkr'] ) || ! current_user_can( 'manage_options' ) ) return;
	check_admin_referer( 'valt_sync_nmkr' );
	valt_sync_nmkr_project_stats();
	wp_redirect( admin_url( 'admin.php?page=valt-nft-monitor&synced=1' ) );
	exit;
} );

/**
 * Sync project stats from NMKR API and cache them.
 */
function valt_sync_nmkr_project_stats(): void {
	$config = valt_nmkr_config();
	if ( empty( $config['api_key'] ) || empty( $config['project_uid'] ) ) return;

	// Get project counts.
	$counts = valt_nmkr_request( 'GET', "GetCounts/{$config['project_uid']}" );
	if ( ! is_wp_error( $counts ) ) {
		update_option( 'valt_nmkr_counts', $counts, false );
	}

	// Get sold/minted NFTs.
	$sold = valt_nmkr_request( 'GET', "GetNfts/{$config['project_uid']}/sold/1/50" );
	if ( ! is_wp_error( $sold ) && is_array( $sold ) ) {
		update_option( 'valt_nmkr_sold', $sold, false );
	}

	// Get all NFTs for overview.
	$all = valt_nmkr_request( 'GET', "GetNfts/{$config['project_uid']}/all/1/50" );
	if ( ! is_wp_error( $all ) && is_array( $all ) ) {
		update_option( 'valt_nmkr_all_nfts', $all, false );
	}

	update_option( 'valt_nmkr_last_sync', current_time( 'mysql' ), false );
	valt_log_event( 'nmkr_sync', 'NMKR project stats synced' );
}

function valt_render_nft_monitor_page(): void {
	$config    = valt_nmkr_config();
	$counts    = get_option( 'valt_nmkr_counts', [] );
	$sold_nfts = get_option( 'valt_nmkr_sold', [] );
	$all_nfts  = get_option( 'valt_nmkr_all_nfts', [] );
	$last_sync = get_option( 'valt_nmkr_last_sync', 'Never' );
	$event_log = get_option( 'valt_event_log', [] );

	$nmkr_base = $config['mode'] === 'mainnet' ? 'https://pay.nmkr.io' : 'https://pay.preprod.nmkr.io';
	$studio_base = $config['mode'] === 'mainnet' ? 'https://studio.nmkr.io' : 'https://studio.preprod.nmkr.io';
	$explorer = $config['mode'] === 'mainnet' ? 'https://cardanoscan.io' : 'https://preprod.cardanoscan.io';
	$project_clean = str_replace( '-', '', $config['project_uid'] );
	$pay_url = "{$nmkr_base}/?p={$project_clean}&c=1";

	if ( isset( $_GET['synced'] ) ) {
		echo '<div class="notice notice-success"><p>NMKR project stats synced.</p></div>';
	}
	?>
	<div class="wrap">
		<h1>NFT Monitor</h1>

		<?php // ── Project Overview ──────────────────────────────────── ?>
		<div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
			<div class="card" style="flex:1; min-width:200px;">
				<h3>NMKR Project</h3>
				<table class="form-table" style="margin:0;">
					<tr><th>Mode</th><td><strong style="color:<?php echo $config['mode'] === 'mainnet' ? 'green' : 'orange'; ?>"><?php echo esc_html( strtoupper( $config['mode'] ) ); ?></strong></td></tr>
					<tr><th>Policy ID</th><td><code style="font-size:11px;word-break:break-all;"><?php echo esc_html( $config['policy_id'] ?: 'Not set' ); ?></code></td></tr>
					<tr><th>Project UID</th><td><code style="font-size:11px;"><?php echo esc_html( $config['project_uid'] ?: 'Not set' ); ?></code></td></tr>
					<tr><th>Last Sync</th><td><?php echo esc_html( $last_sync ); ?></td></tr>
				</table>
				<p style="margin-top:10px;">
					<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=valt-nft-monitor&valt_sync_nmkr=1' ), 'valt_sync_nmkr' ); ?>" class="button button-primary">Sync from NMKR</a>
					<a href="<?php echo esc_url( $studio_base ); ?>" target="_blank" class="button">Open NMKR Studio</a>
				</p>
			</div>

			<?php if ( ! empty( $counts ) ) : ?>
			<div class="card" style="flex:1; min-width:200px;">
				<h3>Collection Stats</h3>
				<table class="form-table" style="margin:0;">
					<tr><th>Total NFTs</th><td><strong><?php echo (int) ( $counts['nftTotal'] ?? 0 ); ?></strong></td></tr>
					<tr><th>Sold / Minted</th><td><strong style="color:green;"><?php echo (int) ( $counts['sold'] ?? 0 ); ?></strong></td></tr>
					<tr><th>Available</th><td><strong><?php echo (int) ( $counts['free'] ?? 0 ); ?></strong></td></tr>
					<tr><th>Reserved</th><td><?php echo (int) ( $counts['reserved'] ?? 0 ); ?></td></tr>
					<tr><th>Errors</th><td><?php echo (int) ( $counts['error'] ?? 0 ); ?></td></tr>
				</table>
			</div>

			<div class="card" style="flex:1; min-width:200px;">
				<h3>Quick Links</h3>
				<p><a href="<?php echo esc_url( $pay_url ); ?>" target="_blank" class="button">NMKR Pay Link (1 NFT)</a></p>
				<p><a href="<?php echo esc_url( "{$nmkr_base}/?p={$project_clean}&c=3" ); ?>" target="_blank" class="button">NMKR Pay Link (3 NFTs)</a></p>
				<p><a href="<?php echo esc_url( $explorer . '/tokenPolicy/' . $config['policy_id'] ); ?>" target="_blank" class="button">View on Cardanoscan</a></p>
				<p style="margin-top:10px; font-size:12px; color:#666;">
					Payment link for embedding:<br>
					<code style="font-size:11px; word-break:break-all;"><?php echo esc_html( $pay_url ); ?></code>
				</p>
			</div>
			<?php endif; ?>
		</div>

		<?php // ── Minted / Sold NFTs ────────────────────────────────── ?>
		<h2>Sold / Minted NFTs</h2>
		<?php if ( empty( $sold_nfts ) ) : ?>
			<p>No sold NFTs yet. Click "Sync from NMKR" to refresh.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr><th>Name</th><th>Buyer</th><th>Sold</th><th>TX Hash</th><th>Price</th><th>IPFS</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $sold_nfts as $nft ) :
					$tx = $nft['initialMintTxHash'] ?? '';
					$addr = $nft['receiveraddress'] ?? $nft['receiverAddress'] ?? '';
				?>
					<tr>
						<td><strong><?php echo esc_html( $nft['displayname'] ?? $nft['name'] ?? '—' ); ?></strong></td>
						<td><code style="font-size:10px;"><?php echo $addr ? esc_html( substr( $addr, 0, 16 ) . '...' ) : '—'; ?></code></td>
						<td><?php echo esc_html( $nft['selldate'] ?? '—' ); ?></td>
						<td>
							<?php if ( $tx ) : ?>
								<a href="<?php echo esc_url( $explorer . '/transaction/' . $tx ); ?>" target="_blank">
									<code style="font-size:10px;"><?php echo esc_html( substr( $tx, 0, 16 ) . '...' ); ?></code>
								</a>
							<?php else : ?>
								<em>pending</em>
							<?php endif; ?>
						</td>
						<td><?php echo $nft['price'] ? number_format( $nft['price'] / 1000000, 1 ) . ' ADA' : '—'; ?></td>
						<td>
							<?php if ( ! empty( $nft['gatewayLink'] ) ) : ?>
								<a href="<?php echo esc_url( $nft['gatewayLink'] ); ?>" target="_blank">View</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php // ── All NFTs in Project ───────────────────────────────── ?>
		<h2>All NFTs in Project</h2>
		<?php if ( empty( $all_nfts ) ) : ?>
			<p>No NFTs in project. Click "Sync from NMKR" to refresh.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr><th>Name</th><th>State</th><th>Minted</th><th>UID</th><th>NMKR Pay</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $all_nfts as $nft ) :
					$uid_clean = str_replace( '-', '', $nft['uid'] ?? '' );
					$state = $nft['state'] ?? 'unknown';
					$state_color = match( $state ) {
						'free' => '#2196F3',
						'sold' => '#4CAF50',
						'reserved' => '#FF9800',
						'error' => '#f44336',
						default => '#999',
					};
				?>
					<tr>
						<td><?php echo esc_html( $nft['displayname'] ?? $nft['name'] ?? '—' ); ?></td>
						<td><span style="color:<?php echo $state_color; ?>; font-weight:600;"><?php echo esc_html( ucfirst( $state ) ); ?></span></td>
						<td><?php echo $nft['minted'] ? 'Yes' : 'No'; ?></td>
						<td><code style="font-size:10px;"><?php echo esc_html( substr( $nft['uid'] ?? '', 0, 12 ) . '...' ); ?></code></td>
						<td>
							<?php if ( $state === 'free' && $uid_clean ) : ?>
								<a href="<?php echo esc_url( "{$nmkr_base}/?p={$project_clean}&n={$uid_clean}" ); ?>" target="_blank" class="button button-small">Pay Link</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php // ── Event Log ─────────────────────────────────────────── ?>
		<h2>Event Log (Last 50)</h2>
		<?php $recent_log = array_slice( $event_log, 0, 50 ); ?>
		<?php if ( empty( $recent_log ) ) : ?>
			<p>No events logged yet.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr><th style="width:160px;">Time</th><th style="width:140px;">Type</th><th>Message</th></tr>
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
