<?php
/**
 * Featured Cities admin portal.
 *
 * Adds a "Featured Cities" submenu under the OVR Properties menu where an admin
 * can manage the cities shown in the strip at the top of the Search Results
 * page. Each entry is a name + an image (Media Library). Stored as a simple
 * list in the `ovr_featured_cities` option; order = display order.
 *
 * @package OVR\Admin
 * @since   1.0.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FeaturedCities {

    public const OPTION      = 'ovr_featured_cities';
    public const PAGE_SLUG   = 'ovr-core-featured-cities';
    public const SAVE_ACTION = 'ovr_save_featured_cities';

    private string $hook_suffix = '';

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_' . self::SAVE_ACTION, [ $this, 'handle_save' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function register_page(): void {
        $this->hook_suffix = (string) add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Featured Cities', 'ovr-core' ),
            __( 'Featured Cities', 'ovr-core' ),
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

    /**
     * Raw stored rows: [ [ 'name' => string, 'image_id' => int ], … ].
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_raw(): array {
        $items = get_option( self::OPTION, [] );
        return is_array( $items ) ? $items : [];
    }

    /**
     * Resolved items for the front end (name + image URL). Skips empty names;
     * falls back to the bundled placeholder when an image is missing.
     *
     * @return array<int,array{name:string,image:string}>
     */
    public static function get_items(): array {
        $out = [];
        foreach ( self::get_raw() as $item ) {
            $name = trim( (string) ( $item['name'] ?? '' ) );
            if ( '' === $name ) {
                continue;
            }
            $image_id = (int) ( $item['image_id'] ?? 0 );
            $image    = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'large' ) : '';
            if ( '' === $image ) {
                $image = OVR_PLUGIN_URL . 'assets/images/ovr-placeholder.jpg';
            }
            $out[] = [ 'name' => $name, 'image' => $image ];
        }
        return $out;
    }

    public function handle_save(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You are not allowed to do that.', 'ovr-core' ) );
        }
        check_admin_referer( self::SAVE_ACTION );

        $names  = isset( $_POST['ovr_city_name'] )  ? (array) wp_unslash( $_POST['ovr_city_name'] )  : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $images = isset( $_POST['ovr_city_image'] ) ? (array) wp_unslash( $_POST['ovr_city_image'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        $clean = [];
        foreach ( $names as $i => $raw_name ) {
            $name = sanitize_text_field( (string) $raw_name );
            if ( '' === trim( $name ) ) {
                continue;
            }
            $clean[] = [
                'name'     => $name,
                'image_id' => (int) ( $images[ $i ] ?? 0 ),
            ];
        }

        update_option( self::OPTION, $clean );

        wp_safe_redirect( add_query_arg( [
            'post_type' => 'ovr_property',
            'page'      => self::PAGE_SLUG,
            'updated'   => '1',
        ], admin_url( 'edit.php' ) ) );
        exit;
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $items = self::get_raw();
        if ( empty( $items ) ) {
            $items = [ [ 'name' => '', 'image_id' => 0 ] ]; // Start with one blank row.
        }
        ?>
        <div class="wrap ovr-adm">
            <div class="ovr-adm-wrap">
                <div class="ovr-adm-head">
                    <div>
                        <h1><?php esc_html_e( 'Featured Cities', 'ovr-core' ); ?></h1>
                        <p><?php esc_html_e( 'These cities appear in the strip at the top of the Search Results page, in the order listed here. Each links to a search for that city. If you add none, the page falls back to your villages automatically.', 'ovr-core' ); ?></p>
                    </div>
                </div>

                <?php if ( ! empty( $_GET['updated'] ) ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--success"><span class="material-symbols-outlined">check_circle</span><span><?php esc_html_e( 'Featured cities saved.', 'ovr-core' ); ?></span></div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
                    <?php wp_nonce_field( self::SAVE_ACTION ); ?>

                    <div class="ovr-adm-card">
                        <div class="ovr-adm-card-body">
                            <div id="ovr-cities-list">
                                <?php foreach ( $items as $item ) :
                                    $name     = (string) ( $item['name'] ?? '' );
                                    $image_id = (int) ( $item['image_id'] ?? 0 );
                                    $thumb    = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
                                    $this->render_row( $name, $image_id, $thumb );
                                endforeach; ?>
                            </div>

                            <p><button type="button" class="ovr-adm-btn ovr-adm-btn--ghost" id="ovr-add-city"><span class="material-symbols-outlined">add</span><?php esc_html_e( 'Add City', 'ovr-core' ); ?></button></p>
                        </div>

                        <div class="ovr-adm-form-foot">
                            <button type="submit" class="ovr-adm-btn ovr-adm-btn--primary"><span class="material-symbols-outlined">save</span><?php esc_html_e( 'Save Featured Cities', 'ovr-core' ); ?></button>
                        </div>
                    </div>
                </form>

                <script type="text/template" id="ovr-city-row-tpl"><?php $this->render_row( '', 0, '' ); ?></script>

                <style>
                    .ovr-adm .ovr-city-row { display:flex; align-items:center; gap:12px; margin-bottom:10px; padding:10px 12px; background:var(--surf); border:1px solid var(--gray-border); border-radius:var(--r-sm); }
                    .ovr-adm .ovr-city-row:last-child { margin-bottom:0; }
                    .ovr-adm .ovr-city-row .ovr-city-name { flex:1 1 auto; }
                    .ovr-adm .ovr-city-thumb { width:56px; height:42px; flex:0 0 auto; background:var(--gray-light); border:1px solid var(--gray-border); border-radius:var(--r-sm); overflow:hidden; }
                    .ovr-adm .ovr-city-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
                    .ovr-adm .ovr-city-remove { display:inline-flex; align-items:center; gap:5px; color:var(--red); cursor:pointer; font-size:14px; font-weight:600; flex:0 0 auto; }
                    .ovr-adm .ovr-city-remove:hover { text-decoration:underline; }
                    .ovr-adm .ovr-city-remove .material-symbols-outlined { font-size:18px; }
                </style>

                <script>
            ( function () {
                var list = document.getElementById( 'ovr-cities-list' );
                var tpl  = document.getElementById( 'ovr-city-row-tpl' ).innerHTML;

                document.getElementById( 'ovr-add-city' ).addEventListener( 'click', function () {
                    var wrap = document.createElement( 'div' );
                    wrap.innerHTML = tpl.trim();
                    list.appendChild( wrap.firstElementChild );
                } );

                list.addEventListener( 'click', function ( e ) {
                    var remove = e.target.closest( '.ovr-city-remove' );
                    if ( remove ) {
                        var rows = list.querySelectorAll( '.ovr-city-row' );
                        var row  = remove.closest( '.ovr-city-row' );
                        if ( rows.length > 1 ) {
                            row.remove();
                        } else {
                            row.querySelector( '.ovr-city-name' ).value = '';
                            row.querySelector( '.ovr-city-image-id' ).value = '';
                            var t = row.querySelector( '.ovr-city-thumb' );
                            t.style.display = 'none';
                            t.querySelector( 'img' ).src = '';
                        }
                        return;
                    }

                    var pick = e.target.closest( '.ovr-city-pick' );
                    if ( pick ) {
                        e.preventDefault();
                        var prow  = pick.closest( '.ovr-city-row' );
                        var frame = wp.media( {
                            title: '<?php echo esc_js( __( 'Select City Image', 'ovr-core' ) ); ?>',
                            library: { type: 'image' },
                            multiple: false,
                            button: { text: '<?php echo esc_js( __( 'Use this image', 'ovr-core' ) ); ?>' }
                        } );
                        frame.on( 'select', function () {
                            var att = frame.state().get( 'selection' ).first().toJSON();
                            prow.querySelector( '.ovr-city-image-id' ).value = att.id;
                            var t   = prow.querySelector( '.ovr-city-thumb' );
                            var img = t.querySelector( 'img' );
                            img.src = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;
                            t.style.display = '';
                        } );
                        frame.open();
                    }
                } );
            } )();
            </script>
            </div>
        </div>
        <?php
    }

    private function render_row( string $name, int $image_id, string $thumb ): void {
        ?>
        <div class="ovr-city-row">
            <input type="text" name="ovr_city_name[]" class="ovr-adm-input ovr-city-name" placeholder="<?php esc_attr_e( 'City name (e.g. Spanish Springs)', 'ovr-core' ); ?>" value="<?php echo esc_attr( $name ); ?>">
            <input type="hidden" name="ovr_city_image[]" class="ovr-city-image-id" value="<?php echo esc_attr( (string) $image_id ); ?>">
            <span class="ovr-city-thumb"<?php echo $thumb ? '' : ' style="display:none"'; ?>>
                <img src="<?php echo esc_url( $thumb ); ?>" alt="">
            </span>
            <button type="button" class="ovr-adm-btn ovr-adm-btn--ghost ovr-adm-btn--sm ovr-city-pick"><span class="material-symbols-outlined">image</span><?php esc_html_e( 'Select Image', 'ovr-core' ); ?></button>
            <a class="ovr-city-remove" role="button"><span class="material-symbols-outlined">delete</span><?php esc_html_e( 'Remove', 'ovr-core' ); ?></a>
        </div>
        <?php
    }
}
