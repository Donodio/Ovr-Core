# User Management Screen — Design Specification

> **Enterprise User Administration & Subscription Management Console**

**Version:** 1.0
**Status:** Draft
**Author:** OVR Platform Architecture

---

## 1. Overview

Redesign the Users admin page (`admin.php?page=ovr-core-users`) into a professional CRM-style management interface using the same FilterTable architecture already built for the Property Listings screen. The new screen separates **Account Status** from **Subscription Status** as independent concerns, provides spreadsheet-style inline column filters, and enables administrators to locate any user within seconds.

## 2. Architecture

### 2.1 Integration Pattern

Follow the same `PropertyListScreen` pattern:

```
UsersAdmin (retrofitted)
  ├── FilterTable (orchestrator: AJAX, enqueue, render)
  │   ├── FilterEngine → FilterTypeInterface implementations
  │   │   ├── TextFilter, DropdownFilter, DateFilter, NumericFilter
  │   │   └── BooleanFilter (unused in this screen)
  │   └── QueryBuilder → WP_User_Query (via QueryBuilder::for_users())
  ├── Custom query callback (build_custom_query)
  ├── Custom cell renderer (render_table_cell)
  └── Stats + toolbar (inline render methods)
```

### 2.2 Files

| File | Action | Purpose |
|------|--------|---------|
| `src/Admin/UsersAdmin.php` | **Modify** (major) | Retrofit to use FilterTable, add column configs, query callback, cell renderer |
| `templates/admin/users.php` | **Simplify** | Remove table rendering, keep only stats bar (or replace entirely) |
| `assets/css/ovr-filter-table.css` | **Reuse** | Already has column filter styles |
| `assets/css/ovr-admin-ui.css` | **Reuse** | Shared OVR admin design system |
| `assets/js/ovr-filter-table.js` | **Reuse** | Already handles AJAX, History API, debounce, pagination |
| `src/Admin/FilterTable.php` | **Reuse** | No changes needed; already supports user queries |

### 2.3 No New Architecture

All new behavior is implemented through the existing `FilterTable` extension points:
- `set_query_callback()` — custom WP_User_Query builder
- `set_render_cell_callback()` — rich cell rendering
- `FilterEngine::add_column_filter()` — column filter definitions

No new classes or architectural layers needed.

---

## 3. Column Definitions

### 3.1 Column Order

| # | Key | Label | Data Source | Sortable | Filter Type |
|---|-----|-------|-------------|----------|-------------|
| 1 | `uid` | User ID | `$user->ID` | Yes | Numeric (exact) |
| 2 | `status` | Status | `ovr_account_status` user meta | Yes | Dropdown (Active/Inactive) |
| 3 | `type` | Type | `ovr_account_type` user meta | Yes | Dropdown (Private Person/Business) |
| 4 | `role` | Role | `$user->roles[0]` | Yes | Dropdown (dynamic from wp_roles) |
| 5 | `username` | Username | `$user->display_name` | Yes | Text search |
| 6 | `phone` | Phone | `ovr_phone` user meta | No | Text search |
| 7 | `email` | Email | `$user->user_email` | Yes | Text search |
| 8 | `registered` | Registration Date | `$user->user_registered` | Yes | Date range |
| 9 | `subscription` | Subscription | `ovr_subscription_plan` user meta | Yes | Dropdown (dynamic from Plans) |
| 10 | `balance` | Balance | `ovr_balance` user meta | Yes | Numeric |

### 3.2 Column Specifications

#### User ID (`uid`)
- Rendered as clickable link with `#` prefix
- Copy-to-clipboard button
- Exact match via numeric filter
- Sortable

#### Status (`status`)
- Rendered as icon: green checkmark for `active`, red X for `inactive`
- Reads `ovr_account_status` user meta
- Default is `active` (including when meta key does not exist)
- **Never** affected by subscription expiry
- Sortable

