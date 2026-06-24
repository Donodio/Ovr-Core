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
                    <div class="ovr-adm-form-grid">
                        <div class="ovr-adm-field">
                            <label class="ovr-adm-label"><?php esc_html_e( 'Plan Name', 'ovr-core' ); ?></label>
                            <input type="text" class="ovr-adm-input" name="plan[name]" value="<?php echo esc_attr( $name ); ?>" required>
                        </div>
                        <div class="ovr-adm-field">
                            <label class="ovr-adm-label"><?php esc_html_e( 'Status', 'ovr-core' ); ?></label>
                            <label class="ovr-switch">
                                <input type="checkbox" name="plan[is_active]" value="1" <?php checked( $active ); ?>>
                                <span class="ovr-switch-track"><span class="ovr-switch-knob"></span></span>
                                <span class="ovr-switch-txt ovr-switch-on"><?php esc_html_e( 'Active', 'ovr-core' ); ?></span>
                                <span class="ovr-switch-txt ovr-switch-off"><?php esc_html_e( 'Inactive', 'ovr-core' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <?php if ( $is_new ) : ?>
                        <div class="ovr-adm-field">
                            <label class="ovr-adm-label"><?php esc_html_e( 'Slug (optional)', 'ovr-core' ); ?></label>
                            <input type="text" class="ovr-adm-input" name="plan[slug]" pattern="[a-z0-9_\-]+" placeholder="auto-generated-from-name">
                            <p class="ovr-adm-hint"><?php esc_html_e( 'Lowercase letters, numbers, underscores. Leave blank to generate from the name.', 'ovr-core' ); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="ovr-adm-form-grid ovr-pm-grid--3">
                        <div class="ovr-adm-field">
                            <label class="ovr-adm-label"><?php printf( esc_html__( 'Price (%s)', 'ovr-core' ), esc_html( $currency ) ); ?></label>
                            <input type="number" class="ovr-adm-input" name="plan[price]" min="0" step="0.01" value="<?php echo esc_attr( number_format( $price, 2, '.', '' ) ); ?>">
                        </div>
                        <div class="ovr-adm-field">
                            <label class="ovr-adm-label"><?php esc_html_e( 'Duration', 'ovr-core' ); ?></label>
                            <select class="ovr-adm-select" name="plan[period]">
                                <?php foreach ( $periods as $key => $label ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $period, $key ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ovr-adm-field">
                            <label class="ovr-adm-label"><?php esc_html_e( 'Max Listings', 'ovr-core' ); ?></label>
                            <input type="number" class="ovr-adm-input" name="plan[max_listings]" min="-1" value="<?php echo esc_attr( (string) $max ); ?>">
                            <p class="ovr-adm-hint"><?php esc_html_e( '-1 = unlimited', 'ovr-core' ); ?></p>
                        </div>
                    </div>

                    <div class="ovr-adm-field">
                        <label class="ovr-adm-label"><?php esc_html_e( 'Short Description', 'ovr-core' ); ?></label>
                        <input type="text" class="ovr-adm-input" name="plan[description]" value="<?php echo esc_attr( $desc ); ?>">
                    </div>

                    <hr class="ovr-pm-rule">

                    <div class="ovr-pm-feats">
                        <div class="ovr-pm-feats-head">
                            <label class="ovr-adm-label"><?php esc_html_e( 'Included Features', 'ovr-core' ); ?></label>
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
                        <div class="ovr-adm-form-grid">
                            <div class="ovr-adm-field">
                                <label class="ovr-adm-check">
                                    <input type="checkbox" name="plan[support_promo]" value="1" <?php checked( $promo ); ?>>
                                    <span><?php esc_html_e( 'Support Promo Codes', 'ovr-core' ); ?></span>
                                </label>
                                <p class="ovr-adm-hint ovr-pm-indent"><?php esc_html_e( 'Allow users to apply discounts at checkout.', 'ovr-core' ); ?></p>
                                <label class="ovr-adm-check" style="margin-top:12px">
                                    <input type="checkbox" name="plan[is_popular]" value="1" <?php checked( $popular ); ?>>
                                    <span><?php esc_html_e( 'Highlight as “Most Popular”', 'ovr-core' ); ?></span>
                                </label>
                            </div>
                            <div class="ovr-adm-field">
                                <label class="ovr-adm-label"><?php esc_html_e( 'Validity Message (Checkout)', 'ovr-core' ); ?></label>
                                <input type="text" class="ovr-adm-input" name="plan[checkout_note]" value="<?php echo esc_attr( $note ); ?>" placeholder="<?php esc_attr_e( 'Billed monthly. Cancel anytime.', 'ovr-core' ); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ovr-pm-foot">
                    <a href="#ovr-pm-close" class="ovr-adm-btn ovr-adm-btn--ghost"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></a>
                    <button type="submit" class="ovr-adm-btn ovr-adm-btn--primary"><span class="material-symbols-outlined"><?php echo $is_new ? 'add' : 'save'; ?></span><?php echo $is_new ? esc_html__( 'Create Plan', 'ovr-core' ) : esc_html__( 'Save Changes', 'ovr-core' ); ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
};
?>
<div class="wrap ovr-adm ovr-plans">
    <style>#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}</style>
    <style>
        /* Screen-unique: modal overlay + feature-row repeater + status switch, scoped under .ovr-adm using the shared palette. */
        .ovr-adm .ovr-pm-grid--3{grid-template-columns:1fr 1fr 1fr}
        .ovr-adm .ovr-pm-rule{border:none;border-top:1px solid var(--gray-border);margin:24px 0}
        .ovr-adm .ovr-pm-section-lbl{display:block;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);margin-bottom:14px}
        .ovr-adm .ovr-pm-indent{margin-left:27px}

        /* Modal */
        .ovr-adm .ovr-pm{position:fixed;inset:0;z-index:100000;display:none;align-items:flex-start;justify-content:center;padding:40px 16px}
        .ovr-adm .ovr-pm:target{display:flex}
        .ovr-adm .ovr-pm-backdrop{position:absolute;inset:0;background:rgba(0,9,97,.45);backdrop-filter:blur(3px)}
        .ovr-adm .ovr-pm-card{position:relative;background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:0 24px 60px rgba(0,9,97,.3);width:100%;max-width:660px;max-height:calc(100vh - 80px);overflow-y:auto;z-index:1}
        .ovr-adm .ovr-pm-head{position:sticky;top:0;background:rgba(255,255,255,.94);backdrop-filter:blur(8px);display:flex;justify-content:space-between;align-items:center;gap:12px;padding:20px 28px;border-bottom:1px solid var(--gray-border);z-index:2}
        .ovr-adm .ovr-pm-head h3{font-size:22px;font-weight:700;margin:0;padding:0;color:var(--ink);line-height:1.2}
        .ovr-adm .ovr-pm-x{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:9999px;color:var(--gray-mid);text-decoration:none;flex-shrink:0}
        .ovr-adm .ovr-pm-x:hover{color:var(--red);background:var(--red-light)}
        .ovr-adm .ovr-pm-body{padding:28px}
        .ovr-adm .ovr-pm-body .ovr-adm-field:last-child{margin-bottom:0}

        /* Status switch */
        .ovr-adm .ovr-switch{display:inline-flex;align-items:center;gap:12px;cursor:pointer;background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:0 14px;width:100%;height:46px;position:relative;user-select:none}
        .ovr-adm .ovr-switch input{position:absolute;left:-9999px;width:1px;height:1px;margin:0;padding:0;opacity:0;-webkit-appearance:none;appearance:none}
        .ovr-adm .ovr-switch-track{position:relative;width:44px;height:24px;border-radius:9999px;background:var(--gray-border);transition:background .2s;flex-shrink:0}
        .ovr-adm .ovr-switch-knob{position:absolute;left:3px;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.35);transition:transform .2s}
        .ovr-adm .ovr-switch input:checked+.ovr-switch-track{background:var(--navy)}
        .ovr-adm .ovr-switch input:checked+.ovr-switch-track .ovr-switch-knob{transform:translateX(20px)}
        .ovr-adm .ovr-switch input:focus-visible+.ovr-switch-track{box-shadow:0 0 0 3px rgba(0,9,97,.28)}
        .ovr-adm .ovr-switch-txt{font-size:14px;font-weight:600}
        .ovr-adm .ovr-switch-on{color:var(--green)}
        .ovr-adm .ovr-switch-off{color:var(--muted)}
        .ovr-adm .ovr-switch input:not(:checked)~.ovr-switch-on{display:none}
        .ovr-adm .ovr-switch input:checked~.ovr-switch-off{display:none}

        /* Feature repeater */
        .ovr-adm .ovr-pm-feats-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
        .ovr-adm .ovr-pm-feats-head .ovr-adm-label{margin:0}
        .ovr-adm .ovr-pm-addfeat{display:inline-flex;align-items:center;gap:4px;background:none;border:none;color:var(--blue);font-size:12px;font-weight:700;letter-spacing:.03em;text-transform:uppercase;cursor:pointer;font-family:inherit;padding:4px 6px;border-radius:var(--r-sm)}
        .ovr-adm .ovr-pm-addfeat:hover{background:var(--blue-light)}
        .ovr-adm .ovr-pm-addfeat .material-symbols-outlined{font-size:16px}
        .ovr-adm .ovr-pm-featlist{display:flex;flex-direction:column;gap:10px}
        .ovr-adm .ovr-pm-featrow{display:flex;align-items:center;gap:10px}
        .ovr-adm .ovr-pm-featic{color:var(--green);font-size:20px;flex-shrink:0}
        .ovr-adm .ovr-pm-featrow input{flex:1;background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-md);padding:9px 12px;font-size:14px;color:var(--ink);font-family:inherit;outline:none}
        .ovr-adm .ovr-pm-featrow input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,9,97,.12)}
        .ovr-adm .ovr-pm-featdel{display:inline-flex;align-items:center;justify-content:center;background:none;border:none;color:var(--gray-mid);cursor:pointer;padding:6px;border-radius:var(--r-sm);flex-shrink:0}
        .ovr-adm .ovr-pm-featdel:hover{color:var(--red);background:var(--red-light)}
        .ovr-adm .ovr-pm-featdel .material-symbols-outlined{font-size:19px}

        .ovr-adm .ovr-pm-foot{position:sticky;bottom:0;background:rgba(255,255,255,.94);backdrop-filter:blur(8px);display:flex;justify-content:flex-end;gap:12px;padding:18px 28px;border-top:1px solid var(--gray-border)}

        /* Popular row marker on the table. */
        .ovr-adm .ovr-plans-table tr.is-popular td:first-child{box-shadow:inset 4px 0 0 var(--navy)}

        @media (max-width:782px){
            .ovr-adm .ovr-pm-grid--3{grid-template-columns:1fr}
        }
        @media (max-width:600px){
            .ovr-adm .ovr-pm{padding:0}
            .ovr-adm .ovr-pm-card{max-width:none;min-height:100vh;max-height:100vh;border-radius:0}
        }
    </style>

    <div class="ovr-adm-wrap">

        <?php if ( $notice ) : ?>
            <div class="ovr-adm-notice ovr-adm-notice--<?php echo esc_attr( $notice['type'] ); ?>">
                <span class="material-symbols-outlined"><?php echo 'success' === $notice['type'] ? 'check_circle' : 'error'; ?></span>
                <span><?php echo esc_html( $notice['text'] ); ?></span>
            </div>
        <?php endif; ?>

        <div class="ovr-adm-head">
            <div>
                <h1><?php esc_html_e( 'Subscription Plans', 'ovr-core' ); ?></h1>
                <p><?php esc_html_e( 'Manage billing tiers and feature access for your users.', 'ovr-core' ); ?></p>
            </div>
            <div class="ovr-adm-actions">
                <a href="#ovr-pm-new" class="ovr-adm-btn ovr-adm-btn--primary">
                    <span class="material-symbols-outlined">add</span><?php esc_html_e( 'Add New Plan', 'ovr-core' ); ?>
                </a>
            </div>
        </div>

        <div class="ovr-adm-card">
            <table class="ovr-adm-table ovr-plans-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Plan Name', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Price', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Duration', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Max Listings', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'ovr-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $plans as $slug => $plan ) :
                        $slug      = (string) $slug;
                        $is_active = ! empty( $plan['is_active'] );
                        $period    = (string) ( $plan['period'] ?? 'monthly' );
                        $max       = (int) ( $plan['max_listings'] ?? 1 );
                        $is_prot   = in_array( $slug, $protected, true );
                        $row_class = ! empty( $plan['is_popular'] ) ? 'is-popular' : '';
                    ?>
                        <tr class="<?php echo esc_attr( $row_class ); ?>">
                            <td>
                                <div class="ovr-adm-name">
                                    <?php echo esc_html( $plan['name'] ?? __( '(unnamed)', 'ovr-core' ) ); ?>
                                    <?php if ( ! empty( $plan['is_popular'] ) ) : ?>
                                        <span class="ovr-adm-badge ovr-adm-badge--navy"><?php esc_html_e( 'Popular', 'ovr-core' ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ( ! empty( $plan['description'] ) ) : ?>
                                    <div class="ovr-adm-sub"><?php echo esc_html( $plan['description'] ); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="ovr-adm-price"><?php echo esc_html( $currency . $fmt_price( (float) ( $plan['price'] ?? 0 ) ) ); ?><span class="ovr-adm-sub" style="display:inline"><?php echo esc_html( $price_suffix[ $period ] ?? '' ); ?></span></td>
                            <td><?php echo esc_html( $periods[ $period ] ?? ucfirst( $period ) ); ?></td>
                            <td class="ovr-adm-num"><?php echo $max < 0 ? esc_html__( 'Unlimited', 'ovr-core' ) : esc_html( number_format_i18n( $max ) ); ?></td>
                            <td>
                                <span class="ovr-adm-status ovr-adm-status--<?php echo $is_active ? 'on' : 'off'; ?>">
                                    <span class="material-symbols-outlined"><?php echo $is_active ? 'check_circle' : 'do_not_disturb_on'; ?></span><?php echo $is_active ? esc_html__( 'Active', 'ovr-core' ) : esc_html__( 'Inactive', 'ovr-core' ); ?>
                                </span>
                            </td>
                            <td>
                                <div class="ovr-adm-cell-actions">
                                    <a href="#ovr-pm-<?php echo esc_attr( $slug ); ?>" class="ovr-adm-act ovr-adm-act--edit" title="<?php esc_attr_e( 'Edit plan', 'ovr-core' ); ?>" aria-label="<?php esc_attr_e( 'Edit plan', 'ovr-core' ); ?>"><span class="material-symbols-outlined">edit</span></a>
                                    <?php if ( ! $is_prot ) : ?>
                                        <a href="<?php echo esc_url( wp_nonce_url(
                                            add_query_arg( [ 'action' => 'ovr_delete_plan', 'plan' => $slug ], $save_url ),
                                            'ovr_delete_plan_' . $slug
                                        ) ); ?>" class="ovr-adm-act ovr-adm-act--danger" title="<?php esc_attr_e( 'Delete plan', 'ovr-core' ); ?>" aria-label="<?php esc_attr_e( 'Delete plan', 'ovr-core' ); ?>"
                                           onclick="return confirm('<?php echo esc_js( __( 'Delete this plan? Subscribers on it move to Base Subscriber. This cannot be undone.', 'ovr-core' ) ); ?>');"><span class="material-symbols-outlined">delete</span></a>
                                    <?php endif; ?>
                                </div>
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
</div>
