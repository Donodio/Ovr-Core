<?php
/**
 * Subscription Plans Management template — admin editor.
 *
 * A table of every billing tier with status/price/duration/limits, plus a
 * :target-driven edit modal per plan and an "add plan" modal. Rendered inside
 * the WordPress admin (which supplies its own chrome, so the standalone mockup
 * sidebar/topbar is dropped). A small inline script powers the feature
 * repeater; everything else works without JavaScript.
 *
 * @package OVR
 * @var array      $plans     slug => plan array, pre-sorted by sort_order.
 * @var array      $periods   storage-key => human label map.
 * @var string[]   $protected Slugs that cannot be deleted.
 * @var string     $currency  Currency symbol.
 * @var string     $save_url  admin-post.php URL.
 * @var string     $page_url  This screen's URL.
 * @var int        $next_sort Suggested sort_order for a new plan.
 * @var array|null $notice    ['type','text'] result notice, or null.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$price_suffix = [ 'monthly' => '/mo', 'quarterly' => '/qtr', 'annually' => '/yr', 'one_time' => '' ];

/** Format a price without trailing .00 but with cents when present. */
$fmt_price = static function ( float $p ) {
    return $p === (float) (int) $p ? number_format_i18n( (int) $p ) : number_format( $p, 2 );
};

/**
 * Render an edit/add modal. $slug empty + $is_new=true builds the "add" modal.
 *
 * @param array $plan
 */
