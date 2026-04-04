<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page for Valt Platform v2.
 * Tabs: NMKR, Stripe, Gamification.
 */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'valt-platform-docs',
		'Valt Settings',
		'Settings',
		'manage_options',
		'valt-settings',
		'valt_render_settings_page'
	);
}, 20 );

// Register settings.
add_action( 'admin_init', function () {
	// NMKR settings.
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_mode' );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_preprod_api_key' );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_mainnet_api_key' );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_preprod_project_uid' );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_mainnet_project_uid' );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_policy_id' );
	register_setting( 'valt_settings_nmkr', 'valt_pinata_jwt' );

	// Stripe settings.
	register_setting( 'valt_settings_stripe', 'valt_stripe_mode' );

	// Gamification settings.
	register_setting( 'valt_settings_gamification', 'valt_points_config' );
	register_setting( 'valt_settings_gamification', 'valt_level_thresholds' );
} );

function valt_render_settings_page(): void {
	$tab = sanitize_text_field( $_GET['tab'] ?? 'nmkr' );
	?>
	<div class="wrap">
		<h1>Valt Platform Settings</h1>
		<nav class="nav-tab-wrapper">
			<a href="?page=valt-settings&tab=nmkr" class="nav-tab <?php echo $tab === 'nmkr' ? 'nav-tab-active' : ''; ?>">NMKR</a>
			<a href="?page=valt-settings&tab=stripe" class="nav-tab <?php echo $tab === 'stripe' ? 'nav-tab-active' : ''; ?>">Stripe</a>
			<a href="?page=valt-settings&tab=gamification" class="nav-tab <?php echo $tab === 'gamification' ? 'nav-tab-active' : ''; ?>">Gamification</a>
		</nav>
		<div style="margin-top:20px;">
		<?php
		switch ( $tab ) {
			case 'stripe':
				valt_render_stripe_settings();
				break;
			case 'gamification':
				valt_render_gamification_settings();
				break;
			default:
				valt_render_nmkr_settings();
		}
		?>
		</div>
	</div>
	<?php
}

function valt_render_nmkr_settings(): void {
	$mode = get_option( 'valt_nmkr_mode', 'preprod' );
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'valt_settings_nmkr' ); ?>
		<table class="form-table">
			<tr>
				<th>Environment</th>
				<td>
					<select name="valt_nmkr_mode">
						<option value="preprod" <?php selected( $mode, 'preprod' ); ?>>Preprod (Testnet)</option>
						<option value="mainnet" <?php selected( $mode, 'mainnet' ); ?>>Mainnet</option>
					</select>
				</td>
			</tr>
			<tr>
				<th>Preprod API Key</th>
				<td><input type="password" name="valt_nmkr_preprod_api_key" value="<?php echo esc_attr( get_option( 'valt_nmkr_preprod_api_key' ) ); ?>" class="regular-text" autocomplete="off">
				<?php if ( defined( 'VALT_NMKR_API_KEY' ) ) : ?><p class="description">Constant VALT_NMKR_API_KEY is set in wp-config.php (used as fallback).</p><?php endif; ?>
				</td>
			</tr>
			<tr>
				<th>Mainnet API Key</th>
				<td><input type="password" name="valt_nmkr_mainnet_api_key" value="<?php echo esc_attr( get_option( 'valt_nmkr_mainnet_api_key' ) ); ?>" class="regular-text" autocomplete="off"></td>
			</tr>
			<tr>
				<th>Preprod Project UID</th>
				<td><input type="text" name="valt_nmkr_preprod_project_uid" value="<?php echo esc_attr( get_option( 'valt_nmkr_preprod_project_uid' ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th>Mainnet Project UID</th>
				<td><input type="text" name="valt_nmkr_mainnet_project_uid" value="<?php echo esc_attr( get_option( 'valt_nmkr_mainnet_project_uid' ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th>Policy ID (CIP25)</th>
				<td><input type="text" name="valt_nmkr_policy_id" value="<?php echo esc_attr( get_option( 'valt_nmkr_policy_id' ) ); ?>" class="large-text">
				<p class="description">Cardano native token policy ID. Must use CIP-25 standard (not CIP-68).</p></td>
			</tr>
			<tr>
				<th>Pinata JWT</th>
				<td><input type="password" name="valt_pinata_jwt" value="<?php echo esc_attr( get_option( 'valt_pinata_jwt' ) ); ?>" class="large-text" autocomplete="off">
				<p class="description">For IPFS uploads of NFT cover art and audio.</p></td>
			</tr>
		</table>
		<?php submit_button( 'Save NMKR Settings' ); ?>
	</form>
	<?php
}

function valt_render_stripe_settings(): void {
	$mode   = get_option( 'valt_stripe_mode', 'test' );
	$config = valt_stripe_config();
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'valt_settings_stripe' ); ?>
		<table class="form-table">
			<tr>
				<th>Environment</th>
				<td>
					<select name="valt_stripe_mode">
						<option value="test" <?php selected( $mode, 'test' ); ?>>Test</option>
						<option value="live" <?php selected( $mode, 'live' ); ?>>Live</option>
					</select>
				</td>
			</tr>
			<tr>
				<th>Secret Key</th>
				<td><code><?php echo $config['secret_key'] ? '***' . substr( $config['secret_key'], -8 ) : 'Not set'; ?></code>
				<p class="description">Set via <code>VALT_STRIPE_SECRET_KEY</code> constant in wp-config.php.</p></td>
			</tr>
			<tr>
				<th>Publishable Key</th>
				<td><code><?php echo esc_html( $config['publishable_key'] ?: 'Not set' ); ?></code>
				<p class="description">Set via <code>VALT_STRIPE_PUBLISHABLE_KEY</code> in wp-config.php.</p></td>
			</tr>
			<tr>
				<th>Webhook URL</th>
				<td><code><?php echo esc_html( rest_url( 'valt/v1/stripe/webhook' ) ); ?></code>
				<p class="description">Add this URL in your Stripe Dashboard > Webhooks. Listen for <code>checkout.session.completed</code>.</p></td>
			</tr>
		</table>
		<?php submit_button( 'Save Stripe Settings' ); ?>
	</form>
	<?php
}

function valt_render_gamification_settings(): void {
	$config = valt_points_config();
	$levels = valt_level_thresholds();
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'valt_settings_gamification' ); ?>
		<h2>Points Per Action</h2>
		<table class="form-table">
		<?php foreach ( $config as $action => $pts ) : ?>
			<tr>
				<th><?php echo esc_html( ucwords( str_replace( '_', ' ', $action ) ) ); ?></th>
				<td><input type="number" name="valt_points_config[<?php echo esc_attr( $action ); ?>]" value="<?php echo (int) $pts; ?>" min="0" style="width:80px;"></td>
			</tr>
		<?php endforeach; ?>
		</table>

		<h2>Level Thresholds</h2>
		<table class="form-table">
		<?php foreach ( $levels as $num => $level ) : ?>
			<tr>
				<th>Level <?php echo (int) $num; ?></th>
				<td>
					<input type="text" name="valt_level_thresholds[<?php echo (int) $num; ?>][name]" value="<?php echo esc_attr( $level['name'] ); ?>" style="width:120px;" placeholder="Name">
					<input type="number" name="valt_level_thresholds[<?php echo (int) $num; ?>][threshold]" value="<?php echo (int) $level['threshold']; ?>" min="0" style="width:80px;" placeholder="Points">
				</td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php submit_button( 'Save Gamification Settings' ); ?>
	</form>
	<?php
}
