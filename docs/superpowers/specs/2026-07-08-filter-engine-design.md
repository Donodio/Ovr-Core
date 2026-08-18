# Filter Engine — Enterprise Reusable Filtering System

**Date:** 2026-07-08
**Status:** Approved Design

## Overview

Replace all ad-hoc admin filtering with a reusable, enterprise-grade filtering engine. Every admin screen gets spreadsheet-style column filters positioned directly above their columns, with AJAX-powered instant filtering, server-side query execution, and full state management via the History API.

## Architecture

```
┌─────────────────────────────────────────────────────┐
│  Admin Screen Classes                               │
│  (PropertyListScreen, UsersAdmin, PaymentsAdmin…)    │
│  Define column config → instantiate FilterTable     │
├─────────────────────────────────────────────────────┤
│  FilterTable (orchestrator)                         │
│  Renders header/filters/table/footer via templates   │
│  Registers AJAX endpoint, processes requests         │
├─────────────────────────────────────────────────────┤
│  FilterEngine (query layer)                         │
│  Column config → renders filter HTML                │
│  Filter params → modifies WP_Query / WP_User_Query  │
│  / custom SQL / ListTable config                    │
├─────────────────────────────────────────────────────┤
│  Filter Types (pluggable)                           │
│  Text, Dropdown, Date, Numeric, Boolean             │
│  Each knows: render() + apply_to_query()            │
└─────────────────────────────────────────────────────┘
```

## Data Flow

1. **Page load** — FilterTable reads $_GET params, uses FilterEngine to build query modifications, executes query, renders full page with column filters above each column.
2. **Filter change** — JS captures event, debounces (350ms), collects all current filter values, POSTs to wp_ajax_ovr_filter_table.
3. **Server** — FilterTable::ajax_handler() receives params, same query path, returns JSON with `{ html, pagination, total, state }`. The client uses `html` to replace table body/footer and `total` to update the results count. `state` is reserved for future enhancements (e.g., saved filter presets).
4. **Client** — replaces table body, pagination footer, updates URL via History API.

## Column Configuration Schema

Each admin screen defines a config array:

```php
$config = [
    'columns' => [
        'id' => [
            'label'    => __('ID', 'ovr-core'),
            'sortable' => true,
            'filter'   => ['type' => 'text', 'placeholder' => 'ID #'],
        ],
        'display_status' => [
            'label'    => __('Display', 'ovr-core'),
            'filter'   => [
                'type'    => 'dropdown',
                'options' => [
                    'approved'      => __('Visible', 'ovr-core'),
                    'hidden'        => __('Hidden', 'ovr-core'),
                    'suspended'     => __('Suspended', 'ovr-core'),
                    'pending_review'=> __('Pending Review', 'ovr-core'),
                ],
            ],
        ],
        'title' => [
            'label'    => __('Name', 'ovr-core'),
            'sortable' => true,
            'filter'   => ['type' => 'text', 'placeholder' => 'Search name…'],
        ],
        'price' => [
            'label'    => __('Price', 'ovr-core'),
            'sortable' => true,
            'filter'   => ['type' => 'numeric'],
        ],
        'type' => [
            'label'    => __('Type', 'ovr-core'),
            'filter'   => [
                'type'    => 'dropdown',
                'source'  => 'taxonomy:ovr_property_type',
            ],
        ],
        'village' => [
            'label'    => __('Village', 'ovr-core'),
            'sortable' => true,
            'filter'   => ['type' => 'dropdown', 'source' => 'taxonomy:ovr_village'],
        ],
        'email' => [
            'label'    => __('Owner Email', 'ovr-core'),
            'sortable' => true,
            'filter'   => ['type' => 'text', 'placeholder' => 'Search email…'],
        ],
        'date' => [
            'label'    => __('Updated', 'ovr-core'),
            'sortable' => true,
            'filter'   => ['type' => 'date'],
        ],
        'featured' => [
            'label'  => __('Featured', 'ovr-core'),
            'filter' => ['type' => 'boolean'],
        ],
    ],
    'global_filters' => [
        'subscription' => [
            'type'    => 'dropdown',
            'label'   => __('Subscription Plan', 'ovr-core'),
            'source'  => 'class:' . Plans::class . '::get_plans',
            'multiple'=> true,
        ],
        'paid_service' => [
            'type'    => 'dropdown',
            'label'   => __('Paid Service', 'ovr-core'),
            'source'  => 'table:ovr_paid_services|id|name',
        ],
        'date_range' => ['type' => 'date_range', 'label' => __('Date Range', 'ovr-core')],
    ],
    'query' => [
        'source'     => 'wp_query',     // wp_query | user_query | custom_table | list_table
        'post_type'  => 'ovr_property',
    ],
    'per_page' => 25,
];
```

### Filter Type → Query Mapping

| Filter Type | WP_Query | WP_User_Query | Custom SQL |
|---|---|---|---|
| text | meta_query LIKE | search across columns | WHERE col LIKE %s |
| dropdown | meta_query = / tax_query | meta_query = / role = | WHERE col = %s |
| date | date_query | meta_query date compare | WHERE col >= %s AND col <= %s |
| numeric | meta_query with compare | meta_query with compare | WHERE col >= %s |
| boolean | meta_query EXISTS/=/!= | meta_query EXISTS/=/!= | WHERE col = %d |

## Filter Types

### TextFilter
- Renders `<input type="text">` with search icon, clear X button
- 350ms debounce on input
- Applies meta query LIKE across configured meta keys

### DropdownFilter
- Renders `<select>` with placeholder "All" option
- For small option sets, renders direct options
- For taxonomies (`source: 'taxonomy:...'`), auto-fetches terms
- For class sources, calls the static method
- For table sources, queries the table for distinct values

