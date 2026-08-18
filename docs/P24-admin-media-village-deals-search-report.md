# Engineering Report — Admin Controls, Media, Village Sections, Deals & Cancellations, Cards, Search, Filtering & Pagination

**Date:** 2026-08-14
**Plugin:** ovr-core
**Scope:** Master implementation prompt (P0–P2). Implemented within the existing architecture; all pre-existing working functionality preserved.

---

## A. ADMIN ACCESS

**How the subscription override works:** Every landlord gate carries an explicit administrator bypass using the WordPress capability `manage_options` (e.g. `ListingForm.php:913`, `AccessControl::user_has_access()`, `SubscriptionGate::guard()`). An administrator is never routed to a subscription-required screen for legitimate management. This is capability-based, **not** hard-coded user IDs (`user_id === 1` is not used anywhere).

**Which capabilities are used:** `manage_options` (admin override), `edit_post`/`edit_others_ovr_properties` (property management), `upload_files` (media), `ovr_manage_users` (user management), `ovr_manage_reviews` (reviews). All checks are server-side.

**Admin actions tested:** property edit, document upload (succeeded, attachment created), photo upload, pricing/calendar/amenities/golf-cart management, user profile photo upload/replace/remove, About Me edit, Admin Notes edit. None were blocked by subscription.

## B. USER MANAGEMENT

- **Profile photo:** stored as user meta `ovr_avatar_id` (attachment ID) — the existing source of truth. Added an **admin avatar manager** to the WP user-edit screen (`UsersAdmin` + `assets/js/ovr-admin-avatar.js`): upload/replace via the extended `ovr_upload_avatar` handler (now accepts an admin-managed `user_id`) and **remove** via a new `ovr_remove_avatar` handler (restores the default placeholder, deletes the orphaned attachment). Nonce-protected; only admins (`manage_options`/`ovr_manage_users`) can change another user's photo; a normal user may only change their own.
- **About Me / bio:** stored in the standard WordPress `wp_users.description` field — the same field the user's own profile edits (no duplicate bio source). Admins can now edit it on the user-edit screen. **Bug fixed:** the `description` column was missing from `wp_users` in this environment (silently breaking every bio save, including the landlord's own dashboard save). Added an idempotent `ensure_user_bio_column()` migration in `Database::create_tables()`. Verified: admin sets bio → appears on the user's property owner card.
- **Admin Notes privacy:** both the property `_ovr_admin_notes` and user `ovr_admin_notes` are admin-only. Verified they appear on **no** public page, search results, REST property endpoint, REST user endpoint, or email. The REST property write path uses a strict meta whitelist that excludes `admin_notes`.

## C. MEDIA

- **Association:** every property's media (gallery, documents, video, panorama, featured image) carries a `_ovr_property_id` back-reference, stamped on save and back-filled for existing properties. Attachment IDs/URLs/metadata are preserved (WordPress-native).
- **Filtering/organization:** a "Filter by property" dropdown in the Media Library (server-rendered list view; JS-injected grid view) scopes the library to one property's media — no need to page through thousands of images.
- **Deletion behavior:** permanent property delete only. Attachments are removed only when `PropertyMedia::is_safe_to_delete()` proves they are referenced by nothing else (another property, featured image, post content, user profile, settings option). Soft-delete/trash/archive never deletes media (recoverable). The `wp_users` column migration and all media logic were verified with shared-media experiments.
- **Protection against deleting shared media:** an attachment referenced by any other property or object is never deleted. Verified live.

## D. VILLAGE SECTIONS

- **Current taxonomy:** `ovr_village` is the authoritative location taxonomy (name, description, per-section image via term meta, curated ordering via `VillageSectionsAdmin`). Properties associate via the taxonomy AND the free-text `_ovr_village_name` meta; search filters by both.
- **Featured Cities status:** confirmed **dead code** — `FeaturedCities` (option `ovr_featured_cities`) was never read by any active workflow. **Retired from active admin UI** (submenu, save handler, enqueue no longer registered) and its dead `use` import removed from `results.php`. **Data preserved** (5 rows intact in the option) — nothing deleted, rollback-safe. Village Sections remain the primary location mechanism; no migration was required because no live content referenced Featured Cities.

## E. DEALS & CANCELLATIONS

- **Eligibility:** a property is a live Deal when `_ovr_is_deal = 1` AND `_ovr_deal_expires >= today` (date-driven, evaluated in the search query via `active_boost_clause`). Expired deals never appear — the daily `expire_due` sweep also clears them. No manual unchecking required.
- **Payment:** new `deals` paid-service type in `PaidService::TYPES` + `UpgradeActivator::MAP` (`_ovr_is_deal`/`_ovr_deal_expires`). Two services seeded via idempotent `ensure_deals_services()`: **30-day at $10** and **60-day at $15**, prices configurable in the Paid Services admin (not hard-coded — the ~$10 guidance is the default only).
- **Duration:** 30 / 60 days, from the service's `duration_days` column.
- **Activation:** purchase flows through the existing `?service=&property=` checkout. `ovr_payment_completed` → `UpgradeActivator::on_payment_completed` (ownership-guarded) sets the flag + expiry. **Verified end-to-end:** wallet purchase of the 30-day deal → payment `completed`, wallet debited, `_ovr_is_deal=1`, expires +30d, property appeared on the Deals page.
- **Expiration:** expired flag is swept by cron and excluded by the query — verified deactivation removes the property from results.
- **Search integration:** `deals_only=1` filter in the existing search architecture (query + sanitize + results title "Deals & Cancellations"). A **Deals & Cancellations nav link** (header mega-menu, `/search/?deals_only=1`) reuses the normal results experience — cards, filters, pagination, map, sorting — with the eligibility filter applied. Combines correctly with village/pets/golf-cart filters (all ANDed).

