<?php
defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Admin menu: Valt Platform → Shortcode Reference
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_menu_page(
		'Valt Platform',
		'Valt Platform',
		'manage_options',
		'valt-platform-docs',
		'valt_platform_docs_page',
		'dashicons-book-alt',
		30
	);

	// First submenu uses same slug → renames the parent menu label
	add_submenu_page(
		'valt-platform-docs',
		'Shortcode Reference — Valt Platform',
		'Shortcode Reference',
		'manage_options',
		'valt-platform-docs',
		'valt_platform_docs_page'
	);
} );

// ---------------------------------------------------------------------------
// Docs page renderer
// ---------------------------------------------------------------------------

function valt_platform_docs_page(): void {
	?>
	<div class="wrap valt-docs">

		<style>
			.valt-docs { max-width: 960px; }
			.valt-docs h1 { display: flex; align-items: center; gap: 8px; }
			.valt-docs .valt-sc {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 20px 24px;
				margin-bottom: 20px;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			.valt-docs .valt-sc > h2 {
				margin-top: 0;
				margin-bottom: 10px;
				font-family: monospace;
				font-size: 18px;
				color: #1d2327;
				display: flex;
				align-items: center;
				gap: 10px;
				flex-wrap: wrap;
			}
			.valt-docs .valt-sc h3 {
				font-size: 11px;
				text-transform: uppercase;
				letter-spacing: 0.6px;
				color: #646970;
				margin: 18px 0 8px;
				border-bottom: 1px solid #f0f0f1;
				padding-bottom: 4px;
			}
			.valt-docs pre {
				background: #f6f7f7;
				border: 1px solid #e2e4e7;
				padding: 12px 14px;
				border-radius: 3px;
				overflow-x: auto;
				font-size: 13px;
				line-height: 1.65;
				margin: 8px 0 0;
				white-space: pre-wrap;
				word-break: break-word;
			}
			.valt-docs .valt-badge {
				display: inline-block;
				padding: 2px 9px;
				border-radius: 3px;
				font-size: 10px;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				vertical-align: middle;
			}
			.valt-docs .valt-badge--enc  { background: #3d3c56; color: #fff; }
			.valt-docs .valt-badge--self { background: #e8c48b; color: #493d3c; }
			.valt-docs table th { font-weight: 600; }
			.valt-docs .desc { color: #50575e; font-size: 13px; line-height: 1.6; margin-bottom: 4px; }
			.valt-docs ul.notes  { margin: 0 0 4px 20px; color: #50575e; font-size: 13px; line-height: 1.7; }
			.valt-docs ol.states { margin: 0 0 4px 20px; color: #50575e; font-size: 13px; line-height: 1.7; }
		</style>

		<h1>
			<span class="dashicons dashicons-book-alt" style="font-size:30px;width:30px;height:30px;color:#e8c48b;"></span>
			Valt Platform &mdash; Shortcode Reference
		</h1>
		<p class="desc" style="margin-bottom:24px;">
			All shortcodes provided by the <strong>Valt Platform</strong> plugin (v<?php echo esc_html( VALT_PLATFORM_VERSION ); ?>).
			Drop them into any Elementor text/HTML widget, Classic Editor, or Gutenberg Shortcode block.
			<span class="valt-badge valt-badge--enc">Enclosing</span> shortcodes wrap content between an opening and closing tag.
			<span class="valt-badge valt-badge--self">Self-closing</span> shortcodes stand alone.
		</p>

		<?php /* ============================================================
		   1. valt_gated_content
		   ============================================================ */ ?>
		<div class="valt-sc">
			<h2>
				[valt_gated_content]
				<span class="valt-badge valt-badge--enc">Enclosing</span>
			</h2>
			<p class="desc">
				Server-side NFT policy gate. Non-holders <strong>never receive the inner HTML</strong> — it is withheld on the server, not merely hidden with CSS.
				Supply either <code>policy_id</code> directly or an <code>artist_id</code> whose <code>valt_policy_id</code> meta will be read.
			</p>

			<h3>Attributes</h3>
			<table class="widefat striped" style="margin-bottom:4px;">
				<thead>
					<tr>
						<th style="width:175px;">Attribute</th>
						<th style="width:220px;">Default</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>policy_id</code></td>
						<td><em>(empty)</em></td>
						<td>Cardano NFT policy ID. Takes precedence over <code>artist_id</code> meta if both are supplied.</td>
					</tr>
					<tr>
						<td><code>artist_id</code></td>
						<td><em>(empty)</em></td>
						<td>Post ID of an Artist CPT. The policy ID is read from its <code>valt_policy_id</code> meta field.</td>
					</tr>
					<tr>
						<td><code>connect_message</code></td>
						<td>"Connect your Cardano wallet to access this exclusive content."</td>
						<td>Message shown when no wallet is connected.</td>
					</tr>
					<tr>
						<td><code>locked_message</code></td>
						<td>"You need to hold an NFT from this collection to unlock this content."</td>
						<td>Message shown when connected but the required NFT is not held.</td>
					</tr>
				</tbody>
			</table>

			<h3>Gate states (rendered in order)</h3>
			<ol class="states">
				<li><strong>No wallet connected</strong> — renders <code>connect_message</code> + CardanoPress modal trigger button.</li>
				<li><strong>Wallet connected, no synced assets</strong> — prompts the user to sync their wallet on the CardanoPress dashboard.</li>
				<li><strong>Wallet connected, NFT not held</strong> — renders <code>locked_message</code>.</li>
				<li><strong>NFT confirmed ✓</strong> — renders the inner content normally.</li>
			</ol>

			<h3>Examples</h3>
<pre>[valt_gated_content policy_id="a0028f350aaabe0545fdcb56b039bfb08e4bb4d8c4d7c3c7d481809"
    locked_message="Hold a Valt NFT to unlock this exclusive content."]
  &lt;p&gt;Secret fan-club content goes here.&lt;/p&gt;
[/valt_gated_content]</pre>
<pre>[valt_gated_content artist_id="42"]
  &lt;p&gt;Content gated by Artist #42&rsquo;s own policy ID.&lt;/p&gt;
[/valt_gated_content]</pre>
		</div>


		<?php /* ============================================================
		   2. valt_connect_prompt
		   ============================================================ */ ?>
		<div class="valt-sc">
			<h2>
				[valt_connect_prompt]
				<span class="valt-badge valt-badge--self">Self-closing</span>
			</h2>
			<p class="desc">
				Renders the CardanoPress wallet connect modal trigger button.
				<strong>Outputs nothing if the visitor already has a wallet connected.</strong>
			</p>

			<h3>Attributes</h3>
			<table class="widefat striped" style="margin-bottom:4px;">
				<thead>
					<tr>
						<th style="width:175px;">Attribute</th>
						<th style="width:220px;">Default</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>text</code></td>
						<td>"Connect Wallet"</td>
						<td>Button label passed to the CardanoPress modal trigger template.</td>
					</tr>
					<tr>
						<td><code>message</code></td>
						<td><em>(empty)</em></td>
						<td>Optional prompt text displayed above the button.</td>
					</tr>
				</tbody>
			</table>

			<h3>Examples</h3>
<pre>[valt_connect_prompt]</pre>
<pre>[valt_connect_prompt message="Connect your Cardano wallet to get started." text="Connect Now"]</pre>
		</div>


		<?php /* ============================================================
		   3. valt_artist_profile
		   ============================================================ */ ?>
		<div class="valt-sc">
			<h2>
				[valt_artist_profile]
				<span class="valt-badge valt-badge--self">Self-closing</span>
			</h2>
			<p class="desc">
				Renders a public artist card: photo, name, genre tag, country tag, and bio.
				No gating — visible to all visitors.
			</p>

			<h3>Attributes</h3>
			<table class="widefat striped" style="margin-bottom:4px;">
				<thead>
					<tr>
						<th style="width:175px;">Attribute</th>
						<th style="width:220px;">Default</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>artist_id</code></td>
						<td><em>(required)</em></td>
						<td>Post ID of the Artist CPT to display.</td>
					</tr>
				</tbody>
			</table>

			<h3>Output fields</h3>
			<ul class="notes">
				<li>Featured image at <code>medium</code> size (profile photo)</li>
				<li>Artist name as an <code>&lt;h2&gt;</code></li>
				<li>Genre and Country as styled tags</li>
				<li>Bio (HTML allowed — stored in the <code>bio</code> Pods meta field)</li>
			</ul>

			<h3>Example</h3>
<pre>[valt_artist_profile artist_id="42"]</pre>
		</div>


		<?php /* ============================================================
		   4. valt_artist_valt
		   ============================================================ */ ?>
		<div class="valt-sc">
			<h2>
				[valt_artist_valt]
				<span class="valt-badge valt-badge--enc">Enclosing</span>
			</h2>
			<p class="desc">
				Combines a public artist header (identical to <code>[valt_artist_profile]</code>) with a gated fan-club zone directly below it.
				Place any Elementor template or content between the tags; this shortcode applies the gate using the artist&rsquo;s own <code>valt_policy_id</code> meta.
			</p>

			<h3>Attributes</h3>
			<table class="widefat striped" style="margin-bottom:4px;">
				<thead>
					<tr>
						<th style="width:175px;">Attribute</th>
						<th style="width:220px;">Default</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>artist_id</code></td>
						<td><em>(required)</em></td>
						<td>Post ID of the Artist CPT. The artist&rsquo;s <code>valt_policy_id</code> meta is used for gating.</td>
					</tr>
				</tbody>
			</table>

			<h3>Notes</h3>
			<ul class="notes">
				<li>If the artist has no <code>valt_policy_id</code> set, the gated zone is omitted — only the public header is shown.</li>
				<li>Gate states are identical to <code>[valt_gated_content]</code>.</li>
				<li>The artist&rsquo;s Policy ID is set on the Artist CPT edit screen (the <strong>Policy ID</strong> column is also visible in the Artist list in wp-admin).</li>
			</ul>

			<h3>Example</h3>
<pre>[valt_artist_valt artist_id="42"]
  [elementor-template id="99"]
[/valt_artist_valt]</pre>
		</div>


		<?php /* ============================================================
		   5. valt_artist_dashboard
		   ============================================================ */ ?>
		<div class="valt-sc">
			<h2>
				[valt_artist_dashboard]
				<span class="valt-badge valt-badge--self">Self-closing</span>
			</h2>
			<p class="desc">
				Full frontend artist management dashboard. The visiting user must be <strong>logged in</strong> and have a linked Artist CPT
				(<code>post_author</code> on the Artist post must equal their WP user ID).
				No attributes.
			</p>

			<h3>Profile tab — editable fields</h3>
			<ul class="notes">
				<li>Artist name (updates <code>post_title</code>)</li>
				<li>Bio (updates <code>bio</code> meta)</li>
				<li>Genre (updates <code>genre</code> meta)</li>
				<li>Country (updates <code>country</code> meta)</li>
				<li>NFT Policy ID (updates <code>valt_policy_id</code> meta)</li>
				<li>Profile photo via <code>wp.media()</code> image uploader (sets post thumbnail)</li>
			</ul>
			<p class="desc">Saved via AJAX action <code>valt_save_artist_profile</code>.</p>

			<h3>Releases tab</h3>
			<ul class="notes">
				<li><strong>Add Release form:</strong> track title, audio file (<code>wp.media()</code> audio picker), album (select from existing albums linked to this artist), duration, track number. Creates a Song CPT via AJAX action <code>valt_add_release</code> with <code>valt_release_status = 1</code>.</li>
				<li><strong>Releases table:</strong> title · album · duration · status badge · mint count.</li>
			</ul>

			<h3>Admin setup</h3>
			<p class="desc">
				In wp-admin, open the Artist CPT and set the <strong>Author</strong> field to the WP user who manages it.
				That user will see the dashboard when they visit a page containing this shortcode.
				A user with no linked artist sees: <em>&ldquo;No artist profile is linked to your account.&rdquo;</em>
			</p>

			<h3>Example</h3>
<pre>[valt_artist_dashboard]</pre>
		</div>


		<?php /* ============================================================
		   6. valt_release_status
		   ============================================================ */ ?>
		<div class="valt-sc">
			<h2>
				[valt_release_status]
				<span class="valt-badge valt-badge--self">Self-closing</span>
			</h2>
			<p class="desc">
				Renders a small inline coloured badge showing the current release status of a Song CPT.
				Useful in tracklists, artist pages, and archive templates.
			</p>

			<h3>Attributes</h3>
			<table class="widefat striped" style="margin-bottom:4px;">
				<thead>
					<tr>
						<th style="width:175px;">Attribute</th>
						<th style="width:220px;">Default</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>post_id</code></td>
						<td><em>(required)</em></td>
						<td>Post ID of the Song CPT.</td>
					</tr>
				</tbody>
			</table>

			<h3>Status values</h3>
			<table class="widefat striped" style="max-width:580px;margin-bottom:4px;">
				<thead>
					<tr><th>Value</th><th>Badge label</th><th>Colour</th><th>Set by</th></tr>
				</thead>
				<tbody>
					<tr>
						<td><code>1</code></td>
						<td>Uploaded</td>
						<td>Grey</td>
						<td>Automatic on Song creation via dashboard or wp-admin</td>
					</tr>
					<tr>
						<td><code>2</code></td>
						<td>In NFT Collection</td>
						<td>Amber</td>
						<td>Admin via the <strong>Valt Release Info</strong> meta box on the Song edit screen</td>
					</tr>
					<tr>
						<td><code>3</code></td>
						<td>Minted (N copies)</td>
						<td>Gold</td>
						<td>Admin via meta box — also set <strong>Mint Count</strong> to populate N</td>
					</tr>
				</tbody>
			</table>

			<h3>Example</h3>
<pre>[valt_release_status post_id="123"]</pre>
		</div>


		<?php /* ============================================================
		   Post Meta Reference
		   ============================================================ */ ?>
		<div class="valt-sc">
			<h2>Post Meta Reference</h2>
			<p class="desc">All three keys are registered via <code>register_post_meta()</code> with <code>show_in_rest => true</code>.</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Post Type</th>
						<th>Meta Key</th>
						<th>Type</th>
						<th>REST</th>
						<th>Purpose</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>artist</code></td>
						<td><code>valt_policy_id</code></td>
						<td>string</td>
						<td>✓</td>
						<td>Cardano NFT policy ID — gates this artist&rsquo;s Valt fan-club zone</td>
					</tr>
					<tr>
						<td><code>song</code></td>
						<td><code>valt_release_status</code></td>
						<td>integer (1–3)</td>
						<td>✓</td>
						<td>Release stage. 1 = Uploaded, 2 = In NFT Collection, 3 = Minted. Default: 1.</td>
					</tr>
					<tr>
						<td><code>song</code></td>
						<td><code>valt_mint_count</code></td>
						<td>integer</td>
						<td>✓</td>
						<td>Number of copies minted. Shown on the Minted badge and in the artist dashboard.</td>
					</tr>
				</tbody>
			</table>
		</div>


		<?php /* ============================================================
		   AJAX Actions
		   ============================================================ */ ?>
		<div class="valt-sc">
			<h2>AJAX Actions <span style="font-size:13px;font-weight:400;color:#646970;">&mdash; developer reference</span></h2>
			<p class="desc">
				Both actions POST to <code>admin-ajax.php</code>. Every request must include <code>nonce</code> (value: <code>valtPlatform.nonce</code>)
				and <code>action</code>. Handlers live in <code>includes/artist-dashboard.php</code>.
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Action slug</th>
						<th>Auth</th>
						<th>Required POST fields</th>
						<th>Success response</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>valt_save_artist_profile</code></td>
						<td>Logged-in user who owns the artist</td>
						<td><code>artist_id, name, bio, genre, country, valt_policy_id, photo_id</code></td>
						<td>Updates Artist CPT post title, meta fields, and featured image thumbnail. Returns success message string.</td>
					</tr>
					<tr>
						<td><code>valt_add_release</code></td>
						<td>Logged-in user who owns the artist</td>
						<td><code>artist_id, title, audio_id, album_id, duration, track_number</code></td>
						<td>Creates Song CPT with <code>valt_release_status = 1</code>. Returns <code>{ song_id, title, album, duration }</code>.</td>
					</tr>
				</tbody>
			</table>
		</div>

	</div><!-- /.wrap.valt-docs -->
	<?php
}
