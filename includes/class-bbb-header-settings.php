<?php
/**
 * Admin screen letting staff choose how the header should appear on
 * the customer-facing build page (any page containing the
 * [bbb_builder] shortcode).
 *
 * Two options are available:
 * - "default_dark": keeps the theme's own header, menu, and
 *   dropdowns exactly as they are, just recoloured dark, with the
 *   site's normal logo swapped for the dark-theme version.
 * - "custom": hides the theme's header entirely and shows our own
 *   two-row header instead - a logo row (The Cycle Hub x Pinarello)
 *   above a simple navigation bar.
 *
 * The footer is never touched by either option - it always renders
 * exactly as the theme (or a page-specific footer set up separately
 * in Flatsome's own page options) would normally show it.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BBB_Header_Settings {

	/**
	 * This function "switches on" the Header Settings screen.
	 * It is called once, from the main plugin file.
	 */
	public static function init() {

		add_action( 'admin_menu', array( __CLASS__, 'register_menu_page' ) );
		add_action( 'admin_post_bbb_save_header_mode', array( __CLASS__, 'handle_save' ) );
	}

	/**
	 * Adds "Header Settings" as a submenu page under the main
	 * Bespoke Bike Builder menu.
	 */
	public static function register_menu_page() {

		add_submenu_page(
			'bbb-dashboard',
			'Header Settings',
			'Header Settings',
			'manage_options',
			'bbb-header-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Displays the two header style options as radio buttons, saving
	 * the choice into a single WordPress option (bbb_header_mode).
	 */
	public static function render_page() {

		$current_mode = get_option( 'bbb_header_mode', 'custom' );
		?>
		<div class="wrap">
			<h1>Header Settings</h1>
			<p>Choose how the header should appear on the customer build page. This only affects pages containing the <code>[bbb_builder]</code> shortcode - every other page on the site is unaffected. The footer is never changed by this setting.</p>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Header setting saved.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

				<input type="hidden" name="action" value="bbb_save_header_mode">
				<?php wp_nonce_field( 'bbb_save_header_mode' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">Header Style</th>
						<td>
							<p>
								<label>
									<input type="radio" name="header_mode" value="default_dark" <?php checked( $current_mode, 'default_dark' ); ?>>
									<strong>Default Header (Dark Themed)</strong> - keeps the site's normal menu and navigation, just recoloured dark, with the dark version of The Cycle Hub logo.
								</label>
							</p>
							<p>
								<label>
									<input type="radio" name="header_mode" value="custom" <?php checked( $current_mode, 'custom' ); ?>>
									<strong>Custom Header</strong> - a dedicated dark header showing The Cycle Hub and Pinarello logos side by side, above a simple navigation bar (Home, Online Shop, Contact, Cycling Blogs).
								</label>
							</p>
						</td>
					</tr>
				</table>

				<button type="submit" class="button button-primary">Save Changes</button>

			</form>
		</div>
		<?php
	}

	/**
	 * Saves the chosen header mode.
	 */
	public static function handle_save() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do this.' );
		}

		check_admin_referer( 'bbb_save_header_mode' );

		$mode = isset( $_POST['header_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['header_mode'] ) ) : 'custom';

		if ( ! in_array( $mode, array( 'default_dark', 'custom' ), true ) ) {
			$mode = 'custom';
		}

		update_option( 'bbb_header_mode', $mode );

		wp_safe_redirect( admin_url( 'admin.php?page=bbb-header-settings&saved=1' ) );
		exit;
	}
}
