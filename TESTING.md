# OVR Core — Phase 1 End-to-End Test Plan

This document defines the manual test procedure for verifying Phase 1 of `ovr-core`. Each section covers one user-visible flow with explicit pass/fail criteria.

**Environment assumed:** WordPress 6.4+, PHP 8.2+, plugin activated, Composer autoload generated (`composer dump-autoload`), permalinks set to `/%postname%/`.

---

## 0. Activation & Setup

| # | Step | Expected | File of Record |
|---|---|---|---|
| 0.1 | Activate plugin | No PHP errors. Pages auto-created: Login, Register, Forgot Password, Pricing, Search, Featured, Onboarding, Dashboard. | `src/Activator.php`, `src/Core/Pages.php` |
| 0.2 | Visit **Settings → Permalinks** and click *Save* | Custom rewrites for `ovr_property` flushed. | `src/PostTypes/PropertyPostType.php` |
| 0.3 | Verify DB tables | `wp_ovr_seasonal_pricing`, `wp_ovr_availability`, `wp_ovr_inquiries`, `wp_ovr_payments`, `wp_ovr_promo_codes` exist. | `src/Core/Database.php` |
| 0.4 | Verify roles | "OVR Landlord" role appears in `wp_options.wp_user_roles`. | `src/Core/Roles.php` |

---

## 1. Registration → Login → Onboarding

| # | Step | Expected |
|---|---|---|
| 1.1 | Navigate to `/register/` | Two-column form renders, header nav fixed at top, footer at bottom |
| 1.2 | Submit form with First/Last name, valid email, phone, matching 8+ char password, terms checked, **landlord checkbox CHECKED** | Redirect to onboarding page; new user role is `ovr_landlord`; `account_status` user meta = `active`; `editing_enabled` = `1`; subscription = `base_subscriber` |
| 1.3 | Submit form without checking terms | Inline error: "You must agree to the Terms of Service" |
| 1.4 | Submit form with mismatched passwords | Inline error: "Passwords do not match" |
| 1.5 | Submit form with existing email | Inline error: "An account with this email already exists" |
| 1.6 | Logout, navigate to `/login/` | Login form renders with bg image, "Remember me" checkbox, Forgot link |
| 1.7 | Login with new credentials | Redirected to onboarding (first login) or dashboard (subsequent) |
| 1.8 | Click "Forgot Password?" → enter email → submit | Confirmation message shows; reset email arrives |

**Files exercised:** `src/Auth/{Login,Registration,PasswordReset}Handler.php`, `templates/auth/*.php`

---

## 2. Subscription Plans Page

| # | Step | Expected |
|---|---|---|
| 2.1 | Visit `/pricing/` while logged in | 5 pricing cards render: Base Subscriber (Free), Standard Homeowner 5 (popular badge), Property Manager 25, Property Manager 40, Long-Term Only |
| 2.2 | Comparison table below cards | Shows Max Listings, Analytics, Featured Placement, API Access, Support Level |
| 2.3 | Promo code field at top | Submitting empty shows error; valid code shows discount preview (admin must seed `wp_ovr_promo_codes`) |
| 2.4 | Click "Subscribe Now" on a paid card | Routes to checkout (Phase 2 — for now, surfaces "Phase 2" placeholder) |

**Files exercised:** `src/Subscription/{Plans,PricingDisplay,UserSubscription}.php`, `templates/pages/pricing-plans.php`, `src/Ajax/AjaxHandler.php::apply_promo`

---

## 3. Homepage

| # | Step | Expected |
|---|---|---|
| 3.1 | Visit homepage (front page) | Hero with bg image, search pill (Location / Check-in / Guests + search button) |
| 3.2 | Featured Properties section | Up to 4 cards from `_ovr_is_featured = 1` properties; falls back gracefully if none exist |
| 3.3 | Explore Villages section | Up to 6 village taxonomy terms with property counts |
| 3.4 | "How It Works" 3-step section | 3 numbered cards with icons |
| 3.5 | Bottom CTA banner | Two buttons: "Browse Properties" + "List Your Property" |
| 3.6 | Submit search pill with a keyword | Redirects to `/search/?keyword=…` |

