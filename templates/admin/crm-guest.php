<?php
/**
 * CRM — Guest profile (Feature 5).
 *
 * @package OVR
 * @var array $guest     Guest row.
 * @var array $stays     Booking history rows.
 * @var array $inquiries Inquiry history rows.
 * @var string $back_url List URL.
 * @var string $edit_url Edit-guest URL.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$symbol = '$';
$ltv    = (float) $guest['total_spend'];
$stays_n = (int) $guest['total_stays'];
$avg    = $stays_n > 0 ? $ltv / $stays_n : 0;
$tags   = array_filter( array_map( 'trim', explode( ',', (string) $guest['tags'] ) ) );
?>
<div class="wrap ovr-crm">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}#wpcontent{padding-left:0}
        .ovr-crm{--navy:#000961;--blue:#00A2E8;--blue-light:#e5f5fe;--navy-light:#e8eaf3;--gold:#DEAF0C;--gold-dark:#b8920a;--green:#2E7D32;--green-light:#e4f4e4;--purple:#6A3FB8;--purple-light:#eee6fb;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--r-sm:6px;--r-md:8px;--r-lg:12px;--shadow-md:0 4px 12px rgba(0,9,97,.08);font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;color:var(--ink)}
        .ovr-crm,.ovr-crm *{box-sizing:border-box}
        .ovr-crm .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:20px}
        .ovr-crm-wrap{padding:24px 40px 48px;max-width:1000px}
        .ovr-crm-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:14px;font-weight:600;margin-bottom:14px}
        .ovr-crm-back:hover{color:var(--blue)}
        .ovr-gp-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);padding:24px;margin-bottom:18px}
        .ovr-gp-id{display:flex;align-items:center;gap:16px}
        .ovr-gp-avatar{width:60px;height:60px;border-radius:50%;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;flex-shrink:0}
        .ovr-gp-name{font-size:24px;font-weight:700;margin:0}
        .ovr-gp-meta{font-size:14px;color:var(--muted);margin-top:4px;display:flex;gap:14px;flex-wrap:wrap}
        .ovr-gp-meta span{display:inline-flex;align-items:center;gap:5px}
        .ovr-gp-tags{margin-top:8px;display:flex;gap:6px;flex-wrap:wrap}
        .ovr-gp-tag{background:var(--blue-light);color:var(--blue);font-size:12px;font-weight:600;padding:3px 10px;border-radius:9999px}
        .ovr-crm-btn{display:inline-flex;align-items:center;gap:8px;padding:0 20px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid var(--gray-border);background:var(--surf);color:var(--navy);min-height:44px}
        .ovr-crm-btn:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-gp-ltv{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
        .ovr-gp-ltv-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);padding:18px;box-shadow:var(--shadow-md)}
        .ovr-gp-ltv-v{font-size:26px;font-weight:700;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
        .ovr-gp-ltv-l{font-size:13px;color:var(--muted);margin-top:3px}
        .ovr-gp-cols{display:grid;grid-template-columns:1fr 1fr;gap:18px}
        .ovr-gp-panel{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);overflow:hidden}
        .ovr-gp-panel h2{font-size:15px;font-weight:700;margin:0;padding:16px 20px;border-bottom:1px solid var(--gray-border);background:#f8f9fb;display:flex;align-items:center;gap:8px}
        .ovr-gp-panel h2 .material-symbols-outlined{font-size:19px;color:var(--navy)}
        .ovr-gp-list{margin:0;padding:0;list-style:none}
        .ovr-gp-row{padding:13px 20px;border-bottom:1px solid var(--gray-light);font-size:14px;display:flex;justify-content:space-between;gap:10px}
        .ovr-gp-row:last-child{border-bottom:none}
        .ovr-gp-row strong{font-weight:600}
        .ovr-gp-row .sub{color:var(--muted);font-size:13px}
        .ovr-gp-amt{font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}
        .ovr-gp-empty{padding:26px 20px;color:var(--muted);font-size:14px;text-align:center}
        .ovr-gp-notes{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);padding:20px;margin-top:18px}
        .ovr-gp-notes h2{font-size:15px;font-weight:700;margin:0 0 10px}
        .ovr-gp-notes p{margin:0;color:var(--ink);font-size:15px;line-height:1.6;white-space:pre-wrap}
        @media(max-width:782px){.ovr-crm-wrap{padding:18px 14px 32px}.ovr-gp-ltv{grid-template-columns:1fr 1fr}.ovr-gp-cols{grid-template-columns:1fr}}
    </style>

    <div class="ovr-crm-wrap">
        <a href="<?php echo esc_url( $back_url ); ?>" class="ovr-crm-back"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'Back to manifest', 'ovr-core' ); ?></a>

        <div class="ovr-gp-head">
            <div class="ovr-gp-id">
                <div class="ovr-gp-avatar"><?php echo esc_html( strtoupper( substr( (string) $guest['name'], 0, 1 ) ?: '?' ) ); ?></div>
                <div>
                    <h1 class="ovr-gp-name"><?php echo esc_html( $guest['name'] ?: __( 'Unnamed guest', 'ovr-core' ) ); ?></h1>
                    <div class="ovr-gp-meta">
                        <?php if ( $guest['email'] ) : ?><span><span class="material-symbols-outlined" style="font-size:16px">mail</span><?php echo esc_html( $guest['email'] ); ?></span><?php endif; ?>
                        <?php if ( $guest['phone'] ) : ?><span><span class="material-symbols-outlined" style="font-size:16px">call</span><?php echo esc_html( $guest['phone'] ); ?></span><?php endif; ?>
                        <?php if ( $guest['address'] ) : ?><span><span class="material-symbols-outlined" style="font-size:16px">place</span><?php echo esc_html( $guest['address'] ); ?></span><?php endif; ?>
                    </div>
                    <?php if ( $tags ) : ?>
                        <div class="ovr-gp-tags"><?php foreach ( $tags as $tag ) : ?><span class="ovr-gp-tag"><?php echo esc_html( $tag ); ?></span><?php endforeach; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="ovr-crm-btn"><span class="material-symbols-outlined">edit</span><?php esc_html_e( 'Edit Guest', 'ovr-core' ); ?></a>
        </div>

        <div class="ovr-gp-ltv">
            <div class="ovr-gp-ltv-card"><div class="ovr-gp-ltv-v"><?php echo esc_html( $symbol . number_format_i18n( $ltv, 2 ) ); ?></div><div class="ovr-gp-ltv-l"><?php esc_html_e( 'Lifetime Value', 'ovr-core' ); ?></div></div>
            <div class="ovr-gp-ltv-card"><div class="ovr-gp-ltv-v"><?php echo esc_html( number_format_i18n( $stays_n ) ); ?></div><div class="ovr-gp-ltv-l"><?php esc_html_e( 'Total Stays', 'ovr-core' ); ?></div></div>
            <div class="ovr-gp-ltv-card"><div class="ovr-gp-ltv-v"><?php echo esc_html( $symbol . number_format_i18n( $avg, 0 ) ); ?></div><div class="ovr-gp-ltv-l"><?php esc_html_e( 'Avg / Stay', 'ovr-core' ); ?></div></div>
            <div class="ovr-gp-ltv-card"><div class="ovr-gp-ltv-v" style="font-size:18px"><?php echo $guest['last_stay'] ? esc_html( date_i18n( 'M j, Y', strtotime( $guest['last_stay'] ) ) ) : '—'; ?></div><div class="ovr-gp-ltv-l"><?php esc_html_e( 'Last Stay', 'ovr-core' ); ?></div></div>
        </div>

        <div class="ovr-gp-cols">
            <div class="ovr-gp-panel">
                <h2><span class="material-symbols-outlined">event_available</span><?php esc_html_e( 'Stay History', 'ovr-core' ); ?></h2>
                <?php if ( empty( $stays ) ) : ?>
                    <div class="ovr-gp-empty"><?php esc_html_e( 'No bookings yet.', 'ovr-core' ); ?></div>
                <?php else : ?>
                    <ul class="ovr-gp-list">
                        <?php foreach ( $stays as $s ) : ?>
                            <li class="ovr-gp-row">
                                <span>
                                    <strong><?php echo esc_html( get_the_title( (int) $s['property_id'] ) ?: __( '(listing removed)', 'ovr-core' ) ); ?></strong><br>
                                    <span class="sub">
                                        <?php echo $s['checkin_date'] ? esc_html( date_i18n( 'M j', strtotime( $s['checkin_date'] ) ) ) : '—'; ?>
                                        – <?php echo $s['checkout_date'] ? esc_html( date_i18n( 'M j, Y', strtotime( $s['checkout_date'] ) ) ) : '—'; ?>
                                        · <?php echo esc_html( ucfirst( str_replace( '_', ' ', $s['status'] ) ) ); ?>
                                    </span>
                                </span>
                                <span class="ovr-gp-amt"><?php echo esc_html( $symbol . number_format_i18n( (float) $s['amount'], 0 ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="ovr-gp-panel">
                <h2><span class="material-symbols-outlined">forum</span><?php esc_html_e( 'Inquiry History', 'ovr-core' ); ?></h2>
                <?php if ( empty( $inquiries ) ) : ?>
                    <div class="ovr-gp-empty"><?php esc_html_e( 'No inquiries on record.', 'ovr-core' ); ?></div>
                <?php else : ?>
                    <ul class="ovr-gp-list">
                        <?php foreach ( $inquiries as $q ) : ?>
                            <li class="ovr-gp-row">
                                <span>
                                    <strong><?php echo esc_html( get_the_title( (int) $q['property_id'] ) ?: __( '(listing removed)', 'ovr-core' ) ); ?></strong><br>
                                    <span class="sub"><?php echo esc_html( wp_trim_words( (string) $q['message'], 12 ) ); ?></span>
                                </span>
                                <span class="sub" style="white-space:nowrap"><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $q['created_at'] ) ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( trim( (string) $guest['notes'] ) !== '' ) : ?>
            <div class="ovr-gp-notes">
                <h2><?php esc_html_e( 'Notes', 'ovr-core' ); ?></h2>
                <p><?php echo esc_html( $guest['notes'] ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
