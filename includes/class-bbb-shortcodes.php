<?php
/**
 * Registers shortcodes that can be pasted into any WordPress Page.
 *
 * [bbb_builder] now renders the "Build Type" step as clickable
 * tiles with a Next button. The remaining option groups are still
 * shown below as a plain list for now, and will become their own
 * interactive steps in the upcoming steps.
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
	 * Loads our CSS and JavaScript files, but only once per page,
	 * even if the shortcode were somehow used more than once.
	 *
	 * wp_enqueue_style() and wp_enqueue_script() are WordPress's
	 * proper way of loading files - they avoid loading the same
	 * file twice and let WordPress manage load order safely
	 * alongside every other plugin and theme file already in use.
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
			'1.0.0'
		);

		wp_enqueue_script(
			'bbb-builder-js',
			BBB_PLUGIN_URL . 'assets/js/builder.js',
			array(),
			'1.0.0',
			true // Load in the footer, after the page content already exists.
		);

		$already_loaded = true;
	}

	/**
	 * Outputs the builder content for a given template.
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

		// The first group (lowest sort_order) is "Build Type" - we treat
		// it specially in this step. Everything else stays a plain list
		// for now, until we build their interactive steps too.
		$first_group       = ! empty( $groups ) ? $groups[0] : null;
		$remaining_groups  = ! empty( $groups ) ? array_slice( $groups, 1 ) : array();

		ob_start();
		?>

		<div class="bbb-builder-placeholder">

			<h2><?php echo esc_html( $template['name'] ); ?></h2>

			<?php if ( $first_group ) : ?>

				<?php
				$first_group_options = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$options_table} WHERE group_id = %d AND is_active = 1 ORDER BY sort_order ASC",
						$first_group['id']
					),
					ARRAY_A
				);
				?>

				<div class="bbb-step">
					<h3><?php echo esc_html( $first_group['label'] ); ?></h3>

					<div class="bbb-tile-group">
						<?php foreach ( $first_group_options as $option ) : ?>
							<div class="bbb-tile"><?php echo esc_html( $option['label'] ); ?></div>
						<?php endforeach; ?>
					</div>

					<button type="button" class="bbb-next-button" disabled>Next</button>
				</div>

			<?php endif; ?>

			<?php if ( ! empty( $remaining_groups ) ) : ?>

				<hr />
				<p><em>The remaining steps below are still shown as a plain list for now, and will become interactive in the next steps.</em></p>

				<?php foreach ( $remaining_groups as $group ) : ?>

					<?php
					$options = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM {$options_table} WHERE group_id = %d AND is_active = 1 ORDER BY sort_order ASC",
							$group['id']
						),
						ARRAY_A
					);
					?>

					<h3><?php echo esc_html( $group['label'] ); ?></h3>
					<ul>
						<?php foreach ( $options as $option ) : ?>
							<li><?php echo esc_html( $option['label'] ); ?></li>
						<?php endforeach; ?>
					</ul>

				<?php endforeach; ?>

			<?php endif; ?>

		</div>

		<?php
		return ob_get_clean();
	}
}
