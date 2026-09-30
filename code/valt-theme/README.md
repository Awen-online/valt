# valt-theme

Custom WordPress child theme for [valt.digital](https://valt.digital), a token-gated music platform on the Cardano preprod testnet where fans collect songs as NFTs from artists, albums and songs managed in WordPress.

## Overview

`valt-theme` is a child theme of [Hello Elementor](https://elementor.com/hello-theme/), extending it with:

- Cardano wallet connection and delegation UI (via [CardanoPress](https://cardanopress.io/))
- Custom content types for Artists, Albums, and Songs (via [Pods](https://pods.io/))
- Dynamic Elementor query filters driven by Pods relationships
- Subscriber access restrictions (no backend access, no admin bar)
- Custom CSS and JavaScript for the site's visual design
- A listen-first layout with a persistent audio player (M4)
- An in-product feedback survey and an artist intake form (M3/M4 feedback and artist evaluation)

## Tech Stack

| Layer | Technology |
|---|---|
| CMS | WordPress |
| Parent theme | Hello Elementor |
| Page builder | Elementor Pro |
| Data layer | Pods framework |
| Blockchain | CardanoPress (CIP-30 wallets, Cardano preprod) |
| Local dev | Local by Flywheel |

## Project Structure

```
valt-theme/
|-- style.css                    Theme header (declares parent: Hello Elementor)
|-- functions.php                Assets (versioned by file time), subscriber restrictions, module loaders
|-- page-valt.php                Full-page template: hero (image or looping video), CTA cards
|-- single-artist.php            Artist page and the Valt (gated zone, collected/sync states)
|-- single-song.php              Song page: player, collect / sold-out panel, about
|-- 404.php                      Branded not-found page
|-- assets/
|   |-- css/main.css             Primary styles (player, spotlight, checkout, sold-out, survey hooks)
|   |-- css/survey.css, intake.css
|   |-- js/player.js             (M4) Persistent listen-first player
|   |-- js/survey.js, intake.js  Feedback survey and artist intake front ends
|   `-- img/                     Logo, favicon, share card (valt-og.png)
|-- functions/
|   |-- site-chrome.php          Nav, footer, meta/Open Graph tags
|   |-- svg-icons.php            Inline icon set
|   |-- survey/                  (M3/M4) Feedback survey, see functions/survey/README.md
|   |-- intake/                  (M4) Artist intake form and leads screen
|   |-- pods/datatag/            Elementor dynamic tag: related artist featured image
|   |-- elementor.php            Elementor query filters via Pods relationships
|   |-- pods.php                 Pods framework integration
|   `-- shortcodes/pods_artist_featured_image.php
|-- scripts/afrocharts-api-sync.py   Historical AfroCharts API song import (M1 era)
`-- cardanopress/                Cardano wallet template overrides
```

## Listen-first player (M4)

`assets/js/player.js` renders one persistent bar at the bottom of every page. Any element with
`data-valt-track='{"id","title","url","artist","artist_url","art","src"}'` is a play control:
clicking it queues every playable track on the page (or inside the container named by
`data-valt-queue`) and starts from that track. The queue and position are kept in
`sessionStorage`, so the bar survives navigation (paused, at the same spot). Song cards, the
spotlight and tracklists from `valt-platform` emit these attributes.

## Hero video

`page-valt.php` reads the page's hero meta (`_valt_hero_video`, `_valt_hero_image`, title,
kicker, CTA labels and subtexts). With a video set, it renders a muted, looping background video;
visitors who prefer reduced motion get the first frame held instead of playback.

## Collect, collected and sold-out states

The song page's collect block comes from `[valt_connect_mint]` in `valt-platform`:

- **Collecting:** the Collect button and edition picker lock for the whole flow. After signing, a
  status panel replaces them (signed, sent, confirming on-chain, in your wallet) and offers
  "Open the Valt" and "Collect more" only once the transaction is confirmed.
- **Arriving from a collect:** the artist page (`?collected=1`) explains the next step (connect
  the same wallet, or sync it) instead of showing "You don't hold a song yet".
- **Sold out:** a calm sold-out panel with the edition count, links to the artist and Discover;
  the heading reads "Edition Sold Out".

## Feedback survey (M3/M4)

`functions/survey/` is a triggered, multi-step survey (NPS, reasons for visiting, ease of
collecting, feature requests, how visitors found Valt), with a `[valt_survey]` trigger shortcode
and a `[valt_survey_form]` inline form. Responses are stored in the site database and reviewed in
**Tools > Valt Feedback**. See [functions/survey/README.md](functions/survey/README.md).

## Artist intake (M4)

`functions/intake/` provides `[valt_artist_intake]`, a low-friction application form for
prospective artists (used on the For Artists page), and `[valt_artist_benefits]`. Submissions go to
`POST /wp-json/valt/v1/artist-intake` (honeypot, consent and rate limiting) and are reviewed in
**Tools > Valt Artist Leads**, with an admin-only CSV export.

## Development

This theme is developed locally using **Local by Flywheel** (Nginx + PHP-FPM + MySQL).

### Asset Versioning

Theme and plugin assets are versioned by file modification time, so a deploy busts browser caches automatically.

### Adding Styles / Scripts

Enqueue new assets in the `wp_enqueue_scripts` hook inside `functions.php`.

### Elementor + Pods

`functions/elementor.php` hooks into Elementor's dynamic query system to filter content by Pods relationships, for example filtering Songs or Albums by a related Artist.

## Deployment

The live site is deployed from the project's private repository after review; this public copy mirrors the deployed theme for milestone review.
