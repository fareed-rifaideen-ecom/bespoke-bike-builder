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

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// A constant is a value that never changes while the plugin runs.
// __FILE__ is a built-in PHP constant that always points to the current file's full path.
// We save it here so other files in the plugin can find their way back to this main file.
define( 'BBB_PLUGIN_FILE', __FILE__ );

// This tells PHP where to find our other plugin files, so we can "require" them below.
define( 'BBB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// This gives us the public web address of our plugin folder, e.g.
// https://thecyclehub.com/wp-content/plugins/bespoke-bike-builder/
// We need this to correctly load our CSS and JavaScript files in the browser.
define( 'BBB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// This loads the file that contains our Activator class (the code that runs on activation).
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-activator.php';

// This loads the file that adds our admin menu page.
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-admin.php';

// This loads the file that registers our [bbb_builder] shortcode.
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-shortcodes.php';

// This loads the file that handles saving build submissions via AJAX.
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-ajax.php';

// This is the actual activation hook.
// It tells WordPress: "When this plugin is activated, run BBB_Activator::activate()."
register_activation_hook( __FILE__, array( 'BBB_Activator', 'activate' ) );

// This starts up the admin menu page by calling its init() method.
BBB_Admin::init();

// This starts up the shortcode feature by calling its init() method.
BBB_Shortcodes::init();

// This starts up the AJAX submission handler by calling its init() method.
BBB_Ajax::init();