#### Type (`type`)
- Rendered as badge
- Reads new `ovr_account_type` user meta key
- Default: `private_person`
- Badge colors: Private Person = blue, Business = gold
- Configurable for future account types
- Sortable

#### Role (`role`)
- Rendered as badge (administrator = red, ovr_landlord = green, subscriber = blue, ovr_support = orange)
- Reads from `$user->roles[0]` (primary role)
- Dropdown filter populated dynamically from `wp_roles()->get_names()`
- Sortable

#### Username (`username`)
- Displays user's display name
- Avatar (20x20) shown inline with name
- Click opens WordPress user profile edit page
- Searchable via text input
- Sortable

#### Phone (`phone`)
- Displays phone number from `ovr_phone` user meta
- Copyable (via copy-to-clipboard button)
- Searchable via text input
- Not sortable

#### Email (`email`)
- Rendered as `mailto:` link
- Copyable
- Searchable via text input
- Sortable

#### Registration Date (`registered`)
- Format: `M j, Y` (e.g., "Jul 8, 2026")
- Sort newest first by default
- Date range filter with presets (Today, 7 Days, 30 Days, This Month, Last Month, This Year)
- Uses native `user_registered` column — filtered via query callback with `pre_user_query` hook

#### Subscription (`subscription`)
- Rendered as colored badge matching plan slug
- Reads `ovr_subscription_plan` user meta
- If subscription has expired (checked via `ovr_subscription_expires` and `ovr_subscription_status`), defaults to `base_subscriber` display
- Plan badge colors: base_subscriber = gray, standard_homeowner_5 = green, property_manager_25 = blue, property_manager_40 = purple, long_term_only = gold
- Sortable

#### Balance (`balance`)
- Rendered as currency string (e.g., `$150.00`)
- Reads `ovr_balance` user meta (float)
- Numeric filter with operators (=, >, <, ≥, ≤, Between)
- Sortable

---

## 4. Filter Configuration

### 4.1 Column Filters

Each filter registered on the `FilterEngine`:

| Column | Type | Config |
|--------|------|--------|
| `uid` | `numeric` | `meta_key: '_oops_uid_fake'` (handled in query callback) |
| `status` | `dropdown` | `options: {active, inactive}`, `meta_key: 'ovr_account_status'` |
| `type` | `dropdown` | `options: {private_person, business}`, `meta_key: 'ovr_account_type'` |
| `role` | `dropdown` | `options: dynamic from wp_roles()`, handled in query callback |
| `username` | `text` | Handled in query callback via `search_columns` |
| `phone` | `text` | `meta_key: 'ovr_phone'` |
| `email` | `text` | Handled in query callback via `search_columns` |
| `registered` | `date` | Handled in query callback via `pre_user_query` |
| `subscription` | `dropdown` | `options: dynamic from Plans::get_plans()`, `meta_key: 'ovr_subscription_plan'` |
| `balance` | `numeric` | `meta_key: 'ovr_balance'` |

### 4.2 Filter Behavior

- Text inputs: debounced at 350ms (handled by `ovr-filter-table.js`)
- Dropdowns: apply immediately on change
- Date inputs: apply on change (date picker + preset buttons)
- Numeric: apply on change (operator select + value inputs)
- All AJAX (no page reloads), History API for back/forward
- Reset Filters clears all inputs and resets to defaults

---

## 5. Subscription Lifecycle Rules

### 5.1 Entities

| Concept | Meta Key | Values |
|---------|----------|--------|
| Account Status | `ovr_account_status` | `active`, `inactive` |
| Subscription Plan | `ovr_subscription_plan` | Plan slug or absent |
| Subscription Status | `ovr_subscription_status` | `none`, `pending`, `active`, `expired`, `cancelled`, `suspended` |
| Subscription Expires | `ovr_subscription_expires` | `Y-m-d` date or absent |

### 5.2 State Machine

