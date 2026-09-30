<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin settings page for Valt Platform v2.
 * Tabs: NMKR, Gamification.
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
	// NMKR settings. Secret fields (API keys, Pinata JWT) use a keep-on-empty
	// sanitizer: submitting a blank value preserves the saved key rather than
	// wiping it. This lets the settings screen render the field empty (never
	// echoing the secret into the page) and lets a wp-config.php constant pin
	// the value with the field disabled, without either path clearing storage.
	$keep_on_empty = function ( string $option ): callable {
		return function ( $value ) use ( $option ) {
			$value = is_string( $value ) ? trim( $value ) : '';
			return '' === $value ? get_option( $option, '' ) : $value;
		};
	};
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_mode' );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_preprod_api_key', [ 'sanitize_callback' => $keep_on_empty( 'valt_nmkr_preprod_api_key' ) ] );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_mainnet_api_key', [ 'sanitize_callback' => $keep_on_empty( 'valt_nmkr_mainnet_api_key' ) ] );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_preprod_project_uid' );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_mainnet_project_uid' );
	register_setting( 'valt_settings_nmkr', 'valt_nmkr_policy_id' );
	register_setting( 'valt_settings_nmkr', 'valt_pinata_jwt', [ 'sanitize_callback' => $keep_on_empty( 'valt_pinata_jwt' ) ] );

	// Gamification settings.
	register_setting( 'valt_settings_gamification', 'valt_points_config' );
	register_setting( 'valt_settings_gamification', 'valt_level_thresholds' );

	// Feature flags.
	register_setting( 'valt_settings_features', 'valt_feature_flags' );
} );

function valt_render_settings_page(): void {
	$tab = sanitize_text_field( $_GET['tab'] ?? 'features' );
	?>
	<div class="wrap">
		<h1>Valt Platform Settings</h1>
		<nav class="nav-tab-wrapper">
			<a href="?page=valt-settings&tab=features" class="nav-tab <?php echo $tab === 'features' ? 'nav-tab-active' : ''; ?>">Features</a>
			<a href="?page=valt-settings&tab=nmkr" class="nav-tab <?php echo $tab === 'nmkr' ? 'nav-tab-active' : ''; ?>">NMKR</a>
			<a href="?page=valt-settings&tab=gamification" class="nav-tab <?php echo $tab === 'gamification' ? 'nav-tab-active' : ''; ?>">Gamification</a>
		</nav>
		<div style="margin-top:20px;">
		<?php
		switch ( $tab ) {
			case 'nmkr':
				valt_render_nmkr_settings();
				break;
			case 'gamification':
				valt_render_gamification_settings();
				break;
			default:
				valt_render_features_settings();
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
				<td><?php valt_render_secret_field( 'valt_nmkr_preprod_api_key', valt_secret_constants( 'api_key', 'preprod' ), 'regular-text' ); ?></td>
			</tr>
			<tr>
				<th>Mainnet API Key</th>
				<td><?php valt_render_secret_field( 'valt_nmkr_mainnet_api_key', valt_secret_constants( 'api_key', 'mainnet' ), 'regular-text' ); ?></td>
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
				<td><?php valt_render_secret_field( 'valt_pinata_jwt', valt_secret_constants( 'pinata_jwt', $mode ), 'large-text', 'For IPFS uploads of NFT cover art and audio.' ); ?></td>
			</tr>
		</table>
		<?php submit_button( 'Save NMKR Settings' ); ?>
	</form>
	<?php
}

/**
 * Render a masked secret input. The saved value is NEVER echoed into the page;
 * the field shows only whether a key is stored. Submitting it blank preserves
 * the saved key (see the keep-on-empty sanitizer). When a wp-config.php constant
 * pins the secret, the field is disabled and the DB copy is ignored.
 *
 * @param string   $option    Option name.
 * @param string[] $constants Constant names that can pin this secret.
 * @param string   $class     Input CSS class.
 * @param string   $help      Optional extra help text.
 */
function valt_render_secret_field( string $option, array $constants, string $class = 'regular-text', string $help = '' ): void {
	$pinned = valt_secret_pinned( $constants );
	$stored = '' !== (string) get_option( $option, '' );
	if ( $pinned ) {
		$placeholder = 'Set in wp-config.php';
	} elseif ( $stored ) {
		$placeholder = '•••••••• saved (leave blank to keep)';
	} else {
		$placeholder = 'not set';
	}
	printf(
		'<input type="password" name="%1$s" value="" class="%2$s" autocomplete="off" placeholder="%3$s"%4$s>',
		esc_attr( $option ),
		esc_attr( $class ),
		esc_attr( $placeholder ),
		$pinned ? ' disabled' : ''
	);
	if ( $pinned ) {
		printf(
			'<p class="description">Pinned by the wp-config.php constant <code>%s</code>. This field is ignored and the database copy is not used.</p>',
			esc_html( $constants[0] )
		);
	} elseif ( $help ) {
		printf( '<p class="description">%s</p>', esc_html( $help ) );
	}
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

function valt_render_features_settings(): void {
	$flags = wp_parse_args( get_option( 'valt_feature_flags', [] ), [
		'gamification' => false,
		'campaigns'    => false,
		'leaderboard'  => false,
		'discovery'    => true,
		'nmkr'         => true,
	] );

	$features = [
		'nmkr'         => [ 'NMKR Minting',      'NFT minting via NMKR API (CIP-25). Required for M2.' ],
		'discovery'    => [ 'Artist Discovery',   'Browse/search/filter artists page.' ],
		'leaderboard'  => [ 'Leaderboard',        'Ranked fan tables. Requires gamification.' ],
		'gamification' => [ 'Gamification',       'Points, badges, levels. Phase 2 feature — disable for now.' ],
		'campaigns'    => [ 'Album Campaigns',    'Proto-tokenomics pledge system. Phase 2 feature — disable for now.' ],
	];
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'valt_settings_features' ); ?>
		<h2>Feature Flags</h2>
		<p>Enable or disable major subsystems. Disabled features hide their nav links, shortcodes, and admin pages.</p>
		<table class="form-table">
		<?php foreach ( $features as $key => [ $label, $desc ] ) : ?>
			<tr>
				<th><?php echo esc_html( $label ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="valt_feature_flags[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $flags[ $key ] ) ); ?>>
						<?php echo esc_html( $desc ); ?>
					</label>
				</td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php submit_button( 'Save Feature Flags' ); ?>
	</form>
	<?php
}
