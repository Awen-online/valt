<?php
/**
 * Seed script: Insert Cullah and Mie artist profiles, albums, and songs.
 *
 * Usage: Visit /wp-admin/ while logged in as admin, then navigate to:
 *   wp-admin/admin.php?page=valt-seed-data&confirm=yes
 *
 * This will create:
 *   - 2 Artist CPTs (Cullah, Mie)
 *   - 2 Album CPTs (Cú Chulainn, The Cave EP)
 *   - 10 Song CPTs with all metadata
 *
 * Safe to run multiple times — checks for existing posts by title.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_submenu_page(
		'valt-platform-docs',
		'Seed Data',
		'Seed Data',
		'manage_options',
		'valt-seed-data',
		'valt_seed_data_page'
	);
}, 99 );

function valt_seed_data_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	$confirm = sanitize_text_field( $_GET['confirm'] ?? '' );

	if ( $confirm !== 'yes' ) {
		echo '<div class="wrap"><h1>Seed Demo Data</h1>';
		echo '<p>This will create 2 artists (Cullah, Mie), 2 albums, and 10 songs with full metadata.</p>';
		echo '<p><strong>Safe to run multiple times</strong> — skips existing posts.</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=valt-seed-data&confirm=yes' ) ) . '" class="button button-primary">Run Seed</a></p>';
		echo '</div>';
		return;
	}

	echo '<div class="wrap"><h1>Seeding Data...</h1><pre>';

	// ─── Create Artists ──────────────────────────────────────────────

	$cullah_id = valt_seed_find_or_create( 'artist', 'Cullah', [
		'post_content' => 'Sonic Autobiographer. 17 studio albums of genre-defying music — hip-hop, folk, rock, electronica. Creative Commons licensed. Milwaukee, WI.',
	] );
	update_post_meta( $cullah_id, 'bio', 'Sonic Autobiographer. 17 studio albums of genre-defying music — hip-hop, folk, rock, electronica. Creative Commons licensed. Milwaukee, WI.' );
	update_post_meta( $cullah_id, 'genre', 'Hip-Hop / Folk / Rock' );
	update_post_meta( $cullah_id, 'country', 'United States' );
	update_post_meta( $cullah_id, 'valt_social_x', 'caborecords' );
	update_post_meta( $cullah_id, 'valt_social_instagram', 'cullah' );
	update_post_meta( $cullah_id, 'valt_social_spotify', 'https://open.spotify.com/artist/4jgvPxSOf35LRGJ5XMISIA' );
	update_post_meta( $cullah_id, 'valt_featured', 1 );
	echo "Artist: Cullah (ID: {$cullah_id})\n";

	$mie_id = valt_seed_find_or_create( 'artist', 'Mie', [
		'post_content' => 'Dark electronic and alternative artist. The Cave EP released Samhain 2025.',
	] );
	update_post_meta( $mie_id, 'bio', 'Dark electronic and alternative artist. The Cave EP released Samhain 2025.' );
	update_post_meta( $mie_id, 'genre', 'Dark Electronic / Alternative' );
	update_post_meta( $mie_id, 'country', 'United States' );
	update_post_meta( $mie_id, 'valt_social_x', 'therealmie' );
	update_post_meta( $mie_id, 'valt_social_instagram', 'therealmie' );
	update_post_meta( $mie_id, 'valt_featured', 1 );
	echo "Artist: Mie (ID: {$mie_id})\n";

	// ─── Create Albums ───────────────────────────────────────────────

	$cuchulainn_id = valt_seed_find_or_create( 'album', 'Cú Chulainn', [
		'post_content' => 'Cullah\'s 17th studio album. Inspired by the legendary Irish Ulster hero, connecting mythological themes to modern struggles with technology, information, and politics. Released October 17, 2025. Creative Commons Attribution licensed.',
		'post_author'  => valt_seed_get_author( $cullah_id ),
	] );
	update_post_meta( $cuchulainn_id, 'artist', $cullah_id );
	echo "Album: Cú Chulainn (ID: {$cuchulainn_id})\n";

	$cave_id = valt_seed_find_or_create( 'album', 'The Cave EP', [
		'post_content' => 'Mie\'s debut EP. Released on Samhain (November 1), 2025. Dark electronic textures with alternative sensibilities.',
		'post_author'  => valt_seed_get_author( $mie_id ),
	] );
	update_post_meta( $cave_id, 'artist', $mie_id );

	// Set up The Cave EP as a demo campaign.
	update_post_meta( $cave_id, 'valt_campaign_active', 1 );
	update_post_meta( $cave_id, 'valt_campaign_goal', 5000 );
	update_post_meta( $cave_id, 'valt_campaign_deadline', '2026-06-01' );
	update_post_meta( $cave_id, 'valt_campaign_description', 'Help fund the full-length follow-up to The Cave EP. Your pledged points signal demand and shape the direction of the album.' );
	echo "Album: The Cave EP (ID: {$cave_id}) — campaign active\n";

	// ─── Create Songs: Cú Chulainn ──────────────────────────────────

	$cullah_songs = [
		[ 'title' => 'Setanta\'s Creed',        'track' => 1, 'duration' => '1:57' ],
		[ 'title' => 'The Gift of Emer',         'track' => 2, 'duration' => '4:15' ],
		[ 'title' => 'Nowhere (I Call Home)',     'track' => 3, 'duration' => '4:38' ],
		[ 'title' => 'Warp Spasm',               'track' => 4, 'duration' => '3:27' ],
		[ 'title' => 'Danse L\'Intervention',    'track' => 5, 'duration' => '7:18' ],
	];

	foreach ( $cullah_songs as $s ) {
		$song_id = valt_seed_find_or_create( 'song', $s['title'], [
			'post_author' => valt_seed_get_author( $cullah_id ),
		] );
		update_post_meta( $song_id, 'artist', $cullah_id );
		update_post_meta( $song_id, 'album', $cuchulainn_id );
		update_post_meta( $song_id, 'track_number', $s['track'] );
		update_post_meta( $song_id, 'duration', $s['duration'] );
		update_post_meta( $song_id, 'valt_release_status', 1 );
		update_post_meta( $song_id, 'valt_mint_count', 0 );
		update_post_meta( $song_id, 'valt_nft_price_usd', 300 ); // $3.00
		update_post_meta( $song_id, 'valt_nft_price_ada', '5' );
		update_post_meta( $song_id, 'valt_nft_max_supply', 100 );
		echo "  Song: {$s['title']} (ID: {$song_id}, track {$s['track']})\n";
	}

	// ─── Create Songs: The Cave EP ───────────────────────────────────

	$mie_songs = [
		[ 'title' => 'Ice',        'track' => 1, 'duration' => '' ],
		[ 'title' => 'Dead End',   'track' => 2, 'duration' => '' ],
		[ 'title' => 'Freakshow', 'track' => 3, 'duration' => '' ],
		[ 'title' => 'Masochist', 'track' => 4, 'duration' => '' ],
		[ 'title' => 'Beauty',    'track' => 5, 'duration' => '' ],
	];

	foreach ( $mie_songs as $s ) {
		$song_id = valt_seed_find_or_create( 'song', $s['title'], [
			'post_author' => valt_seed_get_author( $mie_id ),
		] );
		update_post_meta( $song_id, 'artist', $mie_id );
		update_post_meta( $song_id, 'album', $cave_id );
		update_post_meta( $song_id, 'track_number', $s['track'] );
		if ( $s['duration'] ) {
			update_post_meta( $song_id, 'duration', $s['duration'] );
		}
		update_post_meta( $song_id, 'valt_release_status', 1 );
		update_post_meta( $song_id, 'valt_mint_count', 0 );
		update_post_meta( $song_id, 'valt_nft_price_usd', 200 ); // $2.00
		update_post_meta( $song_id, 'valt_nft_price_ada', '3' );
		update_post_meta( $song_id, 'valt_nft_max_supply', 50 );
		echo "  Song: {$s['title']} (ID: {$song_id}, track {$s['track']})\n";
	}

	echo "\n✓ Seed complete. 2 artists, 2 albums, 10 songs.\n";
	echo '</pre></div>';
}

// ─── Helpers ─────────────────────────────────────────────────────────

/**
 * Find an existing post by type + title, or create it.
 */
