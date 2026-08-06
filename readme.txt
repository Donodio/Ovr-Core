=== OVR Core — Our Villages Rentals ===
Contributors: ourvillagesrentals
Tags: rental, property, vacation, real estate, listing, saas
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium vacation & long-term rental listing platform with subscription plans, advanced search, and landlord dashboards.

== Description ==

OVR Core is a complete rental listing platform built as a robust WordPress plugin. It provides everything needed to run a professional property rental marketplace.

**Features:**

* Custom Property Post Type with rich meta fields
* Advanced search with multi-filter sidebar (village, type, bedrooms, price, amenities)
* Subscription plan system (Free, Standard, Property Manager tiers)
* Custom authentication (Login, Register, Forgot Password)
* Seasonal pricing and availability calendar
* Inquiry system for guest-to-landlord communication
* REST API for headless/mobile access
* Theme-overridable template system
* Elementor-ready architecture
* SaaS/WaaS-ready for multi-tenant deployment

== Installation ==

1. Upload the `ovr-core` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' screen.
3. Required pages are auto-created on activation (Login, Register, Pricing, etc.).
4. Navigate to **OVR Properties** in the admin menu to start adding listings.

== Changelog ==

= 1.2.0 =
Milestone 3 — Search, discovery & details (Section 5–10).
* Bump paid upgrade: tiered search ordering (Featured, then recently Bumped, then newer), 24h/7d SKUs, auto-expiry + renewal.
* Village Sections: new OVR Village Sections Elementor widget + admin enable/order screen.
* Intelligent village search: async autocomplete with typo tolerance across village names + taxonomy.
* Consolidated listing amenities: Features / Views / Amenities grouped into a single "What this place offers" section.
* Compact details page: What's Nearby, Policies & Payment Info folded into the Description tab.
* Admin Properties screen: restored per-row actions (View/Edit/Delete/Duplicate) + one-click Duplicate with audit log.
* Admin Users screen: per-user "View listings" action, de-duplicated email column, Reset Filters button.

= 1.1.2 =
* New OVR Property Carousel Elementor widget (sponsored first, curated + recent fill, 4/3/1 responsive, drag/dots/autoplay).
* New Homepage Carousel admin page (add/remove/reorder curated listings).
* PropertyQuery::get_carousel_ids() composes the carousel order.

= 1.1.0 =
Milestone 3 — Admin tooling, optimization & handover.
* Admin Control Center: YTD/system-health stat cards, configurable dashboard widgets, global search.
* Audit Log system (actor/old/new/user-agent, retention cron, admin screen) + universal .xlsx export.
* Email Management System (editable templates, preview, test-send, recipient modes).
* Settings expansion: General, Listings caps, Media, and Security tabs — all wired (image quality, login lockout, admin 2FA, watermark, favicon).
* Reviews polish: approval timestamps + analytics.
* Paid Services: renewable / auto-renew + purchase reporting.
* Homepage Slideshow CMS feeding the Elementor hero (DB-backed slides).
* Manual ordering for the homepage featured rail.
* Ad Banners: CRUD + impression/click analytics + [ovr_ad_banner] shortcode.
* Map pins by property type / featured / availability + engagement analytics.
* SEO: meta/OG/Twitter/canonical, JSON-LD (Organization, WebSite, Breadcrumb, LodgingBusiness, Review, Image, Video), per-listing SEO fields.
* Performance: WebP generation/serving, responsive image size, versioned map-query caching.
* Cloud Storage dashboard with offload coverage + recovery tools (offload pending, restore missing).
* CSV migration importer with column-mapping UI, dry-run, and image side-loading.
* Front-end accessibility layer (decorative-icon hiding, icon-button labelling, focus ring, aria-pressed).
* Documentation: admin guide, landlord guide, QA checklist, and technical handover.

= 1.0.0 =
* Initial release — Phase 1 (Milestone 1).
* Plugin skeleton with PSR-4 autoloading.
* Property CPT, taxonomies (Village, Property Type, Amenity, Rental Type).
* Custom authentication flows (Login, Register, Forgot Password).
* Subscription plan system with 5 tiers.
* Search & filter system.
* Homepage, Featured Listings, Village Landing pages.
* Onboarding welcome screen.
* REST API endpoints.
* Complete design system CSS.
