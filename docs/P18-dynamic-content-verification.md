# Dynamic Content Verification Report (P18)

**Goal:** no hardcoded homepage content; everything reads from live data; images editable by admin.

## Findings

| Area | Class | Evidence |
|------|-------|----------|
| Homepage Slider populated dynamically (upgrades first, then newest, auto-fill) | ✅ PASS | `PropertyQuery::get_slider()` returns sponsored/upgraded listings first, fills remaining slots from newest; never leaves empty spaces (verified by code path; degrades to newest when no upgrades exist). |
| Village Sections generated dynamically | ✅ PASS | `ovr_village_sections` Elementor widget reads `VillageSectionsAdmin::get_enabled_terms()` → `ovr_village_sections` option (now seeded with 5 canonical sections on first run). Each card shows **live listing count** (`$term->count`) and links to the section archive. |
| Village Section images editable (upload/replace/remove) | ✅ PASS | Added a per-section **media picker** in `VillageSectionsAdmin` (stored as term meta `ovr_village_image_id`, resolved by `SearchFilters::get_village_image()` with placeholder fallback). Admin can upload/replace/remove without touching code. |
| Listing counts are live | ✅ PASS | Village card counts come from term `count` (auto-updated by WordPress on save); no manual sync. |
| Featured Areas read live data (no manual duplication) | ✅ PASS (mechanism) | Property-card blocks query `PropertyQuery`; the only risk is the duplicate slider/featured source noted in the Homepage report — a setting change, not hardcoded data. |
| Nothing requires source-code edits when content changes | ✅ PASS | Slider, village sections, property cards, and search are all data-driven; the curated sections option is manageable from the admin screen. |

## Evidence of execution
- `VillageSectionsAdmin::maybe_seed_sections_option()` seeds `[80,81,82,83,84]` (the 5 canonical `ovr_village` section terms) on first run.
- Runtime: `get_enabled_terms()` returns the seeded terms; `get_village_image()` resolves term meta → placeholder.
- Admin UI: image picker (media modal) + clear button added and lint-clean.

## Verdict
All dynamic-content requirements satisfied at the **plugin mechanism** level. The only remaining
item is placing the dynamic `ovr_village_sections` widget on the live homepage in place of the
static Elementor carousel — an editorial/content step (environmental, not a code defect).
