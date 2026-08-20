<?php
/**
 * Runs once, only when the plugin is activated.
 *
 * This class currently just confirms that activation works correctly.
 * In a later step, this is where we will create the plugin's database tables.
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

		// error_log() writes a message to WordPress's debug log file,
		// so a developer can confirm this code actually ran.
		// It is never shown to site visitors or customers.
		error_log( 'Bespoke Bike Builder: activation hook ran successfully.' );
	}
}