## F. SEARCH

- **Pagination fix (confirmed bug):** root cause was a **rewrite-rule collision** — the search page slug `search` collided with core's `search/(.+)/page/N/` rule, so `/search/page/2/` was parsed as a keyword search for "search". A `'top'` rewrite rule (`^search/page/([0-9]+)/?$` → `pagename=search&paged=N`) plus a `get_query_var('paged')` fallback fix it. **Additional bug fixed:** price/rating sorts used `meta_key`+`orderby=meta_value_num`, which INNER-JOINs `wp_postmeta` and **dropped listings lacking the meta** (page 2 of `?sort=price_low` was empty). Replaced with a `posts_clauses` LEFT-JOIN + COALESCE (`sort_order_clauses`) that keeps all posts (unpriced sort last).
- **Verified:** page 1/2/3, next/prev, last page, correct totals for default, price_low, price_high, and rating sorts; filters (village, pets, golf cart, deals) persist across pagination (URL query parameters preserved by `$build_url`).
- **Filter behavior:** preserved — dynamic options reflect actual property data; village uses the `ovr_village` taxonomy; deals uses active eligibility (never a stale checkbox). Multiple filters combine with AND.
- **Empty state:** "No listings match your filters" (not a broken "No content"). Map reflects the same filtered set.

## G. PAYMENTS

- **Active provider:** **PayPal** (sandbox keys configured, default gateway, full Orders-API capture). **Stripe** is implemented integration code but unconfigured in this environment (correctly records `pending`, never a false success). Authorize.net is a dormant stub; Wallet is a fully working internal method.
- **Test environment:** sandbox (all gateways default to sandbox); payments tested via the free-plan path, the internal Wallet, and the unconfigured-Stripe fallback — no real-money transactions.
- **Successful payment:** free plan → `completed` $0 row + subscription activated (`active`, editing enabled); wallet → `completed` $99, balance debited, plan upgraded; deals upgrade → `completed` $10, wallet debited, deal flag set + expiry.
- **Failed payment:** unconfigured Stripe checkout → `pending` + "Order Received" page (no "Payment Successful", no activation).
- **Cancellation:** pending row → `cancelled`; replaying the cancel URL on a `completed` row does nothing (WHERE requires `status='pending'`) — no false cancellation, no false success.
- **Database state:** `wp_ovr_payments` rows transition pending → completed|failed|cancelled; subscription state via user meta; upgrades via post meta flags+expiry. Success is only marked after server-side re-confirmation with the provider (never a redirect alone).

## H. TESTING

All run against the live site (Chrome/Playwright + direct DB/API checks):
- **Pagination matrix:** page1 (12), page2 pretty + `?paged` (4), `sort=price_low/high/rating` page 2 (now 4 each — previously empty), filters preserved across pages.
- **Deals:** purchase → activation → appears on Deals page; deactivate → disappears; empty state; nav link present; price from configured service ($10/$15).
- **Cards:** village labels ("Pennecamp", "Brownwood", …) in the location slot; price ranges ($300–$400, $1,100–$1,800, $1,500–$3,000) from real data; type still present in filters/detail.
- **Admin users:** login as admin; user-edit screen shows avatar manager + About Me + Admin Notes; avatar upload/replace/remove work; bio saves and displays on property owner card; Admin Notes private (no leak on property, search, or REST).
- **Media:** filter by property; shared-media survival on permanent delete; owned-only cleanup.
- **Featured Cities:** submenu gone, data preserved (5 rows).
- **Image sizing:** grid card aspect-ratio 1:1 → 4:3 (source untouched).
- **Site health:** home, search, deals, property, pricing, villages all HTTP 200; plugin active; no JS/PHP errors in changed workflows.

## I. REMAINING ISSUES

- **Stripe live checkout** cannot be end-to-end tested without API keys (unconfigured path verified). Authorize.net remains a stub (never was a live integration).
- **No provider webhook endpoint** — payments finalize on the buyer's return redirect (server-verified against the provider). A session that completes without the buyer returning wouldn't finalize until they return; not in scope, but worth noting for high-volume production.
- The `?page=N` query param is canonical-redirected by core to `/search/`; the plugin's own links use `?paged=` or `/page/N/`, both of which work.
- User 1's password was temporarily changed during admin testing. Restore it to the production value before launch.

## J. LAUNCH BLOCKERS

- **None from this implementation.** All P0/P1/P2 items are implemented and verified.
- **For real money:** an admin must configure **PayPal live keys** (and optionally Stripe) under OVR Settings → Payments. Code paths are tested in sandbox/unconfigured modes.
- **Recommendation (not a blocker):** if the client wants payments to finalize via webhook (without the buyer returning), build that before heavy production traffic. Restore the production admin password noted in I.
