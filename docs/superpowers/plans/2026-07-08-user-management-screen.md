# User Management Screen — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Retrofit the Users admin screen (`admin.php?page=ovr-core-users`) to use the FilterTable architecture — matching the PropertyListScreen pattern — with 10 columns, inline column filters, AJAX, stats bar, global toolbar, bulk actions, CSV export, and subscription lifecycle compliance.

**Architecture:** Modify `UsersAdmin.php` to create a `FilterTable` instance, configure column filters on the `FilterEngine`, and delegate rendering/querying/AJAX to FilterTable. Handle native user fields (ID, role, email, username, registered) in a custom query callback rather than through the engine's meta-query-based filters. Keep stats bar, toolbar, and service modal as inline render methods.

**Tech Stack:** PHP 8.0+, WordPress, FilterTable, FilterEngine, QueryBuilder, WP_User_Query, ovr-filter-table.js, ovr-admin-ui.css

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `src/Admin/UsersAdmin.php` | Modify (major) | Retrofit to FilterTable architecture |
| `templates/admin/users.php` | Remove | Replaced by FilterTable::render() |
| `src/Auth/RegistrationHandler.php` | Modify (minor) | Add `ovr_account_type` meta on registration |
| `src/Admin/AdminAssets.php` | Modify (minor) | Add users page to enqueue conditions |
| `src/Plugin.php` | Modify (minor) | Add bulk action AJAX listener registration |
| `assets/css/ovr-admin-ui.css` | Modify (minor) | Add users stat card styles (or keep inline) |

---

## Chunk 1: UsersAdmin FilterTable Integration

- [ ] **Step 1: Read current UsersAdmin.php to understand existing API**

```bash
php -l src/Admin/UsersAdmin.php
```

- [ ] **Step 2: Replace UsersAdmin class with FilterTable-backed version**

Write to `src/Admin/UsersAdmin.php`:

