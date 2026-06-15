<?php
/**
 * SEO Manager (Milestone 3 Feature 11).
 *
 * Emits meta description, robots, canonical, Open Graph and Twitter Card tags,
 * tunes the document title, and outputs JSON-LD structured data
 * (Organization + WebSite sitewide; BreadcrumbList on listings and the
 * property-type / amenity / village landing archives). The single-listing
 * LodgingBusiness graph (with reviews, images and video) is emitted by the
 * single template, which has the richest local data.
 *
 * Generic head tags self-suppress if a dedicated SEO plugin (Yoast / Rank Math /
 * SEOPress / AIOSEO) is active, so we never double up; the OVR-specific JSON-LD
 * still emits because those plugins don't model vacation-rental lodging.
 *
 * @package OVR\Frontend
 * @since   2.8.0
 */

namespace OVR\Frontend;

use OVR\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Seo {

    public function init(): void {
        add_filter( 'document_title_parts', [ $this, 'title_parts' ] );
        // Priority 1 so our tags sit near the top of <head>, before the theme's.
        add_action( 'wp_head', [ $this, 'head_tags' ], 1 );
        add_action( 'wp_head', [ $this, 'structured_data' ], 5 );
        // Core already prints a canonical on singular views; we add ours only on
        // the contexts it skips (taxonomy landing pages), so no duplicates.
    }

    /** True when another SEO plugin owns the generic head tags. */
    private function seo_plugin_active(): bool {
        return defined( 'WPSEO_VERSION' )
            || defined( 'RANK_MATH_VERSION' )
            || defined( 'SEOPRESS_VERSION' )
            || defined( 'AIOSEO_VERSION' )
            || class_exists( '\WPSEO_Frontend' );
    }

    /* ───────────────────────── Title ───────────────────────── */

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function title_parts( array $parts ): array {
        if ( $this->seo_plugin_active() ) {
            return $parts;
        }
        if ( is_singular( 'ovr_property' ) ) {
            $custom = (string) get_post_meta( get_queried_object_id(), '_ovr_seo_title', true );
            if ( '' !== $custom ) {
                $parts['title'] = $custom;
            }
        } elseif ( $this->is_landing() ) {
            $term = get_queried_object();
            if ( $term instanceof \WP_Term ) {
                $parts['title'] = sprintf(
                    /* translators: %s: taxonomy term, e.g. a village or property type */
                    __( '%s Rentals', 'ovr-core' ),
                    $term->name
                );
            }
        }
        return $parts;
    }

    /* ───────────────────────── Head meta ───────────────────────── */

    public function head_tags(): void {
        if ( $this->seo_plugin_active() ) {
            return;
        }

        $ctx = $this->context();
        if ( null === $ctx ) {
            return;
        }

        // Robots: honour a per-listing noindex toggle.
        if ( ! empty( $ctx['noindex'] ) ) {
            echo '<meta name="robots" content="noindex,follow">' . "\n";
        }

        $desc = $ctx['description'];
        if ( '' !== $desc ) {
            echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
        }

        // Canonical only where core doesn't already emit one (non-singular).
        if ( ! is_singular() && '' !== $ctx['url'] ) {
            echo '<link rel="canonical" href="' . esc_url( $ctx['url'] ) . '">' . "\n";
        }

        // Open Graph.
        $og = [
            'og:title'       => $ctx['title'],
            'og:description' => $desc,
            'og:url'         => $ctx['url'],
            'og:type'        => $ctx['og_type'],
            'og:site_name'   => $this->site_name(),
        ];
        if ( '' !== $ctx['image'] ) {
            $og['og:image'] = $ctx['image'];
        }
        foreach ( $og as $prop => $val ) {
            if ( '' !== (string) $val ) {
                echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $val ) . '">' . "\n";
            }
        }

        // Twitter Card.
        echo '<meta name="twitter:card" content="' . ( '' !== $ctx['image'] ? 'summary_large_image' : 'summary' ) . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $ctx['title'] ) . '">' . "\n";
        if ( '' !== $desc ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
        }
        if ( '' !== $ctx['image'] ) {
            echo '<meta name="twitter:image" content="' . esc_url( $ctx['image'] ) . '">' . "\n";
        }
    }

    /* ───────────────────────── JSON-LD ───────────────────────── */

    public function structured_data(): void {
        $graph = [];

        // Organization + WebSite, once, on the front page.
        if ( is_front_page() || is_home() ) {
            $graph[] = $this->organization_schema();
            $graph[] = $this->website_schema();
        }

        // Breadcrumbs on listings + landing archives.
        $crumbs = $this->breadcrumb_schema();
        if ( $crumbs ) {
            $graph[] = $crumbs;
        }

        foreach ( $graph as $node ) {
            if ( $node ) {
                echo '<script type="application/ld+json">'
                    . wp_json_encode( $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
                    . '</script>' . "\n";
            }
        }
    }

    /** @return array<string, mixed> */
    private function organization_schema(): array {
        $settings = (array) get_option( Settings::OPTION, [] );
        $logo     = (string) ( $settings['logo_url'] ?? '' );
        $org      = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $this->site_name(),
            'url'      => home_url( '/' ),
        ];
        if ( '' !== $logo ) {
            $org['logo'] = $logo;
        }
        $phone = (string) ( $settings['phone'] ?? '' );
        $email = (string) ( $settings['support_email'] ?? '' );
        if ( '' !== $phone || '' !== $email ) {
            $org['contactPoint'] = array_filter( [
                '@type'       => 'ContactPoint',
                'contactType' => 'customer support',
                'telephone'   => $phone,
                'email'       => $email,
            ] );
        }
        return $org;
    }

    /** @return array<string, mixed> */
    private function website_schema(): array {
        $search = \OVR\Core\Pages::get_page_url( 'ovr_page_search' );
        $node   = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $this->site_name(),
            'url'      => home_url( '/' ),
        ];
        if ( $search ) {
            $node['potentialAction'] = [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => add_query_arg( 'keyword', '{search_term_string}', $search ),
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }
        return $node;
    }

    /** @return array<string, mixed>|null */
    private function breadcrumb_schema(): ?array {
        $items = [];
        $items[] = [ 'name' => __( 'Home', 'ovr-core' ), 'url' => home_url( '/' ) ];

        if ( is_singular( 'ovr_property' ) ) {
            $pid     = get_queried_object_id();
            $village = get_the_terms( $pid, 'ovr_village' );
            if ( is_array( $village ) && ! empty( $village ) ) {
                $items[] = [ 'name' => $village[0]->name, 'url' => get_term_link( $village[0] ) ];
            }
            $items[] = [ 'name' => get_the_title( $pid ), 'url' => get_permalink( $pid ) ];
        } elseif ( $this->is_landing() ) {
            $term = get_queried_object();
            if ( $term instanceof \WP_Term ) {
                $items[] = [ 'name' => $term->name, 'url' => get_term_link( $term ) ];
            }
        } else {
            return null;
        }

        $elements = [];
        foreach ( $items as $i => $item ) {
            $url = is_string( $item['url'] ) ? $item['url'] : '';
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => $url,
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /* ───────────────────────── Context ───────────────────────── */

    /**
     * Resolve the current request to the values the head tags need.
     *
     * @return array{title:string,description:string,url:string,image:string,og_type:string,noindex:bool}|null
     */
    private function context(): ?array {
        if ( is_singular( 'ovr_property' ) ) {
            $pid   = get_queried_object_id();
            $desc  = (string) get_post_meta( $pid, '_ovr_seo_description', true );
            if ( '' === $desc ) {
                $desc = $this->trim_desc(
                    get_post_field( 'post_excerpt', $pid ) ?: get_post_field( 'post_content', $pid )
                );
            }
            $title = (string) get_post_meta( $pid, '_ovr_seo_title', true );
            return [
                'title'       => '' !== $title ? $title : get_the_title( $pid ),
                'description' => $desc,
                'url'         => (string) get_permalink( $pid ),
                'image'       => has_post_thumbnail( $pid ) ? (string) get_the_post_thumbnail_url( $pid, 'large' ) : '',
                'og_type'     => 'product',
                'noindex'     => '1' === (string) get_post_meta( $pid, '_ovr_seo_noindex', true ),
            ];
        }

        if ( $this->is_landing() ) {
            $term = get_queried_object();
            if ( ! ( $term instanceof \WP_Term ) ) {
                return null;
            }
            $desc = $this->trim_desc( term_description( $term ) );
            if ( '' === $desc ) {
                $desc = sprintf(
                    /* translators: 1: term name, 2: site name */
                    __( 'Browse vacation and long-term rentals in %1$s on %2$s. Compare photos, prices and availability and contact owners directly.', 'ovr-core' ),
                    $term->name,
                    $this->site_name()
                );
            }
            return [
                'title'       => sprintf( __( '%s Rentals', 'ovr-core' ), $term->name ),
                'description' => $desc,
                'url'         => (string) get_term_link( $term ),
                'image'       => '',
                'og_type'     => 'website',
                'noindex'     => false,
            ];
        }

        return null;
    }

    /** Whether we're on an OVR landing-page taxonomy archive. */
    private function is_landing(): bool {
        return is_tax( [ 'ovr_property_type', 'ovr_amenity', 'ovr_village', 'ovr_rental_type' ] );
    }

    /* ───────────────────────── Helpers ───────────────────────── */

    private function site_name(): string {
        $settings = (array) get_option( Settings::OPTION, [] );
        $name     = (string) ( $settings['site_name'] ?? '' );
        return '' !== $name ? $name : (string) get_bloginfo( 'name' );
    }

    /** Strip tags + shortcodes and clamp to ~160 chars on a word boundary. */
    private function trim_desc( string $text ): string {
        $text = wp_strip_all_tags( strip_shortcodes( $text ) );
        $text = trim( preg_replace( '/\s+/', ' ', $text ) ?? '' );
        if ( mb_strlen( $text ) <= 160 ) {
            return $text;
        }
        $clip = mb_substr( $text, 0, 157 );
        $sp   = mb_strrpos( $clip, ' ' );
        if ( false !== $sp && $sp > 100 ) {
            $clip = mb_substr( $clip, 0, $sp );
        }
        return $clip . '…';
    }
}
