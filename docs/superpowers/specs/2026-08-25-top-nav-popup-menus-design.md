# Top Navigation Popup Menus — Design

**Date:** 2026-08-25
**Status:** Approved (user confirmed all four design sections)
**Milestone branch:** `milestone-3`

## 1. Summary

Replace the current flat top navigation with three popup (dropdown) menus, each item carrying a Material Symbols icon:

1. **Explore Rentals** — always visible
2. **Site Information** — always visible
3. **My Account** — visible only for logged-in landlord-capability users

Existing dropdown infrastructure (`templates/components/header-nav.php`, click/Escape/outside-click JS) is reused unchanged behaviorally; this work changes the menu *data* and extends rendering to show icons and disabled items.

## 2. Decisions Made

| Question | Decision |
|---|---|
| Relationship to existing nav links | **Replace** current flat links (Home, Search Listings, Villages ▾, Deals & Cancellations, Account, About, Contact) with the two always-on dropdowns (+ My Account when logged in). Logged-out action buttons (Log In / List Your Property) stay. When logged in, the actions area keeps the search icon, Favorites icon, and gold Dashboard button; the separate "Sign Out" pill button is removed — logout lives in My Account ▾, eliminating the duplication. |
| Static page content | Create pages now with sensible placeholder copy, admin-editable in WP admin (same pattern as About page). |
| Villages section grid | **New dedicated shortcut page**; existing Villages archive stays untouched. |
| Villages ID Request | Web form → client-side filled PDF via bundled pdf-lib. |
| Approach | Extend code-defined role-aware nav arrays in `Header` (Approach A). |

## 3. Navigation Structure

### 3.1 Explore Rentals ▾ (always)

| Item | Icon | Target |
|---|---|---|
| Search All Rentals | `search` | `/search` (clean URL, no filters) |
| Featured Properties | `star` | `/search?featured_only=1` |
| Deals & Cancellations | `local_offer` | `/search?deals_only=1` |
| Long Term Rentals | `event_repeat` | `/search?rental_type=long-term-rental` |
| Newest Listings | `fiber_new` | `/search?sort=newest&per_page={newest_listings_count}` |
| Search by Village Section | `map` | new shortcut page (`ovr_page_village_sections`) |
| Map Search | `location_on` | `/search?view=map` (full-screen map + filter sidebar left) |
| Renting in The Villages – An Overview | `menu_book` | static page |
| Verified Owners | `verified` | static page |

### 3.2 Site Information ▾ (always)

| Item | Icon | Target |
|---|---|---|
| Rental Owner Information | `real_estate_agent` | static page |
| The Villages Lifestyle | `diversity_3` | https://www.thevillages.com/lifestyle/ (external ↗) |
| The Villages Town Squares | `storefront` | https://www.thevillages.com/shopping-dining/ (external ↗) |
| Golf The Villages | `golf_course` | https://www.golfthevillages.com (external ↗) |
| OVR User Agreement | `gavel` | static page |
| Forgot My Password | `lock_reset` | forgot-password page (`ovr_page_forgot_password`) |
| Contact OVR | `mail` | contact page with new form → emails `support_email` |
| Sign up to Advertise | `campaign` | register page (`ovr_page_register`) |
| Site Testimonials *(FUTURE)* | `reviews` | disabled — no link |
| OVR Business Partners *(FUTURE)* | `handshake` | disabled — no link |

### 3.3 My Account ▾ (logged-in non-admin only)

| Item | Icon | Target |
|---|---|---|
| My Dashboard | `dashboard` | dashboard page |
| My Listings | `home_work` | dashboard `tab=properties` |
| My Inquiries | `forum` | dashboard `tab=inquiries` |
| Online Villages ID Request | `badge` | new form page |
| Villages Guest Passes | `confirmation_number` | https://gcs.thevillages.com/cgi-bin/gc100 (external ↗) |
| Log Out | `logout` | `wp_logout_url( home_url( '/' ) )` |

Admin users keep today's behavior: visitor menus plus "Site Admin" jump in the actions area. The current landlord nav's Reviews (`tab=reviews`) and Membership (`tab=subscription`) links are retired from the top nav — both remain reachable inside the dashboard sidebar, which is their primary home.

## 4. Implementation Units

### 4.1 Nav data (`src/Frontend/Header.php`)

- Rework `visitor_nav_items()` → two groups: `explore` (label "Explore Rentals") and `site_info` ("Site Information"), each with `children`.
- Rework `landlord_nav_items()` → same two public groups plus `account` ("My Account") group.
- Every child item gains `icon` (Material Symbols name) and optionally `disabled => true`, `target => '_blank'`.
- `menu_nav_items()` (Appearance→Menus override at location `ovr_primary`) extended to include child items so admin customization keeps working; icons default sensibly when a custom menu supplies none.

### 4.2 Header template (`templates/components/header-nav.php`)

- Dropdown links render `<span class="material-symbols-outlined">icon</span>` before the label.
- Disabled children render muted, non-clickable rows with `aria-disabled="true"` + "Coming soon" hint.
- Active-group highlighting: trigger gets `.active` when current request belongs to its group (extends `Header::detect_active_nav()`).
- Mobile drawer renders identical groups/icons via existing group-title pattern.
- CSS additions in `assets/css/ovr-public.css` (or existing topnav stylesheet section): icon sizing/alignment, disabled state, active trigger state.

