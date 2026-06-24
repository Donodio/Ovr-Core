<?php
/**
 * Admin Settings page.
 *
 * Adds a "Settings" submenu under the OVR Properties top-level menu.
 * Tabs: General · Email · Payments · Subscriptions
 *
 * Persists into the existing `ovr_settings` option (already seeded with
 * defaults in Activator). Uses the Settings API for sanitization.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Settings {

    public const OPTION = 'ovr_settings';
    public const PAGE_SLUG = 'ovr-core-settings';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_head', [ $this, 'admin_styles' ] );
        add_action( 'admin_post_ovr_save_roles', [ $this, 'handle_save_roles' ] );
        add_action( 'admin_post_ovr_b2_test', [ $this, 'handle_b2_test' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'OVR Settings', 'ovr-core' ),
            __( 'Settings', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function register_settings(): void {
        register_setting( 'ovr_settings_group', self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );
    }

    /**
     * Sanitize the entire settings payload before save.
     */
    public function sanitize( $input ): array {
        $existing = (array) get_option( self::OPTION, [] );
        $input    = (array) $input;

        $clean = $existing;

        // General.
        if ( isset( $input['currency'] ) )            $clean['currency']            = strtoupper( substr( sanitize_text_field( $input['currency'] ), 0, 3 ) );
        if ( isset( $input['currency_symbol'] ) )     $clean['currency_symbol']     = substr( sanitize_text_field( $input['currency_symbol'] ), 0, 4 );
        if ( isset( $input['default_country'] ) )     $clean['default_country']     = strtoupper( substr( sanitize_text_field( $input['default_country'] ), 0, 2 ) );
        if ( isset( $input['listings_per_page'] ) )   $clean['listings_per_page']   = max( 1, (int) $input['listings_per_page'] );

        // Email.
        if ( isset( $input['from_email'] ) )          $clean['from_email']          = sanitize_email( $input['from_email'] );
        if ( isset( $input['from_name'] ) )           $clean['from_name']           = sanitize_text_field( $input['from_name'] );

        // Payments — environment toggles.
        $envs = [ 'stripe_env', 'paypal_env', 'authnet_env' ];
        foreach ( $envs as $ek ) {
            if ( isset( $input[ $ek ] ) ) {
                $clean[ $ek ] = in_array( $input[ $ek ], [ 'sandbox', 'live' ], true ) ? $input[ $ek ] : 'sandbox';
            }
        }

        // Payments — all credential keys (sandbox + live for each gateway).
        $gateway_text_keys = [
            'stripe_sandbox_publishable_key', 'stripe_sandbox_secret_key',
            'stripe_live_publishable_key',    'stripe_live_secret_key',
            'paypal_sandbox_client_id',       'paypal_sandbox_secret',
            'paypal_live_client_id',          'paypal_live_secret',
            'authnet_sandbox_login_id',       'authnet_sandbox_transaction_key',
            'authnet_live_login_id',          'authnet_live_transaction_key',
        ];
        foreach ( $gateway_text_keys as $gk ) {
            if ( isset( $input[ $gk ] ) ) {
                $clean[ $gk ] = sanitize_text_field( $input[ $gk ] );
            }
        }

        // Legacy flat keys (keep for backward compat reads).
        if ( isset( $input['stripe_publishable_key'] ) ) $clean['stripe_publishable_key'] = sanitize_text_field( $input['stripe_publishable_key'] );
        if ( isset( $input['stripe_secret_key'] ) )      $clean['stripe_secret_key']      = sanitize_text_field( $input['stripe_secret_key'] );
        if ( isset( $input['paypal_client_id'] ) )       $clean['paypal_client_id']       = sanitize_text_field( $input['paypal_client_id'] );
        if ( isset( $input['paypal_secret'] ) )          $clean['paypal_secret']          = sanitize_text_field( $input['paypal_secret'] );
        if ( isset( $input['authnet_login_id'] ) )       $clean['authnet_login_id']       = sanitize_text_field( $input['authnet_login_id'] );
        if ( isset( $input['authnet_transaction_key'] ) ) $clean['authnet_transaction_key'] = sanitize_text_field( $input['authnet_transaction_key'] );

        // Subscriptions.
        if ( isset( $input['grace_period_days'] ) )   $clean['grace_period_days']   = max( 0, (int) $input['grace_period_days'] );
        if ( isset( $input['inactivity_days'] ) )     $clean['inactivity_days']     = max( 0, (int) $input['inactivity_days'] );
        if ( isset( $input['inquiry_retention'] ) )   $clean['inquiry_retention']   = max( 30, (int) $input['inquiry_retention'] );
        $clean['enable_reviews']    = ! empty( $input['enable_reviews'] );
        $clean['review_approval']   = ! empty( $input['review_approval'] );
        $clean['enable_inquiries']  = ! empty( $input['enable_inquiries'] );
        $clean['enable_ical_sync']  = ! empty( $input['enable_ical_sync'] );

        // Listings — bump limit + soft-delete retention (Features F & G).
        if ( isset( $input['bump_daily_limit'] ) )       $clean['bump_daily_limit']       = max( 1, (int) $input['bump_daily_limit'] );
        if ( isset( $input['listing_retention_days'] ) ) $clean['listing_retention_days'] = max( 1, (int) $input['listing_retention_days'] );

        // General (M3 F5) — branding + locale.
        if ( isset( $input['site_name'] ) )      $clean['site_name']      = sanitize_text_field( $input['site_name'] );
        if ( isset( $input['support_email'] ) )  $clean['support_email']  = sanitize_email( $input['support_email'] );
        if ( isset( $input['business_phone'] ) ) $clean['business_phone'] = sanitize_text_field( $input['business_phone'] );
        if ( isset( $input['logo_url'] ) )       $clean['logo_url']       = esc_url_raw( $input['logo_url'] );
        if ( isset( $input['favicon_url'] ) )    $clean['favicon_url']    = esc_url_raw( $input['favicon_url'] );
        if ( isset( $input['timezone_string'] ) ) $clean['timezone_string'] = sanitize_text_field( $input['timezone_string'] );
        if ( isset( $input['date_format'] ) )    $clean['date_format']    = sanitize_text_field( $input['date_format'] );

        // Listing caps (M3 F5).
        foreach ( [ 'max_listings', 'max_photos', 'max_videos', 'max_documents' ] as $ck ) {
            if ( isset( $input[ $ck ] ) ) $clean[ $ck ] = max( 0, (int) $input[ $ck ] );
        }
        if ( isset( $input['default_listing_status'] ) ) {
            $clean['default_listing_status'] = in_array( $input['default_listing_status'], [ 'active', 'inactive' ], true ) ? $input['default_listing_status'] : 'active';
        }

        // Homepage featured rail ordering (M3 F9).
        if ( isset( $input['homepage_featured_mode'] ) ) {
            $clean['homepage_featured_mode'] = in_array( $input['homepage_featured_mode'], [ 'auto', 'manual' ], true ) ? $input['homepage_featured_mode'] : 'auto';
        }
        if ( isset( $input['homepage_featured_ids'] ) ) {
            $ids = array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', (string) $input['homepage_featured_ids'] ) ) ) );
            $clean['homepage_featured_ids'] = implode( ',', $ids );
        }

        // Subscription default (M3 F5).
        if ( isset( $input['default_membership'] ) ) $clean['default_membership'] = sanitize_text_field( $input['default_membership'] );

        // Media (M3 F5).
        if ( isset( $input['image_quality'] ) )    $clean['image_quality']    = max( 10, min( 100, (int) $input['image_quality'] ) );
        $clean['enable_watermark'] = ! empty( $input['enable_watermark'] );
        if ( isset( $input['watermark_position'] ) ) {
            $allowed = [ 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'center' ];
            $clean['watermark_position'] = in_array( $input['watermark_position'], $allowed, true ) ? $input['watermark_position'] : 'bottom-right';
        }
        if ( isset( $input['watermark_opacity'] ) ) $clean['watermark_opacity'] = max( 0, min( 100, (int) $input['watermark_opacity'] ) );

        // Security (M3 F5).
        if ( isset( $input['password_min_length'] ) ) $clean['password_min_length'] = max( 6, min( 64, (int) $input['password_min_length'] ) );
        $clean['password_require_mixed'] = ! empty( $input['password_require_mixed'] );
        if ( isset( $input['session_timeout_hours'] ) ) $clean['session_timeout_hours'] = max( 0, (int) $input['session_timeout_hours'] );
        if ( isset( $input['login_attempt_limit'] ) )   $clean['login_attempt_limit']   = max( 0, (int) $input['login_attempt_limit'] );
        if ( isset( $input['login_lockout_minutes'] ) ) $clean['login_lockout_minutes'] = max( 1, (int) $input['login_lockout_minutes'] );
        $clean['enable_2fa'] = ! empty( $input['enable_2fa'] );

        // Storage — Backblaze B2 (Feature E).
        $clean['b2_enabled']      = ! empty( $input['b2_enabled'] );
        $clean['b2_delete_local'] = ! empty( $input['b2_delete_local'] );
        foreach ( [ 'b2_bucket_name', 'b2_key_id', 'b2_app_key', 'b2_region', 'b2_account_id' ] as $bk ) {
            if ( isset( $input[ $bk ] ) ) {
                $clean[ $bk ] = sanitize_text_field( $input[ $bk ] );
            }
        }
        // Credentials changed — drop the cached B2 auth token.
        delete_transient( 'ovr_b2_auth' );

        // Reputation / testimonials gate.
        if ( isset( $input['min_display_rating'] ) ) {
            $clean['min_display_rating'] = max( 1, min( 5, (int) $input['min_display_rating'] ) );
        }

        // CRM.
        if ( isset( $input['crm_high_value_threshold'] ) ) {
            $clean['crm_high_value_threshold'] = max( 0, (float) $input['crm_high_value_threshold'] );
        }

        // Compliance — legal text blocks (allow basic HTML).
        foreach ( [ 'terms_text', 'privacy_text', 'gdpr_text', 'cookie_text', 'legal_text' ] as $ck ) {
            if ( isset( $input[ $ck ] ) ) {
                $clean[ $ck ] = wp_kses_post( (string) $input[ $ck ] );
            }
        }
        $clean['gdpr_enabled']   = ! empty( $input['gdpr_enabled'] );
        $clean['cookie_banner']  = ! empty( $input['cookie_banner'] );

        // Documentation — internal help / training content.
        if ( isset( $input['documentation'] ) ) {
            $clean['documentation'] = wp_kses_post( (string) $input['documentation'] );
        }

        // Billing — tax + invoice settings (gateways live under Payments).
        $clean['tax_enabled'] = ! empty( $input['tax_enabled'] );
        if ( isset( $input['tax_rate'] ) )    $clean['tax_rate']    = max( 0, min( 100, (float) $input['tax_rate'] ) );
        if ( isset( $input['tax_label'] ) )   $clean['tax_label']   = sanitize_text_field( $input['tax_label'] );
        if ( isset( $input['invoice_prefix'] ) )  $clean['invoice_prefix']  = substr( sanitize_text_field( $input['invoice_prefix'] ), 0, 12 );
        if ( isset( $input['company_name'] ) )    $clean['company_name']    = sanitize_text_field( $input['company_name'] );
        if ( isset( $input['company_address'] ) ) $clean['company_address'] = sanitize_textarea_field( $input['company_address'] );
        if ( isset( $input['invoice_footer'] ) )  $clean['invoice_footer']  = sanitize_textarea_field( $input['invoice_footer'] );

        // Fleet Management — placeholder module scaffolding.
        $clean['fleet_enabled'] = ! empty( $input['fleet_enabled'] );
        if ( isset( $input['fleet_notes'] ) ) {
            $clean['fleet_notes'] = sanitize_textarea_field( $input['fleet_notes'] );
        }

        // WordPress Integration (booking import + webhook).
        if ( isset( $input['wp_sync_url'] ) )  $clean['wp_sync_url']  = esc_url_raw( trim( (string) $input['wp_sync_url'] ) );
        if ( isset( $input['wp_sync_user'] ) ) $clean['wp_sync_user'] = sanitize_text_field( $input['wp_sync_user'] );
        if ( isset( $input['wp_sync_pass'] ) ) $clean['wp_sync_pass'] = sanitize_text_field( $input['wp_sync_pass'] );
        if ( isset( $input['wp_sync_schedule'] ) ) {
            $allowed = [ 'manual', 'hourly', 'twicedaily', 'daily' ];
            $sched   = sanitize_key( $input['wp_sync_schedule'] );
            $clean['wp_sync_schedule'] = in_array( $sched, $allowed, true ) ? $sched : 'manual';
        }
        $clean['wp_sync_enabled'] = ! empty( $input['wp_sync_enabled'] );

        // Re-sync the WordPress import cron whenever the schedule/toggle change.
        if ( class_exists( '\OVR\Sync\WordPressSync' ) ) {
            self::reschedule_wp_sync( $clean['wp_sync_enabled'], $clean['wp_sync_schedule'] ?? 'manual' );
        }

        return $clean;
    }

    /**
     * Align the WordPress-sync cron event with the saved schedule. Clears the
     * event when disabled or set to manual.
     */
    private static function reschedule_wp_sync( bool $enabled, string $schedule ): void {
        $hook = \OVR\Sync\WordPressSync::CRON_HOOK;
        $next = wp_next_scheduled( $hook );
        if ( $next ) {
            wp_unschedule_event( $next, $hook );
        }
        if ( $enabled && 'manual' !== $schedule ) {
            wp_schedule_event( time() + 300, $schedule, $hook );
        }
    }

    /**
     * Render the settings page (tabbed).
     */
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $settings = (array) get_option( self::OPTION, [] );
        $tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $tabs     = [
            'general'       => __( 'General',         'ovr-core' ),
            'compliance'    => __( 'Compliance',      'ovr-core' ),
            'documentation' => __( 'Documentation',   'ovr-core' ),
            'email'         => __( 'Email',           'ovr-core' ),
            'payments'      => __( 'Payments',        'ovr-core' ),
            'billing'       => __( 'Billing',         'ovr-core' ),
            'subscriptions' => __( 'Subscriptions',   'ovr-core' ),
            'listings'      => __( 'Listings',        'ovr-core' ),
            'media'         => __( 'Media',           'ovr-core' ),
            'security'      => __( 'Security',        'ovr-core' ),
            'storage'       => __( 'Storage',         'ovr-core' ),
            'reputation'    => __( 'Reputation',      'ovr-core' ),
            'roles'         => __( 'User Roles',      'ovr-core' ),
            'fleet'         => __( 'Fleet Management', 'ovr-core' ),
            'integration'   => __( 'WordPress Sync',  'ovr-core' ),
        ];
        if ( ! isset( $tabs[ $tab ] ) ) $tab = 'general';

        // The User Roles matrix manages roles (not the ovr_settings option),
        // so it renders its own self-contained form and returns early.
        if ( 'roles' === $tab ) {
            $this->render_roles_page( $tabs, $tab );
            return;
        }
        ?>
            <div class="wrap ovr-settings">
            <div class="ovr-settings-head">
                <div style="display:flex;align-items:center;gap:16px">
                    <span style="display:flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:var(--radius-md);background:var(--p-light);color:var(--p);flex-shrink:0">
                        <span class="material-symbols-outlined" style="font-size:28px">tune</span>
                    </span>
                    <div>
                        <h1><?php esc_html_e( 'OVR Settings', 'ovr-core' ); ?></h1>
                        <p><?php esc_html_e( 'Currency, email, payment gateways, and subscription lifecycle.', 'ovr-core' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="ovr-settings-layout">
            <nav class="ovr-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'ovr-core' ); ?>">
                <?php
                $tab_icons = [
                    'general'       => 'settings',
                    'compliance'    => 'gavel',
                    'documentation' => 'menu_book',
                    'email'         => 'mail',
                    'payments'      => 'payments',
                    'billing'       => 'receipt_long',
                    'subscriptions' => 'subscriptions',
                    'listings'      => 'home_work',
                    'media'         => 'image',
                    'security'      => 'lock',
                    'storage'       => 'cloud',
                    'reputation'    => 'star',
                    'roles'         => 'admin_panel_settings',
                    'fleet'         => 'dashboard',
                    'integration'   => 'sync',
                ];
                foreach ( $tabs as $key => $label ) :
                    $url = add_query_arg( [
                        'post_type' => 'ovr_property',
                        'page'      => self::PAGE_SLUG,
                        'tab'       => $key,
                    ], admin_url( 'edit.php' ) );
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $tab === $key ? 'ovr-settings-tab--active' : ''; ?>">
                        <span class="material-symbols-outlined"><?php echo esc_attr( $tab_icons[ $key ] ?? 'tune' ); ?></span>
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
                <?php settings_fields( 'ovr_settings_group' ); ?>
                <input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[__active_tab]" value="<?php echo esc_attr( $tab ); ?>">

                <div class="ovr-settings-card">
                    <div class="ovr-settings-card-body" style="padding:0">
                        <table class="ovr-settings-table">
                            <?php
                            switch ( $tab ) {
                                case 'general':       $this->render_general( $settings );       break;
                                case 'compliance':    $this->render_compliance( $settings );    break;
                                case 'documentation': $this->render_documentation( $settings ); break;
                                case 'email':         $this->render_email( $settings );         break;
                                case 'payments':      $this->render_payments( $settings );      break;
                                case 'billing':       $this->render_billing( $settings );       break;
                                case 'subscriptions': $this->render_subscriptions( $settings ); break;
                                case 'listings':      $this->render_listings( $settings );      break;
                                case 'media':         $this->render_media( $settings );         break;
                                case 'security':      $this->render_security( $settings );      break;
                                case 'storage':       $this->render_storage( $settings );       break;
                                case 'reputation':    $this->render_reputation( $settings );    break;
                                case 'fleet':         $this->render_fleet( $settings );         break;
                                case 'integration':   $this->render_integration( $settings );   break;
                            }
                            ?>
                        </table>
                    </div>

                    <div class="ovr-settings-submit">
                        <?php submit_button( __( 'Save Changes', 'ovr-core' ), 'primary', 'submit', false, [] ); ?>
                    </div>
                </div>
            </form>
            </div>
        </div>
        <?php
    }

    public function admin_styles(): void {
        $screen = get_current_screen();
        if ( ! $screen || 'ovr_property_page_ovr-core-settings' !== $screen->id ) {
            return;
        }
        ?>
        <style>
            #wpcontent,#wpbody-content{background:#f0f3f7}
            #wpcontent{padding-left:0}
            .ovr-settings{--p:#000961;--p-hover:#000740;--p-light:#e8eaf3;--p-glow:rgba(0,9,97,.12);--navy:#000961;--navy-dark:#000740;--navy-light:#e8eaf3;--blue:#00A2E8;--blue-light:#e5f5fe;--gold:#DEAF0C;--gold-dark:#b8920a;--gold-light:#fef5d6;--green:#2E7D32;--green-light:#e4f4e4;--red:#B3261E;--red-light:#f9e4e2;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#FFFFFF;--bg:#f0f3f7;--shadow-sm:0 1px 3px rgba(0,9,97,.06),0 1px 2px rgba(0,9,97,.04);--shadow-md:0 4px 12px rgba(0,9,97,.08),0 2px 4px rgba(0,9,97,.04);--shadow-lg:0 8px 32px rgba(0,9,97,.1),0 4px 12px rgba(0,9,97,.06);--radius-sm:6px;--radius-md:8px;--radius-lg:12px;--radius-xl:16px;font-family:'OVR Atkinson','Atkinson Hyperlegible Next',system-ui,sans-serif;width:100%;max-width:none;margin:0;padding:24px 28px 48px;color:var(--ink);-webkit-font-smoothing:antialiased}
            .ovr-settings,.ovr-settings *{box-sizing:border-box}
            .ovr-settings .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;line-height:1;vertical-align:middle;font-size:22px}
            .ovr-settings-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:24px}
            .ovr-settings-head h1{font-size:30px;font-weight:700;letter-spacing:-.01em;margin:0;padding:0;line-height:1.2;color:var(--ink)}
            .ovr-settings-head p{margin:6px 0 0;font-size:16px;color:var(--muted)}
            .ovr-settings-layout{display:grid;grid-template-columns:248px minmax(0,1fr);gap:24px;align-items:start}
            .ovr-settings-main{min-width:0}
            .ovr-settings-nav{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-lg);padding:8px;box-shadow:var(--shadow-sm);display:flex;flex-direction:column;gap:2px;position:sticky;top:46px}
            .ovr-settings-nav a{display:flex;align-items:center;gap:12px;padding:0 16px;border-radius:var(--radius-md);font-size:14.5px;font-weight:600;text-decoration:none;color:var(--muted);height:44px;line-height:1;white-space:nowrap}
            .ovr-settings-nav a:active{transform:none}
            .ovr-settings-nav a:hover{color:var(--p);background:var(--p-light)}
            .ovr-settings-nav a.ovr-settings-tab--active{background:var(--p);color:#fff;box-shadow:0 2px 8px var(--p-glow)}
            .ovr-settings-nav a.ovr-settings-tab--active:hover{background:var(--p-hover);color:#fff}
            .ovr-settings-nav a .material-symbols-outlined{font-size:20px;flex-shrink:0}
            .ovr-settings-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-xl);overflow:hidden;box-shadow:var(--shadow-md);margin-bottom:24px;position:relative}
            .ovr-settings-card::before{content:'';position:absolute;top:0;left:0;width:100%;height:4px;background:var(--p);z-index:1}
            .ovr-settings-card-body{padding:24px 28px}
            .ovr-settings-table{width:100%;border-collapse:collapse}
            .ovr-settings-table tr:not(:last-child) td,.ovr-settings-table tr:not(:last-child) th{border-bottom:1px solid var(--gray-border)}
            .ovr-settings-table th{text-align:left;padding:18px 28px;font-size:14px;font-weight:600;color:var(--ink);white-space:nowrap;vertical-align:top;width:240px;background:var(--surf)}
            .ovr-settings-table td{padding:18px 28px;font-size:15px;color:var(--ink);vertical-align:top}
            .ovr-settings-table td .description{font-size:13px;color:var(--gray-mid);margin:5px 0 0;font-style:normal;display:block;line-height:1.5;max-width:480px}
            .ovr-settings input[type=text],.ovr-settings input[type=email],.ovr-settings input[type=number],.ovr-settings input[type=password],.ovr-settings select{font-family:inherit;font-size:15px;color:var(--ink);background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--radius-md);padding:0 14px;outline:none;height:44px;min-height:44px;max-width:400px;width:100%}
            .ovr-settings input[type=text]:focus,.ovr-settings input[type=email]:focus,.ovr-settings input[type=number]:focus,.ovr-settings input[type=password]:focus,.ovr-settings select:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-glow)}
            .ovr-settings input[type=text].small-text,.ovr-settings input[type=number].small-text{max-width:140px}
            .ovr-settings input[type=text].regular-text,.ovr-settings input[type=email].regular-text,.ovr-settings input[type=password].regular-text{max-width:400px}
            .ovr-settings select{appearance:none;-webkit-appearance:none;padding-right:36px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='%235F6B7A' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 11px center;cursor:pointer}
            .ovr-settings .ovr-settings-checkgroup label{display:flex;align-items:center;gap:10px;padding:8px 0;font-size:15px;cursor:pointer}
            .ovr-settings input[type=checkbox]{-webkit-appearance:none;appearance:none;width:20px;height:20px;border:2px solid var(--gray-border);border-radius:5px;background:var(--surf);cursor:pointer;flex-shrink:0;position:relative}
            .ovr-settings input[type=checkbox]:checked{background:var(--p);border-color:var(--p)}
            .ovr-settings input[type=checkbox]:checked::after{content:'';position:absolute;top:2px;left:5px;width:5px;height:10px;border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg)}
            .ovr-settings input[type=checkbox]:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-glow)}
            .ovr-settings input[type=checkbox]:checked:focus{border-color:var(--p)}
            .ovr-settings-submit{padding:20px 28px;border-top:1px solid var(--gray-border);background:var(--bg)}
            .ovr-settings-submit .button-primary{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 28px;border-radius:var(--radius-md);font-size:15px;font-weight:600;font-family:inherit;line-height:1;height:48px;min-height:48px;cursor:pointer;background:var(--gold);color:var(--navy);border:1px solid var(--gold);box-shadow:0 2px 8px rgba(222,175,12,.25);text-decoration:none;letter-spacing:.01em}
            .ovr-settings-submit .button-primary:hover{background:var(--gold-dark);border-color:var(--gold-dark);color:var(--navy);box-shadow:0 4px 16px rgba(222,175,12,.35);transform:translateY(-1px)}
            .ovr-settings-submit .button-primary:active{transform:translateY(0)}
            .ovr-settings-section-title{padding:16px 28px 5px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
            .ovr-settings-env-toggle{display:inline-flex;gap:0;border:1px solid var(--gray-border);border-radius:var(--radius-md);overflow:hidden;font-size:14px;font-weight:600;box-shadow:var(--shadow-sm)}
            .ovr-settings-env-toggle label{padding:8px 18px;cursor:pointer;user-select:none}
            .ovr-settings-env-toggle label:first-child{border-right:1px solid var(--gray-border)}
            .ovr-settings-env-toggle label.ovr-env-active{background:var(--p);color:#fff;border-color:var(--p)}
            .ovr-settings-env-toggle label.ovr-env-active + label{border-color:var(--p)}
            .ovr-settings-env-toggle label:not(.ovr-env-active){background:var(--bg);color:var(--muted)}
            .ovr-settings-env-toggle label:not(.ovr-env-active):hover{background:var(--gray-light);color:var(--ink)}
            .ovr-settings-gateway-header{padding:20px 28px 4px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
            .ovr-settings .updated.notice,.ovr-settings .error.notice,.ovr-settings .notice{display:block;margin:0 0 20px;padding:14px 18px;border-radius:var(--radius-md);font-size:15px}
            .ovr-settings .updated.notice{border-color:var(--p);background:var(--p-light);color:var(--p)}
            .ovr-settings .updated.notice p{margin:0}
            .ovr-settings .notice-warning{border-color:var(--gold);background:var(--gold-light);color:var(--gold-dark)}
            .ovr-settings .notice-error{border-color:var(--red);background:var(--red-light);color:var(--red)}
            @media (max-width:1100px){
                .ovr-settings{padding:20px 18px 36px}
                .ovr-settings-head h1{font-size:26px}
                .ovr-settings-table th{width:180px;padding:14px 20px}
                .ovr-settings-table td{padding:14px 20px}
                .ovr-settings-card-body{padding:20px}
                .ovr-settings-layout{grid-template-columns:1fr;gap:18px}
                .ovr-settings-nav{position:static;top:auto;flex-direction:row;flex-wrap:nowrap;overflow-x:auto;gap:4px}
                .ovr-settings-nav a{flex-shrink:0}
            }
            @media (max-width:782px){
                .ovr-settings-head h1{font-size:24px}
                .ovr-settings-table,.ovr-settings-table tbody,.ovr-settings-table tr,.ovr-settings-table th,.ovr-settings-table td{display:block;width:auto}
                .ovr-settings-table th{width:auto;padding:16px 20px 0;background:transparent}
                .ovr-settings-table td{padding:8px 20px 16px}
                .ovr-settings input[type=text],.ovr-settings input[type=email],.ovr-settings input[type=number],.ovr-settings input[type=password]{max-width:none}
                .ovr-settings-submit{padding:16px 20px}
            }
        </style>
        <?php
    }

    private function render_general( array $s ): void {
        $opt = esc_attr( self::OPTION );
        ?>
        <tr>
            <th><label for="ovr-site-name"><?php esc_html_e( 'Site Name', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-site-name" name="<?php echo $opt; ?>[site_name]" type="text" class="regular-text"
                       value="<?php echo esc_attr( $s['site_name'] ?? get_bloginfo( 'name' ) ); ?>">
                <p class="description"><?php esc_html_e( 'Brand name shown in emails and across the platform.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-support-email"><?php esc_html_e( 'Support Email', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-support-email" name="<?php echo $opt; ?>[support_email]" type="email" class="regular-text"
                       value="<?php echo esc_attr( $s['support_email'] ?? get_option( 'admin_email' ) ); ?>">
                <p class="description"><?php esc_html_e( 'Where admin-bound notifications are sent.', 'ovr-core' ); ?></p></td>
        </tr>
        <tr>
            <th><label for="ovr-phone"><?php esc_html_e( 'Phone Number', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-phone" name="<?php echo $opt; ?>[business_phone]" type="text" class="regular-text"
                       value="<?php echo esc_attr( $s['business_phone'] ?? '' ); ?>"></td>
        </tr>
        <tr>
            <th><label for="ovr-logo"><?php esc_html_e( 'Logo URL', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-logo" name="<?php echo $opt; ?>[logo_url]" type="url" class="regular-text" style="width:480px;max-width:100%"
                       value="<?php echo esc_attr( $s['logo_url'] ?? '' ); ?>" placeholder="https://…/logo.png"></td>
        </tr>
        <tr>
            <th><label for="ovr-favicon"><?php esc_html_e( 'Favicon URL', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-favicon" name="<?php echo $opt; ?>[favicon_url]" type="url" class="regular-text" style="width:480px;max-width:100%"
                       value="<?php echo esc_attr( $s['favicon_url'] ?? '' ); ?>" placeholder="https://…/favicon.ico">
                <p class="description"><?php esc_html_e( 'Output in the site head on front-end pages.', 'ovr-core' ); ?></p></td>
        </tr>
        <tr>
            <th><label for="ovr-tz"><?php esc_html_e( 'Timezone', 'ovr-core' ); ?></label></th>
            <td>
                <select id="ovr-tz" name="<?php echo $opt; ?>[timezone_string]">
                    <option value=""><?php esc_html_e( '— Use WordPress default —', 'ovr-core' ); ?></option>
                    <?php
                    $cur_tz = (string) ( $s['timezone_string'] ?? '' );
                    echo wp_timezone_choice( $cur_tz ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper outputs safe option markup
                    ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-dateformat"><?php esc_html_e( 'Date Format', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-dateformat" name="<?php echo $opt; ?>[date_format]" type="text" class="small-text"
                       value="<?php echo esc_attr( $s['date_format'] ?? get_option( 'date_format' ) ); ?>">
                <p class="description"><?php esc_html_e( 'PHP date format used in platform displays (e.g. F j, Y).', 'ovr-core' ); ?></p></td>
        </tr>
        <tr>
            <th><label for="ovr-currency"><?php esc_html_e( 'Currency Code', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-currency" name="<?php echo esc_attr( self::OPTION ); ?>[currency]" type="text" maxlength="3"
                       value="<?php echo esc_attr( $s['currency'] ?? 'USD' ); ?>" class="small-text">
                <p class="description"><?php esc_html_e( 'ISO-4217 three-letter code (USD, EUR, GBP).', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-symbol"><?php esc_html_e( 'Currency Symbol', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-symbol" name="<?php echo esc_attr( self::OPTION ); ?>[currency_symbol]" type="text" maxlength="4"
                       value="<?php echo esc_attr( $s['currency_symbol'] ?? '$' ); ?>" class="small-text">
            </td>
        </tr>
        <tr>
            <th><label for="ovr-country"><?php esc_html_e( 'Default Country', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-country" name="<?php echo esc_attr( self::OPTION ); ?>[default_country]" type="text" maxlength="2"
                       value="<?php echo esc_attr( $s['default_country'] ?? 'US' ); ?>" class="small-text">
                <p class="description"><?php esc_html_e( 'ISO 3166-1 alpha-2 (US, GB, FR…).', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-lpp"><?php esc_html_e( 'Listings Per Page', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-lpp" name="<?php echo esc_attr( self::OPTION ); ?>[listings_per_page]" type="number" min="1"
                       value="<?php echo esc_attr( (string) ( $s['listings_per_page'] ?? 12 ) ); ?>" class="small-text">
            </td>
        </tr>
        <?php
    }

    private function render_email( array $s ): void {
        ?>
        <tr>
            <th><label for="ovr-from-name"><?php esc_html_e( 'From Name', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-from-name" name="<?php echo esc_attr( self::OPTION ); ?>[from_name]" type="text"
                       value="<?php echo esc_attr( $s['from_name'] ?? get_bloginfo( 'name' ) ); ?>" class="regular-text">
            </td>
        </tr>
        <tr>
            <th><label for="ovr-from-email"><?php esc_html_e( 'From Email', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-from-email" name="<?php echo esc_attr( self::OPTION ); ?>[from_email]" type="email"
                       value="<?php echo esc_attr( $s['from_email'] ?? get_option( 'admin_email' ) ); ?>" class="regular-text">
                <p class="description"><?php esc_html_e( 'Used as the From: address for transactional emails.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <?php
    }

    private function render_gateway_env_toggle( string $gateway, string $label, array $s ): void {
        $env_key = $gateway . '_env';
        $current = $s[ $env_key ] ?? 'sandbox';
        $opt     = esc_attr( self::OPTION );
        ?>
        <tr><td colspan="2" style="padding:0">
            <div class="ovr-settings-gateway-header">
                <h3 style="font-size:17px;font-weight:700;margin:0;color:var(--ink)"><?php echo esc_html( $label ); ?></h3>
                <span class="ovr-settings-env-toggle">
                    <label class="<?php echo 'sandbox' === $current ? 'ovr-env-active' : ''; ?>">
                        <input type="radio" name="<?php echo $opt; ?>[<?php echo esc_attr( $env_key ); ?>]" value="sandbox"
                               <?php checked( $current, 'sandbox' ); ?> style="display:none"
                               onchange="ovrToggleEnv('<?php echo esc_js( $gateway ); ?>','sandbox')">
                        <?php esc_html_e( 'Sandbox', 'ovr-core' ); ?>
                    </label>
                    <label class="<?php echo 'live' === $current ? 'ovr-env-active' : ''; ?>">
                        <input type="radio" name="<?php echo $opt; ?>[<?php echo esc_attr( $env_key ); ?>]" value="live"
                               <?php checked( $current, 'live' ); ?> style="display:none"
                               onchange="ovrToggleEnv('<?php echo esc_js( $gateway ); ?>','live')">
                        <?php esc_html_e( 'Live', 'ovr-core' ); ?>
                    </label>
                </span>
            </div>
        </td></tr>
        <?php
    }

    private function render_payments( array $s ): void {
        $opt = esc_attr( self::OPTION );

        // --- Stripe ---
        $this->render_gateway_env_toggle( 'stripe', 'Stripe', $s );
        $stripe_env = $s['stripe_env'] ?? 'sandbox';
        ?>
        <tr class="ovr-env-row ovr-env-stripe ovr-env-sandbox" <?php if ( 'sandbox' !== $stripe_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Sandbox Publishable Key', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[stripe_sandbox_publishable_key]" type="text" class="regular-text"
                       value="<?php echo esc_attr( $s['stripe_sandbox_publishable_key'] ?? '' ); ?>" placeholder="pk_test_…"></td>
        </tr>
        <tr class="ovr-env-row ovr-env-stripe ovr-env-sandbox" <?php if ( 'sandbox' !== $stripe_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Sandbox Secret Key', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[stripe_sandbox_secret_key]" type="password" class="regular-text"
                       value="<?php echo esc_attr( $s['stripe_sandbox_secret_key'] ?? '' ); ?>" placeholder="sk_test_…"></td>
        </tr>
        <tr class="ovr-env-row ovr-env-stripe ovr-env-live" <?php if ( 'live' !== $stripe_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Live Publishable Key', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[stripe_live_publishable_key]" type="text" class="regular-text"
                       value="<?php echo esc_attr( $s['stripe_live_publishable_key'] ?? '' ); ?>" placeholder="pk_live_…"></td>
        </tr>
        <tr class="ovr-env-row ovr-env-stripe ovr-env-live" <?php if ( 'live' !== $stripe_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Live Secret Key', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[stripe_live_secret_key]" type="password" class="regular-text"
                       value="<?php echo esc_attr( $s['stripe_live_secret_key'] ?? '' ); ?>" placeholder="sk_live_…"></td>
        </tr>

        <?php
        // --- PayPal ---
        $this->render_gateway_env_toggle( 'paypal', 'PayPal', $s );
        $paypal_env = $s['paypal_env'] ?? 'sandbox';
        ?>
        <tr class="ovr-env-row ovr-env-paypal ovr-env-sandbox" <?php if ( 'sandbox' !== $paypal_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Sandbox Client ID', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[paypal_sandbox_client_id]" type="text" class="regular-text"
                       value="<?php echo esc_attr( $s['paypal_sandbox_client_id'] ?? '' ); ?>"></td>
        </tr>
        <tr class="ovr-env-row ovr-env-paypal ovr-env-sandbox" <?php if ( 'sandbox' !== $paypal_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Sandbox Secret', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[paypal_sandbox_secret]" type="password" class="regular-text"
                       value="<?php echo esc_attr( $s['paypal_sandbox_secret'] ?? '' ); ?>"></td>
        </tr>
        <tr class="ovr-env-row ovr-env-paypal ovr-env-live" <?php if ( 'live' !== $paypal_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Live Client ID', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[paypal_live_client_id]" type="text" class="regular-text"
                       value="<?php echo esc_attr( $s['paypal_live_client_id'] ?? '' ); ?>"></td>
        </tr>
        <tr class="ovr-env-row ovr-env-paypal ovr-env-live" <?php if ( 'live' !== $paypal_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Live Secret', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[paypal_live_secret]" type="password" class="regular-text"
                       value="<?php echo esc_attr( $s['paypal_live_secret'] ?? '' ); ?>"></td>
        </tr>

        <?php
        // --- Authorize.net ---
        $this->render_gateway_env_toggle( 'authnet', 'Authorize.net', $s );
        $authnet_env = $s['authnet_env'] ?? 'sandbox';
        ?>
        <tr class="ovr-env-row ovr-env-authnet ovr-env-sandbox" <?php if ( 'sandbox' !== $authnet_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Sandbox API Login ID', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[authnet_sandbox_login_id]" type="text" class="regular-text"
                       value="<?php echo esc_attr( $s['authnet_sandbox_login_id'] ?? '' ); ?>"></td>
        </tr>
        <tr class="ovr-env-row ovr-env-authnet ovr-env-sandbox" <?php if ( 'sandbox' !== $authnet_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Sandbox Transaction Key', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[authnet_sandbox_transaction_key]" type="password" class="regular-text"
                       value="<?php echo esc_attr( $s['authnet_sandbox_transaction_key'] ?? '' ); ?>"></td>
        </tr>
        <tr class="ovr-env-row ovr-env-authnet ovr-env-live" <?php if ( 'live' !== $authnet_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Live API Login ID', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[authnet_live_login_id]" type="text" class="regular-text"
                       value="<?php echo esc_attr( $s['authnet_live_login_id'] ?? '' ); ?>"></td>
        </tr>
        <tr class="ovr-env-row ovr-env-authnet ovr-env-live" <?php if ( 'live' !== $authnet_env ) echo 'style="display:none"'; ?>>
            <th><label><?php esc_html_e( 'Live Transaction Key', 'ovr-core' ); ?></label></th>
            <td><input name="<?php echo $opt; ?>[authnet_live_transaction_key]" type="password" class="regular-text"
                       value="<?php echo esc_attr( $s['authnet_live_transaction_key'] ?? '' ); ?>"></td>
        </tr>

        <script>
        function ovrToggleEnv(gateway, env) {
            document.querySelectorAll('.ovr-env-' + gateway).forEach(function(r) {
                r.style.display = 'none';
            });
            document.querySelectorAll('.ovr-env-' + gateway + '.ovr-env-' + env).forEach(function(r) {
                r.style.display = '';
            });
            document.querySelectorAll('input[name$="[' + gateway + '_env]"]').forEach(function(radio) {
                var label = radio.closest('label');
                if (radio.value === env) {
                    label.classList.add('ovr-env-active');
                } else {
                    label.classList.remove('ovr-env-active');
                }
            });
        }
        </script>
        <?php
    }

    private function render_subscriptions( array $s ): void {
        ?>
        <tr>
            <th><label for="ovr-grace"><?php esc_html_e( 'Renewal Grace Period (days)', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-grace" name="<?php echo esc_attr( self::OPTION ); ?>[grace_period_days]" type="number" min="0"
                       value="<?php echo esc_attr( (string) ( $s['grace_period_days'] ?? 7 ) ); ?>" class="small-text">
                <p class="description"><?php esc_html_e( 'Days after expiry before listings flip to Pending Renewal.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-inact"><?php esc_html_e( 'Inactivity Cutoff (days)', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-inact" name="<?php echo esc_attr( self::OPTION ); ?>[inactivity_days]" type="number" min="0"
                       value="<?php echo esc_attr( (string) ( $s['inactivity_days'] ?? 180 ) ); ?>" class="small-text">
                <p class="description"><?php esc_html_e( 'Listings unedited for this many days get flagged for review.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-retention"><?php esc_html_e( 'Inquiry Retention (days)', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-retention" name="<?php echo esc_attr( self::OPTION ); ?>[inquiry_retention]" type="number" min="30"
                       value="<?php echo esc_attr( (string) ( $s['inquiry_retention'] ?? 365 ) ); ?>" class="small-text">
            </td>
        </tr>
        <tr>
            <th><label for="ovr-default-plan"><?php esc_html_e( 'Default Membership', 'ovr-core' ); ?></label></th>
            <td>
                <?php
                $plans = class_exists( '\OVR\Subscription\Plans' ) ? (array) \OVR\Subscription\Plans::get_plans() : [];
                $cur_plan = (string) ( $s['default_membership'] ?? '' );
                ?>
                <select id="ovr-default-plan" name="<?php echo esc_attr( self::OPTION ); ?>[default_membership]">
                    <option value=""><?php esc_html_e( '— None —', 'ovr-core' ); ?></option>
                    <?php foreach ( $plans as $slug => $plan ) :
                        $pslug  = is_string( $slug ) ? $slug : (string) ( $plan['slug'] ?? '' );
                        $plabel = is_array( $plan ) ? (string) ( $plan['name'] ?? $pslug ) : (string) $plan;
                    ?>
                        <option value="<?php echo esc_attr( $pslug ); ?>" <?php selected( $cur_plan, $pslug ); ?>><?php echo esc_html( $plabel ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e( 'Membership assigned/suggested to new landlords by default.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Feature Toggles', 'ovr-core' ); ?></th>
            <td>
                <div class="ovr-settings-checkgroup">
                <?php
                foreach ( [
                    'enable_reviews'   => __( 'Enable property reviews', 'ovr-core' ),
                    'review_approval'  => __( 'Require admin approval for reviews', 'ovr-core' ),
                    'enable_inquiries' => __( 'Allow guest inquiries', 'ovr-core' ),
                    'enable_ical_sync' => __( 'Run hourly iCal sync', 'ovr-core' ),
                ] as $key => $label ) :
                ?>
                    <label>
                        <input type="checkbox"
                               name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]"
                               value="1" <?php checked( ! empty( $s[ $key ] ) ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Listings tab — bump throttling (Feature F) and soft-delete retention
     * (Feature G). Both are read by Bump / the daily hard-delete cron.
     */
    private function render_listings( array $s ): void {
        $opt = esc_attr( self::OPTION );
        ?>
        <tr>
            <th><label for="ovr-bump-limit"><?php esc_html_e( 'Maximum Daily Bumps', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-bump-limit" name="<?php echo $opt; ?>[bump_daily_limit]" type="number" min="1" step="1"
                       value="<?php echo esc_attr( (string) ( $s['bump_daily_limit'] ?? 12 ) ); ?>" class="small-text">
                <p class="description"><?php esc_html_e( 'How many times each landlord may bump their listings per day. Bumps beyond this are rejected. Default 12.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-retention-days"><?php esc_html_e( 'Deleted Listing Retention (days)', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-retention-days" name="<?php echo $opt; ?>[listing_retention_days]" type="number" min="1" step="1"
                       value="<?php echo esc_attr( (string) ( $s['listing_retention_days'] ?? 90 ) ); ?>" class="small-text">
                <p class="description"><?php esc_html_e( 'Soft-deleted listings are recoverable for this long, then permanently removed by a daily cleanup. Default 90 days.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-max-listings"><?php esc_html_e( 'Maximum Listings / Owner', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-max-listings" name="<?php echo $opt; ?>[max_listings]" type="number" min="0" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['max_listings'] ?? 0 ) ); ?>">
                <p class="description"><?php esc_html_e( '0 = unlimited (subscription plan limits still apply).', 'ovr-core' ); ?></p></td>
        </tr>
        <tr>
            <th><label for="ovr-max-photos"><?php esc_html_e( 'Maximum Photos / Listing', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-max-photos" name="<?php echo $opt; ?>[max_photos]" type="number" min="0" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['max_photos'] ?? 0 ) ); ?>">
                <p class="description"><?php esc_html_e( '0 = unlimited.', 'ovr-core' ); ?></p></td>
        </tr>
        <tr>
            <th><label for="ovr-max-videos"><?php esc_html_e( 'Maximum Videos / Listing', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-max-videos" name="<?php echo $opt; ?>[max_videos]" type="number" min="0" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['max_videos'] ?? 1 ) ); ?>"></td>
        </tr>
        <tr>
            <th><label for="ovr-max-docs"><?php esc_html_e( 'Maximum Documents / Listing', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-max-docs" name="<?php echo $opt; ?>[max_documents]" type="number" min="0" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['max_documents'] ?? 3 ) ); ?>"></td>
        </tr>
        <tr>
            <th><label for="ovr-default-status"><?php esc_html_e( 'Default Listing Status', 'ovr-core' ); ?></label></th>
            <td>
                <select id="ovr-default-status" name="<?php echo $opt; ?>[default_listing_status]">
                    <?php $ds = (string) ( $s['default_listing_status'] ?? 'active' ); ?>
                    <option value="active" <?php selected( $ds, 'active' ); ?>><?php esc_html_e( 'Active', 'ovr-core' ); ?></option>
                    <option value="inactive" <?php selected( $ds, 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></option>
                </select>
                <p class="description"><?php esc_html_e( 'Owner-facing status applied to newly created listings.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-feat-mode"><?php esc_html_e( 'Homepage Featured Order', 'ovr-core' ); ?></label></th>
            <td>
                <?php $fmode = (string) ( $s['homepage_featured_mode'] ?? 'auto' ); ?>
                <select id="ovr-feat-mode" name="<?php echo $opt; ?>[homepage_featured_mode]">
                    <option value="auto" <?php selected( $fmode, 'auto' ); ?>><?php esc_html_e( 'Automatic (paid placement + recency)', 'ovr-core' ); ?></option>
                    <option value="manual" <?php selected( $fmode, 'manual' ); ?>><?php esc_html_e( 'Manual (use the order below)', 'ovr-core' ); ?></option>
                </select>
                <p style="margin:10px 0 4px"><label for="ovr-feat-ids"><?php esc_html_e( 'Manual Order — Property IDs', 'ovr-core' ); ?></label></p>
                <input id="ovr-feat-ids" name="<?php echo $opt; ?>[homepage_featured_ids]" type="text" class="regular-text" style="width:480px;max-width:100%"
                       value="<?php echo esc_attr( (string) ( $s['homepage_featured_ids'] ?? '' ) ); ?>" placeholder="e.g. 128, 64, 203">
                <p class="description"><?php esc_html_e( 'Comma-separated listing IDs, shown in this order on the homepage when Manual is selected. Hidden/inactive listings are skipped automatically.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Media tab (M3 F5) — image quality + watermark behaviour.
     */
    private function render_media( array $s ): void {
        $opt = esc_attr( self::OPTION );
        $pos = (string) ( $s['watermark_position'] ?? 'bottom-right' );
        ?>
        <tr>
            <th><label for="ovr-img-quality"><?php esc_html_e( 'Image Quality', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-img-quality" name="<?php echo $opt; ?>[image_quality]" type="number" min="10" max="100" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['image_quality'] ?? 82 ) ); ?>">
                <p class="description"><?php esc_html_e( 'JPEG/WebP compression quality (10–100). Lower = smaller files. Default 82.', 'ovr-core' ); ?></p></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Watermark', 'ovr-core' ); ?></th>
            <td>
                <div class="ovr-settings-checkgroup">
                    <label><input type="checkbox" name="<?php echo $opt; ?>[enable_watermark]" value="1" <?php checked( ! empty( $s['enable_watermark'] ) ); ?>> <?php esc_html_e( 'Watermark uploaded photos', 'ovr-core' ); ?></label>
                </div>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-wm-pos"><?php esc_html_e( 'Watermark Position', 'ovr-core' ); ?></label></th>
            <td>
                <select id="ovr-wm-pos" name="<?php echo $opt; ?>[watermark_position]">
                    <?php foreach ( [ 'top-left' => 'Top Left', 'top-right' => 'Top Right', 'bottom-left' => 'Bottom Left', 'bottom-right' => 'Bottom Right', 'center' => 'Center' ] as $k => $label ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $pos, $k ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-wm-op"><?php esc_html_e( 'Watermark Opacity (%)', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-wm-op" name="<?php echo $opt; ?>[watermark_opacity]" type="number" min="0" max="100" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['watermark_opacity'] ?? 70 ) ); ?>"></td>
        </tr>
        <?php
    }

    /**
     * Security tab (M3 F5) — password rules, sessions, login throttling, 2FA.
     */
    private function render_security( array $s ): void {
        $opt = esc_attr( self::OPTION );
        ?>
        <tr>
            <th><label for="ovr-pw-min"><?php esc_html_e( 'Minimum Password Length', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-pw-min" name="<?php echo $opt; ?>[password_min_length]" type="number" min="6" max="64" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['password_min_length'] ?? 8 ) ); ?>"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Password Strength', 'ovr-core' ); ?></th>
            <td><div class="ovr-settings-checkgroup">
                <label><input type="checkbox" name="<?php echo $opt; ?>[password_require_mixed]" value="1" <?php checked( ! empty( $s['password_require_mixed'] ) ); ?>> <?php esc_html_e( 'Require letters and numbers', 'ovr-core' ); ?></label>
            </div></td>
        </tr>
        <tr>
            <th><label for="ovr-session"><?php esc_html_e( 'Session Timeout (hours)', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-session" name="<?php echo $opt; ?>[session_timeout_hours]" type="number" min="0" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['session_timeout_hours'] ?? 0 ) ); ?>">
                <p class="description"><?php esc_html_e( '0 = WordPress default (2 days / 14 days "remember me").', 'ovr-core' ); ?></p></td>
        </tr>
        <tr>
            <th><label for="ovr-login-limit"><?php esc_html_e( 'Login Attempt Limit', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-login-limit" name="<?php echo $opt; ?>[login_attempt_limit]" type="number" min="0" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['login_attempt_limit'] ?? 0 ) ); ?>">
                <p class="description"><?php esc_html_e( '0 = no limit. After this many failures from one IP, login is temporarily blocked.', 'ovr-core' ); ?></p></td>
        </tr>
        <tr>
            <th><label for="ovr-lockout"><?php esc_html_e( 'Lockout Duration (minutes)', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-lockout" name="<?php echo $opt; ?>[login_lockout_minutes]" type="number" min="1" step="1" class="small-text"
                       value="<?php echo esc_attr( (string) ( $s['login_lockout_minutes'] ?? 15 ) ); ?>"></td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Two-Factor Authentication', 'ovr-core' ); ?></th>
            <td><div class="ovr-settings-checkgroup">
                <label><input type="checkbox" name="<?php echo $opt; ?>[enable_2fa]" value="1" <?php checked( ! empty( $s['enable_2fa'] ) ); ?>> <?php esc_html_e( 'Require an emailed one-time code for administrator logins', 'ovr-core' ); ?></label>
            </div>
            <p class="description"><?php esc_html_e( 'Adds an email OTP step for users who can manage the platform. Fails open if email cannot be sent. Define OVR_DISABLE_2FA in wp-config.php to bypass in an emergency.', 'ovr-core' ); ?></p></td>
        </tr>
        <?php
    }

    /**
     * Storage tab — Backblaze B2 media offloading (Feature E).
     */
    private function render_storage( array $s ): void {
        $opt     = esc_attr( self::OPTION );
        $enabled = ! empty( $s['b2_enabled'] );

        // Connection status (only probes when enabled + credentialed).
        $status_html = '<span style="color:var(--gray-mid)">' . esc_html__( 'Not configured', 'ovr-core' ) . '</span>';
        if ( class_exists( '\OVR\Storage\BackblazeB2Client' ) && \OVR\Storage\BackblazeB2Client::is_configured() ) {
            $test = \OVR\Storage\BackblazeB2Client::test_connection();
            $color = $test['ok'] ? '#2E7D32' : 'var(--red)';
            $status_html = '<span style="color:' . esc_attr( $color ) . ';font-weight:600">'
                . ( $test['ok'] ? '● ' : '○ ' ) . esc_html( $test['message'] ) . '</span>';
        }

        $test_url = wp_nonce_url( admin_url( 'admin-post.php?action=ovr_b2_test' ), 'ovr_b2_test' );
        ?>
        <tr>
            <th><?php esc_html_e( 'Enable B2 Storage', 'ovr-core' ); ?></th>
            <td>
                <div class="ovr-settings-checkgroup">
                    <label><input type="checkbox" name="<?php echo $opt; ?>[b2_enabled]" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'Store uploaded media on Backblaze B2', 'ovr-core' ); ?></label>
                </div>
                <p class="description"><?php esc_html_e( 'When enabled, new uploads (images, videos, documents, panoramas) are sent to B2 and served from there to reduce server storage.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-b2-bucket"><?php esc_html_e( 'Bucket Name', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-b2-bucket" name="<?php echo $opt; ?>[b2_bucket_name]" type="text" class="regular-text" value="<?php echo esc_attr( (string) ( $s['b2_bucket_name'] ?? '' ) ); ?>"></td>
        </tr>
        <tr>
            <th><label for="ovr-b2-key"><?php esc_html_e( 'API Key (Key ID)', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-b2-key" name="<?php echo $opt; ?>[b2_key_id]" type="text" class="regular-text" value="<?php echo esc_attr( (string) ( $s['b2_key_id'] ?? '' ) ); ?>" autocomplete="off"></td>
        </tr>
        <tr>
            <th><label for="ovr-b2-app"><?php esc_html_e( 'Application Key', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-b2-app" name="<?php echo $opt; ?>[b2_app_key]" type="password" class="regular-text" value="<?php echo esc_attr( (string) ( $s['b2_app_key'] ?? '' ) ); ?>" autocomplete="new-password"></td>
        </tr>
        <tr>
            <th><label for="ovr-b2-region"><?php esc_html_e( 'Region', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-b2-region" name="<?php echo $opt; ?>[b2_region]" type="text" class="small-text" value="<?php echo esc_attr( (string) ( $s['b2_region'] ?? '' ) ); ?>" placeholder="us-west-004">
                <p class="description"><?php esc_html_e( 'Informational (the API discovers endpoints automatically).', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Local Copies', 'ovr-core' ); ?></th>
            <td>
                <div class="ovr-settings-checkgroup">
                    <label><input type="checkbox" name="<?php echo $opt; ?>[b2_delete_local]" value="1" <?php checked( ! empty( $s['b2_delete_local'] ) ); ?>> <?php esc_html_e( 'Delete local resized copies after offloading', 'ovr-core' ); ?></label>
                </div>
                <p class="description"><?php esc_html_e( 'Reclaims disk space. Original files are always kept locally so the photo editor (rotate/crop/watermark) keeps working.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Storage Status', 'ovr-core' ); ?></th>
            <td>
                <p style="margin:0 0 10px"><?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></p>
                <a href="<?php echo esc_url( $test_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Test Connection', 'ovr-core' ); ?></a>
                <p class="description"><?php esc_html_e( 'Save your credentials first, then test. Use a B2 application key scoped to the bucket above.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Probe the B2 connection and bounce back to the Storage tab with a notice.
     */
    public function handle_b2_test(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        check_admin_referer( 'ovr_b2_test' );

        $back = add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
            'tab'       => 'storage',
        ], admin_url( 'edit.php' ) );

        if ( class_exists( '\OVR\Storage\BackblazeB2Client' ) ) {
            $test = \OVR\Storage\BackblazeB2Client::test_connection();
            $back = add_query_arg( 'ovr_b2', $test['ok'] ? 'ok' : 'fail', $back );
        }
        wp_safe_redirect( $back );
        exit;
    }

    /**
     * Reputation tab — controls the minimum star rating a testimonial or guest
     * review must meet to appear publicly (in the Testimonials Carousel widget).
     */
    private function render_reputation( array $s ): void {
        $opt = esc_attr( self::OPTION );
        $min = isset( $s['min_display_rating'] ) ? (int) $s['min_display_rating'] : 4;
        $min = max( 1, min( 5, $min ) );
        ?>
        <tr>
            <th><label for="ovr-min-rating"><?php esc_html_e( 'Minimum Public Rating', 'ovr-core' ); ?></label></th>
            <td>
                <select id="ovr-min-rating" name="<?php echo $opt; ?>[min_display_rating]">
                    <?php foreach ( [ 1, 2, 3, 4, 5 ] as $n ) : ?>
                        <option value="<?php echo esc_attr( (string) $n ); ?>" <?php selected( $n, $min ); ?>>
                            <?php
                            /* translators: %d: star count */
                            echo esc_html( sprintf( _n( '%d star & up', '%d stars & up', $n, 'ovr-core' ), $n ) );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php esc_html_e( 'Reputation management: only testimonials and approved guest reviews at or above this rating are shown in the Testimonials Carousel. Default is 4 stars & up.', 'ovr-core' ); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'How it works', 'ovr-core' ); ?></th>
            <td>
                <p class="description" style="max-width:620px">
                    <?php esc_html_e( 'Manual testimonials are added under the Testimonials menu. Guest reviews left on a property are gated by the existing review-approval setting (Subscriptions tab) and then by this rating threshold before they can surface as testimonials.', 'ovr-core' ); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * WordPress Integration tab — credentials + schedule for importing
     * reservations, the current sync status, and the recent error log.
     */
    private function render_integration( array $s ): void {
        $opt      = esc_attr( self::OPTION );
        $url      = (string) ( $s['wp_sync_url'] ?? '' );
        $user     = (string) ( $s['wp_sync_user'] ?? '' );
        $pass     = (string) ( $s['wp_sync_pass'] ?? '' );
        $schedule = (string) ( $s['wp_sync_schedule'] ?? 'manual' );
        $enabled  = ! empty( $s['wp_sync_enabled'] );
        $latest   = \OVR\Core\SyncLog::latest( 'wordpress' );
        $recent   = \OVR\Core\SyncLog::recent( 8, 'wordpress' );
        $schedules = [
            'manual'     => __( 'Manual only', 'ovr-core' ),
            'hourly'     => __( 'Hourly', 'ovr-core' ),
            'twicedaily' => __( 'Twice daily', 'ovr-core' ),
            'daily'      => __( 'Daily', 'ovr-core' ),
        ];
        ?>
        <tr>
            <th><label for="ovr-wp-enabled"><?php esc_html_e( 'Enable Sync', 'ovr-core' ); ?></label></th>
            <td>
                <label style="display:inline-flex;align-items:center;gap:8px">
                    <input id="ovr-wp-enabled" type="checkbox" name="<?php echo $opt; ?>[wp_sync_enabled]" value="1" <?php checked( $enabled ); ?>>
                    <?php esc_html_e( 'Import reservations from a WordPress source', 'ovr-core' ); ?>
                </label>
                <p class="description"><?php esc_html_e( 'When enabled with a schedule, bookings are imported automatically. You can also import on demand from the Bookings screen.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-wp-url"><?php esc_html_e( 'Source URL', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-wp-url" name="<?php echo $opt; ?>[wp_sync_url]" type="url" class="regular-text" style="width:480px;max-width:100%" value="<?php echo esc_attr( $url ); ?>" placeholder="https://example.com/wp-json/…/bookings">
                <p class="description"><?php esc_html_e( 'A JSON endpoint returning reservations (array, or {"bookings":[…]}). Records map flexibly by common keys (name, email, checkin, checkout, amount, status, listing id/title).', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-wp-user"><?php esc_html_e( 'API Username', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-wp-user" name="<?php echo $opt; ?>[wp_sync_user]" type="text" class="regular-text" value="<?php echo esc_attr( $user ); ?>" autocomplete="off"></td>
        </tr>
        <tr>
            <th><label for="ovr-wp-pass"><?php esc_html_e( 'Application Password', 'ovr-core' ); ?></label></th>
            <td>
                <input id="ovr-wp-pass" name="<?php echo $opt; ?>[wp_sync_pass]" type="password" class="regular-text" value="<?php echo esc_attr( $pass ); ?>" autocomplete="new-password">
                <p class="description"><?php esc_html_e( 'Sent as HTTP Basic auth. Use a WordPress Application Password on the source site — never the account login password.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-wp-schedule"><?php esc_html_e( 'Sync Schedule', 'ovr-core' ); ?></label></th>
            <td>
                <select id="ovr-wp-schedule" name="<?php echo $opt; ?>[wp_sync_schedule]">
                    <?php foreach ( $schedules as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $schedule, $key ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><?php esc_html_e( 'Sync Status', 'ovr-core' ); ?></th>
            <td>
                <?php if ( $latest ) :
                    $ok = 'success' === $latest['status'];
                    ?>
                    <p style="margin:0;font-weight:600;color:<?php echo $ok ? '#2E7D32' : ( 'partial' === $latest['status'] ? '#b8920a' : '#B3261E' ); ?>">
                        <?php echo esc_html( ucfirst( $latest['status'] ) ); ?>
                        — <?php echo esc_html( $latest['message'] ); ?>
                    </p>
                    <p class="description"><?php
                        /* translators: %s: human time diff */
                        printf( esc_html__( 'Last run %s ago.', 'ovr-core' ), esc_html( human_time_diff( strtotime( $latest['created_at'] ), current_time( 'timestamp' ) ) ) );
                    ?></p>
                <?php else : ?>
                    <p class="description"><?php esc_html_e( 'No sync has run yet.', 'ovr-core' ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php if ( $recent ) : ?>
        <tr>
            <th><?php esc_html_e( 'Error / Run Log', 'ovr-core' ); ?></th>
            <td>
                <div style="border:1px solid var(--gray-border,#dbdbdb);border-radius:8px;overflow:hidden;max-width:620px">
                    <?php foreach ( $recent as $row ) :
                        $ok = 'success' === $row['status'];
                        $color = $ok ? '#2E7D32' : ( 'partial' === $row['status'] ? '#b8920a' : '#B3261E' );
                    ?>
                        <div style="display:flex;gap:10px;align-items:flex-start;padding:10px 14px;border-bottom:1px solid #f0f0f0;font-size:13px">
                            <span class="material-symbols-outlined" style="font-size:18px;color:<?php echo esc_attr( $color ); ?>"><?php echo $ok ? 'check_circle' : 'error'; ?></span>
                            <span style="flex:1">
                                <strong><?php echo esc_html( $row['message'] ); ?></strong><br>
                                <span style="color:#8b95a5"><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $row['created_at'] ) ) ); ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php
    }

    /**
     * Compliance tab — Terms, Privacy, GDPR, Cookie and general legal text.
     */
    private function render_compliance( array $s ): void {
        $opt    = esc_attr( self::OPTION );
        $blocks = [
            'terms_text'   => [ __( 'Terms of Service', 'ovr-core' ),  __( 'Shown on the Terms page and linked from registration/checkout.', 'ovr-core' ) ],
            'privacy_text' => [ __( 'Privacy Policy', 'ovr-core' ),    __( 'How user data is collected, used and stored.', 'ovr-core' ) ],
            'gdpr_text'    => [ __( 'GDPR Statement', 'ovr-core' ),    __( 'Data-subject rights, retention and lawful basis.', 'ovr-core' ) ],
            'cookie_text'  => [ __( 'Cookie Policy', 'ovr-core' ),     __( 'Cookies used and how visitors can manage them.', 'ovr-core' ) ],
            'legal_text'   => [ __( 'Additional Legal Text', 'ovr-core' ), __( 'Disclaimers or other notices appended site-wide.', 'ovr-core' ) ],
        ];
        ?>
        <tr>
            <th><?php esc_html_e( 'Compliance Toggles', 'ovr-core' ); ?></th>
            <td>
                <div class="ovr-settings-checkgroup">
                    <label><input type="checkbox" name="<?php echo $opt; ?>[gdpr_enabled]" value="1" <?php checked( ! empty( $s['gdpr_enabled'] ) ); ?>> <?php esc_html_e( 'Enable GDPR data-request handling', 'ovr-core' ); ?></label>
                    <label><input type="checkbox" name="<?php echo $opt; ?>[cookie_banner]" value="1" <?php checked( ! empty( $s['cookie_banner'] ) ); ?>> <?php esc_html_e( 'Show cookie-consent banner', 'ovr-core' ); ?></label>
                </div>
            </td>
        </tr>
        <?php foreach ( $blocks as $key => $meta ) : ?>
            <tr>
                <th><label for="ovr-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $meta[0] ); ?></label></th>
                <td>
                    <textarea id="ovr-<?php echo esc_attr( $key ); ?>" name="<?php echo $opt; ?>[<?php echo esc_attr( $key ); ?>]" rows="6" style="width:100%;max-width:620px;font-family:inherit;font-size:14px;line-height:1.6;border:1px solid var(--gray-border);border-radius:var(--radius-md);padding:12px 14px"><?php echo esc_textarea( (string) ( $s[ $key ] ?? '' ) ); ?></textarea>
                    <p class="description"><?php echo esc_html( $meta[1] ); ?></p>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php
    }

    /**
     * Documentation tab — internal help / training content for staff.
     */
    private function render_documentation( array $s ): void {
        $opt = esc_attr( self::OPTION );
        ?>
        <tr>
            <th><label for="ovr-documentation"><?php esc_html_e( 'Internal Documentation', 'ovr-core' ); ?></label></th>
            <td>
                <textarea id="ovr-documentation" name="<?php echo $opt; ?>[documentation]" rows="14" style="width:100%;max-width:760px;font-family:inherit;font-size:14px;line-height:1.7;border:1px solid var(--gray-border);border-radius:var(--radius-md);padding:14px"><?php echo esc_textarea( (string) ( $s['documentation'] ?? '' ) ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Operational notes, training material and admin guides for your team. Basic HTML allowed. Customer-facing help lives in the Support → Knowledge Base.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Billing tab — tax + invoice settings (payment gateways are under Payments).
     */
    private function render_billing( array $s ): void {
        $opt = esc_attr( self::OPTION );
        ?>
        <tr>
            <th><?php esc_html_e( 'Tax', 'ovr-core' ); ?></th>
            <td>
                <div class="ovr-settings-checkgroup">
                    <label><input type="checkbox" name="<?php echo $opt; ?>[tax_enabled]" value="1" <?php checked( ! empty( $s['tax_enabled'] ) ); ?>> <?php esc_html_e( 'Apply tax to upgrade & subscription charges', 'ovr-core' ); ?></label>
                </div>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-tax-rate"><?php esc_html_e( 'Tax Rate (%)', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-tax-rate" name="<?php echo $opt; ?>[tax_rate]" type="number" min="0" max="100" step="0.01" class="small-text" value="<?php echo esc_attr( (string) ( $s['tax_rate'] ?? 0 ) ); ?>"></td>
        </tr>
        <tr>
            <th><label for="ovr-tax-label"><?php esc_html_e( 'Tax Label', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-tax-label" name="<?php echo $opt; ?>[tax_label]" type="text" class="regular-text" value="<?php echo esc_attr( (string) ( $s['tax_label'] ?? 'Tax' ) ); ?>" placeholder="VAT / GST / Sales Tax"></td>
        </tr>
        <tr>
            <th><label for="ovr-inv-prefix"><?php esc_html_e( 'Invoice Prefix', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-inv-prefix" name="<?php echo $opt; ?>[invoice_prefix]" type="text" class="small-text" value="<?php echo esc_attr( (string) ( $s['invoice_prefix'] ?? 'OVR-' ) ); ?>"></td>
        </tr>
        <tr>
            <th><label for="ovr-company"><?php esc_html_e( 'Company Name', 'ovr-core' ); ?></label></th>
            <td><input id="ovr-company" name="<?php echo $opt; ?>[company_name]" type="text" class="regular-text" value="<?php echo esc_attr( (string) ( $s['company_name'] ?? get_bloginfo( 'name' ) ) ); ?>"></td>
        </tr>
        <tr>
            <th><label for="ovr-company-addr"><?php esc_html_e( 'Company Address', 'ovr-core' ); ?></label></th>
            <td><textarea id="ovr-company-addr" name="<?php echo $opt; ?>[company_address]" rows="3" style="width:100%;max-width:480px;font-family:inherit;border:1px solid var(--gray-border);border-radius:var(--radius-md);padding:10px 14px"><?php echo esc_textarea( (string) ( $s['company_address'] ?? '' ) ); ?></textarea></td>
        </tr>
        <tr>
            <th><label for="ovr-inv-footer"><?php esc_html_e( 'Invoice Footer', 'ovr-core' ); ?></label></th>
            <td>
                <textarea id="ovr-inv-footer" name="<?php echo $opt; ?>[invoice_footer]" rows="3" style="width:100%;max-width:480px;font-family:inherit;border:1px solid var(--gray-border);border-radius:var(--radius-md);padding:10px 14px"><?php echo esc_textarea( (string) ( $s['invoice_footer'] ?? '' ) ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Shown at the bottom of receipts/invoices. Payment gateway credentials are configured under the Payments tab.', 'ovr-core' ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Fleet Management tab — placeholder architecture for a future module.
     */
    private function render_fleet( array $s ): void {
        $opt = esc_attr( self::OPTION );
        ?>
        <tr>
            <th><?php esc_html_e( 'Fleet Management', 'ovr-core' ); ?></th>
            <td>
                <p class="description" style="max-width:620px;margin-top:0">
                    <?php esc_html_e( 'Reserved for a future module to manage vehicles, transfers and equipment associated with rentals. The scaffolding below is intentionally minimal — enabling it only stores the flag for now.', 'ovr-core' ); ?>
                </p>
                <div class="ovr-settings-checkgroup" style="margin-top:10px">
                    <label><input type="checkbox" name="<?php echo $opt; ?>[fleet_enabled]" value="1" <?php checked( ! empty( $s['fleet_enabled'] ) ); ?>> <?php esc_html_e( 'Enable Fleet Management (preview)', 'ovr-core' ); ?></label>
                </div>
            </td>
        </tr>
        <tr>
            <th><label for="ovr-fleet-notes"><?php esc_html_e( 'Planning Notes', 'ovr-core' ); ?></label></th>
            <td><textarea id="ovr-fleet-notes" name="<?php echo $opt; ?>[fleet_notes]" rows="5" style="width:100%;max-width:620px;font-family:inherit;border:1px solid var(--gray-border);border-radius:var(--radius-md);padding:12px 14px"><?php echo esc_textarea( (string) ( $s['fleet_notes'] ?? '' ) ); ?></textarea></td>
        </tr>
        <?php
    }

    /**
     * User Roles permission matrix — its own form (manages roles, not options).
     *
     * Administrator and Guest are shown read-only (administrator is all-caps;
     * guest holds none). The two OVR roles — Landlord and Support — are editable
     * checkboxes applied immediately on save via add_cap / remove_cap.
     *
     * @param array<string,string> $tabs
     */
    private function render_roles_page( array $tabs, string $tab ): void {
        $caps = \OVR\Core\Capabilities::all_caps();
        $matrix = [
            'administrator' => [ 'label' => __( 'Administrator', 'ovr-core' ), 'editable' => false, 'caps' => array_fill_keys( $caps, true ) ],
            'ovr_landlord'  => [ 'label' => __( 'Landlord', 'ovr-core' ),      'editable' => true,  'caps' => $this->role_caps( 'ovr_landlord', $caps ) ],
            'ovr_support'   => [ 'label' => __( 'Support', 'ovr-core' ),       'editable' => true,  'caps' => $this->role_caps( 'ovr_support', $caps ) ],
            'guest'         => [ 'label' => __( 'Guest', 'ovr-core' ),         'editable' => false, 'caps' => array_fill_keys( $caps, false ) ],
        ];
        ?>
        <div class="wrap ovr-settings">
            <div class="ovr-settings-head">
                <div style="display:flex;align-items:center;gap:16px">
                    <span style="display:flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:var(--radius-md);background:var(--p-light);color:var(--p);flex-shrink:0"><span class="material-symbols-outlined" style="font-size:28px">admin_panel_settings</span></span>
                    <div>
                        <h1><?php esc_html_e( 'User Roles', 'ovr-core' ); ?></h1>
                        <p><?php esc_html_e( 'Capability matrix across roles. Landlord and Support are editable.', 'ovr-core' ); ?></p>
                    </div>
                </div>
            </div>

            <div class="ovr-settings-layout">
            <nav class="ovr-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'ovr-core' ); ?>">
                <?php
                $tab_icons = [ 'general' => 'settings', 'compliance' => 'gavel', 'documentation' => 'menu_book', 'email' => 'mail', 'payments' => 'payments', 'billing' => 'receipt_long', 'subscriptions' => 'subscriptions', 'reputation' => 'star', 'roles' => 'admin_panel_settings', 'fleet' => 'dashboard', 'integration' => 'sync' ];
                foreach ( $tabs as $key => $label ) :
                    $url = add_query_arg( [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG, 'tab' => $key ], admin_url( 'edit.php' ) );
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="<?php echo $tab === $key ? 'ovr-settings-tab--active' : ''; ?>">
                        <span class="material-symbols-outlined"><?php echo esc_attr( $tab_icons[ $key ] ?? 'tune' ); ?></span><?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="ovr-settings-main">
            <?php if ( ! empty( $_GET['roles_saved'] ) ) : ?>
                <div class="updated notice"><p><?php esc_html_e( 'Role permissions updated.', 'ovr-core' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="ovr_save_roles">
                <?php wp_nonce_field( 'ovr_save_roles' ); ?>
                <div class="ovr-settings-card">
                    <div class="ovr-settings-card-body" style="padding:0;overflow-x:auto">
                        <table class="ovr-settings-table ovr-roles-matrix" style="min-width:680px">
                            <thead>
                                <tr>
                                    <th style="width:auto"><?php esc_html_e( 'Capability', 'ovr-core' ); ?></th>
                                    <?php foreach ( $matrix as $role => $info ) : ?>
                                        <th style="width:120px;text-align:center"><?php echo esc_html( $info['label'] ); ?><?php echo $info['editable'] ? '' : ' <span style="font-weight:400;color:var(--gray-mid)">(' . esc_html__( 'locked', 'ovr-core' ) . ')</span>'; ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $caps as $cap ) : ?>
                                    <tr>
                                        <td style="font-family:ui-monospace,monospace;font-size:13px"><?php echo esc_html( $cap ); ?></td>
                                        <?php foreach ( $matrix as $role => $info ) :
                                            $has = ! empty( $info['caps'][ $cap ] );
                                        ?>
                                            <td style="text-align:center">
                                                <?php if ( $info['editable'] ) : ?>
                                                    <input type="checkbox" name="caps[<?php echo esc_attr( $role ); ?>][]" value="<?php echo esc_attr( $cap ); ?>" <?php checked( $has ); ?>>
                                                <?php else : ?>
                                                    <span class="material-symbols-outlined" style="font-size:20px;color:<?php echo $has ? '#2E7D32' : '#c9ced6'; ?>"><?php echo $has ? 'check_circle' : 'remove'; ?></span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="ovr-settings-submit">
                        <?php submit_button( __( 'Save Role Permissions', 'ovr-core' ), 'primary', 'submit', false ); ?>
                        <p class="description" style="margin:10px 0 0"><?php esc_html_e( 'Note: a plugin capability re-sync (on version upgrade) restores the default baseline for each role.', 'ovr-core' ); ?></p>
                    </div>
                </div>
            </form>
            </div>
            </div>
        </div>
        <?php
    }

    /**
     * Capabilities currently granted to a role, keyed by cap => bool.
     *
     * @param string[] $all_caps
     * @return array<string, bool>
     */
    private function role_caps( string $role_name, array $all_caps ): array {
        $role = get_role( $role_name );
        $out  = array_fill_keys( $all_caps, false );
        if ( $role ) {
            foreach ( $all_caps as $cap ) {
                $out[ $cap ] = ! empty( $role->capabilities[ $cap ] );
            }
        }
        return $out;
    }

    /**
     * Persist the editable role capabilities (Landlord + Support only).
     */
    public function handle_save_roles(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '403' );
        }
        check_admin_referer( 'ovr_save_roles' );

        $all_caps  = \OVR\Core\Capabilities::all_caps();
        $submitted = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : [];

        foreach ( [ 'ovr_landlord', 'ovr_support' ] as $role_name ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }
            $checked = array_map( 'sanitize_key', (array) ( $submitted[ $role_name ] ?? [] ) );
            foreach ( $all_caps as $cap ) {
                if ( in_array( $cap, $checked, true ) ) {
                    $role->add_cap( $cap );
                } else {
                    $role->remove_cap( $cap );
                }
            }
        }

        wp_safe_redirect( add_query_arg( [
            'post_type'   => 'ovr_property',
            'page'        => self::PAGE_SLUG,
            'tab'         => 'roles',
            'roles_saved' => 1,
        ], admin_url( 'edit.php' ) ) );
        exit;
    }
}
