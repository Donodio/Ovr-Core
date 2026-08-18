# Engineering Report — General Property Info, Map, Testimonials, Guests, Navigation, Inquiries & Payments

**Date:** 2026-08-13
**Plugin:** ovr-core
**Scope:** Master implementation prompt (Phases 1–7). Implemented within the existing architecture; all pre-existing working functionality preserved.

---

## 1. IMPLEMENTED

**Phase 1 — Property Information** (verified intact from prior work + one hardening fix)
- **Rename "Location" → "What's Near"** — the `_ovr_nearby` content block is branded **"What's Near"** on the property page (`templates/property/single.php`). The underlying DB field (`_ovr_nearby`) is unchanged (§5 — no destructive migration). Editor label synced to "What's Near".
- **Conditional What's Near / Policies / Payment Information** — three independent folded blocks under the long description. Each renders only when it has meaningful content. Added a robust **meaningful-content detector** that strips HTML and whitespace so `"<p></p>"`, `"   "`, and empty markup count as empty (§8). This was the one gap found: whitespace-only HTML was previously treated as content.
- **Features/Amenities + Views** — preserved in the Amenities tab, no changes (§12–14).

**Phase 2 — Property Map** (verified intact)
- Map renders via Leaflet + OSM; exact coordinates are jittered ~0.0008 for **approximate-location privacy** (§18–20). The `needs_single_map()` fix (village-term listings) is in place.
- **Graceful handling** verified: properties with no coordinates render no map div, no empty container, no JS errors, and the page remains fully functional (§15–17).

**Phase 3 — Testimonials** (verified intact + full matrix tested)
- **Write Testimonial** works (AJAX path with `ovr_public_nonce`, fixed in prior pass). Form opens, validates (rating/name/email/body), submits, associates with the correct property, enters moderation.
- **Moderation**: submissions are `pending` when `review_approval` is on; admin approve/reject works; approved 4+-star reviews display; rejected reviews never display.
- **4-star rule** enforced on the property-page Testimonials tab (matching the site-wide rule).

**Phase 4 — Navigation** (verified intact)
- **Back to Results** button on the property page with `aria-label`, icon `aria-hidden`, keyboard accessible.
- **Search context preserved** via `?ovr_ref=` (exact filtered + paginated URL stamped on every result card and map popup). Verified `sort=price_low&per_page=12&paged=1` round-trips.
- **Safe fallback**: direct property URL → `/search/`; no open redirect (URL validated against the search-page prefix).

**Phase 5 — Guest Default** (verified intact)
- Inquiry-form guests defaults to **2** (§33–34), user-changeable, and **respects property max occupancy** (falls back to 1 when max < 2) (§35).

**Phase 6 — Inquiries**
- **Removed the dashboard Reply form + "Email instead" composer + REST `/reply` endpoint** (§43). Inquiries are now a record/history system with no in-dashboard messaging.
- **Contact info displayed**: name, email (mailto), phone, message, check-in/check-out, guest count — expandable detail row.
- **12-month window**: dashboard query hard-limited to `created_at >= NOW() - INTERVAL 12 MONTH` (§45). Older records are NOT deleted by this (retention is separate: the `inquiry_retention` setting drives the purge cron; display range and retention are independent) (§46).
- **Property association**: each inquiry stores `property_id` + `landlord_id`; the dashboard filters strictly by `landlord_id` and every read/delete checks ownership (§49, §67).
- **Safe delete**: added a nonce-protected, ownership-checked, JS-confirmed **permanent delete** (`ovr_inquiry_delete`). Invalid/foreign IDs bounce back safely. This matches the platform's permanent-purge behavior (§47).
- **No messaging system**: no chat, threads, inbox/outbox, replies, read receipts, or typing indicators.

**Phase 7 — Payments**
- **Stale references corrected** (comments only — no integration removed):
  - `src/Payment/CheckoutHandler.php` — "Phase 1: Stripe stub…" → describes the real hosted-checkout + server-side finalize flow.
  - `src/Payment/PaymentGateway.php` — "Phase 1 ships a Stripe stub…" → describes the actual provider implementations.
  - `src/Frontend/Checkout.php` + `templates/pages/checkout.php` — "future PCI tokenizer" → card capture delegated to Stripe Checkout / PayPal hosted pages.
- **Business-friendly labels** (user-facing, per §53): "Card (Stripe)" → **"Card"** in `src/Frontend/PaymentSuccess.php` and `templates/dashboard/tab-payments.php`. The checkout page already used business language ("Credit Card", "PayPal", "On Account"). The admin **Stripe configuration UI is retained** — it's legitimate admin config, and Stripe integration code is preserved.

## 2. BUGS FIXED

1. **Whitespace/empty-HTML content shown as real content** — a property whose What's Near was `"<p></p>"` rendered an empty section. Fixed with a meaningful-content detector (`single.php`) that strips HTML + whitespace.
2. *(From prior pass, re-verified this session)* **Write Testimonial 403 "Cookie check failed"** — REST nonce mismatch; switched to the `ovr_public_nonce` AJAX path.
3. *(From prior pass, re-verified)* **Blank map for village-term-only listings** — `needs_single_map()` now honors the `ovr_village` term.
4. *(From prior pass, re-verified)* **Pricing table mobile overflow** — constrained the table container.
5. **Stale "Phase 1 Stripe stub" documentation** across 4 files — corrected to reflect the real architecture (not a functional bug, but misleading).

