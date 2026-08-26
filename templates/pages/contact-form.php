<?php
/**
 * Contact OVR form.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$n = wp_create_nonce( 'ovr_contact' );
?>
<div class="ovr-wrap ovr-cform" data-ovr-contact data-nonce="<?php echo esc_attr( $n ); ?>">
	<h1><?php esc_html_e( 'Contact OVR', 'ovr-core' ); ?></h1>
	<p class="ovr-cform-lede"><?php esc_html_e( 'Questions about a listing, your account, or advertising? Send us a note.', 'ovr-core' ); ?></p>
	<form class="ovr-cform-form" novalidate>
		<p class="ovr-cform-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>
		<label><?php esc_html_e( 'Name *', 'ovr-core' ); ?><input type="text" name="name" required maxlength="100"></label>
		<label><?php esc_html_e( 'Email *', 'ovr-core' ); ?><input type="email" name="email" required maxlength="150"></label>
		<label><?php esc_html_e( 'Phone', 'ovr-core' ); ?><input type="tel" name="phone" maxlength="30"></label>
		<label><?php esc_html_e( 'Subject', 'ovr-core' ); ?><input type="text" name="subject" maxlength="150"></label>
		<label><?php esc_html_e( 'Message *', 'ovr-core' ); ?><textarea name="message" rows="7" required maxlength="5000"></textarea></label>
		<button type="submit" class="ovr-btn ovr-btn-primary"><?php esc_html_e( 'Send Message', 'ovr-core' ); ?></button>
		<p class="ovr-cform-status" role="status" aria-live="polite"></p>
	</form>
</div>
<style>
.ovr-cform{max-width:560px;margin:32px auto 56px;padding:0 16px}
.ovr-cform-hp{position:absolute;left:-9999px}
.ovr-cform-form label{display:block;font-weight:600;font-size:15px;margin-bottom:14px}
.ovr-cform-form input,.ovr-cform-form textarea{display:block;width:100%;margin-top:4px;padding:11px 12px;font-size:16px;border:1px solid #DBDBDB;border-radius:6px}
.ovr-cform-status{min-height:22px;font-size:15px}
.ovr-cform-status.is-error{color:#B3261E}.ovr-cform-status.is-ok{color:#2E7D32}
</style>
