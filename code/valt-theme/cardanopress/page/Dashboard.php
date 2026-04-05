<?php
/**
 * Wallet Dashboard — Valt design system.
 * Shows wallet info, ADA balance, collection, and account details.
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$userProfile = cardanoPress()->userProfile();
$is_connected = $userProfile->isConnected();
$wallet_addr  = $is_connected ? $userProfile->connectedWallet() : '';
$stake_addr   = $is_connected ? $userProfile->connectedStake() : '';
$network      = $is_connected ? $userProfile->connectedNetwork() : '';
$account_info = $is_connected ? $userProfile->getAccountInfo() : [];
$assets       = $is_connected ? $userProfile->storedAssets() : [];
$handles      = $is_connected ? $userProfile->storedHandles() : [];
$transactions = $is_connected ? $userProfile->allTransactions() : [];

// Get the NMKR policy ID to identify Valt NFTs in the wallet.
$valt_policy = '';
if ( function_exists( 'valt_nmkr_config' ) ) {
	$config = valt_nmkr_config();
	$valt_policy = $config['policy_id'] ?? '';
}

// Separate Valt NFTs from other assets.
$valt_nfts   = [];
$other_assets = [];
foreach ( $assets as $asset ) {
	$pid = $asset['policy_id'] ?? '';
	if ( $valt_policy && $pid === $valt_policy ) {
		$valt_nfts[] = $asset;
	} else {
		$other_assets[] = $asset;
	}
}
?>

<div class="valt-site">
	<?php valt_render_nav(); ?>

	<main class="valt-main">
		<div class="valt-container">

			<template x-if="!isConnected">
				<div class="valt-wallet-prompt">
					<div class="valt-wallet-prompt__icon">
						<?php echo valt_svg_logo_animated( 100 ); ?>
					</div>
					<h1>Connect Your Wallet</h1>
					<p>Link your Cardano wallet to access your Valt, view your collection, and collect music NFTs.</p>
					<?php cardanoPress()->template('part/modal-trigger', ['text' => 'Connect Wallet']); ?>
				</div>
			</template>

			<template x-if="isConnected">
				<div>
					<?php cardanoPress()->template('welcome-banner'); ?>

					<?php // ── Wallet Overview ──────────────────────────── ?>
					<div class="valt-dash-overview">
						<div class="valt-dash-stat">
							<span class="valt-dash-stat__label">Balance</span>
							<span class="valt-dash-stat__value">
								<?php
								$balance = '';
								if ( $wallet_addr ) {
									$balance = do_shortcode( '[cardanopress_wallet_balance address="' . esc_attr( $wallet_addr ) . '" unit="ada"]' );
								}
								echo $balance !== '' ? $balance : '0';
								?>
								<small>ADA</small>
							</span>
						</div>
						<div class="valt-dash-stat">
							<span class="valt-dash-stat__label">Valt NFTs</span>
							<span class="valt-dash-stat__value"><?php echo count( $valt_nfts ); ?></span>
						</div>
						<div class="valt-dash-stat">
							<span class="valt-dash-stat__label">Total Assets</span>
							<span class="valt-dash-stat__value"><?php echo count( $assets ); ?></span>
						</div>
						<div class="valt-dash-stat">
							<span class="valt-dash-stat__label">Network</span>
							<span class="valt-dash-stat__value valt-dash-stat__value--sm"><?php echo esc_html( ucfirst( $network ) ); ?></span>
						</div>
					</div>

					<?php // ── Wallet Details Card ──────────────────────── ?>
					<div class="valt-card valt-dash-wallet">
						<h3><?php echo valt_svg_wallet( 18 ); ?> Wallet Details</h3>
						<table class="valt-dash-table">
							<tr>
								<td class="valt-dash-table__label">Extension</td>
								<td><span x-text="connectedExtension"></span></td>
							</tr>
							<tr>
								<td class="valt-dash-table__label">Address</td>
								<td><code class="valt-dash-addr"><?php echo esc_html( $wallet_addr ); ?></code></td>
							</tr>
							<tr>
								<td class="valt-dash-table__label">Stake</td>
								<td><code class="valt-dash-addr"><?php echo esc_html( $stake_addr ); ?></code></td>
							</tr>
							<?php if ( ! empty( $handles ) ) : ?>
							<tr>
								<td class="valt-dash-table__label">ADA Handle</td>
								<td>
									<?php $fav = $userProfile->getFavoriteHandle();
									echo esc_html( $fav ?: ( is_array( $handles ) ? implode( ', ', array_slice( $handles, 0, 3 ) ) : '' ) ); ?>
								</td>
							</tr>
							<?php endif; ?>
							<?php if ( ! empty( $account_info['pool_id'] ) ) : ?>
							<tr>
								<td class="valt-dash-table__label">Delegated to</td>
								<td><code class="valt-dash-addr"><?php echo esc_html( $account_info['pool_id'] ); ?></code></td>
							</tr>
							<?php endif; ?>
						</table>
						<div class="valt-dash-wallet__actions">
							<?php cardanoPress()->template('part/asset-sync'); ?>
							<?php cardanoPress()->template('part/modal-trigger', ['text' => 'Reconnect']); ?>
						</div>
					</div>

					<?php // ── Valt Collection ──────────────────────────── ?>
					<div class="valt-section">
						<div class="valt-section-header">
							<h2><?php echo valt_svg_music( 22 ); ?> Your Valt Collection</h2>
							<?php if ( empty( $valt_nfts ) && empty( $assets ) ) : ?>
								<p>No NFTs found. <a href="<?php echo home_url( '/discover/' ); ?>">Discover artists</a> and start collecting.</p>
							<?php elseif ( empty( $valt_nfts ) ) : ?>
								<p>No Valt music NFTs yet. Your wallet has <?php echo count( $assets ); ?> other asset(s).</p>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $valt_nfts ) ) : ?>
						<div class="valt-collection-grid">
							<?php foreach ( $valt_nfts as $asset ) : ?>
								<?php cardanoPress()->template('part/collection-item', compact('asset')); ?>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>

						<?php if ( ! empty( $other_assets ) ) : ?>
						<details class="valt-dash-other-assets">
							<summary class="valt-btn valt-btn--ghost">
								Show <?php echo count( $other_assets ); ?> other wallet asset(s)
							</summary>
							<div class="valt-collection-grid" style="margin-top:1rem;">
								<?php foreach ( $other_assets as $asset ) : ?>
									<?php cardanoPress()->template('part/collection-item', compact('asset')); ?>
								<?php endforeach; ?>
							</div>
						</details>
						<?php endif; ?>
					</div>

					<?php // ── Recent Transactions ──────────────────────── ?>
					<?php if ( ! empty( $transactions ) ) : ?>
					<div class="valt-section">
						<h2>Recent Transactions</h2>
						<table class="valt-table">
							<thead><tr><th>Network</th><th>Action</th><th>TX Hash</th></tr></thead>
							<tbody>
							<?php
							$explorer_base = $network === 'mainnet' ? 'https://cardanoscan.io/transaction/' : 'https://preprod.cardanoscan.io/transaction/';
							$recent = array_slice( $transactions, 0, 10 );
							foreach ( $recent as $tx ) :
								$hash = is_array( $tx ) ? ( $tx['hash'] ?? $tx[2] ?? '' ) : '';
								$action = is_array( $tx ) ? ( $tx['action'] ?? $tx[1] ?? '' ) : '';
								$net = is_array( $tx ) ? ( $tx['network'] ?? $tx[0] ?? '' ) : '';
							?>
								<tr>
									<td><?php echo esc_html( $net ); ?></td>
									<td><?php echo esc_html( $action ); ?></td>
									<td>
										<?php if ( $hash ) : ?>
										<a href="<?php echo esc_url( $explorer_base . $hash ); ?>" target="_blank" rel="noopener">
											<code><?php echo esc_html( substr( $hash, 0, 16 ) . '...' ); ?></code>
											<?php echo valt_svg_external( 12 ); ?>
										</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php endif; ?>

					<?php the_content(); ?>
				</div>
			</template>

		</div>
	</main>

	<?php valt_render_footer(); ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
