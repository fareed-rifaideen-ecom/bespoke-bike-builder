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
 * As of Step 22, pages containing the [bbb_builder] shortcode
 * completely hide the theme's own header and footer (whose UX
 * Builder footer block proved unreliable to recolour from the
 * outside) and replace them with a small custom dark header (The
 * Cycle Hub logo + Pinarello logo, plus simple navigation back to
 * the main site) and a matching simple dark footer. This is
 * detected automatically via has_shortcode(), so it works on any
 * page the shortcode is placed on, now or in the future.
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
	 * The dark-theme version of The Cycle Hub's logo, and the
	 * Pinarello logo, both shown side by side in our custom header.
	 */
	private static $tch_logo_url       = 'https://thecyclehub.com/wp-content/uploads/TCH-Logo-White-in-Black.jpg';
	private static $pinarello_logo_url = 'https://thecyclehub.com/wp-content/uploads/Pinarello-Logo.png';

	/**
	 * The simple navigation links shown in both our custom header and
	 * our custom footer, so customers can always get back to the main
	 * site without feeling stuck on this page.
	 */
	private static function get_nav_links() {

		return array(
			'Home'          => 'https://thecyclehub.com/',
			'Online Shop'   => 'https://thecyclehub.com/product-category/online-shop/',
			'Contact'       => 'https://thecyclehub.com/contact/',
			'Cycling Blogs' => 'https://thecyclehub.com/the-cycle-hub-blogs/',
		);
	}

	/**
	 * This function "switches on" the shortcode feature.
	 * It is called once, from the main plugin file.
	 */
	public static function init() {

		add_shortcode( 'bbb_builder', array( __CLASS__, 'render_builder' ) );

		// 'wp' fires once WordPress has figured out which page is being
		// viewed, but before the theme starts outputting any HTML - so
		// this is the right moment to check the page's content and, if
		// needed, register the dark-theme hooks below in time.
		add_action( 'wp', array( __CLASS__, 'maybe_enable_dark_page_theme' ) );
	}

	/**
	 * Checks whether the page currently being viewed contains our
	 * shortcode anywhere in its content. If it does, this registers
	 * everything needed to hide the theme's header/footer and print
	 * our own dark, minimal replacements instead - scoped to just
	 * this one page.
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

		// 'wp_body_open' fires immediately after the opening <body> tag -
		// this is the standard WordPress hook for printing markup right
		// at the very top of the page, before the theme's own header.
		add_action( 'wp_body_open', array( __CLASS__, 'print_custom_header' ) );

		// A low priority (5) here just means our footer prints early
		// among everything else hooked to wp_footer, though the exact
		// order doesn't matter much since it's the last visible thing
		// on the page either way.
		add_action( 'wp_footer', array( __CLASS__, 'print_custom_footer' ), 5 );
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
	 * Prints the dark-theme CSS for this page: it hides the theme's
	 * own header/footer entirely, sets the page background dark, and
	 * styles our own custom header/footer markup (printed separately
	 * by print_custom_header() and print_custom_footer() below).
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

			/*
			 * Hide the theme's own header and footer entirely. Rather
			 * than fighting the UX Builder footer block's inline
			 * background colours forever, we simply replace both with
			 * our own minimal dark versions further down this file.
			 */
			body.bbb-dark-page #header,
			body.bbb-dark-page .header-wrapper,
			body.bbb-dark-page #masthead,
			body.bbb-dark-page .top-bar,
			body.bbb-dark-page #top-bar,
			body.bbb-dark-page #footer,
			body.bbb-dark-page .footer-wrapper {
				display: none !important;
			}

			body.bbb-dark-page footer:not(.bbb-dark-footer) {
				display: none !important;
			}

			/* Our own replacement header. */
			.bbb-dark-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				flex-wrap: wrap;
				gap: 16px;
				background-color: #141414;
				padding: 18px 32px;
				border-bottom: 1px solid #262626;
			}

			.bbb-dark-header-logos {
				display: flex;
				align-items: center;
				gap: 16px;
			}

			.bbb-dark-logo {
				height: 40px;
				width: auto;
				display: block;
			}

			.bbb-dark-logo-divider {
				color: #555555;
				font-size: 20px;
				line-height: 1;
			}

			.bbb-dark-nav {
				display: flex;
				gap: 24px;
				flex-wrap: wrap;
			}

			.bbb-dark-nav a {
				color: #e8e8e8;
				text-decoration: none;
				font-size: 13px;
				font-weight: bold;
				text-transform: uppercase;
				letter-spacing: 0.05em;
			}

			.bbb-dark-nav a:hover {
				color: #ffffff;
			}

			/* Our own replacement footer. */
			.bbb-dark-footer {
				background-color: #0d0d0d;
				border-top: 1px solid #262626;
				padding: 24px 32px;
				text-align: center;
				color: #9aa5b1;
				font-size: 13px;
			}

			.bbb-dark-footer-nav {
				display: flex;
				justify-content: center;
				gap: 20px;
				flex-wrap: wrap;
				margin-bottom: 10px;
			}

			.bbb-dark-footer-nav a,
			.bbb-dark-footer a {
				color: #c7ccd1;
				text-decoration: none;
			}

			.bbb-dark-footer-nav a:hover,
			.bbb-dark-footer a:hover {
				color: #ffffff;
			}

			@media (max-width: 600px) {
				.bbb-dark-header {
					justify-content: center;
					text-align: center;
				}
			}

		</style>
		<?php
	}

	/**
	 * Prints our own minimal dark header, right at the top of the
	 * page (immediately after the opening <body> tag), replacing the
	 * theme's own header which is hidden by the CSS above.
	 */
	public static function print_custom_header() {
		?>
		<header class="bbb-dark-header">

			<div class="bbb-dark-header-logos">
				<a href="https://thecyclehub.com/">
					<img src="<?php echo esc_url( self::$tch_logo_url ); ?>" alt="The Cycle Hub" class="bbb-dark-logo">
				</a>
				<span class="bbb-dark-logo-divider">&times;</span>
				<img src="<?php echo esc_url( self::$pinarello_logo_url ); ?>" alt="Pinarello" class="bbb-dark-logo">
			</div>

			<nav class="bbb-dark-nav">
				<?php foreach ( self::get_nav_links() as $label => $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

		</header>
		<?php
	}

	/**
	 * Prints our own minimal dark footer, replacing the theme's own
	 * UX Builder footer block which is hidden by the CSS above.
	 */
	public static function print_custom_footer() {
		?>
		<footer class="bbb-dark-footer">

			<nav class="bbb-dark-footer-nav">
				<?php foreach ( self::get_nav_links() as $label => $url ) : ?>
					<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> The Cycle Hub. All rights reserved.</p>

		</footer>
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
