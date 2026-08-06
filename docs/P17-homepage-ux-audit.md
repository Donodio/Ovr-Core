# Homepage UX Audit Report (P17)

**Philosophy applied:** reduce clutter, reduce cognitive load, reduce scrolling, present only
information that helps a visitor find a property, clear visual hierarchy, reduce click friction.

## Method
- The public homepage is an **Elementor-built page (page ID 110)** plus plugin Elementor
  widgets. Plugin-owned, code-addressable surfaces (slider, village sections, property cards,
  search) were audited for noise and friction. The literal hero/“Who We Are”/“Explore The
  Villages” carousel *content* lives in Elementor/page-builder data and the theme footer — it is
  **outside the plugin repo** and was curated editorially, not via code. Where the plugin owns
  the mechanism, fixes were made; where only content curation applies, it is classified as an
  editorial/environmental step (not a software defect).

## Findings

| Area | Class | Evidence |
|------|-------|----------|
| Duplicate homepage sections (Homepage Slider vs Featured Rentals) | ⚠ WARNING | Both blocks read `PropertyQuery::get_slider()` (priority = sponsored/upgraded, then newest). The Featured Rentals block uses `ovr_property_cards source=slider`. This can show the same properties twice. Live homepage Elementor setting uses `source=slider` for both. Recommend one block use `source=featured` (`get_featured()` = active Featured boost only). **Plugin mechanism supports it; change is a 1-field Elementor setting.** |
| Slider / Featured populated dynamically | ✅ PASS | `get_slider()` returns live data; never empty (fills from newest). No hardcoded cards. |
| Village Sections hardcoded cards | ⚠ WARNING | Live homepage uses a static Elementor “Explore The Villages” carousel with stale `ngrok`/mock image URLs. The plugin provides a **dynamic** `ovr_village_sections` widget (reads curated option + live counts + term links). The dynamic widget is fully functional (P18) but is **not currently placed** on the homepage. Swapping is an Elementor content edit. |
| Dead links (hero CTA `href="#"`) | ⚠ WARNING | Hero CTA and theme footer links resolve to `#` in the rendered page. These live in Elementor/theme content, not plugin code — fix by editing the page/footer in the builder. |
| Search placement above the fold | ✅ PASS | `ovr_homepage_slider` widget renders Hero + Search at the top of the homepage (verified: search autocomplete works, see Search report). |
| Visual hierarchy (Hero → Search → Featured → Slider → Villages → Latest → Footer) | ✅ PASS (plugin widgets) | The plugin widgets render in this order; the live composition is an Elementor layout decision. |
| Click friction (reach listings in few clicks) | ✅ PASS | Search suggestion click → immediate results; village card → archive; property card → detail. No forced interstitial. |

## Verdict
Plugin-owned homepage *mechanisms* are clean, dynamic, and low-friction. The remaining
visual-noise items (duplicate slider/featured, stale carousel images, dead hero/footer links)
are **Elementor/theme content curation**, correctly scoped as an editorial task before launch,
not code defects. No regressions introduced.
