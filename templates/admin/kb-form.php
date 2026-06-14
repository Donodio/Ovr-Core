<?php
/**
 * Knowledge Base article editor (Feature 12).
 *
 * @package OVR
 * @var array|null $article    Existing row, or null for new.
 * @var bool       $is_edit
 * @var array      $statuses   slug => label.
 * @var string[]   $categories
 * @var string     $back_url
 * @var string     $action_url
 * @var string     $nonce
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$a   = $article ?? [];
$val = static function ( string $k, $d = '' ) use ( $a ) {
    return $a[ $k ] ?? $d;
};
$cur_status = (string) $val( 'status', 'draft' );
$cur_cat    = (string) $val( 'category', 'general' );
?>
<div class="wrap ovr-sup">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}#wpcontent{padding-left:0}
        .ovr-sup{--navy:#000961;--blue:#00A2E8;--gold:#DEAF0C;--gold-dark:#b8920a;--gray-border:#DBDBDB;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--shadow-md:0 4px 12px rgba(0,9,97,.08);--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink)}
        .ovr-sup,.ovr-sup *{box-sizing:border-box}
        .ovr-sup .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:20px}
        .ovr-kbf-wrap{padding:24px 40px 48px;max-width:840px}
        .ovr-kbf-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:14px;font-weight:600;margin-bottom:14px}
        .ovr-kbf-back:hover{color:var(--blue)}
        .ovr-kbf-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);padding:30px}
        .ovr-kbf-card h1{font-size:26px;font-weight:700;margin:0 0 22px}
        .ovr-kbf-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px}
        .ovr-kbf-field{display:flex;flex-direction:column;gap:6px;margin-bottom:18px}
        .ovr-kbf-field.full{grid-column:1/-1}
        .ovr-kbf-field label{font-size:13px;font-weight:700;color:var(--ink)}
        .ovr-kbf-field input,.ovr-kbf-field select,.ovr-kbf-field textarea{width:100%;border:1px solid var(--gray-border);border-radius:var(--r-md);padding:11px 14px;font-size:15px;font-family:inherit;background:#fff}
        .ovr-kbf-field textarea{min-height:260px;resize:vertical;line-height:1.6}
        .ovr-kbf-field .hint{font-size:12px;color:var(--muted)}
        .ovr-kbf-foot{display:flex;gap:12px;margin-top:6px}
        .ovr-kbf-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 26px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:48px}
        .ovr-kbf-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold)}.ovr-kbf-btn--primary:hover{background:var(--gold-dark)}
        .ovr-kbf-btn--ghost{background:#fff;color:var(--navy);border-color:var(--gray-border)}.ovr-kbf-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        @media(max-width:782px){.ovr-kbf-wrap{padding:18px 14px 32px}.ovr-kbf-grid{grid-template-columns:1fr}.ovr-kbf-foot{flex-direction:column}.ovr-kbf-btn{width:100%}}
    </style>
    <div class="ovr-kbf-wrap">
        <a class="ovr-kbf-back" href="<?php echo esc_url( $back_url ); ?>"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'Back to Knowledge Base', 'ovr-core' ); ?></a>
        <form method="post" action="<?php echo esc_url( $action_url ); ?>">
            <input type="hidden" name="action" value="ovr_kb_save">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
            <?php if ( $is_edit ) : ?><input type="hidden" name="article_id" value="<?php echo esc_attr( (int) $a['id'] ); ?>"><?php endif; ?>
            <div class="ovr-kbf-card">
                <h1><?php echo $is_edit ? esc_html__( 'Edit Article', 'ovr-core' ) : esc_html__( 'Create Article', 'ovr-core' ); ?></h1>
                <div class="ovr-kbf-field full">
                    <label for="kb-title"><?php esc_html_e( 'Title', 'ovr-core' ); ?></label>
                    <input type="text" id="kb-title" name="title" required value="<?php echo esc_attr( $val( 'title' ) ); ?>">
                </div>
                <div class="ovr-kbf-grid">
                    <div class="ovr-kbf-field">
                        <label for="kb-cat"><?php esc_html_e( 'Category', 'ovr-core' ); ?></label>
                        <select id="kb-cat" name="category">
                            <?php foreach ( $categories as $c ) : ?>
                                <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $cur_cat, $c ); ?>><?php echo esc_html( ucwords( str_replace( '-', ' ', $c ) ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ovr-kbf-field">
                        <label for="kb-status"><?php esc_html_e( 'Status', 'ovr-core' ); ?></label>
                        <select id="kb-status" name="status">
                            <?php foreach ( $statuses as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $cur_status, $slug ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ovr-kbf-field">
                        <label for="kb-sort"><?php esc_html_e( 'Sort Order', 'ovr-core' ); ?></label>
                        <input type="number" id="kb-sort" name="sort_order" step="1" value="<?php echo esc_attr( $val( 'sort_order', '0' ) ); ?>">
                    </div>
                </div>
                <div class="ovr-kbf-field full">
                    <label for="kb-body"><?php esc_html_e( 'Body', 'ovr-core' ); ?></label>
                    <textarea id="kb-body" name="body"><?php echo esc_textarea( (string) $val( 'body' ) ); ?></textarea>
                    <span class="hint"><?php esc_html_e( 'Basic HTML is allowed (headings, lists, links, emphasis).', 'ovr-core' ); ?></span>
                </div>
                <div class="ovr-kbf-foot">
                    <button type="submit" class="ovr-kbf-btn ovr-kbf-btn--primary"><span class="material-symbols-outlined">save</span><?php echo $is_edit ? esc_html__( 'Update Article', 'ovr-core' ) : esc_html__( 'Create Article', 'ovr-core' ); ?></button>
                    <a href="<?php echo esc_url( $back_url ); ?>" class="ovr-kbf-btn ovr-kbf-btn--ghost"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></a>
                </div>
            </div>
        </form>
    </div>
</div>
