# Code

Source code for the Valt platform.

## Contents

> [/valt-theme](valt-theme/) — WordPress child theme of Hello Elementor. Handles Elementor Pro dynamic queries, Pods custom post types (Artists, Albums, Songs), CardanoPress wallet integration, and frontend assets.

> [/valt-platform](valt-platform/) — Standalone WordPress plugin providing server-side NFT token-gating, the Artist Valt fan-club zone, and the frontend Artist Dashboard (profile editing + release management). Exposes six Elementor-droppable shortcodes.

---

## Tech Stack

| Component | Technology |
|-----------|------------|
| CMS | WordPress |
| Parent Theme | Hello Elementor |
| Page Builder | Elementor Pro |
| Data Layer | Pods (custom post types: Artists, Albums, Songs) |
| Blockchain | CardanoPress (Cardano wallet, delegation, NFT collections) |
| Token-Gating | valt-platform plugin (server-side policy ID check via CardanoPress) |
| Local Dev | Local by Flywheel |

---

## Architecture overview

```
wp-content/
  themes/
    valt-theme/          Child theme — Elementor templates, Pods integration,
                         dynamic query hooks, CardanoPress overrides
  plugins/
    valt-platform/       Custom plugin — shortcodes, artist dashboard,
                         NFT gating, admin meta boxes
```

Both components are intentionally decoupled:
- **valt-theme** handles presentation and CMS wiring
- **valt-platform** handles all platform-specific business logic (gating, dashboards, release tracking)

No build step is required for either component. Edit PHP/CSS/JS files directly; WordPress loads changes on page refresh.

---

## Development

See [valt-theme/CLAUDE.md](valt-theme/CLAUDE.md) for theme-specific development guidance.

See [valt-platform/README.md](valt-platform/README.md) for full shortcode and API reference.
