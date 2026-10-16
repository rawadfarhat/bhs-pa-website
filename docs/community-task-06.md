# Community Task 06 website handoff

Business/offer reports now accept category, bounded details, optional consented email and honeypot. Same-origin JSON posts to fixed `community-concerns.php`, which uses the narrow public portal `/concerns` API; no portal DB/private credentials or forwarded visitor IPs. Responses contain accepted/error only. Theme tokens, no-store and safe text insertion remain.

Deploy proxy, client/helpers, JS and CSS together. No config was enabled or secret added. Existing exact website origin and HTTPS public API base must be configured. Portal migration 00034 and activation follow portal `docs/BHS Community/10-production-deployment.md`. Reports are private to administrators; owners receive redacted notices. Website reports share a conservative proxy-IP budget; validate hosting/WAF before pilot.

Browser fixtures exercise actual PHP proxy with a synthetic API, responsive themes, escaping, submission, no-store and outage handling. No real mail, hosting deployment or live report. Both repositories changed. Next Task 07; no manual action needed to continue, retention policy confirmation required before production.