### 4.3 New plugin pages (`src/Core/Pages.php`)

Bump `PAGES_VERSION '7'` → `'8'`. Additions:

| Option key | Title | Slug | Content |
|---|---|---|---|
| `ovr_page_village_sections` | Browse by Village Section | `village-sections` | `[ovr_village_sections]` |
| `ovr_page_renting_overview` | Renting in The Villages – An Overview | `renting-in-the-villages` | placeholder prose |
| `ovr_page_verified_owners` | Verified Owners | `verified-owners` | placeholder prose |
| `ovr_page_owner_information` | Rental Owner Information | `rental-owner-information` | placeholder prose |
| `ovr_page_user_agreement` | OVR User Agreement | `user-agreement` | placeholder prose |
| `ovr_page_id_request` | Online Villages ID Request | `villages-id-request` | `[ovr_id_request]` |

Placeholder prose follows the About-page pattern (`wp:paragraph` blocks, editable in admin).

Contact page sync: during `maybe_sync_pages()`, if `ovr_page_contact` exists and lacks `[ovr_contact_form]`, append it (idempotent `has_shortcode()` check; preserves admin edits).

### 4.4 Villages Section shortcut page

- New shortcode `[ovr_village_sections]` in `ShortcodeManager` + template `templates/pages/village-sections.php`.
- Data source identical to search-results chip strip: `SearchFilters::get_villages()` terms + `SearchFilters::get_village_image()` + term counts.
- First card: **All Areas** (stone-wall banner fallback image, links to clean `/search`), then one large card per section term, **2-across × 3-down desktop**, stacked on mobile. Click → `/search?village_section[]=slug`.

### 4.5 Contact OVR form

- New `[ovr_contact_form]` shortcode + AJAX handler. Anti-spam is **new behavior** (no existing form has it): nonce check, hidden honeypot field, and a transient-based per-IP throttle (max 5 submissions/hour).
- Fields: Name, Email, Phone (optional), Subject, Message.
- Delivery: existing `Mailer` using the already-defined `contact_form` email template (`EmailTemplates.php` line ~170); recipient = `support_email` setting (falls back to site admin email). Phone and Subject are folded into the message body as labeled prefix lines (`Subject: …`, `Phone: …`) rather than extending the template's variable list.
- Distinct JSON errors for nonce failure / validation / mail failure.

### 4.6 Newest Listings setting

- New setting `newest_listings_count` (default `12`), sanitized as positive int, field placed beside *Listings per page* in Settings.
- Menu URL: `/search?sort=newest&per_page={newest_listings_count}`. Note: `sort=newest` is already `SearchHandler`'s default — the explicit param is belt-and-suspenders so the intent survives future default changes.

### 4.7 Online Villages ID Request

- Template `templates/pages/id-request.php` via new `[ovr_id_request]` shortcode.
- Senior-readable form sections mirroring the Lifestyle ID request: property owner info, renter/guest details, rental term dates, IDs requested.
- Field schema = single documented PHP array constant: `label / type / required / pdf_field`.
- PDF generation **client-side** with pdf-lib bundled at `assets/js/vendor/pdf-lib.min.js`, enqueued only on this page. No PII stored server-side; resident downloads/prints the result.
- Two modes:
  - **Fill mode:** admin uploads LifestyleIDForm2025.pdf via Settings media picker (new option `id_form_template`). AcroForm fields filled by schema mapping; unmatched fields degrade gracefully.
  - **Built-in mode (default):** pdf-lib composes a clean letterhead-style printable sheet from entered data. Ships first — it has no dependency on the original PDF file.
- Settings shows an admin notice if `id_form_template` points at a non-PDF file.
- This unit is the least coupled in the spec and can be planned/implemented as its own follow-up chunk if the plan grows too large.

## 5. Error Handling

- All menu URLs resolve through `Pages::get_page_url()` (missing/unpublished page → home URL fallback).
- Contact AJAX: nonce / validation / `wp_mail` failure return distinct errors; honeypot + rate limiting.
- ID form: invalid/non-PDF template → automatic built-in mode fallback + Settings notice.

## 6. Testing Plan

1. Nav matrix: logged-out, logged-in landlord, admin — correct menus per state.
2. Every Explore Rentals link lands on correctly filtered results (verify query args reach `SearchHandler`: `featured_only`, `deals_only`, `rental_type`, `sort/newest`, `view=map`).
3. Shortcut page grid → section-filtered search results.
4. Contact form end-to-end delivery to `support_email`.
5. ID form fill/download/print on Chrome + Safari; both template-fill and built-in modes; missing-template fallback.
6. Mobile drawer parity (groups, icons, disabled items).
7. Disabled future items are non-navigable.

## 7. Out of Scope (Future)

- Site Testimonials functionality (menu row ships disabled).
- OVR Business Partners page (menu row ships disabled).
- Exact replication of LifestyleIDForm2025.pdf layout until the original PDF is supplied; schema mapping is designed for one-line corrections afterward.