$render_modal = static function ( string $slug, array $plan, bool $is_new ) use ( $periods, $currency, $save_url ) {
    $id      = $is_new ? 'ovr-pm-new' : 'ovr-pm-' . $slug;
    $name    = (string) ( $plan['name'] ?? '' );
    $price   = (float)  ( $plan['price'] ?? 0 );
    $period  = (string) ( $plan['period'] ?? 'monthly' );
    $max     = (int)    ( $plan['max_listings'] ?? 1 );
    $desc    = (string) ( $plan['description'] ?? '' );
    $active  = $is_new ? true : ! empty( $plan['is_active'] );
    $popular = ! empty( $plan['is_popular'] );
    $promo   = ! empty( $plan['support_promo'] );
    $note    = (string) ( $plan['checkout_note'] ?? '' );
    $features = is_array( $plan['features'] ?? null ) ? $plan['features'] : [];
    if ( $is_new && ! $features ) {
        $features = [ '' ]; // Start the add form with one empty feature row.
    }
    $title = $is_new
        ? __( 'Add New Plan', 'ovr-core' )
        : sprintf( __( 'Edit Plan: %s', 'ovr-core' ), $name );
    ?>
    <div id="<?php echo esc_attr( $id ); ?>" class="ovr-pm" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $title ); ?>">
        <a href="#ovr-pm-close" class="ovr-pm-backdrop" aria-label="<?php esc_attr_e( 'Close', 'ovr-core' ); ?>" tabindex="-1"></a>
        <div class="ovr-pm-card">
            <form method="post" action="<?php echo esc_url( $save_url ); ?>">
                <input type="hidden" name="action" value="ovr_save_plans">
                <?php wp_nonce_field( 'ovr_save_plans_action', 'ovr_plans_nonce' ); ?>
                <?php if ( $is_new ) : ?>
                    <input type="hidden" name="plan[sort_order]" value="<?php echo esc_attr( (string) ( $plan['sort_order'] ?? 99 ) ); ?>">
                <?php else : ?>
                    <input type="hidden" name="plan[existing_slug]" value="<?php echo esc_attr( $slug ); ?>">
                    <input type="hidden" name="plan[sort_order]" value="<?php echo esc_attr( (string) ( $plan['sort_order'] ?? 99 ) ); ?>">
                <?php endif; ?>

                <div class="ovr-pm-head">
                    <h3><?php echo esc_html( $title ); ?></h3>
                    <a href="#ovr-pm-close" class="ovr-pm-x" aria-label="<?php esc_attr_e( 'Close', 'ovr-core' ); ?>"><span class="material-symbols-outlined">close</span></a>
                </div>

                <div class="ovr-pm-body">
                    <div class="ovr-pm-grid ovr-pm-grid--2">
                        <div class="ovr-fld">
                            <label><?php esc_html_e( 'Plan Name', 'ovr-core' ); ?></label>
                            <input type="text" name="plan[name]" value="<?php echo esc_attr( $name ); ?>" required>
                        </div>
                        <div class="ovr-fld">
                            <label><?php esc_html_e( 'Status', 'ovr-core' ); ?></label>
                            <label class="ovr-switch">
                                <input type="checkbox" name="plan[is_active]" value="1" <?php checked( $active ); ?>>
                                <span class="ovr-switch-track"><span class="ovr-switch-knob"></span></span>
                                <span class="ovr-switch-txt ovr-switch-on"><?php esc_html_e( 'Active', 'ovr-core' ); ?></span>
                                <span class="ovr-switch-txt ovr-switch-off"><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <?php if ( $is_new ) : ?>
                        <div class="ovr-fld">
                            <label><?php esc_html_e( 'Slug (optional)', 'ovr-core' ); ?></label>
                            <input type="text" name="plan[slug]" pattern="[a-z0-9_\-]+" placeholder="auto-generated-from-name">
                            <small><?php esc_html_e( 'Lowercase letters, numbers, underscores. Leave blank to generate from the name.', 'ovr-core' ); ?></small>
                        </div>
                    <?php endif; ?>

                    <div class="ovr-pm-grid ovr-pm-grid--3">
                        <div class="ovr-fld">
                            <label><?php printf( esc_html__( 'Price (%s)', 'ovr-core' ), esc_html( $currency ) ); ?></label>
                            <input type="number" name="plan[price]" min="0" step="0.01" value="<?php echo esc_attr( number_format( $price, 2, '.', '' ) ); ?>">
                        </div>
                        <div class="ovr-fld">
                            <label><?php esc_html_e( 'Duration', 'ovr-core' ); ?></label>
                            <select name="plan[period]">
                                <?php foreach ( $periods as $key => $label ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $period, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ovr-fld">
                            <label><?php esc_html_e( 'Max Listings', 'ovr-core' ); ?></label>
                            <input type="number" name="plan[max_listings]" min="-1" value="<?php echo esc_attr( (string) $max ); ?>">
                            <small><?php esc_html_e( '-1 = unlimited', 'ovr-core' ); ?></small>
                        </div>
                    </div>

                    <div class="ovr-fld">
                        <label><?php esc_html_e( 'Short Description', 'ovr-core' ); ?></label>
                        <input type="text" name="plan[description]" value="<?php echo esc_attr( $desc ); ?>">
                    </div>

                    <hr class="ovr-pm-rule">

                    <div class="ovr-pm-feats">
                        <div class="ovr-pm-feats-head">
                            <label><?php esc_html_e( 'Included Features', 'ovr-core' ); ?></label>
                            <button type="button" class="ovr-pm-addfeat"><span class="material-symbols-outlined">add</span><?php esc_html_e( 'Add Feature', 'ovr-core' ); ?></button>
                        </div>
                        <div class="ovr-pm-featlist">
                            <?php foreach ( $features as $f ) : ?>
                                <div class="ovr-pm-featrow">
                                    <span class="material-symbols-outlined ovr-pm-featic">check_circle</span>
                                    <input type="text" name="plan[features][]" value="<?php echo esc_attr( $f ); ?>" placeholder="<?php esc_attr_e( 'Feature description', 'ovr-core' ); ?>">
                                    <button type="button" class="ovr-pm-featdel" aria-label="<?php esc_attr_e( 'Remove feature', 'ovr-core' ); ?>"><span class="material-symbols-outlined">delete</span></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <hr class="ovr-pm-rule">

                    <div class="ovr-pm-feats">
                        <label class="ovr-pm-section-lbl"><?php esc_html_e( 'Marketing Configuration', 'ovr-core' ); ?></label>
                        <div class="ovr-pm-grid ovr-pm-grid--2">
                            <div class="ovr-fld">
                                <label class="ovr-check">
                                    <input type="checkbox" name="plan[support_promo]" value="1" <?php checked( $promo ); ?>>
                                    <span><?php esc_html_e( 'Support Promo Codes', 'ovr-core' ); ?></span>
                                </label>
                                <small class="ovr-pm-indent"><?php esc_html_e( 'Allow users to apply discounts at checkout.', 'ovr-core' ); ?></small>
                                <label class="ovr-check" style="margin-top:12px">
                                    <input type="checkbox" name="plan[is_popular]" value="1" <?php checked( $popular ); ?>>
                                    <span><?php esc_html_e( 'Highlight as “Most Popular”', 'ovr-core' ); ?></span>
                                </label>
                            </div>
                            <div class="ovr-fld">
                                <label><?php esc_html_e( 'Validity Message (Checkout)', 'ovr-core' ); ?></label>
                                <input type="text" name="plan[checkout_note]" value="<?php echo esc_attr( $note ); ?>" placeholder="<?php esc_attr_e( 'Billed monthly. Cancel anytime.', 'ovr-core' ); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ovr-pm-foot">
                    <a href="#ovr-pm-close" class="ovr-btn ovr-btn--ghost"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></a>
                    <button type="submit" class="ovr-btn ovr-btn--primary"><?php echo $is_new ? esc_html__( 'Create Plan', 'ovr-core' ) : esc_html__( 'Save Changes', 'ovr-core' ); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
};
?>
<div class="wrap ovr-plans">

    <style>
        #wpcontent,#wpbody-content{background:#f7faf9}
        #wpcontent{padding-left:0}
        .ovr-plans{--p:#004c4c;--pc:#006666;--opc:#93e1e0;--sec:#006c4a;--secc:#74f7be;--osc:#00714e;--ter:#735c00;--terc:#cca72f;--err:#ba1a1a;--errc:#ffdad6;--surf:#fff;--sv:#3f4948;--ov:#bec9c8;--on:#181c1c;font-family:'Inter',system-ui,sans-serif;max-width:none;margin:20px 0 56px;padding:0 40px;color:var(--on)}
        .ovr-plans,.ovr-plans *{box-sizing:border-box}
        .ovr-plans .material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;line-height:1}

        .ovr-plans-notice{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:12px;font-size:14px;font-weight:500;margin:0 0 22px}
        .ovr-plans-notice .material-symbols-outlined{font-size:20px}
        .ovr-plans-notice--success{background:#e3f6ec;border:1px solid #9fe0bd;color:#00513a}
        .ovr-plans-notice--error{background:#fff5f4;border:1px solid #f4cfca;color:#93000a}

        .ovr-plans-head{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;flex-wrap:wrap;margin:6px 0 28px}
        .ovr-plans-head h1{font-size:34px;font-weight:700;letter-spacing:-.02em;margin:0;padding:0;line-height:1.15;color:var(--on)}
        .ovr-plans-head p{margin:7px 0 0;color:var(--sv);font-size:15px}
        .ovr-btn{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;border-radius:10px;font-size:14px;font-weight:600;text-decoration:none;line-height:1;border:1px solid transparent;cursor:pointer;font-family:inherit}
        .ovr-btn .material-symbols-outlined{font-size:18px}
        .ovr-btn--primary{background:var(--p);color:#fff}
        .ovr-btn--primary:hover{background:#003838;color:#fff}
        .ovr-btn--ghost{background:var(--surf);color:var(--p);border:1px solid var(--p)}
        .ovr-btn--ghost:hover{background:#eef4f4;color:var(--p)}

        /* Table */
        .ovr-plans-card{background:var(--surf);border:1px solid var(--ov);border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.04);overflow:hidden}
        .ovr-plans-table{width:100%;border-collapse:collapse;text-align:left}
        .ovr-plans-table thead{background:#f1f4f3;border-bottom:1px solid var(--ov)}
        .ovr-plans-table th{padding:15px 24px;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--sv)}
        .ovr-plans-table th.tc{text-align:center}.ovr-plans-table th.tr{text-align:right}
        .ovr-plans-table td{padding:18px 24px;border-bottom:1px solid #eceeed;vertical-align:middle;color:var(--on)}
        .ovr-plans-table tr:last-child td{border-bottom:none}
        .ovr-pl-row:hover{background:#f7faf9}
        .ovr-pl-row.is-popular{box-shadow:inset 4px 0 0 var(--p)}
        .ovr-pl-name{font-size:18px;font-weight:700;margin:0;display:flex;align-items:center;gap:8px}
        .ovr-pl-tag{font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--p);background:var(--opc);padding:3px 8px;border-radius:9999px}
        .ovr-pl-desc{font-size:13px;color:var(--sv);margin:4px 0 0}
        .ovr-pl-price{font-size:20px;font-weight:700;color:var(--p);white-space:nowrap}
        .ovr-pl-price small{font-size:13px;font-weight:400;color:var(--sv)}
        .ovr-pl-num{text-align:center}
        .ovr-pl-status{text-align:center}
        .ovr-chip{display:inline-flex;align-items:center;padding:5px 13px;border-radius:9999px;font-size:11px;font-weight:700;letter-spacing:.03em;text-transform:uppercase}
        .ovr-chip--on{background:var(--secc);color:var(--osc)}
        .ovr-chip--off{background:var(--surface-variant,#e0e3e2);color:var(--sv)}
        .ovr-pl-act{text-align:right;white-space:nowrap}
        .ovr-pl-edit{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;color:var(--sv);text-decoration:none;border:1px solid transparent}
        .ovr-pl-edit:hover{color:var(--p);background:#e9f1f1}
        .ovr-pl-edit .material-symbols-outlined{font-size:20px}
        .ovr-pl-del{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;color:var(--sv);text-decoration:none;border:1px solid transparent;margin-left:2px}
        .ovr-pl-del:hover{color:var(--err);background:var(--errc)}
        .ovr-pl-del .material-symbols-outlined{font-size:19px}
        .ovr-pl-row.is-inactive .ovr-pl-name,.ovr-pl-row.is-inactive .ovr-pl-desc,.ovr-pl-row.is-inactive .ovr-pl-price,.ovr-pl-row.is-inactive .ovr-pl-num,.ovr-pl-row.is-inactive .ovr-pl-dur{opacity:.55}

        /* Modal */
        .ovr-pm{position:fixed;inset:0;z-index:100000;display:none;align-items:flex-start;justify-content:center;padding:40px 16px}
        .ovr-pm:target{display:flex}
        .ovr-pm-backdrop{position:absolute;inset:0;background:rgba(24,28,28,.45);backdrop-filter:blur(3px)}
        .ovr-pm-card{position:relative;background:var(--surf);border:1px solid var(--ov);border-radius:16px;box-shadow:0 24px 60px rgba(0,0,0,.3);width:100%;max-width:660px;max-height:calc(100vh - 80px);overflow-y:auto;z-index:1}
        .ovr-pm-head{position:sticky;top:0;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);display:flex;justify-content:space-between;align-items:center;gap:12px;padding:22px 28px;border-bottom:1px solid var(--ov);z-index:2}
        .ovr-pm-head h3{font-size:24px;font-weight:700;margin:0;padding:0;color:var(--on);line-height:1.2}
        .ovr-pm-x{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:9999px;color:var(--sv);text-decoration:none;flex-shrink:0}
        .ovr-pm-x:hover{color:var(--err);background:var(--errc)}
        .ovr-pm-body{padding:28px}
        .ovr-pm-grid{display:grid;gap:20px}
        .ovr-pm-grid--2{grid-template-columns:1fr 1fr}
        .ovr-pm-grid--3{grid-template-columns:1fr 1fr 1fr}
        .ovr-pm-rule{border:none;border-top:1px solid var(--ov);margin:24px 0}
        .ovr-pm-section-lbl{display:block;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);margin-bottom:14px}

        .ovr-fld{margin-bottom:20px}
        .ovr-fld:last-child{margin-bottom:0}
        .ovr-pm-grid .ovr-fld{margin-bottom:0}
        .ovr-fld>label{display:block;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);margin-bottom:8px}
        .ovr-fld input[type=text],.ovr-fld input[type=number],.ovr-fld select{width:100%;background:#fff;border:1px solid var(--ov);border-radius:10px;padding:11px 13px;font-size:14px;color:var(--on);font-family:inherit;outline:none;line-height:1.4}
        .ovr-fld input:focus,.ovr-fld select:focus{border-color:var(--p);box-shadow:0 0 0 2px rgba(0,76,76,.18)}
        .ovr-fld small{display:block;margin-top:6px;font-size:12px;color:var(--sv)}

        .ovr-switch{display:inline-flex;align-items:center;gap:12px;cursor:pointer;background:#fff;border:1px solid var(--ov);border-radius:10px;padding:0 14px;width:100%;height:46px;position:relative;user-select:none}
        /* Kill the native WP-admin checkbox entirely; the track/knob is the control. */
        .ovr-switch input{position:absolute;left:-9999px;width:1px;height:1px;margin:0;padding:0;opacity:0;-webkit-appearance:none;appearance:none}
        .ovr-switch-track{position:relative;width:44px;height:24px;border-radius:9999px;background:var(--ov);transition:background .2s;flex-shrink:0}
        .ovr-switch-knob{position:absolute;left:3px;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.35);transition:transform .2s}
        .ovr-switch input:checked+.ovr-switch-track{background:var(--p)}
        .ovr-switch input:checked+.ovr-switch-track .ovr-switch-knob{transform:translateX(20px)}
        .ovr-switch input:focus-visible+.ovr-switch-track{box-shadow:0 0 0 3px rgba(0,76,76,.28)}
        .ovr-switch-txt{font-size:14px;font-weight:600}
        .ovr-switch-on{color:var(--sec)}
        .ovr-switch-off{color:var(--sv)}
        .ovr-switch input:not(:checked)~.ovr-switch-on{display:none}
        .ovr-switch input:checked~.ovr-switch-off{display:none}

        .ovr-check{display:inline-flex;align-items:center;gap:9px;cursor:pointer;font-size:14px;color:var(--on)}
        .ovr-check input{width:17px;height:17px;accent-color:var(--p);cursor:pointer}
        .ovr-pm-indent{margin:6px 0 0 26px;font-size:12px;color:var(--sv)}

        .ovr-pm-feats-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
        .ovr-pm-feats-head label{font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv)}
        .ovr-pm-addfeat{display:inline-flex;align-items:center;gap:4px;background:none;border:none;color:var(--p);font-size:12px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;cursor:pointer;font-family:inherit;padding:4px 6px;border-radius:8px}
        .ovr-pm-addfeat:hover{background:#e9f1f1}
        .ovr-pm-addfeat .material-symbols-outlined{font-size:16px}
        .ovr-pm-featlist{display:flex;flex-direction:column;gap:10px}
        .ovr-pm-featrow{display:flex;align-items:center;gap:10px}
        .ovr-pm-featic{color:var(--sec);font-size:20px;flex-shrink:0}
        .ovr-pm-featrow input{flex:1;background:#fff;border:1px solid var(--ov);border-radius:10px;padding:9px 12px;font-size:14px;color:var(--on);font-family:inherit;outline:none}
        .ovr-pm-featrow input:focus{border-color:var(--p);box-shadow:0 0 0 2px rgba(0,76,76,.18)}
        .ovr-pm-featdel{display:inline-flex;align-items:center;justify-content:center;background:none;border:none;color:var(--sv);cursor:pointer;padding:6px;border-radius:8px;flex-shrink:0}
        .ovr-pm-featdel:hover{color:var(--err);background:var(--errc)}
        .ovr-pm-featdel .material-symbols-outlined{font-size:19px}

        .ovr-pm-foot{position:sticky;bottom:0;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);display:flex;justify-content:flex-end;gap:12px;padding:18px 28px;border-top:1px solid var(--ov)}

        @media (max-width:782px){
            .ovr-plans{padding:0 12px}
            .ovr-pm-grid--2,.ovr-pm-grid--3{grid-template-columns:1fr}
            /* Stack the table into cards */
            .ovr-plans-table thead{display:none}
            .ovr-plans-table,.ovr-plans-table tbody,.ovr-plans-table tr,.ovr-plans-table td{display:block;width:100%}
            .ovr-pl-row{padding:8px 4px;border-bottom:1px solid var(--ov)}
            .ovr-plans-table td{border:none;padding:7px 18px;display:flex;justify-content:space-between;align-items:center;gap:16px;text-align:right}
            .ovr-plans-table td::before{content:attr(data-label);font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--sv);text-align:left}
            .ovr-pl-namecell{display:block;text-align:left}
            .ovr-pl-namecell::before{display:none}
            .ovr-pl-act{justify-content:flex-end}
        }
        @media (max-width:600px){
            .ovr-plans-head h1{font-size:26px}
            .ovr-plans-head .ovr-btn{width:100%;justify-content:center}
            .ovr-pm{padding:0}
            .ovr-pm-card{max-width:none;min-height:100vh;max-height:100vh;border-radius:0}
        }
    </style>

    <?php if ( $notice ) : ?>
        <div class="ovr-plans-notice ovr-plans-notice--<?php echo esc_attr( $notice['type'] ); ?>">
            <span class="material-symbols-outlined"><?php echo 'success' === $notice['type'] ? 'check_circle' : 'error'; ?></span>
            <span><?php echo esc_html( $notice['text'] ); ?></span>
        </div>
    <?php endif; ?>

    <div class="ovr-plans-head">
        <div>
            <h1><?php esc_html_e( 'Subscription Plans', 'ovr-core' ); ?></h1>
            <p><?php esc_html_e( 'Manage billing tiers and feature access for your users.', 'ovr-core' ); ?></p>
        </div>
        <a href="#ovr-pm-new" class="ovr-btn ovr-btn--primary">
            <span class="material-symbols-outlined">add</span><?php esc_html_e( 'Add New Plan', 'ovr-core' ); ?>
        </a>
    </div>

    <div class="ovr-plans-card">
        <table class="ovr-plans-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Plan Name', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Price', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Duration', 'ovr-core' ); ?></th>
                    <th class="tc"><?php esc_html_e( 'Max Listings', 'ovr-core' ); ?></th>
                    <th class="tc"><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                    <th class="tr"><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $plans as $slug => $plan ) :
                    $slug      = (string) $slug;
                    $is_active = ! empty( $plan['is_active'] );
                    $period    = (string) ( $plan['period'] ?? 'monthly' );
                    $max       = (int) ( $plan['max_listings'] ?? 1 );
                    $is_prot   = in_array( $slug, $protected, true );
                    $row_class = 'ovr-pl-row' . ( $is_active ? '' : ' is-inactive' ) . ( ! empty( $plan['is_popular'] ) ? ' is-popular' : '' );
                ?>
                    <tr class="<?php echo esc_attr( $row_class ); ?>">
                        <td class="ovr-pl-namecell">
                            <p class="ovr-pl-name">
                                <?php echo esc_html( $plan['name'] ?? __( '(unnamed)', 'ovr-core' ) ); ?>
                                <?php if ( ! empty( $plan['is_popular'] ) ) : ?>
                                    <span class="ovr-pl-tag"><?php esc_html_e( 'Popular', 'ovr-core' ); ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if ( ! empty( $plan['description'] ) ) : ?>
                                <p class="ovr-pl-desc"><?php echo esc_html( $plan['description'] ); ?></p>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php esc_attr_e( 'Price', 'ovr-core' ); ?>">
                            <span class="ovr-pl-price"><?php echo esc_html( $currency . $fmt_price( (float) ( $plan['price'] ?? 0 ) ) ); ?><small><?php echo esc_html( $price_suffix[ $period ] ?? '' ); ?></small></span>
                        </td>
                        <td class="ovr-pl-dur" data-label="<?php esc_attr_e( 'Duration', 'ovr-core' ); ?>"><?php echo esc_html( $periods[ $period ] ?? ucfirst( $period ) ); ?></td>
                        <td class="ovr-pl-num" data-label="<?php esc_attr_e( 'Max Listings', 'ovr-core' ); ?>"><?php echo $max < 0 ? esc_html__( 'Unlimited', 'ovr-core' ) : esc_html( number_format_i18n( $max ) ); ?></td>
                        <td class="ovr-pl-status" data-label="<?php esc_attr_e( 'Status', 'ovr-core' ); ?>">
                            <span class="ovr-chip <?php echo $is_active ? 'ovr-chip--on' : 'ovr-chip--off'; ?>"><?php echo $is_active ? esc_html__( 'Active', 'ovr-core' ) : esc_html__( 'Inactive', 'ovr-core' ); ?></span>
                        </td>
                        <td class="ovr-pl-act" data-label="<?php esc_attr_e( 'Actions', 'ovr-core' ); ?>">
                            <a href="#ovr-pm-<?php echo esc_attr( $slug ); ?>" class="ovr-pl-edit" aria-label="<?php esc_attr_e( 'Edit plan', 'ovr-core' ); ?>"><span class="material-symbols-outlined">edit</span></a>
                            <?php if ( ! $is_prot ) : ?>
                                <a href="<?php echo esc_url( wp_nonce_url(
                                    add_query_arg( [ 'action' => 'ovr_delete_plan', 'plan' => $slug ], $save_url ),
                                    'ovr_delete_plan_' . $slug
                                ) ); ?>" class="ovr-pl-del" aria-label="<?php esc_attr_e( 'Delete plan', 'ovr-core' ); ?>"
                                   onclick="return confirm('<?php echo esc_js( __( 'Delete this plan? Subscribers on it move to Base Subscriber. This cannot be undone.', 'ovr-core' ) ); ?>');"><span class="material-symbols-outlined">delete</span></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    // Edit modals for every plan, plus the add-plan modal.
    foreach ( $plans as $slug => $plan ) {
        $render_modal( (string) $slug, $plan, false );
    }
    $render_modal( '', [ 'sort_order' => $next_sort ], true );
    ?>

    <!-- Cloneable feature row for the JS repeater -->
    <template id="ovr-pm-feat-tpl">
        <div class="ovr-pm-featrow">
            <span class="material-symbols-outlined ovr-pm-featic">check_circle</span>
            <input type="text" name="plan[features][]" value="" placeholder="<?php esc_attr_e( 'Feature description', 'ovr-core' ); ?>">
            <button type="button" class="ovr-pm-featdel" aria-label="<?php esc_attr_e( 'Remove feature', 'ovr-core' ); ?>"><span class="material-symbols-outlined">delete</span></button>
        </div>
    </template>

    <script>
    (function () {
        var root = document.querySelector('.ovr-plans');
        if (!root) return;
        var tpl = document.getElementById('ovr-pm-feat-tpl');

        root.addEventListener('click', function (e) {
            var add = e.target.closest('.ovr-pm-addfeat');
            if (add) {
                e.preventDefault();
                var list = add.closest('.ovr-pm-feats').querySelector('.ovr-pm-featlist');
                var node = tpl.content.firstElementChild.cloneNode(true);
                list.appendChild(node);
                var input = node.querySelector('input');
                if (input) input.focus();
                return;
            }
            var del = e.target.closest('.ovr-pm-featdel');
            if (del) {
                e.preventDefault();
                var row = del.closest('.ovr-pm-featrow');
                if (row) row.remove();
            }
        });
    })();
    </script>
</div>