```
Registration
  → Account: active
  → Subscription: base_subscriber (ovr_subscription_plan = absent)
  
Membership Purchase
  → Account: active (unchanged)
  → Subscription: purchased plan slug (e.g., standard_homeowner_5)
  
Subscription Expiry (Lifecycle cron)
  → Account: active (NEVER changes)
  → Subscription plan → base_subscriber (meta cleared)
  → Listings → hidden (pending_renewal)

Renewal
  → Account: active (unchanged)
  → Subscription → restored plan slug
  → Listings → restored (visible)
```

### 5.3 Display Rules

The **Subscription column** shows:
- If `ovr_subscription_status` is `active` and `ovr_subscription_expires` ≥ today → show the plan name
- Otherwise → show "Base Subscriber" (gray badge)

The **Status column** shows:
- If `ovr_account_status` is `active` or meta key absent → green checkmark
- If `ovr_account_status` is `inactive` → red X

---

## 6. Custom Query Callback

The `build_custom_query()` callback receives `(array $filters, int $page, string $orderby, string $order, string $search, FilterTable $table)` and returns `WP_User_Query`.

Logic:
1. **Orderby mapping**: uid → `ID`, username → `display_name`, email → `user_email`, registered → `user_registered`, subscription → `meta_value` (with meta_key = `ovr_subscription_plan`), status → `meta_value` (with meta_key = `ovr_account_status`), type → `meta_value` (with meta_key = `ovr_account_type`), role → `role`, balance → `meta_value_num` (with meta_key = `ovr_balance`)
2. **Search**: Uses `search_columns` = `['user_login', 'user_nicename', 'user_email', 'display_name']` with wildcard `*term*`
3. **Role filter**: Applied via `role__in` parameter for multi-role support
4. **Date filter (registration)**: Applied via `pre_user_query` hook to add WHERE clause on `user_registered` column
5. **Meta queries**: Applied via `$table->get_engine()->apply_filters()` for standard column filters
6. **Permission gate**: All queries run under `ovr_manage_users` capability (already enforced by page registration)

---

## 7. UI Layout

```
┌─────────────────────────────────────────────────┐
│  All Properties  [+ Add User] [Export] [↻] [↺]  │  ← Global Toolbar
├─────────────────────────────────────────────────┤
│  [Total: N]  [Active Subs: N]  [Managers: N]    │  ← Stats Bar
│                    [Pending: N]                  │
├─────────────────────────────────────────────────┤
│  ┌─────┬──────┬────┬────┬────────┬───────┬───┐  │
│  │ ID  │Status│Type│Role│Username│Phone  │…  │  │  ← Column Filters
│  │ [num]│ [sel]│[sel]│[sel]│ [text] │ [text]│   │  │
│  ├─────┼──────┼────┼────┼────────┼───────┼───┤  │
│  │ #42 │  ✓   │ B  │ A  │ jdoe   │ …     │   │  │  ← Table Rows
│  │ #15 │  ✓   │ P  │ L  │ msmith │ …     │   │  │
│  └─────┴──────┴────┴────┴────────┴───────┴───┘  │
├─────────────────────────────────────────────────┤
│  [Bulk Actions ▼] [Apply]    « 1 2 3 … N »      │  ← Pagination
└─────────────────────────────────────────────────┘
```

### 7.1 Global Toolbar

| Item | Action | Implementation |
|------|--------|----------------|
| + Add User | Links to `wp-admin/user-new.php` | HTML href |
| Export Users | Triggers CSV download | Admin action hook (CSV generation callback) |
| ↻ Refresh | Reloads current page | Button with `location.reload()` |
| ↺ Reset Filters | Clears all filters, restores defaults | JS via filter-table.js bindings |

### 7.2 Bulk Actions

| Action | Implementation | Status |
|--------|----------------|--------|
| Activate | Sets `ovr_account_status = 'active'` | Phase 1 |
| Suspend | Sets `ovr_account_status = 'inactive'` | Phase 1 |
| Reset Password | Triggers WordPress password reset email | Phase 1 |
| Assign Membership | Redirects to bulk assignment page | Phase 1 |
| Send Email | Architecture prepared, not wired | Future |
| Export Selected | Architecture prepared, not wired | Future |

