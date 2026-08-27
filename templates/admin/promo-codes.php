<?php
/**
 * Promo Codes Admin Template.
 *
 * @var array[] $rows
 * @var array   $plan_options  slug => name
 * @var array|null $editing
 * @var array|null $notice
 * @var string $page_url
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Promo Codes', 'ovr-core' ); ?> <span style="font-size:13px;color:#646970"><?php esc_html_e( '— attached to subscription plans', 'ovr-core' ); ?></span></h1>
    <?php if ( $notice ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?>"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start">
        <div>
            <table class="widefat striped">
                <thead><tr>
                    <th><?php esc_html_e( 'Code', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Discount', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Plans', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Uses', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Valid', 'ovr-core' ); ?></th>
                    <th><?php esc_html_e( 'Active', 'ovr-core' ); ?></th>
                    <th></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No promo codes yet.', 'ovr-core' ); ?></td></tr>
                <?php else : foreach ( $rows as $r ) : ?>
                    <?php
                    $plans_raw = $r['applicable_plans'] ?? '';
                    $plans_list = '';
                    if ( '' !== trim( (string) $plans_raw ) ) {
                        $decoded = json_decode( (string) $plans_raw, true );
                        if ( is_array( $decoded ) ) { $plans_list = implode( ', ', array_map( 'esc_html', $decoded ) ); }
                        else { $plans_list = esc_html( (string) $plans_raw ); }
                    } else {
                        $plans_list = esc_html__( 'All plans', 'ovr-core' );
                    }
                    $disc = 'percentage' === $r['discount_type'] ? $r['discount_value'] . '%' : '$' . number_format( (float) $r['discount_value'], 2 );
                    $uses = esc_html( $r['current_uses'] . ( null !== $r['max_uses'] ? ' / ' . $r['max_uses'] : '' ) );
                    $valid = trim( ( $r['valid_from'] ?? '' ) . ' ' . ( $r['valid_until'] ?? '' ) ) ?: '—';
                    ?>
                    <tr>
                        <td><code><?php echo esc_html( $r['code'] ); ?></code></td>
                        <td><?php echo esc_html( $disc ); ?></td>
                        <td style="font-size:12px"><?php echo $plans_list; ?></td>
                        <td><?php echo $uses; ?></td>
                        <td style="font-size:12px"><?php echo esc_html( $valid ); ?></td>
                        <td><?php echo $r['is_active'] ? esc_html__( 'Yes', 'ovr-core' ) : esc_html__( 'No', 'ovr-core' ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( add_query_arg( 'edit', $r['id'], $page_url ) ); ?>"><?php esc_html_e( 'Edit', 'ovr-core' ); ?></a> |
                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'ovr_delete_promo', 'promo' => $r['id'] ], admin_url( 'admin-post.php' ) ), 'ovr_delete_promo_' . $r['id'] ) ); ?>" onclick="return confirm('Delete this promo code?')"><?php esc_html_e( 'Delete', 'ovr-core' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div style="background:#fff;border:1px solid #ccd0d4;padding:20px">
            <h2 style="margin-top:0"><?php echo $editing ? esc_html__( 'Edit Promo Code', 'ovr-core' ) : esc_html__( 'Add Promo Code', 'ovr-core' ); ?></h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'ovr_save_promo_action', 'ovr_promo_nonce' ); ?>
                <input type="hidden" name="action" value="ovr_save_promo">
                <?php if ( $editing ) : ?><input type="hidden" name="promo_id" value="<?php echo esc_attr( $editing['id'] ); ?>"><?php endif; ?>

                <p><label><strong><?php esc_html_e( 'Code', 'ovr-core' ); ?></strong><br>
                    <input type="text" name="code" value="<?php echo esc_attr( $editing['code'] ?? '' ); ?>" style="width:100%;text-transform:uppercase" required placeholder="SUMMER20"></label></p>

                <p style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <label><strong><?php esc_html_e( 'Type', 'ovr-core' ); ?></strong><br>
                        <select name="discount_type" style="width:100%">
                            <option value="percentage" <?php selected( $editing['discount_type'] ?? 'percentage', 'percentage' ); ?>><?php esc_html_e( 'Percentage %', 'ovr-core' ); ?></option>
                            <option value="fixed" <?php selected( $editing['discount_type'] ?? '', 'fixed' ); ?>><?php esc_html_e( 'Fixed $', 'ovr-core' ); ?></option>
                        </select></label>
                    <label><strong><?php esc_html_e( 'Value', 'ovr-core' ); ?></strong><br>
                        <input type="number" step="0.01" min="0" name="discount_value" value="<?php echo esc_attr( $editing['discount_value'] ?? '' ); ?>" style="width:100%" required></label>
                </p>

                <p><label><strong><?php esc_html_e( 'Max Uses (blank = unlimited)', 'ovr-core' ); ?></strong><br>
                    <input type="number" min="1" name="max_uses" value="<?php echo esc_attr( $editing['max_uses'] ?? '' ); ?>" style="width:100%"></label></p>

                <p style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <label><strong><?php esc_html_e( 'Valid From', 'ovr-core' ); ?></strong><br>
                        <input type="date" name="valid_from" value="<?php echo esc_attr( $editing['valid_from'] ?? '' ); ?>" style="width:100%"></label>
                    <label><strong><?php esc_html_e( 'Valid Until', 'ovr-core' ); ?></strong><br>
                        <input type="date" name="valid_until" value="<?php echo esc_attr( $editing['valid_until'] ?? '' ); ?>" style="width:100%"></label>
                </p>

                <p><label><strong><?php esc_html_e( 'Applicable Plans (leave empty = all)', 'ovr-core' ); ?></strong><br>
                    <select name="applicable_plans[]" multiple style="width:100%;min-height:110px">
                        <?php
                        $sel_plans = [];
                        if ( $editing && ! empty( $editing['applicable_plans'] ) ) {
                            $decoded = json_decode( (string) $editing['applicable_plans'], true );
                            if ( is_array( $decoded ) ) { $sel_plans = array_map( 'sanitize_key', $decoded ); }
                        }
                        foreach ( $plan_options as $slug => $name ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( in_array( $slug, $sel_plans, true ) ); ?>><?php echo esc_html( $name . ' (' . $slug . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select><br><span style="font-size:12px;color:#646970"><?php esc_html_e( 'Hold Ctrl/Cmd to select multiple.', 'ovr-core' ); ?></span></label></p>

                <p><label><input type="checkbox" name="is_active" value="1" <?php checked( $editing ? (int) $editing['is_active'] : 1, 1 ); ?>> <?php esc_html_e( 'Active', 'ovr-core' ); ?></label></p>

                <p>
                    <button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update Promo Code', 'ovr-core' ) : esc_html__( 'Add Promo Code', 'ovr-core' ); ?></button>
                    <?php if ( $editing ) : ?> <a href="<?php echo esc_url( $page_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'ovr-core' ); ?></a><?php endif; ?>
                </p>
            </form>
        </div>
    </div>
</div>
