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

        return $clean;
    }

    /**
     * Render the settings page (tabbed).
     */
    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $settings = (array) get_option( self::OPTION, [] );
        $tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        $tabs     = [
            'general'       => __( 'General',       'ovr-core' ),
            'email'         => __( 'Email',         'ovr-core' ),
            'payments'      => __( 'Payments',      'ovr-core' ),
            'subscriptions' => __( 'Subscriptions', 'ovr-core' ),
        ];
        if ( ! isset( $tabs[ $tab ] ) ) $tab = 'general';
        ?>
        <div class="wrap ovr-admin-wrap" style="font-family:'Inter',sans-serif">
            <h1 style="font-size:24px;font-weight:700;margin-bottom:8px"><?php esc_html_e( 'OVR Settings', 'ovr-core' ); ?></h1>
            <p style="color:#3f4948;margin:0 0 20px"><?php esc_html_e( 'Currency, email, payment gateways, and subscription lifecycle.', 'ovr-core' ); ?></p>

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tabs as $key => $label ) :
                    $url = add_query_arg( [
                        'post_type' => 'ovr_property',
                        'page'      => self::PAGE_SLUG,
                        'tab'       => $key,
                    ], admin_url( 'edit.php' ) );
                ?>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" style="margin-top:20px">
                <?php settings_fields( 'ovr_settings_group' ); ?>
                <input type="hidden" name="<?php echo esc_attr( self::OPTION ); ?>[__active_tab]" value="<?php echo esc_attr( $tab ); ?>">

                <table class="form-table">
                    <?php
                    switch ( $tab ) {
                        case 'general':       $this->render_general( $settings );       break;
                        case 'email':         $this->render_email( $settings );         break;
                        case 'payments':      $this->render_payments( $settings );      break;
                        case 'subscriptions': $this->render_subscriptions( $settings ); break;
                    }
                    ?>
                </table>

                <?php submit_button( __( 'Save Changes', 'ovr-core' ) ); ?>
            </form>
        </div>
        <?php
    }

    private function render_general( array $s ): void {
        ?>
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
        <tr><th colspan="2">
            <h3 style="margin:24px 0 8px;display:flex;align-items:center;gap:12px">
                <?php echo esc_html( $label ); ?>
                <span style="display:inline-flex;gap:0;border:1px solid #bec9c8;border-radius:6px;overflow:hidden;font-size:13px;font-weight:500">
                    <label style="padding:4px 14px;cursor:pointer;<?php echo 'sandbox' === $current ? 'background:#006666;color:#fff;' : 'background:#f1f4f3;color:#3f4948;'; ?>">
                        <input type="radio" name="<?php echo $opt; ?>[<?php echo esc_attr( $env_key ); ?>]" value="sandbox"
                               <?php checked( $current, 'sandbox' ); ?> style="display:none"
                               onchange="ovrToggleEnv('<?php echo esc_js( $gateway ); ?>','sandbox')">
                        <?php esc_html_e( 'Sandbox', 'ovr-core' ); ?>
                    </label>
                    <label style="padding:4px 14px;cursor:pointer;<?php echo 'live' === $current ? 'background:#006666;color:#fff;' : 'background:#f1f4f3;color:#3f4948;'; ?>">
                        <input type="radio" name="<?php echo $opt; ?>[<?php echo esc_attr( $env_key ); ?>]" value="live"
                               <?php checked( $current, 'live' ); ?> style="display:none"
                               onchange="ovrToggleEnv('<?php echo esc_js( $gateway ); ?>','live')">
                        <?php esc_html_e( 'Live', 'ovr-core' ); ?>
                    </label>
                </span>
            </h3>
        </th></tr>
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
            // Update toggle button styles
            var header = document.querySelector('.ovr-env-' + gateway).closest('tbody')
                || document.querySelector('.ovr-env-' + gateway).parentElement;
            document.querySelectorAll('input[name$="[' + gateway + '_env]"]').forEach(function(radio) {
                var label = radio.closest('label');
                if (radio.value === env) {
                    label.style.background = '#006666';
                    label.style.color = '#fff';
                } else {
                    label.style.background = '#f1f4f3';
                    label.style.color = '#3f4948';
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
            <th><?php esc_html_e( 'Feature Toggles', 'ovr-core' ); ?></th>
            <td>
                <?php
                foreach ( [
                    'enable_reviews'   => __( 'Enable property reviews', 'ovr-core' ),
                    'review_approval'  => __( 'Require admin approval for reviews', 'ovr-core' ),
                    'enable_inquiries' => __( 'Allow guest inquiries', 'ovr-core' ),
                    'enable_ical_sync' => __( 'Run hourly iCal sync', 'ovr-core' ),
                ] as $key => $label ) :
                ?>
                    <label style="display:block;margin-bottom:6px">
                        <input type="checkbox"
                               name="<?php echo esc_attr( self::OPTION ); ?>[<?php echo esc_attr( $key ); ?>]"
                               value="1" <?php checked( ! empty( $s[ $key ] ) ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php
    }
}
