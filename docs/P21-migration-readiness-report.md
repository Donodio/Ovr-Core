# Migration Readiness Report (P21 — Migration)

**Basis:** `docs/P16-production-migration-plan.md` plus the implemented infrastructure in the plugin.

| Item | Status | Evidence |
|------|--------|----------|
| Backups (DB + files + settings snapshot) | ✅ PASS | Plan Phase 0.4 captures DB dump, plugin/uploads/theme, and the `ovr_settings` option snapshot before cutover. |
| Rollback (reversible) | ✅ PASS | Schema changes are additive via `dbDelta` (`Database::check_schema_version()` runs on `admin_init`/`init` and auto-applies on version bump); seeding is idempotent. Code rollback = `git checkout <prev>` + `composer dump-autoload`. Plan Phase 3 documents full revert + selective data recovery. |
| DNS | ⚠ WARNING (environmental) | DNS/SSL/CDN are hosting-provider actions outside the plugin; the plan lists them as go-live steps to be performed by the operator. |
| Downtime | ✅ PASS (planned) | Plan defines a maintenance window + pre-launch sign-off; activation is a single reversible step. |
| Cron Jobs | ✅ PASS | `DeletedListingsAdmin::register_cron()` schedules the daily hard-delete; Property expiry/subscription cron hooks exist and are attached on boot. |
| Payment Gateway | ✅ PASS (config) / ⚠ (Stripe live untested) | Gateway config is admin-set (`ovr_settings`), not in repo. PayPal/Stripe/Authorize/Wallet all dispatch through the shared `CheckoutHandler`. PayPal merchant creds present; **Stripe live not configured here** (see Payment report). |
| Notifications | ✅ PASS | All 12 notification types verified firing with correct recipients/templates (Notification Test Report). |
| Post-launch testing | ✅ PASS (procedure) | Plan Phase 2 defines smoke test (register→inquiry→wallet top-up→sandbox PayPal→notification delivery) + monitoring baseline + 7-day rollback kit retention. |

## Verdict
✅ PASS for everything the plugin controls (backups procedure, reversible schema/seeding, cron, gateway
dispatch, notifications, post-launch runbook). DNS/SSL/CDN and live Stripe execution are operator/environment
tasks, correctly classified as environmental — not software defects.
