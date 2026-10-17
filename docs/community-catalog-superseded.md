> **SUPERSEDED — 17 October 2026.** This document records the rejected BHS Community design/implementation. Its tasks, completion claims, configuration, migration and deployment instructions are historical only. The implementation has been reverted. Do not resume these tasks or enable this design; await the user's replacement requirements.

# Archived website Community integration notes

The following section was preserved from the pre-rollback README. The active README and website runtime have been reverted.

## BHS Community (Task 04, disabled by default)

New `community` and `community-detail` pages use the portal's anonymous published API through a bounded HTTPS cURL client. No Community DB queries, API secrets, portal sessions or listing caches are introduced. Community navigation appears only with `community.enabled=true`. Fixed image/event/debug PHP endpoints remain file routes. Report forms are explicitly unavailable shells until portal Task 06.

Copy the actual Community/debug blocks from `config.php.example` into private production config while preserving existing settings. Set `app.env=production`, `community.enabled=false`, `allow_loopback_http=false`, HTTPS `portal_public_api_base_url` ending `/api/v1/community/public`, and exact `website_origin` before acceptance testing. `portal.root_path` must provide read-only shared Config/LoggingService/DebugRuntime source and `public/assets/debug.js`; website loads its own config only, never portal private config. Missing support fails closed. PHP cURL, CA trust and outgoing HTTPS are required. Responses are no-store, no redirects/fallback cache, 3-second connection/8-second total timeout and bounded JSON/images. Confirm CDN cache exclusions and actual root/subdirectory rewrites on hosting.

Full coordinated migration/config/deployment handoff lives in portal `docs/BHS Community/10-production-deployment.md` and Task 04 verification in `14-task-04-completion.md`. Pending portal migrations are 00030–00032; Task 04 adds none. No scanner/email/cron worker is activated. Deployment remains separately authorized.

Browser verification: `node tests/community-ui-browser.js <playwright-module> <php-binary> <chrome-binary> <portal-root>`. It renders actual pages against synthetic public API fixtures in a temporary project, never changes active config, and tests themes/mobile/XSS/DTO privacy/no-store/outages. It does not prove production TLS/scanner/device readiness. Next implementation task: portal Task 05; no manual action needed to continue disabled development.
