<?php
/**
 * Bespoke Bike Builder - Pricing Settings
 *
 * Adds a "Pricing" admin page with a single control: an
 * Administrator-editable on/off toggle for whether estimated prices
 * (built from each option's existing price_delta field, already
 * saved from the Manage Options screen) are shown to customers on
 * the public builder.
 *
 * This is intentionally its own small, self-contained settings class
 * - modeled on the existing BBB_Header_Settings / BBB_Notices_Settings
 * pattern - so it never needs to touch class-bbb-manage-options.php
 * or class-bbb-activator.php. Pricing data itself (price_delta per
 * option) already exists in the wp_bbb_options table and is already
 * editable today; this class only controls whether that data is
 * ever shown on the customer-facing page.
 *
 * Off by default, so nothing about the customer experience changes
 * until an Administrator deliberately turns this on.
 *
 * As of this update, this settings page lives under the main
 * "Bespoke Bike Builder" admin menu instead of under Settings, so
 * every plugin screen is found in one place.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
exit; // Exit if accessed directly.
}

class BBB_Pricing_Settings {

/**
 * The option name this setting is stored under.
 */
const OPTION_NAME = 'bbb_pricing_enabled';

/**
 * This function "switches on" the settings page.
 * It is called once, from the main plugin file.
 */
public static function init() {
add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
}

/**
 * Registers the "BBB Pricing" page as a submenu under the main
 * "Bespoke Bike Builder" admin menu, visible only to Administrators.
 */
public static function add_settings_page() {
add_submenu_page( 'bbb-dashboard', 'BBB Pricing', 'Pricing', 'manage_options', 'bbb-pricing-settings', array( __CLASS__, 'render_settings_page' ) );
}

/**
 * Registers the single checkbox setting with WordPress's Settings
 * API, so it gets saved, sanitised and nonce-protected the standard
 * way without any custom form-handling code in this class.
 */
public static function register_setting() {
register_setting(
'bbb_pricing_settings_group',
self::OPTION_NAME,
array(
'type'              => 'boolean',
'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
'default'           => false,
)
);
}

/**
 * Converts whatever the checkbox posts (or doesn't post, if left
 * unticked) into a clean true/false value.
 */
public static function sanitize_checkbox( $value ) {
return ! empty( $value );
}

/**
 * Whether pricing should currently be shown to customers. Used by
 * class-bbb-shortcodes.php (to decide whether to output price data
 * at all) and class-bbb-ajax.php (to decide whether to calculate
 * and email a total). Defaults to false/off if the setting has
 * never been saved.
 *
 * @return bool
 */
public static function is_enabled() {
return (bool) get_option( self::OPTION_NAME, false );
}

/**
 * Renders the actual "BBB Pricing" admin page.
 */
public static function render_settings_page() {

if ( ! current_user_can( 'manage_options' ) ) {
return;
}
?>
<div class="wrap">
<h1>BBB Pricing</h1>
<p>Controls whether customers see estimated prices while building on the public Dogma F builder. Prices themselves are set per option on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbb-manage-options' ) ); ?>">Manage Options</a> screen (Price Delta field) - this page only controls whether that data is ever displayed.</p>
<form method="post" action="options.php">
<?php settings_fields( 'bbb_pricing_settings_group' ); ?>
<table class="form-table" role="presentation">
<tr>
<th scope="row">Show estimated pricing to customers</th>
<td>
<label>
<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>" value="1" <?php checked( self::is_enabled() ); ?> />
Show a running estimated total in the builder and a price breakdown on the Review step.
</label>
<p class="description">When off (the default), no price is ever shown or sent to the customer's browser, even if options already have a Price Delta saved.</p>
</td>
</tr>
</table>
<?php submit_button( 'Save Changes' ); ?>
</form>
</div>
<?php
}
}