function valt_seed_find_or_create( string $post_type, string $title, array $extra = [] ): int {
	$existing = get_posts( [
		'post_type'      => $post_type,
		'title'          => $title,
		'posts_per_page' => 1,
		'post_status'    => 'any',
	] );

	if ( ! empty( $existing ) ) {
		// Update existing.
		$id = $existing[0]->ID;
		if ( ! empty( $extra ) ) {
			wp_update_post( array_merge( $extra, [ 'ID' => $id ] ) );
		}
		return $id;
	}

	return wp_insert_post( array_merge( [
		'post_type'   => $post_type,
		'post_title'  => $title,
		'post_status' => 'publish',
	], $extra ) );
}

/**
 * Get the post_author from an artist CPT (for linking songs/albums to the same WP user).
 */
function valt_seed_get_author( int $artist_id ): int {
	$artist = get_post( $artist_id );
	return $artist ? (int) $artist->post_author : get_current_user_id();
}

// ─── Page Seeder ─────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
	add_submenu_page(
		'valt-platform-docs',
		'Seed Pages',
		'Seed Pages',
		'manage_options',
		'valt-seed-pages',
		'valt_seed_pages_page'
	);
}, 100 );

function valt_seed_pages_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
	$confirm = sanitize_text_field( $_GET['confirm'] ?? '' );
	if ( $confirm !== 'yes' ) {
		echo '<div class="wrap"><h1>Seed Site Pages</h1>';
		echo '<p>Creates/updates all VALT pages with shortcode content. Sets the "Valt Full Page" template. Removes Elementor data from replaced pages.</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=valt-seed-pages&confirm=yes' ) ) . '" class="button button-primary">Run Page Seed</a></p></div>';
		return;
	}

	echo '<div class="wrap"><h1>Seeding Pages...</h1><pre>';

	$video_url = 'https://objects-us-east-1.dream.io/website-backup-wsc/awen/20241231_0855_Treasure%20Behind%20Vinyl_storyboard_01jgekekhzfn18gb7hhajfjra3.mp4';

	$pages = [
		'Home' => [
			'slug'    => 'home',
			'content' => '<h2 class="text-center">Collect The Sounds & Support The Artists</h2>

[valt_trending_artists limit="6"]

<h2>Latest Releases</h2>
[valt_song_grid limit="12" columns="3"]

<h2>Active Campaigns</h2>
[valt_active_campaigns limit="4"]',
			'hero'    => [
				'_valt_hero'          => '1',
				'_valt_hero_video'    => $video_url,
				'_valt_hero_title'    => 'VALT',
				'_valt_hero_subtitle' => 'Digital Music Collectables',
				'_valt_hero_cta'      => 'Explore',
				'_valt_hero_cta_url'  => home_url( '/discover/' ),
			],
		],
		'Discover' => [
			'slug'    => 'discover',
			'content' => '<h1>Discover Artists</h1>
[valt_discover_artists per_page="12" show_filters="yes"]',
		],
		'Leaderboard' => [
			'slug'    => 'leaderboard',
			'content' => '<h1>Leaderboard</h1>
<p>Top fans across the VALT platform.</p>

<div class="valt-tabs">
<button class="valt-tab-btn valt-tab-btn--active" data-tab="global">All Time</button>
<button class="valt-tab-btn" data-tab="monthly">This Month</button>
</div>
<div class="valt-tab-panel valt-tab-panel--active" data-panel="global">
[valt_leaderboard scope="global" limit="50"]
</div>
<div class="valt-tab-panel" data-panel="monthly">
[valt_leaderboard scope="monthly" limit="50"]
</div>',
		],
		'Campaigns' => [
			'slug'    => 'campaigns',
			'content' => '<h1>Album Campaigns</h1>
<p>Back the music you believe in. Pledge your points to fund upcoming albums.</p>
[valt_active_campaigns limit="12"]',
		],
		'Fan Dashboard' => [
			'slug'    => 'fan-dashboard',
			'content' => '<h1>Your Dashboard</h1>
[valt_fan_dashboard]',
		],
		'Contact' => [
			'slug'    => 'contact',
			'content' => '<h1>Contact</h1>
<p>Get in touch with the VALT team.</p>
[valt_contact_form]',
		],
		'Terms & Conditions' => [
			'slug'    => 'terms',
			'content' => '<h1>Terms & Conditions</h1>
<p><em>Last Updated: September 28, 2024</em></p>

<h2>1. Definitions</h2>
<p><strong>Valt</strong> refers to the digital platform operated by Awen LLC. <strong>NFT</strong> refers to Non-Fungible Tokens on the Cardano blockchain. <strong>User</strong> refers to any person accessing or using Valt.</p>

<h2>2. Eligibility</h2>
<p>You must be at least 18 years old and comply with all applicable laws to use Valt.</p>

<h2>3. User Account</h2>
<p>You are responsible for maintaining the confidentiality of your account credentials and Cardano wallet.</p>

<h2>4. NFT Transactions</h2>
<p>All NFT sales are final. Royalties and fees are disclosed at the time of purchase. Ownership of an NFT does not grant copyright to the underlying work.</p>

<h2>5. Blockchain</h2>
<p>Valt operates on the Cardano blockchain. Transactions are irreversible. Gas fees may apply.</p>

<h2>6. Intellectual Property</h2>
<p>Valt platform IP is owned by Awen LLC. Third-party NFT IP belongs to respective creators.</p>

<h2>7. Limitation of Liability</h2>
<p>Valt is provided "as is." Awen LLC is not liable for losses related to blockchain transactions, wallet security, or third-party services.</p>

<h2>8. Governing Law</h2>
<p>These terms are governed by the laws of the State of Wyoming, USA.</p>

<h2>9. Contact</h2>
<p>Awen LLC, Sheridan, WY — <a href="mailto:cullah@awen.online">cullah@awen.online</a></p>',
		],
		'Privacy Policy' => [
			'slug'    => 'privacy-policy',
			'content' => '<h1>Privacy Policy</h1>

<h2>Who We Are</h2>
<p>Valt is operated by Awen LLC. Our website address is: ' . home_url() . '</p>

<h2>Data We Collect</h2>
<p>We collect information you provide when creating an account, connecting a wallet, or contacting us. This includes email addresses, wallet addresses, and transaction data.</p>

<h2>Cookies</h2>
<p>We use cookies for login sessions and site preferences. No tracking cookies are used for advertising.</p>

<h2>Embedded Content</h2>
<p>Pages may include embedded content (videos, images) from other websites, which may collect data about you.</p>

<h2>Data Retention</h2>
<p>Transaction data is retained on the Cardano blockchain permanently. Account data is retained while your account is active.</p>

<h2>Your Rights</h2>
<p>You may request export or deletion of your personal data by contacting us at <a href="mailto:cullah@awen.online">cullah@awen.online</a>.</p>',
		],
	];

	foreach ( $pages as $title => $config ) {
		$page_id = valt_seed_find_or_create( 'page', $title, [
			'post_content' => $config['content'],
			'post_name'    => $config['slug'],
		] );

		// Set Valt Full Page template.
		update_post_meta( $page_id, '_wp_page_template', 'page-valt.php' );

		// Remove Elementor data.
		delete_post_meta( $page_id, '_elementor_data' );
		delete_post_meta( $page_id, '_elementor_edit_mode' );
		delete_post_meta( $page_id, '_elementor_template_type' );

		// Set hero meta if provided.
		if ( ! empty( $config['hero'] ) ) {
			foreach ( $config['hero'] as $key => $value ) {
				update_post_meta( $page_id, $key, $value );
			}
		}

		echo "Page: {$title} (ID: {$page_id}, slug: {$config['slug']})\n";
	}

	// Set Home as the static front page.
	$home_page = get_page_by_path( 'home' );
	if ( $home_page ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_page->ID );
		echo "\nFront page set to: Home (ID: {$home_page->ID})\n";
	}

	echo "\n✓ Page seed complete. " . count( $pages ) . " pages created/updated.\n";
	echo '</pre></div>';
}
