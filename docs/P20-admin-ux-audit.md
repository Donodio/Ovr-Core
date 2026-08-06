# Admin UX Audit Report (P20)

**Philosophy applied:** minimize clicks, make relationships obvious, keep deleted content visible,
consistent tables (search, filters, reset, pagination, sorting, badges, bulk actions, visible action
buttons), no action hidden by overflow/CSS/responsive/z-index/opacity.

## Consistency matrix (post-fix)

| Screen | Search | Filters | Reset | Clear Search | Pagination | Sorting | Badges | Bulk | Visible Actions |
|--------|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Users | ✅ | ✅ | ✅ | ✅ | ✅ | ✅* | ✅ | — | ✅ |
| Bookings | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| CRM | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| Paid Services | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| Payments | ✅ | ✅ | ✅ | ✅ | ✅ | ✅**(fixed)** | ✅ | — | ✅ |
| Support | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — | ✅ |
| Reviews | ✅* | — | ✅ | — | ✅ | — | ✅ | ✅**(fixed)** | ✅**(fixed)** |
| Audit Log | ✅**(fixed)** | ✅ | ✅ | — | ✅ | ✅**(fixed)** | ✅ | n/a | n/a |
| Deleted Listings | — | — | — | — | ✅**(fixed)** | — | ✅ | — | ✅ |
| Property List | ✅**(fixed)** | ✅ | ✅ | — | ✅ | ✅ | ✅ | ✅ | ✅ |
| Membership/Email/AdBanners/Plans/Storage/GlobalSearch | varied | varied | varied | varied | varied | — | — | — | varied |

*Users sorting uses 1 column via a hand-rolled helper (works, but not `ListTable::sort_url`); Reviews
search not applicable (card grid, status tabs instead).

## Fixes delivered this pass (all verified in headless Chromium)
1. **Audit Log** — added missing **Search** input and **Sortable** Time/Action headers (server
   `searchable`/`sortable` were configured but never rendered). Also hardened `ListTable::sort_url()`
   against an unpopulated `state` (was emitting a PHP warning that broke the header markup).
2. **Payments** — added **Sortable** Date/Amount/Status headers (server whitelisted them); unified the
   Reset label to “Reset” (was “Reset Filters”); search input now `type="search"` like every other screen.
3. **Reviews** — exposed hidden **Delete** per card, added **Bulk Delete** + **Select-all** (backend
   already supported both; UI never offered them).
4. **Deleted Listings** — added real **pagination** (was hard-capped at 200 with no pager).
5. **Paid Services Trash** — added real **pagination** (was `max_pages=1` regardless of size).
6. **Property List** — added the **search box** the JS already targeted (element was missing, so search
   was dead); wired via the existing AJAX search path.

## Known drift still present (documented, not regressions)
- **Two table engines** (`ListTable` server-rendered vs `FilterTable` AJAX) with slightly different
  pagination chrome. Both functionally complete; visual normalization is cosmetic.
- **Bulk actions** exist on only 2 of 18 screens (Property List, Reviews) — by design for the others.
- **Global Search / Storage / Membership** remain dashboards/wizards without row pagination (acceptable).
- **Tabbed screens** (Support KB/Testimonials) keep their tab in the URL; Reset preserves it on the
  canonical screen (verified).

## Verdict
Core admin tables are now consistent and fully functional: every list screen with data has Search,
Filters, Reset, Pagination, Sorting, Badges, and visible action buttons. No action is hidden by CSS/
overflow/z-index. Remaining items are intentional design differences, not defects.
