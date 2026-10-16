# BHS Parents Association website

This is the rebuilt public-facing BHS PA website. It is independent from the portal codebase but reads and writes the portal database.

## Setup

1. Copy `config.php.example` to `config.php` and set the portal database credentials.
2. Run the portal migration `database/migrations/2026_10_01_allow_direct_public_parent_contacts.sql` from the `bhs-pa-cashbox` repository.
3. Point the web server document root (or virtual host) at this directory and enable Apache rewrite rules.

Before enabling dynamic Events in staging, run the portal migrations through
`202610021200_add_event_image_variants.sql`. The public site never generates
images on request: it serves pre-generated variants and temporarily falls back
to the original for older photos until the portal backfill has processed them.

The checked-in `config.php` is for the current local WAMP environment and is ignored by Git so deployment credentials remain local.

## Data behavior

- Member pages read active members from `pa_member_profiles` joined to `users`, use the profile's `pa_member_profile` photo attachment, and respect `display_order`.
- The home page shows profiles with `main_listing = 1`; the PA page shows every active profile.
- Parent submissions write directly to `parent_contacts_clean`.
- Existing contacts are matched by exact normalized family BHS ID first, then normalized email. Submitted values update the canonical record.
- The browser receives the same success message for inserts and updates and is never told whether a matching record existed.
- The Events navigation is hidden when no active event is published. Otherwise it links to a dynamic listing and a submenu of event detail pages.
- Event photos are exposed only when both `publish_to_website` and `publish_photos` are enabled on an active event.
- Event page `{attachment:FileName}` placeholders become links to that event's public attachments. Optional display text uses `{attachment:FileName;name:Display Name}` and safely falls back to the filename when omitted or empty. `{image_gallery}` is replaced in place by the responsive thumbnail gallery; when omitted, the gallery is appended after the event content.

The public event renderer requires PHP's built-in DOM extension. The gallery
uses native browser APIs and has no Composer or JavaScript package dependency.

## BHS Community (Task 04, disabled by default)

New `community` and `community-detail` pages use the portal's anonymous published API through a bounded HTTPS cURL client. No Community DB queries, API secrets, portal sessions or listing caches are introduced. Community navigation appears only with `community.enabled=true`. Fixed image/event/debug PHP endpoints remain file routes. Report forms are explicitly unavailable shells until portal Task 06.

Copy the actual Community/debug blocks from `config.php.example` into private production config while preserving existing settings. Set `app.env=production`, `community.enabled=false`, `allow_loopback_http=false`, HTTPS `portal_public_api_base_url` ending `/api/v1/community/public`, and exact `website_origin` before acceptance testing. `portal.root_path` must provide read-only shared Config/LoggingService/DebugRuntime source and `public/assets/debug.js`; website loads its own config only, never portal private config. Missing support fails closed. PHP cURL, CA trust and outgoing HTTPS are required. Responses are no-store, no redirects/fallback cache, 3-second connection/8-second total timeout and bounded JSON/images. Confirm CDN cache exclusions and actual root/subdirectory rewrites on hosting.

Full coordinated migration/config/deployment handoff lives in portal `docs/BHS Community/10-production-deployment.md` and Task 04 verification in `14-task-04-completion.md`. Pending portal migrations are 00030–00032; Task 04 adds none. No scanner/email/cron worker is activated. Deployment remains separately authorized.

Browser verification: `node tests/community-ui-browser.js <playwright-module> <php-binary> <chrome-binary> <portal-root>`. It renders actual pages against synthetic public API fixtures in a temporary project, never changes active config, and tests themes/mobile/XSS/DTO privacy/no-store/outages. It does not prove production TLS/scanner/device readiness. Next implementation task: portal Task 05; no manual action needed to continue disabled development.
