# P25 — Production Readiness & Launch Report

**Date:** 2026-08-14
**Plugin:** ovr-core
**Scope:** Final production-hardening pass over the master prompt (items 1–53). Existing architecture preserved; no rebuilds, no new competitor features.

---

## A. PRODUCTION CHANGES

Four bugs were fixed; everything else was verified as already working end-to-end:

1. **Golf-cart search filter returned zero results** (`src/Property/PropertyQuery.php`, `templates/property/single.php`)
   The "Golf Cart" search filter queried only the `ovr_feature` taxonomy, but all live golf-cart data was stored under `ovr_amenity` (`golf-cart-included` / `golf-cart-extra-charge`) — 3 published properties (340, 346, 387) never matched, so the filter was permanently empty. Added `PropertyQuery::GOLF_CART_SLUGS` + `has_golf_cart()`, made the filter match the golf-cart terms across **both** taxonomies (OR), and switched the single-property "Golf Cart Included" spec chip to the same helper. Verified: filter now returns 3; chip renders.

2. **Media cleanup could delete a user's profile photo** (`src/Core/PropertyMedia.php`)
   `is_safe_to_delete()` claimed to protect user avatars but only queried `_ovr_property_id` user meta; the actual avatar meta is `ovr_avatar_id`. A shared avatar would be destroyed on property purge. Added an explicit `ovr_avatar_id` user-meta check alongside the existing one.

3. **Permanent-delete handler could bypass soft-delete** (`src/Admin/DeletedListingsAdmin.php`)
   `handle_purge()` allowed `wp_delete_post( $id, true )` on a *published* listing via a crafted URL (only the UI gated it). Added a `'trash' === $post->post_status` guard, matching the AJAX path in `PropertyListScreen`.

4. **Map view horizontal overflow on mobile** (`assets/css/ovr-public.css`)
   The split map view's mobile media query used `grid-template-columns: 1fr`, which cannot shrink below the nowrap card-title's min-content width → the layout viewport ballooned to 592px on a 390px phone. Changed to `minmax(0, 1fr)` (and `min-width: 0` on `.ovr-map-cards`). Verified all map/mobile layouts now fit 390px with zero overflow.

All test data (deals flag, wallet balance, payment row, wallet transaction, inquiry, review, bios, admin notes, deletion-test posts) was removed afterwards; the site was restored to its pre-pass baseline (16 published / 0 trashed properties).

> **Follow-up (village mega-menu links):** clicking any village from the "Villages Info" mega menu / Villages page produced WordPress's generic HTTP 500 fatal (bugs 6–7 above). The two fixes are in `src/Core/TemplateLoader.php` (new wrapper `templates/pages/village-landing-page.php`) and `src/Frontend/VillagePage.php` (taxonomy-based query). All 9 village pages now return 200 with their assigned properties, the empty state renders correctly, and unknown slugs 404.

## B. BUGS FIXED

| # | Bug | Root cause | Fix |
|---|-----|-----------|-----|
| 1 | Golf Cart search filter returns 0 results | Filter read `ovr_feature`; data in `ovr_amenity` | Cross-taxonomy golf-cart matching in `PropertyQuery::build_args()` |
| 2 | "Golf Cart Included" chip missing on single pages | `has_term('golf-cart-included','ovr_feature')` ignored amenity data | `PropertyQuery::has_golf_cart()` used in `single.php` |
| 3 | Media purge could delete user avatars | `is_safe_to_delete()` checked `_ovr_property_id` user meta, not `ovr_avatar_id` | Added `ovr_avatar_id` check |
| 4 | Purge URL could permanently delete a live listing | `DeletedListingsAdmin::handle_purge()` lacked a trash-status guard | Added `'trash' === $post->post_status` guard |
| 5 | Map view forced a 592px layout on mobile | `1fr` grid track can't shrink below nowrap title min-content | `minmax(0, 1fr)` + `min-width:0` |
| 6 | **Village pages (/village/{slug}/) returned HTTP 500** | `TemplateLoader` returned the bare `village-landing.php` partial for `is_tax('ovr_village')` with no `$village`/`$query` data | New `pages/village-landing-page.php` wrapper resolves the term and delegates to `VillagePage::render()` |
| 7 | **Village pages showed the wrong properties (e.g. The Villages = 0)** | `VillagePage::render()` filtered by the free-text `_ovr_village_name` meta instead of the `ovr_village` taxonomy term the archive represents | Query now uses `village_section` (taxonomy) — The Villages now shows its 3 assigned listings |