```php
<?php

namespace OVR\Admin;

use OVR\Subscription\Plans;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class UsersAdmin {

    public const PAGE_SLUG  = 'ovr-core-users';
    public const PER_PAGE   = 20;

    private ?FilterTable $filter_table = null;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );

        $this->filter_table = new FilterTable( self::PAGE_SLUG );
        $this->filter_table->set_columns( $this->get_column_labels() );
        $this->filter_table->set_sortable( [ 'uid', 'status', 'type', 'role', 'username', 'email', 'registered', 'subscription', 'balance' ] );
        $this->filter_table->set_bulk_actions( [
            'activate'    => __( 'Activate', 'ovr-core' ),
            'suspend'     => __( 'Suspend', 'ovr-core' ),
            'reset_password' => __( 'Reset Password', 'ovr-core' ),
            'assign_membership' => __( 'Assign Membership', 'ovr-core' ),
        ] );
        $this->filter_table->set_labels( __( 'User', 'ovr-core' ), __( 'Users', 'ovr-core' ) );
        $this->filter_table->set_query_callback( [ $this, 'build_custom_query' ] );
        $this->filter_table->set_render_cell_callback( [ $this, 'render_table_cell' ] );
        $this->filter_table->init();

        // Register column filters on the FilterEngine for rendering.
        // Query filtering is handled in build_custom_query for all columns.
        $engine = $this->filter_table->get_engine();
        $engine->add_column_filter( 'uid', [
            'type'  => 'numeric',
            'label' => __( 'User ID', 'ovr-core' ),
        ] );
        $engine->add_column_filter( 'status', [
            'type'      => 'dropdown',
            'label'     => __( 'Status', 'ovr-core' ),
            'options'   => [
                'active'   => __( 'Active', 'ovr-core' ),
                'inactive' => __( 'Inactive', 'ovr-core' ),
            ],
            'meta_key'  => 'ovr_account_status',
        ] );
        $engine->add_column_filter( 'type', [
            'type'      => 'dropdown',
            'label'     => __( 'Type', 'ovr-core' ),
            'options'   => [
                'private_person' => __( 'Private Person', 'ovr-core' ),
                'business'       => __( 'Business', 'ovr-core' ),
            ],
            'meta_key'  => 'ovr_account_type',
        ] );
        $engine->add_column_filter( 'role', [
            'type'    => 'dropdown',
            'label'   => __( 'Role', 'ovr-core' ),
            'options' => $this->get_role_options(),
        ] );
        $engine->add_column_filter( 'username', [
            'type'  => 'text',
            'label' => __( 'Username', 'ovr-core' ),
            'placeholder' => __( 'Search username…', 'ovr-core' ),
        ] );
        $engine->add_column_filter( 'phone', [
            'type'        => 'text',
            'label'       => __( 'Phone', 'ovr-core' ),
            'placeholder' => __( 'Search phone…', 'ovr-core' ),
            'meta_key'    => 'ovr_phone',
        ] );
        $engine->add_column_filter( 'email', [
            'type'        => 'text',
            'label'       => __( 'Email', 'ovr-core' ),
            'placeholder' => __( 'Search email…', 'ovr-core' ),
        ] );
        $engine->add_column_filter( 'registered', [
            'type'  => 'date',
            'label' => __( 'Registration Date', 'ovr-core' ),
        ] );
        $engine->add_column_filter( 'subscription', [
            'type'      => 'dropdown',
            'label'     => __( 'Subscription', 'ovr-core' ),
            'options'   => $this->get_plan_options(),
            'meta_key'  => 'ovr_subscription_plan',
        ] );
        $engine->add_column_filter( 'balance', [
            'type'      => 'numeric',
            'label'     => __( 'Balance', 'ovr-core' ),
            'meta_key'  => 'ovr_balance',
        ] );

        // CSV export handler.
        add_action( 'admin_post_ovr_export_users_csv', [ $this, 'handle_export_csv' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Users', 'ovr-core' ),
            __( 'Users', 'ovr-core' ),
            'ovr_manage_users',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function enqueue( string $hook ): void {
        if ( ( $_GET['page'] ?? '' ) !== self::PAGE_SLUG ) {
            return;
        }
        wp_enqueue_style(
            'ovr-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&family=Inter:wght@400;500;600;700&display=swap',
            [],
            null
        );
        if ( $this->filter_table ) {
            $this->filter_table->enqueue_assets( $hook );
        }
    }

    public function render(): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            return;
        }

        $stats = $this->get_stats();
        $csv_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=ovr_export_users_csv' ),
            'ovr_export_users_csv'
        );

        echo '<div class="wrap ovr-ld ovr-us-page" id="ovr-filter-table-wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__( 'Users Management', 'ovr-core' ) . '</h1>';
        echo '<hr class="wp-header-end">';

        $this->render_global_toolbar( $csv_url );
        $this->render_stats_bar( $stats );

        if ( $this->filter_table ) {
            $this->filter_table->render();
        }

        echo '</div>';
    }

    private function render_global_toolbar( string $csv_url ): void {
        ?>
        <div class="ovr-adm-toolbar" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;padding:12px 0;">
            <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="button button-primary">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:-4px">person_add</span>
                <?php esc_html_e( 'Add User', 'ovr-core' ); ?>
            </a>
            <a href="<?php echo esc_url( $csv_url ); ?>" class="button">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:-4px">download</span>
                <?php esc_html_e( 'Export CSV', 'ovr-core' ); ?>
            </a>
            <button type="button" class="button" onclick="location.reload()">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:-4px">refresh</span>
                <?php esc_html_e( 'Refresh', 'ovr-core' ); ?>
            </button>
            <button type="button" class="button ovr-reset-filters">
                <span class="material-symbols-outlined" style="font-size:18px;vertical-align:-4px">filter_alt_off</span>
                <?php esc_html_e( 'Reset Filters', 'ovr-core' ); ?>
            </button>
        </div>
        <script>
        (function() {
            document.querySelector('.ovr-reset-filters')?.addEventListener('click', function() {
                var params = new URLSearchParams(window.location.search);
                params.delete('ovr_filters');
                params.delete('paged');
                params.delete('orderby');
                params.delete('order');
                params.delete('s');
                window.location.search = params.toString();
            });
        })();
        </script>
        <?php
    }

    private function render_stats_bar( array $stats ): void {
        ?>
        <div class="ovr-adm-stats" style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
            <?php $stat_items = [
                [ 'icon' => 'people', 'value' => $stats['total_users'] ?? 0, 'label' => __( 'Total Users', 'ovr-core' ) ],
                [ 'icon' => 'verified', 'value' => $stats['active_subs'] ?? 0, 'label' => __( 'Active Subscriptions', 'ovr-core' ) ],
                [ 'icon' => 'badge', 'value' => $stats['property_managers'] ?? 0, 'label' => __( 'Property Managers', 'ovr-core' ) ],
                [ 'icon' => 'pending_actions', 'value' => $stats['pending_approvals'] ?? 0, 'label' => __( 'Pending Approvals', 'ovr-core' ) ],
            ];
            foreach ( $stat_items as $s ) : ?>
                <div class="ovr-adm-stat" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px 24px;display:flex;align-items:center;gap:16px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                    <div style="width:48px;height:48px;border-radius:8px;background:#f0f0f1;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <span class="material-symbols-outlined" style="font-size:24px;color:#1d2327"><?php echo esc_html( $s['icon'] ); ?></span>
                    </div>
                    <div>
                        <div style="font-size:28px;font-weight:700;line-height:1.2;color:#1d2327;font-variant-numeric:tabular-nums;"><?php echo esc_html( number_format_i18n( (int) $s['value'] ) ); ?></div>
                        <div style="font-size:13px;color:#646970;font-weight:500;"><?php echo esc_html( $s['label'] ); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function get_role_options(): array {
        $roles = [];
        foreach ( wp_roles()->get_names() as $slug => $name ) {
            $roles[ $slug ] = $name;
        }
        return $roles;
    }

    private function get_plan_options(): array {
        $plans = Plans::get_plans();
        $options = [];
        foreach ( $plans as $slug => $plan ) {
            $options[ $slug ] = is_array( $plan ) ? ( $plan['name'] ?? $slug ) : (string) $plan;
        }
        return $options;
    }

    public function get_column_labels(): array {
        return [
            'uid'          => __( 'User ID', 'ovr-core' ),
            'status'       => __( 'Status', 'ovr-core' ),
            'type'         => __( 'Type', 'ovr-core' ),
            'role'         => __( 'Role', 'ovr-core' ),
            'username'     => __( 'Username', 'ovr-core' ),
            'phone'        => __( 'Phone', 'ovr-core' ),
            'email'        => __( 'Email', 'ovr-core' ),
            'registered'   => __( 'Registered', 'ovr-core' ),
            'subscription' => __( 'Subscription', 'ovr-core' ),
            'balance'      => __( 'Balance', 'ovr-core' ),
        ];
    }
```

