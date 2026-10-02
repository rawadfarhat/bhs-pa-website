# BHS Parents Association website

This is the rebuilt public-facing BHS PA website. It is independent from the portal codebase but reads and writes the portal database.

## Setup

1. Copy `config.php.example` to `config.php` and set the portal database credentials.
2. Run the portal migration `database/migrations/2026_10_01_allow_direct_public_parent_contacts.sql` from the `bhs-pa-cashbox` repository.
3. Point the web server document root (or virtual host) at this directory and enable Apache rewrite rules.

The checked-in `config.php` is for the current local WAMP environment and is ignored by Git so deployment credentials remain local.

## Data behavior

- Member pages read active members from `pa_member_profiles` joined to `users`, use the profile's `pa_member_profile` photo attachment, and respect `display_order`.
- The home page shows profiles with `main_listing = 1`; the PA page shows every active profile.
- Parent submissions write directly to `parent_contacts_clean`.
- Existing contacts are matched by exact normalized family BHS ID first, then normalized email. Submitted values update the canonical record.
- The browser receives the same success message for inserts and updates and is never told whether a matching record existed.
