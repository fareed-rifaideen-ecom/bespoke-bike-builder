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
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-roles.php';
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-roles-admin-page.php';

// This loads the Staff Frontend Portal: a [bbb_staff_portal] shortcode
// that Administrators place on any normal WordPress page. Staff log
// in there directly (via wp_signon(), the same core authentication as
// wp-login.php) and never need to visit /wp-admin/ at all. Access is
// gated by the manage_bbb_submissions / manage_options capabilities.
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-staff-portal.php';

// This loads the Submission Event Log: a listener that records an
// audit-trail row every time a build request is submitted or its
// status changes, by listening for the bbb_submission_created and
// bbb_submission_status_updated actions fired from BBB_Ajax and
// BBB_Staff_Portal respectively. It does not hook into anything
// itself unless BBB_Event_Log::init() is called below.
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-event-log.php';

// This loads the Notification Recipient Settings: a Settings > BBB
// Notifications admin page where an Administrator can set which
// email addresses get notified of new build requests (the Sales
// Manager and any additional staff), instead of that address being
// hardcoded into class-bbb-ajax.php.
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-notification-settings.php';

// This loads the Pricing Settings: a Settings > BBB Pricing admin
// page with a single on/off toggle for whether estimated prices
// (built from each option's existing price_delta field, already
// editable from Manage Options) are ever shown to customers. Off by
// default, so nothing about the customer experience changes until an
// Administrator deliberately turns this on. class-bbb-shortcodes.php
// and class-bbb-ajax.php both check BBB_Pricing_Settings::is_enabled()
// before doing anything price-related.
require_once BBB_PLUGIN_DIR . 'includes/class-bbb-pricing-settings.php';

register_activation_hook( __FILE__, array( 'BBB_Activator', 'activate' ) );
add_action( 'admin_init', array( 'BBB_Activator', 'maybe_upgrade' ) );

BBB_Admin::init();
BBB_Shortcodes::init();
BBB_Ajax::init();
BBB_Manage_Options::init();
BBB_Header_Settings::init();
BBB_Notices_Settings::init();
BBB_Draft_Resume::init();
BBB_Roles::init();
BBB_Roles_Admin_Page::init();

// This starts up the Staff Frontend Portal shortcode and its AJAX
// handlers (login, logout, status updates).
BBB_Staff_Portal::init();

// This starts up the Submission Event Log listener.
BBB_Event_Log::init();

// This starts up the Notification Recipient Settings page.
BBB_Notification_Settings::init();

// This starts up the Pricing Settings page.
BBB_Pricing_Settings::init();

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

// This loads the Pricing customer-facing layer (running estimated
// total in the left column + price breakdown on the Review step).
// Runs at priority 23, after all three layers above, and only
// enqueues if the file exists on disk AND pricing is currently
// enabled via Settings > BBB Pricing - so this has zero effect on
// the page at all while pricing is off (the default).
add_action( 'wp_enqueue_scripts', function () {

if ( ! class_exists( 'BBB_Pricing_Settings' ) || ! BBB_Pricing_Settings::is_enabled() ) {
return;
}

$js_path = BBB_PLUGIN_DIR . 'assets/js/builder-pricing.js';

if ( file_exists( $js_path ) ) {
wp_enqueue_script(
'bbb-builder-pricing',
BBB_PLUGIN_URL . 'assets/js/builder-pricing.js',
array(),
filemtime( $js_path ),
true
);
}
}, 23 );