- [ ] **Step 3: Verify syntax so far**

```bash
php -l src/Admin/UsersAdmin.php
```

## Chunk 2: Query Callback, Cell Renderer, Bulk Actions, CSV Export

- [ ] **Step 1: Add the custom query callback method to UsersAdmin**

Add inside the class after `get_column_labels()`:

```php
    public function build_custom_query( array $filters, int $page, string $orderby, string $order, string $search, FilterTable $table ): \WP_User_Query {
        $per_page = self::PER_PAGE;

        // Orderby mapping.
        $orderby_map = [
            'uid'          => 'ID',
            'username'     => 'display_name',
            'email'        => 'user_email',
            'registered'   => 'user_registered',
            'status'       => 'meta_value',
            'type'         => 'meta_value',
            'role'         => 'role',
            'subscription' => 'meta_value',
            'balance'      => 'meta_value_num',
        ];
        $wp_orderby  = $orderby_map[ $orderby ] ?? 'user_registered';
        $meta_key    = null;
        if ( 'meta_value' === $wp_orderby ) {
            $meta_key_map = [
                'status'       => 'ovr_account_status',
                'type'         => 'ovr_account_type',
                'subscription' => 'ovr_subscription_plan',
            ];
            $meta_key = $meta_key_map[ $orderby ] ?? null;
        } elseif ( 'meta_value_num' === $wp_orderby ) {
            $meta_key = 'ovr_balance';
        }

        $args = [
            'fields'     => 'all',
            'number'     => $per_page,
            'paged'      => $page,
            'orderby'    => $wp_orderby,
            'order'      => $order,
        ];

        if ( $meta_key ) {
            $args['meta_key'] = $meta_key;
        }

        // Search: username, email, display name, nicename.
        if ( '' !== $search ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
        }

        // Column-level meta queries (status, type, phone, subscription, balance).
        $meta_query = [];
        $meta_filters = [ 'status', 'type', 'subscription', 'balance', 'phone' ];
        foreach ( $meta_filters as $key ) {
            if ( ! isset( $filters[ $key ] ) || '' === $filters[ $key ] || [] === $filters[ $key ] ) {
                continue;
            }
            $val = $filters[ $key ];
            switch ( $key ) {
                case 'status':
                    $meta_query[] = [ 'key' => 'ovr_account_status', 'value' => $val ];
                    break;
                case 'type':
                    $meta_query[] = [ 'key' => 'ovr_account_type', 'value' => $val ];
                    break;
                case 'subscription':
                    $meta_query[] = [ 'key' => 'ovr_subscription_plan', 'value' => $val ];
                    break;
                case 'phone':
                    $meta_query[] = [ 'key' => 'ovr_phone', 'value' => $val, 'compare' => 'LIKE' ];
                    break;
                case 'balance':
                    $this->apply_balance_filter( $meta_query, $val );
                    break;
            }
        }

        // UID exact match.
        if ( isset( $filters['uid'] ) && is_array( $filters['uid'] ) ) {
            $uid_val = $filters['uid']['val'] ?? '';
            if ( '' !== $uid_val ) {
                $uid = absint( $uid_val );
                if ( $uid ) {
                    return new \WP_User_Query( [ 'include' => [ $uid ], 'number' => 1 ] );
                }
            }
        }

        // Role filter.
        if ( isset( $filters['role'] ) && '' !== $filters['role'] ) {
            $args['role__in'] = [ sanitize_key( $filters['role'] ) ];
        }

        if ( $meta_query ) {
            $args['meta_query'] = $meta_query;
        }

        $query = new \WP_User_Query( $args );

        // Date filter for user_registered.
        if ( isset( $filters['registered'] ) && is_array( $filters['registered'] ) ) {
            $from = $filters['registered']['from'] ?? '';
            $to   = $filters['registered']['to'] ?? '';
            if ( '' !== $from || '' !== $to ) {
                $this->apply_registered_date_filter( $query, $from, $to );
            }
        }

        return $query;
    }

    private function apply_balance_filter( array &$meta_query, $val ): void {
        $op   = $val['op'] ?? '=';
        $val1 = $val['val'] ?? '';
        $val2 = $val['val2'] ?? '';
        if ( '' === $val1 && 'bt' !== $op ) {
            return;
        }
        if ( 'bt' === $op && ( '' === $val1 || '' === $val2 ) ) {
            return;
        }
        switch ( $op ) {
            case 'bt':
                $meta_query[] = [ 'key' => 'ovr_balance', 'value' => [ (float) $val1, (float) $val2 ], 'compare' => 'BETWEEN', 'type' => 'NUMERIC' ];
                break;
            default:
                $meta_query[] = [ 'key' => 'ovr_balance', 'value' => (float) $val1, 'compare' => $op, 'type' => 'NUMERIC' ];
                break;
        }
    }

    private function apply_registered_date_filter( \WP_User_Query $query, string $from, string $to ): void {
        global $wpdb;
        $where = '';
        if ( '' !== $from ) {
            $where .= $wpdb->prepare( ' AND user_registered >= %s', $from . ' 00:00:00' );
        }
        if ( '' !== $to ) {
            $where .= $wpdb->prepare( ' AND user_registered <= %s', $to . ' 23:59:59' );
        }
        if ( '' !== $where ) {
            add_action( 'pre_user_query', function ( \WP_User_Query $q ) use ( $where ) {
                $q->query_where .= $where;
            } );
        }
    }
```

