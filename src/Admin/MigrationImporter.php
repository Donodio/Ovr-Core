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
     * The fields a CSV column can map to, per entity type. Group prefix is for
     * the optgroup label.
     *
     * @param string $entity listing | user | review
     * @return array<string, array<string, string>> group => [ key => label ]
     */
    public static function target_fields( string $entity = 'listing' ): array {
        if ( 'user' === $entity ) {
            return [
                __( 'Account', 'ovr-core' ) => [
                    'user_email' => __( 'Email (required)', 'ovr-core' ),
                    'username'   => __( 'Username / login', 'ovr-core' ),
                    'password'   => __( 'Password (placeholder if blank)', 'ovr-core' ),
                    'role'       => __( 'Role (landlord/subscriber/admin)', 'ovr-core' ),
                    'legacy_id'  => __( 'Legacy ID (dedupe key)', 'ovr-core' ),
                ],
                __( 'Profile', 'ovr-core' ) => [
                    'first_name'    => __( 'First name', 'ovr-core' ),
                    'last_name'     => __( 'Last name', 'ovr-core' ),
                    'display_name'  => __( 'Display name', 'ovr-core' ),
                    'phone'         => __( 'Phone', 'ovr-core' ),
                    'bio'           => __( 'Bio', 'ovr-core' ),
                    'account_status'=> __( 'Account status (active/inactive)', 'ovr-core' ),
                    'verified'      => __( 'Verified owner (yes/no)', 'ovr-core' ),
                ],
            ];
        }

        if ( 'review' === $entity ) {
            return [
                __( 'Link', 'ovr-core' ) => [
                    'property_ref'  => __( 'Listing legacy ID (dedupe key)', 'ovr-core' ),
                    'property_title'=> __( 'Listing title (fallback link)', 'ovr-core' ),
                    'legacy_id'     => __( 'Review legacy ID (dedupe key)', 'ovr-core' ),
                ],
                __( 'Review', 'ovr-core' ) => [
                    'rating'      => __( 'Rating (1–5)', 'ovr-core' ),
                    'title'       => __( 'Review title', 'ovr-core' ),
                    'body'        => __( 'Review text', 'ovr-core' ),
                    'guest_name'  => __( 'Guest name', 'ovr-core' ),
                    'guest_email' => __( 'Guest email', 'ovr-core' ),
                    'stay_date'   => __( 'Stay date (YYYY-MM-DD)', 'ovr-core' ),
                    'status'      => __( 'Status (pending/approved/rejected)', 'ovr-core' ),
                ],
            ];
        }

        return [
            __( 'Core', 'ovr-core' ) => [
                'title'          => __( 'Title', 'ovr-core' ),
                'content'        => __( 'Description (content)', 'ovr-core' ),
                'excerpt'        => __( 'Short description (excerpt)', 'ovr-core' ),
                'owner_email'    => __( 'Owner email', 'ovr-core' ),
                'status'         => __( 'Listing status (active/inactive)', 'ovr-core' ),
                'legacy_id'      => __( 'Legacy ID (dedupe key)', 'ovr-core' ),
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
                'tax_feature'       => __( 'Features (comma-separated)', 'ovr-core' ),
                'golf_cart'         => __( 'Golf cart (included/extra/yes/no)', 'ovr-core' ),
            ],
            __( 'Images', 'ovr-core' ) => [
                'featured_image' => __( 'Featured image URL', 'ovr-core' ),
                'gallery'        => __( 'Gallery image URLs (comma-separated)', 'ovr-core' ),
            ],
        ];
    }

    /** Flat key→label map of all targets for a given entity. */
    private static function flat_targets( string $entity = 'listing' ): array {
        $flat = [];
        foreach ( self::target_fields( $entity ) as $fields ) {
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
        <div class="wrap ovr-adm">
            <style>#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}</style>
            <div class="ovr-adm-wrap">
                <div class="ovr-adm-head">
                    <div>
                        <h1><?php esc_html_e( 'Import Listings (CSV)', 'ovr-core' ); ?></h1>
                        <p><?php esc_html_e( 'Upload a CSV export, map columns to listing fields, then import.', 'ovr-core' ); ?></p>
                    </div>
                </div>

                <?php if ( 'parse' === $err ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--error"><span class="material-symbols-outlined">error</span><span><?php esc_html_e( 'Could not read that file as CSV. Make sure it is a .csv with a header row.', 'ovr-core' ); ?></span></div>
                <?php elseif ( 'upload' === $err ) : ?>
                    <div class="ovr-adm-notice ovr-adm-notice--error"><span class="material-symbols-outlined">error</span><span><?php esc_html_e( 'Upload failed. Please choose a .csv file.', 'ovr-core' ); ?></span></div>
                <?php endif; ?>

                <div class="ovr-adm-card">
                    <div class="ovr-adm-card-body">
                        <p style="margin-top:0;max-width:720px;color:var(--muted)">
                            <?php esc_html_e( 'Upload a CSV export of users, listings, or property reviews. The first row must be column headers. On the next screen you map each column to a field, preview a dry run, then import. Image columns on listings may contain public image URLs, which are downloaded into the Media Library.', 'ovr-core' ); ?>
                        </p>
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                            <input type="hidden" name="action" value="ovr_import_upload">
                            <?php wp_nonce_field( 'ovr_import_upload' ); ?>
                            <label class="ovr-adm-label" for="ovr-imp-entity"><?php esc_html_e( 'Import', 'ovr-core' ); ?></label>
                            <select id="ovr-imp-entity" name="entity" class="ovr-adm-select">
                                <option value="listing"><?php esc_html_e( 'Listings', 'ovr-core' ); ?></option>
                                <option value="user"><?php esc_html_e( 'Users', 'ovr-core' ); ?></option>
                                <option value="review"><?php esc_html_e( 'Property reviews', 'ovr-core' ); ?></option>
                            </select>
                            <input type="file" name="csv" accept=".csv,text/csv" required>
                            <button type="submit" class="ovr-adm-btn ovr-adm-btn--primary"><span class="material-symbols-outlined">upload_file</span><?php esc_html_e( 'Upload & Continue', 'ovr-core' ); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_mapping( array $data ): void {
        $header   = (array) $data['header'];
        $rows     = (array) $data['rows'];
        $entity   = (string) ( $data['entity'] ?? 'listing' );
        $targets  = self::target_fields( $entity );
        $auto     = $this->auto_map( $header, $entity );
        $statuses = [ 'publish' => __( 'Published', 'ovr-core' ), 'draft' => __( 'Draft', 'ovr-core' ) ];
        ?>
        <div class="wrap ovr-adm">
            <style>#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}</style>
            <div class="ovr-adm-wrap">
                <div class="ovr-adm-head">
                    <div>
                        <h1>
                        <?php
                        if ( 'user' === $entity ) {
                            esc_html_e( 'Import Users — Map Columns', 'ovr-core' );
                        } elseif ( 'review' === $entity ) {
                            esc_html_e( 'Import Reviews — Map Columns', 'ovr-core' );
                        } else {
                            esc_html_e( 'Import Listings — Map Columns', 'ovr-core' );
                        }
                        ?>
                        </h1>
                        <p><?php printf( esc_html__( 'File: %1$s · %2$s data rows detected.', 'ovr-core' ), '<strong>' . esc_html( (string) ( $data['filename'] ?? 'upload.csv' ) ) . '</strong>', '<strong>' . number_format_i18n( count( $rows ) ) . '</strong>' ); ?></p>
                    </div>
                    <div class="ovr-adm-actions">
                        <a href="<?php echo esc_url( add_query_arg( 'reset', '1', $this->page_url() ) ); ?>" class="ovr-adm-btn ovr-adm-btn--ghost"><span class="material-symbols-outlined">restart_alt</span><?php esc_html_e( 'Start over', 'ovr-core' ); ?></a>
                    </div>
                </div>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="ovr_import_run">
                    <?php wp_nonce_field( 'ovr_import_run' ); ?>

                    <div class="ovr-adm-card">
                        <table class="ovr-adm-table">
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
                                    <td><div class="ovr-adm-name"><?php echo esc_html( (string) $col ); ?></div></td>
                                    <td><span class="ovr-adm-sub"><?php echo esc_html( mb_strimwidth( $sample, 0, 50, '…' ) ); ?></span></td>
                                    <td>
                                        <select name="map[<?php echo (int) $i; ?>]" class="ovr-adm-select">
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
                    </div>

                    <div class="ovr-adm-card">
                        <div class="ovr-adm-card-head">
                            <h2><?php esc_html_e( 'Options', 'ovr-core' ); ?></h2>
                        </div>
                        <div class="ovr-adm-card-body">
                            <div class="ovr-adm-form-grid">
                                <?php if ( 'user' === $entity ) : ?>
                                    <div class="ovr-adm-field">
                                        <label class="ovr-adm-label" for="ovr-imp-role"><?php esc_html_e( 'Default role', 'ovr-core' ); ?></label>
                                        <select id="ovr-imp-role" name="default_role" class="ovr-adm-select">
                                            <option value="ovr_landlord"><?php esc_html_e( 'Landlord / owner', 'ovr-core' ); ?></option>
                                            <option value="subscriber"><?php esc_html_e( 'Subscriber', 'ovr-core' ); ?></option>
                                            <option value="administrator"><?php esc_html_e( 'Administrator', 'ovr-core' ); ?></option>
                                        </select>
                                        <p class="ovr-adm-hint"><?php esc_html_e( 'Assigned when no Role column is mapped. A mapped role overrides this.', 'ovr-core' ); ?></p>
                                    </div>
                                <?php elseif ( 'review' === $entity ) : ?>
                                    <div class="ovr-adm-field">
                                        <label class="ovr-adm-label" for="ovr-imp-rstatus"><?php esc_html_e( 'Default review status', 'ovr-core' ); ?></label>
                                        <select id="ovr-imp-rstatus" name="review_status" class="ovr-adm-select">
                                            <option value="pending"><?php esc_html_e( 'Pending (needs approval)', 'ovr-core' ); ?></option>
                                            <option value="approved"><?php esc_html_e( 'Approved (live)', 'ovr-core' ); ?></option>
                                            <option value="rejected"><?php esc_html_e( 'Rejected', 'ovr-core' ); ?></option>
                                        </select>
                                        <p class="ovr-adm-hint"><?php esc_html_e( 'Used when no Status column is mapped. Approved reviews update the property rating.', 'ovr-core' ); ?></p>
                                    </div>
                                <?php else : ?>
                                <div class="ovr-adm-field">
                                    <label class="ovr-adm-label"><?php esc_html_e( 'Default owner', 'ovr-core' ); ?></label>
                                    <?php wp_dropdown_users( [ 'name' => 'default_owner', 'show_option_none' => __( '— Current admin —', 'ovr-core' ), 'option_none_value' => 0, 'class' => 'ovr-adm-select' ] ); ?>
                                    <p class="ovr-adm-hint"><?php esc_html_e( 'Used when no Owner email column is mapped or an email is not found.', 'ovr-core' ); ?></p>
                                </div>
                                <div class="ovr-adm-field">
                                    <label class="ovr-adm-label" for="ovr-imp-status"><?php esc_html_e( 'Post status', 'ovr-core' ); ?></label>
                                    <select id="ovr-imp-status" name="post_status" class="ovr-adm-select"><?php foreach ( $statuses as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
                                </div>
                                <div class="ovr-adm-field ovr-adm-field--full">
                                    <label class="ovr-adm-check"><input type="checkbox" name="dedupe_title" value="1"> <?php esc_html_e( 'Update an existing listing instead of creating a duplicate when the title matches.', 'ovr-core' ); ?></label>
                                </div>
                                <div class="ovr-adm-field ovr-adm-field--full">
                                    <label class="ovr-adm-check"><input type="checkbox" name="import_images" value="1" checked> <?php esc_html_e( 'Download featured / gallery image URLs into the Media Library (slower).', 'ovr-core' ); ?></label>
                                </div>
                                <?php endif; ?>
                                <div class="ovr-adm-field ovr-adm-field--full">
                                    <label class="ovr-adm-check"><input type="checkbox" name="dedupe_legacy" value="1" checked> <?php esc_html_e( 'Update an existing row when its Legacy ID matches (re-run safe).', 'ovr-core' ); ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="ovr-adm-form-foot">
                            <button type="submit" name="do" value="dryrun" class="ovr-adm-btn ovr-adm-btn--ghost"><span class="material-symbols-outlined">visibility</span><?php esc_html_e( 'Dry Run (preview)', 'ovr-core' ); ?></button>
                            <button type="submit" name="do" value="import" class="ovr-adm-btn ovr-adm-btn--primary" onclick="return confirm('<?php echo esc_js( __( 'Import these listings now?', 'ovr-core' ) ); ?>')"><span class="material-symbols-outlined">publish</span><?php esc_html_e( 'Run Import', 'ovr-core' ); ?></button>
                        </div>
                    </div>
                </form>

                <?php $this->maybe_render_results(); ?>
            </div>
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
        <div class="ovr-adm-card">
            <div class="ovr-adm-card-head">
                <h2><?php echo $is_dry ? esc_html__( 'Dry Run Preview', 'ovr-core' ) : esc_html__( 'Import Results', 'ovr-core' ); ?></h2>
                <?php if ( $is_dry ) : ?><span class="ovr-adm-badge ovr-adm-badge--blue"><?php esc_html_e( 'Preview — nothing was written', 'ovr-core' ); ?></span><?php endif; ?>
            </div>
            <div class="ovr-adm-card-body">
                <div class="ovr-adm-stats">
                    <div class="ovr-adm-stat">
                        <div class="ovr-adm-stat-ic"><span class="material-symbols-outlined">add_circle</span></div>
                        <div><div class="ovr-adm-stat-v"><?php echo (int) $res['created']; ?></div><div class="ovr-adm-stat-l"><?php esc_html_e( 'Created', 'ovr-core' ); ?></div></div>
                    </div>
                    <div class="ovr-adm-stat">
                        <div class="ovr-adm-stat-ic"><span class="material-symbols-outlined">sync</span></div>
                        <div><div class="ovr-adm-stat-v"><?php echo (int) $res['updated']; ?></div><div class="ovr-adm-stat-l"><?php esc_html_e( 'Updated', 'ovr-core' ); ?></div></div>
                    </div>
                    <div class="ovr-adm-stat">
                        <div class="ovr-adm-stat-ic"><span class="material-symbols-outlined">block</span></div>
                        <div><div class="ovr-adm-stat-v"><?php echo (int) $res['skipped']; ?></div><div class="ovr-adm-stat-l"><?php esc_html_e( 'Skipped', 'ovr-core' ); ?></div></div>
                    </div>
                    <div class="ovr-adm-stat">
                        <div class="ovr-adm-stat-ic"><span class="material-symbols-outlined">error</span></div>
                        <div><div class="ovr-adm-stat-v"><?php echo (int) ( $res['errors'] ?? 0 ); ?></div><div class="ovr-adm-stat-l"><?php esc_html_e( 'Errors', 'ovr-core' ); ?></div></div>
                    </div>
                </div>
                <?php if ( ! empty( $res['messages'] ) ) : ?>
                    <ul style="max-height:280px;overflow:auto;background:var(--bg);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:12px 12px 12px 28px;margin:18px 0 0;font-size:14px;line-height:1.6">
                        <?php foreach ( (array) $res['messages'] as $m ) : ?>
                            <li><?php echo esc_html( (string) $m ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
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
        $parsed['entity']   = in_array( $_POST['entity'] ?? 'listing', [ 'listing', 'user', 'review' ], true )
            ? (string) $_POST['entity']
            : 'listing';
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
        $entity        = in_array( (string) ( $data['entity'] ?? 'listing' ), [ 'listing', 'user', 'review' ], true )
            ? (string) $data['entity']
            : 'listing';
        $default_owner = (int) ( $_POST['default_owner'] ?? 0 ) ?: get_current_user_id();
        $post_status   = 'draft' === ( $_POST['post_status'] ?? 'publish' ) ? 'draft' : 'publish';
        $dedupe_title  = ! empty( $_POST['dedupe_title'] );
        $import_images = ! empty( $_POST['import_images'] );
        $dedupe_legacy = ! empty( $_POST['dedupe_legacy'] );
        $default_role  = in_array( (string) ( $_POST['default_role'] ?? 'ovr_landlord' ), [ 'ovr_landlord', 'subscriber', 'administrator' ], true )
            ? (string) $_POST['default_role']
            : 'ovr_landlord';
        $review_status = in_array( (string) ( $_POST['review_status'] ?? 'pending' ), [ 'pending', 'approved', 'rejected' ], true )
            ? (string) $_POST['review_status']
            : 'pending';

        $result = $this->process( (array) $data['rows'], $map, $entity, [
            'dry'           => $is_dry,
            'default_owner' => $default_owner,
            'post_status'   => $post_status,
            'dedupe_title'  => $dedupe_title,
            'import_images' => $import_images,
            'dedupe_legacy' => $dedupe_legacy,
            'default_role'  => $default_role,
            'review_status' => $review_status,
        ] );

        set_transient( $this->transient_key() . '_result', $result, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( $this->page_url() );
        exit;
    }

    /* ───────────────────────── Core import ───────────────────────── */

    /**
     * @param array<int,array<int,string>> $rows
     * @param array<int,string>            $map  column index → target key
     * @param string                       $entity listing | user | review
     * @param array<string,mixed>          $opts
     * @return array<string,mixed>
     */
    private function process( array $rows, array $map, string $entity, array $opts ): array {
        $dry      = ! empty( $opts['dry'] );
        $created  = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = 0;
        $messages = [];

        if ( ! $dry ) {
            @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.PHP.IniSet
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        if ( 'listing' === $entity && ! in_array( 'title', $map, true ) ) {
            return [ 'dry' => $dry, 'created' => 0, 'updated' => 0, 'skipped' => count( $rows ), 'errors' => 0,
                'messages' => [ __( 'No column is mapped to Title — map one and try again.', 'ovr-core' ) ] ];
        }
        if ( 'user' === $entity && ! in_array( 'user_email', $map, true ) ) {
            return [ 'dry' => $dry, 'created' => 0, 'updated' => 0, 'skipped' => count( $rows ), 'errors' => 0,
                'messages' => [ __( 'No column is mapped to Email — map one and try again.', 'ovr-core' ) ] ];
        }
        if ( 'review' === $entity && ! ( in_array( 'property_ref', $map, true ) || in_array( 'property_title', $map, true ) ) ) {
            return [ 'dry' => $dry, 'created' => 0, 'updated' => 0, 'skipped' => count( $rows ), 'errors' => 0,
                'messages' => [ __( 'No column is mapped to a property link (Listing legacy ID or Listing title) — map one and try again.', 'ovr-core' ) ] ];
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

            if ( 'user' === $entity ) {
                if ( '' === ( $fields['user_email'] ?? '' ) ) {
                    $skipped++;
                    $messages[] = sprintf( __( 'Row %d: skipped (empty email).', 'ovr-core' ), $row_no );
                    continue;
                }
                $existing = $this->find_existing_user( $fields, $opts );
            } elseif ( 'review' === $entity ) {
                $existing = $this->find_existing_review( $fields, $opts );
                if ( ! $this->resolve_review_property( $fields ) ) {
                    $skipped++;
                    $errors++;
                    $messages[] = sprintf( __( 'Row %d: skipped (no matching property for this review).', 'ovr-core' ), $row_no );
                    continue;
                }
            } else {
                $title = $fields['title'] ?? '';
                if ( '' === $title ) {
                    $skipped++;
                    $messages[] = sprintf( __( 'Row %d: skipped (empty title).', 'ovr-core' ), $row_no );
                    continue;
                }
                $existing = $this->find_existing_listing( $fields, $opts );
            }

            if ( $dry ) {
                if ( $row_no <= 15 ) {
                    if ( 'listing' === $entity ) {
                        $messages[] = sprintf(
                            '%s "%s"%s',
                            $existing ? __( 'Update', 'ovr-core' ) : __( 'Create', 'ovr-core' ),
                            $fields['title'],
                            $this->preview_extras( $fields )
                        );
                    } else {
                        $label = 'user' === $entity ? ( $fields['user_email'] ?? '' ) : sprintf( '#%s', $fields['property_ref'] ?? $fields['property_title'] ?? '?' );
                        $messages[] = sprintf( '%s %s', $existing ? __( 'Update', 'ovr-core' ) : __( 'Create', 'ovr-core' ), $label );
                    }
                }
                $existing ? $updated++ : $created++;
                continue;
            }

            if ( 'user' === $entity ) {
                $id = $this->upsert_user( $existing, $fields, $opts, $messages, $row_no );
            } elseif ( 'review' === $entity ) {
                $id = $this->upsert_review( $existing, $fields, $opts, $messages, $row_no );
            } else {
                $id = $this->upsert_listing( $existing, $fields['title'], $fields, $opts, $messages, $row_no );
            }

            if ( $id <= 0 ) {
                $skipped++;
                $errors++;
                continue;
            }
            $existing ? $updated++ : $created++;
        }

        if ( ! $dry ) {
            $messages[] = __( 'Import complete.', 'ovr-core' );
        }

        return compact( 'dry', 'created', 'updated', 'skipped', 'errors', 'messages' );
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

        // Stable legacy key (dedupe / re-run safety).
        if ( ! empty( $fields['legacy_id'] ) ) {
            update_post_meta( $post_id, '_ovr_legacy_id', sanitize_text_field( $fields['legacy_id'] ) );
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

    /**
     * Locate an existing listing for dedupe: by legacy ID first, then (when the
     * option is on) by title.
     *
     * @param array<string,string> $fields
     * @param array<string,mixed>  $opts
     */
    private function find_existing_listing( array $fields, array $opts ): int {
        if ( ! empty( $opts['dedupe_legacy'] ) && ! empty( $fields['legacy_id'] ) ) {
            $ids = get_posts( [
                'post_type'   => 'ovr_property',
                'post_status' => 'any',
                'meta_key'    => '_ovr_legacy_id',
                'meta_value'  => $fields['legacy_id'],
                'fields'      => 'ids',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ] );
            if ( $ids ) {
                return (int) $ids[0];
            }
        }
        if ( ! empty( $opts['dedupe_title'] ) && ! empty( $fields['title'] ) ) {
            $found = get_page_by_title( $fields['title'], OBJECT, 'ovr_property' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_title_get_page_by_title
            if ( $found ) {
                return (int) $found->ID;
            }
        }
        return 0;
    }

    /**
     * Locate an existing user for dedupe: by legacy ID first, then by email.
     *
     * @param array<string,string> $fields
     */
    private function find_existing_user( array $fields, array $opts = [] ): int {
        if ( ! empty( $opts['dedupe_legacy'] ) && ! empty( $fields['legacy_id'] ) ) {
            $users = get_users( [
                'meta_key'   => 'ovr_legacy_id',
                'meta_value' => $fields['legacy_id'],
                'fields'     => 'ID',
                'number'     => 1,
            ] );
            if ( $users ) {
                return (int) $users[0];
            }
        }
        if ( ! empty( $fields['user_email'] ) ) {
            $u = get_user_by( 'email', $fields['user_email'] );
            if ( $u ) {
                return (int) $u->ID;
            }
        }
        return 0;
    }

    /**
     * Locate an existing review by its legacy ID (re-run safety).
     *
     * @param array<string,string> $fields
     */
    private function find_existing_review( array $fields, array $opts = [] ): int {
        if ( empty( $opts['dedupe_legacy'] ) || empty( $fields['legacy_id'] ) ) {
            return 0;
        }
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ovr_reviews WHERE legacy_id = %s",
            $fields['legacy_id']
        ) );
    }

    /**
     * Resolve the property a review attaches to, via listing legacy ID or title.
     *
     * @param array<string,string> $fields
     */
    private function resolve_review_property( array $fields ): int {
        if ( ! empty( $fields['property_ref'] ) ) {
            $ids = get_posts( [
                'post_type'   => 'ovr_property',
                'post_status' => 'any',
                'meta_key'    => '_ovr_legacy_id',
                'meta_value'  => $fields['property_ref'],
                'fields'      => 'ids',
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ] );
            if ( $ids ) {
                return (int) $ids[0];
            }
        }
        if ( ! empty( $fields['property_title'] ) ) {
            $p = get_page_by_title( $fields['property_title'], OBJECT, 'ovr_property' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_title_get_page_by_title
            if ( $p ) {
                return (int) $p->ID;
            }
        }
        return 0;
    }

    /**
     * Create or update one user from a row's mapped fields.
     *
     * @param array<string,string> $fields
     * @param array<string,mixed>  $opts
     * @param array<int,string>    $messages (by ref)
     */
    private function upsert_user( int $existing, array $fields, array $opts, array &$messages, int $row_no ): int {
        $email = sanitize_email( $fields['user_email'] );
        if ( ! is_email( $email ) ) {
            $messages[] = sprintf( __( 'Row %d: skipped (invalid email "%s").', 'ovr-core' ), $row_no, $email );
            return 0;
        }

        $login = ! empty( $fields['username'] ) ? sanitize_user( $fields['username'] ) : $email;
        if ( ! $login ) {
            $login = $email;
        }
        $role = $this->map_user_role( $fields['role'] ?? '' );
        if ( ! $role ) {
            $role = (string) ( $opts['default_role'] ?? 'ovr_landlord' );
        }

        $first  = ! empty( $fields['first_name'] ) ? sanitize_text_field( $fields['first_name'] ) : '';
        $last   = ! empty( $fields['last_name'] ) ? sanitize_text_field( $fields['last_name'] ) : '';
        $display = ! empty( $fields['display_name'] ) ? sanitize_text_field( $fields['display_name'] ) : '';
        if ( '' === $display ) {
            $display = trim( $first . ' ' . $last ) ?: $email;
        }

        $userdata = [
            'user_email'   => $email,
            'user_login'   => $login,
            'display_name' => $display,
            'first_name'   => $first,
            'last_name'    => $last,
            'role'         => $role,
        ];
        if ( ! empty( $fields['password'] ) ) {
            $userdata['user_pass'] = wp_unslash( $fields['password'] );
        } else {
            $userdata['user_pass'] = wp_generate_password( 24, true, true );
        }

        if ( $existing ) {
            $userdata['ID'] = $existing;
            unset( $userdata['user_login'] );
            $uid = wp_update_user( $userdata );
        } else {
            if ( username_exists( $login ) ) {
                $login = $this->unique_login( $login );
                $userdata['user_login'] = $login;
            }
            $uid = wp_insert_user( $userdata );
        }

        if ( is_wp_error( $uid ) || ! $uid ) {
            $messages[] = sprintf( __( 'Row %d: failed to save user "%s".', 'ovr-core' ), $row_no, $email );
            return 0;
        }
        $uid = (int) $uid;

        if ( ! empty( $fields['legacy_id'] ) ) {
            update_user_meta( $uid, 'ovr_legacy_id', sanitize_text_field( $fields['legacy_id'] ) );
        }
        if ( isset( $fields['phone'] ) && '' !== $fields['phone'] ) {
            update_user_meta( $uid, 'ovr_phone', sanitize_text_field( $fields['phone'] ) );
        }
        if ( isset( $fields['bio'] ) && '' !== $fields['bio'] ) {
            update_user_meta( $uid, 'description', sanitize_textarea_field( $fields['bio'] ) );
        }
        if ( isset( $fields['account_status'] ) && '' !== $fields['account_status'] ) {
            $st = sanitize_key( $fields['account_status'] );
            update_user_meta( $uid, 'ovr_account_status', in_array( $st, [ 'active', 'inactive' ], true ) ? $st : 'active' );
        }
        if ( isset( $fields['verified'] ) && '' !== $fields['verified'] ) {
            if ( $this->truthy( $fields['verified'] ) ) {
                update_user_meta( $uid, \OVR\Core\Verification::META_VERIFIED, '1' );
            } else {
                delete_user_meta( $uid, \OVR\Core\Verification::META_VERIFIED );
            }
        }

        // Subscription plan: if a plan column is supplied, activate that
        // membership for the user (grants the landlord role + restores listings).
        if ( isset( $fields['plan'] ) && '' !== (string) $fields['plan'] ) {
            $slug = $this->resolve_plan_slug( (string) $fields['plan'] );
            if ( $slug ) {
                \OVR\Subscription\SubscriptionManager::activate( $uid, $slug );
                if ( ! empty( $fields['plan_expires'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $fields['plan_expires'] ) ) {
                    update_user_meta( $uid, \OVR\Subscription\UserSubscription::META_EXPIRES, (string) $fields['plan_expires'] );
                }
            } else {
                $messages[] = sprintf( __( 'Row %d: unknown plan "%s" — skipped plan assignment.', 'ovr-core' ), $row_no, $fields['plan'] );
            }
        }

        return $uid;
    }

    /**
     * Map a free-text plan column to a known plan slug (Plans::get_plans()).
     *
     * @return string|null
     */
    private function resolve_plan_slug( string $raw ): ?string {
        $raw   = trim( $raw );
        $plans = \OVR\Subscription\Plans::get_plans();
        $by_key = [];
        foreach ( $plans as $p ) {
            $by_key[ strtolower( (string) ( $p['slug'] ?? '' ) ) ] = $p['slug'];
        }
        $norm = strtolower( preg_replace( '/[^a-z0-9]+/', '_', $raw ) );
        if ( isset( $by_key[ $norm ] ) ) {
            return $by_key[ $norm ];
        }
        // Fall back to a keyword/name match.
        foreach ( $plans as $p ) {
            $name = strtolower( (string) ( $p['name'] ?? '' ) );
            if ( '' !== $name && ( false !== strpos( $norm, preg_replace( '/[^a-z0-9]+/', '_', $name ) ) || false !== strpos( $name, $raw ) ) ) {
                return (string) $p['slug'];
            }
        }
        foreach ( $by_key as $k => $slug ) {
            if ( false !== strpos( $k, $norm ) || false !== strpos( $norm, $k ) ) {
                return $slug;
            }
        }
        return null;
    }

    /**
     * Create or update one review from a row's mapped fields. Links to a property
     * via property_ref / property_title; status defaults to pending (moderation).
     *
     * @param array<string,string> $fields
     * @param array<string,mixed>  $opts
     * @param array<int,string>    $messages (by ref)
     */
    private function upsert_review( int $existing, array $fields, array $opts, array &$messages, int $row_no ): int {
        $property_id = $this->resolve_review_property( $fields );
        if ( ! $property_id ) {
            $messages[] = sprintf( __( 'Row %d: skipped (no matching property for this review).', 'ovr-core' ), $row_no );
            return 0;
        }

        $rating = max( 1, min( 5, (int) ( $fields['rating'] ?? 0 ) ) );
        if ( ! $rating ) {
            $messages[] = sprintf( __( 'Row %d: skipped (missing rating).', 'ovr-core' ), $row_no );
            return 0;
        }
        $body = sanitize_textarea_field( $fields['body'] ?? '' );
        if ( '' === $body ) {
            $messages[] = sprintf( __( 'Row %d: skipped (missing review text).', 'ovr-core' ), $row_no );
            return 0;
        }

        $title     = sanitize_text_field( $fields['title'] ?? '' );
        $name      = sanitize_text_field( $fields['guest_name'] ?? '' );
        $email     = sanitize_email( $fields['guest_email'] ?? '' );
        $stay_raw  = sanitize_text_field( $fields['stay_date'] ?? '' );
        $stay      = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $stay_raw ) ? $stay_raw : null;
        $status    = ! empty( $fields['status'] ) ? sanitize_key( $fields['status'] ) : ( $opts['review_status'] ?? 'pending' );
        if ( ! in_array( $status, [ 'pending', 'approved', 'rejected' ], true ) ) {
            $status = 'pending';
        }
        $legacy    = ! empty( $fields['legacy_id'] ) ? sanitize_text_field( $fields['legacy_id'] ) : null;

        global $wpdb;
        $table = $wpdb->prefix . 'ovr_reviews';

        if ( $existing ) {
            $wpdb->update(
                $table,
                [
                    'property_id' => $property_id,
                    'guest_name'  => $name ?: __( 'Anonymous', 'ovr-core' ),
                    'guest_email' => $email,
                    'rating'      => $rating,
                    'title'       => substr( $title, 0, 255 ),
                    'body'        => $body,
                    'stay_date'   => $stay,
                    'status'      => $status,
                    'legacy_id'   => $legacy,
                ],
                [ 'id' => $existing ],
                [ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );
            $review_id = $existing;
        } else {
            $inserted = $wpdb->insert(
                $table,
                [
                    'property_id' => $property_id,
                    'user_id'     => null,
                    'guest_name'  => $name ?: __( 'Anonymous', 'ovr-core' ),
                    'guest_email' => $email,
                    'rating'      => $rating,
                    'title'       => substr( $title, 0, 255 ),
                    'body'        => $body,
                    'stay_date'   => $stay,
                    'status'      => $status,
                    'legacy_id'   => $legacy,
                    'approved_at' => 'approved' === $status ? current_time( 'mysql' ) : null,
                ],
                [ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
            );
            if ( false === $inserted ) {
                $messages[] = sprintf( __( 'Row %d: failed to save review.', 'ovr-core' ), $row_no );
                return 0;
            }
            $review_id = (int) $wpdb->insert_id;
        }

        if ( 'approved' === $status ) {
            \OVR\Property\Reviews::recompute_aggregates( $property_id );
        }

        return $review_id;
    }

    /** Map a free-text role column to a known WordPress role slug. */
    private function map_user_role( string $raw ): string {
        $r = strtolower( trim( $raw ) );
        if ( '' === $r ) {
            return '';
        }
        if ( in_array( $r, [ 'landlord', 'owner', 'ovr_landlord' ], true ) ) {
            return 'ovr_landlord';
        }
        if ( in_array( $r, [ 'subscriber', 'guest', 'member' ], true ) ) {
            return 'subscriber';
        }
        if ( in_array( $r, [ 'admin', 'administrator' ], true ) ) {
            return 'administrator';
        }
        return '';
    }

    /** Generate a login that does not already exist. */
    private function unique_login( string $login ): string {
        $base = $login;
        $i    = 1;
        while ( username_exists( $login ) ) {
            $login = $base . '-' . $i++;
        }
        return $login;
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
            'tax_feature'       => 'ovr_feature',
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
        // Golf-cart term → ovr_feature so PropertyQuery::has_golf_cart() matches.
        if ( ! empty( $fields['golf_cart'] ) ) {
            $this->apply_golf_cart( $post_id, $fields['golf_cart'] );
        }
    }

    /** Set the canonical golf-cart term on a listing from a free-text value. */
    private function apply_golf_cart( int $post_id, string $value ): void {
        $v = strtolower( trim( $value ) );
        if ( in_array( $v, [ 'no', 'n', '0', 'false', 'none', 'off' ], true ) ) {
            return;
        }
        $slug = ( false !== strpos( $v, 'extra' ) || false !== strpos( $v, 'charge' ) )
            ? 'golf-cart-extra-charge'
            : 'golf-cart-included';

        $term = term_exists( $slug, 'ovr_feature' );
        if ( ! $term ) {
            $term = wp_insert_term( ucwords( str_replace( '-', ' ', $slug ) ), 'ovr_feature', [ 'slug' => $slug ] );
        }
        if ( ! is_wp_error( $term ) && ! empty( $term['term_id'] ) ) {
            wp_set_object_terms( $post_id, (int) $term['term_id'], 'ovr_feature', true );
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
     * @param string            $entity listing | user | review
     * @return array<int,string>
     */
    private function auto_map( array $header, string $entity = 'listing' ): array {
        $rules = [
            'title'          => [ 'title', 'name', 'listing' ],
            'content'        => [ 'description', 'content', 'details', 'about' ],
            'excerpt'        => [ 'summary', 'excerpt', 'short' ],
            'owner_email'    => [ 'owner', 'email', 'host' ],
            'bedrooms'       => [ 'bedroom', 'bedrooms', 'br' ],
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
            'tax_village'    => [ 'section', 'village section' ],
            'tax_feature'    => [ 'feature' ],
            'golf_cart'      => [ 'golf cart', 'golfcart' ],
            'legacy_id'      => [ 'legacy', 'old id', 'source id', 'import id' ],
            'featured_image' => [ 'image', 'photo', 'thumbnail', 'featured' ],
            'gallery'        => [ 'gallery', 'images', 'photos' ],
        ];

        if ( 'user' === $entity ) {
            $rules = [
                'user_email'    => [ 'email', 'user email', 'login' ],
                'username'      => [ 'username', 'login', 'user' ],
                'password'      => [ 'password', 'pass' ],
                'role'          => [ 'role', 'type' ],
                'legacy_id'     => [ 'legacy', 'old id', 'source id' ],
                'first_name'    => [ 'first', 'given' ],
                'last_name'     => [ 'last', 'surname', 'family' ],
                'display_name'  => [ 'display', 'full name' ],
                'phone'         => [ 'phone', 'tel', 'mobile' ],
                'bio'           => [ 'bio', 'about', 'description' ],
                'account_status'=> [ 'status', 'account' ],
                'verified'      => [ 'verified', 'verif' ],
                'plan'          => [ 'plan', 'membership', 'subscription', 'tier' ],
                'plan_expires'  => [ 'plan expires', 'expires', 'expiry', 'membership expires', 'renews' ],
            ];
        } elseif ( 'review' === $entity ) {
            $rules = [
                'property_ref'  => [ 'legacy', 'property id', 'listing id', 'source id' ],
                'property_title'=> [ 'listing', 'property title', 'title' ],
                'legacy_id'     => [ 'review id', 'review legacy' ],
                'rating'        => [ 'rating', 'stars', 'score' ],
                'title'         => [ 'review title', 'subject' ],
                'body'          => [ 'review', 'comment', 'text', 'body' ],
                'guest_name'    => [ 'guest', 'name', 'author' ],
                'guest_email'    => [ 'guest email', 'reviewer email' ],
                'stay_date'     => [ 'stay', 'date', 'visited' ],
                'status'        => [ 'status', 'approved' ],
            ];
        }

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
