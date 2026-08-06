# P16 — Production Migration & Launch Plan

A runbook for moving `ovr-core` from staging to a live production WordPress
environment. It is grounded in the mechanisms already present in the codebase
(see `docs/HANDOVER.md` and `src/Core/Database.php`); this document sequences
them into a safe, reversible rollout.

**Key facts the plan relies on**
- Schema is versioned by the `ovr_db_version` option against `OVR_DB_VERSION`.
  `Database::check_schema_version()` runs on `admin_init` and `init` and applies
  `dbDelta` automatically whenever the constant increases — **no manual SQL
  migration step is required**.
- Activation seeds (idempotently, safe to re-run): the `ovr_property` CPT +
  taxonomies + roles/caps, front-end pages + shortcodes (`src/Core/Pages.php`),
  email templates (`EmailTemplates::maybe_seed`), and the paid-services
  catalogue (`PaidService::maybe_seed`).
- Credentials (PayPal/Stripe/Authorize.Net, SMTP, Backblaze B2) live in admin
  **Settings**, never in the repo. They are migrated as configuration, not code.
- Composer PSR-4 autoloader must be generated: `composer install --no-dev`.

---

## Phase 0 — Pre-Launch (do all of this before touching production)

1. **Environment parity check**
   - WP 6.4+, PHP 8.2+ (GD/WebP), MySQL/MariaDB, Elementor + Elementor Pro,
     active theme **OVR Villages** (owns header/footer/logo).
   - Confirm `composer install --no-dev` succeeds on a clean checkout; generate
     the autoloader.
2. **Staging validation**
   - Fresh-activate on a staging clone. Expect no PHP errors; 15+ custom tables
     created (`wp_ovr_*`). Verify pages auto-created (Login, Register, Forgot
     Password, Pricing, Search, Featured, Onboarding, Dashboard, Contact…).
   - Run the `TESTING.md` end-to-end plan; all pass/fail criteria green.
   - Exercise the P14 notification suite and the P15 payment paths that *are*
     executable without buyer credentials (Wallet purchase, cancel, failure).
3. **Secrets inventory (client-owned)**
   - PayPal (client id/secret + **sandbox buyer** only for pre-launch testing),
     Stripe secret key, Authorize.Net login/key, SMTP, Backblaze B2 keys.
   - Store in the production admin **Settings → OVR Settings**; export a
     settings snapshot (`ovr_settings` option) for the rollback kit.
4. **Backup & snapshot kit** (keep until 7 days post-launch)
   - Full DB dump (`mysqldump` or host snapshot).
   - Files: `wp-content/plugins/ovr-core/`, `wp-content/uploads/`, active theme.
   - Record current `ovr_db_version` value and the git commit SHA deployed.
5. **Permalinks & rewrites** — set "Post name" and re-save so `ovr_property`
   rewrites flush.
6. **Go/No-Go** — sign-off that staging smoke tests, payments (sandbox), and
   notification rendering all pass.

---

## Phase 1 — Migration (deploy the code & data)

1. **Maintenance window** — enable maintenance mode (or quiet hours) to avoid
   writes mid-swap. Announce downtime.
2. **Deploy code**
   - `git fetch` + `git checkout <release-sha>` on production (or copy the
     verified build). Run `composer install --no-dev` and `composer dump-autoload`.
   - Do **not** activate yet if a schema bump is expected; activate last so the
     automatic `check_schema_version()` runs once with the new code present.
3. **Activate / upgrade**
   - Activate (or "Update" if already active). On activation:
     - `Database::create_tables()` / phase-2 tables apply via `dbDelta`;
       `ovr_db_version` is bumped and saved.
     - Pages, email templates, and paid-services catalogue are (re)seeded
       idempotently.
   - Verify in the DB that `ovr_db_version == OVR_DB_VERSION` and all `wp_ovr_*`
     tables exist/are current.
4. **Configuration migration**
   - Import the `ovr_settings` snapshot captured in Phase 0.4 (or re-enter
     values). Confirm each gateway `is_configured()` returns true where needed.
   - Re-save Permalinks once more post-activation.
5. **Data migration (if any existing data)**
   - For legacy/import data use `src/Admin/MigrationImporter.php` (the existing
     importer) rather than manual SQL. Validate row counts before/after.
   - Subscription/wallet/user-meta are carried by WordPress users + usermeta;
     confirm the "OVR Landlord" role and caps exist.

---

## Phase 2 — Launch (go live)

1. **Smoke test (production, logged-in admin)**
   - Home/Elementor render intact; header/footer present.
   - Register → onboarding; submit an inquiry (fires `inquiry_landlord` +
     `inquiry_guest` emails).
   - Wallet top-up + a sandbox PayPal order (merchant creds) to confirm the
     redirect path; **buyer-approval capture remains gated on sandbox-buyer
     availability** (see P15).
   - Trigger one of each notification template via the P14 hooks; confirm
     `wp_mail` delivers (or lands in the mail log).
2. **DNS / SSL / CDN**
   - Confirm A/AAAA + CNAME, valid TLS cert, and that the CDN/caching layer
     (if any) is purged after activation so new rewrites/asset hashes serve.
3. **Monitoring baseline**
   - Enable error logging; watch for `PHP Fatal`/deprecation spikes for 30 min.
   - Spot-check the audit log (`wp_ovr_audit_log`) for expected events.
4. **Announce** — disable maintenance mode, open the site, post launch notice.

---

## Phase 3 — Rollback (if launch fails)

The design is **reversible** because schema changes are additive (`dbDelta`)
and seeding is idempotent.

1. **Code rollback (fastest, first resort)**
   - `git checkout <previous-sha>` + `composer dump-autoload` + (re)activate the
     prior version. `check_schema_version()` keeps the DB compatible; it will
     not downgrade tables.
2. **Full revert**
   - Restore the Phase-0 DB dump and file backup. Re-import the prior
     `ovr_settings` snapshot.
3. **Selective data recovery**
   - If only a bad data import occurred, restore just the affected `wp_ovr_*`
     tables from the snapshot; leave the schema in place.
4. **Gateway/email misconfig**
   - Revert `ovr_settings` from the Phase-0 snapshot; no code change required.
5. **Post-rollback**
   - Keep the maintenance window until verified; re-run the Phase-2 smoke test
     on the restored version; document the incident and the root cause before
     re-attempting.

---

## Checklist (one-line)
- [ ] Pre: env parity, staging E2E green, secrets inventoried, backups + `ovr_settings` snapshot taken
- [ ] Deploy: code at release SHA, `composer install --no-dev`, autoloader generated
- [ ] Activate: `ovr_db_version` bumped, all `wp_ovr_*` tables current, pages/templates seeded
- [ ] Config: gateways `is_configured()`, permalinks re-saved
- [ ] Launch: smoke test, DNS/SSL/CDN purge, monitoring baseline, maintenance off
- [ ] Rollback kit verified and retained 7 days
