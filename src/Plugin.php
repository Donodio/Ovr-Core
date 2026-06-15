<?php
/**
 * Central Plugin Orchestrator.
 *
 * Singleton that boots all modules in the correct order. Each module is
 * responsible for registering its own hooks. This class simply wires
 * them together and provides a service-locator for cross-module access.
 *
 * @package OVR
 * @since   1.0.0
 */

namespace OVR;

use OVR\Core\Assets;
use OVR\Core\Database;
use OVR\Core\Roles;
use OVR\Core\Pages;
use OVR\Core\TemplateLoader;
use OVR\PostTypes\PropertyPostType;
use OVR\PostTypes\Taxonomies;
use OVR\Testimonials\TestimonialPostType;
use OVR\Auth\LoginHandler;
use OVR\Auth\RegistrationHandler;
use OVR\Auth\PasswordResetHandler;
use OVR\Auth\AuthRedirects;
use OVR\Subscription\Plans;
use OVR\Subscription\UserSubscription;
use OVR\Subscription\PricingDisplay;
use OVR\Subscription\Lifecycle;
use OVR\Subscription\SubscriptionGate;
use OVR\Subscription\UpgradeActivator;
use OVR\Property\PropertyQuery;
use OVR\Property\PropertyMeta;
use OVR\Property\PropertyCard;
use OVR\Property\SeasonalPricing;
use OVR\Property\IcalSync;
use OVR\Property\Reviews;
use OVR\Search\SearchHandler;
use OVR\Search\SearchFilters;
use OVR\Frontend\Homepage;
use OVR\Frontend\SearchResults;
use OVR\Frontend\SingleProperty;
use OVR\Frontend\FeaturedListings;
use OVR\Frontend\VillagePage;
use OVR\Frontend\Onboarding;
use OVR\Frontend\Navigation;
use OVR\Frontend\Dashboard;
use OVR\Frontend\ListingForm;
use OVR\Shortcodes\ShortcodeManager;
use OVR\Ajax\AjaxHandler;
use OVR\REST\PropertyEndpoint;
use OVR\REST\InquiryEndpoint;
use OVR\REST\ReviewEndpoint;
use OVR\Elementor\ElementorIntegration;
use OVR\Admin\PropertyMetaBoxes;
use OVR\Admin\PropertyEditorScreen;
use OVR\Admin\TestimonialMetaBox;
use OVR\Admin\AdminAssets;
use OVR\Admin\Settings;
use OVR\Admin\PlansAdmin;
use OVR\Admin\FeaturedCities;
use OVR\Admin\PlatformOverview;
use OVR\Admin\ReviewsAdmin;
use OVR\Admin\PaidServicesAdmin;
use OVR\Admin\UsersAdmin;
use OVR\Admin\PaymentsAdmin;
use OVR\Admin\BookingsAdmin;
use OVR\Admin\CrmAdmin;
use OVR\Notifications\Notifications;
use OVR\Payment\CheckoutHandler;
use OVR\Payment\Wallet;
use OVR\Sync\WordPressSync;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Registered module instances for cross-module access.
     *
     * @var array<string, object>
     */
    private array $modules = [];

    /**
     * Prevent external instantiation.
     */
    private function __construct() {}

    /**
     * Prevent cloning.
     */
    private function __clone() {}

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize all plugin modules.
     *
     * Called on `plugins_loaded` from the bootstrap file.
     *
     * @since 1.0.0
     */
    public function init(): void {
        // Load text domain for translations.
        $this->load_textdomain();

        // Boot core infrastructure first.
        $this->boot_core();

        // Boot content types.
        $this->boot_post_types();

        // Boot authentication.
        $this->boot_auth();

        // Boot subscription system.
        $this->boot_subscriptions();

        // Boot property system.
        $this->boot_property();

        // Boot search.
        $this->boot_search();

        // Boot frontend page renderers.
        $this->boot_frontend();

        // Boot shortcodes.
        $this->boot_shortcodes();

        // Boot AJAX handlers.
        $this->boot_ajax();

        // Boot transactional notifications.
        $this->boot_notifications();

        // Boot REST API.
        $this->boot_rest();

        // Boot Elementor integration (conditional).
        $this->boot_elementor();

        // Listing soft-delete cleanup cron (Feature G). Registered
        // unconditionally — wp-cron runs outside the admin context.
        \OVR\Admin\DeletedListingsAdmin::register_cron();

        // Audit event listeners + retention cron (M3 F2). Front + admin: logins,
        // registrations and payments happen outside wp-admin too.
        $this->modules['audit_events'] = new \OVR\Core\AuditEvents();
        $this->modules['audit_events']->init();
        \OVR\Core\AuditLog::register_cron();

        // Email trigger engine (M3 F6) — subscription/review/listing emails fire
        // outside the admin context too.
        $this->modules['email_events'] = new \OVR\Email\EmailEvents();
        $this->modules['email_events']->init();

        // Settings behaviour bindings (M3 F5) — image quality, sessions, login
        // throttling, 2FA, favicon. Front + admin.
        $this->modules['settings_behaviors'] = new \OVR\Core\SettingsBehaviors();
        $this->modules['settings_behaviors']->init();

        // Backblaze B2 offloading + URL rewriting (Feature E). Runs on front +
        // admin so externally-stored media resolves to its B2 URL everywhere.
        $this->modules['storage_offloader'] = new \OVR\Storage\StorageOffloader();
        $this->modules['storage_offloader']->init();

        // Boot admin UI (only inside wp-admin / AJAX).
        if ( is_admin() ) {
            $this->boot_admin();
        }

        /**
         * Fires after all OVR modules have been initialized.
         *
         * @since 1.0.0
         */
        do_action( 'ovr_loaded' );
    }

    /**
     * Boot admin-only modules (meta boxes, admin asset enqueue).
     *
     * @since 1.0.0
     */
    private function boot_notifications(): void {
        $this->modules['notifications'] = new Notifications();
        $this->modules['notifications']->init();

        $this->modules['checkout'] = new CheckoutHandler();
        $this->modules['checkout']->init();

        $this->modules['wallet'] = new Wallet();
        $this->modules['wallet']->init();
    }

    private function boot_admin(): void {
        $this->modules['admin_meta_boxes'] = new PropertyMetaBoxes();
        $this->modules['admin_property_editor'] = new PropertyEditorScreen();
        $this->modules['admin_testimonial_meta'] = new TestimonialMetaBox();
        $this->modules['admin_assets']     = new AdminAssets();
        $this->modules['admin_settings']   = new Settings();
        $this->modules['admin_plans']      = new PlansAdmin();
        $this->modules['admin_featured_cities'] = new FeaturedCities();
        $this->modules['admin_overview']   = new PlatformOverview();
        $this->modules['admin_reviews']         = new ReviewsAdmin();
        $this->modules['admin_paid_services']   = new PaidServicesAdmin();
        $this->modules['admin_users']           = new UsersAdmin();
        $this->modules['admin_payments']        = new PaymentsAdmin();
        $this->modules['admin_bookings']         = new BookingsAdmin();
        $this->modules['admin_crm']              = new CrmAdmin();
        $this->modules['admin_membership']       = new \OVR\Admin\MembershipAdmin();
        $this->modules['admin_support']          = new \OVR\Admin\SupportAdmin();
        $this->modules['admin_listing_filters'] = new \OVR\Admin\ListingFilters();
        $this->modules['admin_deleted_listings'] = new \OVR\Admin\DeletedListingsAdmin();
        $this->modules['admin_audit_log'] = new \OVR\Admin\AuditLogAdmin();
        $this->modules['admin_emails'] = new \OVR\Admin\EmailManagerAdmin();

        $this->modules['admin_meta_boxes']->init();
        $this->modules['admin_property_editor']->init();
        $this->modules['admin_testimonial_meta']->init();
        $this->modules['admin_assets']->init();
        $this->modules['admin_settings']->init();
        $this->modules['admin_plans']->init();
        $this->modules['admin_featured_cities']->init();
        $this->modules['admin_overview']->init();
        $this->modules['admin_reviews']->init();
        $this->modules['admin_paid_services']->init();
        $this->modules['admin_users']->init();
        $this->modules['admin_payments']->init();
        $this->modules['admin_bookings']->init();
        $this->modules['admin_crm']->init();
        $this->modules['admin_membership']->init();
        $this->modules['admin_support']->init();
        $this->modules['admin_listing_filters']->init();
        $this->modules['admin_deleted_listings']->init();
        $this->modules['admin_audit_log']->init();
        $this->modules['admin_emails']->init();
    }

    /**
     * Load plugin text domain.
     *
     * @since 1.0.0
     */
    private function load_textdomain(): void {
        load_plugin_textdomain(
            'ovr-core',
            false,
            dirname( OVR_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Boot core infrastructure modules.
     *
     * @since 1.0.0
     */
    private function boot_core(): void {
        $this->modules['assets']          = new Assets();
        $this->modules['database']        = new Database();
        $this->modules['roles']           = new Roles();
        $this->modules['pages']           = new Pages();
        $this->modules['template_loader'] = new TemplateLoader();

        foreach ( [ 'assets', 'database', 'roles', 'pages', 'template_loader' ] as $key ) {
            $this->modules[ $key ]->init();
        }
    }

    /**
     * Boot custom post types and taxonomies.
     *
     * @since 1.0.0
     */
    private function boot_post_types(): void {
        $this->modules['property_cpt']   = new PropertyPostType();
        $this->modules['taxonomies']     = new Taxonomies();
        $this->modules['testimonial_cpt'] = new TestimonialPostType();

        $this->modules['property_cpt']->init();
        $this->modules['taxonomies']->init();
        $this->modules['testimonial_cpt']->init();
    }

    /**
     * Boot authentication system.
     *
     * @since 1.0.0
     */
    private function boot_auth(): void {
        $this->modules['login']          = new LoginHandler();
        $this->modules['registration']   = new RegistrationHandler();
        $this->modules['password_reset'] = new PasswordResetHandler();
        $this->modules['auth_redirects'] = new AuthRedirects();

        $this->modules['login']->init();
        $this->modules['registration']->init();
        $this->modules['password_reset']->init();
        $this->modules['auth_redirects']->init();
    }

    /**
     * Boot subscription plan system.
     *
     * @since 1.0.0
     */
    private function boot_subscriptions(): void {
        $this->modules['plans']             = new Plans();
        $this->modules['user_subscription'] = new UserSubscription();
        $this->modules['pricing_display']   = new PricingDisplay();
        $this->modules['lifecycle']         = new Lifecycle();
        $this->modules['subscription_gate'] = new SubscriptionGate();
        $this->modules['upgrade_activator'] = new UpgradeActivator();
        $this->modules['loyalty']           = new \OVR\Subscription\Loyalty();

        $this->modules['plans']->init();
        $this->modules['user_subscription']->init();
        $this->modules['pricing_display']->init();
        $this->modules['lifecycle']->init();
        $this->modules['subscription_gate']->init();
        $this->modules['upgrade_activator']->init();
        $this->modules['loyalty']->init();
    }

    /**
     * Boot property listing system.
     *
     * @since 1.0.0
     */
    private function boot_property(): void {
        $this->modules['property_query']   = new PropertyQuery();
        $this->modules['property_meta']    = new PropertyMeta();
        $this->modules['property_card']    = new PropertyCard();
        $this->modules['seasonal_pricing'] = new SeasonalPricing();
        $this->modules['ical_sync']        = new IcalSync();
        $this->modules['geocoder']         = new \OVR\Property\Geocoder();
        $this->modules['reviews']          = new Reviews();
        $this->modules['review_requests']  = new \OVR\Property\ReviewRequestPage();
        $this->modules['wp_sync']          = new WordPressSync();

        $this->modules['property_meta']->init();
        $this->modules['seasonal_pricing']->init();
        $this->modules['ical_sync']->init();
        $this->modules['geocoder']->init();
        $this->modules['reviews']->init();
        $this->modules['review_requests']->init();
        $this->modules['wp_sync']->init();
    }

    /**
     * Boot search and filtering system.
     *
     * @since 1.0.0
     */
    private function boot_search(): void {
        $this->modules['search_handler'] = new SearchHandler();
        $this->modules['search_filters'] = new SearchFilters();

        $this->modules['search_handler']->init();
        $this->modules['search_filters']->init();
    }

    /**
     * Boot frontend page renderers.
     *
     * @since 1.0.0
     */
    private function boot_frontend(): void {
        $this->modules['homepage']          = new Homepage();
        $this->modules['search_results']    = new SearchResults();
        $this->modules['single_property']   = new SingleProperty();
        $this->modules['featured_listings'] = new FeaturedListings();
        $this->modules['village_page']      = new VillagePage();
        $this->modules['onboarding']        = new Onboarding();
        $this->modules['navigation']        = new Navigation();
        $this->modules['dashboard']         = new Dashboard();
        $this->modules['listing_form']      = new ListingForm();

        foreach ( [
            'homepage', 'search_results', 'single_property',
            'featured_listings', 'village_page', 'onboarding', 'navigation',
            'dashboard', 'listing_form',
        ] as $key ) {
            $this->modules[ $key ]->init();
        }
    }

    /**
     * Boot shortcode registrations.
     *
     * @since 1.0.0
     */
    private function boot_shortcodes(): void {
        $this->modules['shortcodes'] = new ShortcodeManager();
        $this->modules['shortcodes']->init();
    }

    /**
     * Boot AJAX handlers.
     *
     * @since 1.0.0
     */
    private function boot_ajax(): void {
        $this->modules['ajax'] = new AjaxHandler();
        $this->modules['ajax']->init();
    }

    /**
     * Boot REST API endpoints.
     *
     * @since 1.0.0
     */
    private function boot_rest(): void {
        $this->modules['rest_property'] = new PropertyEndpoint();
        $this->modules['rest_inquiry']  = new InquiryEndpoint();
        $this->modules['rest_review']   = new ReviewEndpoint();

        $this->modules['rest_property']->init();
        $this->modules['rest_inquiry']->init();
        $this->modules['rest_review']->init();
    }

    /**
     * Boot Elementor integration if Elementor is active.
     *
     * @since 1.0.0
     */
    private function boot_elementor(): void {
        if ( ! did_action( 'elementor/loaded' ) ) {
            return;
        }

        $this->modules['elementor'] = new ElementorIntegration();
        $this->modules['elementor']->init();
    }

    /**
     * Retrieve a registered module instance.
     *
     * @param string $key Module key.
     * @return object|null
     */
    public function module( string $key ): ?object {
        return $this->modules[ $key ] ?? null;
    }

    /**
     * Get the plugin URL for an asset path.
     *
     * @param string $path Relative path within plugin.
     * @return string Full URL.
     */
    public static function asset_url( string $path ): string {
        return OVR_PLUGIN_URL . ltrim( $path, '/' );
    }

    /**
     * Get the plugin directory path.
     *
     * @param string $path Relative path within plugin.
     * @return string Full filesystem path.
     */
    public static function plugin_path( string $path = '' ): string {
        return OVR_PLUGIN_DIR . ltrim( $path, '/' );
    }
}