Bulk actions are dispatched via custom DOM event `ovr_bulk_action`, already supported by `ovr-filter-table.js`. The `UsersAdmin` class listens for this event and handles the AJAX request.

### 7.3 Stats Bar

Four stat cards displayed above the filter bar:

| Stat | Query |
|------|-------|
| Total Users | `count_users()` |
| Active Subscriptions | Users with `ovr_subscription_status = 'active'` AND `ovr_subscription_expires >= today` |
| Property Managers | Users with role `ovr_landlord` |
| Pending Approvals | Users where `ovr_account_type` meta key is absent (new registrations needing type assignment). Excludes users where the key exists but is empty string. |

---

## 8. Account Type (`ovr_account_type`)

### 8.1 Meta Key

- **Key:** `ovr_account_type`
- **Values:** `private_person`, `business`
- **Default:** `private_person` (set at registration)
- **Storage:** User meta

### 8.2 Registration Hook

In `RegistrationHandler`, after user creation:
```php
add_user_meta( $user_id, 'ovr_account_type', 'private_person', true );
```

### 8.3 Admin Profile Field

Add a select field to the WordPress user profile edit page (via `show_user_profile` / `edit_user_profile` hooks):
- Options: Private Person, Business
- Saves to `ovr_account_type` meta

### 8.4 Extensibility

Future account types can be added by:
1. Adding the value to the dropdown options
2. Adding a filter hook `ovr_account_types` for programmatic registration

No database migration needed — it's user meta.

---

## 9. CSV Export

- URL: `admin.php?page=ovr-core-users&export_csv=1&_wpnonce=...`
- `UsersAdmin` handles the `export_csv` GET param before rendering
- Outputs `Content-Type: text/csv` with `Content-Disposition: attachment`
- Columns: ID, Status, Type, Role, Username, Phone, Email, Registration Date, Subscription, Balance
- Respects current filters (exports filtered results, not all users)
- Uses `fputcsv()` with streaming output (no memory buffer for large datasets)

---

## 10. Roles & Capabilities

- Page gated by `ovr_manage_users` capability (administrator only, defined in `Capabilities::admin()`)
- All AJAX endpoints check `ovr_manage_users` via `current_user_can()`
- Nonce verification on all state-changing actions

---

## 11. Audit Logging (Architecture)

### 11.1 Hook Points

Prepare for future audit logging by defining:

```php
/**
 * Fires when a user's account is modified from the Users admin screen.
 *
 * @param int    $target_user_id The user being modified.
 * @param string $action         Action identifier (e.g., 'activate', 'suspend').
 * @param mixed  $old_value      Previous value before change.
 * @param mixed  $new_value      New value after change.
 * @param int    $admin_id       ID of the administrator performing the action.
 */
do_action( 'ovr_user_admin_action', $target_user_id, $action, $old_value, $new_value, get_current_user_id() );
```

### 11.2 Actions to Log (Future)

- `ovr_user_status_toggle`: Account status changed
- `ovr_user_type_change`: Account type changed
- `ovr_user_role_change`: User role changed
- `ovr_user_subscription_assign`: Subscription manually assigned
- `ovr_user_balance_adjust`: Balance adjusted
- `ovr_user_password_reset`: Password reset triggered
- `ovr_user_bulk_action`: Bulk action applied to multiple users

---

## 12. Column Summary (Data Sources)

| Column | Key | Data Location | WP_User_Query Field |
|--------|-----|---------------|---------------------|
| User ID | `uid` | `$user->ID` | `ID` |
| Account Status | `status` | `get_user_meta($id, 'ovr_account_status', true)` | `meta_query` |
| Account Type | `type` | `get_user_meta($id, 'ovr_account_type', true)` | `meta_query` |
| Role | `role` | `$user->roles[0]` | `role__in` |
| Username | `username` | `$user->display_name` | `search` with `search_columns` |
| Phone | `phone` | `get_user_meta($id, 'ovr_phone', true)` | `meta_query` |
| Email | `email` | `$user->user_email` | `search` with `search_columns` |
| Registration Date | `registered` | `$user->user_registered` | `pre_user_query` WHERE |
| Subscription | `subscription` | `get_user_meta($id, 'ovr_subscription_plan', true)` | `meta_query` |
| Balance | `balance` | `get_user_meta($id, 'ovr_balance', true)` | `meta_query` |