- [ ] **Step 2: Verify syntax**

```bash
php -l src/Admin/UsersAdmin.php
```

- [ ] **Step 3: Add the cell renderer method**

```php
    public function render_table_cell( string $key, $item ): void {
        $uid         = (int) $item->ID;
        $status      = get_user_meta( $uid, 'ovr_account_status', true ) ?: 'active';
        $type        = get_user_meta( $uid, 'ovr_account_type', true ) ?: 'private_person';
        $role        = $item->roles[0] ?? '';
        $plan_slug   = $this->resolve_plan_slug( $uid );
        $plan_data   = Plans::get_plan( $plan_slug );
        $plan_name   = $plan_data['name'] ?? __( 'Base Subscriber', 'ovr-core' );
        $balance     = (float) get_user_meta( $uid, 'ovr_balance', true );
        $phone       = get_user_meta( $uid, 'ovr_phone', true );
        $edit_url    = admin_url( 'user-edit.php?user_id=' . $uid );
        $avatar      = get_avatar( $uid, 20 );

        switch ( $key ) {
            case 'uid':
                ?><a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-us-uid" style="font-weight:600;">#<?php echo (int) $uid; ?></a>
                <button type="button" class="ovr-us-uid-copy" data-copy="<?php echo (int) $uid; ?>" style="background:none;border:none;cursor:pointer;color:#8c8f94;padding:0 4px;vertical-align:middle;font-size:14px;" title="<?php esc_attr_e( 'Copy User ID', 'ovr-core' ); ?>">
                    <span class="material-symbols-outlined" style="font-size:16px;">content_copy</span>
                </button><?php
                break;

            case 'status':
                if ( 'active' === $status ) : ?>
                    <span class="ovr-us-status-icon" style="color:#2e7d32;" aria-label="<?php esc_attr_e( 'Active', 'ovr-core' ); ?>">
                        <span class="material-symbols-outlined">check_circle</span>
                    </span>
                <?php else : ?>
                    <span class="ovr-us-status-icon" style="color:#b3261e;" aria-label="<?php esc_attr_e( 'Inactive', 'ovr-core' ); ?>">
                        <span class="material-symbols-outlined">cancel</span>
                    </span>
                <?php endif;
                break;

            case 'type':
                if ( 'business' === $type ) : ?>
                    <span class="ovr-adm-badge" style="background:#fef5d6;color:#b8920a;border:1px solid #f0d767;font-size:12px;font-weight:600;padding:3px 10px;border-radius:4px;white-space:nowrap;"><?php esc_html_e( 'Business', 'ovr-core' ); ?></span>
                <?php else : ?>
                    <span class="ovr-adm-badge" style="background:#e5f5fe;color:#0073aa;border:1px solid #b8e1f5;font-size:12px;font-weight:600;padding:3px 10px;border-radius:4px;white-space:nowrap;"><?php esc_html_e( 'Private Person', 'ovr-core' ); ?></span>
                <?php endif;
                break;

            case 'role':
                $role_colors = [
                    'administrator' => [ 'bg' => '#f9e4e2', 'fg' => '#b3261e', 'border' => '#f0ccc8' ],
                    'ovr_landlord'  => [ 'bg' => '#e4f4e4', 'fg' => '#2e7d32', 'border' => '#c8e6c9' ],
                    'ovr_support'   => [ 'bg' => '#fff3e0', 'fg' => '#e65100', 'border' => '#ffe0b2' ],
                    'subscriber'    => [ 'bg' => '#e5f5fe', 'fg' => '#0073aa', 'border' => '#b8e1f5' ],
                ];
                $colors = $role_colors[ $role ] ?? [ 'bg' => '#f0f0f1', 'fg' => '#646970', 'border' => '#dcdcde' ];
                $display = wp_roles()->get_names()[ $role ] ?? $role;
                ?><span class="ovr-adm-badge" style="background:<?php echo esc_attr( $colors['bg'] ); ?>;color:<?php echo esc_attr( $colors['fg'] ); ?>;border:1px solid <?php echo esc_attr( $colors['border'] ); ?>;font-size:12px;font-weight:600;padding:3px 10px;border-radius:4px;white-space:nowrap;"><?php echo esc_html( $display ); ?></span><?php
                break;

            case 'username':
                ?><div style="display:flex;align-items:center;gap:8px;">
                    <?php echo $avatar; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped */ ?>
                    <a href="<?php echo esc_url( $edit_url ); ?>" style="font-weight:500;text-decoration:none;"><?php echo esc_html( $item->display_name ); ?></a>
                </div><?php
                break;

            case 'phone':
                if ( $phone ) : ?>
                    <span><?php echo esc_html( $phone ); ?></span>
                    <button type="button" class="ovr-us-phone-copy" data-copy="<?php echo esc_attr( $phone ); ?>" style="background:none;border:none;cursor:pointer;color:#8c8f94;padding:0 4px;vertical-align:middle;font-size:14px;" title="<?php esc_attr_e( 'Copy phone', 'ovr-core' ); ?>">
                        <span class="material-symbols-outlined" style="font-size:16px;">content_copy</span>
                    </button><?php
                else :
                    ?><span style="color:#8c8f94;">&mdash;</span><?php
                endif;
                break;

            case 'email':
                ?><a href="mailto:<?php echo esc_attr( $item->user_email ); ?>" style="text-decoration:none;"><?php echo esc_html( $item->user_email ); ?></a><?php
                break;

            case 'registered':
                $ts = strtotime( $item->user_registered );
                echo esc_html( date_i18n( 'M j, Y', $ts ?: time() ) );
                break;

            case 'subscription':
                $badge_styles = [
                    'base_subscriber'     => [ 'bg' => '#f0f0f1', 'fg' => '#646970', 'border' => '#dcdcde' ],
                    'standard_homeowner_5' => [ 'bg' => '#e4f4e4', 'fg' => '#2e7d32', 'border' => '#c8e6c9' ],
                    'property_manager_25'  => [ 'bg' => '#e5f5fe', 'fg' => '#0073aa', 'border' => '#b8e1f5' ],
                    'property_manager_40'  => [ 'bg' => '#f3e5f5', 'fg' => '#7b1fa2', 'border' => '#e1bee7' ],
                    'long_term_only'       => [ 'bg' => '#fef5d6', 'fg' => '#b8920a', 'border' => '#f0d767' ],
                ];
                $colors = $badge_styles[ $plan_slug ] ?? [ 'bg' => '#f0f0f1', 'fg' => '#646970', 'border' => '#dcdcde' ];
                ?><span class="ovr-adm-badge" style="background:<?php echo esc_attr( $colors['bg'] ); ?>;color:<?php echo esc_attr( $colors['fg'] ); ?>;border:1px solid <?php echo esc_attr( $colors['border'] ); ?>;font-size:12px;font-weight:600;padding:3px 10px;border-radius:4px;white-space:nowrap;"><?php echo esc_html( $plan_name ); ?></span><?php
                break;

            case 'balance':
                $settings = get_option( 'ovr_settings', [] );
                $symbol   = $settings['currency_symbol'] ?? '$';
                echo esc_html( $balance > 0 ? $symbol . number_format( $balance, 2 ) : $symbol . '0.00' );
                break;

            default:
                echo esc_html( $item->$key ?? '' );
                break;
        }
    }

    private function resolve_plan_slug( int $user_id ): string {
        $sub_status = get_user_meta( $user_id, 'ovr_subscription_status', true );
        $expires    = get_user_meta( $user_id, 'ovr_subscription_expires', true );
        $plan       = get_user_meta( $user_id, 'ovr_subscription_plan', true );

        // If subscription is active and not expired, show the plan.
        if ( 'active' === $sub_status && $expires && strtotime( $expires ) >= time() ) {
            return $plan ?: 'base_subscriber';
        }
        // Otherwise, show base_subscriber.
        return 'base_subscriber';
    }
```

