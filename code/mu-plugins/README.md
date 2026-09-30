# mu-plugins

Must-use plugins deployed to `wp-content/mu-plugins/`. WordPress loads them automatically, before
regular plugins, and they cannot be deactivated from wp-admin.

## valt-login-throttle.php (bug log R-17)

Rate-limits failed `wp-login.php` attempts per IP to blunt brute-force and credential-stuffing, since
there is no web application firewall in front of WordPress. After too many failures from one IP in a
window, further attempts from that IP are refused (correct passwords included) until the lockout
expires on its own.

| Constant | Default | Meaning |
|----------|---------|---------|
| `VALT_LOGIN_MAX_FAILS` | 5 | Failures allowed before lockout |
| `VALT_LOGIN_WINDOW` | 900 | Seconds over which failures are counted |
| `VALT_LOGIN_LOCKOUT` | 900 | Seconds an IP stays locked once tripped |
| `VALT_TRUSTED_PROXY` | unset | If defined, trust the right-most `X-Forwarded-For` hop (only behind a proxy you control) |

## valt-slug-rescue.php (bug log R-18)

Conservative 301 redirects for hand-typed near misses of real page slugs (for example `/forartists` to
`/for-artists/`, and the old `/terms-2/` to `/terms/`). It exists because launch copy prints URLs as
plain text in places where they cannot be tapped, and a visitor who retyped one lost the hyphen and hit
a 404. Every entry must be an unambiguous guess at exactly one real page.

## valt-survey-pulse.php

A read-only `GET /wp-json/valt/v1/survey-pulse` endpoint that exposes the feedback survey's aggregate
(and response rows) to Awen's internal operations hub, which pulls it; the site never pushes. Requests
must carry an `X-Awen-Hub-Key` header matching the site's shared client key, stored in the site
settings (never in code). The survey itself lives in the theme: `code/valt-theme/functions/survey/`.
