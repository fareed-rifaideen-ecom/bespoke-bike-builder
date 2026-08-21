<?php
/**
 * Registers shortcodes that can be pasted into any WordPress Page.
 *
 * [bbb_builder] renders every option group as its own step in a
 * one-step-at-a-time wizard, followed by a Review step and finally
 * a lead capture step (Name, Email, Phone, and an optional Remarks
 * message). Each option step is displayed as tiles or as a dropdown,
 * depending on that group's display_type value in the database.
 *
 * As of Step 20, any tile whose option has a product photo attached
 * (via the Manage Options admin screen) shows that photo as an
 * image card instead of a plain text tile, and the whole wizard uses
 * a dark, premium "Pinarello Dark" visual theme.
 *
 * As of Step 21, that dark theme now extends to the WHOLE page -
 * header, footer, and page background - but only on pages that
 * actually contain the [bbb_builder] shortcode. This is detected
 * automatically by checking the current page's content early in the
 * page load, so it works on any page the shortcode is placed on,
 * now or in the future, with zero manual configuration.
 *
 * Navigation, the Review summary, the lead form validation, and the
 * actual save-to-database submission are all handled by
 * assets/js/builder.js together with includes/class-bbb-ajax.php.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BBB_Shortcodes {

	/**
	 * The dark-theme version of The Cycle Hub's logo, used only on
	 * pages where the [bbb_builder] shortcode appears.
	 */
	private static $dark_logo_url = 'https://thecyclehub.com/wp-content/uploads/TCH-Logo-White-in-Black.jpg';

	/**
	 * This function "switches on" the shortcode feature.
	 * It is called once, from the main plugin file.
	 */
	public static function init() {

		add_shortcode( 'bbb_builder', array( __CLASS__, 'render_builder' ) );

		// 'wp' fires once WordPress has figured out which page is being
		// viewed, but before the theme starts outputting any HTML - so
		// this is the right moment to check the page's content and, if
		// needed, register the dark-theme hooks below in time for the
		// header to already render dark.
		add_action( 'wp', array( __CLASS__, 'maybe_enable_dark_page_theme' ) );
	}

	/**
	 * Checks whether the page currently being viewed contains our
	 * shortcode anywhere in its content. If it does, this registers
	 * everything needed to darken the whole page (body class, inline
	 * CSS, and a logo-swap script) - scoped to just this one page.
	 */
	public static function maybe_enable_dark_page_theme() {

		if ( ! is_singular() ) {
			return;
		}

		global $post;

		if ( ! $post || ! has_shortcode( $post->post_content, 'bbb_builder' ) ) {
			return;
		}

		add_filter( 'body_class', array( __CLASS__, 'add_dark_page_body_class' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_dark_page_styles' ), 999 );
		add_action( 'wp_footer', array( __CLASS__, 'print_logo_swap_script' ) );
	}

	/**
	 * Adds a "bbb-dark-page" class to the <body> tag, which every
	 * dark-theme CSS rule below is scoped under. This is what keeps
	 * every other page on the site completely unaffected.
	 */
	public static function add_dark_page_body_class( $classes ) {

		$classes[] = 'bbb-dark-page';
		return $classes;
	}

	/**
	 * Prints the dark-theme CSS for the header, footer, and general
	 * page background - only applied where body.bbb-dark-page exists.
	 *
	 * These selectors target common Flatsome theme structure. If any
	 * element doesn't pick up the dark styling (a theme customisation
	 * can sometimes use different class names), we can refine the
	 * exact selectors after seeing it live.
	 */
	public static function print_dark_page_styles() {
		?>
		<style id="bbb-dark-page-theme">

			body.bbb-dark-page {
				background-color: #0d0d0d !important;
			}

			body.bbb-dark-page #wrapper,
			body.bbb-dark-page #main,
			body.bbb-dark-page .page-wrapper,
			body.bbb-dark-page .container {
				background-color: #0d0d0d !important;
			}

			/* Top announcement bar (e.g. "DUBAI CYCLING SHOP IN JUMEIRAH..."). */
			body.bbb-dark-page .top-bar,
			body.bbb-dark-page #top-bar {
				background-color: #1a1a1a !important;
				border-color: #262626 !important;
			}

			/* Main header bar and its navigation menu. */
			body.bbb-dark-page #header,
			body.bbb-dark-page .header-wrapper,
			body.bbb-dark-page #masthead {
				background-color: #141414 !important;
				border-color: #262626 !important;
			}

			body.bbb-dark-page #header a,
			body.bbb-dark-page .header-nav-main a,
			body.bbb-dark-page .nav > li > a,
			body.bbb-dark-page .nav-dropdown a {
				color: #e8e8e8 !important;
			}

			body.bbb-dark-page #header a:hover,
			body.bbb-dark-page .header-nav-main a:hover {
				color: #ffffff !important;
			}

			/* Dropdown submenus. */
			body.bbb-dark-page .nav-dropdown,
			body.bbb-dark-page .sub-menu {
				background-color: #1e1e1e !important;
				border-color: #333333 !important;
			}

			/* Header icons (search, cart, account). */
			body.bbb-dark-page #header .icon,
			body.bbb-dark-page #header svg {
				color: #e8e8e8 !important;
				fill: #e8e8e8 !important;
			}

			/* Footer. */
			body.bbb-dark-page #footer,
			body.bbb-dark-page .footer-wrapper,
			body.bbb-dark-page footer {
				background-color: #0d0d0d !important;
				color: #9aa5b1 !important;
				border-color: #262626 !important;
			}

			body.bbb-dark-page #footer a,
			body.bbb-dark-page footer a {
				color: #c7ccd1 !important;
			}

			body.bbb-dark-page #footer a:hover,
			body.bbb-dark-page footer a:hover {
				color: #ffffff !important;
			}

			body.bbb-dark-page #footer h1,
			body.bbb-dark-page #footer h2,
			body.bbb-dark-page #footer h3,
			body.bbb-dark-page #footer h4,
			body.bbb-dark-page footer h1,
			body.bbb-dark-page footer h2,
			body.bbb-dark-page footer h3,
			body.bbb-dark-page footer h4 {
				color: #ffffff !important;
			}

			/* Page title area, if the theme renders one above the content. */
			body.bbb-dark-page .page-title,
			body.bbb-dark-page .title-bar {
				background-color: #0d0d0d !important;
				color: #e8e8e8 !important;
			}

		</style>
		<?php
	}

	/**
	 * Swaps the site's normal logo image for the dark-theme version,
	 * only on pages using the [bbb_builder] shortcode. This runs in
	 * the footer (after the header has already loaded) and simply
	 * updates the src of whichever logo image element it finds -
	 * it does not affect the logo on any other page, since this
	 * script is only ever printed on pages where the shortcode exists.
	 */
	public static function print_logo_swap_script() {

		$dark_logo_url = esc_js( self::$dark_logo_url );
		?>
		<script>
		document.addEventListener( 'DOMContentLoaded', function () {

			var logoSelectors = [
				'#logo img',
				'.logo img',
				'.header-logo img',
				'#header .logo img'
			];

			logoSelectors.forEach( function ( selector ) {

				document.querySelectorAll( selector ).forEach( function ( logoImage ) {
					logoImage.src = '<?php echo $dark_logo_url; ?>';
				} );
			} );
		} );
		</script>
		<?php
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
			'1.7.0'
		);

		wp_enqueue_script(
			'bbb-builder-js',
			BBB_PLUGIN_URL . 'assets/js/builder.js',
			array(),
			'1.7.0',
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

		$total_option_steps = count( $groups );

		// These two values let our JavaScript securely talk to WordPress
		// in the background (via AJAX) when the customer submits their
		// build request. The nonce is a one-time security token tied to
		// this specific page load.
		$ajax_url = admin_url( 'admin-ajax.php' );
		$nonce    = wp_create_nonce( 'bbb_submit_build' );

		ob_start();
		?>

		<div class="bbb-builder-placeholder" data-total-option-steps="<?php echo esc_attr( $total_option_steps ); ?>" data-template-id="<?php echo esc_attr( $template['id'] ); ?>" data-ajax-url="<?php echo esc_attr( $ajax_url ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">

			<h2><?php echo esc_html( $template['name'] ); ?></h2>

			<p class="bbb-progress">Step 1 of <?php echo esc_html( $total_option_steps ); ?></p>

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

				<div class="bbb-step<?php echo $is_first_step ? ' bbb-step-active' : ''; ?>" data-step-index="<?php echo esc_attr( $index ); ?>" data-group-id="<?php echo esc_attr( $group['id'] ); ?>" data-group-label="<?php echo esc_attr( $group['label'] ); ?>">

					<h3><?php echo esc_html( $group['label'] ); ?></h3>

					<?php if ( 'dropdown' === $group['display_type'] ) : ?>

						<select class="bbb-dropdown">
							<option value="">Select an option</option>
							<?php foreach ( $options as $option ) : ?>
								<option value="<?php echo esc_attr( $option['id'] ); ?>" data-label="<?php echo esc_attr( $option['label'] ); ?>">
									<?php echo esc_html( $option['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>

					<?php else : ?>

						<div class="bbb-tile-group">
							<?php foreach ( $options as $option ) : ?>

								<?php
								$thumb_url = ! empty( $option['image_id'] )
									? wp_get_attachment_image_url( $option['image_id'], 'medium' )
									: '';
								?>

								<div class="bbb-tile<?php echo $thumb_url ? ' bbb-tile-with-image' : ''; ?>" data-option-id="<?php echo esc_attr( $option['id'] ); ?>" data-value="<?php echo esc_attr( $option['label'] ); ?>">

									<?php if ( $thumb_url ) : ?>
										<div class="bbb-tile-image" style="background-image:url('<?php echo esc_url( $thumb_url ); ?>');"></div>
									<?php endif; ?>

									<span class="bbb-tile-label"><?php echo esc_html( $option['label'] ); ?></span>

								</div>

							<?php endforeach; ?>
						</div>

					<?php endif; ?>

				</div>

			<?php endforeach; ?>

			<div class="bbb-step bbb-review-step" data-step-index="<?php echo esc_attr( $total_option_steps ); ?>">

				<h3>Review Your Build</h3>

				<div class="bbb-review-content"></div>

			</div>

			<div class="bbb-step bbb-lead-step" data-step-index="<?php echo esc_attr( $total_option_steps + 1 ); ?>">

				<h3>Your Details</h3>

				<div class="bbb-lead-form-fields">

					<div class="bbb-lead-field">
						<label for="bbb-lead-name">Full Name</label>
						<input type="text" id="bbb-lead-name" class="bbb-lead-input" placeholder="Your full name">
					</div>

					<div class="bbb-lead-field">
						<label for="bbb-lead-email">Email Address</label>
						<input type="email" id="bbb-lead-email" class="bbb-lead-input" placeholder="you@example.com">
					</div>

					<div class="bbb-lead-field">
						<label for="bbb-lead-phone">Phone Number</label>
						<input type="tel" id="bbb-lead-phone" class="bbb-lead-input" placeholder="+971 50 000 0000">
					</div>

					<div class="bbb-lead-field">
						<label for="bbb-lead-message">Additional Information or Remarks (optional)</label>
						<textarea id="bbb-lead-message" class="bbb-lead-input bbb-lead-textarea" rows="4" placeholder="Anything else we should know about your build?"></textarea>
					</div>

					<p class="bbb-lead-error" style="display:none;"></p>

				</div>

				<div class="bbb-success-message" style="display:none;"></div>

			</div>

			<div class="bbb-nav">
				<button type="button" class="bbb-back-button" style="display:none;">Back</button>
				<button type="button" class="bbb-next-button" disabled>Next</button>
			</div>

		</div>

		<?php
		return ob_get_clean();
	}
}
