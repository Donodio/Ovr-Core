# Sponsored Property Carousel — Design Spec

**Date:** 2026-08-06
**Status:** Approved (design pending)
**Plugin module(s):** Elementor widgets · Plugin admin · Property query · Frontend JS

## Summary

Add a new drag-and-drop **Elementor widget — "OVR Property Carousel"** — that renders a
sliding carousel of property cards for the homepage, inspired by rent-by-village style
sponsored-property carousels. The carousel shows **4 cards per view** on desktop, 3 on
tablet, and **1 on mobile**; it supports both **auto** (autoplay, looping) and **manual**
(drag-to-swipe, arrow buttons, dots) scrolling.

It composes three tiers of listings — **sponsored first**, then **owner-curated**, then
**most-recent fill** — de-duplicated and truncated to the requested count.

An accompanying **admin page** lets the site owner **click a listing to set it to the
homepage carousel** (the curated tier) and reorder those picks.

## Defining terms

- **Sponsored listing** — a property with an **active Featured boost**. The "Featured"
  upgrade is one of this plugin's paid boosts; its flag/expiry metas are
  `_ovr_is_featured` / `_ovr_featured_expires` (see `UpgradeActivator::MAP`). Active =
  flag is `1` **and** the expiry is empty or in the future, matching the existing
  `Property::PropertyQuery::active_boost_clause()` / `UpgradeActivator::is_active()`.
- **Curated pick** — a property the site owner explicitly added to the homepage
  carousel via the admin page, persisted as an ordered set of post IDs.
- **Recent fill** — most-recently-published properties used only to reach the requested
  count when sponsored + curated don't fill it.

## Goals

1. A homepage carousel widget a site owner can drop onto any page/section.
2. Sponsored listings always lead; newest sponsored first.
3. The owner curates additional picks without writing code.
4. Reuse the plugin's existing carousel engine and widget conventions — no new slider
   dependency, no duplicated JS.
5. 100 % responsive; hybrid + touch friendly; accessible.

## Non-goals

- Introduce no new paid product; the sponsored tier simply reads the existing Featured
  flag.
- No per-landlord / multi-tenant variant in this iteration.
- No lightbox or full-screen media handling.

## Assumptions

- **"Sponsored" == the existing Featured boost** (confirmed by the requester).
- Autoplay **on** by default (~5 s), pauses on hover/focus/drag; loop on; arrows + dots on.
- Curated picks are reordered with **up/down** controls (not typed numbers) — confirmed.
- All tiers read `post_type = ovr_property`, `post_status = publish`.

## Section: Display order

The final ordered ID list is:

```
ordered =
    sponsored (active Featured, ordered date DESC)
    + curated picks (owner's persisted order)
    + recent fill (newest published, date DESC)
dedupe(ordered)          // first occurrence wins → sponsored keeps its leading spot
take(ordered, count)     // truncate to the widget's count (default 4)
```

A property that is simultaneously sponsored and curated appears exactly once, at its
quality-sponsored (leading) position.

## Components

### 1. Elementor widget — `src/Elementor/Widgets/PropertyCarouselWidget.php`

Extends `Elementor\Widget_Base`, namespace `OVR\Elementor\Widgets`.

- `get_name()` → `ovr_property_carousel`
- `get_title()` → `OVR Property Carousel`
- `get_icon()` → `eicon-carousel`
- `get_categories()` → `[ 'ovr-widgets' ]`
- `get_keywords()` → property, carousel, slider, sponsored, featured
- `get_script_depends()` → `[ 'ovr-testimonials' ]`

#### Controls

- **Query (CONTENT)**
  - `count` — NUMBER, default `4`, min `1`, max `120` (cards per view).
  - `source` — SELECT, default `sponsored`: `sponsored` = "Sponsored first",
    `all` = "All current listings, sponsored still lead".
- **Section Header (CONTENT):**
  - `heading` TEXT (e.g. "Suggested &Featured Properties"), `subheading` TEXTAREA,
    `show_section_header` SWITCHER default `yes`.
