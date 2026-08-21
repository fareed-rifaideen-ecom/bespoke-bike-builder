<?php
/**
 * Handles the wp-admin menu pages for Bespoke Bike Builder.
 *
 * This file adds two pages:
 * - "Bespoke Bike Builder" - the original page confirming our build
 *   templates data exists (from Step 7).
 * - "Build Requests" - a real working list of every customer build
 *   submission, where staff can review the full build spec, any
 *   optional Remarks the customer left, and update each request's
 *   status.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BBB_Admin {

	/**
	 * The list of valid statuses a build request can move through.
	 */
	private static $statuses = array( 'new', 'contacted', 'confirmed', 'completed' );

	/**
	 * This function "switches on" the admin pages feature.
	 * It is called once, from the main plugin file.
	 */
	public static function init() {

		add_action( 'admin_menu', array( __CLASS__, 'register_menu_pages' ) );

		// admin_post_* handles form submissions sent to wp-admin/admin-post.php.
		// This is WordPress's standard way of safely handling a normal form
		// submission (not AJAX) from inside wp-admin.
		add_action( 'admin_post_bbb_update_status', array( __CLASS__, 'handle_update_status' ) );
	}

	/**
	 * Adds "Bespoke Bike Builder" as a top-level menu item, plus a
	 * "Build Requests" page underneath it.
	 */
	public static function register_menu_pages() {

		add_menu_page(
			'Bespoke Bike Builder',
			'Bespoke Bike Builder',
			'manage_options',
			'bbb-dashboard',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-palmtree',
			56
		);

		add_submenu_page(
			'bbb-dashboard',              // Parent menu slug - keeps this nested under the page above.
			'Build Requests',             // Page title
			'Build Requests',             // Menu title
			'manage_options',             // Required capability
			'bbb-submissions',            // Unique menu slug
			array( __CLASS__, 'render_submissions_page' )
		);
	}

	/**
	 * Displays the original data-check page from Step 7.
	 */
	public static function render_dashboard_page() {

		global $wpdb;

		$table_name = $wpdb->prefix . 'bbb_templates';

		$templates = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

		?>
		<div class="wrap">
			<h1>Bespoke Bike Builder</h1>
			<p>This page confirms the plugin's database table and initial data were created successfully.</p>

			<?php if ( empty( $templates ) ) : ?>

				<p><strong>No build templates found yet.</strong></p>

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

	/**
	 * Displays every customer build request, most recent first, with
	 * the full build spec, any Remarks the customer left, and a
	 * status dropdown for each one.
	 */
	public static function render_submissions_page() {

		global $wpdb;

		$submissions_table      = $wpdb->prefix . 'bbb_submissions';
		$submission_items_table = $wpdb->prefix . 'bbb_submission_items';
		$groups_table           = $wpdb->prefix . 'bbb_option_groups';
		$options_table          = $wpdb->prefix . 'bbb_options';
		$templates_table        = $wpdb->prefix . 'bbb_templates';

		$submissions = $wpdb->get_results(
			"SELECT s.*, t.name AS template_name
			FROM {$submissions_table} s
			LEFT JOIN {$templates_table} t ON t.id = s.template_id
			ORDER BY s.created_at DESC",
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1>Build Requests</h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Status updated.</p></div>
			<?php endif; ?>

			<?php if ( empty( $submissions ) ) : ?>

				<p>No build requests have been submitted yet.</p>

			<?php else : ?>

				<table class="widefat striped">
					<thead>
						<tr>
							<th>Reference</th>
							<th>Build</th>
							<th>Customer</th>
							<th>Contact</th>
							<th>Build Details</th>
							<th>Remarks</th>
							<th>Status</th>
							<th>Submitted</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $submissions as $submission ) : ?>

							<?php
							$items = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT g.label AS group_label, o.label AS option_label
									FROM {$submission_items_table} si
									LEFT JOIN {$groups_table} g ON g.id = si.group_id
									LEFT JOIN {$options_table} o ON o.id = si.option_id
									WHERE si.submission_id = %d",
									$submission['id']
								),
								ARRAY_A
							);
							?>

							<tr>
								<td><strong><?php echo esc_html( $submission['reference_code'] ); ?></strong></td>
								<td><?php echo esc_html( $submission['template_name'] ); ?></td>
								<td><?php echo esc_html( $submission['customer_name'] ); ?></td>
								<td>
									<?php echo esc_html( $submission['customer_email'] ); ?><br>
									<?php echo esc_html( $submission['customer_whatsapp'] ); ?>
								</td>
								<td>
									<details>
										<summary>View build</summary>
										<ul style="margin: 8px 0 0 0;">
											<?php foreach ( $items as $item ) : ?>
												<li><strong><?php echo esc_html( $item['group_label'] ); ?>:</strong> <?php echo esc_html( $item['option_label'] ); ?></li>
											<?php endforeach; ?>
										</ul>
									</details>
								</td>
								<td style="max-width: 220px;">
									<?php echo ! empty( $submission['customer_message'] ) ? esc_html( $submission['customer_message'] ) : '<span style="color:#999;">-</span>'; ?>
								</td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="bbb_update_status">
										<input type="hidden" name="submission_id" value="<?php echo esc_attr( $submission['id'] ); ?>">
										<?php wp_nonce_field( 'bbb_update_status_' . $submission['id'] ); ?>
										<select name="status" onchange="this.form.submit()">
											<?php foreach ( self::$statuses as $status ) : ?>
												<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $submission['status'], $status ); ?>>
													<?php echo esc_html( ucfirst( $status ) ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</form>
								</td>
								<td><?php echo esc_html( $submission['created_at'] ); ?></td>
							</tr>

						<?php endforeach; ?>
					</tbody>
				</table>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Handles the status dropdown's form submission, updates the
	 * matching row, then redirects back to the Build Requests page.
	 */
	public static function handle_update_status() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do this.' );
		}

		$submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
		$new_status    = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		check_admin_referer( 'bbb_update_status_' . $submission_id );

		if ( ! $submission_id || ! in_array( $new_status, self::$statuses, true ) ) {
			wp_die( 'Invalid request.' );
		}

		global $wpdb;

		$submissions_table = $wpdb->prefix . 'bbb_submissions';

		$wpdb->update(
			$submissions_table,
			array(
				'status'     => $new_status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $submission_id )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=bbb-submissions&updated=1' ) );
		exit;
	}
}
