<?php
/**
 * Online Villages ID Request.
 *
 * Variables: $schema (sections/fields), $template (PDF template URL, '' = built-in).
 * Submission is handled entirely client-side (assets/js/ovr-id-request.js).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ovr-wrap ovr-idreq">
	<h1><?php esc_html_e( 'Online Villages ID Request', 'ovr-core' ); ?></h1>
	<p class="ovr-idreq-lede"><?php esc_html_e( 'Fill in the details below and we will prepare your Villages Lifestyle ID request as a PDF. Download or print it and bring it to a Villages ID center with a photo ID.', 'ovr-core' ); ?></p>
	<form class="ovr-idreq-form" novalidate>
		<?php foreach ( $schema as $section ) : ?>
			<fieldset class="ovr-idreq-section">
				<legend><?php echo esc_html( $section['section'] ); ?></legend>
				<?php foreach ( $section['fields'] as $field ) : ?>
					<label class="ovr-idreq-field">
						<span class="ovr-idreq-label"><?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? ' <span class="ovr-idreq-req" aria-hidden="true">*</span>' : ''; ?></span>
						<input type="<?php echo esc_attr( $field['type'] ); ?>"
							name="<?php echo esc_attr( $field['name'] ); ?>"
							<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
							data-pdf-field="<?php echo esc_attr( $field['pdf_field'] ); ?>"
							data-label="<?php echo esc_attr( $field['label'] ); ?>">
					</label>
				<?php endforeach; ?>
			</fieldset>
		<?php endforeach; ?>
		<div class="ovr-idreq-actions">
			<button type="submit" class="ovr-btn ovr-btn-primary"><?php esc_html_e( 'Download PDF', 'ovr-core' ); ?></button>
			<button type="button" class="ovr-btn ovr-btn-secondary" data-ovr-print><?php esc_html_e( 'Print', 'ovr-core' ); ?></button>
		</div>
		<p class="ovr-idreq-status" role="status" aria-live="polite"></p>
	</form>
	<script>window.OVR_ID_FORM={templateUrl:<?php echo '' !== $template ? wp_json_encode( esc_url_raw( $template ) ) : "''"; ?>};</script>
</div>
<style>
.ovr-idreq{max-width:560px;margin:32px auto 56px;padding:0 16px}
.ovr-idreq h1{margin-bottom:8px}
.ovr-idreq-lede{font-size:16px;color:#5F6B7A;margin-bottom:24px}
.ovr-idreq-form fieldset{border:1px solid #DBDBDB;border-radius:6px;padding:18px 16px;margin:0 0 20px}
.ovr-idreq-form legend{font-weight:700;font-size:17px;padding:0 6px}
.ovr-idreq-form label{display:block;font-weight:600;font-size:16px;margin-bottom:14px}
.ovr-idreq-req{color:#B3261E}
.ovr-idreq-form input{display:block;width:100%;margin-top:4px;padding:11px 12px;font-size:16px;border:1px solid #DBDBDB;border-radius:6px}
.ovr-idreq-form input.is-invalid{border-color:#B3261E;box-shadow:0 0 0 2px rgba(179,38,30,.15)}
.ovr-idreq-err{display:block;margin-top:4px;font-size:14px;font-weight:400;color:#B3261E}
.ovr-idreq-actions{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.ovr-idreq-status{min-height:22px;font-size:16px}
.ovr-idreq-status.is-error{color:#B3261E}.ovr-idreq-status.is-ok{color:#2E7D32}
</style>
