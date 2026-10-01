# valt-platform

WordPress plugin providing token-gating, Artist Valt zones, and the Artist Dashboard for the [Valt](https://valt.digital) music platform.

Part of the [Awen-online/valt](https://github.com/Awen-online/valt) codebase · lives at `wp-content/plugins/valt-platform/`

---

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.0+ |
| PHP | 8.1+ (with `sodium` for the in-page checkout) |
| [CardanoPress](https://cardanopress.io) | Latest |
| [Pods](https://pods.io) | Latest |

CardanoPress provides wallet connection and NFT asset storage. Pods provides the Artist, Album, and Song custom post types.

---

## Installation

1. Copy `valt-platform/` into `wp-content/plugins/`
2. Activate in **wp-admin → Plugins**
3. Configure keys under **Settings** (`wp-admin/admin.php?page=valt-settings`) and watch mints in the
   **NFT Monitor** (`wp-admin/admin.php?page=valt-nft-monitor`). In the curated build these pages have no
   sidebar parent, because the admin docs module that adds the **Valt Platform** menu is not included.

---

## File Structure

```
valt-platform.php              Plugin header, constants, bootstrap; optional modules load only if present
includes/
  gating.php                   Per-artist NFT gating: valt_user_holds_policy(), valt_filter_assets_for_artist()
  rest-meta.php                register_post_meta() with show_in_rest
  shortcodes.php               The original 6 gating/profile/dashboard shortcodes
  shortcodes-new.php           Front-end building blocks: mint button, spotlight, song grid, tracklist, ...
  checkout.php                 (M4) NMKR Pay multi-edition checkout + the collect token
  anvil.php                    (M4) In-page wallet checkout via the Anvil API + WP-CLI tools
  media-gate.php               (M4) Holder-only media served from outside the web root
  nmkr.php                     NMKR upload/mint flow, CIP-25 metadata, per-song inventory
  helpers.php                  Config (keys via constants/options), request wrappers, event log
  artist-dashboard.php         valt_render_artist_dashboard() + 2 wp_ajax_ handlers
  admin-meta.php, admin-settings.php, admin-nft-monitor.php   wp-admin screens
  discovery.php, rest-api.php, ajax-handlers.php, cron.php, db-schema.php
assets/
  css/valt-platform.css        Dashboard, badge, gated-content styles (Valt palette)
  js/valt-platform.js          Dashboard uploaders, edition picker, both collect flows, pending panel
  js/hls-video.js              (M4) HLS playback for holder-only video (loaded only where used)
```

The curated open-source build omits a few dormant modules (campaigns, gamification, leaderboard,
admin docs, seed data); the plugin loads them only when the files exist.

---

## Shortcodes

All shortcodes are Elementor-droppable. Drop them via the Shortcode widget or paste into any HTML/text area.

---

### `[valt_gated_content]` (enclosing)

Server-side NFT policy gate. Non-holders **never receive the inner HTML**: the content is withheld on the server, not merely hidden with CSS.

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
[valt_gated_content policy_id="bf5a88ac0a236c22c2772a51ff2fa33301e17c42aa8f95fcd585b86a"
    locked_message="Hold a Valt NFT to unlock this content."]
  <p>Exclusive fan-club content here.</p>
[/valt_gated_content]

[valt_gated_content artist_id="42"]
  <p>Gated by Artist #42's own policy ID.</p>
[/valt_gated_content]
```

---

### `[valt_connect_prompt]` (self-closing)

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

### `[valt_artist_profile]` (self-closing)

Renders a public artist card. No gating: visible to all visitors.

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

### `[valt_artist_valt]` (enclosing)

Combines a public artist header with a gated fan-club zone below it. The policy ID comes from the artist's own `valt_policy_id` meta, so no attribute is needed.

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

### `[valt_artist_dashboard]` (self-closing)

Full frontend artist management dashboard. Requires the visitor to be **logged in** and have a linked Artist CPT (`post_author` = their WP user ID).

```
[valt_artist_dashboard]
```

No attributes.

**Profile tab**: editable fields saved via AJAX (`valt_save_artist_profile`):

| Field | Stored as |
|-------|-----------|
| Artist name | `post_title` |
| Bio | `bio` meta |
| Genre | `genre` meta |
| Country | `country` meta |
| NFT Policy ID | `valt_policy_id` meta |
| Profile photo | Post thumbnail (via `wp.media()`) |

**Releases tab:**
- **Add Release form**: title, audio file (`wp.media()` audio picker), album, duration, track number. Creates a Song CPT via `valt_add_release` with `valt_release_status = 1`.
- **Releases table**: title · album · duration · status badge · mint count.

**Admin setup:** In wp-admin, edit the Artist CPT and set the **Author** field to the WP user who manages it.

---

### `[valt_release_status]` (self-closing)

Renders a small inline badge showing a Song CPT's current release status.

```
[valt_release_status post_id=""]
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `post_id` | _(required)_ | Post ID of the Song CPT. |

**Status values:**

| Value | Label | Badge color | Set by |
|-------|-------|-------------|--------|
| `1` | Uploaded | Gray | Automatic on Song creation |
| `2` | In NFT Collection | Amber | Admin via Song meta box |
| `3` | Minted (N copies) | Gold | Admin via Song meta box + Mint Count field |

**Example:**
```
[valt_release_status post_id="123"]
```

---

## Collecting (M4)

A song can be collected through one of two checkouts, chosen per song with the `valt_checkout`
post meta (`nmkr`, the default, or `anvil`). Both are started by the mint button
(`[valt_mint_button]` / `[valt_connect_mint]`), which shows a 1 to 5 edition picker when more than
one edition is free.

### Collect token

Both checkouts are public REST endpoints protected by a user-independent token
(`valt_collect_token()`, an HMAC of a 12-hour window with the site salt), localized to the page as
`valtPlatform.collectToken` and sent as `nonce`. WordPress nonces are bound to the logged-in user,
so they failed for wallet-connected visitors (bug log R-36); this token also works on cached pages.

### NMKR Pay (hosted checkout)

- **1 edition:** the button links straight to NMKR Pay for one free edition of this song.
- **2 to 5 editions:** `POST /wp-json/valt/v1/collect` with `{ song_id, qty, nonce }` picks `qty`
  free editions of this song, creates an NMKR `nmkr_pay_specific` payment transaction and returns
  `{ url, qty, price }`; the browser opens `url`.
- Rate limit: 6 checkout starts per IP per 10 minutes (HTTP 429).
- Errors: `403` bad token, `404` unknown song, `400` quantity out of range, `409` not enough
  editions left (`available` in the error data), `502` NMKR did not return a checkout.
- NMKR requirement found in testing: the project price list needs an entry for every quantity
  offered (1 to `VALT_COLLECT_MAX_QTY`).

### In-page wallet checkout (Anvil)

Songs switched to `anvil` never leave the page: one transaction pays for and mints the editions
straight to the buyer's wallet.

1. **Build** `POST /wp-json/valt/v1/anvil/build` `{ song_id, qty, address, utxos, nonce }`
   (`address` and `utxos` come from the wallet's CIP-30 `getChangeAddress()` / `getUtxos()`).
   The server reserves `qty` edition numbers in its ledger for `VALT_ANVIL_LOCK_TTL` (15 minutes),
   skipping any number that already exists on-chain, asks Anvil to build a transaction that pays the
   price to the payout address and mints the editions with CIP-25 metadata to the buyer, and returns
   `{ build_id, tx, hash, editions }`.
2. **Sign** in the wallet: `signTx(tx, true)`.
3. **Submit** `POST /wp-json/valt/v1/anvil/submit` `{ build_id, witness, nonce }`. Before adding the
   policy signature, the server decodes the stored transaction and checks that it mints exactly the
   reserved editions, to the buyer, and pays the right amount; it then adds the witnesses without
   changing the transaction id, submits through Anvil and returns
   `{ tx_hash, explorer, valt_url, editions }`.
4. **Confirm** `GET /wp-json/valt/v1/anvil/status?tx=<hash>` returns `{ confirmed, confirmations }`
   (cached briefly). The page shows a pending panel and offers "Open the Valt" only once confirmed.

If the wallet declines, `POST /wp-json/valt/v1/anvil/cancel` `{ build_id, nonce }` frees the
reserved editions immediately. Builds are rate limited to 8 per IP per 10 minutes.

- **Edition names:** Anvil editions use their own series (`<asset>a01`, `a02`, ...) so they never
  collide with NMKR's `e01`-style names, and carry the display name `"<Song> #N"`.
- **Supply:** `valt_anvil_cap` (post meta) caps the series; for switched songs, stock, the scarcity
  badge and the sold-out state come from the ledger.
- **Fees:** Anvil adds a service fee of 0.15 ADA plus 1 ADA per edition to each transaction, on top of
  the network fee and the minimum ADA that travels with each token.
- Preprod only in this build: the checkout refuses to run on any other network.

### Configuration constants (`wp-config.php`)

| Constant | Purpose |
|----------|---------|
| `VALT_ANVIL_PREPROD_API_KEY` / `VALT_ANVIL_MAINNET_API_KEY` | Anvil API keys (the one matching the network mode is used) |
| `VALT_ANVIL_PAYOUT_ADDRESS` | Address that receives collect payments |
| `VALT_ANVIL_POLICY_SKEY_PATH` | Path to the policy signing key file, outside the web root. The key must hash to the configured policy id |
| `VALT_ANVIL_LOCK_TTL` | How long an unsigned build holds its editions (default 15 minutes) |
| `VALT_COLLECT_MAX_QTY` | Maximum editions per checkout (default 5) |
| `VALT_SCARCITY_THRESHOLD` | Show "Only N left" at or below this many free editions (default 5) |
| `VALT_TRUSTED_PROXY` | Trust `X-Forwarded-For` for rate limiting (only behind a known proxy) |

### WP-CLI

```
wp valt anvil status
wp valt anvil enable <song_id> --cap=<n> [--accept-overlap] [--preview]
wp valt anvil disable <song_id>
wp valt anvil dryrun <song_id> --buyer=<address> [--payout=<address>] [--qty=<n>]
wp valt anvil reconcile <song_id>
wp valt anvil burn-build --assets=<names> --from=<address> --out=<file>
wp valt anvil burn-submit --file=<file> --witness=<hex>
```

`enable` refuses unless the checkout is ready (API key, payout address, policy key); `--preview` lets
a local site switch the UI without them. `dryrun` builds and verifies a transaction without
submitting it. `reconcile` marks every edition found on-chain as sent in the ledger. The burn commands
build a burn transaction for the holding wallet to sign; the server never signs for holders.

---

## Holder-only media (M4)

```
[valt_private_video artist_id="" file="" hls="" poster="" title=""]
```

| Attribute | Description |
|-----------|-------------|
| `artist_id` | Artist whose holders may watch |
| `file` | MP4 file name in that artist's private media folder |
| `hls` | Optional HLS playlist (`.m3u8`) in the same folder; loads `hls-video.js` on that page only |
| `poster`, `title` | Poster image URL and accessible title |

Files live outside the web root (`<parent of ABSPATH>/valt-private-media/<artist_id>/`) and are only
served through `/?valt_media=<artist_id>/<file>` after the same per-artist holder check the Valt gate
uses (admins always pass). HTTP Range requests are supported for seeking. Use it inside
`[valt_gated_content]` so non-holders never receive the tag at all.

---

## Front-end building blocks (`shortcodes-new.php`)

| Shortcode | Attributes | Purpose |
|-----------|-----------|---------|
| `[valt_mint_button]` | `song_id` | Price, edition count, scarcity badge, edition picker and the Collect button (either checkout); a sold-out panel once every edition is claimed |
| `[valt_connect_mint]` | `song_id` | Wallet prompt plus the mint button (used on song pages) |
| `[valt_nft_status]` | `song_id` | Mint status badge |
| `[valt_song_card]` | `song_id` | Single song card |
| `[valt_song_grid]` | `artist_id`, `album_id`, `limit`, `exclude`, `columns`, `ids` | Song cards with play buttons; `ids` gives a curated order |
| `[valt_spotlight]` | `song_id`, `size` (`lg`/`md`), `kicker`, `teaser` (MP4 URL), `blurb` | Featured release block with a muted teaser video, what holders unlock, and a Collect or "Listen to" call to action |
| `[valt_tracklist]` | `artist_id`, `limit`, `exclude`, `ids`, `play_all` | Compact playable rows with a Play all control |
| `[valt_featured_artists]` | `limit`, `genre`, `country`, `ids` | Artist cards; `ids` shows exactly those artists in that order |
| `[valt_discover_artists]` | `per_page`, `show_filters` | Searchable artist directory |
| `[valt_trending_artists]` | `limit` | Trending artists |
| `[valt_follow_button]`, `[valt_artist_fans]`, `[valt_fan_dashboard]` | `artist_id`, `limit` | Follow and fan views |
| `[valt_contact_form]` | none | Contact form (optional reCAPTCHA v3) |

**Scarcity and sold-out state.** With live stock known, cards, the spotlight and the mint button show
"Only N left" / "Last one" at or below `VALT_SCARCITY_THRESHOLD`, and "Sold out" once every edition
is claimed. Unknown stock (for example an API hiccup) never reads as sold out.

Shortcodes that belong to the omitted dormant modules (`valt_leaderboard`, `valt_user_points`,
`valt_user_badges`, `valt_campaign_card`, `valt_active_campaigns`) are registered but not part of
the milestone functionality; their back ends are not included in this build.

---

## REST API (`/wp-json/valt/v1/`)

| Route | Method | Purpose |
|-------|--------|---------|
| `/discover/artists` | GET | Artist directory (`search`, `genre`, `country`, `sort`, `page`, `per_page`) |
| `/discover/genres` | GET | Genres in use |
| `/discover/trending` | GET | Trending artists |
| `/nft/status/<song_id>` | GET | Mint status for a song (logged-in users) |
| `/collect` | POST | NMKR Pay multi-edition checkout (see Collecting) |
| `/anvil/build`, `/anvil/submit`, `/anvil/cancel`, `/anvil/status` | POST / GET | In-page wallet checkout (see Collecting) |

`rest-api.php` also registers `/leaderboard`, `/user/points`, `/user/badges` and `/campaigns` routes
for the dormant modules, which are not included in this build. The theme adds `/survey`,
`/survey-results`, `/survey-export`, `/artist-intake` and `/artist-intake-csv`, and the mu-plugin adds
`/survey-pulse`.

---

## Post Meta Reference

The core keys below are registered with `register_post_meta()` and `show_in_rest => true` (see
`includes/rest-meta.php` for the rest, such as social links, NFT pricing and supply).

| Post Type | Meta Key | Type | Purpose |
|-----------|----------|------|---------|
| `artist` | `valt_policy_id` | string | Cardano NFT policy ID; gates this artist's Valt fan-club zone |
| `song` | `valt_release_status` | integer (1–3) | Release stage. Default: `1`. |
| `song` | `valt_mint_count` | integer | Number of copies minted. Shown on badge and in dashboard. |

---

## Admin Features

### Song edit screen: Valt Release Info meta box
Allows admins to advance a song's `valt_release_status` (1 → 2 → 3) and set the `valt_mint_count`.

### Artist list: Policy ID column
The `valt_policy_id` value for each artist is shown as a column in the wp-admin Artist post list for quick reference.

### Settings and NFT Monitor
**Settings** (`valt-settings`) holds the NMKR and Pinata settings and the module toggles, with secret fields that keep
their stored value when left empty. **NFT Monitor** (`valt-nft-monitor`) shows NMKR project status,
minted NFTs and the event log. On the full platform both sit under a top-level **Valt Platform** menu
with a **Shortcode Reference** page; that admin docs module is not included in this build.

---

## AJAX Actions

The dashboard's two actions POST to `admin-ajax.php`. Every request includes `nonce` (`valtPlatform.nonce`) and `action`. Handlers are in `includes/artist-dashboard.php`; the admin mint, upload and follow actions are in `includes/ajax-handlers.php`.

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
