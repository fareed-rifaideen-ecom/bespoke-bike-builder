<?php
/**
 * Bespoke Bike Builder — Submission Event Log
 *
 * Implements the "Event Log" entity from Blueprint Section 6.1
 * (audit history of changes, assignments, statuses and payment
 * events). This class does not create submissions or change
 * statuses itself - it only listens for actions fired by the
 * classes that do (BBB_Ajax and BBB_Staff_Portal), and writes a
 * human-readable row into wp_bbb_submission_events for each one.
 *
 * Keeping this as a separate listener, rather than writing log
 * rows directly inside BBB_Ajax/BBB_Staff_Portal, means any future
 * event source (deposits, revisions, quote PDFs, etc.) can log
 * activity the same way, just by firing its own do_action() call -
 * without ever needing to know how logging itself works.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBB_Event_Log {

	public static function init() {
		add_action( 'bbb_submission_created', array( __CLASS__, 'log_submission_created' ), 10, 4 );
		add_action( 'bbb_submission_status_updated', array( __CLASS__, 'log_status_updated' ), 10, 4 );
	}

	/**
	 * Logs a new build request being submitted by a customer.
	 *
	 * @param int    $submission_id
	 * @param int    $template_id
	 * @param string $reference_code
	 * @param array  $summary_lines Human-readable option selections, e.g. "Frame Colour: Black".
	 */
	public static function log_submission_created( $submission_id, $template_id, $reference_code, $summary_lines ) {

		$description = sprintf(
			'Build request %s submitted with %d selection(s).',
			$reference_code,
			is_array( $summary_lines ) ? count( $summary_lines ) : 0
		);

		// Customer submissions have no logged-in WordPress user behind
		// them (customers never log in), so actor_id is left null here.
		self::insert_event( $submission_id, 'submission_created', $description, null );
	}

	/**
	 * Logs a staff member changing a submission's status.
	 *
	 * @param int    $submission_id
	 * @param string $old_status
	 * @param string $new_status
	 * @param int    $user_id Staff member who made the change.
	 */
	public static function log_status_updated( $submission_id, $old_status, $new_status, $user_id ) {

		$old_status = $old_status ? $old_status : '(none)';

		$description = sprintf(
			'Status changed from "%s" to "%s".',
			$old_status,
			$new_status
		);

		self::insert_event( $submission_id, 'status_updated', $description, $user_id );
	}

	/**
	 * Writes one row into wp_bbb_submission_events.
	 *
	 * Kept private so every event this class logs goes through the
	 * same, single insert path - callers only describe *what*
	 * happened, never *how* it gets stored.
	 *
	 * @param int         $submission_id
	 * @param string      $event_type
	 * @param string      $description
	 * @param int|null    $actor_id
	 */
	private static function insert_event( $submission_id, $event_type, $description, $actor_id ) {

		global $wpdb;

		$table = $wpdb->prefix . 'bbb_submission_events';

		$wpdb->insert(
			$table,
			array(
				'submission_id' => absint( $submission_id ),
				'event_type'    => $event_type,
				'description'   => $description,
				'actor_id'      => $actor_id ? absint( $actor_id ) : null,
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Fetches every logged event for one submission, oldest first, so
	 * a future staff-portal detail view can show a full timeline.
	 *
	 * @param int $submission_id
	 * @return array
	 */
	public static function get_events_for_submission( $submission_id ) {

		global $wpdb;

		$table = $wpdb->prefix . 'bbb_submission_events';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE submission_id = %d ORDER BY created_at ASC",
				absint( $submission_id )
			),
			ARRAY_A
		);
	}
}
