<?php
/**
 * OVR — Homepage Setup Script (run once).
 *
 * Builds a 100% Elementor-native layout for the OVR homepage and writes it to
 * the target page's `_elementor_data`, replacing the previous monolithic HTML
 * widget. Every section is then editable, reorderable, and restylable in the
 * Elementor editor while listings/villages stay dynamic from the database.
 *
 * Layout written:
 *   1. Hero          — ovr_hero_slider (action_cards) with two CTA cards.
 *   2. Who We Are     — Heading + Text Editor (native).
 *   3. Explore Villages — Heading + ovr_villages_slider.
 *   4. Featured Rentals — Heading + ovr_property_cards (3 featured, 3 cols) + Button.
 *   5. Helpful Resources — Heading + inner 3-column section of native Icon Boxes.
 *
 * Usage (Local site must be running so the DB is reachable):
 *   wp eval-file wp-content/plugins/ovr-core/setup-homepage.php
 *
 * Target page defaults to ID 99; override with env OVR_HOME_PAGE_ID.
 * The original _elementor_data + post_content are saved to post meta
 * `_ovr_home_backup` (only on first run) so the change is reversible.
 *
 * @package OVR
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    fwrite( STDERR, "Run through WP-CLI:  wp eval-file wp-content/plugins/ovr-core/setup-homepage.php\n" );
    exit( 1 );
}

/* ── Logging helper (WP-CLI aware) ─────────────────────────────────────── */
$ovr_log = static function ( string $msg, string $type = 'log' ): void {
    if ( class_exists( '\WP_CLI' ) ) {
        switch ( $type ) {
            case 'success': \WP_CLI::success( $msg ); break;
            case 'warning': \WP_CLI::warning( $msg ); break;
            case 'error':   \WP_CLI::error( $msg ); break; // exits
            default:        \WP_CLI::log( $msg );
        }
    } else {
        echo $msg . "\n";
    }
};

/* ── Resolve the target page ───────────────────────────────────────────── */
$page_id = (int) ( getenv( 'OVR_HOME_PAGE_ID' ) ?: 99 );
$page    = get_post( $page_id );

if ( ! $page || 'page' !== $page->post_type ) {
    $front = (int) get_option( 'page_on_front' );
    if ( $front && get_post( $front ) ) {
        $ovr_log( "Page #{$page_id} not found — falling back to the configured front page #{$front}.", 'warning' );
        $page_id = $front;
        $page    = get_post( $front );
    } else {
        $ovr_log( "No valid target page found (tried #{$page_id} and the front page). Set OVR_HOME_PAGE_ID and retry.", 'error' );
        return;
    }
}

if ( ! class_exists( '\Elementor\Plugin' ) ) {
    $ovr_log( 'Elementor is not active. The data will be written, but Elementor must be active to render/edit it.', 'warning' );
}
if ( ! class_exists( '\OVR\Core\Pages' ) ) {
    $ovr_log( 'OVR Core plugin is not loaded. Activate ovr-core first.', 'error' );
    return;
}

/* ── Element builders (closures so nothing leaks to global scope) ───────── */
$rid = static fn(): string => substr( md5( uniqid( '', true ) . wp_rand() ), 0, 7 );