**Files exercised:** `src/Frontend/Homepage.php`, `templates/pages/homepage.php`, `src/Property/PropertyCard.php`

---

## 4. Property Search Results

| # | Step | Expected |
|---|---|---|
| 4.1 | Visit `/search/` | Filter sidebar (left), results grid (right), view toggles (grid/list/map), sort dropdown, pagination |
| 4.2 | Toggle a Village checkbox | Form auto-submits (debounce-free for checkboxes), URL updates with `village[]=…`, results filter |
| 4.3 | Toggle a Property Type checkbox | Same auto-submit behavior |
| 4.4 | Type a price in `price_min` | Debounced auto-submit (~600 ms after stopping); URL updates with `price_min=…` |
| 4.5 | Click a Bedrooms chip | Auto-submits; only one radio selected at a time |
| 4.6 | Toggle Pets switch | Auto-submits; URL gets `pets=1` |
| 4.7 | Click "Clear all" link | Returns to clean `/search/` URL |
| 4.8 | Click view-toggle "List" | Cards render in horizontal list-card layout |
| 4.9 | Click view-toggle "Grid" | Returns to grid card layout |
| 4.10 | Change sort dropdown | URL updates `sort=…`, results re-order; `paged` resets to 1 |
| 4.11 | Apply filters that match nothing | No-results empty state appears with suggestion list + "Clear All Filters" + "Browse Featured" CTAs |
| 4.12 | Click pagination "2" | Loads page 2 with sliding-window pager (1 ⋯ 2 3 4 ⋯ N) |
| 4.13 | Active filter chip click | Removes that single filter, preserves others |

**Files exercised:** `src/Frontend/SearchResults.php`, `src/Search/SearchHandler.php`, `templates/search/{results,filters-sidebar,no-results}.php`, `assets/js/ovr-search.js`

---

## 5. Single Property Listing

Pre-req: create at least one `ovr_property` post with featured image, gallery, address, price, seasonal-pricing rows, and a few `ovr_availability` blocks (some past, some future, some `show_as_available=1`).

| # | Step | Expected |
|---|---|---|
| 5.1 | Visit any property single | Sticky header (title + price + "Check Availability" button), 5-tile gallery grid below |
| 5.2 | Click any gallery tile | Lightbox opens full-screen with backdrop, prev/next/close buttons, image counter |
| 5.3 | Press `→` and `←` keys | Lightbox navigates; arrows hide at first/last image |
| 5.4 | Press `Esc` | Lightbox closes; focus returns to the originating tile |
| 5.5 | Touch swipe left/right (mobile) | Lightbox advances 1 image per ≥50 px swipe |
| 5.6 | Scroll past hero — title block | Shows badges (Featured, Pet Friendly, rating chip), village name, guest/bed/bath summary |
| 5.7 | About this home section | Rich text content from `post_content` rendered safely via `wp_kses_post` |
| 5.8 | Amenities icon grid | First 6 amenities visible with Material icons; "Show all N" button if more |
| 5.9 | Seasonal Pricing table | Sortable rows showing season name, dates, nightly rate, min stay |
| 5.10 | Calendar — past dates | Greyed out, no hover, not clickable |
| 5.11 | Calendar — blocked dates | Red `error_container` background with strikethrough |
| 5.12 | Calendar — click an available day | Day gets primary background; same day click resets selection |
| 5.13 | Calendar — click a second later day | Range fills with `is-range-mid` styling |
| 5.14 | Confirm sidebar inquiry form | Inputs `ovr-checkin-{ID}` and `ovr-checkout-{ID}` populate from calendar selection |
| 5.15 | Right sidebar | Sticky inquiry card, optional map (only if `_ovr_latitude`/`_ovr_longitude` set) |
| 5.16 | Bottom — Similar listings | Up to 3 properties from same village/type, source property excluded |
| 5.17 | View page source | `<script type="application/ld+json">` schema.org `LodgingBusiness` block present with name, image, priceRange, geo, aggregateRating |