## 3. TESTS PERFORMED

All tests run against the live site (Chrome via Playwright + direct DB/API checks). Not theoretical.

**Empty-content combinations (§60)** — PASS all four:
- A (all populated) → What's Near + Policies + Payment
- B (nearby empty) → Policies + Payment only
- C (policies/payment empty) → What's Near only
- D (all empty incl. `"<p></p>"`) → no sections, no whitespace

**Testimonials matrix (§61)** — PASS:
- Write Testimonial opens → form validates empty submission
- 5-star submit with moderation ON → **pending** (not published)
- Admin approve → **approved** → displays on property
- Admin reject → **rejected** → never displays
- Property A review appears only on A; B's review never appears on A (property association)

**Map matrix (§62)** — PASS:
- Valid coords → Leaflet renders tiles + marker
- No coords / no village → no map div, no JS errors, page works
- Approximate marker (jitter) present; internal address untouched
- Desktop + mobile render

**Back button matrix (§63)** — PASS:
- From search → returns to exact filtered results (`sort=price_low&per_page=12&paged=1`)
- Direct URL → safe fallback to `/search/`

**Guest default (§33-35)** — PASS: default 2 on max≥2 properties; default 1 on max=1 property; user-changeable.

**Inquiries (§64)** — PASS:
- New inquiry appears in landlord history; correct property (`#387`) association
- Contact email + phone + message visible
- 12-month window enforced in query
- Delete (confirmed) removes the row permanently; foreign/unauthorized IDs rejected
- No reply/messaging UI (REST reply route returns 404)

**Payments (§65)** — PASS:
- Checkout page loads for free + paid plans
- Free plan (`base_subscriber`) → `completed` $0 payment + subscription activated (`active`, editing enabled)
- Wallet checkout → `completed` $99, wallet debited 500→401, plan upgraded
- Cancel URL on pending row → `cancelled` (never `completed`)
- Cancel replay on completed row → stays `completed` (state integrity)
- Unconfigured Stripe checkout → `pending` + "Order Received" page (no false success)

## 4. DATA SAFETY

- **Property data**: no fields renamed or migrated. `_ovr_nearby`, `_ovr_policies`, `_ovr_payment_info` unchanged; only presentation labels changed.
- **Testimonials**: review rows preserved; moderation status (pending/approved/rejected) never auto-deleted; only my created test rows were removed after testing (pre-existing rows 1, 3 untouched).
- **Inquiries**: 12-month display window does NOT delete older records — retention is governed separately by the existing `inquiry_retention` purge cron (default 365 days). Removed reply functionality but kept all existing inquiry rows and their `responses` data intact (no data deleted).
- **Payment records**: all `wp_ovr_payments` rows preserved. Only my temporary test rows (44–47) were cleaned after testing.
- **Map/address**: the exact `_ovr_address`, `_ovr_latitude`, `_ovr_longitude` are preserved internally. The public map uses a jittered approximate marker — the real address is never overwritten.

## 5. PAYMENT ARCHITECTURE

- **Current provider**: **PayPal** (Orders API) is the active, configured provider (sandbox keys present, default gateway). **Stripe** (Checkout Sessions) is real, fully-implemented integration code but **currently unconfigured** (no API keys) — it correctly falls back to "pending" rather than charging. **Authorize.net** is a dormant stub. **Wallet** (internal store credit) is a fully working instant method.
- **Where integrated**: `src/Payment/CheckoutHandler.php` (start + finalize), `src/Payment/StripeGateway.php`, `src/Payment/PayPalGateway.php`, `src/Payment/WalletGateway.php`. Success is confirmed **server-side by re-checking with the provider on the buyer's return** — never by the success redirect alone. No webhook endpoint exists; confirmation is return-redirect + provider API verification (idempotent against replay).
- **Stale references removed**: 4 stale "Phase 1 stub" comments corrected; user-facing "Card (Stripe)" → "Card". No active Stripe/PayPal integration code was removed.
- **Flows tested**: free plan, wallet, cancel, cancel-replay integrity, unconfigured-Stripe pending fallback.

## 6. REMAINING ISSUES

- **Stripe live checkout cannot be tested end-to-end** in this environment because no Stripe API keys are configured. The unconfigured path (→ pending, no false success) is verified; a real Stripe flow requires an admin to add keys.
- **No webhook endpoint**: if the client wants payments to finalize without requiring the buyer's return redirect (e.g., a session completes outside the browser), a provider webhook would be needed. Not added (out of scope — not requested; current behavior is functional).
- **Authorize.net is a stub** with config UI only — not a live integration. If the client expects Authorize.net to work, that is a separate build.
- **Header `.ovrv-actions` overflow (~18px on ≤375px)** — pre-existing theme header issue, unrelated to this scope.

## 7. LAUNCH BLOCKERS

- **None for this scope.** All Phases 1–7 are implemented and verified.
- **For live payments specifically**: an admin must add real **PayPal live keys** (and optionally **Stripe keys**) under OVR → Settings → Payments before accepting real money. This is configuration, not a code defect — the code paths are tested in sandbox/unconfigured modes.
- **Recommendation (not a blocker)**: if the client needs payments to finalize via webhook (rather than the buyer returning to the site), that should be built before heavy production traffic.