- [ ] **Step 4: Add stats, CSV export, and bulk action methods**

```php
    private function get_stats(): array {
        global $wpdb;

        $users_data = count_users();

        // Active subscriptions: status=active AND expires >= today.
        $active_subs = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} um1
             INNER JOIN {$wpdb->usermeta} um2 ON um1.user_id = um2.user_id
             WHERE um1.meta_key = %s AND um1.meta_value = %s
             AND um2.meta_key = %s AND um2.meta_value >= %s",
            'ovr_subscription_status', 'active',
            'ovr_subscription_expires', current_time( 'Y-m-d' )
        ) );

        $managers = $users_data['avail_roles']['ovr_landlord'] ?? 0;

        // Pending approvals: users without ovr_account_type meta key.
        $pending = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
             WHERE um.user_id IS NULL",
            'ovr_account_type'
        ) );

        return [
            'total_users'       => (int) ( $users_data['total_users'] ?? 0 ),
            'active_subs'       => max( 0, (int) $active_subs ),
            'property_managers' => (int) $managers,
            'pending_approvals' => (int) $pending,
        ];
    }

    public function handle_export_csv(): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_export_users_csv' );

        $args = [
            'fields'  => 'all',
            'number'  => 0,  // All users
            'orderby' => 'user_registered',
            'order'   => 'DESC',
        ];

        $search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
        if ( $search ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
        }

        $users = get_users( $args );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=ovr-users-export-' . current_time( 'Y-m-d' ) . '.csv' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, [ 'ID', 'Status', 'Type', 'Role', 'Username', 'Phone', 'Email', 'Registration Date', 'Subscription', 'Balance' ] );

        foreach ( $users as $user ) {
            $uid = (int) $user->ID;
            fputcsv( $output, [
                $uid,
                get_user_meta( $uid, 'ovr_account_status', true ) ?: 'active',
                get_user_meta( $uid, 'ovr_account_type', true ) ?: 'private_person',
                $user->roles[0] ?? '',
                $user->display_name,
                get_user_meta( $uid, 'ovr_phone', true ),
                $user->user_email,
                $user->user_registered,
                $this->resolve_plan_slug( $uid ),
                (float) get_user_meta( $uid, 'ovr_balance', true ),
            ] );
        }

        fclose( $output );
        exit;
    }
```

