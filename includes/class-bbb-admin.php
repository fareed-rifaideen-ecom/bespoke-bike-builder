<?php
/**
 * Handles the wp-admin menu page for Bespoke Bike Builder.
 *
 * Right now, this page simply lists every row in the wp_bbb_templates
 * table, so we can confirm our database table and seeded data
 * (from Step 6) actually exist and are readable.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BBB_Admin {

	/**
	 * This function "switches on" the admin page feature.
	 * It is called once, from the main plugin file.
	 */
	public static function init() {

		// 'admin_menu' is a WordPress hook that fires while wp-admin
		// is building its left-hand sidebar menu. We attach our own
		// function to it, so our menu item gets added at the right moment.
		add_action( 'admin_menu', array( __CLASS__, 'register_menu_page' ) );
	}

	/**
	 * Adds "Bespoke Bike Builder" as a new top-level menu item
	 * in the wp-admin sidebar.
	 */
	public static function register_menu_page() {

		add_menu_page(
			'Bespoke Bike Builder',       // Page title (shown in the browser tab)
			'Bespoke Bike Builder',       // Menu title (shown in the sidebar)
			'manage_options',             // Required capability - Administrators only
			'bbb-dashboard',              // Unique menu slug (used in the page URL)
			array( __CLASS__, 'render_dashboard_page' ), // Function that displays the page
			'dashicons-palmtree',         // Icon shown next to the menu item
			56                             // Position in the sidebar menu order
		);
	}

	/**
	 * Displays the actual content of the admin page.
	 * This runs only when an Administrator visits the page,
	 * because of the 'manage_options' capability check above.
	 */
	public static function render_dashboard_page() {

		global $wpdb;

		$table_name = $wpdb->prefix . 'bbb_templates';

		// $wpdb->get_results() reads rows back out of the database.
		// ARRAY_A means each row comes back as a simple associative array,
		// e.g. $row['name'], $row['brand'], etc.
		$templates = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

		?>
		<div class="wrap">
			<h1>Bespoke Bike Builder</h1>
			<p>This page confirms the plugin's database table and initial data were created successfully.</p>

			<?php if ( empty( $templates ) ) : ?>

				<p><strong>No build templates found yet.</strong> Something may have gone wrong in Step 6 - let's check together.</p>

			<?php else : ?>

				<table class="widefat striped">
					<thead>
						<tr>
							<th>ID</th>
							<th>Name</th>
							<th>Brand</th>
							<th>Slug</th>
							<th>Active</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $templates as $template ) : ?>
							<tr>
								<td><?php echo esc_html( $template['id'] ); ?></td>
								<td><?php echo esc_html( $template['name'] ); ?></td>
								<td><?php echo esc_html( $template['brand'] ); ?></td>
								<td><?php echo esc_html( $template['slug'] ); ?></td>
								<td><?php echo $template['is_active'] ? 'Yes' : 'No'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

			<?php endif; ?>

		</div>
		<?php
	}
}
