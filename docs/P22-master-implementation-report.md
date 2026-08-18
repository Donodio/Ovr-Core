# Master Implementation Report — Property Navigation, Calendar, Front-End Display, Admin, Theme & Media

**Date:** 2026-08-13
**Plugin:** ovr-core
**Scope:** Master implementation prompt (48 requirements) — implemented within the existing architecture, preserving all working functionality.

---

## Completed

| # | Requirement | Status | Implementation |
|---|-------------|--------|----------------|
| 2 | Back-to-results control | **Done** | Relabelled "Go Back" → **"Back to Results"** (`templates/property/single.php:271`); `aria-label="Back to results"` + icon `aria-hidden`. Existing `?ovr_ref=` mechanism already preserves search query / village / filters / pagination / sorting; verified end-to-end. |
| 3 | 15-month calendar window | **Done** | `months_ahead` 18 → **15** (`single.php:406`, `calendar.php:19`). |
| 4 | Five months at a time | **Done** | Rebuilt as a sliding window: 5 months visible (desktop), prev/next shift by 1, all 15 months stay in the DOM (`is-hidden` toggling) so the range-picker still reads every date (`calendar.php`). |
| 5 | Calendar accessibility | **Done** | `aria-label="Previous months"` / `"Next months"`, `aria-controls`, `disabled` states at the ends, `focus-visible` ring, `aria-hidden` on chevron icons. |
| 6 | Calendar responsiveness | **Done** | 3 months on ≤640px, nav arrows stay usable, no horizontal page overflow from the calendar. |
| 7 | Pricing public display | **Done (was working)** | `_ovr_hide_pricing` honored; verified hide → "See Description For Pricing", un-hide → table returns. |
| 8 | Pricing data never deleted | **Done (was working)** | Rows stored in `ovr_seasonal_pricing`; `_ovr_hide_pricing` only toggles display. **Verified: 3 rows retained while hidden.** |
| 9 | Admin pricing editor | **Done (was working)** | Editor always shows/manages rows; public display gated independently. |
| 10 | General info tab | **Done** | Description + "What's Near" + Policies + Payment Information folded under the description. |
| 11 | Rename Location → What's Near | **Done** | Front-end label `"What's Near"` (`single.php:448`); editor label synced. DB field `_ovr_nearby` unchanged (per spec). |
| 12–15 | What's Near / Policies / Payment Information (independent, conditional) | **Done** | Three independent folded blocks; each only renders when populated. Verified: all three show when present, all hidden when empty (property 387 = 0 blocks). |
| 16 | Documents persist/reorder/delete/display | **Done (was working)** | Verified existing `_ovr_document_ids` flow; no duplicate system. |
| 17 | Features/Amenities | **Done (was working)** | Unchanged, remains in Amenities tab. |
| 18 | Property views | **Done (was working)** | Unchanged per client direction. |
| 19 | Golf cart (existing model) | **Done** | Preserved taxonomy-term model (`golf-cart-included`), per approved decision. |
| 20 | Golf cart on property summary | **Done** | Added "Golf Cart Included" spec chip to the specs strip (`single.php`). Verified show/hide. |
| 21 | Property map fix | **Done** | Fixed `needs_single_map()` to also consider the `ovr_village` term (`src/Core/Assets.php`), fixing blank maps for village-term-only listings. Verified Leaflet renders (tiles + thumb-tack). |
| 22 | Map location privacy | **Done (was working)** | Marker jittered ~0.0008 (street-level); "approximate location" caption. |
| 23 | Write Testimonial button | **Fixed** | Was sending an unprinted `wp_rest` nonce → 403 "Cookie check failed". Switched to the proven `ovr_public_nonce` admin-ajax path (`reviews-section.php`). Verified submit returns success + persists. |
| 24 | 4-star moderation rule | **Done** | Applied the site-wide 4-and-above rule to the property-page Testimonials tab too (`Reviews::get_for_property` gains `$min_rating`, default 4). Verified 3-star hidden, 5-star shown. Moderation pending/approve flow preserved. |
| 25 | Admin review management | **Done (was working)** | `ReviewsAdmin` screen + pending badge + approve/reject/delete/restore existing. |
| 26 | Guest default = 2 | **Done** | Inquiry-form guests dropdown defaults to 2 (`inquiry-form.php`), clamped when max < 2. Verified 2 on max=4 property, 1 on max=1 property. |
| 27 | Admin login | **Done (was working)** | Verified admin authentication + full admin environment. |
| 28–29 | Theme color schemes | **Built (rebuilt, not restored)** | No legacy palettes existed anywhere (code/DB/git — verified). Created `ThemeSchemes` with 6 predefined palettes applied site-wide via CSS custom properties (`--ovr-primary`, `--ovr-secondary`, `--ovr-gold`, …) with a selector in Settings → General. Verified selection persists and changes the live site globally. |
| 30 | WP dashboard vs OVR Properties separation | **Done (was working)** | No WP functionality removed; OVR menus remain under its own submenu. |
| 31–34 | Property media association / metadata | **Done** | Every property's media (gallery, documents, video, panorama, featured image) gets `_ovr_property_id` back-reference on save (`PropertyMedia::stamp_associations`). Back-filled 18 properties / 32 attachments. |
| 35–37 | Safe media deletion | **Done** | On **permanent delete only** (post already trashed), attachments are removed only when safe. `is_safe_to_delete` checks: other-property references, featured images, user profiles, content URLs, `_ovr_property_id`, settings/theme options. Trash/soft-delete never deletes. **Verified shared media survives.** |
| 38 | Property ID organization | **Done** | Media-library "Filter by property" dropdown (list view server-rendered; grid view injected via JS). Verified filtering to a property shows only its media. |
| 39–40 | Header logo | **Verified** | Single existing asset (`ovr-logo.png`, 1920×1280) used with aspect ratio preserved (no distortion). **No dedicated horizontal asset exists** to switch to (see Deferred). |
| 41 | Admin property management | **Done (was working)** | Full CRUD + meta boxes + filters confirmed. |
| 42 | Subscription restriction bug | **Verified fixed** | Every gate carries a `manage_options` bypass (e.g. `ListingForm.php:913`). **Verified live: admin uploaded a document successfully** (200, attachment created) with no subscription block. |
| 43 | Regression testing | **Done** | See Tested section. |
| 44 | Don't break existing data | **Done** | No DB columns/meta keys renamed; only additive meta. Pricing/review/media data preserved. |

