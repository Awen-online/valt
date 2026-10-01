# Valt: Open-Source Core

**Valt** is a token-gated music platform on **Cardano**: independent artists publish songs, fans
collect those songs as NFTs, and holding an artist's song NFT unlocks that artist's private
**"Valt"**: exclusive, on-chain-gated content.

- **Live portal:** https://www.valt.digital (Cardano pre-production testnet)
- **Project Catalyst:** Fund 11, Project **#1100019**
- **Policy ID:** `bf5a88ac0a236c22c2772a51ff2fa33301e17c42aa8f95fcd585b86a` (verify on preprod.cardanoscan.io)

> **Scope.** This is the **curated open-source core** published for Project Catalyst review: the
> application logic that delivers the milestones' graded functionality (wallet connection, collecting and
> minting, NFT-gating, holder-only media). Operational deploy tooling and a few dormant, non-milestone modules
> (gamification, campaigns, leaderboard, admin docs, seed data) live in a private repository and are not
> included here; the plugin loads them only when present.
>
> **No secrets are committed.** Credentials (NMKR API key, Pinata JWT, Anvil API key, the policy key
> file path) are read at runtime from WordPress options / PHP constants, never from source: see
> `valt-platform/includes/helpers.php` (`valt_nmkr_config()`) and `valt-platform/includes/anvil.php`
> (`valt_anvil_config()`). The Blockfrost project id belongs to CardanoPress's own settings.

## Contents

- **[valt-platform/](valt-platform/)**: standalone WordPress plugin: server-side NFT token-gating,
  the collect checkouts (in-page Anvil checkout and NMKR Pay), holder-only media, discovery, REST API,
  the Artist Dashboard, and an admin **NFT Monitor**. See [valt-platform/README.md](valt-platform/README.md) for the shortcode/API reference.
- **[valt-theme/](valt-theme/)**: Hello Elementor child theme: home, discover, artist page +
  **The Valt**, song page + Collect/mint, and the wallet collection, with Pods (Artists/Albums/Songs)
  and CardanoPress wiring. See [valt-theme/README.md](valt-theme/README.md).
- **[mu-plugins/](mu-plugins/)**: must-use plugins (login throttle, slug rescue, survey-pulse endpoint).
  See [mu-plugins/README.md](mu-plugins/README.md).
- **[valt-mint/](valt-mint/)**: Node fallback minting tool and the checkout test script.
  See [valt-mint/README.md](valt-mint/README.md).

## Added at M4 (Launch & Rollout)

