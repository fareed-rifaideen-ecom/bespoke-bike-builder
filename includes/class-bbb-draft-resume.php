<?php
/**
 * Bespoke Bike Builder — Draft & Resume System
 *
 * Self-contained addition that lets a customer save their in-progress
 * Dogma F configuration and resume it later via a secure emailed link,
 * per Blueprint Section 10 (Draft, Resume, Share and Revisions).
 *
 * This registers its own database table, its own AJAX actions, and its
 * own activation-time table-creation check — it does not modify or
 * depend on the internals of class-bbb-activator.php, class-bbb-ajax.php
 * or class-bbb-shortcodes.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBB_Draft_Resume {

	const TABLE_NAME = 'bbb_drafts';
	const NONCE_ACTION = 'bbb_draft_resume_nonce';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_create_table' ) );
		add_action( 'wp_ajax_bbb_save_draft', array( __CLASS__, 'ajax_save_draft' ) );
		add_action( 'wp_ajax_nopriv_bbb_save_draft', array( __CLASS__, 'ajax_save_draft' ) );
		add_action( 'wp_ajax_bbb_get_draft', array( __CLASS__, 'ajax_get_draft' ) );
		add_action( 'wp_ajax_nopriv_bbb_get_draft', array( __CLASS__, 'ajax_get_draft' ) );
		add_action( 'wp_ajax_bbb_email_draft_link', array( __CLASS__, 'ajax_email_draft_link' ) );
		add_action( 'wp_ajax_nopriv_bbb_email_draft_link', array( __CLASS__, 'ajax_email_draft_link' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Creates the wp_bbb_drafts table on first load if it doesn't already
	 * exist. Safe to run on every plugins_loaded call — dbDelta() and the
	 * SHOW TABLES check both no-op harmlessly if the table is present.
	 */
	public static function maybe_create_table() {
		global $wpdb;
		$table = self::table_name();

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			token VARCHAR(64) NOT NULL,
			email VARCHAR(190) NULL,
			selections LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			expires_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token)
		) $charset_collate;";

		dbDelta( $sql );
	}

	private static function generate_token() {
		return bin2hex( random_bytes( 16 ) );
	}

	private static function verify_nonce() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		return wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Saves or updates a draft. If no token is supplied, a new one is
	 * generated and returned. Selections are stored as-is (a JSON string
	 * built client-side from the customer's current tile/dropdown state)
	 * — never trusted or parsed as executable data, only stored and
	 * echoed back verbatim on resume.
	 */
	public static function ajax_save_draft() {
		if ( ! self::verify_nonce() ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 403 );
		}

		global $wpdb;
		$table = self::table_name();

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$selections_raw = isset( $_POST['selections'] ) ? wp_unslash( $_POST['selections'] ) : '';
		$selections_json = wp_json_encode( json_decode( $selections_raw, true ) );

		if ( $selections_json === 'null' || empty( $selections_raw ) ) {
			wp_send_json_error( array( 'message' => 'No selections to save.' ), 400 );
		}

		$now = current_time( 'mysql' );
		$expires = gmdate( 'Y-m-d H:i:s', time() + ( 30 * DAY_IN_SECONDS ) );

		if ( $token ) {
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE token = %s", $token ) );
			if ( $existing ) {
				$wpdb->update(
					$table,
					array(
						'selections' => $selections_json,
						'updated_at' => $now,
						'expires_at' => $expires,
					),
					array( 'token' => $token )
				);
				wp_send_json_success( array( 'token' => $token ) );
			}
		}

		$token = self::generate_token();
		$wpdb->insert(
			$table,
			array(
				'token'      => $token,
				'selections' => $selections_json,
				'created_at' => $now,
				'updated_at' => $now,
				'expires_at' => $expires,
			)
		);

		wp_send_json_success( array( 'token' => $token ) );
	}

	public static function ajax_get_draft() {
		if ( ! self::verify_nonce() ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 403 );
		}

		global $wpdb;
		$table = self::table_name();
		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		if ( ! $token ) {
			wp_send_json_error( array( 'message' => 'Missing token.' ), 400 );
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT selections, expires_at FROM $table WHERE token = %s", $token ) );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => 'Draft not found.' ), 404 );
		}

		if ( $row->expires_at && strtotime( $row->expires_at ) < time() ) {
			wp_send_json_error( array( 'message' => 'This resume link has expired.' ), 410 );
		}

		wp_send_json_success( array( 'selections' => $row->selections ) );
	}

	/**
	 * Emails the resume link to the customer. Uses the site's default
	 * wp_mail() transport — no external email service dependency.
	 */
	public static function ajax_email_draft_link() {
		if ( ! self::verify_nonce() ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 403 );
		}

		global $wpdb;
		$table = self::table_name();

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( ! $token || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'A valid email address is required.' ), 400 );
		}

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE token = %s", $token ) );
		if ( ! $exists ) {
			wp_send_json_error( array( 'message' => 'Draft not found.' ), 404 );
		}

		$wpdb->update( $table, array( 'email' => $email ), array( 'token' => $token ) );

		$resume_url = add_query_arg( 'bbb_resume', $token, self::current_page_url() );

		$subject = 'Continue your Pinarello Dogma F build — The Cycle Hub';
		$message = "Hi,\n\nHere's your link to continue your Pinarello Dogma F build where you left off:\n\n" . $resume_url . "\n\nThis link stays valid for 30 days.\n\n— The Cycle Hub";

		$sent = wp_mail( $email, $subject, $message );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => 'Could not send the email. Please try again.' ), 500 );
		}

		wp_send_json_success( array( 'message' => 'Resume link sent.' ) );
	}

	private static function current_page_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		// Strip any existing bbb_resume param and query string so we start clean.
		$path = strtok( $uri, '?' );
		return $scheme . $host . $path;
	}
}
