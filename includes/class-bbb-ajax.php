<?php
/**
 * Handles saving a completed build request to the database, once the
 * customer clicks "Submit Build Request" on the public builder page.
 *
 * This runs through WordPress's admin-ajax.php system, which lets
 * JavaScript on the page send data to PHP in the background, without
 * ever reloading the page. It works for both logged-in and logged-out
 * visitors, since customers browsing the shop are not logged in.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BBB_Ajax {

	/**
	 * This function "switches on" the AJAX handler.
	 * It is called once, from the main plugin file.
	 */
	public static function init() {

		// 'wp_ajax_bbb_submit_build' handles the request for logged-in users
		// (e.g. Administrators testing the form), while 'wp_ajax_nopriv_...'
		// handles it for regular site visitors, who are not logged in at all.
		// Both need to point to the same function, since either kind of
		// visitor should be able to submit a build request.
		add_action( 'wp_ajax_bbb_submit_build', array( __CLASS__, 'handle_submit_build' ) );
		add_action( 'wp_ajax_nopriv_bbb_submit_build', array( __CLASS__, 'handle_submit_build' ) );
	}

	/**
	 * Validates and saves one completed build request.
	 *
	 * This always ends by calling wp_send_json_success() or
	 * wp_send_json_error(), which both stop PHP execution and send a
	 * JSON response back to the JavaScript that made the request.
	 */
	public static function handle_submit_build() {

		// check_ajax_referer() confirms the request included the correct
		// security token (nonce) that we generated for this specific page.
		// This blocks malicious requests coming from outside our own site.
		check_ajax_referer( 'bbb_submit_build', 'nonce' );

		$template_id       = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$customer_name     = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
		$customer_email    = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
		$customer_whatsapp = isset( $_POST['customer_whatsapp'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_whatsapp'] ) ) : '';
		$options_raw       = isset( $_POST['options'] ) ? wp_unslash( $_POST['options'] ) : '';

		// Never trust data sent from the browser. Even though our
		// JavaScript already checks these fields, someone could bypass
		// JavaScript entirely, so we check everything again here too.
		if ( empty( $template_id ) || empty( $customer_name ) || empty( $customer_whatsapp ) ) {
			wp_send_json_error( array( 'message' => 'Please fill in all required fields.' ) );
		}

		if ( empty( $customer_email ) || ! is_email( $customer_email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ) );
		}

		$options = json_decode( $options_raw, true );

		if ( empty( $options ) || ! is_array( $options ) ) {
			wp_send_json_error( array( 'message' => 'Please complete every build step before submitting.' ) );
		}

		global $wpdb;

		$submissions_table       = $wpdb->prefix . 'bbb_submissions';
		$submission_items_table  = $wpdb->prefix . 'bbb_submission_items';
		$groups_table            = $wpdb->prefix . 'bbb_option_groups';
		$options_table           = $wpdb->prefix . 'bbb_options';
		$templates_table         = $wpdb->prefix . 'bbb_templates';

		$template = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$templates_table} WHERE id = %d AND is_active = 1", $template_id ),
			ARRAY_A
		);

		if ( ! $template ) {
			wp_send_json_error( array( 'message' => 'This build experience is no longer available.' ) );
		}

		$now            = current_time( 'mysql' );
		$reference_code = self::generate_reference_code();

		$inserted = $wpdb->insert(
			$submissions_table,
			array(
				'template_id'       => $template_id,
				'reference_code'    => $reference_code,
				'customer_name'     => $customer_name,
				'customer_whatsapp' => $customer_whatsapp,
				'customer_email'    => $customer_email,
				'customer_message'  => null,
				'status'            => 'new',
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);

		if ( ! $inserted ) {
			wp_send_json_error( array( 'message' => 'We could not save your build request. Please try again.' ) );
		}

		$submission_id = $wpdb->insert_id;

		// While saving each selected option, we also build up a plain-text
		// summary (e.g. "Frame Colour: Black") to include in the
		// notification email below.
		$summary_lines = array();

		foreach ( $options as $selection ) {

			$group_id  = isset( $selection['group_id'] ) ? absint( $selection['group_id'] ) : 0;
			$option_id = isset( $selection['option_id'] ) ? absint( $selection['option_id'] ) : 0;

			if ( ! $group_id || ! $option_id ) {
				continue;
			}

			$wpdb->insert(
				$submission_items_table,
				array(
					'submission_id' => $submission_id,
					'group_id'      => $group_id,
					'option_id'     => $option_id,
				)
			);

			$group_label  = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$groups_table} WHERE id = %d", $group_id ) );
			$option_label = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$options_table} WHERE id = %d", $option_id ) );

			if ( $group_label && $option_label ) {
				$summary_lines[] = $group_label . ': ' . $option_label;
			}
		}

		self::send_notification_email( $template, $reference_code, $customer_name, $customer_email, $customer_whatsapp, $summary_lines );

		wp_send_json_success( array( 'reference_code' => $reference_code ) );
	}

	/**
	 * Creates a short, human-friendly reference code for this
	 * submission, e.g. "BBB-4F92A1", so staff and customers have an
	 * easy way to refer to this specific build request.
	 */
	private static function generate_reference_code() {

		return 'BBB-' . strtoupper( substr( uniqid(), -6 ) );
	}

	/**
	 * Emails the shop whenever a new build request comes in, so staff
	 * know about it immediately instead of having to check the
	 * database or an admin page.
	 *
	 * The recipient address is hard-coded for now, since only one
	 * person needs it today. A future step can move this into a
	 * proper settings field if more recipients are needed.
	 */
	private static function send_notification_email( $template, $reference_code, $customer_name, $customer_email, $customer_whatsapp, $summary_lines ) {

		$to      = 'fareed@thecyclehub.com';
		$subject = 'New Bike Build Request - ' . $reference_code;

		$body  = "A new custom bike build request has been submitted.\n\n";
		$body .= 'Build: ' . $template['name'] . "\n";
		$body .= 'Reference: ' . $reference_code . "\n\n";
		$body .= "Customer Details:\n";
		$body .= 'Name: ' . $customer_name . "\n";
		$body .= 'Email: ' . $customer_email . "\n";
		$body .= 'Phone: ' . $customer_whatsapp . "\n\n";
		$body .= "Build Selections:\n";

		foreach ( $summary_lines as $line ) {
			$body .= '- ' . $line . "\n";
		}

		wp_mail( $to, $subject, $body );
	}
}
