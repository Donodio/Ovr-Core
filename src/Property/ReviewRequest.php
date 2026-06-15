<?php
/**
 * Review Requests (Feature 7).
 *
 * A landlord generates a tokened link for a past guest; the guest opens it and
 * leaves a review. Backed by wp_ovr_review_requests. The token is unguessable
 * and single-purpose; completing a review marks the request done.
 *
 * @package OVR\Property
 * @since   2.0.0
 */

namespace OVR\Property;

use OVR\Core\AuditLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewRequest {

    /** Query var that opens the public review page. */
    public const QUERY_VAR = 'ovr_review_request';

    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ovr_review_requests';
    }

    /**
     * Create a request for a property + guest, returning [id, token].
     *
     * @return array{id:int,token:string}
     */
    public static function create( int $property_id, int $owner_id, string $guest_name = '', string $guest_email = '', int $booking_id = 0 ): array {
        global $wpdb;
        $token = substr( hash_hmac( 'sha256', $property_id . '|' . microtime() . '|' . wp_rand(), (string) wp_salt( 'auth' ) ), 0, 40 );

        $wpdb->insert( self::table(), [
            'property_id' => $property_id,
            'owner_id'    => $owner_id,
            'booking_id'  => $booking_id ?: null,
            'guest_name'  => sanitize_text_field( $guest_name ),
            'guest_email' => sanitize_email( $guest_email ),
            'token'       => $token,
            'status'      => 'pending',
            'created_at'  => current_time( 'mysql' ),
        ] );

        $id = (int) $wpdb->insert_id;
        AuditLog::record( 'review_request.create', 'review_request', $id, [ 'property_id' => $property_id ] );
        return [ 'id' => $id, 'token' => $token ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get_by_token( string $token ): ?array {
        global $wpdb;
        if ( '' === $token ) {
            return null;
        }
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token = %s LIMIT 1', $token ), ARRAY_A );
        return $row ?: null;
    }

    /**
     * Requests created by an owner, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for_owner( int $owner_id, int $limit = 50 ): array {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE owner_id = %d ORDER BY created_at DESC LIMIT %d', $owner_id, $limit ),
            ARRAY_A
        );
        return $rows ?: [];
    }

    /**
     * Mark a request emailed.
     */
    public static function mark_sent( int $id ): void {
        global $wpdb;
        $wpdb->update( self::table(), [ 'status' => 'sent', 'sent_at' => current_time( 'mysql' ) ], [ 'id' => $id ] );
    }

    /**
     * Mark a request completed when its review lands.
     */
    public static function mark_completed( string $token, int $review_id ): void {
        global $wpdb;
        $wpdb->update(
            self::table(),
            [ 'status' => 'completed', 'review_id' => $review_id, 'completed_at' => current_time( 'mysql' ) ],
            [ 'token' => $token ]
        );
        AuditLog::record( 'review_request.completed', 'review_request', 0, [ 'token' => substr( $token, 0, 8 ), 'review_id' => $review_id ] );
    }

    /**
     * Public review URL for a token (property permalink + query var).
     */
    public static function public_url( int $property_id, string $token ): string {
        return add_query_arg( self::QUERY_VAR, $token, get_permalink( $property_id ) );
    }

    /**
     * Email the guest their review link. Returns whether mail was accepted.
     */
    public static function send_email( int $id ): bool {
        global $wpdb;
        $req = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A );
        if ( ! $req || ! is_email( $req['guest_email'] ) ) {
            return false;
        }

        $url   = self::public_url( (int) $req['property_id'], (string) $req['token'] );
        $title = get_the_title( (int) $req['property_id'] );
        $site  = get_bloginfo( 'name' );

        $subject = sprintf(
            /* translators: %s: property title */
            __( 'How was your stay at %s?', 'ovr-core' ),
            $title
        );
        $body = sprintf(
            /* translators: 1: guest name 2: property 3: review URL 4: site */
            __( "Hi %1\$s,\n\nThank you for staying at %2\$s. We'd love to hear about your experience — it only takes a minute:\n\n%3\$s\n\nWith thanks,\n%4\$s", 'ovr-core' ),
            $req['guest_name'] ?: __( 'there', 'ovr-core' ),
            $title,
            $url,
            $site
        );

        $sent = wp_mail( $req['guest_email'], $subject, $body );
        if ( $sent ) {
            self::mark_sent( $id );
        }
        return (bool) $sent;
    }
}
