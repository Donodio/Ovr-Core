<?php
/**
 * Online Villages ID Request.
 *
 * Senior-readable web form mirroring the Lifestyle ID request. Submission
 * stays entirely client-side: JS fills the admin-supplied AcroForm template
 * (Settings > id_form_template) or composes a built-in printable sheet.
 * No PII is stored server-side.
 *
 * @package OVR\Frontend
 */

namespace OVR\Frontend;

use OVR\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class IdRequest {

	public function init(): void {}

	/**
	 * Single source of truth for the form. `pdf_field` maps each input to
	 * the LifestyleIDForm2025.pdf AcroForm field name; correct mappings are
	 * one-line edits once the original PDF is compared against output.
	 *
	 * @return array<int, array{section:string, fields:array<int, array{name:string,label:string,type:string,required?:bool,pdf_field:string}>}>
	 */
	public static function schema(): array {
		return [
			[
				'section' => __( 'Property Owner Information', 'ovr-core' ),
				'fields'  => [
					[ 'name' => 'owner_name',  'label' => __( 'Owner Name', 'ovr-core' ),  'type' => 'text', 'required' => true, 'pdf_field' => 'OwnerName' ],
					[ 'name' => 'owner_phone', 'label' => __( 'Owner Phone', 'ovr-core' ), 'type' => 'tel',  'required' => true, 'pdf_field' => 'OwnerPhone' ],
					[ 'name' => 'owner_email', 'label' => __( 'Owner Email', 'ovr-core' ), 'type' => 'email', 'required' => false, 'pdf_field' => 'OwnerEmail' ],
					[ 'name' => 'property_address', 'label' => __( 'Rental Property Address', 'ovr-core' ), 'type' => 'text', 'required' => true, 'pdf_field' => 'PropertyAddress' ],
				],
			],
			[
				'section' => __( 'Renter / Guest Requesting ID', 'ovr-core' ),
				'fields'  => [
					[ 'name' => 'guest_name',  'label' => __( 'Full Name', 'ovr-core' ), 'type' => 'text', 'required' => true, 'pdf_field' => 'GuestName' ],
					[ 'name' => 'guest_dob',   'label' => __( 'Date of Birth', 'ovr-core' ), 'type' => 'date', 'required' => false, 'pdf_field' => 'GuestDOB' ],
					[ 'name' => 'guest_phone', 'label' => __( 'Phone', 'ovr-core' ), 'type' => 'tel', 'required' => true, 'pdf_field' => 'GuestPhone' ],
					[ 'name' => 'guest_email', 'label' => __( 'Email', 'ovr-core' ), 'type' => 'email', 'required' => false, 'pdf_field' => 'GuestEmail' ],
				],
			],
			[
				'section' => __( 'Rental Term', 'ovr-core' ),
				'fields'  => [
					[ 'name' => 'lease_start', 'label' => __( 'Lease Start Date', 'ovr-core' ), 'type' => 'date', 'required' => true, 'pdf_field' => 'LeaseStart' ],
					[ 'name' => 'lease_end',   'label' => __( 'Lease End Date', 'ovr-core' ),   'type' => 'date', 'required' => true, 'pdf_field' => 'LeaseEnd' ],
					[ 'name' => 'ids_requested', 'label' => __( 'Number of IDs Requested', 'ovr-core' ), 'type' => 'number', 'required' => true, 'pdf_field' => 'IDsRequested' ],
				],
			],
		];
	}

	/**
	 * Template URL passed to JS ('' = built-in mode).
	 */
	public static function template_url(): string {
		$s = (array) get_option( 'ovr_settings', [] );
		$url = trim( (string) ( $s['id_form_template'] ?? '' ) );
		if ( '' !== $url && ! preg_match( '/\.pdf(\?|$)/i', $url ) ) {
			return ''; // Non-PDF selection degrades to built-in mode.
		}
		return $url;
	}

	public static function render(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to request a Villages Lifestyle ID.', 'ovr-core' ) . '</p>';
		}
		return TemplateLoader::get_rendered( 'pages/id-request.php', [
			'schema'   => self::schema(),
			'template' => self::template_url(),
		] );
	}
}
