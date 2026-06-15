# Our Village Rentals — Handover & Operations

_Technical handover for the team that will deploy and run the `ovr-core` plugin.
Credentials (payment gateway, Backblaze B2, SMTP) remain the client's and are
configured in the admin, never committed to the repo._

---

## 1. Requirements

- **WordPress** 6.4+
- **PHP** 8.2+ (GD with WebP support recommended for image optimisation)
- **Elementor** + Elementor Pro (homepage and several page layouts are
  Elementor-native; the OVR hero/search/cards/testimonials widgets register
  under the "OVR" category)
- MySQL/MariaDB (the plugin creates 15 custom tables — see §4)
- Active theme: **OVR Villages** (owns the site header/footer + logo)

## 2. Install & activate

1. Place the plugin at `wp-content/plugins/ovr-core/` and run
   `composer install --no-dev` in that directory (PSR-4 autoloader).
2. Activate **OVR Core** in Plugins. On activation it:
   - registers roles/capabilities and the `ovr_property` CPT + taxonomies,
   - creates/updates custom tables (schema version `OVR_DB_VERSION`),
   - seeds pages (see §3), email templates, and the paid-services catalogue,
   - flushes rewrite rules.
3. Set **Settings → Permalinks** to a "pretty" structure (post name).
4. Configure **Properties → OVR Settings** (general, media, security, storage).

Schema upgrades are idempotent and run automatically on `admin_init`/`init`
whenever `OVR_DB_VERSION` increases — no manual migration step.

## 3. Pages & shortcodes

Activation creates the front-end pages, each backed by a shortcode. Key ones:

| Page | Shortcode |
|------|-----------|
| Login / Register / Forgot password | `[ovr_login]` `[ovr_register]` `[ovr_forgot_password]` |
| Pricing / Subscription / Checkout / Success | `[ovr_pricing_plans]` `[ovr_subscription_select]` `[ovr_checkout]` `[ovr_payment_success]` |
| Search / Map / Featured / Villages | `[ovr_search_results]` `[ovr_map]` `[ovr_featured_listings]` `[ovr_villages]` |
| Dashboard / Onboarding | `[ovr_dashboard]` `[ovr_onboarding]` |
| Ad banner (place anywhere) | `[ovr_ad_banner placement="homepage"]` |

Page IDs are stored as individual `ovr_page_*` options. The homepage is built in
Elementor using the OVR widgets.

## 4. Custom database tables

All prefixed with `{wp_prefix}ovr_`:

`seasonal_pricing`, `availability`, `inquiries`, `payments`, `audit_log`,
`promos`, `wallet`, `reviews`, `bookings`, `booking_guests`, `paid_services`,
`sync_log`, `support_tickets`, `support_replies`, `knowledge_base`,
`review_requests`, `loyalty`, `file_storage`, `email_templates`, `bump_log`,
`hero_slides`, `ad_banners`.

Current schema version: see `OVR_DB_VERSION` in `ovr-core.php`.

## 5. Cron jobs

Registered via WP-Cron (ensure real cron hits `wp-cron.php` in production):

| Hook | Schedule | Purpose |
|------|----------|---------|
| `ovr_audit_purge` | monthly (`ovr_monthly`) | Delete audit-log rows older than 365 days |
| `ovr_subscription_expires` / `ovr_subscription_expired` | daily | Membership expiry checks + emails |
| `ovr_purge_old_inquiries` | scheduled | Remove inquiries older than the retention window (12 months) |
| `ovr_listing_purge_*` | per-listing | Permanently remove soft-deleted listings after the grace period |
| `ovr_wordpress_sync_event` / `ovr_booking_wp_sync` | scheduled | WordPress booking sync |
| `ovr_ical_sync_event` | scheduled | Import external iCal feeds |

Recommended production crontab entry:
```
*/5 * * * * curl -s https://YOURSITE/wp-cron.php?doing_wp_cron > /dev/null
```
(and set `define('DISABLE_WP_CRON', true);` in `wp-config.php`).

## 6. Environment / constants

Defined in `ovr-core.php` (do not edit) or `wp-config.php` (operational toggles):

| Constant | Where | Effect |
|----------|-------|--------|
| `OVR_DISABLE_2FA` | wp-config | Bypass admin email-OTP 2FA (recovery/escape hatch) |
| `OVR_DISABLE_WEBP` | wp-config | Turn off WebP generation/serving |
| `DISABLE_WP_CRON` | wp-config | Use a real system cron (recommended — see §5) |

