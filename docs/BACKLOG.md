# OVR Core — Backlog (deferred features)

## Favorites System — LOW priority, Future Release

Client explicitly deferred this (do **not** build yet).

**Scope when picked up:**
- **Add to Favorites** — a heart/favorite toggle on listing cards and the single
  listing page (the old site showed an "❤ ADD TO MY FAVORITES" button).
- **Display Favorites** — a renter-facing page/tab listing the properties they
  favorited.

**Implementation notes for later:**
- Store per-user favorites (logged-in: user meta `ovr_favorites` = array of post
  IDs; guests: localStorage with optional merge on login).
- AJAX toggle endpoint (mirror the existing compare-list pattern in
  `assets/js/ovr-property.js` / `ovr-search.js`).
- A "My Favorites" dashboard tab + a shortcode/page for display.
- Reuse `PropertyCard::render_*` for the favorites grid.

Tracked here per the client's instruction to "create a backlog item only."