- **Carousel (CONTENT):**
  - `per_view` responsive SELECT, defaults desktop `4` / tablet `3` / mobile `1`.
  - `gap` responsive SLIDER px, default 24.
  - `autoplay` SWITCHER default `yes`; `autoplay_speed` SLIDER 1500–12000 default 5000;
    `loop` SWITCHER default `yes`; `show_arrows` SWITCHER default `yes`;
    `show_dots` SWITCHER default `yes`.
- **Style — Section Header:** heading/sub heading colors + `Group_Control_Typography`.
- **Style — Card:** background, border-radius, padding (DIMENSIONS), border (group),
  box-shadow (group), text-align.
- **Style — Image:** square aspect locked (`aspect-ratio: 1/1`; object-fit cover),
  border-radius SLIDER (default 12).
- **Style — Typography/colors:** `ID`, `Name`, `Type`, `Price`, `Size` — a COLOR +
  `Group_Control_Typography` each.
- **Style — Arrows & Dots:** arrow icon color, arrow bg, dot color, active dot color.

#### `render()` (final/intended wiring)

1. Read + validate settings.
2. `$ids = PropertyQuery::get_carousel_ids($count, $source === 'sponsored')`.
3. Empty → if Elementor edit mode, output a dashed placeholder and return; else return.
4. Resolve currency symbol from global settings (`currency_symbol ?? '$'`).
5. `print_structural_css()` once per request; open the public wrapper `.ovr-pc` with
   `data-ovr-carousel`, `data-autoplay`, `data-interval`, `data-loop`,
   `data-ovr-prefix="ovr-pc"`, and a `--ovr-pc-per` CSS var from the per_view control.
6. Loop `$ids`: load card data via `PropertyCard::get_card_data($id)` and render a
   `.ovr-pc-card` linking to the permalink, with:
   - square `.ovr-pc-image` (`<img>` alt = title);
   - `.ovr-pc-id` — `#` + formatted post ID;
   - `.ovr-pc-name` — title;
   - `.ovr-pc-type` — first `ovr_property_type` term name;
   - `.ovr-pc-price` — `symbol . number_format(base_price,0) . ' / night'`, hidden when
     `base_price <= 0`;
   - `.ovr-pc-size` — `number_format(sqft) . ' sq ft'`, hidden when `sqft <= 0`.
7. Arrows (`-.prev` / `-.next`) and `.ovr-pc-dots` according to the switches.

Structural CSS only handles layout/motion; all visual colors/spacing come from the
Elementor selectors, mirroring `TestimonialsCarouselWidget::print_structural_css()` but
under the `ovr-pc` prefix (and its own style id to print once).

#### CSS prefix note

- Do not collide with the testimonials engine (`ovr-tc-*`) — use `ovr-pc-*`.
  Each widget prints its own structural block with a static `$printed` guard.

### 2. Edit `src/Elementor/ElementorIntegration.php`

- `require_once` the new widget file.
- `$widgets_manager->register( new Widgets\PropertyCarouselWidget() );`

### 3. Generalize the carousel engine — `assets/js/ovr-testimonials.js`

The file already contains a complete, dependency-free carousel. Make its class prefix
parametric while keeping the testimonials widget byte-for-byte unchanged at runtime:

- Read `data-ovr-prefix` on the root; default `ovr-tc`.
- Selectors become `${prefix}-track`, `${prefix}-card`, `${prefix}-prev`,
  `${prefix}-next`, `${prefix}-dots`, `${prefix}-dot`.
- Read per-view from the CSS var `--${prefix}-per`.
- Ready flag becomes `data-${prefix}-ready` instead of `data-tc-ready`.
- Keep the global `window.ovrInitTestimonials` alias and the existing
  `frontend/element_ready/ovr_testimonials_carousel.default` hook; add an analogous
  `frontend/element_ready/ovr_property_carousel.default` hook that calls the parametrized
  init on the new widget's root.
