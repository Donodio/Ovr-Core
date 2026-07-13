<?php
/**
 * Paid Service — create / edit form (Feature 1).
 *
 * @package OVR
 * @var array|null $service         Existing row, or null for new.
 * @var bool       $is_edit         Edit vs create.
 * @var array      $types           Service-type metadata.
 * @var string     $currency_symbol Currency symbol.
 * @var string     $back_url        List screen URL.
 * @var string     $action_url      admin-post.php.
 * @var string     $nonce           Save nonce.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$s   = $service ?? [];
$val = static function ( string $key, $default = '' ) use ( $s ) {
    return $s[ $key ] ?? $default;
};
$sym       = $currency_symbol;
$cur_type  = (string) $val( 'service_type', 'featured' );
$is_active = $is_edit ? ! empty( $s['is_active'] ) : true;
?>
<div class="wrap ovr-ps">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}
        #wpcontent{padding-left:0}
        .ovr-ps{--navy:#000961;--blue:#00A2E8;--gold:#DEAF0C;--gold-dark:#b8920a;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--shadow-md:0 4px 12px rgba(0,9,97,.08);--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink)}
        .ovr-ps,.ovr-ps *{box-sizing:border-box}
        .ovr-ps .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:20px}
        .ovr-psf-wrap{padding:24px 40px 48px;max-width:860px}
        .ovr-psf-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:14px;font-weight:600;margin-bottom:14px}
        .ovr-psf-back:hover{color:var(--blue)}
        .ovr-psf-head h1{font-size:28px;font-weight:700;margin:0 0 4px}
        .ovr-psf-head p{margin:0 0 22px;color:var(--muted);font-size:15px}
        .ovr-psf-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);padding:30px}
        .ovr-psf-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
        .ovr-psf-field{display:flex;flex-direction:column;gap:6px;margin-bottom:20px}
        .ovr-psf-field.full{grid-column:1/-1}
        .ovr-psf-field label{font-size:13px;font-weight:700;letter-spacing:.02em;color:var(--ink)}
        .ovr-psf-field .hint{font-size:12px;color:var(--muted);font-weight:400}
        .ovr-psf-field input[type=text],.ovr-psf-field input[type=number],.ovr-psf-field textarea,.ovr-psf-field select{width:100%;border:1px solid var(--gray-border);border-radius:var(--r-md);padding:11px 14px;font-size:15px;font-family:inherit;background:#fff;outline:none}
        .ovr-psf-field input:focus,.ovr-psf-field textarea:focus,.ovr-psf-field select:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.1)}
        .ovr-psf-field textarea{min-height:90px;resize:vertical}
        /* P6.6: proper input-group so the $ never overlaps the digits, at any value. */
        .ovr-psf-prefix{display:flex;align-items:stretch;border:1px solid var(--gray-border);border-radius:var(--r-md);overflow:hidden;background:#fff}
        .ovr-psf-prefix:focus-within{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.1)}
        .ovr-psf-prefix span{display:flex;align-items:center;padding:0 13px;background:var(--gray-light);color:var(--muted);font-weight:600;border-right:1px solid var(--gray-border)}
        .ovr-psf-prefix input[type=number]{border:none!important;border-radius:0!important;box-shadow:none!important;flex:1;min-width:0}
        .ovr-psf-check{display:flex;align-items:center;gap:10px;padding:14px 16px;border:1px solid var(--gray-border);border-radius:var(--r-md);background:var(--gray-light)}
        .ovr-psf-check input{width:18px;height:18px}
        .ovr-psf-foot{display:flex;gap:12px;margin-top:8px}
        .ovr-psf-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 26px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:48px}
        .ovr-psf-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold)}
        .ovr-psf-btn--primary:hover{background:var(--gold-dark)}
        .ovr-psf-btn--ghost{background:#fff;color:var(--navy);border-color:var(--gray-border)}
        .ovr-psf-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        @media(max-width:782px){.ovr-psf-wrap{padding:18px 14px 32px}.ovr-psf-grid{grid-template-columns:1fr}.ovr-psf-foot{flex-direction:column}.ovr-psf-btn{width:100%}}
    </style>

    <div class="ovr-psf-wrap">
        <a class="ovr-psf-back" href="<?php echo esc_url( $back_url ); ?>"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'Back to Paid Services', 'ovr-core' ); ?></a>
        <div class="ovr-psf-head">
            <h1><?php echo $is_edit ? esc_html__( 'Edit Service', 'ovr-core' ) : esc_html__( 'Create Service', 'ovr-core' ); ?></h1>
            <p><?php esc_html_e( 'Define a promotional upgrade owners can purchase for a listing.', 'ovr-core' ); ?></p>
        </div>

        <form method="post" action="<?php echo esc_url( $action_url ); ?>">
            <input type="hidden" name="action" value="ovr_paid_service_save">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
            <?php if ( $is_edit ) : ?><input type="hidden" name="service_id" value="<?php echo esc_attr( (int) $s['id'] ); ?>"><?php endif; ?>

            <div class="ovr-psf-card">
                <div class="ovr-psf-field full">
                    <label for="ps-name"><?php esc_html_e( 'Name', 'ovr-core' ); ?></label>
                    <input type="text" id="ps-name" name="name" required value="<?php echo esc_attr( $val( 'name' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Featured Listing — 14 Days', 'ovr-core' ); ?>">
                </div>

                <div class="ovr-psf-field full">
                    <label for="ps-desc"><?php esc_html_e( 'Description', 'ovr-core' ); ?></label>
                    <textarea id="ps-desc" name="description" placeholder="<?php esc_attr_e( 'Shown to owners on the upgrade card.', 'ovr-core' ); ?>"><?php echo esc_textarea( $val( 'description' ) ); ?></textarea>
                </div>

                <div class="ovr-psf-grid">
                    <div class="ovr-psf-field">
                        <label for="ps-type"><?php esc_html_e( 'Service Type (placement behaviour)', 'ovr-core' ); ?></label>
                        <select id="ps-type" name="service_type">
                            <?php foreach ( $types as $key => $meta ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $cur_type, $key ); ?>><?php echo esc_html( $meta['label'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint"><?php esc_html_e( 'Determines where the boost appears (search top, homepage slider, or featured).', 'ovr-core' ); ?></span>
                    </div>

                    <div class="ovr-psf-field">
                        <label for="ps-price"><?php esc_html_e( 'Price', 'ovr-core' ); ?></label>
                        <div class="ovr-psf-prefix">
                            <span><?php echo esc_html( $sym ); ?></span>
                            <input type="number" id="ps-price" name="price" min="0" step="0.01" value="<?php echo esc_attr( $val( 'price', '0' ) ); ?>">
                        </div>
                    </div>

                    <div class="ovr-psf-field">
                        <label for="ps-duration"><?php esc_html_e( 'Duration (days)', 'ovr-core' ); ?></label>
                        <input type="number" id="ps-duration" name="duration_days" min="1" step="1" value="<?php echo esc_attr( $val( 'duration_days', '14' ) ); ?>">
                    </div>

                    <div class="ovr-psf-field">
                        <label for="ps-badge"><?php esc_html_e( 'Badge (optional)', 'ovr-core' ); ?></label>
                        <input type="text" id="ps-badge" name="badge" value="<?php echo esc_attr( $val( 'badge' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. Highest Conversion', 'ovr-core' ); ?>">
                    </div>

                    <div class="ovr-psf-field">
                        <label for="ps-priority"><?php esc_html_e( 'Priority Weight', 'ovr-core' ); ?></label>
                        <input type="number" id="ps-priority" name="priority_weight" step="1" value="<?php echo esc_attr( $val( 'priority_weight', '0' ) ); ?>">
                        <span class="hint"><?php esc_html_e( 'Higher = ranked above other boosts of the same type.', 'ovr-core' ); ?></span>
                    </div>

                    <div class="ovr-psf-field">
                        <label for="ps-max"><?php esc_html_e( 'Max Simultaneous Listings', 'ovr-core' ); ?></label>
                        <input type="number" id="ps-max" name="max_simultaneous" min="0" step="1" value="<?php echo esc_attr( $val( 'max_simultaneous', '0' ) ); ?>">
                        <span class="hint"><?php esc_html_e( '0 = unlimited. Caps how many listings can hold this boost at once (e.g. homepage slider).', 'ovr-core' ); ?></span>
                    </div>

                    <div class="ovr-psf-field">
                        <label for="ps-sort"><?php esc_html_e( 'Sort Order', 'ovr-core' ); ?></label>
                        <input type="number" id="ps-sort" name="sort_order" step="1" value="<?php echo esc_attr( $val( 'sort_order', '0' ) ); ?>">
                        <span class="hint"><?php esc_html_e( 'Lower numbers appear first in the catalogue.', 'ovr-core' ); ?></span>
                    </div>
                </div>

                <?php
                $is_renewable = $is_edit ? ! empty( $s['is_renewable'] ) : true;
                $auto_renew   = $is_edit ? ! empty( $s['auto_renew'] ) : false;
                ?>
                <div class="ovr-psf-field full">
                    <label class="ovr-psf-check">
                        <input type="checkbox" name="is_renewable" value="1" <?php checked( $is_renewable ); ?>>
                        <span><?php esc_html_e( 'Renewable — owners can renew this service when it expires', 'ovr-core' ); ?></span>
                    </label>
                    <label class="ovr-psf-check">
                        <input type="checkbox" name="auto_renew" value="1" <?php checked( $auto_renew ); ?>>
                        <span><?php esc_html_e( 'Auto-Renew — renew automatically at the end of each term', 'ovr-core' ); ?></span>
                    </label>
                </div>

                <div class="ovr-psf-field full">
                    <label class="ovr-psf-check">
                        <input type="checkbox" name="is_active" value="1" <?php checked( $is_active ); ?>>
                        <span><?php esc_html_e( 'Active — available for owners to purchase', 'ovr-core' ); ?></span>
                    </label>
                </div>

                <div class="ovr-psf-foot">
                    <button type="submit" class="ovr-psf-btn ovr-psf-btn--primary">
                        <span class="material-symbols-outlined">save</span><?php echo $is_edit ? esc_html__( 'Update Service', 'ovr-core' ) : esc_html__( 'Create Service', 'ovr-core' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $back_url ); ?>" class="ovr-psf-btn ovr-psf-btn--ghost"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></a>
                </div>
            </div>
        </form>
    </div>
</div>
