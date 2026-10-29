# Deployment cache freshness

Portal HTML now fingerprints local CSS/JS/MJS references using their file SHA-256 (16 hexadecimal characters), including shared shell assets injected by the front controller. Existing query parameters remain intact. Changed bytes therefore get a different URL automatically without a manually bumped release constant. Dynamically requested code and manifests receive no-cache/max-age=0/must-revalidate headers from public/.htaccess, with Apache expiry generation disabled for those extensions.

Service worker v52 deletes its previous offline cache on activation and uses network fetch with cache:reload for navigation and static assets, bypassing stale browser HTTP cache entries. Existing private API/media/navigation exclusions and offline denial are preserved. Site storage, authentication and user preferences are not erased. The existing service-worker update and activation flow remains responsible for clients already open at deployment.

The website uses content hashes for its shared stylesheet and JavaScript too, plus the same Apache code-asset revalidation policy. Existing PHP-page no-store headers remain in place. No migration, worker, dispatch or scanner activation is required.

Verification: PHP syntax, actual shared-asset URL rewriting, service-worker network-cache options and private boundaries, website event/member regressions and debug coverage passed. Two older worker fixtures were corrected to provide the Headers object present on real browser Requests. Local Apache is not listening on port 80, so live header checks could not run. Hosting/CDN configuration and post-deployment HTTP checks remain unverified. These changes take effect after deployment; they cannot remotely delete a cache before the browser contacts the updated server. Full Database/Browser acceptance groups remain unrun.

Existing unrelated website parent-contact edits are preserved and excluded from this task. Next: commit/push the requested changes to staging when authorized; that Action deploys GoDaddy staging, then check normal navigation and response headers on staging.
