# Valt Feedback Survey

A persisted, triggered feedback survey for valt.digital, ported from the proven
Sync.Land survey system and bridged to **Awen OS** the same way.

Collects: NPS (0–10), "what brought you to Valt" (multi-select), collect-ease
(1–5), a feature request (free text), and how-they-found-us, stored locally and
viewable in **Tools → Valt Feedback**, with the aggregate exposed to the Awen OS
hub via a read-only pull endpoint.

## Files

| File | Role |
|------|------|
| `schema.php` | Creates `{prefix}valt_survey_responses` (dbDelta). Runs on `after_switch_theme` and, lazily, on `init` so the table exists even when added to an already-active theme. |
| `data.php` | Settings, session cookie (`valt_session`), NPS / use-case aggregation, client IP. |
| `survey.php` | Footer modal markup, the `[valt_survey]` trigger shortcode and the `[valt_survey_form]` inline form. Question copy lives here. |
| `rest.php` | `valt/v1` routes: `POST /survey` (public submit), `GET /survey-results`, `GET /survey-export` (admin). |
| `admin.php` | Tools → Valt Feedback: NPS, use-cases, responses, CSV export, settings. |
| `loader.php` | Requires the modules; enqueues `survey.js` + `survey.css` with `VALTSurveyConfig`. Required from `functions.php`. |
| `../../assets/js/survey.js` | Trigger logic + multi-step flow + submit. Mirrors `survey_shown` / `survey_completed` into awen-client analytics when `window.awenAnalytics` is present. |
| `../../assets/css/survey.css` | Modal styling in the Valt palette. |
| `code/mu-plugins/valt-survey-pulse.php` | The Awen OS bridge (see below). |

## Triggers

`survey.js` shows the modal on the first matching condition (90-day dismissal):
manual (`[valt_survey]` shortcode, e.g. a `/feedback` page linked in outreach
emails), post-mint (`?minted=success` in the URL, or a `window.dispatchEvent(new
CustomEvent('valt:minted'))` from the mint flow), Nth visit (default 3), or
seconds on site (default 300). All thresholds are set in Tools → Valt Feedback.

## Wiring to Awen OS

Awen OS ingests survey data by **pulling** it: the hub calls in; Valt never
pushes. This mirrors Sync.Land's `sync/v1/survey-pulse`.

- **Endpoint:** `GET https://www.valt.digital/wp-json/valt/v1/survey-pulse`
- **Auth:** request header `X-Awen-Hub-Key` must equal the `awen_client_api_key`
  option, the **same shared site key the awen-client plugin already holds** for
  this node. Compared with `hash_equals`; fails closed if the key is unset.
- **Returns:** `node_id`, `generated_at`, `nps` (average / index / promoter split
  / distribution), `use_cases` (counts), `response_count`, `last_response_at`, and
  raw `responses[]` (id, nps_score, use_case, collect_ease, feature_request,
  how_found_us, trigger_type, created_at; up to 2000 rows). No emails or IPs.

### Hub-side registration

Register the Valt node's survey-pulse source in Awen OS "Pulse", exactly as Sync
is registered, pointing at the endpoint above with Valt's existing site key.
Until that's done, responses accumulate locally and remain fully usable via
Tools → Valt Feedback and the CSV export; only the hub aggregation waits.

## Notes / tradeoffs

- **Submit is public** (`permission_callback => __return_true`), protected by a
  honeypot, a one-per-session-per-hour rate limit and a site-wide hourly cap
  (60 by default, filter `valt_survey_global_hourly_cap`), matching the Sync system.
  Deliberate: feedback must work for logged-out visitors. Rows carry no
  privilege; worst case is junk feedback, caught by the rate limit.
- **Events vs. survey:** events on Valt are handled by the awen-client plugin;
  this subsystem is survey-only and does not create an events table.
- **Reused from Sync, renamed for Valt:** `fml_*`→`valt_*`, `FML/v1`→`valt/v1`,
  `licensing_ease`→`collect_ease`, question copy rewritten for collectors/artists.
