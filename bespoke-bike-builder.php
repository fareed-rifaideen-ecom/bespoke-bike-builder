<?php
/**
 * Plugin Name:       Bespoke Bike Builder
 * Plugin URI:        https://github.com/fareed-rifaideen-ecom/bespoke-bike-builder
 * Description:       A custom bicycle build request platform for The Cycle Hub, starting with the Pinarello Dogma F custom build experience.
 * Version:           1.0.0
 * Author:            Fareed M. Rifaideen
 * Author URI:        https://fareed-rifaideen.netlify.app/
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        bespoke-bike-builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BBB_PLUGIN_FILE', __FILE__ );
define( 'BBB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once BBB_PLUGIN_DIR . 'includes/class-bbb-activator.php';
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-admin.php';
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-shortcodes.php';
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-ajax.php';
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-manage-options.php';
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-header-settings.php';
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-notices-settings.php';
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-draft-resume.php';

// This loads the Staff Roles & Capabilities system: registers the
// Custom Build Manager / Sales Staff / Option Manager roles defined
// in the blueprint, plus their bbb_ capabilities. Self-contained —
// does not modify any existing class's permission checks.
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-roles.php';

// This loads the small admin screen that shows role setup status
// and which staff members currently hold each role.
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-roles-admin-page.php';

register_activation_hook( __FILE__, array( 'BBB_Activator', 'activate' ) );
add_action( 'admin_init', array( 'BBB_Activator', 'maybe_upgrade' ) );

BBB_Admin::init();
BBB_Shortcodes::init();
BBB_Ajax::init();
BBB_Manage_Options::init();
BBB_Header_Settings::init();
BBB_Notices_Settings::init();
BBB_Draft_Resume::init();

// This starts up the Staff Roles system and its admin page.
BBB_Roles::init();
BBB_Roles_Admin_Page::init();

// This loads the Group 1 responsive layer (progress bar, breakpoint tile
// columns, sticky mobile nav, expandable summary, Cockpit width/stem split,
// touch-target sizing) for the customer-facing Dogma F builder. It runs
// as its own wp_enqueue_scripts action at priority 20, so it always loads
// after the shortcode class's own builder.css/builder.js enqueue (which
// runs at the default priority 10). It only enqueues anything if the
// responsive files actually exist on disk, so it can never cause a fatal
// error even if those files are ever removed.
add_action( 'wp_enqueue_scripts', function () {
	$css_path = BBB_PLUGIN_DIR . 'assets/css/builder-responsive.css';
	$js_path  = BBB_PLUGIN_DIR . 'assets/js/builder-responsive.js';

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'bbb-builder-responsive',
			BBB_PLUGIN_URL . 'assets/css/builder-responsive.css',
			array(),
			filemtime( $css_path )
		);
	}

	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'bbb-builder-responsive',
			BBB_PLUGIN_URL . 'assets/js/builder-responsive.js',
			array(),
			filemtime( $js_path ),
			true
		);
	}
}, 20 );

// This loads the Notices & WhatsApp customer-facing layer (disclaimer
// banners, Frame Size WhatsApp help link, and the Notes-step agreement
// checkbox). Runs at priority 21, after the responsive layer above, and
// only enqueues if the files exist on disk. The disclaimer text,
// checkbox text and WhatsApp number/messages are passed from PHP to
// JavaScript via wp_localize_script, reading live from the "BBB
// Notices" admin settings page rather than being hardcoded.
add_action( 'wp_enqueue_scripts', function () {
	$css_path = BBB_PLUGIN_DIR . 'assets/css/builder-notices.css';
	$js_path  = BBB_PLUGIN_DIR . 'assets/js/builder-notices.js';

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'bbb-builder-notices',
			BBB_PLUGIN_URL . 'assets/css/builder-notices.css',
			array(),
			filemtime( $css_path )
		);
	}

	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'bbb-builder-notices',
			BBB_PLUGIN_URL . 'assets/js/builder-notices.js',
			array(),
			filemtime( $js_path ),
			true
		);

		wp_localize_script( 'bbb-builder-notices', 'bbbNotices', array(
			'whatsappNumber'      => BBB_Notices_Settings::get_whatsapp_number(),
			'disclaimerText'      => BBB_Notices_Settings::get_disclaimer_text(),
			'checkboxText'        => BBB_Notices_Settings::get_checkbox_text(),
			'whatsappMessage'     => BBB_Notices_Settings::get_whatsapp_message(),
			'whatsappSizeMessage' => BBB_Notices_Settings::get_whatsapp_size_message(),
		) );
	}
}, 21 );

// This loads the Draft & Resume customer-facing layer (auto-save,
// "Save & continue later" prompt, and auto-resume from an emailed
// link). Runs at priority 22, after the two layers above. The AJAX
// URL and a dedicated nonce are passed to JavaScript via
// wp_localize_script so all save/resume requests are authenticated
// the same way any other WordPress AJAX call would be.
add_action( 'wp_enqueue_scripts', function () {
	$css_path = BBB_PLUGIN_DIR . 'assets/css/builder-draft-resume.css';
	$js_path  = BBB_PLUGIN_DIR . 'assets/js/builder-draft-resume.js';

	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'bbb-builder-draft-resume',
			BBB_PLUGIN_URL . 'assets/css/builder-draft-resume.css',
			array(),
			filemtime( $css_path )
		);
	}

	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'bbb-builder-draft-resume',
			BBB_PLUGIN_URL . 'assets/js/builder-draft-resume.js',
			array(),
			filemtime( $js_path ),
			true
		);

		wp_localize_script( 'bbb-builder-draft-resume', 'bbbDraftResume', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'bbb_draft_resume_nonce' ),
		) );
	}
}, 22 );
