<p align="center">
  <img src="docs/assets/valt-banner.png" alt="Valt - Token-gated music platform on Cardano" width="600">
</p>

<p align="center">
  <a href="https://www.valt.digital">valt.digital</a> &bull;
  <a href="https://projectcatalyst.io/funds/11/cardano-use-cases-concept/afrocharts-or-web3-artist-portal-awen">Catalyst Fund11</a> &bull;
  <a href="https://awen.online">Awen</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Catalyst-Fund11-3D3C56?style=flat-square" alt="Catalyst Fund11">
  <img src="https://img.shields.io/badge/grant-100%2C000%20ADA-C9A66B?style=flat-square" alt="100,000 ADA">
  <img src="https://img.shields.io/badge/code-MIT-E8C48B?style=flat-square" alt="MIT">
  <img src="https://img.shields.io/badge/network-Cardano%20preprod-0033AD?style=flat-square" alt="Cardano preprod">
</p>

---

# Valt | Web3 Artist Portal on Cardano

*Project Catalyst Fund 11: "Afrocharts | Web3 Artist Portal" (Awen), Project #1100019*

Valt is an open-source, token-gated music platform where independent artists publish their music and fans **collect songs as Cardano NFTs** to unlock exclusive, per-artist content ("The Valt"). Access follows **on-chain ownership**: content is withheld on the server, never merely hidden in the browser.

Built on WordPress, powered by Cardano, and funded by **Project Catalyst Fund 11**.

## Features

- **Server-side NFT token-gating** - content is released only to wallets that hold the artist's song NFT, checked against the CardanoPress asset cache on the server
- **Artist Valts** - per-artist gated fan-club zones, configurable from the artist dashboard
- **Collect songs as Cardano NFTs** - an in-page wallet checkout for featured releases (built on the Anvil transaction API, 1 to 5 editions per order, payment and mint in one transaction) plus NMKR Pay for the rest of the catalogue
- **Artist Dashboard** - a frontend profile editor and release manager with media uploaders
- **In-product feedback survey** - a persisted NPS and ownership-model survey (the M3 feedback deliverable), live at [/feedback](https://www.valt.digital/feedback/)
- **REST API** - namespaced discovery and ownership-status endpoints (`/wp-json/valt/v1/`)
- **Automated tests and CI** - a PHPUnit suite plus a load harness, run on PHP 8.1-8.3 via GitHub Actions on every push

## Repository Structure

```
valt/
├── code/                      # Curated open-source subset of the platform
│   ├── valt-platform/         # Token-gating, checkout (Anvil + NMKR), REST API, artist dashboard
│   ├── valt-theme/            # Hello Elementor child: templates, player, survey, intake
│   ├── mu-plugins/            # Login throttle, slug redirects, survey prompt
│   └── valt-mint/             # Node fallback minting tool + disclosed checkout test script
├── docs/                      # Project Catalyst milestone evidence
│   ├── M1_Initialization/     # Setup report, API docs, status + timeline
│   ├── M2_Development/        # Development report, launch-partner roster, evidence
│   ├── M3_Implementation/     # Test & bug-fix, security audit, feedback + roadmap, PoA
│   ├── M4_LaunchRollout/      # PoA, rollout bug log, status + timeline
│   └── M5_Closeout/           # final report, close-out report, video script
├── tests/                     # PHPUnit suite + load harness
└── README.md
```

> **[/code](code/README.md)** - the curated open-source platform (plugin + theme)
>
> **[/docs](docs/README.md)** - Catalyst milestone reports, design documents, and the project timeline

## Tech Stack

| Layer | Technology |
|-------|-----------|
| CMS | WordPress 6+ |
| Parent Theme | Hello Elementor |
| Page Builder | Elementor Pro |
| Data Layer | Pods (CPTs: Artists, Albums, Songs) |
| Wallet | CardanoPress (CIP-30 connection, delegation, NFT assets) |
| Minting / checkout | Anvil transaction API (in-page checkout, project policy key) and NMKR (NMKR Pay, IPFS pinning) |
| Token-Gating | valt-platform plugin (server-side, CardanoPress asset cache) |
| Tests / CI | PHPUnit + GitHub Actions (PHP 8.1-8.3) + semgrep |

## Project Catalyst Fund 11

Valt is funded by a **100,000 ADA** grant from [Cardano Project Catalyst Fund 11](https://projectcatalyst.io/funds/11/cardano-use-cases-concept/afrocharts-or-web3-artist-portal-awen) under the **Cardano Use Cases** category (Project #1100019).

| Milestone | Focus | Status |
|-----------|-------|--------|
| M1 | Initialization - infrastructure & design | Delivered |
| M2 | Development - core platform & launch partners | Delivered - [evidence](docs/M2_Development/) |
| M3 | Implementation & Prelaunch - testing, security, feedback | Evidence published - [evidence](docs/M3_Implementation/) |
| M4 | Launch & Rollout - public launch, announcements, rollout support | Evidence published - [evidence](docs/M4_LaunchRollout/) |
| M5 | Closeout & Evaluation - feedback, artist evaluation, final report, closeout video | Evidence published - [evidence](docs/M5_Closeout/) |

## Getting Started

See **[code/README.md](code/README.md)** for installation, configuration, and the shortcode and REST API reference. The published build is verified on every push: `composer install && vendor/bin/phpunit` (expect `OK (19 tests, 41 assertions)`), and `php tests/load-plugin.php code/valt-platform` reports `VERDICT=LOADED`.

## License

The code released in this repository is licensed under the **[MIT License](LICENSE)**, as committed in the Project Catalyst Fund 11 application. Copyright (c) 2026 Awen LLC.

The hosted Valt platform at [valt.digital](https://www.valt.digital), along with platform-specific operational logic that is not part of this repository (the NMKR minting pipeline, feedback-pulse bridge, admin tooling, etc.), remains proprietary to Awen LLC.

---

<p align="center">
  <a href="https://awen.online">
    <img src="https://awen.online/wp-content/uploads/2025/01/Awen-Logo-2.0-Full-Final.png" alt="Awen" width="120">
  </a>
</p>
<p align="center">
  Built by <a href="https://awen.online">Awen</a>
</p>
