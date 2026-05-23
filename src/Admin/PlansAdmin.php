<?php
/**
 * Pricing Plans admin editor.
 *
 * Adds a "Pricing Plans" submenu under OVR Properties. Lets admins:
 *   - edit name / price / period / max_listings / popular flag /
 *     sort_order / description / features list / active toggle
 *   - add new plans
 *   - delete plans (except base_subscriber, which is the protected default)
 *
 * Persists to the same `ovr_subscription_plans` option that
 * Plans::get_plans() reads from.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

use OVR\Subscription\Plans;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PlansAdmin {

    public const PAGE_SLUG  = 'ovr-core-plans';
    private const OPTION    = 'ovr_subscription_plans';
    private const PROTECTED = [ 'base_subscriber' ];

    public function init(): void {
        add_action( 'admin_menu',                     [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_save_plans',      [ $this, 'handle_save' ] );
        add_action( 'admin_post_ovr_delete_plan',     [ $this, 'handle_delete' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Pricing Plans', 'ovr-core' ),
            __( 'Pricing Plans', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $plans = Plans::get_plans();
        // Sort by sort_order for predictable display.
        uasort( $plans, fn( $a, $b ) => (int) ( $a['sort_order'] ?? 0 ) <=> (int) ( $b['sort_order'] ?? 0 ) );

        $saved = isset( $_GET['saved'] );
        ?>
        <div class="wrap ovr-admin-wrap" style="font-family:'Inter',sans-serif">
            <h1 style="font-size:24px;font-weight:700"><?php esc_html_e( 'Pricing Plans', 'ovr-core' ); ?></h1>
            <p style="color:#3f4948;margin:0 0 20px">
                <?php esc_html_e( 'Edit the subscription tiers shown on the pricing page. Changes apply instantly to the front-end.', 'ovr-core' ); ?>
            </p>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Plans saved.', 'ovr-core' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="ovr_save_plans">
                <?php wp_nonce_field( 'ovr_save_plans_action', 'ovr_plans_nonce' ); ?>

                <div style="display:grid;grid-template-columns:1fr;gap:20px">
                    <?php foreach ( $plans as $slug => $plan ) :
                        $this->render_plan_card( (string) $slug, $plan, false );
                    endforeach; ?>
                </div>

                <p style="margin-top:24px">
                    <?php submit_button( __( 'Save All Plans', 'ovr-core' ), 'primary', 'submit', false ); ?>
                    <a href="<?php echo esc_url( $this->page_url() . '#new-plan' ); ?>"
                       class="button button-secondary"
                       style="margin-left:8px"><?php esc_html_e( 'Add a new plan', 'ovr-core' ); ?></a>
                    <a href="<?php echo esc_url( wp_nonce_url( $this->page_url() . '&action=ovr_reset_plans', 'ovr_reset_plans_action' ) ); ?>"
                       class="button button-link-delete"
                       style="margin-left:auto;float:right"
                       onclick="return confirm('<?php echo esc_js( __( 'Reset all plans to defaults? This cannot be undone.', 'ovr-core' ) ); ?>');">
                        <?php esc_html_e( 'Reset to defaults', 'ovr-core' ); ?>
                    </a>
                </p>

                <!-- New plan card -->
                <h2 id="new-plan" style="margin-top:48px;font-size:18px"><?php esc_html_e( 'Add a New Plan', 'ovr-core' ); ?></h2>
                <?php $this->render_plan_card( '', [], true ); ?>
            </form>
        </div>
        <?php
    }

    private function render_plan_card( string $slug, array $plan, bool $is_new ): void {
        $name   = (string) ( $plan['name']         ?? '' );
        $price  = (float)  ( $plan['price']        ?? 0 );
        $period = (string) ( $plan['period']       ?? 'monthly' );
        $max    = (int)    ( $plan['max_listings'] ?? 1 );
        $desc   = (string) ( $plan['description']  ?? '' );
        $sort   = (int)    ( $plan['sort_order']   ?? 99 );
        $popular = ! empty( $plan['is_popular'] );
        $active  = $is_new ? true : ! empty( $plan['is_active'] );
        $features = is_array( $plan['features'] ?? null ) ? $plan['features'] : [];
        $features_text = implode( "\n", $features );

        $field_prefix = $is_new ? 'new_plan' : 'plans[' . $slug . ']';
        $is_protected = in_array( $slug, self::PROTECTED, true );
        ?>
        <div class="ovr-plan-card" style="background:#fff;border:1px solid #c0cccc;border-radius:14px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,0.04)">

            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:16px;flex-wrap:wrap">
                <div style="flex:1;min-width:240px">
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#3f4948;margin-bottom:4px">
                        <?php esc_html_e( 'Plan Name', 'ovr-core' ); ?>
                    </label>
                    <input type="text" name="<?php echo esc_attr( $field_prefix ); ?>[name]"
                           value="<?php echo esc_attr( $name ); ?>"
                           class="regular-text"
                           style="width:100%;font-size:15px;font-weight:500" <?php echo $is_new ? '' : 'required'; ?>>
                </div>

                <div style="min-width:160px">
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#3f4948;margin-bottom:4px">
                        <?php esc_html_e( 'Slug', 'ovr-core' ); ?>
                    </label>
                    <?php if ( $is_new ) : ?>
                        <input type="text" name="<?php echo esc_attr( $field_prefix ); ?>[slug]"
                               class="regular-text"
                               placeholder="my_new_plan"
                               pattern="[a-z0-9_]+"
                               title="<?php esc_attr_e( 'lowercase letters, numbers, underscores only', 'ovr-core' ); ?>">
                    <?php else : ?>
                        <code style="display:block;padding:6px 10px;background:#f1f4f3;border-radius:6px;font-size:13px;color:#3f4948"><?php echo esc_html( $slug ); ?></code>
                        <input type="hidden" name="<?php echo esc_attr( $field_prefix ); ?>[slug]" value="<?php echo esc_attr( $slug ); ?>">
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:16px;margin-bottom:16px">
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#3f4948;margin-bottom:4px">
                        <?php esc_html_e( 'Price', 'ovr-core' ); ?>
                    </label>
                    <input type="number" min="0" step="0.01" name="<?php echo esc_attr( $field_prefix ); ?>[price]"
                           value="<?php echo esc_attr( number_format( $price, 2, '.', '' ) ); ?>" class="regular-text">
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#3f4948;margin-bottom:4px">
                        <?php esc_html_e( 'Period', 'ovr-core' ); ?>
                    </label>
                    <select name="<?php echo esc_attr( $field_prefix ); ?>[period]" class="regular-text">
                        <?php foreach ( [ 'monthly', 'annually', 'one_time' ] as $opt ) : ?>
                            <option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $period, $opt ); ?>>
                                <?php echo esc_html( ucfirst( str_replace( '_', ' ', $opt ) ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#3f4948;margin-bottom:4px">
                        <?php esc_html_e( 'Max Listings', 'ovr-core' ); ?>
                    </label>
                    <input type="number" min="-1" name="<?php echo esc_attr( $field_prefix ); ?>[max_listings]"
                           value="<?php echo esc_attr( (string) $max ); ?>" class="regular-text">
                    <small style="color:#6f7979;display:block;margin-top:2px"><?php esc_html_e( '-1 = unlimited', 'ovr-core' ); ?></small>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#3f4948;margin-bottom:4px">
                        <?php esc_html_e( 'Sort Order', 'ovr-core' ); ?>
                    </label>
                    <input type="number" min="0" name="<?php echo esc_attr( $field_prefix ); ?>[sort_order]"
                           value="<?php echo esc_attr( (string) $sort ); ?>" class="regular-text">
                </div>
            </div>

            <div style="margin-bottom:16px">
                <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#3f4948;margin-bottom:4px">
                    <?php esc_html_e( 'Short Description', 'ovr-core' ); ?>
                </label>
                <input type="text" name="<?php echo esc_attr( $field_prefix ); ?>[description]"
                       value="<?php echo esc_attr( $desc ); ?>" class="regular-text" style="width:100%">
            </div>

            <div style="margin-bottom:16px">
                <label style="display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#3f4948;margin-bottom:4px">
                    <?php esc_html_e( 'Features (one per line)', 'ovr-core' ); ?>
                </label>
                <textarea name="<?php echo esc_attr( $field_prefix ); ?>[features]"
                          rows="4" class="large-text"
                          placeholder="Up to 5 listings&#10;Priority support&#10;Enhanced analytics"><?php echo esc_textarea( $features_text ); ?></textarea>
            </div>

            <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center">
                <label>
                    <input type="checkbox" name="<?php echo esc_attr( $field_prefix ); ?>[is_popular]" value="1" <?php checked( $popular ); ?>>
                    <?php esc_html_e( 'Highlight as "Most Popular"', 'ovr-core' ); ?>
                </label>
                <label>
                    <input type="checkbox" name="<?php echo esc_attr( $field_prefix ); ?>[is_active]" value="1" <?php checked( $active ); ?>>
                    <?php esc_html_e( 'Active (visible on pricing page)', 'ovr-core' ); ?>
                </label>

                <?php if ( ! $is_new && ! $is_protected ) : ?>
                    <span style="margin-left:auto">
                        <a href="<?php echo esc_url( wp_nonce_url(
                            add_query_arg( [ 'action' => 'ovr_delete_plan', 'plan' => $slug ], admin_url( 'admin-post.php' ) ),
                            'ovr_delete_plan_' . $slug
                        ) ); ?>"
                           class="button button-link-delete"
                           onclick="return confirm('<?php echo esc_js( __( 'Delete this plan? Users on it will be moved to Base Subscriber.', 'ovr-core' ) ); ?>');">
                            <?php esc_html_e( 'Delete plan', 'ovr-core' ); ?>
                        </a>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save handler — covers both bulk-edit and new-plan creation.
     */
    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '403' );
        if ( ! isset( $_POST['ovr_plans_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ovr_plans_nonce'] ) ), 'ovr_save_plans_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ovr-core' ) );
        }

        $existing = (array) get_option( self::OPTION, [] );

        // Update existing plans.
        $submitted = is_array( $_POST['plans'] ?? null ) ? $_POST['plans'] : [];
        foreach ( $submitted as $slug => $row ) {
            $slug = sanitize_key( $slug );
            if ( ! $slug ) continue;
            $existing[ $slug ] = $this->normalize_plan( $slug, (array) $row );
        }

        // Add new plan if any non-empty fields were submitted.
        $new = is_array( $_POST['new_plan'] ?? null ) ? $_POST['new_plan'] : [];
        if ( ! empty( $new['name'] ) && ! empty( $new['slug'] ) ) {
            $new_slug = sanitize_key( $new['slug'] );
            if ( $new_slug && ! isset( $existing[ $new_slug ] ) ) {
                $existing[ $new_slug ] = $this->normalize_plan( $new_slug, $new );
            }
        }

        update_option( self::OPTION, $existing );

        wp_safe_redirect( $this->page_url() . '&saved=1' );
        exit;
    }

    public function handle_delete(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '403' );
        $slug = sanitize_key( $_GET['plan'] ?? '' );
        if ( ! $slug || in_array( $slug, self::PROTECTED, true ) ) {
            wp_safe_redirect( $this->page_url() );
            exit;
        }
        if ( ! isset( $_GET['_wpnonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ovr_delete_plan_' . $slug ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ovr-core' ) );
        }

        $plans = (array) get_option( self::OPTION, [] );
        unset( $plans[ $slug ] );
        update_option( self::OPTION, $plans );

        // Move users on the deleted plan back to base.
        $users = get_users( [
            'meta_key'   => 'ovr_subscription_plan',
            'meta_value' => $slug,
            'fields'     => [ 'ID' ],
        ] );
        foreach ( $users as $u ) {
            update_user_meta( (int) $u->ID, 'ovr_subscription_plan', 'base_subscriber' );
        }

        wp_safe_redirect( $this->page_url() . '&saved=1' );
        exit;
    }

    /**
     * Normalize a plan payload into the canonical structure.
     */
    private function normalize_plan( string $slug, array $row ): array {
        $features_raw = (string) wp_unslash( $row['features'] ?? '' );
        $features = array_values( array_filter( array_map(
            'trim',
            explode( "\n", $features_raw )
        ) ) );

        return [
            'name'         => sanitize_text_field( wp_unslash( $row['name']        ?? '' ) ),
            'slug'         => $slug,
            'price'        => round( (float) ( $row['price'] ?? 0 ), 2 ),
            'period'       => sanitize_key( $row['period'] ?? 'monthly' ),
            'max_listings' => (int) ( $row['max_listings'] ?? 1 ),
            'is_popular'   => ! empty( $row['is_popular'] ),
            'is_active'    => ! empty( $row['is_active'] ),
            'description'  => sanitize_text_field( wp_unslash( $row['description'] ?? '' ) ),
            'features'     => array_map( 'sanitize_text_field', $features ),
            'sort_order'   => (int) ( $row['sort_order'] ?? 99 ),
            'currency'     => 'USD',
        ];
    }

    private function page_url(): string {
        return add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
        ], admin_url( 'edit.php' ) );
    }
}
