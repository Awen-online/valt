<p align="center">
  <img src="https://objects-us-east-1.dream.io/website-backup-wsc/Assets/github/1-f531f7.png" alt="Afrocharts Web3 Portal / Valt">
</p>

# Afrocharts | Web3 Artist Portal (Awen)

## aka Valt

**Websites:** [Catalyst Proposal](https://projectcatalyst.io/funds/11/cardano-use-cases-concept/afrocharts-or-web3-artist-portal-awen) | [valt.digital](https://www.valt.digital) | [Afrocharts](https://www.afrocharts.com)


Welcome to the official GitHub repository for **Valt** aka Afrocharts Web3 Portal. We are an ambitious initiative spearheaded by Awen aimed at transforming the accessibility and integration of music through Web3 technologies using Cardano blockchain.

In the rapidly evolving landscape of digital music and interactive experiences, the necessity for a streamlined, equitable, and innovative method to feature African music within these domains grows ever more critical.

Valt is poised to meet this challenge head-on with the an exciting partnership with Afrocharts.

## Our Vision

Our mission is to revolutionize the way music is utilized in the digital realm. Our vision is a transparent, efficient, and fair ecosystem where artists receive fair recognition and compensation for the impact and cultural contributions.

Valt embodies a streamlined vision for a more connected and financially equitable music industry, where artists and fans engage in meaningful exchanges.

## What Makes Valt Unique?
**Proof of Verified Ownership:** Valt introduces a secure way to collect and manage music licenses using blockchain. This ensures artists are compensated and provides collectors with a platform to prove ownership, enhancing the value and enjoyment of digital music collecting.

**Direct-to-Fan Exclusivity:** Through Valt, artists offer exclusive content directly to fans, fostering intimate relationships and unique music experiences. This direct line enhances the fan experience with personal touches and rare content.

**A Community-Driven Marketplace:** At its core, Valt features a marketplace driven by its community, allowing fans to buy, sell, and trade music licenses. This not only supports artists but also offers fans a way to monetize their collections, creating a lively ecosystem where music assets circulate, benefiting all participants.

---

## Repository Structure

```
/code     Source code for WordPress theme and platform plugin
/docs     Project documentation, reports, and API references
```

> [/docs](docs/README.md) — Comprehensive documentation including technical details, user guides, and design overviews.
>
> [/code](code/README.md) — Source code for the WordPress theme and platform plugin.

---

## Codebase Overview

The platform runs on **WordPress** and is split into two custom components:

### [valt-theme](code/valt-theme/)
WordPress child theme of Hello Elementor. Responsibilities:
- Elementor Pro page templates and dynamic query hooks
- Pods CPT integration (Artists, Albums, Songs — relationships, fields, dynamic tags)
- CardanoPress template overrides (collection page, dashboard)
- Frontend asset pipeline (Ruda font, Valt colour palette)
- Afrocharts data sync script

### [valt-platform](code/valt-platform/)
Standalone WordPress plugin. Responsibilities:
- **Server-side NFT token-gating** via CardanoPress policy ID check — content is withheld on the server, never just CSS-hidden
- **Artist Valt** — per-artist gated fan-club zone configurable from the artist dashboard
- **Artist Dashboard** — frontend profile editor and release manager with `wp.media()` uploaders
- Six Elementor-droppable shortcodes
- Admin meta boxes (Song release status, Artist policy ID column)

### Tech Stack

| Layer | Technology |
|-------|------------|
| CMS | WordPress 6+ |
| Parent Theme | Hello Elementor |
| Page Builder | Elementor Pro |
| Data Layer | Pods (CPTs: Artists, Albums, Songs) |
| Blockchain | CardanoPress (Cardano wallet, delegation, NFT assets) |
| Token-Gating | valt-platform plugin (server-side, CardanoPress API) |
| Local Dev | Local by Flywheel |

---

## Shortcodes at a Glance

The `valt-platform` plugin exposes these shortcodes (full reference in [code/valt-platform/README.md](code/valt-platform/README.md) and in the **Valt Platform → Shortcode Reference** page in wp-admin):

| Shortcode | Type | Purpose |
|-----------|------|---------|
| `[valt_gated_content]` | Enclosing | Server-side NFT gate — non-holders never receive the HTML |
| `[valt_connect_prompt]` | Self-closing | CardanoPress wallet connect button; silent if already connected |
| `[valt_artist_profile]` | Self-closing | Public artist card: photo, name, genre, country, bio |
| `[valt_artist_valt]` | Enclosing | Public artist header + gated fan-club zone |
| `[valt_artist_dashboard]` | Self-closing | Full frontend artist profile & release management dashboard |
| `[valt_release_status]` | Self-closing | Inline badge: Uploaded / In NFT Collection / Minted |

---

## Join the Movement

Valt is more than just a project; it's a pioneering movement aimed at elevating the presence and impact of African music in digital spaces worldwide. Whether you're an artist aspiring to globalize your music, a developer in search of unique soundtracks for your projects, or an enthusiast passionate about the convergence of music and technology, we welcome you to join us.

For more information, to participate in our journey, or to share your ideas and feedback, please contact us at info@valt.digital.

Let's embark on this exciting journey together to redefine the future!
