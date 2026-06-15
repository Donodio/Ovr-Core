<?php
/**
 * CSV Migration Importer (Milestone 3 Feature 14).
 *
 * A spreadsheet importer with a column-mapping UI: upload a CSV, map each
 * column to a listing field (title / content / meta / taxonomy / owner / image
 * URLs), dry-run a preview, then import — creating ovr_property listings and
 * side-loading any image URLs. Built per the client decision to migrate from a
 * client-provided CSV export rather than a live WordPress pull.
 *
 * @package OVR\Admin
 * @since   2.8.0
 */

namespace OVR\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MigrationImporter {

    public const PAGE_SLUG = 'ovr-core-import';
    private const MAX_ROWS  = 1000;

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_post_ovr_import_upload', [ $this, 'handle_upload' ] );
        add_action( 'admin_post_ovr_import_run', [ $this, 'handle_run' ] );
    }

    public function register_page(): void {
        add_submenu_page(
            'edit.php?post_type=ovr_property',
            __( 'Import Listings (CSV)', 'ovr-core' ),
            __( 'Import (CSV)', 'ovr-core' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    private function page_url(): string {
        return add_query_arg( [ 'post_type' => 'ovr_property', 'page' => self::PAGE_SLUG ], admin_url( 'edit.php' ) );
    }

    private function transient_key(): string {
        return 'ovr_import_' . get_current_user_id();
    }

    /**
     * The fields a CSV column can map to. Group prefix is for the optgroup label.
     *
     * @return array<string, array<string, string>> group => [ key => label ]
     */
    public static function target_fields(): array {
        return [
            __( 'Core', 'ovr-core' ) => [
                'title'          => __( 'Title', 'ovr-core' ),
                'content'        => __( 'Description (content)', 'ovr-core' ),
                'excerpt'        => __( 'Short description (excerpt)', 'ovr-core' ),
                'owner_email'    => __( 'Owner email', 'ovr-core' ),
                'status'         => __( 'Listing status (active/inactive)', 'ovr-core' ),
            ],
            __( 'Details (meta)', 'ovr-core' ) => [
                'bedrooms'     => __( 'Bedrooms', 'ovr-core' ),
                'bathrooms'    => __( 'Bathrooms', 'ovr-core' ),
                'beds'         => __( 'Beds', 'ovr-core' ),
                'max_guests'   => __( 'Max guests', 'ovr-core' ),
                'sqft'         => __( 'Square feet', 'ovr-core' ),
                'base_price'   => __( 'Base price', 'ovr-core' ),
                'min_stay'     => __( 'Minimum stay (nights)', 'ovr-core' ),
                'pets_allowed' => __( 'Pets allowed (yes/no)', 'ovr-core' ),
                'address'      => __( 'Address', 'ovr-core' ),
                'city'         => __( 'City', 'ovr-core' ),
                'state'        => __( 'State', 'ovr-core' ),
                'zip'          => __( 'ZIP / Postcode', 'ovr-core' ),
                'country'      => __( 'Country', 'ovr-core' ),
                'village_name' => __( 'Village name (text)', 'ovr-core' ),
                'latitude'     => __( 'Latitude', 'ovr-core' ),
                'longitude'    => __( 'Longitude', 'ovr-core' ),
                'video_url'    => __( 'Video URL', 'ovr-core' ),
                'ical_url'     => __( 'iCal feed URL', 'ovr-core' ),
            ],
            __( 'Taxonomies', 'ovr-core' ) => [
                'tax_village'       => __( 'Village (taxonomy)', 'ovr-core' ),
                'tax_property_type' => __( 'Property type', 'ovr-core' ),
                'tax_amenity'       => __( 'Amenities (comma-separated)', 'ovr-core' ),
                'tax_rental_type'   => __( 'Rental type', 'ovr-core' ),
            ],
            __( 'Images', 'ovr-core' ) => [
                'featured_image' => __( 'Featured image URL', 'ovr-core' ),
                'gallery'        => __( 'Gallery image URLs (comma-separated)', 'ovr-core' ),
            ],
        ];
    }

    /** Flat key→label map of all targets. */
    private static function flat_targets(): array {
        $flat = [];
        foreach ( self::target_fields() as $fields ) {
            $flat += $fields;
        }
        return $flat;
    }

    /* ───────────────────────── Render ───────────────────────── */

    public function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to import.', 'ovr-core' ) );
        }
        if ( ! empty( $_GET['reset'] ) ) {
            delete_transient( $this->transient_key() );
            delete_transient( $this->transient_key() . '_result' );
        }
        $data = get_transient( $this->transient_key() );
        if ( is_array( $data ) && ! empty( $data['header'] ) ) {
            $this->render_mapping( $data );
        } else {
            $this->render_upload();
        }
    }

    private function render_upload(): void {
        $err = sanitize_key( wp_unslash( $_GET['err'] ?? '' ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import Listings (CSV)', 'ovr-core' ); ?></h1>
            <p class="description" style="max-width:720px">
                <?php esc_html_e( 'Upload a CSV export of your listings. The first row must be column headers. On the next screen you map each column to a listing field, preview a dry run, then import. Image columns may contain public image URLs, which are downloaded into the Media Library.', 'ovr-core' ); ?>
            </p>
            <?php if ( 'parse' === $err ) : ?>
                <div class="notice notice-error"><p><?php esc_html_e( 'Could not read that file as CSV. Make sure it is a .csv with a header row.', 'ovr-core' ); ?></p></div>
            <?php elseif ( 'upload' === $err ) : ?>
                <div class="notice notice-error"><p><?php esc_html_e( 'Upload failed. Please choose a .csv file.', 'ovr-core' ); ?></p></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px">
                <input type="hidden" name="action" value="ovr_import_upload">
                <?php wp_nonce_field( 'ovr_import_upload' ); ?>
                <input type="file" name="csv" accept=".csv,text/csv" required>
                <?php submit_button( __( 'Upload & Continue', 'ovr-core' ) ); ?>
            </form>
        </div>
        <?php
    }

    private function render_mapping( array $data ): void {
        $header   = (array) $data['header'];
        $rows     = (array) $data['rows'];
        $targets  = self::target_fields();
        $auto     = $this->auto_map( $header );
        $statuses = [ 'publish' => __( 'Published', 'ovr-core' ), 'draft' => __( 'Draft', 'ovr-core' ) ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import Listings — Map Columns', 'ovr-core' ); ?></h1>
            <p>
                <?php printf( esc_html__( 'File: %1$s · %2$s data rows detected.', 'ovr-core' ), '<strong>' . esc_html( (string) ( $data['filename'] ?? 'upload.csv' ) ) . '</strong>', '<strong>' . number_format_i18n( count( $rows ) ) . '</strong>' ); ?>
                · <a href="<?php echo esc_url( add_query_arg( 'reset', '1', $this->page_url() ) ); ?>"><?php esc_html_e( 'Start over', 'ovr-core' ); ?></a>
            </p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="ovr_import_run">
                <?php wp_nonce_field( 'ovr_import_run' ); ?>

                <table class="wp-list-table widefat fixed striped" style="max-width:900px">
                    <thead><tr>
                        <th style="width:34%"><?php esc_html_e( 'CSV Column', 'ovr-core' ); ?></th>
                        <th style="width:30%"><?php esc_html_e( 'Sample', 'ovr-core' ); ?></th>
                        <th style="width:36%"><?php esc_html_e( 'Maps to', 'ovr-core' ); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ( $header as $i => $col ) :
                        $sample = '';
                        foreach ( $rows as $r ) {
                            if ( isset( $r[ $i ] ) && '' !== trim( (string) $r[ $i ] ) ) { $sample = (string) $r[ $i ]; break; }
                        }
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( (string) $col ); ?></strong></td>
                            <td><span style="color:#646970"><?php echo esc_html( mb_strimwidth( $sample, 0, 50, '…' ) ); ?></span></td>
                            <td>
                                <select name="map[<?php echo (int) $i; ?>]">
                                    <option value=""><?php esc_html_e( '— Ignore —', 'ovr-core' ); ?></option>
                                    <?php foreach ( $targets as $group => $fields ) : ?>
                                        <optgroup label="<?php echo esc_attr( $group ); ?>">
                                            <?php foreach ( $fields as $key => $label ) : ?>
                                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $auto[ $i ] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h2><?php esc_html_e( 'Options', 'ovr-core' ); ?></h2>
                <table class="form-table" style="max-width:760px">
                    <tr>
                        <th><?php esc_html_e( 'Default owner', 'ovr-core' ); ?></th>
                        <td><?php
                            wp_dropdown_users( [ 'name' => 'default_owner', 'show_option_none' => __( '— Current admin —', 'ovr-core' ), 'option_none_value' => 0 ] );
                        ?><p class="description"><?php esc_html_e( 'Used when no Owner email column is mapped or an email is not found.', 'ovr-core' ); ?></p></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Post status', 'ovr-core' ); ?></th>
                        <td><select name="post_status"><?php foreach ( $statuses as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Match existing by Title', 'ovr-core' ); ?></th>
                        <td><label><input type="checkbox" name="dedupe_title" value="1"> <?php esc_html_e( 'Update an existing listing instead of creating a duplicate when the title matches.', 'ovr-core' ); ?></label></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Import images', 'ovr-core' ); ?></th>
                        <td><label><input type="checkbox" name="import_images" value="1" checked> <?php esc_html_e( 'Download featured / gallery image URLs into the Media Library (slower).', 'ovr-core' ); ?></label></td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="do" value="dryrun" class="button"><?php esc_html_e( 'Dry Run (preview)', 'ovr-core' ); ?></button>
                    <button type="submit" name="do" value="import" class="button button-primary" onclick="return confirm('<?php echo esc_js( __( 'Import these listings now?', 'ovr-core' ) ); ?>')"><?php esc_html_e( 'Run Import', 'ovr-core' ); ?></button>
                </p>
            </form>

            <?php $this->maybe_render_results(); ?>
        </div>
        <?php
    }

    /** Render results stashed by handle_run() in a one-shot transient. */
    private function maybe_render_results(): void {
        $key = $this->transient_key() . '_result';
        $res = get_transient( $key );
        if ( ! is_array( $res ) ) {
            return;
        }
        delete_transient( $key );
        $is_dry = ! empty( $res['dry'] );
        ?>
        <hr>
        <h2><?php echo $is_dry ? esc_html__( 'Dry Run Preview', 'ovr-core' ) : esc_html__( 'Import Results', 'ovr-core' ); ?></h2>
        <p>
            <?php printf(
                esc_html__( '%1$s created · %2$s updated · %3$s skipped', 'ovr-core' ),
                '<strong>' . (int) $res['created'] . '</strong>',
                '<strong>' . (int) $res['updated'] . '</strong>',
                '<strong>' . (int) $res['skipped'] . '</strong>'
            ); ?>
            <?php if ( $is_dry ) : ?><em><?php esc_html_e( '(nothing was written — this is a preview)', 'ovr-core' ); ?></em><?php endif; ?>
        </p>
        <?php if ( ! empty( $res['messages'] ) ) : ?>
            <ul style="max-height:280px;overflow:auto;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 12px 12px 28px;max-width:900px">
                <?php foreach ( (array) $res['messages'] as $m ) : ?>
                    <li><?php echo esc_html( (string) $m ); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
    }

    /* ───────────────────────── Handlers ───────────────────────── */

    public function handle_upload(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        check_admin_referer( 'ovr_import_upload' );

        if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
            wp_safe_redirect( add_query_arg( 'err', 'upload', $this->page_url() ) );
            exit;
        }

        $parsed = $this->parse_csv( (string) $_FILES['csv']['tmp_name'] );
        if ( null === $parsed ) {
            wp_safe_redirect( add_query_arg( 'err', 'parse', $this->page_url() ) );
            exit;
        }

        $parsed['filename'] = sanitize_file_name( (string) ( $_FILES['csv']['name'] ?? 'upload.csv' ) );
        set_transient( $this->transient_key(), $parsed, HOUR_IN_SECONDS );
        wp_safe_redirect( $this->page_url() );
        exit;
    }

    public function handle_run(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'ovr-core' ) );
        }
        check_admin_referer( 'ovr_import_run' );

        $data = get_transient( $this->transient_key() );
        if ( ! is_array( $data ) || empty( $data['header'] ) ) {
            wp_safe_redirect( $this->page_url() );
            exit;
        }

        $map           = isset( $_POST['map'] ) && is_array( $_POST['map'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['map'] ) ) : [];
        $is_dry        = 'import' !== ( $_POST['do'] ?? 'dryrun' );
        $default_owner = (int) ( $_POST['default_owner'] ?? 0 ) ?: get_current_user_id();
        $post_status   = 'draft' === ( $_POST['post_status'] ?? 'publish' ) ? 'draft' : 'publish';
        $dedupe_title  = ! empty( $_POST['dedupe_title'] );
        $import_images = ! empty( $_POST['import_images'] );

        $result = $this->process( (array) $data['rows'], $map, [
            'dry'           => $is_dry,
            'default_owner' => $default_owner,
            'post_status'   => $post_status,
            'dedupe_title'  => $dedupe_title,
            'import_images' => $import_images,
        ] );

        set_transient( $this->transient_key() . '_result', $result, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( $this->page_url() );
        exit;
    }

    /* ───────────────────────── Core import ───────────────────────── */

    /**
     * @param array<int,array<int,string>> $rows
     * @param array<int,string>            $map  column index → target key
     * @param array<string,mixed>          $opts
     * @return array<string,mixed>
     */
    private function process( array $rows, array $map, array $opts ): array {
        $dry      = ! empty( $opts['dry'] );
        $created  = 0;
        $updated  = 0;
        $skipped  = 0;
        $messages = [];

        if ( ! $dry ) {
            @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.PHP.IniSet
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Has the user mapped a title? Without one we can't name listings.
        if ( ! in_array( 'title', $map, true ) ) {
            return [ 'dry' => $dry, 'created' => 0, 'updated' => 0, 'skipped' => count( $rows ),
                'messages' => [ __( 'No column is mapped to Title — map one and try again.', 'ovr-core' ) ] ];
        }

        $row_no = 0;
        foreach ( $rows as $row ) {
            $row_no++;
            if ( $row_no > self::MAX_ROWS ) {
                $messages[] = sprintf( __( 'Stopped at the %d-row limit; split the file to import the rest.', 'ovr-core' ), self::MAX_ROWS );
                break;
            }

            // Collect mapped values for this row.
            $fields = [];
            foreach ( $map as $idx => $target ) {
                if ( '' === $target ) {
                    continue;
                }
                $fields[ $target ] = isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';
            }

            $title = $fields['title'] ?? '';
            if ( '' === $title ) {
                $skipped++;
                $messages[] = sprintf( __( 'Row %d: skipped (empty title).', 'ovr-core' ), $row_no );
                continue;
            }

            // Match existing by title?
            $existing = 0;
            if ( ! empty( $opts['dedupe_title'] ) ) {
                $found = get_page_by_title( $title, OBJECT, 'ovr_property' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_title_get_page_by_title
                if ( $found ) {
                    $existing = (int) $found->ID;
                }
            }

            if ( $dry ) {
                if ( $row_no <= 15 ) {
                    $messages[] = sprintf(
                        '%s "%s"%s',
                        $existing ? __( 'Update', 'ovr-core' ) : __( 'Create', 'ovr-core' ),
                        $title,
                        $this->preview_extras( $fields )
                    );
                }
                $existing ? $updated++ : $created++;
                continue;
            }

            $post_id = $this->upsert_listing( $existing, $title, $fields, $opts, $messages, $row_no );
            if ( $post_id <= 0 ) {
                $skipped++;
                continue;
            }
            $existing ? $updated++ : $created++;
        }

        if ( ! $dry ) {
            $messages[] = __( 'Import complete.', 'ovr-core' );
        }

        return compact( 'dry', 'created', 'updated', 'skipped', 'messages' );
    }

    /**
     * Create or update one listing from a row's mapped fields.
     *
     * @param array<string,string> $fields
     * @param array<string,mixed>  $opts
     * @param array<int,string>    $messages (by ref)
     */
    private function upsert_listing( int $existing, string $title, array $fields, array $opts, array &$messages, int $row_no ): int {
        // Owner from email column, else default.
        $owner = (int) $opts['default_owner'];
        if ( ! empty( $fields['owner_email'] ) ) {
            $user = get_user_by( 'email', $fields['owner_email'] );
            if ( $user ) {
                $owner = (int) $user->ID;
            }
        }

        $postarr = [
            'post_type'   => 'ovr_property',
            'post_title'  => $title,
            'post_status' => (string) $opts['post_status'],
            'post_author' => $owner,
        ];
        if ( isset( $fields['content'] ) ) {
            $postarr['post_content'] = wp_kses_post( $fields['content'] );
        }
        if ( isset( $fields['excerpt'] ) ) {
            $postarr['post_excerpt'] = sanitize_textarea_field( $fields['excerpt'] );
        }

        if ( $existing ) {
            $postarr['ID'] = $existing;
            $post_id       = wp_update_post( $postarr, true );
        } else {
            $post_id = wp_insert_post( $postarr, true );
        }
        if ( is_wp_error( $post_id ) || ! $post_id ) {
            $messages[] = sprintf( __( 'Row %d: failed to save "%s".', 'ovr-core' ), $row_no, $title );
            return 0;
        }
        $post_id = (int) $post_id;

        // Meta + taxonomy + images.
        $this->apply_meta( $post_id, $fields );
        $this->apply_taxonomies( $post_id, $fields );
        if ( ! empty( $opts['import_images'] ) ) {
            $this->apply_images( $post_id, $fields, $messages, $row_no );
        }

        // Sensible defaults for a freshly imported listing.
        if ( ! $existing ) {
            update_post_meta( $post_id, '_ovr_admin_status', 'approved' );
            if ( empty( get_post_meta( $post_id, '_ovr_listing_status', true ) ) ) {
                update_post_meta( $post_id, '_ovr_listing_status', 'active' );
            }
        }

        return $post_id;
    }

    /** @param array<string,string> $fields */
    private function apply_meta( int $post_id, array $fields ): void {
        $int   = [ 'bedrooms', 'beds', 'max_guests', 'sqft', 'min_stay' ];
        $dec   = [ 'bathrooms', 'base_price', 'latitude', 'longitude' ];
        $text  = [ 'address', 'city', 'state', 'zip', 'country', 'village_name' ];
        $url   = [ 'video_url', 'ical_url' ];

        foreach ( $int as $k ) {
            if ( isset( $fields[ $k ] ) && '' !== $fields[ $k ] ) {
                update_post_meta( $post_id, '_ovr_' . $k, absint( $fields[ $k ] ) );
            }
        }
        foreach ( $dec as $k ) {
            if ( isset( $fields[ $k ] ) && '' !== $fields[ $k ] ) {
                update_post_meta( $post_id, '_ovr_' . $k, (float) $fields[ $k ] );
            }
        }
        foreach ( $text as $k ) {
            if ( isset( $fields[ $k ] ) && '' !== $fields[ $k ] ) {
                update_post_meta( $post_id, '_ovr_' . $k, sanitize_text_field( $fields[ $k ] ) );
            }
        }
        foreach ( $url as $k ) {
            if ( isset( $fields[ $k ] ) && '' !== $fields[ $k ] ) {
                update_post_meta( $post_id, '_ovr_' . $k, esc_url_raw( $fields[ $k ] ) );
            }
        }
        if ( isset( $fields['pets_allowed'] ) && '' !== $fields['pets_allowed'] ) {
            update_post_meta( $post_id, '_ovr_pets_allowed', $this->truthy( $fields['pets_allowed'] ) ? 1 : 0 );
        }
        if ( isset( $fields['status'] ) && '' !== $fields['status'] ) {
            $s = sanitize_key( $fields['status'] );
            update_post_meta( $post_id, '_ovr_listing_status', in_array( $s, [ 'active', 'inactive', 'pending_renewal' ], true ) ? $s : 'active' );
        }
    }

    /** @param array<string,string> $fields */
    private function apply_taxonomies( int $post_id, array $fields ): void {
        $tax_map = [
            'tax_village'       => 'ovr_village',
            'tax_property_type' => 'ovr_property_type',
            'tax_amenity'       => 'ovr_amenity',
            'tax_rental_type'   => 'ovr_rental_type',
        ];
        foreach ( $tax_map as $field => $tax ) {
            if ( empty( $fields[ $field ] ) || ! taxonomy_exists( $tax ) ) {
                continue;
            }
            $names = array_filter( array_map( 'trim', explode( ',', $fields[ $field ] ) ) );
            if ( $names ) {
                wp_set_object_terms( $post_id, $names, $tax, false );
            }
        }
        // Mirror the village taxonomy into the village-name meta if not set.
        if ( ! empty( $fields['tax_village'] ) && empty( $fields['village_name'] ) ) {
            $first = trim( explode( ',', $fields['tax_village'] )[0] );
            if ( '' !== $first ) {
                update_post_meta( $post_id, '_ovr_village_name', sanitize_text_field( $first ) );
            }
        }
    }

    /**
     * @param array<string,string> $fields
     * @param array<int,string>    $messages (by ref)
     */
    private function apply_images( int $post_id, array $fields, array &$messages, int $row_no ): void {
        if ( ! empty( $fields['featured_image'] ) ) {
            $id = $this->sideload( $fields['featured_image'], $post_id );
            if ( $id > 0 ) {
                set_post_thumbnail( $post_id, $id );
            } else {
                $messages[] = sprintf( __( 'Row %d: featured image could not be downloaded.', 'ovr-core' ), $row_no );
            }
        }
        if ( ! empty( $fields['gallery'] ) ) {
            $ids = [];
            foreach ( array_filter( array_map( 'trim', explode( ',', $fields['gallery'] ) ) ) as $url ) {
                $gid = $this->sideload( $url, $post_id );
                if ( $gid > 0 ) {
                    $ids[] = $gid;
                }
            }
            if ( $ids ) {
                update_post_meta( $post_id, '_ovr_gallery_ids', implode( ',', $ids ) );
            }
        }
    }

    private function sideload( string $url, int $post_id ): int {
        $url = esc_url_raw( $url );
        if ( '' === $url ) {
            return 0;
        }
        $id = media_sideload_image( $url, $post_id, null, 'id' );
        return is_wp_error( $id ) ? 0 : (int) $id;
    }

    /* ───────────────────────── Helpers ───────────────────────── */

    /**
     * Parse the uploaded CSV into header + rows.
     *
     * @return array{header:array<int,string>,rows:array<int,array<int,string>>}|null
     */
    private function parse_csv( string $tmp ): ?array {
        $fh = fopen( $tmp, 'r' );
        if ( ! $fh ) {
            return null;
        }
        $header = fgetcsv( $fh );
        if ( ! is_array( $header ) || count( $header ) < 1 ) {
            fclose( $fh );
            return null;
        }
        // Strip a UTF-8 BOM from the first header cell.
        if ( isset( $header[0] ) ) {
            $header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
        }
        $header = array_map( static fn( $h ) => sanitize_text_field( (string) $h ), $header );

        $rows = [];
        while ( ( $line = fgetcsv( $fh ) ) !== false ) {
            // Skip fully empty lines.
            if ( 1 === count( $line ) && ( null === $line[0] || '' === trim( (string) $line[0] ) ) ) {
                continue;
            }
            $rows[] = array_map( static fn( $v ) => (string) $v, $line );
            if ( count( $rows ) >= self::MAX_ROWS + 50 ) {
                break; // hard cap on what we hold in the transient.
            }
        }
        fclose( $fh );

        return [ 'header' => $header, 'rows' => $rows ];
    }

    /**
     * Guess a target for each column from its header name.
     *
     * @param array<int,string> $header
     * @return array<int,string>
     */
    private function auto_map( array $header ): array {
        $rules = [
            'title'          => [ 'title', 'name', 'listing' ],
            'content'        => [ 'description', 'content', 'details', 'about' ],
            'excerpt'        => [ 'summary', 'excerpt', 'short' ],
            'owner_email'    => [ 'owner', 'email', 'host' ],
            'bedrooms'       => [ 'bedroom', 'beds room', 'br' ],
            'bathrooms'      => [ 'bathroom', 'bath', 'ba' ],
            'beds'           => [ 'beds' ],
            'max_guests'     => [ 'guest', 'sleeps', 'occupanc' ],
            'sqft'           => [ 'sqft', 'square', 'size' ],
            'base_price'     => [ 'price', 'rate', 'cost' ],
            'min_stay'       => [ 'min stay', 'minimum', 'nights' ],
            'pets_allowed'   => [ 'pet' ],
            'address'        => [ 'address', 'street' ],
            'city'           => [ 'city', 'town' ],
            'state'          => [ 'state', 'province' ],
            'zip'            => [ 'zip', 'postal', 'postcode' ],
            'country'        => [ 'country' ],
            'village_name'   => [ 'village', 'community', 'neighbourhood', 'neighborhood' ],
            'latitude'       => [ 'lat' ],
            'longitude'      => [ 'lng', 'long', 'lon' ],
            'video_url'      => [ 'video' ],
            'ical_url'       => [ 'ical', 'calendar' ],
            'tax_property_type' => [ 'type', 'property type' ],
            'tax_amenity'    => [ 'amenit', 'feature' ],
            'featured_image' => [ 'image', 'photo', 'thumbnail', 'featured' ],
            'gallery'        => [ 'gallery', 'images', 'photos' ],
        ];
        $auto = [];
        foreach ( $header as $i => $col ) {
            $lc = strtolower( (string) $col );
            foreach ( $rules as $target => $needles ) {
                foreach ( $needles as $needle ) {
                    if ( false !== strpos( $lc, $needle ) ) {
                        $auto[ $i ] = $target;
                        continue 3;
                    }
                }
            }
        }
        return $auto;
    }

    /** @param array<string,string> $fields */
    private function preview_extras( array $fields ): string {
        $bits = [];
        if ( ! empty( $fields['base_price'] ) ) {
            $bits[] = '$' . $fields['base_price'];
        }
        if ( ! empty( $fields['bedrooms'] ) ) {
            $bits[] = $fields['bedrooms'] . 'BR';
        }
        if ( ! empty( $fields['tax_village'] ) ) {
            $bits[] = $fields['tax_village'];
        }
        return $bits ? ' — ' . implode( ', ', $bits ) : '';
    }

    private function truthy( string $v ): bool {
        return in_array( strtolower( trim( $v ) ), [ '1', 'yes', 'y', 'true', 'on', 'allowed' ], true );
    }
}