$widget = static function ( string $type, array $settings ) use ( $rid ): array {
    return [ 'id' => $rid(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => [] ];
};

$column = static function ( array $children, int $size = 100 ) use ( $rid ): array {
    return [ 'id' => $rid(), 'elType' => 'column', 'settings' => [ '_column_size' => $size, '_inline_size' => null ], 'elements' => $children ];
};

$section = static function ( array $columns, array $settings = [], bool $inner = false ) use ( $rid ): array {
    return [ 'id' => $rid(), 'elType' => 'section', 'settings' => $settings, 'elements' => $columns, 'isInner' => $inner ];
};

$heading = static function ( string $title, string $align = 'center' ) use ( $widget ): array {
    return $widget( 'heading', [
        'title'                       => $title,
        'header_size'                 => 'h2',
        'align'                       => $align,
        'title_color'                 => '#1b1b20',
        'typography_typography'       => 'custom',
        'typography_font_family'      => 'Atkinson Hyperlegible Next',
        'typography_font_size'        => [ 'unit' => 'px', 'size' => 32 ],
        'typography_font_size_mobile' => [ 'unit' => 'px', 'size' => 24 ],
        'typography_font_weight'      => '600',
    ] );
};

$sec_settings = static function ( string $bg = '', int $py = 64 ): array {
    $s = [
        'content_width' => [ 'unit' => 'px', 'size' => 1280 ],
        'padding'       => [ 'unit' => 'px', 'top' => (string) $py, 'right' => '24', 'bottom' => (string) $py, 'left' => '24', 'isLinked' => false ],
    ];
    if ( '' !== $bg ) {
        $s['background_background'] = 'classic';
        $s['background_color']      = $bg;
    }
    return $s;
};

$icon_box = static function ( string $icon, string $title, string $desc, string $url ) use ( $widget ): array {
    return $widget( 'icon-box', [
        'selected_icon'     => [ 'value' => $icon, 'library' => 'fa-solid' ],
        'title_text'        => $title,
        'description_text'  => $desc,
        'link'              => [ 'url' => $url, 'is_external' => '', 'nofollow' => '' ],
        'position'          => 'top',
        'primary_color'     => '#010b62',
        'title_color'       => '#1b1b20',
        'description_color' => '#5F6B7A',
    ] );
};

/* ── Dynamic links + hero image ────────────────────────────────────────── */
$search_url   = \OVR\Core\Pages::get_page_url( 'ovr_page_search' );
$register_url = \OVR\Core\Pages::get_page_url( 'ovr_page_register' );
$featured_url = \OVR\Core\Pages::get_page_url( 'ovr_page_featured' );
$hero_bg      = OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';

/* ── 1. Hero ───────────────────────────────────────────────────────────── */
$hero = $section(
    [ $column( [
        $widget( 'ovr_hero_slider', [
            'hero_layout'    => 'action_cards',
            'heading'        => 'Rental Homes in The Villages, Florida',
            'subtitle'       => 'Owner-direct rentals, seasonal stays, and monthly homes. Connect directly with landlords in our community.',
            'bg_image'       => [ 'url' => $hero_bg, 'id' => '' ],
            'card1_title'    => 'Find a Rental',
            'card1_desc'     => 'Browse our extensive directory of seasonal and long-term homes.',
            'card1_btn_text' => 'Search Now',
            'card1_btn_link' => [ 'url' => $search_url, 'is_external' => '', 'nofollow' => '' ],
            'card2_title'    => 'List My Property',
            'card2_desc'     => 'Reach thousands of renters looking for homes in our community.',
            'card2_btn_text' => 'Start Listing',
            'card2_btn_link' => [ 'url' => $register_url, 'is_external' => '', 'nofollow' => '' ],
        ] ),
    ] ) ],
    [
        'stretch_section' => 'section-stretched',
        'layout'          => 'full_width',
        'gap'             => 'no',
        'padding'         => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ],
    ]
);

/* ── 2. Who We Are ─────────────────────────────────────────────────────── */
$who = $section(
    [ $column( [
        $heading( 'Who We Are', 'center' ),
        $widget( 'text-editor', [
            'editor'     => '<p>Serving landlords and renters since 2013, OVR is the premier local platform dedicated to connecting property owners with reliable renters in The Villages community. We pride ourselves on offering an authentic, owner-direct experience, fostering trust and clarity without the corporate overhead of global marketplaces.</p>',
            'align'      => 'center',
            'text_color' => '#454651',
        ] ),
    ] ) ],
    $sec_settings( '#f5f2fa', 64 )
);

/* ── 3. Explore The Villages ───────────────────────────────────────────── */
$villages = $section(
    [ $column( [
        $heading( 'Explore The Villages', 'left' ),
        $widget( 'ovr_villages_slider', [
            'layout'     => 'slider',
            'count'      => 6,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'show_count' => '',
        ] ),
    ] ) ],
    $sec_settings( '', 48 )
);

