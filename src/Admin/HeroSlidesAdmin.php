<?php
/**
 * Homepage Slides admin portal (Milestone 3 Feature 7).
 *
 * Adds a "Homepage Slides" submenu under the OVR Properties menu where an admin
 * builds the hero slideshow: an ordered list of slides, each a background image
 * plus optional heading / subtitle / CTA. The Elementor "OVR Hero Section"
 * widget (Background = Slideshow) renders these — the homepage stays Elementor-
 * native. Stored in wp_ovr_hero_slides via {@see \OVR\Frontend\HeroSlides}.
 *
 * @package OVR\Admin
 * @since   2.6.0
 */

namespace OVR\Admin;

use OVR\Frontend\HeroSlides;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HeroSlidesAdmin {

    public const PAGE_SLUG   = 'ovr-core-hero-slides';
    public const SAVE_ACTION = 'ovr_save_hero_slides';

    private string $hook_suffix = '';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_' . self::SAVE_ACTION, [ $this, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function register_page(): void {
        $this->hook_suffix = (string) add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Homepage Slides', 'ovr-core' ),
            __( 'Homepage Slides', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function enqueue( string $hook ): void {
        if ( $hook === $this->hook_suffix ) {
            wp_enqueue_media();
        }
    }

    private function page_url(): string {
        return add_query_arg(
            [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG ],
            admin_url( 'edit.php' )
        );
    }

    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do that.', 'ovr-core' ) );
        }
        check_admin_referer( self::SAVE_ACTION );

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanitized per-field below.
        $images    = isset( $_POST['ovr_slide_image'] )    ? (array) wp_unslash( $_POST['ovr_slide_image'] )    : [];
        $headings  = isset( $_POST['ovr_slide_heading'] )  ? (array) wp_unslash( $_POST['ovr_slide_heading'] )  : [];
        $subtitles = isset( $_POST['ovr_slide_subtitle'] ) ? (array) wp_unslash( $_POST['ovr_slide_subtitle'] ) : [];
        $cta_texts = isset( $_POST['ovr_slide_cta_text'] ) ? (array) wp_unslash( $_POST['ovr_slide_cta_text'] ) : [];
        $cta_urls  = isset( $_POST['ovr_slide_cta_url'] )  ? (array) wp_unslash( $_POST['ovr_slide_cta_url'] )  : [];
        $enabled   = isset( $_POST['ovr_slide_enabled'] )  ? (array) wp_unslash( $_POST['ovr_slide_enabled'] )  : [];
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput

        $rows = [];
        foreach ( $images as $i => $image_id ) {
            $rows[] = [
                'image_id'   => (int) $image_id,
                'heading'    => sanitize_text_field( (string) ( $headings[ $i ] ?? '' ) ),
                'subtitle'   => sanitize_textarea_field( (string) ( $subtitles[ $i ] ?? '' ) ),
                'cta_text'   => sanitize_text_field( (string) ( $cta_texts[ $i ] ?? '' ) ),
                'cta_url'    => esc_url_raw( (string) ( $cta_urls[ $i ] ?? '' ) ),
                // Checkbox value carries the row index when ticked.
                'is_enabled' => in_array( (string) $i, array_map( 'strval', $enabled ), true ),
            ];
        }

        HeroSlides::replace( $rows );

        wp_safe_redirect( add_query_arg( 'updated', '1', $this->page_url() ) );
        exit;
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $slides = HeroSlides::all();
        if ( empty( $slides ) ) {
            $slides = [ [ 'image_id' => 0, 'heading' => '', 'subtitle' => '', 'cta_text' => '', 'cta_url' => '', 'is_enabled' => 1 ] ];
        }
        ?>
        <div class="wrap ovr-adm">
            <div class="ovr-adm-wrap">
                <div class="ovr-adm-head">
                    <div>
                        <h1><?php esc_html_e( 'Homepage Slides', 'ovr-core' ); ?></h1>
                        <p><?php esc_html_e( 'Build the homepage hero slideshow. Each slide is a background image with an optional heading, subtitle and button. They rotate in the order shown here. To display them, edit the homepage in Elementor, open the "OVR Hero Section" widget and set Background to "Homepage Slideshow".', 'ovr-core' ); ?></p>
                    </div>
                </div>

                <?php if ( ! empty( $_GET['updated'] ) ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--success"><span class="material-symbols-outlined">check_circle</span><span><?php esc_html_e( 'Homepage slides saved.', 'ovr-core' ); ?></span></div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
                    <?php wp_nonce_field( self::SAVE_ACTION ); ?>

                    <div class="ovr-adm-card">
                        <div class="ovr-adm-card-body">
                            <div id="ovr-slides-list">
                                <?php foreach ( array_values( $slides ) as $i => $slide ) : $this->render_row( $slide, (int) $i ); endforeach; ?>
                            </div>

                            <p>
                                <button type="button" class="ovr-adm-btn ovr-adm-btn--ghost" id="ovr-add-slide"><span class="material-symbols-outlined">add</span><?php esc_html_e( 'Add Slide', 'ovr-core' ); ?></button>
                            </p>
                        </div>

                        <div class="ovr-adm-form-foot">
                            <button type="submit" class="ovr-adm-btn ovr-adm-btn--primary"><span class="material-symbols-outlined">save</span><?php esc_html_e( 'Save Slides', 'ovr-core' ); ?></button>
                        </div>
                    </div>
                </form>

                <script type="text/template" id="ovr-slide-row-tpl"><?php
                    $this->render_row( [ 'image_id' => 0, 'heading' => '', 'subtitle' => '', 'cta_text' => '', 'cta_url' => '', 'is_enabled' => 1 ] );
                ?></script>

                <style>
                    .ovr-adm .ovr-slide-row { display:flex; gap:16px; margin-bottom:14px; padding:14px; background:var(--surf); border:1px solid var(--gray-border); border-radius:var(--r-md); }
                    .ovr-adm .ovr-slide-row:last-child { margin-bottom:0; }
                    .ovr-adm .ovr-slide-media { flex:0 0 180px; }
                    .ovr-adm .ovr-slide-thumb { width:180px; height:108px; background:var(--gray-light); border:1px solid var(--gray-border); border-radius:var(--r-sm); overflow:hidden; display:flex; align-items:center; justify-content:center; color:var(--gray-mid); font-size:12px; }
                    .ovr-adm .ovr-slide-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
                    .ovr-adm .ovr-slide-media .ovr-adm-btn { width:100%; margin-top:8px; }
                    .ovr-adm .ovr-slide-fields { flex:1 1 auto; display:flex; flex-direction:column; gap:8px; }
                    .ovr-adm .ovr-slide-foot { display:flex; align-items:center; justify-content:space-between; gap:12px; }
                    .ovr-adm .ovr-slide-remove { display:inline-flex; align-items:center; gap:5px; color:var(--red); cursor:pointer; font-size:14px; font-weight:600; }
                    .ovr-adm .ovr-slide-remove:hover { color:var(--red); text-decoration:underline; }
                    .ovr-adm .ovr-slide-remove .material-symbols-outlined { font-size:18px; }
                </style>

                <script>
            ( function () {
                var list = document.getElementById( 'ovr-slides-list' );
                var tpl  = document.getElementById( 'ovr-slide-row-tpl' ).innerHTML;

                function reindex() {
                    // Keep the enabled-checkbox value in sync with each row's
                    // position so the server pairs it with the right slide.
                    list.querySelectorAll( '.ovr-slide-row' ).forEach( function ( row, i ) {
                        var cb = row.querySelector( '.ovr-slide-enabled' );
                        if ( cb ) { cb.value = i; }
                    } );
                }

                document.getElementById( 'ovr-add-slide' ).addEventListener( 'click', function () {
                    var wrap = document.createElement( 'div' );
                    wrap.innerHTML = tpl.trim();
                    list.appendChild( wrap.firstElementChild );
                    reindex();
                } );

                list.addEventListener( 'click', function ( e ) {
                    var remove = e.target.closest( '.ovr-slide-remove' );
                    if ( remove ) {
                        var rows = list.querySelectorAll( '.ovr-slide-row' );
                        if ( rows.length > 1 ) {
                            remove.closest( '.ovr-slide-row' ).remove();
                        } else {
                            // Never leave zero rows — blank the only one instead.
                            var row = remove.closest( '.ovr-slide-row' );
                            row.querySelectorAll( 'input[type=text], input[type=url], textarea' ).forEach( function ( f ) { f.value = ''; } );
                            row.querySelector( '.ovr-slide-image-id' ).value = '';
                            var t = row.querySelector( '.ovr-slide-thumb' );
                            t.innerHTML = '<?php echo esc_js( __( 'No image', 'ovr-core' ) ); ?>';
                        }
                        reindex();
                        return;
                    }

                    var pick = e.target.closest( '.ovr-slide-pick' );
                    if ( pick ) {
                        e.preventDefault();
                        var prow  = pick.closest( '.ovr-slide-row' );
                        var frame = wp.media( {
                            title: '<?php echo esc_js( __( 'Select Slide Image', 'ovr-core' ) ); ?>',
                            library: { type: 'image' },
                            multiple: false,
                            button: { text: '<?php echo esc_js( __( 'Use this image', 'ovr-core' ) ); ?>' }
                        } );
                        frame.on( 'select', function () {
                            var att = frame.state().get( 'selection' ).first().toJSON();
                            prow.querySelector( '.ovr-slide-image-id' ).value = att.id;
                            var src = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
                            prow.querySelector( '.ovr-slide-thumb' ).innerHTML = '<img src="' + src + '" alt="">';
                        } );
                        frame.open();
                    }
                } );

                reindex();
            } )();
            </script>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $slide
     * @param int                  $index Row position; primes the enabled-checkbox
     *                                    value so it pairs with the right slide even
     *                                    before the JS reindex runs.
     */
    private function render_row( array $slide, int $index = 0 ): void {
        $image_id = (int) ( $slide['image_id'] ?? 0 );
        $thumb    = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'medium' ) : '';
        $enabled  = ! empty( $slide['is_enabled'] );
        ?>
        <div class="ovr-slide-row">
            <div class="ovr-slide-media">
                <input type="hidden" name="ovr_slide_image[]" class="ovr-slide-image-id" value="<?php echo esc_attr( (string) $image_id ); ?>">
                <div class="ovr-slide-thumb">
                    <?php if ( $thumb ) : ?>
                        <img src="<?php echo esc_url( $thumb ); ?>" alt="">
                    <?php else : ?>
                        <?php esc_html_e( 'No image', 'ovr-core' ); ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="ovr-adm-btn ovr-adm-btn--ghost ovr-adm-btn--sm ovr-slide-pick"><span class="material-symbols-outlined">image</span><?php esc_html_e( 'Select Image', 'ovr-core' ); ?></button>
            </div>

            <div class="ovr-slide-fields">
                <input type="text" class="ovr-adm-input" name="ovr_slide_heading[]" placeholder="<?php esc_attr_e( 'Heading (optional)', 'ovr-core' ); ?>" value="<?php echo esc_attr( (string) ( $slide['heading'] ?? '' ) ); ?>">
                <textarea class="ovr-adm-textarea" name="ovr_slide_subtitle[]" rows="2" placeholder="<?php esc_attr_e( 'Subtitle (optional)', 'ovr-core' ); ?>"><?php echo esc_textarea( (string) ( $slide['subtitle'] ?? '' ) ); ?></textarea>
                <div style="display:flex;gap:8px">
                    <input type="text" class="ovr-adm-input" name="ovr_slide_cta_text[]" placeholder="<?php esc_attr_e( 'Button text (optional)', 'ovr-core' ); ?>" value="<?php echo esc_attr( (string) ( $slide['cta_text'] ?? '' ) ); ?>" style="flex:0 0 220px">
                    <input type="url" class="ovr-adm-input" name="ovr_slide_cta_url[]" placeholder="<?php esc_attr_e( 'Button link (https://…)', 'ovr-core' ); ?>" value="<?php echo esc_attr( (string) ( $slide['cta_url'] ?? '' ) ); ?>">
                </div>
                <div class="ovr-slide-foot">
                    <label class="ovr-adm-check">
                        <input type="checkbox" class="ovr-slide-enabled" name="ovr_slide_enabled[]" value="<?php echo esc_attr( (string) $index ); ?>" <?php checked( $enabled ); ?>>
                        <?php esc_html_e( 'Visible in slideshow', 'ovr-core' ); ?>
                    </label>
                    <a class="ovr-slide-remove" role="button"><span class="material-symbols-outlined">delete</span><?php esc_html_e( 'Remove slide', 'ovr-core' ); ?></a>
                </div>
            </div>
        </div>
        <?php
    }
}
