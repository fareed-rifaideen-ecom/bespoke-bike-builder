<?php
/**
 * Runs once, only when the plugin is activated.
 *
 * This class creates all of the plugin's database tables, and seeds
 * starter data so we have real content to build and test the
 * customer-facing builder in the next steps.
 *
 * As of Step 18, maybe_upgrade() also runs on every admin page load
 * to safely add new columns (like image_id) to existing tables on
 * sites where the plugin was already active before that column was
 * introduced - without requiring anyone to deactivate/reactivate.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BBB_Activator {

	/**
	 * This function runs automatically when someone clicks "Activate"
	 * on the Bespoke Bike Builder plugin in wp-admin.
	 */
	public static function activate() {

		self::create_templates_table();
		self::create_option_groups_table();
		self::create_options_table();
		self::create_submissions_table();
		self::create_submission_items_table();

		self::seed_default_template();
		self::seed_default_option_groups_and_options();

		error_log( 'Bespoke Bike Builder: activation hook ran successfully.' );
	}

	/**
	 * Safely re-runs table creation for tables whose structure has
	 * changed since the plugin was first activated. dbDelta() only
	 * ever adds or modifies columns to match the SQL given to it - it
	 * never deletes existing data, so this is safe to run on every
	 * admin page load.
	 */
	public static function maybe_upgrade() {

		self::create_options_table();
	}

	/**
	 * Creates the wp_bbb_templates table.
	 *
	 * This table stores each "Build Template" - for example,
	 * the Pinarello Dogma F Road Bike custom build experience.
	 */
	private static function create_templates_table() {

		global $wpdb;

		$table_name      = $wpdb->prefix . 'bbb_templates';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			brand VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Creates the wp_bbb_option_groups table.
	 *
	 * Each row here is a step in the builder, e.g. "Frame Colour"
	 * or "Groupset", and it belongs to one template (via template_id).
	 */
	private static function create_option_groups_table() {

		global $wpdb;

		$table_name      = $wpdb->prefix . 'bbb_option_groups';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			template_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(191) NOT NULL,
			display_type VARCHAR(20) NOT NULL DEFAULT 'tile',
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY template_id (template_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Creates (or, as of Step 18, upgrades) the wp_bbb_options table.
	 *
	 * Each row here is one selectable choice, e.g. "Black" or
	 * "Shimano Ultegra Di2", and it belongs to one option group
	 * (via group_id). As of Step 18, each option can also carry an
	 * optional image_id, pointing at a WordPress Media Library
	 * attachment, so the customer-facing builder can show a real
	 * product photo for that choice.
	 */
	private static function create_options_table() {

		global $wpdb;

		$table_name      = $wpdb->prefix . 'bbb_options';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			group_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(191) NOT NULL,
			price_delta DECIMAL(10,2) NOT NULL DEFAULT 0,
			image_id BIGINT UNSIGNED NULL DEFAULT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY group_id (group_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Creates the wp_bbb_submissions table.
	 *
	 * Each row here is one customer's completed build request,
	 * including their contact details and a unique reference code.
	 */
	private static function create_submissions_table() {

		global $wpdb;

		$table_name      = $wpdb->prefix . 'bbb_submissions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			template_id BIGINT UNSIGNED NOT NULL,
			reference_code VARCHAR(50) NOT NULL,
			customer_name VARCHAR(191) NOT NULL,
			customer_whatsapp VARCHAR(50) NOT NULL,
			customer_email VARCHAR(191) NOT NULL,
			customer_message TEXT NULL,
			status VARCHAR(50) NOT NULL DEFAULT 'new',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference_code (reference_code),
			KEY template_id (template_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Creates the wp_bbb_submission_items table.
	 *
	 * Each row here records one option a customer picked as part
	 * of one submission - for example "Frame Colour: Black".
	 * A single submission will have several of these rows.
	 */
	private static function create_submission_items_table() {

		global $wpdb;

		$table_name      = $wpdb->prefix . 'bbb_submission_items';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id BIGINT UNSIGNED NOT NULL,
			group_id BIGINT UNSIGNED NOT NULL,
			option_id BIGINT UNSIGNED NOT NULL,
			PRIMARY KEY  (id),
			KEY submission_id (submission_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Adds the initial "Pinarello Dogma F Road Bike" template row,
	 * but only if it does not already exist.
	 */
	private static function seed_default_template() {

		global $wpdb;

		$table_name = $wpdb->prefix . 'bbb_templates';
		$slug       = 'dogma-f';

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE slug = %s",
				$slug
			)
		);

		if ( $existing ) {
			return;
		}

		$now = current_time( 'mysql' );

		$wpdb->insert(
			$table_name,
			array(
				'name'       => 'Pinarello Dogma F Road Bike',
				'brand'      => 'Pinarello',
				'slug'       => $slug,
				'is_active'  => 1,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
	}

	/**
	 * Adds starter option groups and options for the Dogma F template,
	 * but only the very first time (if Dogma F already has any option
	 * groups, this function does nothing - so reactivating the plugin
	 * will never create duplicates).
	 */
	private static function seed_default_option_groups_and_options() {

		global $wpdb;

		$templates_table = $wpdb->prefix . 'bbb_templates';
		$groups_table    = $wpdb->prefix . 'bbb_option_groups';
		$options_table   = $wpdb->prefix . 'bbb_options';

		// Find the Dogma F template's ID.
		$template_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$templates_table} WHERE slug = %s",
				'dogma-f'
			)
		);

		if ( ! $template_id ) {
			return;
		}

		// If Dogma F already has option groups, stop here - already seeded.
		$existing_groups = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$groups_table} WHERE template_id = %d",
				$template_id
			)
		);

		if ( $existing_groups > 0 ) {
			return;
		}

		// This array describes every group and its starter options.
		// display_type 'tile' means image-style buttons; 'dropdown' means
		// a normal select box. Staff will manage all of this later from
		// the Staff Frontend - this is just enough to build and test with.
		$groups = array(
			array(
				'label'        => 'Build Type',
				'display_type' => 'tile',
				'options'      => array( 'Complete Build', 'Frame Only' ),
			),
			array(
				'label'        => 'Frame Colour',
				'display_type' => 'tile',
				'options'      => array( 'Black', 'White' ),
			),
			array(
				'label'        => 'Frame Size',
				'display_type' => 'dropdown',
				'options'      => array( '51', '53', '55' ),
			),
			array(
				'label'        => 'Cockpit',
				'display_type' => 'tile',
				'options'      => array( '42/100', '42/110' ),
			),
			array(
				'label'        => 'Groupset',
				'display_type' => 'tile',
				'options'      => array( 'Shimano Ultegra Di2', 'Shimano Dura-Ace Di2' ),
			),
			array(
				'label'        => 'Wheelset',
				'display_type' => 'tile',
				'options'      => array( 'Fulcrum Speed 42' ),
			),
		);

		$sort_order = 0;

		foreach ( $groups as $group ) {

			$sort_order++;

			$wpdb->insert(
				$groups_table,
				array(
					'template_id'  => $template_id,
					'label'        => $group['label'],
					'display_type' => $group['display_type'],
					'sort_order'   => $sort_order,
				)
			);

			$group_id = $wpdb->insert_id;

			$option_sort = 0;

			foreach ( $group['options'] as $option_label ) {

				$option_sort++;

				$wpdb->insert(
					$options_table,
					array(
						'group_id'    => $group_id,
						'label'       => $option_label,
						'price_delta' => 0,
						'is_active'   => 1,
						'sort_order'  => $option_sort,
					)
				);
			}
		}
	}
}