**Files exercised:** `src/Frontend/SingleProperty.php`, `src/Property/{PropertyMeta,PropertyQuery,SeasonalPricing}.php`, `templates/property/*.php`, `assets/js/ovr-property.js`

---

## 6. Inquiry Form (AJAX + Fallback)

### 6a. With JavaScript enabled

| # | Step | Expected |
|---|---|---|
| 6a.1 | Fill inquiry form (logged out): name, email, phone, message; pick dates from calendar; pick guest count | All fields fill cleanly |
| 6a.2 | Submit | Button shows "Sending…" with spinner; AJAX POST to `admin-ajax.php` with `action=ovr_submit_inquiry`, `nonce=ovr_public_nonce` |
| 6a.3 | Success response | Inline `.ovr-alert-success` appears below button; form resets; new row in `wp_ovr_inquiries` with `status='new'` |
| 6a.4 | Submit with empty required fields | Inline `.ovr-alert-error`: "Please fill in all required fields." |
| 6a.5 | Submit with checkout ≤ check-in | Error: "Checkout must be after check-in." |
| 6a.6 | Submit with `ovr_hp` honeypot filled | Silent success (no DB row inserted); spam-safe |

### 6b. With JavaScript disabled (admin-post.php fallback)

| # | Step | Expected |
|---|---|---|
| 6b.1 | Disable JS in browser | `data-ovr-inquiry-form` handler not attached |
| 6b.2 | Submit form | Browser POSTs to `/wp-admin/admin-post.php` with `action=ovr_submit_inquiry` + WordPress nonce `ovr_inquiry_nonce` |
| 6b.3 | Server processes via `admin_post_ovr_submit_inquiry` action | Validates nonce, calls shared `process_inquiry()`, inserts row, redirects back with `?ovr_inquiry=sent#ovr-inquiry` |
| 6b.4 | Page re-renders | Success alert visible above the form (templated by `inquiry-form.php` reading `$_GET['ovr_inquiry']`) |
| 6b.5 | Tamper with nonce in DevTools, submit | Redirect to `?ovr_inquiry=nonce_failed`; error alert shown |

**Files exercised:** `src/Ajax/AjaxHandler.php` (both `submit_inquiry` and `submit_inquiry_post`), `templates/property/inquiry-form.php`, `assets/js/ovr-property.js`

---

## 7. Featured Listings & Village Landing

| # | Step | Expected |
|---|---|---|
| 7.1 | Visit `/featured/` | Hero "Featured Properties", masonry/grid of properties where `_ovr_is_featured=1` |
| 7.2 | Visit a village term archive (e.g. `/village/oak-village/`) | Village hero, statistics cards, filtered property grid |
| 7.3 | Map embed | OpenStreetMap iframe renders if `_ovr_latitude`/`_ovr_longitude` populated |

**Files exercised:** `src/Frontend/{FeaturedListings,VillagePage}.php`, `templates/pages/{featured-listings,village-landing}.php`

---

## 8. Onboarding Welcome

| # | Step | Expected |
|---|---|---|
| 8.1 | First login as new landlord | Redirect to `/welcome/` |
| 8.2 | Page renders | Welcome hero, 4 quick-start bento cards: Complete Profile (with progress bar), List Your First Property (highlighted), Choose Subscription, Explore Platform |
| 8.3 | Subsequent logins | Redirect to dashboard, NOT onboarding |

**Files exercised:** `src/Frontend/Onboarding.php`, `src/Auth/AuthRedirects.php`, `templates/auth/onboarding.php`

---

## 9. Header / Footer / Navigation