## Tested

All verified live against the running site (Chrome, Playwright):

- **Property page:** renders; Back button label + returns to exact filtered results (`sort=price_low&per_page=12` preserved); What's Near / Policies / Payment each conditional; empty sections hidden.
- **Calendar:** 15 months total; 5 visible initially (Aug 2026); prev disabled at start; next shifts to Sep; prev enabled; end state Jun–Oct 2027 with next disabled; mobile shows 3; no calendar overflow.
- **Map:** Leaflet loads, 18 tiles, thumb-tack present; village-term-only listing now loads the map (previously blank).
- **Golf cart:** chip shows when term assigned, hides when removed.
- **Guests:** default 2 (max=4), fallback 1 (max=1).
- **Pricing:** hide → table gone + "See Description For Pricing"; **3 rows retained in DB**; un-hide → table returns.
- **Testimonials:** Write Testimonial opens; 5-star submit → success (`review_id` created, auto-approved); 3-star approved review hidden from page, 5-star shown.
- **Admin:** login works; settings color-scheme selector (6 options) saves and applies globally; document upload as admin succeeds (no subscription block).
- **Media:** list-view filter shows exactly a property's 10 photos; permanent delete removes owned-only attachments; shared attachment survives owner deletion, removed after last reference.
- **Site-wide:** homepage, search, search-map, villages, about, contact, pricing, login all render (no regressions).

## Bugs Found (during testing)

1. **Write Testimonial 403 "Cookie check failed"** — REST path used an unprinted `wp_rest` nonce. **Fixed** (switched to `ovr_public_nonce` admin-ajax).
2. **Blank map for village-term-only listings** — `needs_single_map()` missed the `ovr_village` term. **Fixed.**
3. **Pricing table horizontal page overflow on mobile** — table's `min-width` pushed past the viewport. **Fixed** (`max-width:100%` on `.ovr-rates-wrap`).
4. **Pre-existing (not fixed):** header `.ovrv-actions` rail overflows ~18px on ≤375px — a theme header layout issue outside the property-page scope (noted for the client).

## Deferred

- **Horizontal header logo asset:** only one logo (`ovr-logo.png`, 1920×1280 portrait-ish) exists; the header uses it without distortion. A dedicated wide/horizontal logo would better fit the header, but no such asset exists to switch to. The client's "narrow/long logo" recollection suggests a missing asset — upload a wide variant via OVR Settings → Header & Menu ("Logo Height" + "Header Logo") and it will be used automatically.
- **Original color-scheme palettes:** none existed in the codebase, database, or git history, so palettes were **built fresh** from the current design system rather than recovered. If the client can supply the original palette values, they can be pasted into `ThemeSchemes::palettes()`.
- **Multi-state golf cart (Electric/Gas/Included/etc.):** per approved decision, the existing boolean taxonomy model was preserved rather than building a new data model + migration.

## Data Safety

- **Media association:** each property's media carries `_ovr_property_id` (attachment meta). It is written on property save and back-filled for existing listings. Gallery/docs/video/pano are still owned by the property via `_ovr_gallery_ids`, `_ovr_document_ids`, `_ovr_video_id`, `_ovr_panorama_id` — unchanged.
- **Deletion:** platform uses **soft delete (trash) → admin restore → permanent delete (auto-purge after 180 days)**. Trash/restore never deletes media. Only permanent delete triggers cleanup, and only for attachments proven safe (see checks in `PropertyMedia::is_safe_to_delete`).
- **Shared media protection:** an attachment referenced by any other property, featured image, post content, user profile, settings option, or holding a `_ovr_property_id` pointing elsewhere is **never** deleted. Verified with a shared-attachment experiment.
- **Pricing:** hiding the table never touches rows (`_ovr_hide_pricing` is display-only).
- **Reviews:** moderation status (pending/approved) and the 4-star display threshold are separate; submitted reviews are stored before moderation.

## Theme (color schemes)

- **Did it exist?** No. No color-scheme selector, palette option, or customizer control existed in the plugin or theme (verified via code + git history + DB).
- **Restored / rebuilt?** **Rebuilt** as a new `ThemeSchemes` module (6 palettes) because nothing original existed to restore.
- **Why not recovered?** No original palette values were stored anywhere.
- **How it applies:** a small `:root` CSS custom-property override printed site-wide (after the core stylesheet), so no page markup or per-page edits are needed.

## Launch Blockers

- **None functional.** All required behaviors are implemented and verified on the live site.
- **Recommendations before production launch (not blockers):**
  1. Upload a proper **horizontal header logo** (wide aspect) — the current 1920×1280 asset renders as a small sliver in the header.
  2. Optionally fix the header `.ovrv-actions` 18px overflow on small phones (theme `header.css`).
  3. If the client remembers specific original color palettes, supply them to replace the newly-created defaults.
  4. Confirm the `review_approval` setting (currently off → auto-approve). If the client wants all reviews moderated, enable it in OVR Settings → Subscriptions.