/* ── 4. Featured Rentals ───────────────────────────────────────────────── */
$featured = $section(
    [ $column( [
        $heading( 'Featured Rentals', 'left' ),
        $widget( 'ovr_property_cards', [
            'posts_per_page' => 3,
            'columns'        => '3',
            'featured_only'  => 'yes',
            'sort'           => 'newest',
        ] ),
        $widget( 'button', [
            'text'  => 'View All Rentals',
            'link'  => [ 'url' => $featured_url, 'is_external' => '', 'nofollow' => '' ],
            'align' => 'center',
        ] ),
    ] ) ],
    $sec_settings( '#ffffff', 64 )
);

/* ── 5. Helpful Resources ──────────────────────────────────────────────── */
$resources_inner = $section(
    [
        $column( [ $icon_box( 'fas fa-circle-info', 'Villages Info', 'Learn about amenities, town squares, and community lifestyle.', '#' ) ], 33 ),
        $column( [ $icon_box( 'fas fa-circle-question', 'FAQ', 'Answers to common questions for both renters and landlords.', '#' ) ], 33 ),
        $column( [ $icon_box( 'fas fa-file-lines', 'PDF Updates', 'Downloadable guides, seasonal newsletters, and rental forms.', '#' ) ], 34 ),
    ],
    [ 'gap' => 'extended' ],
    true
);

$helpful = $section(
    [ $column( [
        $heading( 'Helpful Resources', 'center' ),
        $resources_inner,
    ] ) ],
    $sec_settings( '', 64 )
);

/* ── Assemble + encode ─────────────────────────────────────────────────── */
$data = [ $hero, $who, $villages, $featured, $helpful ];
$json = wp_json_encode( $data );

if ( false === $json ) {
    $ovr_log( 'Failed to JSON-encode the Elementor layout. Aborting.', 'error' );
    return;
}

/* ── Back up the original (first run only) ──────────────────────────────── */
$existing_backup = get_post_meta( $page_id, '_ovr_home_backup', true );
if ( empty( $existing_backup ) ) {
    update_post_meta( $page_id, '_ovr_home_backup', [
        'data'    => get_post_meta( $page_id, '_elementor_data', true ),
        'content' => $page->post_content,
        'time'    => current_time( 'mysql' ),
    ] );
    $ovr_log( "Backed up the original page #{$page_id} to meta '_ovr_home_backup'." );
} else {
    $ovr_log( "Original backup already exists for page #{$page_id} — leaving it untouched." );
}

/* ── Write Elementor meta ──────────────────────────────────────────────── */
update_post_meta( $page_id, '_elementor_data', wp_slash( $json ) );
update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );
if ( defined( 'ELEMENTOR_VERSION' ) ) {
    update_post_meta( $page_id, '_elementor_version', ELEMENTOR_VERSION );
}

/* ── Clear Elementor's generated CSS so the new layout renders ──────────── */
if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
    \Elementor\Plugin::$instance->files_manager->clear_cache();
}

/* ── Report ────────────────────────────────────────────────────────────── */
$is_front = ( (int) get_option( 'page_on_front' ) === $page_id && 'page' === get_option( 'show_on_front' ) );
$ovr_log( 'Homepage layout written: Hero → Who We Are → Explore The Villages → Featured Rentals → Helpful Resources.' );
$ovr_log( 'Edit it in Elementor: ' . admin_url( 'post.php?post=' . $page_id . '&action=elementor' ) );
$ovr_log( 'View it: ' . get_permalink( $page_id ) );
if ( ! $is_front ) {
    $ovr_log( "Note: page #{$page_id} is NOT set as the site's front page. Set Settings → Reading → Homepage to it if that's intended.", 'warning' );
}
$ovr_log( "Done. Page #{$page_id} is now a native Elementor homepage.", 'success' );
