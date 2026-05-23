<?php
/**
 * Balance tab — wallet balance, topup form, transaction history.
 *
 * @package OVR
 * @var float $balance
 * @var array $transactions
 * @var bool  $topup_saved
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$settings = get_option( 'ovr_settings', [] );
$symbol   = $settings['currency_symbol'] ?? '$';
?>
<div style="display:grid;grid-template-columns:1fr;gap:20px">

    <!-- Balance card -->
    <div class="ovr-card" style="padding:32px;background:linear-gradient(135deg,var(--ovr-primary-container),var(--ovr-surface));position:relative;overflow:hidden">
        <span class="material-symbols-outlined" style="position:absolute;top:24px;right:24px;font-size:64px;color:var(--ovr-primary);opacity:0.15">account_balance_wallet</span>
        <div style="font-size:13px;text-transform:uppercase;letter-spacing:0.05em;font-weight:600;color:var(--ovr-on-surface-variant);margin-bottom:6px">
            <?php esc_html_e( 'Available Balance', 'ovr-core' ); ?>
        </div>
        <div style="font-size:42px;font-weight:700;color:var(--ovr-on-primary-container);font-variant-numeric:tabular-nums">
            <?php echo esc_html( $symbol . number_format( $balance, 2 ) ); ?>
        </div>
        <p style="margin:8px 0 0;font-size:13px;color:var(--ovr-on-surface-variant);max-width:480px">
            <?php esc_html_e( 'Use your balance for one-click subscription renewals or service charges. Top up below.', 'ovr-core' ); ?>
        </p>
    </div>

    <!-- Topup -->
    <div class="ovr-card" style="padding:24px">
        <h3 style="font-size:16px;font-weight:600;margin:0 0 6px"><?php esc_html_e( 'Add Funds', 'ovr-core' ); ?></h3>
        <p style="margin:0 0 16px;font-size:13px;color:var(--ovr-on-surface-variant)">
            <?php esc_html_e( 'Pick an amount to credit to your wallet. You\'ll be redirected to your selected payment provider.', 'ovr-core' ); ?>
        </p>

        <?php if ( $topup_saved ) : ?>
            <div class="ovr-alert ovr-alert-success" style="margin-bottom:16px">
                <span class="material-symbols-outlined">check_circle</span>
                <span><?php esc_html_e( 'Topup recorded — funds will appear once payment is confirmed.', 'ovr-core' ); ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
            <input type="hidden" name="action" value="ovr_wallet_topup">
            <?php wp_nonce_field( 'ovr_topup_action', 'ovr_topup_nonce' ); ?>

            <div style="flex:1;min-width:160px">
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                    <?php esc_html_e( 'Amount', 'ovr-core' ); ?>
                </label>
                <div style="position:relative">
                    <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--ovr-on-surface-variant);font-size:14px"><?php echo esc_html( $symbol ); ?></span>
                    <input type="number" name="amount" min="5" step="5" value="50" required class="ovr-form-input" style="padding-left:28px">
                </div>
            </div>

            <div style="flex:1;min-width:180px">
                <label style="display:block;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px">
                    <?php esc_html_e( 'Payment Method', 'ovr-core' ); ?>
                </label>
                <select name="gateway" class="ovr-form-select">
                    <option value="stripe">Stripe</option>
                    <option value="paypal">PayPal</option>
                    <option value="authorize_net">Authorize.net</option>
                </select>
            </div>

            <button type="submit" class="ovr-btn ovr-btn-primary ovr-btn-pill">
                <span class="material-symbols-outlined" style="font-size:18px">add</span>
                <?php esc_html_e( 'Add Funds', 'ovr-core' ); ?>
            </button>
        </form>
    </div>

    <!-- Transactions -->
    <div class="ovr-card" style="padding:24px">
        <h3 style="font-size:16px;font-weight:600;margin:0 0 16px"><?php esc_html_e( 'Recent Activity', 'ovr-core' ); ?></h3>

        <?php if ( empty( $transactions ) ) : ?>
            <p style="margin:0;font-size:14px;color:var(--ovr-on-surface-variant)"><?php esc_html_e( 'No transactions yet.', 'ovr-core' ); ?></p>
        <?php else : ?>
            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px">
                <?php foreach ( $transactions as $t ) :
                    $is_credit = 'credit' === $t['kind'];
                ?>
                    <li style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 14px;background:var(--ovr-surface-container-low);border-radius:var(--ovr-radius-md)">
                        <div style="min-width:0;flex:1">
                            <div style="font-size:14px;font-weight:500"><?php echo esc_html( $t['description'] ); ?></div>
                            <div style="font-size:12px;color:var(--ovr-on-surface-variant)">
                                <?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $t['created_at'] ) ); ?>
                            </div>
                        </div>
                        <div style="text-align:right;flex-shrink:0">
                            <div style="font-weight:600;font-variant-numeric:tabular-nums;color:<?php echo $is_credit ? '#00714e' : 'var(--ovr-error)'; ?>">
                                <?php echo $is_credit ? '+' : '−'; ?><?php echo esc_html( $symbol . number_format( (float) $t['amount'], 2 ) ); ?>
                            </div>
                            <div style="font-size:11px;color:var(--ovr-outline);font-variant-numeric:tabular-nums">
                                <?php echo esc_html( $symbol . number_format( (float) $t['balance_after'], 2 ) ); ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
