# Community website — Task 09 setup and acceptance

Task 09 implements the approved Events-style public directory. Implementation is local;
this document does not authorize upload, public activation or production deployment.

## Files and integration

- `community.php` is the single clean `/community` destination. Business and promotion
  query views retain the full shared website header/footer and visible Back-to links.
- `includes/community-client.php` calls only the anonymous portal
  `/api/v1/community/public/` endpoint family over HTTPS. No authentication token,
  portal database credentials, owner directory, card or redemption API is used.
- `includes/community-view.php`, `assets/community.css` and `assets/community.js`
  render the Events-style server-paged listings and combined detail views.
- `community-image.php` proxies only current public approved derivatives. It rechecks
  global availability and the portal rechecks entity/image visibility on every request.
- Header/mobile/footer links require the same request-time availability predicate.
  `.htaccess` and `url()` include clean Community routes. Raw `community.php` deliberately executes rather than redirecting before the guard; it enforces
  the same gate. No sitemap/homepage teaser has been added.

## Configuration (manual, when a staging pilot is authorized)

Portal `config/config.php`, inside existing `community`:

```php
'public' => ['enabled' => false],
```

Default/absent/false disables anonymous discovery. Only set `true` for an explicitly
approved pilot/launch. It additionally requires at least one enabled, nondeleted
business with a valid published revision. This switch does not alter portal ACL,
owner management, review, cards, redemption, mail or image scanning/release.

Website `config.php`:

```php
'community' => [
    'portal_base_url' => 'https://<canonical-portal-host>/<optional-portal-base-path>',
    'connect_timeout_ms' => 2000,
    'timeout_ms' => 5000,
    'json_max_bytes' => 1048576,
    'image_max_bytes' => 8388608,
],
```

Use the actual staging portal address for staging; use the production portal address
for production. Remove the optional path if the portal is at its domain root.
Never supply a login token, Maps key, PHP filesystem path, `/public`, or API suffix.
Keep the checked-in example blank. There is no second website enable flag.

PHP requires cURL with HTTPS support and a valid CA bundle, plus the website's existing
PHP extensions. Outbound HTTPS to the canonical portal must work on the host. Certificate
and hostname validation stay enabled; redirects are refused. Connect/total timeouts
are capped at 2/5 seconds; response limits cap at 1 MiB JSON / 8 MiB images. Smaller
configured limits are allowed. No visitor can choose an upstream host/path.

Existing `app.base_path` remains authoritative for a deliberate subfolder install;
otherwise links infer the script directory. Do not point staging at production as a
fallback. Config files remain private and are not committed with live credentials.

## Freshness and visibility

There is no positive cross-request cache. Availability is memoized only during one
PHP request. Community HTML, images and shared-header page responses send no-store.
CDN/proxy rules must honor this and exclude `/community`, `community.php`,
`community-image.php` and the portal public API from cache/stale-if-error policies.
No ETag/304 can skip visibility checks. The portal PWA already excludes API traffic.

An active visible Community page re-fetches its current server-rendered view every
30 seconds; hidden tabs suspend the timer and revalidate on return. Back/Forward and
restored pages revalidate; restored content is cleared during checking. Network/schema
failure clears previous catalog content and hides its navigation link. There is no
stale/offline catalog fallback. Already delivered pixels cannot be recalled instantly.
Public counters are only the BO-approved combinations; unlimited remaining/total are
omitted. Browsing does not consume a promotion. Expired/disabled/withdrawn entities
and revoked images disappear on the next checked request; Upcoming offers remain visible.

Ordering is normal name + ID ascending, as approved for the initial website design.
The portal Browse Community retains its separately requested updated-date descending
order. All counts, filtering, ordering, pages and lookups are computed on the server;
no all-record fetch or client-side slicing is used. Page size 25/50/100, maximum 100.
Related business promotions paginate separately. Filter choices use public visible
records only, 25 per server lookup page with search/Previous/Next. Retired categories
still label existing approved businesses. Ordinary filter changes apply automatically;
JavaScript-disabled visitors retain links and the form's Search submit fallback.

Return context is a bounded allowlist of tab/search/category/locality/business-filter,
root page/size, related page/search and one originating promotion. There is no external
return URL or recursive history stack. Direct promotion links default Back to promotions;
direct business links default Back to businesses. Removed pages clamp to the last page
with an explanatory status. Invalid/mismatched context is rejected safely.

Maps show the validated saved Google link; representable query/CID locations use a
trusted generated iframe. Short links use a clearly labelled address-based map when
an address is supplied. No arbitrary pasted iframe or server-side short-link resolver.
Days without supplied hours are omitted; no supplied days hides the hours section.

## Diagnostics and jobs

Portal `community-public` uses central LoggingService, registered in both portal configs.
Its local switch is off; example entity registration follows the existing registry
convention. Existing severity, level and entity choices are preserved. Static stages
and bounded boolean/count metadata only; no upstream URLs, raw provider errors,
filter text, payloads, contacts, image bytes or credentials.

Standalone website has no portal autoload/central debug runtime. Its portable adapter
calls central LoggingService/AppDebug only when those are supplied by a host integration;
otherwise no separate debug writer or console system is introduced. HTTP failure status
and safe page feedback remain available. This is an explicit standalone-boundary exclusion,
not a claim that portal runtime instrumentation covers the website. Portal endpoint
failures remain centrally diagnosable. No website diagnostic flag or secret is added.

No schema migration, queue/email trigger, scheduled worker or cron is introduced.
Existing Community notification/scanner config, gates and staging manual controls stay
unchanged. No new manual job button is needed because this task adds no recurring job.

## Staging acceptance and production delta

1. Deploy matching portal public controller/service/Browse changes/config and website
   page/helpers/assets/header/footer/bootstrap/rewrite changes only when requested.
2. Configure the staging HTTPS connector; verify `/api/v1/community/public/availability`
   returns only `data.available`. Keep public disabled until intentional pilot activation.
3. With disabled switch or no published businesses: header/mobile/footer link absent;
   clean/raw/detail/media URLs deny. With one published business: link appears.
4. Test Businesses search/category/locality, filtered zero, page 2, browser Back/Forward,
   refresh and copied links; business → promotion → Back to business → Back to businesses.
5. Test Promotions → selected promotion + business, Back to promotions, View all promotions
   → Back to selected promotion. Confirm original filters/page and mobile return links.
6. Change counter flags and redeem through the authenticated BO flow; confirm only selected
   public counters, live refresh, expired/withdrawn entities and image revocation. Never use
   public browsing as a redemption or scan route.
7. Check real staging Maps links/iframe, safe optional images, desktop/mobile keyboard/menu,
   iPhone/Android browsers and slow/outage behavior. Test JavaScript disabled as well.
8. For production, use the production connector URL/base path and valid HTTPS/CA/outbound
   access. Keep public false until coordinated release approval; verify actual website/API
   no-store/CDN behavior. Preserve the existing card keyring, mail overrides, scanning and
   image release settings unless separately authorized. There are no new jobs or migrations.

Actual hosting TLS/CA/firewall, CDN behavior, live Maps accuracy, public URL deployment and
physical-device acceptance must be recorded during staging/release review. Local synthetic
fixtures do not prove these. To disable safely, set portal public.enabled=false; no audit,
receipt or business data is removed. Do not restore a stale catalog as an outage workaround.

Next: Task 10 coordinated release evidence and production handoff, only when authorized.

After changing PHP/configuration on a host that caches opcodes, use the supported host invalidation/restart process if needed; a browser/PWA refresh cannot refresh PHP opcodes. No public cache-reset endpoint is added.
