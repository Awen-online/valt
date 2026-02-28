# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Custom WordPress child theme for valt.digital — a Cardano blockchain-integrated site for managing NFTs, digital assets, artists, albums, and songs. Child theme of Hello Elementor.

**Stack:** WordPress · Hello Elementor (parent) · Elementor Pro · Pods (custom post types) · CardanoPress (Cardano wallet) · Alpine.js · Tailwind CSS · Local by Flywheel (Nginx + PHP-FPM + MySQL)

## Development

No build step. Edit PHP/CSS/JS files directly; WordPress loads them on page refresh.

**Local URL:** typically `http://valt.local` via Local by Flywheel.

**Cache busting:** bump `$style_version` in `functions.php` when deploying CSS changes.

To enable the Three.js particle animation, uncomment the enqueue calls in `functions.php` (lines ~38–76).

## Architecture

### functions.php
Entry point. Handles:
- Enqueuing `assets/css/main.css` and `assets/css/cardanopress_styles.css`
- Subscriber access control: redirects subscribers from `/wp-admin` and hides the admin bar
- Requires all files in `functions/`

### functions/elementor.php
Hooks into Elementor's query system via `elementor/query/{filter_name}` to filter dynamic content using Pods relationship meta queries (e.g. filtering songs by a related artist field).

### functions/pods/datatag/Pods_Related_Artist_Featured_Image.php
A custom Elementor dynamic tag (extends `Data_Tag`) that retrieves the featured image URL from a related Pods object. Registered via `elementor/dynamic_tags/register`. Returns a URL/IMAGE category value for use in Elementor's image or URL controls.

### functions/shortcodes/pods_artist_featured_image.php
Registers `[pods_artist_featured_image pod="" field="" size="" class=""]` shortcode. Walks a Pods relationship field to get the related object's featured image URL. Contains extensive `error_log()` debug calls.

### cardanopress/
Template overrides for the CardanoPress plugin. WordPress loads these instead of the plugin's own templates. Key templates:
- `page/Dashboard.php` — main wallet dashboard
- `page/Collection.php` — NFT asset collection
- `modal-connect.php` — wallet connection modal
- `payment-form.php` — payment with reCAPTCHA
- `pool-delegation.php` — stake pool delegation UI
- `part/` — 21 reusable partials (modal, delegation flow, profile, assets, menu)

CardanoPress templates use Alpine.js for reactivity (`x-data`, `x-show`, `x-for`) and Tailwind CSS utility classes. Access the CardanoPress API via the `cardanoPress()` singleton (e.g. `cardanoPress()->userProfile()`, `cardanoPress()->option()`).

### Pods Custom Post Types and Relationships
Defined via the Pods plugin (not in theme PHP):
- **Artists**, **Albums**, **Songs**
- Songs → Artist (relationship field key: `artist`)
- Albums → Artist, Songs → Album

### scripts/afrocharts-api-sync.py
Standalone Python script that fetches song data from the Afrocharts REST API and writes it to `songs.xml`. Run manually when syncing external music data.

## Patterns

**Adding styles/scripts:** enqueue in the `wp_enqueue_scripts` hook in `functions.php`; set the version string for cache busting.

**Adding an Elementor query filter:** add a function hooked to `elementor/query/{your_filter_name}` in `functions/elementor.php` that receives a `$query` object and applies `WP_Query` args.

**Adding a CardanoPress template override:** copy the plugin's template file into `cardanopress/` maintaining the same relative path; WordPress will use the theme copy automatically.

**Debugging:** the codebase uses `error_log()` extensively in Pods/dynamic-tag code. Check the PHP error log (or Local's log viewer) when troubleshooting dynamic tag or shortcode issues.