- [ ] **Step 5: Add the closing brace and verify**

Make sure the class closing `}` is present at the end of the file.

```bash
php -l src/Admin/UsersAdmin.php
```

- [ ] **Step 6: Verify all methods are balanced (no syntax errors)**

```bash
php -l src/Admin/UsersAdmin.php
```

## Chunk 3: Bulk Action AJAX, Account Type, Registration Hook, Plugin Wiring

- [ ] **Step 1: Add bulk action AJAX handler to UsersAdmin**

Add to `init()` after the CSV export handler:
```php
add_action( 'wp_ajax_ovr_users_bulk_action', [ $this, 'handle_bulk_action' ] );
```

Add the handler method:
```php
    public function handle_bulk_action(): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ovr-core' ) ], 403 );
        }
        check_ajax_referer( 'ovr_filter_table_ovr-core-users', 'nonce' );

        $action = sanitize_key( wp_unslash( $_POST['bulk_action'] ?? '' ) );
        $user_ids = isset( $_POST['user_ids'] ) && is_array( $_POST['user_ids'] )
            ? array_map( 'absint', $_POST['user_ids'] )
            : [];

        if ( empty( $user_ids ) ) {
            wp_send_json_error( [ 'message' => __( 'No users selected.', 'ovr-core' ) ], 400 );
        }

        $allowed = [ 'activate', 'suspend', 'reset_password', 'assign_membership' ];
        if ( ! in_array( $action, $allowed, true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid action.', 'ovr-core' ) ], 400 );
        }

        $updated = 0;
        foreach ( $user_ids as $uid ) {
            switch ( $action ) {
                case 'activate':
                    update_user_meta( $uid, 'ovr_account_status', 'active' );
                    break;
                case 'suspend':
                    update_user_meta( $uid, 'ovr_account_status', 'inactive' );
                    break;
                case 'reset_password':
                    $this->send_password_reset( $uid );
                    break;
                case 'assign_membership':
                    // Future: redirect to bulk assignment page.
                    break;
            }
            do_action( 'ovr_user_admin_action', $uid, $action, null, null, get_current_user_id() );
            $updated++;
        }

        wp_send_json_success( [
            'message' => sprintf( __( '%d user(s) updated.', 'ovr-core' ), $updated ),
        ] );
    }

    private function send_password_reset( int $user_id ): void {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }
        $key = get_password_reset_key( $user );
        if ( is_wp_error( $key ) ) {
            return;
        }
        $message = __( 'Someone has requested a password reset for your account on OVR.', 'ovr-core' ) . "\r\n\r\n";
        $message .= network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ) . "\r\n\r\n";
        $message .= __( 'If you did not request this, please ignore this email.', 'ovr-core' ) . "\r\n";
        wp_mail( $user->user_email, sprintf( __( '[OVR] Password Reset for %s', 'ovr-core' ), $user->display_name ), $message );
    }
```