- **In-page collect checkout** (`valt-platform/includes/anvil.php`) replacing the hosted NMKR Pay redirect for switched songs (the featured releases, London and Freakshow), with a pending/confirmation panel in `valt-platform/assets/js/valt-platform.js`.
- **Holder-only video** (`valt-platform/includes/media-gate.php`, `valt-platform/assets/js/hls-video.js`) behind per-artist gating.
- **Listen-first player** (`valt-theme/assets/js/player.js`) and the **artist intake** form (`valt-theme/functions/intake/`), alongside the in-product **feedback survey** added at M3 (`valt-theme/functions/survey/`).
- **mu-plugins/**: login throttling and slug rescue redirects from the rollout bug log (R-17, R-18), and the read-only survey-pulse endpoint.
- **valt-mint/**: a Node fallback minting tool (dry-run by default, keys kept outside the repo) and `bin/collect-test.js`, the script behind the disclosed multi-wallet checkout test in the M4 bug log.

Every change is recorded with its root cause in [docs/M4_LaunchRollout/M4_Rollout_Bug_Log.pdf](../docs/M4_LaunchRollout/M4_Rollout_Bug_Log.pdf).

## Tech stack

| Layer | Technology |
|-------|-----------|
| Application | WordPress (headless-capable app layer) |
| Web3 / superfan logic | `valt-platform` plugin |
| Front-end | `valt-theme` (Hello Elementor child) + Elementor Pro + Alpine.js |
| Wallet / CIP-30 | CardanoPress |
| Collect checkout (since M4) | Anvil API (featured releases): one transaction pays and mints to the buyer; the server verifies it and co-signs with the project policy key |
| Minting / NFT mgmt / IPFS | NMKR (pre-production): NMKR Pay checkout for the rest of the catalog, song uploads; Pinata for IPFS |
| On-chain queries | Blockfrost (pre-production), through CardanoPress's wallet asset sync |
| Content modeling | Pods: `artist`, `album`, `song` custom post types |

```
  Fan browser (CIP-30 wallet: Eternl, Lace, ...)
        | connect / collect
        v
  valt.digital (WordPress)
    - valt-theme   : home, discover, artist + "The Valt", song page, collection
    - valt-platform: discovery, mint/collect, token-gating, NFT Monitor, REST API
        |                    |                      |
        v                    v                      v
     Anvil (preprod)      NMKR (preprod)         Blockfrost (preprod, via CardanoPress)
   in-page checkout     NMKR Pay + uploads     ownership / asset sync
```

Both components are intentionally decoupled: **valt-theme** handles presentation and CMS wiring;
**valt-platform** handles platform-specific logic (gating, minting, dashboards). No build step:
edit PHP/CSS/JS directly; WordPress loads changes on refresh.

## Key modules (`valt-platform/includes/`)

| File | Responsibility |
|------|----------------|
| `gating.php` | NFT-ownership gating: resolves each held NFT to its artist and unlocks that artist's Valt |
| `nmkr.php` | NMKR flow: CIP-25 metadata with CIP-60 music fields, IPFS (Pinata), `UploadNft` → `MintAndSendSpecific`, status polling, per-song inventory |
| `discovery.php` / `rest-api.php` | Artist/song discovery + `/wp-json/valt/v1/` endpoints |
| `ajax-handlers.php` | Admin mint / upload, follow and dashboard actions (some serve the dormant modules) |
| `shortcodes-new.php` | Front-end building blocks (mint button, spotlight, song grids, tracklist, ...) |
| `admin-nft-monitor.php` | Admin **NFT Monitor**: NMKR project stats, policy, mint event log |
| `helpers.php` | Config (keys via WP options) + the NMKR request wrapper |
| `anvil.php` (M4) | In-page collect checkout: edition ledger with short holds, Anvil build, transaction verification before the policy co-signature, submit, on-chain status, WP-CLI tools (`wp valt anvil ...`) |
| `checkout.php` (M4) | Multi-edition NMKR Pay checkout (1 to 5 editions) and the collect token |
| `media-gate.php` (M4) | Holder-only video: files stored outside the web root, streamed only to wallets holding that artist's NFT (HLS, range requests) |

## How NFT-gating works

1. A fan connects a Cardano wallet (CIP-30, via CardanoPress).
2. On an artist page, `gating.php` reads the wallet's on-chain assets and checks whether any belong
   to **this artist** under the project policy. All songs share one policy, so the artist is resolved
   from each NFT's on-chain metadata, falling back to the local NFT registry by token name.
3. If the wallet holds one of the artist's song NFTs, **The Valt unlocks**; otherwise it stays locked
   and prompts the fan to collect. (See `valt-theme/single-artist.php`.)

## How minting works

Minting happens **at collect time**: there is no pre-minted stock sitting in a wallet. The checkout is
chosen per song:

- **In-page Anvil checkout** (featured releases, London and Freakshow): the server reserves edition
  numbers, Anvil builds one transaction that pays the price and mints the editions to the buyer, the
  server verifies it and adds the policy signature, and the fan's wallet signs. See `anvil.php`.
- **NMKR Pay** (the rest of the catalog): editions are uploaded to the NMKR project and NMKR mints
  the edition to the buyer on payment. The original M2 flow (`UploadNft` then `MintAndSendSpecific`,
  with status polling) is described in the Development Report
  (`docs/M2_Development/M2_Development_Report.pdf`, §6).

All editions are minted under the one project policy on the Cardano preprod testnet.

## Milestone 2 evidence

`docs/M2_Development/`: **Proof of Achievement**, Development Report, Launch Partner Roster, and the
screenshot set (E1-E12 and S1-S5, incl. the minting sequence). On-chain proof: the policy above on **preprod.cardanoscan.io**.
