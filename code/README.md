# Valt — Open-Source Core

**Valt** is a token-gated music platform on **Cardano**: independent artists publish songs, fans
collect those songs as NFTs, and holding an artist's song NFT unlocks that artist's private
**"Valt"** — exclusive, on-chain-gated content.

- **Live portal:** https://www.valt.digital (Cardano pre-production testnet)
- **Project Catalyst:** Fund 11, Project **#1100019**
- **Policy ID:** `bf5a88ac0a236c22c2772a51ff2fa33301e17c42aa8f95fcd585b86a` (verify on preprod.cardanoscan.io)

> **Scope.** This is the **curated open-source core** published for Project Catalyst review — the
> application logic that delivers the milestone's graded functionality (wallet connection, minting,
> NFT-gating). Operational deploy tooling and a few non-milestone modules (payments, gamification)
> live in a private repository and are out of scope here.
>
> **No secrets are committed.** Credentials (NMKR API key, Blockfrost project id, Pinata JWT) are
> read at runtime from WordPress options / PHP constants, never from source — see
> `valt-platform/includes/helpers.php` (`valt_nmkr_config()`).

## Contents

- **[valt-platform/](valt-platform/)** — standalone WordPress plugin: server-side NFT token-gating,
  the on-the-fly minting flow (NMKR), discovery, REST API, the Artist Dashboard, and an admin
  **NFT Monitor**. See [valt-platform/README.md](valt-platform/README.md) for the shortcode/API reference.
- **[valt-theme/](valt-theme/)** — Hello Elementor child theme: home, discover, artist page +
  **The Valt**, song page + Collect/mint, and the wallet collection, with Pods (Artists/Albums/Songs)
  and CardanoPress wiring.

## Tech stack

| Layer | Technology |
|-------|-----------|
| Application | WordPress (headless-capable app layer) |
| Web3 / superfan logic | `valt-platform` plugin |
| Front-end | `valt-theme` (Hello Elementor child) + Elementor Pro + Alpine.js |
| Wallet / CIP-30 | CardanoPress |
| Minting / NFT mgmt / IPFS | NMKR (pre-production) |
| On-chain queries | Blockfrost (pre-production) |
| Content modeling | Pods — `artist`, `album`, `song` custom post types |

```
  Fan browser (CIP-30 wallet: Eternl, Nami, Lace, ...)
        | connect / collect
        v
  valt.digital (WordPress)
    - valt-theme   : home, discover, artist + "The Valt", song page, collection
    - valt-platform: discovery, mint/collect, token-gating, NFT Monitor, REST API
        |                         |
        v                         v
     NMKR (preprod)          Blockfrost (preprod)
   minting + IPFS pinning    ownership / chain queries
```

Both components are intentionally decoupled: **valt-theme** handles presentation and CMS wiring;
**valt-platform** handles platform-specific logic (gating, minting, dashboards). No build step —
edit PHP/CSS/JS directly; WordPress loads changes on refresh.

## Key modules (`valt-platform/includes/`)

| File | Responsibility |
|------|----------------|
| `gating.php` | NFT-ownership gating — resolves each held NFT to its artist and unlocks that artist's Valt |
| `nmkr.php` | Minting flow: CIP-25 metadata, IPFS, `UploadNft` → `MintAndSendSpecific`, status polling |
| `discovery.php` / `rest-api.php` | Artist/song discovery + `/wp-json/valt/v1/` endpoints |
| `ajax-handlers.php` | Collect / mint / follow actions |
| `shortcodes-new.php` | Front-end building blocks (mint button, song grids, collection, …) |
| `admin-nft-monitor.php` | Admin **NFT Monitor** — NMKR project stats, policy, mint event log |
| `helpers.php` | Config (keys via WP options) + the NMKR request wrapper |

## How NFT-gating works

1. A fan connects a Cardano wallet (CIP-30, via CardanoPress).
2. On an artist page, `gating.php` reads the wallet's on-chain assets and checks whether any belong
   to **this artist** under the project policy. All songs share one policy, so the artist is resolved
   from each NFT's on-chain metadata, falling back to the local NFT registry by token name.
3. If the wallet holds one of the artist's song NFTs, **The Valt unlocks**; otherwise it stays locked
   and prompts the fan to collect. (See `valt-theme/single-artist.php`.)

## How minting works

Minting is **on-the-fly** — there is no pre-minted inventory. A "Collect" registers a fresh NFT in
the NMKR project (`UploadNft`) and mints it to the fan's wallet (`MintAndSendSpecific`), then polls
for confirmation. Full sequence: `docs/M2_Development/M2_Minting_Flow.md`.

## Milestone 2 evidence

`docs/M2_Development/` — **Proof of Achievement**, Development Report, Launch Partner Roster, and the
screenshot set (E1–E9). On-chain proof: the policy above on **preprod.cardanoscan.io**.