## C. TESTS PERFORMED

All run live against the site (HTTP + wp-cli + CDP/headless Chrome at 390px).

- **Pagination matrix:** `?paged=1/2/3`, next/prev, correct "Showing X–Y of Z", page 2 = 13–16 of 16 for default, `sort=price_low`, `price_high`, `rating`; page 3 correctly empty. Empty state is the proper "No listings match your filters" message.
- **Filter + pagination state:** `pets=1&per_page=3` → page-2 link preserves `pets=1`; navigating shows "Showing 4–5 of 5". Village/pets/golf-cart/deals all AND correctly.
- **Filters:** pets (5), golf cart (3 via `features[]` and `amenities[]`), deals (1), village `pennecamp` (1), combined `pets+golf` (1).
- **Deals lifecycle:** full wallet purchase of the 30-day deal via the real checkout form → payment row `completed` ($10), wallet debited, `_ovr_is_deal=1`, expiry = +30d, property appears on Deals page; simulated expiry → removed from Deals results; restored → reappears. Daily `expire_due` sweep clears stale flags.
- **Admin override:** admin logged in → dashboard opens (no "Subscription required"); admin property editor shows Admin Controls / Admin Notes / Reassign / Complimentary; landlord (active plan) sees none of them; landlord without access is gated (capability `manage_options`, no hard-coded IDs).
- **User management:** admin avatar upload/replace/remove handlers (nonce + capability), About Me writes to `wp_users.description` and renders on the owner card, Admin Notes private (absent from property page, search, REST property/users endpoints, homepage, and emails).
- **Deletion lifecycle:** soft delete → 404 on public + removed from search + media preserved; restore → republished; shared attachment survives one owner's purge, preserved while referenced anywhere; owned-only attachment cleaned on permanent purge.
- **Media library:** "Filter by property" renders (list mode) and narrows rows 22 → 7 for property 340; grid-view JS injects the filter dropdown.
- **Payments:** wallet purchase completed end-to-end; payment-success page shows order state. Unconfigured-gateway path (Stripe) records `pending` — never a false success (P15 path verified previously).
- **Email audit:** inquiry submission → landlord notification + guest confirmation with correct recipients and content, no admin-notes leak; all template recipients/triggers audited.
- **Reviews:** 5-star submit via public nonce → stored, auto-approved, rendered on page; moderation status preserved.
- **Mobile (390px, headless Chrome):** homepage, search (grid/list/map), property + calendar, pricing, villages, deals, login, register, dashboard, add-listing (new + edit), checkout, inquiries — **no horizontal overflow anywhere** after the map fix.
- **HTTP audit:** all key pages 200; no PHP errors in the changed workflows (only environment noise: Elementor deprecation notices, mailpit was offline until started for the email test).

## D. MIGRATION READINESS

Documented in `docs/P16-production-migration-plan.md` and `docs/P21-migration-readiness-report.md`; the admin importer (`MigrationImporter`) maps fields directly.

- **Maps automatically:** title/description/excerpt, owner email, status, bedrooms/bathrooms/beds/guests/sqft, base price/min stay, pets, address/city/state/zip/country, village name, lat/lng, video/ical URLs, village/property-type/amenity/rental-type taxonomies, featured image + gallery (CSV importer).
- **Requires manual handling / custom logic:** seasonal pricing rows (`ovr_seasonal_pricing`), calendar blocks, inquiries, reviews, wallet transactions, subscriptions/upgrade state and active promotions (deals/featured/bump expiries) — these are dynamic tables that need a row-level migration, not CSV field copy.
- **Intentionally excluded:** Featured Cities (retired; data preserved, not migrated), obsolete `top_of_page` paid services (retired `active=0`, historical rows kept), any legacy field with no equivalent.
- **Still to validate:** an end-to-end test migration on a staging copy with representative data (the P16 runbook sequences this before production).

## E. PAYMENT READINESS