| # | Step | Expected |
|---|---|---|
| 9.1 | Logged out | Header shows: brand, Explore/Villages/Pricing/Help links, search icon, "Sign In" link, "List Your Property" primary button |
| 9.2 | Logged in | Header shows: search + favorite icons, avatar (links to dashboard), "Sign Out" outline button |
| 9.3 | Active page link | Underlined and bold on the current section (e.g. Pricing on `/pricing/`) |
| 9.4 | Mobile (≤1024 px) | Mobile menu icon visible; tap opens drawer with same links + sign-in/out |
| 9.5 | Footer | Brand, 6 footer links (About, Careers, Terms, Privacy, Contact, Trust & Safety), copyright |

**Files exercised:** `templates/components/{header-nav,footer}.php`, `src/Frontend/Navigation.php`

---

## 10. Cross-cutting

| # | Step | Expected |
|---|---|---|
| 10.1 | View any plugin page source | `ovrData` localized object on window: `{ ajaxUrl, restUrl, nonce, siteUrl, pluginUrl, i18n }` |
| 10.2 | Check Network tab | `ovr-public.css` loads on every page; `ovr-auth.css` only on auth pages; `ovr-search.js` only on search/property pages; `ovr-property.js` only on single property |
| 10.3 | REST API | `GET /wp-json/ovr/v1/properties` returns published `ovr_property` posts (auth respected) |
| 10.4 | Run `wp ovr-core --help` (if WP-CLI present) | Lists available CLI commands (Phase 2 — currently none) |
| 10.5 | Theme override test | Copy `templates/property/card.php` to `wp-content/themes/<active>/ovr-core/property/card.php`, modify it, reload search results | Theme version is used (verified by `TemplateLoader::locate()`) |

---

## 11. Security Checks

| # | Test | Expected |
|---|---|---|
| 11.1 | Tamper with registration nonce | `wp_die("Security check failed.")` |
| 11.2 | Submit AJAX inquiry without nonce | 403 Forbidden |
| 11.3 | Direct GET on a template file | Loads only the security guard `if (!defined('ABSPATH')) exit;` — no output |
| 11.4 | XSS in property title | Output escaped via `esc_html()` everywhere — no script execution |
| 11.5 | SQL injection in `?village[]=` | `sanitize_key()` strips bad chars before `WP_Query` |

---

## 12. Performance Sanity

| # | Test | Expected |
|---|---|---|
| 12.1 | Lighthouse on homepage | Performance ≥ 80, no layout shifts |
| 12.2 | Repeated calendar loads | `wp_cache_get('ovr_avail_<id>', 'ovr')` hits after first request |
| 12.3 | Repeated price-range queries | `ovr_price_range` transient cached for 1 hour |
| 12.4 | Repeated seasonal-pricing reads | `ovr_pricing_<id>` cached for 1 hour |

---

## Code-Level Verification (Performed via Code Review)

The following was verified by reading source — runtime testing requires an active WP instance:

- ✅ **PSR-4 autoload chain** wires correctly (`composer.json` → `src/`)
- ✅ **All shortcodes** registered in `ShortcodeManager::register_shortcodes()`
- ✅ **Single property template intercept** in `TemplateLoader::load_property_template()` routes to `templates/property/single.php`
- ✅ **AJAX endpoints** registered for both `wp_ajax_*` and `wp_ajax_nopriv_*` where appropriate
- ✅ **Nonces** present on every form: `ovr_register_nonce`, `ovr_login_nonce`, `ovr_reset_nonce`, `ovr_inquiry_nonce`, plus `ovr_public_nonce` for AJAX
- ✅ **Capability checks**: `current_user_can('edit_ovr_properties')` gates property meta REST writes
- ✅ **Sanitization**: every `$_POST`/`$_GET` reads through `sanitize_*()` or `absint()`/`floatval()` before use
- ✅ **Escaping**: every echo of user/DB data uses `esc_html`, `esc_attr`, `esc_url`, or `wp_kses_post`
- ✅ **PHP syntax** clean across all 17 new/modified files (verified via `php -l`)
- ✅ **JS syntax** clean for `ovr-property.js` and `ovr-search.js` (verified via `node --check`)

---

**Pass criterion for Phase 1 sign-off:** all sections 0–10 pass without console errors or PHP notices, and all of section 11 fails appropriately (security boundaries hold).
