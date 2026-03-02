# valt-platform

WordPress plugin providing token-gating, Artist Valt zones, and the Artist Dashboard for the [Valt](https://valt.digital) music platform.

Part of the [Awen-online/valt](https://github.com/Awen-online/valt) codebase · lives at `wp-content/plugins/valt-platform/`

---

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.0+ |
| PHP | 8.0+ |
| [CardanoPress](https://cardanopress.io) | Latest |
| [Pods](https://pods.io) | Latest |

CardanoPress provides wallet connection and NFT asset storage. Pods provides the Artist, Album, and Song custom post types.

---

## Installation

1. Copy `valt-platform/` into `wp-content/plugins/`
2. Activate in **wp-admin → Plugins**
3. Visit **Valt Platform → Shortcode Reference** in the admin sidebar for live documentation

---

## File Structure

```
valt-platform.php              Plugin header, constants, bootstrap (requires all includes)
includes/
  gating.php                   valt_user_holds_policy() · valt_get_current_artist()
  rest-meta.php                register_post_meta() for 3 keys with show_in_rest
  shortcodes.php               All 6 shortcodes
  artist-dashboard.php         valt_render_artist_dashboard() + 2 wp_ajax_ handlers
  admin-meta.php               Song meta box (status/mint) + Artist list Policy ID column
  admin-docs.php               wp-admin reference page (Valt Platform menu)
assets/
  css/valt-platform.css        Dashboard, badge, gated-content styles (Valt palette)
  js/valt-platform.js          Tab switching, wp.media() uploaders, AJAX form saves
```

---

## Shortcodes

All shortcodes are Elementor-droppable. Drop them via the Shortcode widget or paste into any HTML/text area.

---

### `[valt_gated_content]` — Enclosing

Server-side NFT policy gate. Non-holders **never receive the inner HTML** — the content is withheld on the server, not merely hidden with CSS.

```
[valt_gated_content
    policy_id=""
    artist_id=""
    connect_message="Connect your Cardano wallet to access this exclusive content."
    locked_message="You need to hold an NFT from this collection to unlock this content."]

  Your gated content here.

[/valt_gated_content]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `policy_id` | _(empty)_ | Cardano NFT policy ID. Takes precedence over `artist_id` meta if both are set. |
| `artist_id` | _(empty)_ | Post ID of an Artist CPT. Policy ID is read from its `valt_policy_id` meta. |
| `connect_message` | _(see above)_ | Shown when no wallet is connected. |
| `locked_message` | _(see above)_ | Shown when connected but the NFT is not held. |

**Gate states (rendered in order):**
1. **No wallet** → `connect_message` + CardanoPress modal trigger button
2. **Connected, no synced assets** → prompt to sync wallet on CardanoPress dashboard
3. **Connected, wrong NFT** → `locked_message`
4. **NFT confirmed ✓** → inner content rendered normally

**Examples:**
```
[valt_gated_content policy_id="a0028f350aaabe0545fdcb56b039bfb08e4bb4d8c4d7c3c7d481809"
    locked_message="Hold a Valt NFT to unlock this content."]
  <p>Exclusive fan-club content here.</p>
[/valt_gated_content]

[valt_gated_content artist_id="42"]
  <p>Gated by Artist #42's own policy ID.</p>
[/valt_gated_content]
```

---

### `[valt_connect_prompt]` — Self-closing

Renders the CardanoPress wallet connect modal trigger. **Silent if the visitor already has a wallet connected.**

```
[valt_connect_prompt text="Connect Wallet" message=""]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `text` | `"Connect Wallet"` | Button label. |
| `message` | _(empty)_ | Optional prompt text shown above the button. |

**Example:**
```
[valt_connect_prompt message="Connect your wallet to access exclusive content." text="Connect Now"]
```

---

### `[valt_artist_profile]` — Self-closing

Renders a public artist card. No gating — visible to all visitors.

```
[valt_artist_profile artist_id=""]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `artist_id` | _(required)_ | Post ID of the Artist CPT. |

**Output:** artist photo (featured image, medium size) · name (h2) · genre tag · country tag · bio (HTML).

**Example:**
```
[valt_artist_profile artist_id="42"]
```

---

### `[valt_artist_valt]` — Enclosing

Combines a public artist header with a gated fan-club zone below it. The policy ID comes from the artist's own `valt_policy_id` meta — no attribute needed.

```
[valt_artist_valt artist_id=""]
  Your gated fan-club content here.
[/valt_artist_valt]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `artist_id` | _(required)_ | Post ID of the Artist CPT. |

**Notes:**
- If the artist has no `valt_policy_id` set, only the public header is shown (gated zone omitted).
- Gate states are identical to `[valt_gated_content]`.

**Example:**
```
[valt_artist_valt artist_id="42"]
  [elementor-template id="99"]
[/valt_artist_valt]
```

---

### `[valt_artist_dashboard]` — Self-closing

Full frontend artist management dashboard. Requires the visitor to be **logged in** and have a linked Artist CPT (`post_author` = their WP user ID).

```
[valt_artist_dashboard]
```

No attributes.

**Profile tab** — editable fields saved via AJAX (`valt_save_artist_profile`):

| Field | Stored as |
|-------|-----------|
| Artist name | `post_title` |
| Bio | `bio` meta |
| Genre | `genre` meta |
| Country | `country` meta |
| NFT Policy ID | `valt_policy_id` meta |
| Profile photo | Post thumbnail (via `wp.media()`) |

**Releases tab:**
- **Add Release form** — title, audio file (`wp.media()` audio picker), album, duration, track number. Creates a Song CPT via `valt_add_release` with `valt_release_status = 1`.
- **Releases table** — title · album · duration · status badge · mint count.

**Admin setup:** In wp-admin, edit the Artist CPT and set the **Author** field to the WP user who manages it.

---

### `[valt_release_status]` — Self-closing

Renders a small inline badge showing a Song CPT's current release status.

```
[valt_release_status post_id=""]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `post_id` | _(required)_ | Post ID of the Song CPT. |

**Status values:**

| Value | Label | Badge colour | Set by |
|-------|-------|-------------|--------|
| `1` | Uploaded | Grey | Automatic on Song creation |
| `2` | In NFT Collection | Amber | Admin via Song meta box |
| `3` | Minted (N copies) | Gold | Admin via Song meta box + Mint Count field |

**Example:**
```
[valt_release_status post_id="123"]
```

---

## Post Meta Reference

All three keys are registered with `register_post_meta()` and `show_in_rest => true`.

| Post Type | Meta Key | Type | Purpose |
|-----------|----------|------|---------|
| `artist` | `valt_policy_id` | string | Cardano NFT policy ID — gates this artist's Valt fan-club zone |
| `song` | `valt_release_status` | integer (1–3) | Release stage. Default: `1`. |
| `song` | `valt_mint_count` | integer | Number of copies minted. Shown on badge and in dashboard. |

---

## Admin Features

### Song edit screen — Valt Release Info meta box
Allows admins to advance a song's `valt_release_status` (1 → 2 → 3) and set the `valt_mint_count`.

### Artist list — Policy ID column
The `valt_policy_id` value for each artist is shown as a column in the wp-admin Artist post list for quick reference.

### Valt Platform menu
A top-level **Valt Platform** item appears in the wp-admin sidebar with a **Shortcode Reference** page — the same documentation rendered inline with live syntax highlighting.

---

## AJAX Actions

Both actions POST to `admin-ajax.php`. Every request includes `nonce` (`valtPlatform.nonce`) and `action`. Handlers are in `includes/artist-dashboard.php`.

| Action | Auth | POST fields | Response |
|--------|------|-------------|----------|
| `valt_save_artist_profile` | Logged-in, owns artist | `artist_id, name, bio, genre, country, valt_policy_id, photo_id` | Success message string |
| `valt_add_release` | Logged-in, owns artist | `artist_id, title, audio_id, album_id, duration, track_number` | `{ song_id, title, album, duration }` |

---

## CardanoPress API used

```php
cardanoPress()->userProfile()->isConnected()          // bool
cardanoPress()->userProfile()->storedAssets()          // array of ['policy_id' => '...', ...]
cardanoPress()->template('part/modal-trigger')         // echoes connect button HTML
```

---

## Artist ↔ User link

`post_author` on the Artist CPT = WP user ID. Set by admin when creating the Artist.
`valt_get_current_artist()` resolves this by querying `get_posts(['post_type'=>'artist', 'author'=>get_current_user_id()])`.
