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

## GitHub deployment to GoDaddy

`.github/workflows/deploy-bhs-pa-website.yml` deploys pushes on `staging` to the
staging website folder and pushes on `main` to the production website folder.
For a manual deployment, open Actions > Deploy BHS PA Website to GoDaddy > Run
workflow. Select `target_branch` (`staging` or `main`); this controls both the
checked-out website code and the server destination. Check `push_all_files` to
force-upload every deployable file. Leave it unchecked to compare file contents
with the server using rsync checksums and upload only new or changed files.
Automatic pushes force-upload all deployable files. All modes retain the same
config/runtime/portal exclusions and never delete remote files.

Keep this workflow on the default branch so GitHub offers manual runs, and on
both deployment branches. GitHub environments are named `staging` and `production`; configure
any desired approval rules in repository Settings > Environments.

In this website repository, open Settings > Secrets and variables > Actions and
create these **repository secrets** (portal repository secrets are not inherited):

| Secret | Value |
| --- | --- |
| `CPANEL_HOST` | Same GoDaddy SSH hostname or server IP used by the portal, without `https://` or a path. |
| `CPANEL_PORT` | Same SSH port used by the portal; use the actual hosting SSH port, not the cPanel web interface port. |
| `CPANEL_USERNAME` | Same cPanel SSH account username used by the portal. |
| `CPANEL_PASSWORD` | That account's SSH/cPanel password, as used by the portal workflow. |
| `CPANEL_DEPLOY_PATH` | Absolute production **website** document root, without a trailing slash. For example `/home/YOUR_CPANEL_USERNAME/public_html` if that is the live website's document root. Do not use the portal's deployment path. |
| `CPANEL_STAGING_DEPLOY_PATH` | Absolute staging **website** document root, without a trailing slash. For example `/home/YOUR_CPANEL_USERNAME/public_html/website_staging` only if that is the staging domain's configured document root. Do not use `portal_staging`. |

Confirm the two actual document roots in cPanel Domains before setting the path
secrets. The workflow requires distinct paths under `/home/USER/public_html`.
Examples above are placeholders, not detected hosting values.

SSH uses username/password authentication with `StrictHostKeyChecking=no`,
matching the portal deployment workflow. No known-hosts secret is required.

Before the first run, create each destination folder and place its own
`config.php` there, based on `config.php.example`. Production must use the
production portal database and portal root path; staging must use the staging
portal database and portal root path. Set `app.base_path` to the website URL's
subdirectory, or an empty string when served from the domain root. Ensure the
hosting account supports SSH, password authentication and remote `rsync`.

Deployment preserves server `config.php`, logs, storage and portal folders. It
uploads tracked website files, including `.htaccess`, without deleting remote
files. Consequently, files removed or renamed in Git need separate remote
cleanup. It does not run database migrations or change hosting configuration.