- **Active provider:** PayPal (sandbox keys configured; default gateway). Stripe integration exists but is unconfigured → correctly records `pending`, never a false success. Authorize.net is a dormant stub (never live). Wallet is a fully-working internal method.
- **Test mode:** sandbox only; no real-money transactions were taken.
- **Successful payment:** deals upgrade via wallet → `completed`, debited, activated, appeared on Deals page.
- **Failed payment:** unconfigured gateway → `pending` + "Order Received", no activation (verified in P15).
- **Cancellation:** pending → `cancelled`; replaying cancel on a completed row does nothing (WHERE `status='pending'`).
- **Upgrade/subscription state:** payment row + post-meta flags + expiry + user subscription status all transition on `ovr_payment_completed` server-side re-confirmation — never on a redirect alone.
- **For real money:** an admin must configure **PayPal live keys** under OVR Settings → Payments and run one controlled transaction (the agreed ~$1 test) before accepting customer payments.

## F. DATA SAFETY

- **Deletion model:** soft delete (trash) → admin restore → permanent delete, auto-purged by cron after the retention window (default 180 days). Soft-deleted listings are invisible publicly, restorable, and never lose media.
- **Media deletion:** permanent delete only; `PropertyMedia::is_safe_to_delete()` preserves any attachment referenced by another property, a featured image, post content, a user profile (`ovr_avatar_id` — now checked), a settings option, or holding a `_ovr_property_id` elsewhere. Trash/archive/soft-delete never deletes media. Bias is toward preservation.
- **Backup / rollback:** `docs/P16-production-migration-plan.md` Phase 0 (full DB dump + files + settings snapshot + `ovr_db_version`/SHA) and Phase 3 (restore DB dump + files, or just the affected `wp_ovr_*` tables) document the rollback kit and 7-day retention.

## G. MOBILE QA

Tested at 390×844 via headless Chrome for horizontal overflow, and by inspection of the responsive CSS for the calendar (3 months ≤640px), filters sidebar, forms, cards, and map toggle:

- Public: homepage, search (grid/list/map), property page + calendar + pricing + gallery, villages, deals, featured, login, register.
- Landlord: dashboard, add-listing (create + edit), inquiries, checkout.
- Admin: property management, user management, media library, deleted-listings (overflow checks; wp-admin's own responsive styles apply).

**Remaining mobile issues:** none found in the plugin's own surfaces. Two pre-existing, theme-owned notes carried forward from P22: the header `.ovrv-actions` rail can overflow ~18px on very small (≤375px) phones (theme CSS, outside this plugin), and the horizontal header logo asset is not supplied (see H).

## H. REMAINING ISSUES

- **Passwords:** user 1's admin password and landlord user 2's password were temporarily changed during this and prior testing. They must be reset to their production values before launch.
- **Stripe live checkout** cannot be end-to-end tested without API keys (unconfigured path verified).
- **No provider webhook:** payments finalize on the buyer's server-verified return redirect. A session completed at the provider but never returned from wouldn't finalize until the buyer returns. Recommended (not a blocker) before high-volume production.
- **Elementor deprecation notices** appear in the PHP log (Elementor core, not this plugin); harmless but noisy.
- The `?page=N` pagination alias is canonical-redirected by core to `/search/`; the plugin's own links use `?paged=` or `/page/N/`, both verified working.

## I. DEFERRED FEATURES

- **Favorites** — not built (per explicit direction; revisit only with evidence of need).
- **Website-level testimonials migration** — handled after the property-review system is stable.
- **Advanced competitor features** (AI search, analytics, scoring, chat, CRM) — intentionally not implemented.
- **Horizontal header logo asset** — no wide logo exists; the current 1920×1280 asset renders as a small header sliver. A wide logo via OVR Settings → Header & Menu will be used automatically.

## J. LAUNCH BLOCKERS

1. **Configure PayPal live keys** (and optionally Stripe) under OVR Settings → Payments. Until then, real customer payments cannot be captured (sandbox/unconfigured paths are verified, but a live charge requires live credentials).
2. **Restore the production admin password** (and landlord test passwords changed during QA) before launch.
3. **No data-loss blockers:** deletion, media, and admin-note safety are verified. If the client's data was previously on a different platform, the staging test migration (P16 runbook) must be completed and validated before cutover; this is a sequencing requirement, not a code blocker.

---

**Verdict:** The platform meets the production-readiness definition in item 51 — the four bugs found were fixed and verified, no critical regressions remain, and the remaining items are configuration/credential steps plus the staging test migration.