- Autoplay pause/hover, keyboard arrows, dots — logic unchanged (already generic to
  track/card markup, but keep drag-swipe generic too).

### 4. Query helper — `src/Property/PropertyQuery.php`

Add a single, testable public static in the following signature:

`PropertyQuery::get_carousel_ids( int $count = 4, bool $sponsored_only = true ): int[]`

Internal:

- Tier 1 (sponsored): WP_Query `ovr_property`/`publish`, date DESC, meta_query with
  `active_boost_clause('_ovr_is_featured','_ovr_featured_expires')`; `fields=ids`,
  `no_found_rows`, capped `posts_per_page` (e.g. max( count, 200 )).
- Tier 2 (curated): read the ordered curated IDs list from the settings
  option `homepage_carousel_ids`; keep only IDs that resolve to published posts,
  preserving list order.
- Tier 3 (recent fill): newest published `ovr_property` IDs not already chosen, to
  reach `count`.
- Merge: `sponsored + curated + recent`, de-duplicate (first wins), truncate to `count`.

Notes:

- Returns a plain ID list (no `the_post()`); the widget renders each card directly.
- Uses existing `active_boost_clause()` so expiry handling stays single-sourced.

### 5. Admin page — `src/Admin/PropertyCarouselAdmin.php`

- Adds submenu page **OVR Properties ▸ Homepage Carousel**, parent
  `edit.php?post_type=ovr_property`, rendered in a pattern matching other OVR admin
  pages (see `PropertyListScreen` / `FeaturedCities`).
- Capability: use the same capability guard the sibling property admin pages use
  (e.g. `manage_ovr`-style custom cap), and check `current_user_can` in render + handlers.
- UI: a table of published properties (thumbnail, title, ID) each with an
  **"Included" toggle** and **up/down** order buttons; a **Save** action writes the
  ordered list of included property IDs to the settings option.
- Handler: `handle_save()` sanitized list → `update_option( 'ov_settings', … )` with
  `homepage_carousel_ids` = ordered comma list of absints; redirect back with an admin
  notice (or AJAX for granular toggle).
- Ordering: persisted order = user's up/down arrangement; not typed numbers.
- Empty state notice if no published properties (else the screen still renders with a
  notice).

### 6. Persistence

Store in the plugin's central settings option `ov_settings` (the array `Settings` uses):

```
'homepage_carousel_ids' => 'int,int,int,…'   // ordered curated picks; empty = none
```

Sanitized in `Admin\Settings` (or the new page's own handler) to absints of existing
`ovr_property` posts. Default empty.

## Wiring view

```
owner → admin "Homepage Carousel" page sets + reorder
         │  write `homepage_carousel_ids`
         ▼  Elementor widget controls
PropertyQuery::get_carousel_ids()  ← render()
         │  sponsor→curated→recent→ dedupe→truncate
         ▼  ids
         └ → PropertyCard::get_card_data(id) → .ovr-pc-* markup → engine (auto/drag) → front-end
```

## Error & empty handling

- No matching property rows → stop; Elementor: dashed placeholder; public: nothing.
- A curated ID no longer exists → null in merge.
- Expired Featured → dropped in tier 1 via the existing boost clause.

## QA checklist

- [ ] `get_carousel_ids()` composition order (sponsored → curated → recent), duplicate
  handling, truncation.
- [ ] Elementor: drop widget, verify 4/3/1 responsive, drag + arrows + dots and autoplay.
- [ ] Price / size / ID shown correctly; price/size hidden when unset.
- [ ] Admin page add/remove/reorder persists and reflects in the carousel.
- [ ] Keyboard arrows + dots focus; `role="region"` carousel ARIA.
- [ ] `readme.txt` changelog entry.

## Future / open

- Whether a curated pick that is also an active sponsor stays at the sponsored position
  (current behavior — already specified).
- Expose "cards per view" as a compact control variant across OVR widgets for later parity.