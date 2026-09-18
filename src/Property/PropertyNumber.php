<?php
/**
 * Immutable public OVR Property Number.
 *
 * AUTHORITATIVE SOURCE: wp_ovr_property_numbers
 *   - id         : public property number (AUTO_INCREMENT primary key)
 *   - post_id    : unique link to wp_posts.ID
 *   - created_at : allocation timestamp
 *
 * DERIVED MIRRORS / SEARCH INDEX:
 *   - _ovr_property_number : mirrors wp_ovr_property_numbers.id for fast reads
 *   - _ovr_pid_search      : mirrors _ovr_property_number for admin text search
 *
 * Post IDs remain the internal WordPress identifier; URLs keep using
 * /listing/{post_id}/. Only the human-facing display changes.
 *
 * @package OVR\Property
 * @since   2.11.0
 */

namespace OVR\Property;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use OVR\PostTypes\PropertyPostType;

class PropertyNumber {

    /** Post meta key that mirrors the table row for fast reads. */
    public const META_KEY = '_ovr_property_number';

    /** @var string Table name (with global prefix). */
    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_property_numbers';
    }

    // -----------------------------------------------------------------
    // READ
    // -----------------------------------------------------------------

    /**
      * Return the public property number for a post.
      *
      * Resolves from the authoritative wp_ovr_property_numbers table first,
      * then falls back to the _ovr_property_number meta mirror, then to the
      * WP post ID as a last resort for pre-migration records.
      *
      * If the table row exists but the meta mirror is missing/stale, this
      * method repairs the meta silently.
      */
     public static function get( int $post_id ): int {
         global $wpdb;
         $table = self::table();

         // Authoritative source: wp_ovr_property_numbers.
         $number = (int) $wpdb->get_var( $wpdb->prepare(
             "SELECT id FROM $table WHERE post_id = %d",
             $post_id
         ) );

         if ( $number > 0 ) {
             // Repair stale/missing derived meta where safe.
             $meta = (int) get_post_meta( $post_id, self::META_KEY, true );
             if ( $meta !== $number ) {
                 update_post_meta( $post_id, self::META_KEY, $number );
                 update_post_meta( $post_id, '_ovr_pid_search', (string) $number );
             }
             return $number;
         }

         // Fallback: meta mirror (pre-migration or incomplete migration).
         $meta = (int) get_post_meta( $post_id, self::META_KEY, true );
         if ( $meta > 0 ) {
             return $meta;
         }

         // Last resort: WP post ID. This only applies to posts that have
         // never been assigned a proper OVR Property Number.
         return $post_id;
     }

    // -----------------------------------------------------------------
    // ALLOCATE
    // -----------------------------------------------------------------

    /**
     * Atomically assign a public property number to a post.
     *
     * Uses MySQL AUTO_INCREMENT on the dedicated table, which is
     * concurrency-safe. The resulting row `id` becomes the public number.
     *
     * @return int Public property number (always > 0 on success).
     */
    public static function assign( int $post_id ): int {
        global $wpdb;
        $table = self::table();

        // Already assigned? Return cached value.
        $cached = (int) get_post_meta( $post_id, self::META_KEY, true );
        if ( $cached > 0 ) {
            return $cached;
        }

        // Double-check the table in case meta was lost.
        $existing = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE post_id = %d",
            $post_id
        ) );
        if ( $existing > 0 ) {
            update_post_meta( $post_id, self::META_KEY, $existing );
            return $existing;
        }

        // Atomic INSERT — MySQL auto-increment is safe under concurrency.
        $wpdb->insert( $table, [ 'post_id' => $post_id ], [ '%d' ] );
        $number = (int) $wpdb->insert_id;

        if ( $number > 0 ) {
            update_post_meta( $post_id, self::META_KEY, $number );
            update_post_meta( $post_id, '_ovr_pid_search', (string) $number );
        }

        return $number > 0 ? $number : $post_id;
    }

    // -----------------------------------------------------------------
    // SAVE-POST CALLBACK
    // -----------------------------------------------------------------

    /**
      * Hooked to save_post_ovr_property.
      *
      * Assigns a number on the first legitimate save of a new listing.
      * Skips autosaves, revisions, and auto-draft stubs.
      *
      * PropertyNumber::assign() is itself idempotent, so calling this on
      * every legitimate save is safe: existing listings keep their number,
      * and auto-draft → real-draft transitions are no longer missed.
      */
     public static function maybe_assign( int $post_id, \WP_Post $post, bool $update ): void {
         // Only act on real ovr_property posts.
         if ( PropertyPostType::POST_TYPE !== $post->post_type ) {
             return;
         }
 
         // Skip revisions.
         if ( wp_is_post_revision( $post_id ) ) {
             return;
         }
 
         // Skip autosaves.
         if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
             return;
         }
 
         // Skip auto-draft stubs — they are not yet real listings.
         if ( 'auto-draft' === $post->post_status ) {
             return;
         }
 
         // assign() is idempotent: returns existing number if already set.
         self::assign( $post_id );
     }

    // -----------------------------------------------------------------
    // MIGRATION
    // -----------------------------------------------------------------

    /**
     * Idempotent migration for existing properties.
     *
     * Ensures every published/legitimate ovr_property has a row in
     * wp_ovr_property_numbers and the _ovr_property_number meta.
     *
     * Safe to run repeatedly; never renumbers an already-assigned post.
     */
    public static function migrate(): void {
        global $wpdb;
        $table  = self::table();
        $pm     = $wpdb->postmeta;

        // 1. Ensure the table exists.
        $wpdb->query( "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_post_id (post_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" );

        // 2. Find every ovr_property that lacks _ovr_property_number meta.
        $missing = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID
             FROM {$wpdb->posts} p
             LEFT JOIN $pm AS m ON m.post_id = p.ID AND m.meta_key = %s
             WHERE p.post_type = %s
             AND m.meta_id IS NULL
             ORDER BY p.ID ASC",
            self::META_KEY,
            PropertyPostType::POST_TYPE
        ) );

        if ( empty( $missing ) ) {
            return;
        }

        // 3. Allocate one row per missing property.
        //    Wrap in a transaction for speed and atomicity of the batch.
        $wpdb->query( 'START TRANSACTION' );

        foreach ( $missing as $row ) {
            $post_id = (int) $row->ID;

            // Re-check the table in case another process inserted concurrently.
            $exists = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $table WHERE post_id = %d",
                $post_id
            ) );

            if ( $exists > 0 ) {
                update_post_meta( $post_id, self::META_KEY, $exists );
                continue;
            }

            // Preserve the currently displayed Property ID (WP post ID) as the
            // initial OVR Property Number wherever uniqueness permits.
            $wpdb->insert( $table, [ 'id' => $post_id, 'post_id' => $post_id ], [ '%d', '%d' ] );
            $number = (int) $wpdb->insert_id;

            if ( $number > 0 ) {
                update_post_meta( $post_id, self::META_KEY, $number );
                update_post_meta( $post_id, '_ovr_pid_search', (string) $number );
            }
        }

        $wpdb->query( 'COMMIT' );
    }
}
