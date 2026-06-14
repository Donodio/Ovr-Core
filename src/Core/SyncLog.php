<?php
/**
 * Sync log writer + reader.
 *
 * One row per sync run (iCal, platform, or WordPress import) in
 * wp_ovr_sync_log, giving the integration dashboards a durable record of
 * last-sync time, status, imported counts, and errors.
 *
 * @package OVR\Core
 * @since   2.0.0
 */

namespace OVR\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SyncLog {

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_sync_log';
    }

    /**
     * Record a sync run.
     *
     * @param string   $channel     ical | wordpress | airbnb | vrbo …
     * @param string   $status      success | partial | error
     * @param int      $imported    Rows imported.
     * @param string   $message     Human-readable summary.
     * @param array    $details     Extra context (stored as JSON).
     * @param int|null $property_id Optional property scope.
     * @param string   $source_url  Optional feed URL.
     */
    public static function record( string $channel, string $status, int $imported, string $message, array $details = [], ?int $property_id = null, string $source_url = '' ): int {
        global $wpdb;
        $wpdb->insert( self::table(), [
            'channel'     => substr( $channel, 0, 40 ),
            'property_id' => $property_id ?: null,
            'source_url'  => $source_url ? substr( $source_url, 0, 500 ) : null,
            'status'      => substr( $status, 0, 20 ),
            'imported'    => $imported,
            'message'     => $message,
            'details'     => $details ? wp_json_encode( $details ) : null,
            'created_at'  => current_time( 'mysql' ),
        ] );
        return (int) $wpdb->insert_id;
    }

    /**
     * Most recent runs, optionally filtered by channel.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function recent( int $limit = 50, string $channel = '' ): array {
        global $wpdb;
        $table = self::table();
        if ( '' !== $channel ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} WHERE channel = %s ORDER BY created_at DESC, id DESC LIMIT %d", $channel, $limit ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit ),
                ARRAY_A
            );
        }
        return $rows ?: [];
    }

    /**
     * The latest run for a channel, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function latest( string $channel ): ?array {
        $rows = self::recent( 1, $channel );
        return $rows[0] ?? null;
    }
}