Payment gateway and Backblaze B2 credentials are entered in **OVR Settings**, not
in code. The active payment gateway is filterable via `ovr_active_gateway`.

## 7. Email configuration

- All transactional emails are managed in **Properties → Emails** (subject, HTML,
  recipient, enable/disable, `{{variables}}`).
- Mail is sent through `wp_mail()`. For reliable delivery, configure an SMTP /
  transactional provider (e.g. an SMTP plugin or a `wp_mail` integration) at the
  host level — OVR routes through whatever `wp_mail` uses.
- The "From" name/address default to the site; override with your SMTP plugin or
  a `wp_mail_from` filter.

## 8. Cloud storage (Backblaze B2)

- Configure under **Settings → Storage** (Key ID, Application Key, Bucket).
- Once enabled, new media is offloaded and served from B2; URLs are rewritten
  transparently. Optionally delete local copies of sized images.
- Monitor and recover via **Properties → Cloud Storage** (coverage, Test
  Connection, Offload Pending, Restore Missing Originals).

## 9. Extension points (hooks)

**Actions**: `ovr_loaded`, `ovr_activated`, `ovr_deactivated`,
`ovr_user_registered`, `ovr_property_saved`, `ovr_listing_saved`,
`ovr_listing_bumped`, `ovr_listing_deleted`, `ovr_inquiry_submitted`,
`ovr_inquiry_replied`, `ovr_review_submitted`, `ovr_review_status_changed`,
`ovr_checkout_started`, `ovr_payment_completed`, `ovr_subscription_renewed`,
`ovr_subscription_expired`, `ovr_upgrade_activated`, `ovr_upgrade_deactivated`,
`ovr_wallet_changed`, `ovr_loyalty_adjusted`, plus gateway webhook hooks
(`ovr_stripe_webhook`, `ovr_paypal_webhook`, `ovr_authnet_webhook`).

**Filters**: `ovr_active_gateway`, `ovr_listing_upgrades`, `ovr_login_redirect`,
`ovr_village_default_images`, `ovr_watermark_font`, `ovr_watermark_text`.

## 10. Deploying changes

1. Deploy code to `wp-content/plugins/ovr-core/` (git pull or build artifact);
   run `composer install --no-dev` if dependencies changed.
2. Load any wp-admin page once (or hit the site) so the schema check runs and
   applies new tables/columns for the bumped `OVR_DB_VERSION`.
3. If page/rewrite changes were made, visit **Settings → Permalinks** to flush.
4. Run the regression smoke test (§11) and walk `docs/QA-CHECKLIST.md`.

## 11. Verifying a deploy (smoke test)

Bootstrap WordPress from the CLI and assert the core surface. On WP Engine /
standard hosting use WP-CLI:

```bash
wp eval-file path/to/smoke.php
```

A minimal `smoke.php` should assert: `ovr_property` CPT + taxonomies + roles
exist; all `ovr_*` tables exist; `get_option('ovr_db_version')` matches;
key classes autoload; key shortcodes are registered; `PropertyQuery::query()`
and `get_map_points()` run; and `ovr_audit_purge` is scheduled. The Milestone 3
reference run was **35/35**.

> On the local Flywheel/Local dev box (where the homebrew `wp` can't reach the
> socket), bootstrap with Local's PHP binary and inject the MySQL socket:
> `php -d mysqli.default_socket="<run-dir>/mysql/mysqld.sock" smoke.php`
> with `$_SERVER['HTTP_HOST']` set, then `require wp-load.php`.

## 12. Backups & recovery

- Back up the database (all `wp_` + `wp_ovr_*` tables) and `wp-content/uploads`.
- When B2 offloading is on, uploads also live in the bucket; **Restore Missing
  Originals** (Cloud Storage) re-downloads originals and rebuilds sizes.
- Keep the `ovr_audit_log` for the compliance window before the monthly purge.

## 13. Security notes

- Admin 2FA (email OTP) and login-attempt lockout are configurable under
  Settings → Security; `OVR_DISABLE_2FA` is a break-glass only.
- Payment card data is never stored (PCI scope stays with the gateway).
- All admin actions are recorded in the Audit Log.

---

_Companion docs: `docs/ADMIN-GUIDE.md`, `docs/LANDLORD-GUIDE.md`,
`docs/QA-CHECKLIST.md`._
