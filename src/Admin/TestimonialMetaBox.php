<?php
/**
 * Testimonial Meta Box + admin list columns.
 *
 * Renders the editor UI for an ovr_testimonial (quote, rating, role/location,
 * optional linked property) and adds Rating / Source / Property columns to the
 * Testimonials list table.
 *
 * @package OVR\Admin
 * @since   1.1.0
 */

namespace OVR\Admin;

use OVR\Testimonials\TestimonialPostType as T;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class TestimonialMetaBox {

    private const NONCE_ACTION = 'ovr_testimonial_save';
    private const NONCE_NAME   = 'ovr_testimonial_nonce';
    private const POST_TYPE    = 'ovr_testimonial';

    public function init(): void {
        add_action( 'add_meta_boxes_' . self::POST_TYPE, [ $this, 'register' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save' ], 10, 2 );

        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'columns' ] );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'column_content' ], 10, 2 );
    }

    public function register(): void {
        add_meta_box(
            'ovr_testimonial_details',
            __( 'Testimonial Details', 'ovr-core' ),
            [ $this, 'render' ],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render( \WP_Post $post ): void {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

        $quote       = (string) get_post_meta( $post->ID, T::META_QUOTE, true );
        $rating      = (int) get_post_meta( $post->ID, T::META_RATING, true );
        $rating      = $rating ?: 5;
        $role        = (string) get_post_meta( $post->ID, T::META_ROLE, true );
        $property_id = (int) get_post_meta( $post->ID, T::META_PROPERTY, true );
        $source      = (string) get_post_meta( $post->ID, T::META_SOURCE, true ) ?: 'manual';

        $properties = get_posts( [
            'post_type'      => 'ovr_property',
            'post_status'    => [ 'publish', 'draft', 'pending' ],
            'posts_per_page' => 300,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ] );
        ?>
        <style>
            .ovr-tmb{font-family:'Inter',sans-serif;max-width:760px}
            .ovr-tmb .row{margin-bottom:18px}
            .ovr-tmb label.lbl{display:block;font-weight:600;margin-bottom:6px;color:#181c1c}
            .ovr-tmb textarea,.ovr-tmb input[type=text],.ovr-tmb select{width:100%;max-width:560px;padding:8px 10px;border:1px solid #c3c4c7;border-radius:6px}
            .ovr-tmb .desc{color:#646970;font-size:12px;margin:4px 0 0}
            .ovr-tmb .stars{display:inline-flex;gap:6px}
            .ovr-tmb .stars label{cursor:pointer;font-size:26px;line-height:1;color:#dcdcde}
            .ovr-tmb .stars input{position:absolute;opacity:0;width:0;height:0}
            .ovr-tmb .stars label.on{color:#cca72f}
            .ovr-tmb .pill{display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;background:#e7f3f3;color:#006666;text-transform:capitalize}
        </style>
        <div class="ovr-tmb">

            <?php if ( 'review' === $source ) : ?>
                <div class="row">
                    <span class="pill"><?php esc_html_e( 'Promoted from a property review', 'ovr-core' ); ?></span>
                </div>
            <?php endif; ?>

            <div class="row">
                <label class="lbl" for="ovr-t-quote"><?php esc_html_e( 'Quote', 'ovr-core' ); ?></label>
                <textarea id="ovr-t-quote" name="ovr_t[quote]" rows="4" placeholder="<?php esc_attr_e( 'What the guest or owner said…', 'ovr-core' ); ?>"><?php echo esc_textarea( $quote ); ?></textarea>
            </div>

            <div class="row">
                <label class="lbl"><?php esc_html_e( 'Rating', 'ovr-core' ); ?></label>
                <span class="stars" data-ovr-stars>
                    <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                        <label class="<?php echo $i <= $rating ? 'on' : ''; ?>" data-val="<?php echo esc_attr( (string) $i ); ?>" title="<?php echo esc_attr( (string) $i ); ?>">
                            <input type="radio" name="ovr_t[rating]" value="<?php echo esc_attr( (string) $i ); ?>" <?php checked( $i, $rating ); ?>>&#9733;
                        </label>
                    <?php endfor; ?>
                </span>
                <p class="desc"><?php esc_html_e( 'Only testimonials at or above the minimum public rating (OVR Settings → Reputation) appear on the site.', 'ovr-core' ); ?></p>
            </div>

            <div class="row">
                <label class="lbl" for="ovr-t-role"><?php esc_html_e( 'Role / Location', 'ovr-core' ); ?></label>
                <input type="text" id="ovr-t-role" name="ovr_t[role]" value="<?php echo esc_attr( $role ); ?>" placeholder="<?php esc_attr_e( 'Verified Guest · Oak Village', 'ovr-core' ); ?>">
            </div>

            <div class="row">
                <label class="lbl" for="ovr-t-property"><?php esc_html_e( 'Linked Property (optional)', 'ovr-core' ); ?></label>
                <select id="ovr-t-property" name="ovr_t[property]">
                    <option value="0"><?php esc_html_e( '— None —', 'ovr-core' ); ?></option>
                    <?php foreach ( $properties as $pid ) : ?>
                        <option value="<?php echo esc_attr( (string) $pid ); ?>" <?php selected( $pid, $property_id ); ?>>
                            <?php echo esc_html( get_the_title( $pid ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="desc"><?php esc_html_e( 'Associate this testimonial with a listing. The featured image is used as the avatar.', 'ovr-core' ); ?></p>
            </div>

            <input type="hidden" name="ovr_t[source]" value="<?php echo esc_attr( $source ); ?>">
        </div>
        <script>
        ( function () {
            var wrap = document.querySelector( '.ovr-tmb [data-ovr-stars]' );
            if ( ! wrap ) return;
            var labels = Array.prototype.slice.call( wrap.querySelectorAll( 'label' ) );
            function paint( val ) {
                labels.forEach( function ( l ) {
                    l.classList.toggle( 'on', parseInt( l.getAttribute( 'data-val' ), 10 ) <= val );
                } );
            }
            labels.forEach( function ( l ) {
                l.addEventListener( 'mouseenter', function () { paint( parseInt( l.getAttribute( 'data-val' ), 10 ) ); } );
                l.addEventListener( 'click', function () {
                    var input = l.querySelector( 'input' );
                    if ( input ) input.checked = true;
                } );
            } );
            wrap.addEventListener( 'mouseleave', function () {
                var checked = wrap.querySelector( 'input:checked' );
                paint( checked ? parseInt( checked.value, 10 ) : 0 );
            } );
        } )();
        </script>
        <?php
    }

    public function save( int $post_id, \WP_Post $post ): void {
        if ( ! isset( $_POST[ self::NONCE_NAME ] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $raw = isset( $_POST['ovr_t'] ) && is_array( $_POST['ovr_t'] ) ? wp_unslash( $_POST['ovr_t'] ) : [];

        $quote  = sanitize_textarea_field( $raw['quote'] ?? '' );
        $rating = max( 1, min( 5, (int) ( $raw['rating'] ?? 5 ) ) );
        $role   = sanitize_text_field( $raw['role'] ?? '' );
        $prop   = absint( $raw['property'] ?? 0 );
        $source = in_array( ( $raw['source'] ?? 'manual' ), [ 'manual', 'review' ], true ) ? $raw['source'] : 'manual';

        update_post_meta( $post_id, T::META_QUOTE, $quote );
        update_post_meta( $post_id, T::META_RATING, $rating );
        update_post_meta( $post_id, T::META_ROLE, $role );
        update_post_meta( $post_id, T::META_PROPERTY, $prop );
        update_post_meta( $post_id, T::META_SOURCE, $source );
    }

    /**
     * @param array<string,string> $columns
     * @return array<string,string>
     */
    public function columns( array $columns ): array {
        $new = [];
        foreach ( $columns as $key => $label ) {
            if ( 'date' === $key ) {
                $new['ovr_rating']   = __( 'Rating', 'ovr-core' );
                $new['ovr_source']   = __( 'Source', 'ovr-core' );
                $new['ovr_property'] = __( 'Property', 'ovr-core' );
            }
            $new[ $key ] = $label;
        }
        return $new;
    }

    public function column_content( string $column, int $post_id ): void {
        switch ( $column ) {
            case 'ovr_rating':
                $rating = max( 0, min( 5, (int) get_post_meta( $post_id, T::META_RATING, true ) ) );
                $stars  = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
                echo '<span style="color:#cca72f;letter-spacing:1px">' . esc_html( $stars ) . '</span>';
                break;
            case 'ovr_source':
                $source = (string) get_post_meta( $post_id, T::META_SOURCE, true ) ?: 'manual';
                echo esc_html( 'review' === $source ? __( 'Review', 'ovr-core' ) : __( 'Manual', 'ovr-core' ) );
                break;
            case 'ovr_property':
                $prop = (int) get_post_meta( $post_id, T::META_PROPERTY, true );
                echo $prop ? esc_html( get_the_title( $prop ) ) : '—';
                break;
        }
    }
}
