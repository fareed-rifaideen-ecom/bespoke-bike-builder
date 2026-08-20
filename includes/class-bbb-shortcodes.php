<?php
/**
 * Registers shortcodes that can be pasted into any WordPress Page.
 *
 * For now, this only contains [bbb_builder], which displays a plain,
 * unstyled list of a build template's option groups and options.
 * This proves our database data can reach a real public page. Design
 * and interactivity will be added in the upcoming steps.
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

		// add_shortcode() tells WordPress: whenever [bbb_builder] appears
		// inside a Page or Post's content, run render_builder() and use
		// whatever it returns as the replacement content.
		add_shortcode( 'bbb_builder', array( __CLASS__, 'render_builder' ) );
	}

	/**
	 * Outputs the builder content for a given template.
	 *
	 * @param array $atts Attributes passed inside the shortcode tag,
	 *                     e.g. [bbb_builder template="dogma-f"]
	 *                     gives us $atts['template'] = 'dogma-f'.
	 */
	public static function render_builder( $atts ) {

		// shortcode_atts() safely merges the attributes someone typed
		// with sensible defaults, in case they forget to type one.
		$atts = shortcode_atts(
			array(
				'template' => 'dogma-f',
			),
			$atts
		);

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

		// If no matching, active template is found, show a simple message
		// instead of breaking the page. This keeps things safe on a live site.
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

		// ob_start() and ob_get_clean() let us build up a block of HTML
		// using plain PHP/HTML mixed together, then capture it as text
		// to return - shortcode functions must return their output,
		// never print it directly with echo.
		ob_start();
		?>

		<div class="bbb-builder-placeholder">

			<h2><?php echo esc_html( $template['name'] ); ?></h2>
			<p>This is a temporary, unstyled preview. Design and step-by-step
			   interaction will be added in the next steps.</p>

			<?php foreach ( $groups as $group ) : ?>

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

		</div>

		<?php
		return ob_get_clean();
	}
}