- [ ] **Step 2: Add bulk action event listener binding**

In `render()` or in a separate JS block, add the bulk action listener. Add this BEFORE `$this->filter_table->render()`?

Actually, the bulk action listener should be in the JS. The `ovr-filter-table.js` already dispatches a `ovr_bulk_action` custom event. We need UsersAdmin to listen for it.

Add to the page after FilterTable::render(). Add at the bottom of `render()` before the closing `</div>`:

```php
        <script>
        (function() {
            document.addEventListener('ovr_bulk_action', function(e) {
                var detail = e.detail;
                if (detail.screenId !== '<?php echo esc_js( self::PAGE_SLUG ); ?>') return;
                var fd = new FormData();
                fd.append('action', 'ovr_users_bulk_action');
                fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'ovr_filter_table_ovr-core-users' ) ); ?>');
                fd.append('bulk_action', detail.action);
                detail.ids.forEach(function(id) { fd.append('user_ids[]', id); });
                fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
                    method: 'POST',
                    body: fd,
                })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    if (resp.success) {
                        location.reload();
                    } else {
                        alert(resp.data.message || 'Error');
                    }
                });
            });
        })();
        </script>
```

But wait, this inline JS won't persist through AJAX refreshes. The `ovr-filter-table.js` already binds `ovr_bulk_action` event on page load. Since the inline script is in the initial HTML, it runs once on page load. After AJAX refresh, the inline script doesn't re-run because it's not returned by the AJAX handler (which only returns rows + pagination).

I should move this to `ovr-filter-table.js` or use a separate JS file that's enqueued. For Phase 1, the simplest approach: add an inline script in `enqueue()` that persists across AJAX.

Actually, looking at `ovr-filter-table.js`, the bulk action listener is already there in `bindBulkActions()`:
```js
var event = new CustomEvent('ovr_bulk_action', {
    detail: { action: select.value, ids: ids, screenId: config.screenId },
});
document.dispatchEvent(event);
```

