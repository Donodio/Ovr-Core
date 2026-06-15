<?php
/**
 * Calendar Sync dashboard (Feature 2).
 *
 * @package OVR
 * @var array  $channels    [ key => [ label, latest(row|null) ] ].
 * @var array  $recent      Recent SyncLog rows (all channels).
 * @var bool   $wp_enabled
 * @var string $wp_schedule
 * @var string $wp_url
 * @var string $page_url
 * @var string $settings_url
 * @var string $wp_sync_url  Manual WordPress-sync action URL.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$status_color = static function ( string $s ): string {
    return 'success' === $s ? '#2E7D32' : ( 'partial' === $s ? '#b8920a' : '#B3261E' );
};
?>
<div class="wrap ovr-bk">
    <style>
        #wpcontent,#wpbody-content{background:#f0f3f7}#wpcontent{padding-left:0}
        .ovr-bk{--navy:#000961;--blue:#00A2E8;--blue-light:#e5f5fe;--green:#2E7D32;--green-light:#e4f4e4;--gold:#DEAF0C;--gold-dark:#b8920a;--red:#B3261E;--gray-border:#DBDBDB;--gray-light:#f2f4f7;--gray-mid:#8b95a5;--ink:#1C2430;--muted:#5F6B7A;--surf:#fff;--shadow-md:0 4px 12px rgba(0,9,97,.08);--r-md:8px;--r-lg:12px;font-family:'Inter',system-ui,sans-serif;width:100%;max-width:none;margin:0;color:var(--ink)}
        .ovr-bk,.ovr-bk *{box-sizing:border-box}
        .ovr-bk .material-symbols-outlined{line-height:1;vertical-align:middle;font-size:20px}
        .ovr-syn-wrap{padding:24px 40px 48px}
        .ovr-syn-back{display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-size:14px;font-weight:600;margin-bottom:14px}
        .ovr-syn-back:hover{color:var(--blue)}
        .ovr-syn-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:20px}
        .ovr-syn-head h1{font-size:30px;font-weight:700;margin:0;line-height:1.2}
        .ovr-syn-head p{margin:6px 0 0;font-size:16px;color:var(--muted)}
        .ovr-syn-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:0 22px;border-radius:var(--r-md);font-size:15px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;min-height:46px}
        .ovr-syn-btn--primary{background:var(--gold);color:var(--navy);border-color:var(--gold)}.ovr-syn-btn--primary:hover{background:var(--gold-dark)}
        .ovr-syn-btn--ghost{background:var(--surf);color:var(--navy);border-color:var(--gray-border)}.ovr-syn-btn--ghost:hover{border-color:var(--blue);color:var(--blue)}
        .ovr-syn-cards{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:24px}
        .ovr-syn-card{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);padding:22px}
        .ovr-syn-card h2{font-size:16px;font-weight:700;margin:0 0 12px;display:flex;align-items:center;gap:8px}
        .ovr-syn-dot{width:10px;height:10px;border-radius:50%;display:inline-block}
        .ovr-syn-row{display:flex;justify-content:space-between;gap:12px;padding:7px 0;font-size:14px;border-top:1px solid var(--gray-light)}
        .ovr-syn-row:first-of-type{border-top:none}
        .ovr-syn-row .k{color:var(--muted)}
        .ovr-syn-row .v{font-weight:600;text-align:right;word-break:break-word}
        .ovr-syn-tablecard{background:var(--surf);border:1px solid var(--gray-border);border-radius:var(--r-lg);box-shadow:var(--shadow-md);overflow:hidden}
        .ovr-syn-tablecard h2{font-size:16px;font-weight:700;margin:0;padding:18px 22px;border-bottom:1px solid var(--gray-border)}
        .ovr-syn-table{width:100%;border-collapse:collapse}
        .ovr-syn-table th{text-align:left;padding:11px 22px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);background:#f8f9fb;border-bottom:2px solid var(--gray-border)}
        .ovr-syn-table td{padding:12px 22px;font-size:14px;border-bottom:1px solid var(--gray-border);vertical-align:top}
        .ovr-syn-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:600;text-transform:capitalize;color:#fff}
        .ovr-syn-empty{padding:50px 24px;text-align:center;color:var(--muted)}
        @media(max-width:782px){.ovr-syn-wrap{padding:18px 14px 32px}.ovr-syn-cards{grid-template-columns:1fr}}
    </style>

    <div class="ovr-syn-wrap">
        <a class="ovr-syn-back" href="<?php echo esc_url( $page_url ); ?>"><span class="material-symbols-outlined">arrow_back</span><?php esc_html_e( 'Back to Bookings', 'ovr-core' ); ?></a>
        <div class="ovr-syn-head">
            <div>
                <h1><?php esc_html_e( 'Calendar Sync', 'ovr-core' ); ?></h1>
                <p><?php esc_html_e( 'Import status for VRBO / Airbnb (iCal) and WordPress reservations.', 'ovr-core' ); ?></p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <a href="<?php echo esc_url( $settings_url ); ?>" class="ovr-syn-btn ovr-syn-btn--ghost"><span class="material-symbols-outlined">settings</span><?php esc_html_e( 'Sync Settings', 'ovr-core' ); ?></a>
                <a href="<?php echo esc_url( $wp_sync_url ); ?>" class="ovr-syn-btn ovr-syn-btn--primary" onclick="return confirm('<?php echo esc_js( __( 'Run a WordPress import now?', 'ovr-core' ) ); ?>');"><span class="material-symbols-outlined">sync</span><?php esc_html_e( 'Sync Now', 'ovr-core' ); ?></a>
            </div>
        </div>

        <div class="ovr-syn-cards">
            <?php foreach ( $channels as $key => $ch ) :
                $latest = $ch['latest'];
                $status = $latest ? (string) $latest['status'] : '';
                $color  = $latest ? $status_color( $status ) : '#c9ced6';
            ?>
                <div class="ovr-syn-card">
                    <h2><span class="ovr-syn-dot" style="background:<?php echo esc_attr( $color ); ?>"></span><?php echo esc_html( $ch['label'] ); ?></h2>
                    <?php if ( $latest ) : ?>
                        <div class="ovr-syn-row"><span class="k"><?php esc_html_e( 'Status', 'ovr-core' ); ?></span><span class="v" style="color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></div>
                        <div class="ovr-syn-row"><span class="k"><?php esc_html_e( 'Last Sync', 'ovr-core' ); ?></span><span class="v"><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( (string) $latest['created_at'] ) ) ); ?></span></div>
                        <div class="ovr-syn-row"><span class="k"><?php esc_html_e( 'Imported', 'ovr-core' ); ?></span><span class="v"><?php echo esc_html( number_format_i18n( (int) $latest['imported'] ) ); ?></span></div>
                        <?php if ( ! empty( $latest['source_url'] ) ) : ?>
                            <div class="ovr-syn-row"><span class="k"><?php esc_html_e( 'Source', 'ovr-core' ); ?></span><span class="v" style="max-width:60%"><?php echo esc_html( $latest['source_url'] ); ?></span></div>
                        <?php endif; ?>
                        <div class="ovr-syn-row"><span class="k"><?php esc_html_e( 'Message', 'ovr-core' ); ?></span><span class="v" style="max-width:60%;font-weight:400"><?php echo esc_html( (string) $latest['message'] ); ?></span></div>
                    <?php else : ?>
                        <p style="color:var(--muted);font-size:14px;margin:4px 0 0"><?php esc_html_e( 'No sync has run yet on this channel.', 'ovr-core' ); ?></p>
                    <?php endif; ?>
                    <?php if ( 'wordpress' === $key ) : ?>
                        <div class="ovr-syn-row"><span class="k"><?php esc_html_e( 'Schedule', 'ovr-core' ); ?></span><span class="v"><?php echo $wp_enabled ? esc_html( ucfirst( $wp_schedule ) ) : esc_html__( 'Disabled', 'ovr-core' ); ?></span></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ovr-syn-tablecard">
            <h2><?php esc_html_e( 'Recent Sync Activity', 'ovr-core' ); ?></h2>
            <?php if ( empty( $recent ) ) : ?>
                <div class="ovr-syn-empty"><?php esc_html_e( 'No sync runs recorded yet.', 'ovr-core' ); ?></div>
            <?php else : ?>
                <table class="ovr-syn-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'When', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Channel', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Imported', 'ovr-core' ); ?></th>
                            <th><?php esc_html_e( 'Detail', 'ovr-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent as $row ) : ?>
                            <tr>
                                <td style="white-space:nowrap"><?php echo esc_html( date_i18n( 'M j, g:i a', strtotime( (string) $row['created_at'] ) ) ); ?></td>
                                <td style="text-transform:capitalize"><?php echo esc_html( (string) $row['channel'] ); ?></td>
                                <td><span class="ovr-syn-badge" style="background:<?php echo esc_attr( $status_color( (string) $row['status'] ) ); ?>"><?php echo esc_html( ucfirst( (string) $row['status'] ) ); ?></span></td>
                                <td><?php echo esc_html( number_format_i18n( (int) $row['imported'] ) ); ?></td>
                                <td style="color:var(--muted)"><?php echo esc_html( (string) $row['message'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
