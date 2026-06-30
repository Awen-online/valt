# Valt — Milestone 2: Development Report

**Project:** Valt — Superfan Experience Engine (Web3 Artist Portal)
**Project Catalyst:** Fund 11, Project #1100019
**Milestone:** 2 — Development
**Updated:** 2026-06-17
**Live deployment:** https://www.valt.digital

---

## Table of Contents

1. [Milestone Overview](#1-milestone-overview)
2. [Acceptance Criteria → Evidence Map](#2-acceptance-criteria--evidence-map)
3. [Web3 Portal Backend (Deliverable 2)](#3-web3-portal-backend-deliverable-2)
   - [Architecture as Built](#architecture-as-built)
   - [Platform & Feature Inventory](#platform--feature-inventory)
   - [REST API](#rest-api)
   - [Data Model](#data-model)
4. [TestNet NFT Proof (Deliverable 1)](#4-testnet-nft-proof-deliverable-1)
   - [NMKR Pre-Production Project](#nmkr-pre-production-project)
   - [Music Metadata Standard](#music-metadata-standard)
   - [On-Chain Evidence](#on-chain-evidence)
5. [User Flow (As Built)](#5-user-flow-as-built)
6. [Supporting Tasks](#6-supporting-tasks)
7. [Evidence & Screenshots](#7-evidence--screenshots)
8. [Repository & External Documents](#8-repository--external-documents)

---

## 1. Milestone Overview

Milestone 2 (Development) delivers a working Web3 artist portal with on-chain NFT
functionality on the Cardano pre-production testnet. Where Milestone 1 established the
design and integration plan, Milestone 2 implements it: a deployed, wallet-connected
platform where fans can collect songs as NFT editions and unlock token-gated artist
content.

The platform is **live and publicly accessible at https://www.valt.digital**, running on
the Cardano pre-production testnet (`preprod`) as is appropriate for the development
milestone. Mainnet launch is scheduled for a later milestone following security review
and QA.

The two registered deliverables for this milestone are:

1. **TestNet NFT proof** — demonstrable minting of artist songs as NFTs on the Cardano testnet.
2. **Web3 Portal Backend** — the functional portal: discovery, wallet connection, minting, and NFT-gated artist content.

---

## 2. Acceptance Criteria → Evidence Map

| # | Acceptance criterion | Status | Evidence |
|---|----------------------|--------|----------|
| 1 | TestNet NFT proof | ✅ Met | NMKR preprod project live; songs minted on-chain (policy `bf5a88ac…`); §4, screenshots E1–E3 |
| 2 | Web3 Portal Backend | ✅ Met | Live portal at valt.digital; §3, screenshots E4–E9 |
| 3 | Partner songs ready | ✅ Met | 12 songs / 2 artists published with full metadata; §3 |
| 4 | Testnet backend | ✅ Met | `valt_nmkr_mode = preprod`, Blockfrost + NMKR integration; §3–4 |
| 5 | Mint song as NFT | ✅ Met | Mint/Collect flow on every song page; §4–5, screenshot E2 |
| 6 | NFT-restricted artist access | ✅ Met | "The Valt" token-gated content unlocks for NFT holders; §5, screenshot E6 |
| 7 | Repository updated | ✅ Met | `github.com/Awen-online/valt` @ `main`; §8 |

---

## 3. Web3 Portal Backend (Deliverable 2)

### Architecture as Built

The portal is built on WordPress as a headless-capable application layer, with a custom
plugin (`valt-platform`) providing all Web3 and superfan functionality, and a custom theme
(`valt-theme`) providing the front-end experience. Cardano integration is handled through
CardanoPress (wallet connection / CIP-30), NMKR (minting and NFT management), and Blockfrost
(chain queries).

```
                         ┌─────────────────────────────┐
   Fan browser (CIP-30   │      valt.digital (WP)       │
   wallet: Eternl, Nami, │                              │
   Lace, Flint, …)       │  valt-theme (Hello Elementor │
        │                │     child + Alpine.js)       │
        │  Wallet Connect │  valt-platform plugin v2.0.0 │
        ├───────────────►│   • discovery / song grid    │
        │                │   • mint / collect           │
        │                │   • The Valt (token gating)  │
        │                │   • follow / fan dashboard    │
        │                │   • NFT Monitor (admin)       │
        │                │  CardanoPress v1.33.0         │
        │                │  Pods (content modeling)      │
        │                └──────┬───────────┬───────────┘
        │                       │           │
        │                  ┌────▼────┐  ┌───▼─────────┐
        └─────────────────►│  NMKR   │  │  Blockfrost  │
          mint / collect   │ (preprod)│  │   (preprod)  │
                           └─────────┘  └──────────────┘
                          minting + IPFS    chain queries
```

**Stack summary**

| Layer | Technology |
|-------|-----------|
| Application | WordPress |
| Web3 / superfan logic | `valt-platform` plugin v2.0.0 (22+ PHP modules) |
| Front-end | `valt-theme` (Hello Elementor child), Elementor Pro, Alpine.js |
| Wallet / CIP-30 | CardanoPress v1.33.0 |
| Minting / NFT mgmt | NMKR (pre-production) |
| Chain queries | Blockfrost (pre-production) |
| Content modeling | Pods (Artists, Songs custom post types) |
| Music player | fml-music-player |
| Hosting | DreamHost (https://www.valt.digital) |

### Platform & Feature Inventory

The following surfaces are live and verified on production:

| Section | Route | Function |
|---------|-------|----------|
| Header / navigation | global | Wallet-aware nav, connect button |
| Home | `/` | 12-song grid, trending artists, "owned" badges for connected wallets |
| Discover | `/discover/` | Filterable artist directory (genre / country), REST-driven |
| Artist page + **The Valt** | `/artist/{slug}/` | Artist profile, follow button, token-gated vault that opens for NFT holders |
| Song page | `/song/{slug}/` | Full CIP music metadata, Mint/Collect button, Cardanoscan link |
| Collection ("My Valt") | `/collection/` | Wallet's owned NFT editions (CardanoPress) |
| Fan Dashboard | `/fan-dashboard/` | Connected fan's activity, follows, owned editions |
| Contact | `/contact/` | Contact form |
| FAQ | `/faq/` | Fan onboarding / wallet / testnet explainer |
| NFT Monitor (admin) | wp-admin | NMKR sync, bulk upload, mint event log |

Feature-flagged sections (gamification / leaderboard / campaigns) exist in code but are
intentionally disabled for this milestone and excluded from navigation.

**Published content:** 2 artists (Cullah — 7 songs; Mie — 5 songs), 12 songs total, each
with album art and full metadata (songwriter, producer, copyright, license, genre, duration,
description).

### REST API

The plugin exposes a namespaced REST API (`/wp-json/valt/v1/`) consumed by the front-end:

| Endpoint | Purpose |
|----------|---------|
| `GET /discover/artists` | Artist directory with filters |
| `GET /discover/genres` | Genre facets |
| `GET /discover/trending` | Trending artists |
| `GET /nft/status/{song_id}` | Wallet's ownership status for a song (auth) |
| `GET /leaderboard`, `/user/points`, `/user/badges` | Gamification (flagged off) |
| `POST /campaigns/{id}/pledge`, `/stripe/*` | Campaigns / payments (flagged off) |

### Data Model

Custom tables (active): `valt_follows` (wallet-verified follows), `valt_nft_registry`
(per-song asset name + policy id, metadata fallback for collection display). Artists and
Songs are modeled as Pods custom post types with artist↔song relationships.

---

## 4. TestNet NFT Proof (Deliverable 1)

### NMKR Pre-Production Project

Songs are minted as NFTs through an NMKR pre-production project:

| Field | Value |
|-------|-------|
| Network | Cardano **pre-production testnet** |
| NMKR project UID | `2b486bb9-fa80-4229-833d-3442f5f75820` |
| Policy ID | `bf5a88ac0a236c22c2772a51ff2fa33301e17c42aa8f95fcd585b86a` |
| Metadata standard | CIP-25 |
| Edition model | Limited numbered editions per song (50 copies/song design) |
| Token naming | `valt` + clean song slug + id (e.g. `valtwarpspasm258`) |

Each of the 12 songs is registered in the platform's NFT registry with its on-chain asset
name and policy id, enabling collection display and ownership verification.

### Music Metadata Standard

NFTs carry CIP-25 music metadata (v3 profile): authors, contributing artists, copyright,
duration, and edition number — so each collected song is a self-describing, standards-
compliant music NFT.

### On-Chain Evidence

The NMKR pre-production project shows real mint activity (minted, sold, and reserved
editions) — confirming end-to-end minting on the testnet. Individual tokens are verifiable
on **preprod.cardanoscan.io** under the policy id above. See screenshots E1–E3.

> Token verification URL pattern:
> `https://preprod.cardanoscan.io/token/<policyId><hexAssetName>`

---

## 5. User Flow (As Built)

The implemented user flow matches the Milestone 1 design:

```
  Home / Discover ──► Artist Page ──► Song Page
        │                 │              │
        │                 │              ▼
        │                 │        Mint / Collect ──► NMKR (preprod)
        ▼                 ▼              │
   Connect Wallet ──► Wallet Dashboard ◄─┘
   (CIP-30)               │
                          ▼
                  Owns song NFT? ──Yes──► The Valt (token-gated content)
                          │
                          └──No──► prompt to Mint / Collect
```

- **Home / Discover / Browse** — explore songs and artists; "owned" badges appear once a wallet is connected.
- **Artist Page** — artist profile and **The Valt**, the artist-specific vault.
- **Song Page** — listen, view full metadata, and Mint/Collect the song NFT.
- **Wallet Connect (CIP-30)** — connect any Cardano wallet to authenticate ownership.
- **Wallet Dashboard / Collection** — view owned editions.
- **Token gate** — holding an artist's song NFT unlocks that artist's Valt content; non-holders are prompted to collect.

---

## 6. Supporting Tasks

- **Design system** — implemented as the live design system (Navy/Gold/Cream palette, Ruda type, animated vinyl-record + vault-handle logo, WCAG-AA buttons).
- **Partner songs ready** — 12 songs from 2 partner artists published with full metadata and album art.
- **Testnet backend** — NMKR + Blockfrost in pre-production mode; `valt_nmkr_mode = preprod`.
- **Mint song as NFT** — Mint/Collect implemented on every song page; mints route through NMKR preprod.
- **NFT-restricted artist access** — "The Valt" gates content by on-chain NFT ownership.
- **Repository updated** — all code in `github.com/Awen-online/valt` (`main`).

---

## 7. Evidence & Screenshots

> Capture these from the live site (https://www.valt.digital) and NMKR dashboard, then embed.

| ID | Screenshot | Where |
|----|-----------|-------|
| E1 | NMKR preprod dashboard — project `2b486bb9…`, mint/sold counts | NMKR |
| E2 | Song page Mint/Collect flow with wallet connected | `/song/{slug}/` |
| E3 | Minted token on preprod.cardanoscan.io (policy `bf5a88ac…`) | Cardanoscan |
| E4 | Home — 12-song grid live | `/` |
| E5 | Discover — artist directory | `/discover/` |
| E6 | **The Valt** — token-gated content unlocked for holder | `/artist/{slug}/` |
| E7 | Collection — wallet's owned editions | `/collection/` |
| E8 | NFT Monitor admin — NMKR sync + event log | wp-admin |
| E9 | Browser showing live public URL **https://www.valt.digital** | — |

---

## 8. Repository & External Documents

- **Repository:** https://github.com/Awen-online/valt (branch `main`)
- **Live portal:** https://www.valt.digital
- **Milestone 1 deliverables:** `docs/M1_Initialization/` (Setup Report, Partner API Documentation, Project Status Report, Project Timeline)
- **Points / rewards design:** `docs/VALT_POINTS_WHITEPAPER.md`
