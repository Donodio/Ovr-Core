<?php
/**
 * Villages Archive.
 *
 * Renders the /villages/ page: every village (ovr_village term) that has at
 * least one publicly-visible property, grouped by its parent Section so the
 * page reads as "regions → villages within them". Each village links to its
 * existing single-village landing page (the ovr_village term archive, which
 * the rewrite rule maps to /village/<slug>/).
 *
 * @package OVR\Frontend
 * @since   1.0.0
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VillagesArchive {

    /** Term meta key the Village Sections admin stores each section's image against. */
    public const IMAGE_META = 'ovr_village_image_id';

    public function init(): void {}

    /**
     * Resolve a village term's display image (large), or ''.
     *
     * Honours the per-section image an admin assigned in the Village Sections
     * portal (stored as term meta). Falls back to the curated section image
     * seeded by the Homepage module, then to none (the template shows a themed
     * placeholder so every card keeps the same shape).
     */
    public static function image_url( \WP_Term $term ): string {
        // Own image first.
        $img_id = (int) get_term_meta( $term->term_id, self::IMAGE_META, true );
        if ( $img_id ) {
            $url = wp_get_attachment_image_url( $img_id, 'large' );
            if ( $url ) {
                return $url;
            }
        }
        // Inherit the parent Section's image (child villages rarely carry their
        // own) so a whole section stays visually consistent.
        if ( $term->parent ) {
            $parent_img = (int) get_term_meta( (int) $term->parent, self::IMAGE_META, true );
            if ( $parent_img ) {
                $url = wp_get_attachment_image_url( $parent_img, 'large' );
                if ( $url ) {
                    return $url;
                }
            }
        }
        // Curated homepage section image (P17) — keyed by term id in an option.
        $curated = get_option( 'ovr_village_section_images', [] );
        if ( is_array( $curated ) && ! empty( $curated[ $term->term_id ] ) ) {
            $cid = (int) $curated[ $term->term_id ];
            $url = wp_get_attachment_image_url( $cid, 'large' );
            if ( $url ) {
                return $url;
            }
        }
        return '';
    }

    public static function render(): string {
        return TemplateLoader::get_rendered( 'pages/villages.php', [
            'groups' => self::grouped_villages(),
        ] );
    }

    /**
     * Build the grouped list of villages.
     *
     * Returns an ordered map of:
     *   group label => [ WP_Term, WP_Term, ... ]
     *
     * Villages with no published properties are omitted (count === 0).
     * Top-level terms (Sections) become group headings; their child terms
     * are listed beneath them. Any village without a parent Section is
     * collected under a generic "Other Villages" group so nothing is lost.
     *
     * @return array<string, \WP_Term[]>
     */
    public static function grouped_villages(): array {
        $terms = get_terms( [
            'taxonomy'   => 'ovr_village',
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ] );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return [];
        }

        // Index terms by ID so we can resolve parents.
        $by_id = [];
        foreach ( $terms as $term ) {
            $by_id[ $term->term_id ] = $term;
        }

        $groups       = [];
        $other_label  = __( 'Villages', 'ovr-core' );

        foreach ( $terms as $term ) {
            // A term acts as a "village" leaf. If it has a parent that is also
            // a village term, group it under the parent's name; otherwise the
            // term itself heads a group.
            if ( $term->parent && isset( $by_id[ $term->parent ] ) ) {
                $group_label = $by_id[ $term->parent ]->name;
            } else {
                // Top-level term. Group it under its own name only if it has
                // children; otherwise fold it into the generic group so we
                // don't create dozens of single-item headings.
                $has_children = false;
                foreach ( $terms as $maybe_child ) {
                    if ( $maybe_child->parent === $term->term_id ) {
                        $has_children = true;
                        break;
                    }
                }
                $group_label = $has_children ? $term->name : $other_label;

                // A parent term that is just a heading (has children) should
                // not also appear as a clickable village in its own group.
                if ( $has_children ) {
                    $groups[ $group_label ] = $groups[ $group_label ] ?? [];
                    continue;
                }
            }

            $groups[ $group_label ]   = $groups[ $group_label ] ?? [];
            $groups[ $group_label ][] = $term;
        }

        // Attach each village's display image so the template can render a
        // uniform photo-first card (name beneath the image).
        foreach ( $groups as &$villages ) {
            foreach ( $villages as $term ) {
                $term->image_url = self::image_url( $term );
            }
        }
        unset( $villages );

        // Drop any empty groups (a heading whose only members had 0 posts —
        // already filtered by hide_empty, but be defensive).
        $groups = array_filter( $groups );

        // Stable, human-friendly ordering: named Section groups first
        // (alphabetical), the generic catch-all last.
        uksort( $groups, static function ( $a, $b ) use ( $other_label ) {
            if ( $a === $other_label ) {
                return 1;
            }
            if ( $b === $other_label ) {
                return -1;
            }
            return strcasecmp( $a, $b );
        } );

        return $groups;
    }
}
