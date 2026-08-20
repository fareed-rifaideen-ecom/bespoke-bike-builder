<?php
/**
 * Registers shortcodes that can be pasted into any WordPress Page.
 *
 * [bbb_builder] now renders every option group as its own step in a
 * one-step-at-a-time wizard. Each step is displayed as tiles or as
 * a dropdown, depending on that group's display_type value in the
 * database (this is exactly the admin-controlled setting described
 * in our blueprint). Navigation between steps is handled by
 * assets/js/builder.js.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BBB_Shortcodes {

	/**
	 * This function "switches on" the shortcode feature.
	 * It is called once, from the main plugin file.
	 */
	public static function init() {

		add_shortcode( 'bbb_builder', array( __CLASS__, 'render_builder' ) );
	}

	/**
	 * Loads our CSS and JavaScript files, but only once per page.
	 */
	private static function enqueue_assets() {

		static $already_loaded = false;

		if ( $already_loaded ) {
			return;
		}

		wp_enqueue_style(
			'bbb-builder-css',
			BBB_PLUGIN_URL . 'assets/css/builder.css',
			array(),
			'1.1.0'
		);

		wp_enqueue_script(
			'bbb-builder-js',
			BBB_PLUGIN_URL . 'assets/js/builder.js',
			array(),
			'1.1.0',
			true
		);

		$already_loaded = true;
	}

	/**
	 * Outputs the full step-by-step builder for a given template.
	 */
	public static function render_builder( $atts ) {

		$atts = shortcode_atts(
			array(
				'template' => 'dogma-f',
			),
			$atts
		);

		self::enqueue_assets();

		global $wpdb;

		$templates_table = $wpdb->prefix . 'bbb_templates';
		$groups_table    = $wpdb->prefix . 'bbb_option_groups';
		$options_table   = $wpdb->prefix . 'bbb_options';

		$template = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$templates_table} WHERE slug = %s AND is_active = 1",
				$atts['template']
			),
			ARRAY_A
		);

		if ( ! $template ) {
			return '<p>This build experience is not available right now.</p>';
		}

		$groups = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$groups_table} WHERE template_id = %d ORDER BY sort_order ASC",
				$template['id']
			),
			ARRAY_A
		);

		if ( empty( $groups ) ) {
			return '<p>This build experience has no options configured yet.</p>';
		}

		$total_steps = count( $groups );

		ob_start();
		?>

		<div class="bbb-builder-placeholder" data-total-steps="<?php echo esc_attr( $total_steps ); ?>">

			<h2><?php echo esc_html( $template['name'] ); ?></h2>

			<p class="bbb-progress">Step 1 of <?php echo esc_html( $total_steps ); ?></p>

			<?php foreach ( $groups as $index => $group ) : ?>

				<?php
				$options = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$options_table} WHERE group_id = %d AND is_active = 1 ORDER BY sort_order ASC",
						$group['id']
					),
					ARRAY_A
				);

				$is_first_step = ( 0 === $index );
				?>

				<div class="bbb-step<?php echo $is_first_step ? ' bbb-step-active' : ''; ?>" data-step-index="<?php echo esc_attr( $index ); ?>">

					<h3><?php echo esc_html( $group['label'] ); ?></h3>

					<?php if ( 'dropdown' === $group['display_type'] ) : ?>

						<select class="bbb-dropdown">
							<option value="">Select an option</option>
							<?php foreach ( $options as $option ) : ?>
								<option value="<?php echo esc_attr( $option['label'] ); ?>">
									<?php echo esc_html( $option['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>

					<?php else : ?>

						<div class="bbb-tile-group">
							<?php foreach ( $options as $option ) : ?>
								<div class="bbb-tile" data-value="<?php echo esc_attr( $option['label'] ); ?>">
									<?php echo esc_html( $option['label'] ); ?>
								</div>
							<?php endforeach; ?>
						</div>

					<?php endif; ?>

				</div>

			<?php endforeach; ?>

			<div class="bbb-nav">
				<button type="button" class="bbb-back-button" style="display:none;">Back</button>
				<button type="button" class="bbb-next-button" disabled>Next</button>
			</div>

		</div>

		<?php
		return ob_get_clean();
	}
}
