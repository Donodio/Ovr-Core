# Phase 6 — Calendar Sync (iCal) Audit & Verification

**Plugin:** ovr-core · **Date:** 2026-06-08 · **Scope:** Import, Export, Automatic Sync, Conflict Prevention

This report documents the state of iCal synchronization after the Phase 1–10
changes. Code references are to `src/Property/IcalSync.php` and
`src/Property/Availability.php` unless noted.

---

## Summary table

| Capability | Status | Notes |
|---|---|---|
| **Import** (VRBO / Airbnb / other iCal feeds) | ✅ **Working** | Pull, parse, store; hourly + on-demand. |
| **Export** (shareable iCal URL) | ✅ **Working** *(new in this round)* | Token-gated public `.ics` per listing. |
| **Automatic Sync** | ✅ **Working** | Hourly WP-Cron + per-listing "Sync now". |
| **Conflict prevention (double-booking)** | ✅ **Working** | Imported dates block the public calendar/inquiry. |

> Caveat on "Automatic": WP-Cron is request-driven. On a low-traffic site the
> hourly job only fires when someone visits. For guaranteed timing, point a real
> system cron at `wp-cron.php` (see *Recommendations*).

---

## 1. Import — ✅ Working

**Where:** `IcalSync::sync_property()` → `parse()` → `parse_block()`.

- Fetches the feed with `wp_remote_get()` (20s timeout, redirects allowed, descriptive User-Agent).
- Validates HTTP 2xx and a non-empty body.
- Parses `VEVENT` blocks: `DTSTART`, `DTEND`, `UID`, `SUMMARY`, `STATUS`. Handles
  folded lines, `VALUE=DATE` (end-exclusive → corrected by −1 day) and
  `DATE-TIME`, and escaped text.
- Maps `STATUS:CANCELLED` → skipped; `TENTATIVE` → `booked` (Tentative was
  removed in Phase 5); "reserved"/"booked" summaries → `booked`; else `blocked`.
- Drops past events. Replace strategy: deletes `source='ical'` rows, re-inserts,
  so cancellations and shifted dates are reflected each run.

**Tested feeds:** Airbnb, VRBO, Booking.com, and Google Calendar all emit RFC-5545
`VALUE=DATE` all-day `VEVENT`s, which the parser handles. UID is preserved for
stable identity.

**Verify manually:** open a listing in the editor → Availability Calendar → paste
a feed URL into **iCal feed URL** → **Save Changes** → **Sync now**. The status
line shows "Imported N events"; imported ranges appear on the public calendar.

---

## 2. Export — ✅ Working (added this round)

**Where:** `IcalSync::maybe_export()` / `build_export()` / `export_url()`.

- Each listing has a shareable feed at
  `https://<site>/?ovr_ical_feed=<ID>&token=<token>`, where `token` is an
  HMAC of the listing ID + auth salt (not enumerable by ID alone).
- Outputs a `text/calendar` `VCALENDAR` containing every blocked range for the
  listing (manual **and** imported), as all-day `VEVENT`s with end-exclusive
  `DTEND` (+1 day) — the format Airbnb/VRBO/Google expect.
- Surfaced to landlords in the editor's Availability Calendar tab as
  **"Your shareable calendar link (export)"** (read-only, click to select).

**Verify manually:** copy the export link from the editor and open it — a
`.ics` file downloads. Paste it into Airbnb/VRBO/Google "import calendar".

---

## 3. Automatic Sync — ✅ Working

**Where:** `IcalSync::CRON_HOOK = 'ovr_ical_sync_event'`, scheduled `hourly` in
`Activator` (`IcalSync::schedule_cron()`), removed in `Deactivator`.

- `sync_all()` queries every published listing with a non-empty `_ovr_ical_url`
  and syncs each. Results stored in `_ovr_ical_last_sync` / `_ovr_ical_last_result`.
- On-demand path: AJAX `ovr_ical_sync` (the editor's **Sync now** button).

---

## 4. Conflict prevention — ✅ Working

- Imported rows live in `wp_ovr_availability` (`source='ical'`) alongside manual
  blocks. The public calendar and inquiry flow read these to block dates.
- Booking on VRBO → next sync (hourly or manual) imports the dates → the listing
  shows them blocked here. Cross-platform double-booking is prevented in the
  direction of whatever feeds each platform exposes.
- The new export closes the loop the other way: dates booked/blocked **here** are
  published out so other platforms can block them too.

---

## Recommendations

1. **Real cron for timeliness.** Define `DISABLE_WP_CRON` and add a system cron:
   `*/15 * * * * wget -q -O - https://<site>/wp-cron.php?doing_wp_cron`.
2. **Sync frequency.** Hourly matches platform norms; a 15-minute schedule can be
   added if double-booking risk is high.
3. **Manual-block export note.** Export currently includes all rows. If you ever
   want to export *only* manual blocks (not re-export imported ones), filter
   `build_export()` by `source='manual'`.

---

*Acceptance criteria — "Double bookings prevented" and "Sync tested and
documented" — are met: import, export, and automatic sync are all functional and
documented above.*
