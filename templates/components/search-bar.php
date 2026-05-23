<?php
/**
 * Search Bar Component (reusable).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<?php
$show_type   = isset( $show_type )   ? (bool) $show_type   : true;
$show_guests = isset( $show_guests ) ? (bool) $show_guests : false;
$placeholder = $placeholder ?? __( 'Search by village or keyword…', 'ovr-core' );
?>
<form class="ovr-search-pill" action="<?php echo esc_url( \OVR\Core\Pages::get_page_url( 'ovr_page_search' ) ); ?>" method="get">
    <div class="ovr-search-field">
        <span class="ovr-search-field-label"><?php esc_html_e( 'Location', 'ovr-core' ); ?></span>
        <input type="text" name="keyword" placeholder="<?php echo esc_attr( $placeholder ); ?>">
    </div>

    <?php if ( $show_type ) : ?>
        <div class="ovr-search-divider"></div>
        <div class="ovr-search-field">
            <span class="ovr-search-field-label"><?php esc_html_e( 'Property Type', 'ovr-core' ); ?></span>
            <select name="property_type">
                <option value=""><?php esc_html_e( 'All Types', 'ovr-core' ); ?></option>
                <?php
                $types = get_terms( [ 'taxonomy' => 'ovr_property_type', 'hide_empty' => true ] );
                if ( ! is_wp_error( $types ) ) :
                    foreach ( $types as $type ) : ?>
                        <option value="<?php echo esc_attr( $type->slug ); ?>"><?php echo esc_html( $type->name ); ?></option>
                    <?php endforeach;
                endif; ?>
            </select>
        </div>
    <?php endif; ?>

    <?php if ( $show_guests ) : ?>
        <div class="ovr-search-divider"></div>
        <div class="ovr-search-field">
            <span class="ovr-search-field-label"><?php esc_html_e( 'Guests', 'ovr-core' ); ?></span>
            <input type="number" name="guests" min="1" max="20" placeholder="<?php esc_attr_e( 'Any', 'ovr-core' ); ?>">
        </div>
    <?php endif; ?>

    <button type="submit" class="ovr-btn ovr-btn-primary ovr-btn-pill ovr-search-submit" aria-label="<?php esc_attr_e( 'Search', 'ovr-core' ); ?>">
        <span class="material-symbols-outlined">search</span>
    </button>
</form>