---

## 13. Edge Cases

| Case | Behavior |
|------|----------|
| User has no `ovr_account_status` meta | Treated as `active` |
| User has no `ovr_subscription_plan` meta | Displayed as "Base Subscriber" |
| User has no `ovr_balance` meta | Displayed as `$0.00` |
| User has no `ovr_account_type` meta | Treated as `private_person` (default) |
| User has no `ovr_phone` meta | Displayed as `—` |
| Subscription expired but `ovr_subscription_plan` not cleared | Check `ovr_subscription_expires` + today comparison |
| User has multiple roles | Only `$user->roles[0]` displayed |
| Role filter empty (match all) | `role__in` omitted from query args |
| Search term empty | `search` omitted from query args |
| Bulk action with 0 selected users | Alert shown, no action taken |
| CSV export with 0 results | Empty CSV with headers only |
| Date filter parsing failure | Gracefully ignored (filter not applied) |

---

## 14. Performance

- All queries server-side via `WP_User_Query` (no client-side filtering)
- `search_columns` limits search to indexed user columns
- Pagination via `number` / `offset` / `paged` parameters
- Meta queries use indexed `wp_usermeta.meta_key + meta_value`
- CSV export streams output via `fputcsv` (no memory buffer)
- Bulk actions process in a loop with individual updates (safe for large selections; future optimization to batch processing if needed)

---

## 15. Accessibility

- Table uses proper `<th scope="col">` / `<th scope="row">` semantics
- Sortable headers marked with `aria-sort`
- Filter inputs have `<label>` elements
- Color-coded badges include text labels (not color-only)
- Icons have `aria-label` text
- Bulk action buttons have descriptive text
- Keyboard navigation: Tab through filters, Enter to apply
- Focus management preserved after AJAX table refresh

---

## 16. Security

- Page and all AJAX endpoints gated by `current_user_can('ovr_manage_users')`
- Nonce verification on all state-changing actions (`toggle_status`, `bulk_action`)
- CSV export outputs only user data admin already has permission to see
- User search respects `search_columns` restrictions
- Bulk action IDs validated (sanitized to `absint`)
- All output escaped (`esc_html`, `esc_attr`, `esc_url`)
- SQL query modifications via `pre_user_query` use `$wpdb->prepare()`

---

## 17. Quality Assurance Checklist

- [ ] `php -l` passes on all modified/new PHP files
- [ ] All 10 columns render with correct data
- [ ] Column order matches spec
- [ ] Every column filter works independently
- [ ] Multiple filters combine correctly (AND logic)
- [ ] Reset Filters clears all inputs
- [ ] Sorting works on all sortable columns
- [ ] Sorting respects active filters
- [ ] Sorting persists across pagination
- [ ] AJAX updates table without page reload
- [ ] History API back/forward restores filter state
- [ ] Users with no meta keys show default values
- [ ] Account status stays Active after subscription expiry
- [ ] Expired subscriptions show Base Subscriber
- [ ] Bulk Activate/Suspend works
- [ ] Bulk Reset Password sends email
- [ ] CSV export downloads file with correct data
- [ ] Add User links to user-new.php
- [ ] Refresh reloads page
- [ ] 4 stat cards show correct counts
- [ ] Account type field appears on user profile
- [ ] Large dataset remains responsive
- [ ] Non-admin cannot access page
- [ ] Unauthorized AJAX requests rejected
- [ ] Nonce validation on state changes
- [ ] All output properly escaped
- [ ] Page matches Property Listings visual style
