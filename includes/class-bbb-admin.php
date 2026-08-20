<?php
/**
 * Handles the wp-admin menu page for Bespoke Bike Builder.
 *
 * This page lists every row in the wp_bbb_templates table, and now
 * also shows the Dogma F template's option groups and their options,
 * so we can confirm all of our Step 8 tables and seed data are
 * correct before we start building the customer-facing pages.
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

		add_action( 'admin_menu', array( __CLASS__, 'register_menu_page' ) );
	}

	/**
	 * Adds "Bespoke Bike Builder" as a new top-level menu item
	 * in the wp-admin sidebar.
	 */
	public static function register_menu_page() {

		add_menu_page(
			'Bespoke Bike Builder',
			'Bespoke Bike Builder',
			'manage_options',
			'bbb-dashboard',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-palmtree',
			56
		);
	}

	/**
	 * Displays the actual content of the admin page.
	 */
	public static function render_dashboard_page() {

		global $wpdb;

		$templates_table = $wpdb->prefix . 'bbb_templates';
		$groups_table    = $wpdb->prefix . 'bbb_option_groups';
		$options_table   = $wpdb->prefix . 'bbb_options';

		$templates = $wpdb->get_results( "SELECT * FROM {$templates_table}", ARRAY_A );

		?>
		<div class="wrap">
			<h1>Bespoke Bike Builder</h1>
			<p>This page confirms the plugin's database tables and initial data were created successfully.</p>

			<h2>Build Templates</h2>

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

							<?php
							// For each template, also show its option groups and options.
							// This is the part that is new in Step 9.
							$groups = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT * FROM {$groups_table} WHERE template_id = %d ORDER BY sort_order ASC",
									$template['id']
								),
								ARRAY_A
							);
							?>

						<?php endforeach; ?>
					</tbody>
				</table>

			<?php endif; ?>

			<h2>Dogma F &mdash; Option Groups</h2>

			<?php
			// We already know Dogma F's template ID from the loop above,
			// but to keep this section simple and independent, we look
			// it up again directly by its slug.
			$dogma_f_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$templates_table} WHERE slug = %s",
					'dogma-f'
				)
			);

			$groups = array();

			if ( $dogma_f_id ) {
				$groups = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$groups_table} WHERE template_id = %d ORDER BY sort_order ASC",
						$dogma_f_id
					),
					ARRAY_A
				);
			}
			?>

			<?php if ( empty( $groups ) ) : ?>

				<p><strong>No option groups found yet.</strong> Something may have gone wrong in Step 8 - let's check together.</p>

			<?php else : ?>

				<table class="widefat striped">
					<thead>
						<tr>
							<th>Group</th>
							<th>Display Type</th>
							<th>Options</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $groups as $group ) : ?>

							<?php
							// For each group, fetch its options and join their
							// labels into one simple comma-separated line.
							$options = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT label FROM {$options_table} WHERE group_id = %d ORDER BY sort_order ASC",
									$group['id']
								),
								ARRAY_A
							);

							$option_labels = wp_list_pluck( $options, 'label' );
							?>

							<tr>
								<td><?php echo esc_html( $group['label'] ); ?></td>
								<td><?php echo esc_html( $group['display_type'] ); ?></td>
								<td><?php echo esc_html( implode( ', ', $option_labels ) ); ?></td>
							</tr>

						<?php endforeach; ?>
					</tbody>
				</table>

			<?php endif; ?>

		</div>
		<?php
	}
}
