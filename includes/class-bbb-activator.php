<?php
/**
 * Runs once, only when the plugin is activated.
 *
 * This class creates the plugin's database tables and, the first time
 * the table is created, adds the initial "Pinarello Dogma F Road Bike"
 * build template so we have real data to work with in later steps.
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
		self::seed_default_template();

		error_log( 'Bespoke Bike Builder: activation hook ran successfully.' );
	}

	/**
	 * Creates the wp_bbb_templates table.
	 *
	 * This table stores each "Build Template" - for example,
	 * the Pinarello Dogma F Road Bike custom build experience.
	 * In the future, other build templates (like Specialized) will
	 * also live as rows in this same table.
	 */
	private static function create_templates_table() {

		global $wpdb;

		// $wpdb->prefix automatically matches whatever table prefix
		// this specific WordPress site uses (commonly "wp_", but not always).
		$table_name = $wpdb->prefix . 'bbb_templates';

		// This tells MySQL which character set and collation to use,
		// matching whatever the rest of this WordPress site already uses.
		$charset_collate = $wpdb->get_charset_collate();

		// This is the SQL statement that describes the table structure.
		// The specific spacing and formatting here follows WordPress's
		// required style so that dbDelta() (below) can read it correctly.
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

		// dbDelta() is a special WordPress function built specifically
		// for safely creating or updating database tables. It will not
		// delete existing data when we change the table structure later.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Adds the initial "Pinarello Dogma F Road Bike" template row,
	 * but only if it does not already exist. This means it is safe
	 * to reactivate the plugin multiple times without creating
	 * duplicate rows.
	 */
	private static function seed_default_template() {

		global $wpdb;

		$table_name = $wpdb->prefix . 'bbb_templates';
		$slug       = 'dogma-f';

		// Check if a template with this slug already exists.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE slug = %s",
				$slug
			)
		);

		// If it already exists, do nothing - this avoids duplicate rows.
		if ( $existing ) {
			return;
		}

		$now = current_time( 'mysql' );

		// $wpdb->insert() safely inserts a new row into the table.
		// WordPress automatically protects these values from SQL injection.
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
}
