<?php
/**
 * Contact OVR form: renders the form and handles its AJAX submission.
 *
 * Anti-spam (new behavior, none existed previously): nonce + honeypot +
 * per-IP transient throttle (max 5 submissions/hour). Delivery goes through
 * the existing Mailer 'contact_form' admin template, whose recipient
 * resolution already prefers Settings > support_email.
 *
 * @package OVR\Frontend
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;
use OVR\Email\Mailer;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class ContactForm {

	public function init(): void {}

	public static function render(): string {
		return TemplateLoader::get_rendered( 'pages/contact-form.php', [] );
	}

	/**
	 * AJAX: wp_ajax_ovr_contact / nopriv.
	 */
	public static function ajax_submit(): void {
		// $die = false so failures return distinct JSON errors (spec §4.5).
		if ( ! check_ajax_referer( 'ovr_contact', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please reload the page and try again.', 'ovr-core' ) ], 403 );
		}

		// Honeypot — real users never see/fill this field. Fake success so
		// bots learn nothing (mirrors the inquiry-handler pattern).
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( [ 'message' => __( 'Thanks! Your message has been sent.', 'ovr-core' ) ] );
		}

		// Per-IP throttle: 5 per rolling hour.
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key  = 'ovr_contact_rl_' . md5( $ip );
		$hits = (int) get_transient( $key );
		if ( $hits >= 5 ) {
			wp_send_json_error( [ 'message' => __( 'Too many messages sent. Please try again later.', 'ovr-core' ) ], 429 );
		}
		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

		$name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone   = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

		if ( '' === $name || ! is_email( $email ) || '' === $message ) {
			wp_send_json_error( [ 'message' => __( 'Please provide your name, a valid email, and a message.', 'ovr-core' ) ], 400 );
		}

		// Phone/Subject fold into the body (template defines only three vars).
		$body = '';
		if ( '' !== $subject ) {
			$body .= 'Subject: ' . $subject . "\n";
		}
		if ( '' !== $phone ) {
			$body .= 'Phone: ' . $phone . "\n";
		}
		$body .= "\n" . $message;

		$sent = Mailer::send( 'contact_form', [
			'sender_name'     => $name,
			'sender_email'    => $email,
			'contact_message' => $body,
		], [] );

		if ( ! $sent ) {
			wp_send_json_error( [ 'message' => __( 'Your message could not be sent. Please email us directly.', 'ovr-core' ) ], 500 );
		}
		wp_send_json_success( [ 'message' => __( 'Thanks! Your message has been sent.', 'ovr-core' ) ] );
	}
}
