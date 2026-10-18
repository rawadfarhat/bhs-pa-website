# Community — website production deployment handoff

Task 10, 18 October 2026. This is an operator guide, not deployment/activation authorization.
The coordinated authoritative guide is `docs/BHS Community Replacement/24-task-10-production-deployment-guide.md`
in the portal repository. Source reviewed here: website base `9f6cc3a5697e0370cfdbf3f8e9e5a41cde633250`;
record the final reviewed documentation commit and paired portal release commit before upload.
No remote hosting configuration/deployed version has been independently inspected.

## Manual actions before deployment

1. Confirm the actual cPanel production website folder and portal HTTPS URL/root, backup
   both repositories' deployed files/config, portal database, private keys and runtime
   storage, and record deployed versions. Keep staging and production DB/config separate.
2. Verify the matching portal release and successful required portal migrations 00035–00042,
   earlier mail/ACL/Cron Logs foundations and actual migration ledger checksums. The website
   has **no new Community SQL migration**. Do not import staging data or fresh schema.
3. Confirm web PHP cURL/HTTPS and CA trust, outbound access to the portal, root/subfolder
   Apache rewrites and CDN/no-store behavior. Do not disable TLS validation or follow an
   arbitrary redirect to repair configuration. Check actual phone/Maps behavior separately.
4. Review GitHub environment/destination secrets and complete artifact. The website workflow
   deploys on **push to staging/main**; portal upload is a separate workflow. It exports
   tracked files, preserves config.php, portal folders and runtime data, excludes tests/docs
   and uses no delete-sync. There is no automatic config edit, SQL migration or cron setup.
   Do not push/merge simply to publish this guide without deployment authorization.

## Production config.php changes

Merge these sections into the **existing** production file; preserve the existing DB
credentials and other site settings. Replace every placeholder with actual host values:

```php
'app' => [
    'base_path' => '', // domain root; actual website subfolder otherwise
    'debug' => false,
],
'portal' => [
    'root_path' => '/home/CPANEL_USER/public_html/portal',
],
'community' => [
    'portal_base_url' => 'https://YOUR_PRODUCTION_PORTAL_HOST',
    'connect_timeout_ms' => 2000,
    'timeout_ms' => 5000,
    'json_max_bytes' => 1048576,
    'image_max_bytes' => 8388608,
],
```

- `portal.root_path` is a filesystem path to the portal root containing config/src/public;
  it supports existing Events integration. Do not add `/public` or put a URL there.
- `community.portal_base_url` is the canonical HTTPS portal URL, with a real base path
  if applicable; no `/public`, `/api/v1`, token, Google key or filesystem path. Community
  uses only anonymous published HTTP data, not copied portal DB credentials.
- Staging keeps its actual staging portal HTTPS URL and `/portal_staging` filesystem root.
  Never use production as an outage fallback. `app.base_path` is the website path, not
  the portal path. A root website normally uses an empty string.
- There is **no website enable flag**. Portal `community.public.enabled` defaults false;
  keep it false through initial deployment and enable only for an intentional pilot/launch.
  At least one enabled, nondeleted, approved-published business is additionally required.
  This gate does not turn off portal management/card/redemption access.
- The portal's shared private Community card key file stays private and is the **same file
  for staging and production per user instruction**. The website never receives it.
  Existing image scan/release and mail gates are separate portal/operator decisions.

Syntax-check the actual server file with the verified hosting PHP binary. Both workflows
exclude live configuration, so uploading the tracked example does not configure the site.
When needed, use supported host OPcache invalidation/restart after config/code updates;
a browser refresh alone does not clear PHP opcodes. No public reset/phpinfo endpoint.

## Files / ordered upload

Deploy the reviewed complete website snapshot paired with the portal release:
`community.php`, `community-image.php`, `includes/community-client.php`,
`includes/community-view.php`, current bootstrap/header/footer, Community CSS/JS, website
assets/font/artwork and `.htaccess`. Include matching intro/image-loader helpers/assets;
do not copy only the PHP page. Preserve existing other site functionality and private config.
The portal must be ready first; keep public discovery off during coordinated maintenance.
Review any obsolete files on the host separately because rsync does not delete them.

No new website Composer package, cron job, worker, queue or manual job trigger is needed.
Portal Community notices use its existing separate bounded worker and staging-only Email
Logs button; the public website never processes mail or redeems a promotion.

## Manual staging/production acceptance

- [ ] Public off or zero published businesses: header/mobile/footer Community link absent;
      clean/raw PHP, business/promotion detail, lookup/API and image routes denied. Raw
      community.php executes the guard rather than redirecting before it.
- [ ] Enable intentionally with a published business: Businesses/Promotions tabs, approved
      introduction, automatic search/category/locality, chips and server pages/counts work.
      Filtered zero stays an accessible empty result; single-page pagination is hidden.
- [ ] Business → exact promotion → Back to business → Back to businesses restores filters/
      pages. Promotions → selected offer includes its business; Back to promotions and
      View all promotions → Back to selected promotion work. Test copied links, refresh,
      browser Back/Forward, JavaScript-disabled navigation and an actual subfolder if used.
- [ ] Only BO-selected counters appear. Verify date boundaries/Upcoming/expired, saved
      Google location/address-based map, supplied hours only, no owner/parent/card/receipt
      leakage, and derivative-only images. Test image loading/success/failure/lazy states.
- [ ] Unpublish/disable/restore in the portal propagates on the next checked request.
      Verify visible-tab refresh within 30 seconds, return from hidden/BFCache, and actual
      media withdrawal. No CDN/conditional/304/stale-if-error bypass; no offline catalog.
- [ ] Outage/schema/oversize/timeout/redirect/invalid TLS responses show a safe unavailable
      state, hide the link and retain no stale business data; unrelated site pages work.
- [ ] Desktop/tablet/phone layout, full shared header/footer, keyboard/menu/focus, long names,
      loading feedback/reduced motion, actual iPhone/Android and real hosting Maps/TLS/CA
      behave correctly. Synthetic local fixtures do not certify providers or physical devices.
- [ ] Existing Home/Events/PA/registration/Portal links still work, including legacy Events
      root_path integration. Review real hosting resource/performance and error monitoring.

Keep Community HTML, images and portal public API out of CDN/proxy caches and stale fallbacks;
honor no-store on shared-header responses too. Test with actual hosting, not only a local
server. Already delivered pixels cannot be recalled instantly from an open browser.

## Disable / rollback

Set portal public.enabled=false to hide/deny the website. This does not disable portal
redemption/management or stop mail cron. Coordinate portal maintenance/mail pause separately.
Preserve receipts/audits/usage/keys and queue evidence. Restore only a reviewed compatible
portal/website artifact pair; do not drop forward schema or restore an old DB over new
transactions. No stale directory fallback. Accepted email cannot be recalled.

Use the portal sign-off register for final commits, operator/hosting evidence, unresolved
global GoDaddy/scanner/e-sign prerequisites and explicit production/activation approval.
No further deployment or activation is automatic after Task 10 documentation completion.
