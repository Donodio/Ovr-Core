<?php
/**
 * Users Management — admin list & actions.
 *
 * Adds a "Users" submenu under OVR Properties with a styled table
 * showing all WordPress users, their subscription plan, listing count,
 * account status, and quick action links.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Core\TemplateLoader;
use OVR\Core\Verification;
use OVR\Subscription\Plans;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class UsersAdmin {

    public const PAGE_SLUG  = 'ovr-core-users';
    public const PER_PAGE   = 20;

    /** Admin-only user meta introduced for Mark feedback P6.5. */
    public const META_PRICE_OVERRIDE = 'ovr_subscription_price_override';

    /** Free-text admin notes shown only to admins on the user editor. */
    public const META_ADMIN_NOTES = 'ovr_admin_notes';

    /** HMAC-signed cookie remembering the admin behind a "Log in as user" session. */
    public const SWITCH_COOKIE = 'ovr_switch_back';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_user_toggle_status', [ $this, 'handle_toggle_status' ] );
        // §9: admin impersonation ("Log in as user") + switch-back affordance.
        add_action( 'admin_post_ovr_login_as_user', [ $this, 'handle_login_as' ] );
        add_action( 'admin_post_ovr_switch_back',   [ $this, 'handle_switch_back' ] );
        add_action( 'admin_bar_menu', [ $this, 'switch_back_admin_bar' ], 999 );
        // P6.2: CSV export must run before any admin HTML is sent.
        add_action( 'admin_init', [ $this, 'maybe_export_csv' ] );
        // P6.5: admin-only fields on the user profile editor.
        add_action( 'show_user_profile', [ $this, 'render_profile_fields' ] );
        add_action( 'edit_user_profile', [ $this, 'render_profile_fields' ] );
        add_action( 'personal_options_update', [ $this, 'save_profile_fields' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_profile_fields' ] );
        // Streamline the WordPress user editor: hide default fields the platform
        // does not use (Website, Bio, personal options, application passwords).
        add_action( 'admin_head-user-edit.php', [ $this, 'hide_default_profile_fields' ] );
        add_action( 'admin_head-profile.php',   [ $this, 'hide_default_profile_fields' ] );
    }

    /**
     * Hide WordPress' default profile fields the OVR platform does not use, so
     * the edit-user screen shows only the relevant account + OVR fields.
     */
    public function hide_default_profile_fields(): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            return;
        }
        ?>
        <style>
            /* Personal Options block (visual editor, syntax highlighting, admin
               colour scheme, keyboard shortcuts, toolbar) */
            .user-rich-editing-wrap,
            .user-syntax-highlighting-wrap,
            .user-admin-color-wrap,
            .user-comment-shortcuts-wrap,
            .user-admin-bar-front-wrap,
            .user-language-wrap,
            /* Contact / about */
            .user-url-wrap,
            .user-description-wrap,
            /* Application passwords */
            .application-passwords { display: none !important; }
            /* Drop the now-empty "Personal Options" heading above the name section. */
            #your-profile > h2:first-of-type { display: none; }
        </style>
        <?php
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

    public function render(): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            return;
        }

        $search  = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
        $role    = sanitize_key( wp_unslash( $_GET['role'] ?? '' ) );
        $sub     = sanitize_key( wp_unslash( $_GET['subscription'] ?? '' ) );
        $status  = sanitize_key( wp_unslash( $_GET['status'] ?? '' ) );
        $verif   = sanitize_key( wp_unslash( $_GET['verification'] ?? '' ) );
        $type    = sanitize_key( wp_unslash( $_GET['type'] ?? '' ) );
        $paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $orderby = sanitize_key( wp_unslash( $_GET['orderby'] ?? 'registered' ) );
        $order   = strtoupper( sanitize_key( wp_unslash( $_GET['order'] ?? 'DESC' ) ) );
        if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
            $order = 'DESC';
        }

        $args = [
            'fields'     => 'all',
            'number'     => self::PER_PAGE,
            'paged'      => $paged,
            'orderby'    => $orderby,
            'order'      => $order,
        ];

        if ( $search ) {
            // Partial match across name + email (Phase 11).
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
        }
        // Role (Administrator vs. User) and Account Type (Landlord vs. Subscriber)
        // filters, resolved together into role__in / role__not_in so they combine.
        $role_in     = [];
        $role_not_in = [];
        if ( 'administrator' === $role ) {
            $role_in[] = 'administrator';
        } elseif ( 'user' === $role ) {
            $role_not_in[] = 'administrator';
        }
        if ( 'landlord' === $type ) {
            $role_in[] = 'ovr_landlord';
        } elseif ( 'subscriber' === $type ) {
            $role_not_in[] = 'ovr_landlord';
            $role_not_in[] = 'administrator';
        }
        if ( $role_in ) {
            $args['role__in'] = array_values( array_unique( $role_in ) );
        }
        if ( $role_not_in ) {
            $args['role__not_in'] = array_values( array_unique( $role_not_in ) );
        }

        // Subscription type + account status filters (Phase 11), combinable.
        $meta_query = [];
        if ( $sub ) {
            $meta_query[] = [ 'key' => 'ovr_subscription_plan', 'value' => $sub ];
        }
        // Verification status filter. "Not verified" also matches users with no
        // verification meta yet (the default state).
        if ( 'not_verified' === $verif ) {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => Verification::META_KEY, 'value' => 'not_verified' ],
                [ 'key' => Verification::META_KEY, 'compare' => 'NOT EXISTS' ],
            ];
        } elseif ( $verif ) {
            $meta_query[] = [ 'key' => Verification::META_KEY, 'value' => $verif ];
        }
        // Account status ("active" = explicitly active OR no meta yet) combined
        // with subscription renewal state ("pending renewal" = an expired
        // subscription awaiting renewal). Four selectable states.
        if ( $status ) {
            $acct_active = [
                'relation' => 'OR',
                [ 'key' => 'ovr_account_status', 'value' => 'active' ],
                [ 'key' => 'ovr_account_status', 'compare' => 'NOT EXISTS' ],
            ];
            $acct_inactive = [ 'key' => 'ovr_account_status', 'value' => 'inactive' ];
            $expired       = [ 'key' => \OVR\Subscription\UserSubscription::META_STATUS, 'value' => \OVR\Subscription\UserSubscription::STATUS_EXPIRED ];
            $not_expired   = [
                'relation' => 'OR',
                [ 'key' => \OVR\Subscription\UserSubscription::META_STATUS, 'value' => \OVR\Subscription\UserSubscription::STATUS_EXPIRED, 'compare' => '!=' ],
                [ 'key' => \OVR\Subscription\UserSubscription::META_STATUS, 'compare' => 'NOT EXISTS' ],
            ];

            switch ( $status ) {
                case 'active':
                    $meta_query[] = [ 'relation' => 'AND', $acct_active, $not_expired ];
                    break;
                case 'inactive':
                    $meta_query[] = [ 'relation' => 'AND', $acct_inactive, $not_expired ];
                    break;
                case 'active_pending':
                    $meta_query[] = [ 'relation' => 'AND', $acct_active, $expired ];
                    break;
                case 'inactive_pending':
                    $meta_query[] = [ 'relation' => 'AND', $acct_inactive, $expired ];
                    break;
            }
        }
        if ( $meta_query ) {
            $args['meta_query'] = $meta_query;
        }

        $user_query = new \WP_User_Query( $args );
        $users      = $user_query->get_results();
        $total      = $user_query->get_total();
        $max_pages  = (int) ceil( $total / self::PER_PAGE );

        $plans = Plans::get_plans();

        TemplateLoader::render( 'admin/users.php', [
            'users'       => $users,
            'plans'       => $plans,
            'stats'       => $this->get_stats(),
            'search'      => $search,
            'role'        => $role,
            'subscription'=> $sub,
            'status'      => $status,
            'verification'=> $verif,
            'type'        => $type,
            'paged'       => $paged,
            'max_pages'   => $max_pages,
            'total'       => $total,
            'orderby'     => $orderby,
            'order'       => $order,
            'page_url'    => $this->page_url(),
            'base_url'    => $this->base_url(),
            'notice'      => $this->read_notice(),
            'toggle_url'  => admin_url( 'admin-post.php' ),
            'csv_url'     => add_query_arg( 'export_csv', '1', $this->page_url() ),
        ] );
    }

    /**
     * Compute quick stats for the stat cards.
     *
     * @return array<string, int|string>
     */
    private function get_stats(): array {
        global $wpdb;

        $users_data = count_users();

        $active = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
                'ovr_account_status',
                'active'
            )
        );

        $managers = $users_data['avail_roles']['ovr_landlord'] ?? 0;

        $total_users = (int) ( $users_data['total_users'] ?? 0 );

        // "Not Yet Verified" — users who have not been marked as a verified
        // homeowner or registered PM (i.e. total minus the positively verified).
        $verified = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value IN ( %s, %s )",
                Verification::META_KEY,
                Verification::VERIFIED_HOMEOWNER,
                Verification::REGISTERED_PM
            )
        );
        $not_verified = max( 0, $total_users - $verified );

        return [
            'total_users'    => $total_users,
            'active_subs'    => max( 0, (int) $active ),
            'property_managers' => (int) $managers,
            'not_verified'   => $not_verified,
        ];
    }

    /**
     * P6.2: export all users to CSV. Runs on admin_init (before any HTML) so the
     * download headers are valid. Columns: User ID, Name, Email, Phone,
     * Subscription, Balance, Registration Date.
     */
    public function maybe_export_csv(): void {
        if ( empty( $_GET['export_csv'] ) || ( $_GET['page'] ?? '' ) !== self::PAGE_SLUG ) {
            return;
        }
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            return;
        }

        $users = get_users( [
            'number'  => -1,
            'orderby' => 'registered',
            'order'   => 'DESC',
            'fields'  => 'all',
        ] );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="ovr-users-' . gmdate( 'Y-m-d' ) . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        // UTF-8 BOM so Excel reads accented names/emails correctly.
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, [
            __( 'User ID', 'ovr-core' ),
            __( 'Name', 'ovr-core' ),
            __( 'Email', 'ovr-core' ),
            __( 'Phone', 'ovr-core' ),
            __( 'Subscription', 'ovr-core' ),
            __( 'Balance', 'ovr-core' ),
            __( 'Registration Date', 'ovr-core' ),
        ] );

        foreach ( $users as $u ) {
            $plan      = \OVR\Subscription\UserSubscription::get_plan( (int) $u->ID );
            $plan_name = $plan['name'] ?? \OVR\Subscription\UserSubscription::get_plan_slug( (int) $u->ID );
            $phone     = (string) get_user_meta( (int) $u->ID, 'ovr_phone', true );
            $balance   = (float) get_user_meta( (int) $u->ID, \OVR\Payment\Wallet::META_BALANCE, true );

            fputcsv( $out, self::csv_safe_row( [
                (int) $u->ID,
                $u->display_name,
                $u->user_email,
                $phone,
                $plan_name,
                number_format( $balance, 2, '.', '' ),
                $u->user_registered,
            ] ) );
        }
        fclose( $out );
        exit;
    }

    /**
     * Neutralise CSV/formula injection: a cell whose first character is one that
     * a spreadsheet treats as a formula (= + - @, tab, CR) is prefixed with an
     * apostrophe so Excel/Sheets render it as literal text. fputcsv already
     * handles comma/quote/newline quoting.
     *
     * @param array<int, int|string> $row
     * @return array<int, int|string>
     */
    private static function csv_safe_row( array $row ): array {
        return array_map( static function ( $v ) {
            $s = (string) $v;
            return ( '' !== $s && strpbrk( $s[0], "=+-@\t\r" ) !== false ) ? "'" . $s : $v;
        }, $row );
    }

    /**
     * P6.5: admin-only fields on the WordPress user profile editor —
     * current subscription, a renewal price override (for legacy customers),
     * phone number, and account balance.
     */
    public function render_profile_fields( \WP_User $user ): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            return;
        }
        $plan      = \OVR\Subscription\UserSubscription::get_plan( (int) $user->ID );
        $plan_slug = \OVR\Subscription\UserSubscription::get_plan_slug( (int) $user->ID );
        $plan_name = $plan['name'] ?? $plan_slug;
        $expires   = (string) get_user_meta( (int) $user->ID, \OVR\Subscription\UserSubscription::META_EXPIRES, true );
        $phone     = (string) get_user_meta( (int) $user->ID, 'ovr_phone', true );
        $balance   = (string) get_user_meta( (int) $user->ID, \OVR\Payment\Wallet::META_BALANCE, true );
        $override  = (string) get_user_meta( (int) $user->ID, self::META_PRICE_OVERRIDE, true );

        // Plan options (excluding the internal Base Subscriber fallback — P4).
        $plans = \OVR\Subscription\Plans::get_plans();
        wp_nonce_field( 'ovr_user_profile_fields', 'ovr_user_profile_nonce' );
        ?>
        <h2><?php esc_html_e( 'OVR Subscription & Account', 'ovr-core' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="ovr_subscription_plan"><?php esc_html_e( 'Current Subscription', 'ovr-core' ); ?></label></th>
                <td>
                    <select name="ovr_subscription_plan" id="ovr_subscription_plan">
                        <?php
                        // A member who has never subscribed has an EMPTY plan slug, so no
                        // <option> matched and the browser silently selected the first paid
                        // plan in the list. Saving any unrelated field (a phone number) then
                        // posted that plan — which now activates a real subscription via
                        // SubscriptionManager::activate(). This explicit non-paid option is
                        // rendered first and selected for both the "never subscribed" and
                        // "expired" states, so the default submission stays inert.
                        $no_paid_plan = ( '' === $plan_slug || 'base_subscriber' === $plan_slug );
                        ?>
                        <option value="base_subscriber" <?php selected( $no_paid_plan ); ?>>
                            <?php
                            echo '' === $plan_slug
                                ? esc_html__( 'No subscription', 'ovr-core' )
                                : esc_html__( 'Expired (Base Subscriber)', 'ovr-core' );
                            ?>
                        </option>
                        <?php foreach ( (array) $plans as $slug => $p ) :
                            if ( 'base_subscriber' === $slug ) { continue; } // internal fallback, never selectable (P4)
                            $label = is_array( $p ) ? ( $p['name'] ?? $slug ) : (string) $p;
                        ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $plan_slug, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: 1: current plan name, 2: expiry date */
                            esc_html__( 'Active plan: %1$s. Expires: %2$s', 'ovr-core' ),
                            '<strong>' . esc_html( $plan_name ) . '</strong>',
                            $expires ? esc_html( mysql2date( get_option( 'date_format' ), $expires ) ) : esc_html__( 'n/a', 'ovr-core' )
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="ovr_subscription_price_override"><?php esc_html_e( 'Subscription Price Override', 'ovr-core' ); ?></label></th>
                <td>
                    <span class="ovr-money-group">
                        <span class="ovr-money-prefix">$</span>
                        <input type="number" step="0.01" min="0" name="ovr_subscription_price_override" id="ovr_subscription_price_override" class="ovr-money-input" value="<?php echo esc_attr( $override ); ?>" placeholder="<?php esc_attr_e( 'Public price', 'ovr-core' ); ?>">
                    </span>
                    <p class="description"><?php esc_html_e( 'Optional. Charge this renewal price instead of the current public price (for legacy customers). Leave blank to use the standard plan price.', 'ovr-core' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ovr_phone"><?php esc_html_e( 'Phone Number', 'ovr-core' ); ?></label></th>
                <td>
                    <input type="text" name="ovr_phone" id="ovr_phone" class="regular-text" value="<?php echo esc_attr( $phone ); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="ovr_balance"><?php esc_html_e( 'Account Balance', 'ovr-core' ); ?></label></th>
                <td>
                    <span class="ovr-money-group">
                        <span class="ovr-money-prefix">$</span>
                        <input type="number" step="0.01" name="ovr_balance" id="ovr_balance" class="ovr-money-input" value="<?php echo esc_attr( '' !== $balance ? $balance : '0' ); ?>">
                    </span>
                    <p class="description"><?php esc_html_e( 'Manual credit balance. Used for adjustments and credits applied at renewal.', 'ovr-core' ); ?></p>
                </td>
            </tr>
            <?php $verif = \OVR\Core\Verification::get( (int) $user->ID ); ?>
            <tr>
                <th><label for="ovr_verification_status"><?php esc_html_e( 'Verification Status', 'ovr-core' ); ?></label></th>
                <td>
                    <select name="ovr_verification_status" id="ovr_verification_status">
                        <?php foreach ( \OVR\Core\Verification::statuses() as $vkey => $vlabel ) : ?>
                            <option value="<?php echo esc_attr( $vkey ); ?>" <?php selected( $verif, $vkey ); ?>><?php echo esc_html( $vlabel ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Shown as a trust badge on every listing this user owns. Combats fraudulent listings.', 'ovr-core' ); ?></p>
                </td>
            </tr>
            <?php $admin_notes = (string) get_user_meta( (int) $user->ID, self::META_ADMIN_NOTES, true ); ?>
            <tr>
                <th><label for="ovr_admin_notes"><?php esc_html_e( 'Admin Notes', 'ovr-core' ); ?></label></th>
                <td>
                    <textarea name="ovr_admin_notes" id="ovr_admin_notes" class="large-text" rows="4"><?php echo esc_textarea( $admin_notes ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Internal notes about this account. Visible to administrators only — never shown to the user or on the site.', 'ovr-core' ); ?></p>
                </td>
            </tr>
        </table>
        <style>
            .ovr-money-group{display:inline-flex;align-items:stretch;border:1px solid #8c8f94;border-radius:4px;overflow:hidden;background:#fff;max-width:220px}
            .ovr-money-group:focus-within{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
            .ovr-money-prefix{display:inline-flex;align-items:center;padding:0 10px;background:#f0f0f1;color:#50575e;font-weight:600;border-right:1px solid #dcdcde}
            .ovr-money-input{border:none!important;box-shadow:none!important;outline:none!important;padding:6px 10px;width:150px;margin:0!important}
            .ovr-money-input:focus{box-shadow:none!important}
        </style>
        <?php
    }

    /**
     * P6.5: persist the admin-only profile fields.
     */
    public function save_profile_fields( int $user_id ): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            return;
        }
        if ( ! isset( $_POST['ovr_user_profile_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_user_profile_nonce'] ) ), 'ovr_user_profile_fields' ) ) {
            return;
        }

        if ( isset( $_POST['ovr_subscription_plan'] ) ) {
            $slug = sanitize_key( wp_unslash( $_POST['ovr_subscription_plan'] ) );
            if ( '' !== $slug ) {
                // Writing META_PLAN alone left status='none' and no expiry, so
                // is_active() — and therefore has_listing_access() — stayed false:
                // an admin could assign a paid plan and the member still got
                // "subscription" as their block reason. Route through the canonical
                // activation path instead, which sets status/start/expiry, grants the
                // landlord role and restores pending_renewal listings.
                $current = \OVR\Subscription\UserSubscription::get_plan_slug( $user_id );
                $is_paid = \OVR\Subscription\UserSubscription::is_paid_plan( $slug );
                $active  = \OVR\Subscription\UserSubscription::is_active( $user_id );

                if ( $is_paid && ( $slug !== $current || ! $active ) ) {
                    // Only when the plan actually changes or the member is not
                    // currently active — re-saving an unrelated field (phone,
                    // balance) must not silently extend an existing expiry date.
                    \OVR\Subscription\SubscriptionManager::activate( $user_id, $slug );
                } else {
                    update_user_meta( $user_id, \OVR\Subscription\UserSubscription::META_PLAN, $slug );
                }
            }
        }

        if ( isset( $_POST['ovr_subscription_price_override'] ) ) {
            $raw = trim( (string) wp_unslash( $_POST['ovr_subscription_price_override'] ) );
            if ( '' === $raw ) {
                delete_user_meta( $user_id, self::META_PRICE_OVERRIDE );
            } else {
                update_user_meta( $user_id, self::META_PRICE_OVERRIDE, number_format( (float) $raw, 2, '.', '' ) );
            }
        }

        if ( isset( $_POST['ovr_phone'] ) ) {
            update_user_meta( $user_id, 'ovr_phone', sanitize_text_field( wp_unslash( $_POST['ovr_phone'] ) ) );
        }

        if ( isset( $_POST['ovr_balance'] ) ) {
            $bal = (float) wp_unslash( $_POST['ovr_balance'] );
            update_user_meta( $user_id, \OVR\Payment\Wallet::META_BALANCE, number_format( $bal, 2, '.', '' ) );
        }

        if ( isset( $_POST['ovr_admin_notes'] ) ) {
            $notes = sanitize_textarea_field( wp_unslash( $_POST['ovr_admin_notes'] ) );
            if ( '' === $notes ) {
                delete_user_meta( $user_id, self::META_ADMIN_NOTES );
            } else {
                update_user_meta( $user_id, self::META_ADMIN_NOTES, $notes );
            }
        }

        // Verification classification (P8 §9).
        if ( isset( $_POST['ovr_verification_status'] ) ) {
            $vs = sanitize_key( wp_unslash( $_POST['ovr_verification_status'] ) );
            if ( isset( \OVR\Core\Verification::statuses()[ $vs ] ) ) {
                update_user_meta( $user_id, \OVR\Core\Verification::META_KEY, $vs );
            }
        }
    }

    /**
     * Toggle a user's account status between active and inactive.
     */
    public function handle_toggle_status(): void {
        if ( ! current_user_can( 'ovr_manage_users' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_user_toggle_status' );

        $user_id = (int) ( $_GET['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_safe_redirect( $this->page_url() );
            exit;
        }

        $current = get_user_meta( $user_id, 'ovr_account_status', true );
        $new     = 'inactive' === $current ? 'active' : 'inactive';
        update_user_meta( $user_id, 'ovr_account_status', $new );

        wp_safe_redirect( $this->page_url() . '&msg=status_updated' );
        exit;
    }

    /**
     * §9: Log in as another user. Only full administrators may impersonate; the
     * original admin id is stored in an HMAC-signed cookie so they can switch
     * back from the admin bar. The action is recorded to the audit log.
     */
    public function handle_login_as(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do this.', 'ovr-core' ) );
        }
        check_admin_referer( 'ovr_login_as_user' );

        $target = (int) ( $_GET['user_id'] ?? 0 );
        $user   = $target ? get_userdata( $target ) : null;
        if ( ! $user ) {
            wp_die( esc_html__( 'User not found.', 'ovr-core' ) );
        }
        $orig = get_current_user_id();
        if ( $target === $orig ) {
            wp_safe_redirect( $this->page_url() );
            exit;
        }

        \OVR\Core\AuditLog::record( 'admin.login_as', 'user', $target, [ 'from' => $orig ] );

        $this->set_switch_cookie( $orig );
        wp_clear_auth_cookie();
        wp_set_current_user( $target );
        wp_set_auth_cookie( $target );

        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    /**
     * §9: return to the original admin account after a "Log in as user" session.
     */
    public function handle_switch_back(): void {
        check_admin_referer( 'ovr_switch_back' );
        $orig = $this->read_switch_cookie();
        $this->clear_switch_cookie();

        if ( $orig && get_userdata( $orig ) ) {
            wp_clear_auth_cookie();
            wp_set_current_user( $orig );
            wp_set_auth_cookie( $orig );
            wp_safe_redirect( $this->page_url() );
            exit;
        }
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    /**
     * §9: admin-bar node offering a way back to the original admin account while
     * impersonating a user.
     */
    public function switch_back_admin_bar( $bar ): void {
        $orig = $this->read_switch_cookie();
        if ( ! $orig ) {
            return;
        }
        $orig_user = get_userdata( $orig );
        $bar->add_node( [
            'id'    => 'ovr-switch-back',
            'title' => sprintf(
                /* translators: %s: admin display name */
                __( '↩ Back to %s', 'ovr-core' ),
                $orig_user ? $orig_user->display_name : __( 'admin', 'ovr-core' )
            ),
            'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=ovr_switch_back' ), 'ovr_switch_back' ),
            'meta'  => [ 'title' => __( 'Return to your administrator account', 'ovr-core' ) ],
        ] );
    }

    private function set_switch_cookie( int $orig ): void {
        $value = $orig . '|' . hash_hmac( 'sha256', (string) $orig, wp_salt( 'auth' ) );
        setcookie(
            self::SWITCH_COOKIE,
            $value,
            0,
            defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
            defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
            is_ssl(),
            true
        );
        $_COOKIE[ self::SWITCH_COOKIE ] = $value;
    }

    private function read_switch_cookie(): int {
        $raw = isset( $_COOKIE[ self::SWITCH_COOKIE ] ) ? (string) wp_unslash( $_COOKIE[ self::SWITCH_COOKIE ] ) : '';
        if ( '' === $raw || false === strpos( $raw, '|' ) ) {
            return 0;
        }
        [ $orig, $sig ] = explode( '|', $raw, 2 );
        $expected = hash_hmac( 'sha256', (string) (int) $orig, wp_salt( 'auth' ) );
        if ( ! hash_equals( $expected, (string) $sig ) ) {
            return 0;
        }
        return (int) $orig;
    }

    private function clear_switch_cookie(): void {
        setcookie(
            self::SWITCH_COOKIE,
            '',
            time() - 3600,
            defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
            defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
            is_ssl(),
            true
        );
        unset( $_COOKIE[ self::SWITCH_COOKIE ] );
    }

    private function read_notice(): ?array {
        if ( empty( $_GET['msg'] ) ) {
            return null;
        }
        switch ( sanitize_key( wp_unslash( $_GET['msg'] ) ) ) {
            case 'status_updated':
                return [
                    'type' => 'success',
                    'text' => __( 'User status updated.', 'ovr-core' ),
                ];
        }
        return null;
    }

    private function base_url(): string {
        return add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
        ], admin_url( 'edit.php' ) );
    }

    private function page_url(): string {
        return ListTable::preserve_url( $this->base_url() );
    }
}
