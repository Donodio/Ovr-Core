# OVR Core — QA Checklist & Regression Tracker

_Milestone 3, Feature 15. Last automated regression: 35/35 core checks passing
(see "Automated regression" below). Use this document before every release._

## How to use

1. Work top-to-bottom through the **Manual QA matrix**. Mark each row Pass / Fail / N-A.
2. Log any failure in the **Severity tracker** with a severity (S1–S4) and owner.
3. Re-run the **Automated regression** bootstrap after fixes.
4. A release is shippable when there are no open **S1/S2** issues.

### Severity scale

| Sev | Meaning | Examples |
|-----|---------|----------|
| **S1 — Blocker** | Core flow broken, data loss, security hole | Can't create a listing; payment not recorded; PII leak |
| **S2 — Major** | Important feature broken, no workaround | Search returns nothing; emails never send |
| **S3 — Minor** | Feature works with a workaround / cosmetic on key page | Misaligned card; wrong label |
| **S4 — Trivial** | Polish / edge case | Spacing nit on an admin sub-tab |

---

## Manual QA matrix

### A. Authentication & accounts (M1)
- [ ] Register a new account → welcome email received → redirected to onboarding.
- [ ] Log in / log out; "Forgot password" sends a reset link that works.
- [ ] Login lockout triggers after the configured failed-attempt limit (Settings → Security).
- [ ] Admin 2FA email OTP prompts on login when enabled (escape hatch: `OVR_DISABLE_2FA`).

### B. Membership & checkout (M1/M2)
- [ ] Pricing page lists plans; selecting one reaches checkout.
- [ ] Completing payment activates the membership and records a row in Payments.
- [ ] Subscription renewal / expiry emails fire (check Emails templates enabled).
- [ ] Listing gate: a member without an active paid subscription cannot publish a listing.

### C. Listings (M2/M3)
- [ ] Landlord creates a listing (all tabs): general, pricing, photos, location, availability.
- [ ] Photos upload, reorder, caption, rotate; watermark applied; orientation correct.
- [ ] Pricing model (weekly / monthly / fixed-block) saves and displays.
- [ ] Edit, Delete, Bump from the dashboard each work; deleted listing lands in Deleted Listings.
- [ ] Single-listing page: gallery, map, video/panorama, reviews, inquiry form, phone reveal.
- [ ] SEO box (sidebar): custom meta title/description/noindex respected on the front end.

### D. Search & map (M2/M3)
- [ ] Search filters (village, type, price, beds, dates) return correct results.
- [ ] Featured rail + bump ordering show boosted listings first.
- [ ] Map view: pins coloured by type, gold ring on featured, dimmed when booked tonight; legend shows.
- [ ] Map engagement counter on Platform Overview increments after interacting with the map.

### E. Reviews, inquiries, bookings (M2/M3)
- [ ] Submit a review → admin moderation → approval timestamp + appears publicly.
- [ ] Inquiry sends landlord + guest emails; appears in CRM/inquiries.
- [ ] Booking + availability block sync; iCal export/import.

### F. Milestone 3 admin features
- [ ] **Homepage Slides**: add slides → set hero Background = Slideshow in Elementor → rotates on homepage; falls back to single image when empty.
- [ ] **Ad Banners**: create a banner per placement; `[ovr_ad_banner placement="…"]` renders it; impressions + clicks increment; CTR shown.
- [ ] **Emails**: edit a template, preview, test-send; disabling stops that email.
- [ ] **Paid Services**: create/edit/enable/trash; renewable / auto-renew flags; purchase reporting cards.
- [ ] **Settings**: General / Listings caps / Media / Security / Storage tabs save and take effect.
- [ ] **Audit Log**: actions recorded with actor/old/new; filters + CSV/Excel export.
- [ ] **Cloud Storage**: status, coverage stats; Offload Pending + Restore Missing (when B2 configured).
- [ ] **Import (CSV)**: upload → map → dry run → import creates listings with meta + terms + images.
- [ ] **Global Search**: finds listings/members/payments/reviews/inquiries by keyword/ID.

### G. Performance & accessibility (M3)
- [ ] WebP siblings generated on upload; WebP served to Chrome (DevTools → Network shows `.webp`).
- [ ] Map-points query cached (second map load faster); cache busts after editing a listing.
- [ ] Keyboard: Tab reaches all controls with a visible focus ring; icon-only buttons announce a label.
- [ ] Images have alt text or are marked decorative; no screen-reader filename noise.

### H. Cross-cutting
- [ ] Every page is mobile-responsive at 400 / 600 / 768 / 1024 / 1100 px.
- [ ] Dark buttons/backgrounds keep visible white text.
- [ ] No PHP notices/warnings in `debug.log` during the flows above.

---

## Automated regression

Bootstrap WordPress against the live DB and run the smoke script (see
`docs/HANDOVER.md` → "Verifying a deploy"). Latest run on the development site:

```
REGRESSION: 35 passed, 0 failed
```
(One historical assertion about a non-existent `ovr_pages` option array was a
bad test, not a product bug — pages are stored as individual `ovr_page_*`
options. The corrected suite is all-green.)

Covers: CPT + taxonomies + roles; all 15 custom tables; DB schema version;
core class autoload; key shortcodes; search + map queries; scheduled cron.

---

## Severity tracker

| # | Date | Area | Description | Sev | Status | Owner |
|---|------|------|-------------|-----|--------|-------|
| _example_ | 2026-06-15 | Search | (none open) | — | Closed | — |

_No open S1/S2 issues at Milestone 3 close._