### DateFilter
- Renders two date inputs (from/to) with quick preset buttons
- Presets: Today, Last 7 Days, Last 30 Days, This Month, Last Month, This Year
- Applies date_query (WP_Query) or SQL date comparison

### NumericFilter
- Renders operator dropdown + value input(s)
- Operators: =, >, <, ≥, ≤, Between (shows two inputs for Between)
- Applies meta_query with appropriate compare operator

### BooleanFilter
- Renders ternary dropdown: Any / Yes / No
- Yes → meta value = '1', No → meta value != '1' OR NOT EXISTS

## Sorting

Sorting uses the same AJAX flow as filtering:

- Clicking a sortable column header sends an AJAX request with the `orderby` and `order` params alongside all active filter values
- The sort arrow indicator updates client-side on success
- Sort state is included in the History API URL so it survives back/forward
- Sorting within the same column toggles ASC ↔ DESC
- Sorting a different column sets ASC initially

## QueryBuilder

A helper that normalizes modifications across query types:

```php
class QueryBuilder {
    public function add_meta_query(array $clause): void;
    public function add_tax_query(array $clause): void;
    public function add_date_query(array $clause): void;
    public function set_search(string $term): void;
    public function add_where(string $clause, ...$params): void;  // for custom SQL
    public function get_wp_query_args(): array;
    public function get_user_query_args(): array;
    public function get_sql_where(): string;
    public function get_sql_params(): array;
}
```

### DropdownFilter: `source` vs `options` Precedence

Both are optional but follow explicit precedence:

| Config | Behavior |
|---|---|
| `'options' => ['key'=>'Label']` | Renders those exact options — no external lookup |
| `'source' => 'taxonomy:ovr_type'` | Fetches terms from the named taxonomy — overrides `options` if both present |
| `'source' => 'class:MyClass::method'` | Calls the static method to get options array |
| `'source' => 'table:prefix_table|id_col|name_col'` | Queries the table for `id_col` and `name_col` |
| Neither | Renders free-text dropdown (user types to filter) |

When both `source` and `options` are set, `source` wins (runtime lookup replaces static list).

## Security & Error Handling

Every AJAX endpoint requires:
- `check_ajax_referer('ovr_filter_nonce')` on every request
- `current_user_can()` check matching the admin screen's capability
- Server-side validation of all filter keys against the allowed column config (reject unknown keys)
- SQL injection prevention via `$wpdb->prepare()` — no raw string interpolation

On error:
- Server returns `{ success: false, message: string }`
- Client shows a dismissible error toast (auto-dismiss 4s)
- Previous filter state remains active (the URL is NOT updated on failure)

On network failure:
- Client shows "Connection error. Retrying…" toast
- One automatic retry after 2s
- If retry fails, show persistent error message with manual "Retry" button

## JS Controller (ovr-filter-table.js)

Vanilla JS module, no framework dependency.

### Initialization
- Scans for `[data-ovr-filter-table]` wrapper element
- Reads `data-config` attribute for filter metadata
- Attaches listeners to all `[data-filter-key]` elements

### Events
- Text input: debounced 350ms `input` event
- Dropdown/date: `change` event → immediate request

### State Object Shape
Each history entry stores a plain object:

```js
{
    url:  "?page=ovr-properties&s=villa&pt=apartment",
    time: Date.now()
}
```

On `popstate`, the JS controller reads the URL from the state object and sends a fresh AJAX request to restore that filter state.

### Request Flow
1. Debounce if applicable
2. Collect `{ key: value }` from all `[data-filter-key]` elements
3. Build URL with params
4. `POST` to `admin-ajax.php?action=ovr_filter_table`
5. On success response → `history.pushState({ url, time }, '', url)` → replace `#ovr-table-tbody` and `#ovr-table-footer` with response HTML → restore scroll position
6. On error → show inline error toast (3s timeout) — do not update URL

### Features
- Loading shimmer on table body during request
- Clear individual filter (X button on each input)
- Reset all filters button
- Back/forward via `popstate` listener
- Bulk action checkbox management

## Implementation Plan (Phase 1 Only)

This plan covers building the core engine and applying it to Property Listings only. Subsequent screens (Users, Payments, Reviews, Bookings, CRM, Membership, Audit Log, Paid Services) will be follow-up plans after this one is validated.

### What We Build

| Component | Files | Purpose |
|---|---|---|
| Filter Type Interface | `src/Admin/FilterTypes/FilterTypeInterface.php` | Contract for all filter types — lives alongside implementations |
| Filter Types | `src/Admin/FilterTypes/{Text,Dropdown,Date,Numeric,Boolean}Filter.php` | 5 filter implementations |
| QueryBuilder | `src/Admin/QueryBuilder.php` | Normalized query modifier for WP_Query / WP_User_Query / SQL |
| FilterEngine | `src/Admin/FilterEngine.php` | Config parser, filter HTML renderer, dispatches to filter types |
| FilterTable | `src/Admin/FilterTable.php` | Orchestrator: render table, AJAX handler, pagination, state |
| JS Controller | `assets/js/ovr-filter-table.js` | Vanilla JS AJAX filtering controller |
| CSS | `assets/css/ovr-filter-table.css` | Column filter row styles |
| Base Template | `templates/admin/filter-table.php` | Reusable admin table template |
| ListTable update | `src/Admin/ListTable.php` (modified) | Add `with_filters()` for easier integration |

### Property Listing Screen Conversion

| File | Change |
|---|---|
| `src/Admin/PropertyListScreen.php` | Define column config, use FilterTable instead of custom query + form, register AJAX |
| `templates/admin/property-list.php` | Replace with FilterTable render call, remove old filter HTML/JS |
| `src/Plugin.php` | Register `ovr_admin_filter_table` AJAX endpoint, enqueue filter assets |