So the JS dispatches `ovr_bulk_action`, and UsersAdmin needs a listener. The listener can be added in a separate JS file or inline in enqueue().

For simplicity, add the listener inline in `render()`.

- [ ] **Step 3: Add account type meta to RegistrationHandler**

Read `src/Auth/RegistrationHandler.php`, find the user creation point, and add after user is created:
```php
update_user_meta( $user_id, 'ovr_account_type', 'private_person' );
```

This ensures every new user gets the default account type.

- [ ] **Step 4: Add account type field to WordPress user profile**

Add this method to UsersAdmin:
```php
    public function add_profile_fields( \WP_User $user ): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            return;
        }
        $type = get_user_meta( $user->ID, 'ovr_account_type', true ) ?: 'private_person';
        ?>
        <h3><?php esc_html_e( 'OVR Account Information', 'ovr-core' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="ovr_account_type"><?php esc_html_e( 'Account Type', 'ovr-core' ); ?></label></th>
                <td>
                    <select name="ovr_account_type" id="ovr_account_type">
                        <option value="private_person" <?php selected( $type, 'private_person' ); ?>><?php esc_html_e( 'Private Person', 'ovr-core' ); ?></option>
                        <option value="business" <?php selected( $type, 'business' ); ?>><?php esc_html_e( 'Business', 'ovr-core' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Administrative account classification. Does not affect subscription billing.', 'ovr-core' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_profile_fields( int $user_id ): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            return;
        }
        if ( isset( $_POST['ovr_account_type'] ) ) {
            $type = sanitize_key( wp_unslash( $_POST['ovr_account_type'] ) );
            if ( in_array( $type, [ 'private_person', 'business' ], true ) ) {
                update_user_meta( $user_id, 'ovr_account_type', $type );
            }
        }
    }
```

Register in `init()`:
```php
add_action( 'show_user_profile', [ $this, 'add_profile_fields' ] );
add_action( 'edit_user_profile', [ $this, 'add_profile_fields' ] );
add_action( 'personal_options_update', [ $this, 'save_profile_fields' ] );
add_action( 'edit_user_profile_update', [ $this, 'save_profile_fields' ] );
```

- [ ] **Step 5: Update Plugin.php to add bulk action AJAX registration**

Check `src/Plugin.php` — the `UsersAdmin::init()` is already called. The bulk action AJAX endpoint `ovr_users_bulk_action` is registered inside `UsersAdmin::init()`, so no Plugin.php changes needed.

But verify that UsersAdmin is properly imported and booted:
```php
// In boot_admin():
$this->modules['admin_users'] = new UsersAdmin();
// ...
$this->modules['admin_users']->init();
```

This should already be present. No changes needed.

- [ ] **Step 6: Remove old template file**

```bash
rm templates/admin/users.php
```

The old template is replaced entirely by FilterTable::render() + inline render methods.

- [ ] **Step 7: Final syntax check on ALL modified files**

```bash
php -l src/Admin/UsersAdmin.php
```

## Verification

- [ ] **Step 1: php -l on all changed files**

```bash
for f in src/Admin/UsersAdmin.php src/Auth/RegistrationHandler.php; do [ -f "$f" ] && php -l "$f" || echo "SKIP $f"; done && echo "DONE"
```

- [ ] **Step 2: Visit `admin.php?page=ovr-core-users`**
  - Verify page renders with header, toolbar, stats bar, filter bar, table
  - Verify all 10 columns appear in correct order
  - Verify column filters render above each column

- [ ] **Step 3: Test filters**
  - Type in text filter → debounced AJAX updates table
  - Change dropdown filter → instant update
  - Test numeric filter on balance
  - Test date filter with preset
  - Combine multiple filters

- [ ] **Step 4: Test sorting**
  - Click each sortable column header → table sorts
  - Sort direction toggles ASC/DESC

- [ ] **Step 5: Test pagination**
  - Navigate between pages
  - Filters persist across pages

- [ ] **Step 6: Test bulk actions**
  - Select users → choose action → Apply
  - Verify Activate/Suspend works
  - Verify Reset Password sends email

- [ ] **Step 7: Test CSV export**
  - Click Export CSV → file downloads
  - Verify columns match table

- [ ] **Step 8: Test subscription lifecycle**
  - User with active subscription shows plan name
  - User with expired subscription shows "Base Subscriber"
  - Account status stays Active regardless of subscription

- [ ] **Step 9: Test account type**
  - New registration gets `private_person` default
  - Admin profile page shows account type field
  - Changing type saves correctly
