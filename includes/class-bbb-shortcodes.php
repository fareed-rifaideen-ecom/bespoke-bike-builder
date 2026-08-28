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
 * As of Step 23, the HEADER shown on pages with [bbb_builder] can be
 * switched between two styles via Bespoke Bike Builder -> Header
 * Settings in wp-admin:
 * - "default_dark": the theme's own header/menu, simply recoloured
 *   dark, with the dark version of The Cycle Hub logo swapped in.
 * - "custom": our own two-row header (logos, then a nav bar),
 *   replacing the theme's header entirely.
 *
 * The FOOTER is never touched by this plugin at all - it always
 * renders exactly as the theme (or a page-specific footer assigned
 * through Flatsome's own page options) would normally show it.
 *
 * Navigation, the Review summary, the lead form validation, and the
 * actual save-to-database submission are all handled by
 * assets/js/builder.js together with includes/class-bbb-ajax.php.
 *
 * As of this update, the builder placeholder also carries a
 * data-compatibility-rules attribute: a JSON map of
 * "trigger_option_id" -> { target_group_id, allowed_option_ids: [...] },
 * built from whatever rules staff have configured on the Manage
 * Options screen (Blueprint Section 21). builder.js is expected to
 * read this attribute and hide/disable options in a target group
 * whenever a trigger option elsewhere is selected, and to restore
 * full availability whenever no rule for that group currently
 * applies. That enforcement logic lives in builder.js, which has not
 * been reviewed as part of this change - this only adds the data.
 *
 * As of this update, the wizard is also wrapped in a two-column
 * "Split Builder" layout per Blueprint Section 31 (Desktop: image
 * left, form right; Tablet: image banner, form below; Mobile:
 * compact single column). Each image-bearing tile now also carries
 * a data-image-url attribute so builder.js can update a live preview
 * image on the left/top panel as the customer makes selections. See
 * assets/css/builder.css for the breakpoint behaviour.
 *
 * As of this update, the image panel also includes a live "selected
 * summary" list directly beneath the preview image, showing every
 * option group the customer has answered so far (label + chosen
 * value), with a small thumbnail chip next to any selection that has
 * a photo. This is kept updated by builder.js in real time - it is
 * the same style of row used later on the Review step, just visible
 * throughout the whole build instead of only at the end.
 *
 * As of this update, assets/js/builder-thumbnail-gallery.js is also
 * enqueued. It is a fully additive, self-contained script (it
 * injects its own CSS and never edits builder.js or this file's
 * markup) that locks the main preview image to the customer's Frame
 * Colour selection, shows a temporary hover preview of any other
 * photo-bearing option when the mouse is over it (an option tile in
 * the current step, or a row in the selected-summary list), adds a
 * small disclaimer caption under the main image about Groupset and
 * Wheelset photos being for visual reference only, and opens a
 * full-screen magnified zoom overlay (panning with the cursor) when
 * the main image itself is clicked. See that file for details. Its
 * enqueued version number is bumped on every change to that file so
 * browsers and any page-caching layer fetch the latest copy instead
 * of serving a stale cached one.
 *
 * As of this update, whenever BBB_Pricing_Settings::is_enabled()
 * returns true (Settings > BBB Pricing, off by default), the wizard
 * container also carries a data-pricing-enabled="1" attribute, and
 * every option (tile or dropdown <option>) carries a data-price
 * attribute holding its already-existing price_delta value from the
 * database. Neither attribute is ever output while pricing is
 * disabled, so no price data reaches the customer's browser at all
 * unless an Administrator has deliberately turned this on. The
 * display logic that reads these attributes (running total + Review
 * breakdown) lives entirely in assets/js/builder-pricing.js, which is
 * itself only enqueued while pricing is enabled (see
 * bespoke-bike-builder.php).
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
exit; // Exit if accessed directly.
}

class BBB_Shortcodes {

/**
 * The dark-theme version of The Cycle Hub's logo, and the
 * Pinarello logo, both used by the header styles below.
 */
private static $tch_logo_url       = 'https://thecyclehub.com/wp-content/uploads/TCH-Logo-White-in-Black.jpg';
private static $pinarello_logo_url = 'https://thecyclehub.com/wp-content/uploads/Pinarello-Logo.png';

/**
 * Which header style is currently active for this page load -
 * either "default_dark" or "custom". Set once, in
 * maybe_enable_dark_page_theme(), from the bbb_header_mode option.
 */
private static $header_mode = 'custom';

/**
 * The simple navigation links used by the custom header style.
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
 * everything needed to apply the currently selected header style
 * - scoped to just this one page. The footer is never touched.
 */
public static function maybe_enable_dark_page_theme() {

if ( ! is_singular() ) {
return;
}

global $post;

if ( ! $post || ! has_shortcode( $post->post_content, 'bbb_builder' ) ) {
return;
}

self::$header_mode = get_option( 'bbb_header_mode', 'custom' );

add_filter( 'body_class', array( __CLASS__, 'add_dark_page_body_class' ) );
add_action( 'wp_head', array( __CLASS__, 'print_dark_page_styles' ), 999 );

if ( 'custom' === self::$header_mode ) {

// 'wp_body_open' fires immediately after the opening <body>
// tag - the standard WordPress hook for printing markup at
// the very top of the page, before the theme's own header.
add_action( 'wp_body_open', array( __CLASS__, 'print_custom_header' ) );

} else {

// In "default_dark" mode, the theme's own header stays and
// is simply recoloured via CSS; we still need to swap its
// logo image, which a small script in the footer handles
// (running after the header has already rendered).
add_action( 'wp_footer', array( __CLASS__, 'print_logo_swap_script' ) );
}
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
 * Prints the dark-theme CSS for this page. The page background
 * is always darkened; the header rules differ depending on
 * whether "default_dark" or "custom" mode is active. The footer
 * is never touched by any of this.
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

<?php if ( 'custom' === self::$header_mode ) : ?>

/* Custom header mode: hide the theme's own header/top-bar
   entirely (the footer is deliberately left alone). */
body.bbb-dark-page #header,
body.bbb-dark-page .header-wrapper,
body.bbb-dark-page #masthead,
body.bbb-dark-page .top-bar,
body.bbb-dark-page #top-bar {
display: none !important;
}

/* Our own replacement header: a logo row above a nav bar. */
.bbb-custom-header {
background-color: #0d0d0d;
}

.bbb-custom-header-logos {
display: flex;
align-items: center;
justify-content: center;
gap: 20px;
padding: 20px;
background-color: #141414;
}

.bbb-custom-logo {
height: 44px;
width: auto;
display: block;
}

.bbb-custom-logo-divider {
color: #555555;
font-size: 22px;
line-height: 1;
}

.bbb-custom-header-nav {
display: flex;
align-items: center;
justify-content: center;
gap: 32px;
flex-wrap: wrap;
background-color: #000000;
padding: 14px 20px;
}

.bbb-custom-header-nav a {
color: #e8e8e8;
text-decoration: none;
font-size: 13px;
font-weight: bold;
text-transform: uppercase;
letter-spacing: 0.06em;
}

.bbb-custom-header-nav a:hover {
color: #ffffff;
}

<?php else : ?>

/* Default dark mode: keep the theme's own header/menu, but
   recolour it dark instead of hiding it. */
body.bbb-dark-page .top-bar,
body.bbb-dark-page #top-bar {
background-color: #1a1a1a !important;
border-color: #262626 !important;
}

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

body.bbb-dark-page .nav-dropdown,
body.bbb-dark-page .sub-menu {
background-color: #1e1e1e !important;
border-color: #333333 !important;
}

body.bbb-dark-page #header .icon,
body.bbb-dark-page #header svg {
color: #e8e8e8 !important;
fill: #e8e8e8 !important;
}

<?php endif; ?>

</style>
<?php
}

/**
 * Prints our custom two-row header (logo row + nav bar), used
 * only when "custom" header mode is selected.
 */
public static function print_custom_header() {
?>
<header class="bbb-custom-header">

<div class="bbb-custom-header-logos">
<a href="https://thecyclehub.com/">
<img src="<?php echo esc_url( self::$tch_logo_url ); ?>" alt="The Cycle Hub" class="bbb-custom-logo">
</a>
<span class="bbb-custom-logo-divider">&times;</span>
<img src="<?php echo esc_url( self::$pinarello_logo_url ); ?>" alt="Pinarello" class="bbb-custom-logo">
</div>

<nav class="bbb-custom-header-nav">
<?php foreach ( self::get_nav_links() as $label => $url ) : ?>
<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
<?php endforeach; ?>
</nav>

</header>
<?php
}

/**
 * Swaps the site's normal logo image for the dark-theme version,
 * used only when "default_dark" header mode is selected (the
 * theme's own header stays, but needs its logo replaced).
 */
public static function print_logo_swap_script() {

$dark_logo_url = esc_js( self::$tch_logo_url );
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
'1.9.0'
);

wp_enqueue_script(
'bbb-builder-js',
BBB_PLUGIN_URL . 'assets/js/builder.js',
array(),
'1.9.0',
true
);

// Additive-only script for the Frame Colour hero lock + hover
// preview + click-to-zoom (Blueprint follow-up). It injects its
// own CSS and never touches builder.js or the markup above, so it
// is safe to load independently. IMPORTANT: bump this version
// string every time builder-thumbnail-gallery.js changes, so
// browsers and any page cache fetch the new file instead of
// reusing a stale cached copy.
wp_enqueue_script(
'bbb-thumbnail-gallery',
BBB_PLUGIN_URL . 'assets/js/builder-thumbnail-gallery.js',
array(),
'5.0.1',
true
);

$already_loaded = true;
}

/**
 * Builds a JSON-ready array describing every compatibility rule
 * currently configured (Blueprint Section 21), for the given
 * template's option groups. Shape:
 *
 *   {
 *     "<trigger_option_id>": {
 *       "target_group_id": <int>,
 *       "allowed_option_ids": [<int>, <int>, ...]
 *     },
 *     ...
 *   }
 *
 * If no rules have been configured anywhere, this returns an
 * empty array/object, so existing behaviour (every active option
 * always available) is completely unaffected until an
 * Administrator actually creates a rule.
 *
 * @param array $group_ids IDs of the option groups belonging to this template.
 * @return array
 */
private static function get_compatibility_rules_map( $group_ids ) {

if ( empty( $group_ids ) ) {
return array();
}

global $wpdb;

$rules_table = $wpdb->prefix . 'bbb_compatibility_rules';

$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rules_table ) ) === $rules_table;

if ( ! $table_exists ) {
return array();
}

$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );

$rows = $wpdb->get_results(
$wpdb->prepare(
"SELECT trigger_option_id, target_group_id, allowed_option_id FROM {$rules_table} WHERE target_group_id IN ({$placeholders})",
$group_ids
),
ARRAY_A
);

$map = array();

foreach ( $rows as $row ) {

$trigger_id = (string) $row['trigger_option_id'];

if ( ! isset( $map[ $trigger_id ] ) ) {
$map[ $trigger_id ] = array(
'target_group_id'    => (int) $row['target_group_id'],
'allowed_option_ids' => array(),
);
}

$map[ $trigger_id ]['allowed_option_ids'][] = (int) $row['allowed_option_id'];
}

return $map;
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

// Whether estimated pricing should be output to the page at all
// (Settings > BBB Pricing, off by default). Guarded with
// class_exists() so this file never fatals even if, for some
// reason, class-bbb-pricing-settings.php failed to load.
$pricing_enabled = class_exists( 'BBB_Pricing_Settings' ) && BBB_Pricing_Settings::is_enabled();

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

$group_ids           = wp_list_pluck( $groups, 'id' );
$compatibility_rules = self::get_compatibility_rules_map( $group_ids );

ob_start();
?>

<div class="bbb-builder-placeholder" data-total-option-steps="<?php echo esc_attr( $total_option_steps ); ?>" data-template-id="<?php echo esc_attr( $template['id'] ); ?>" data-ajax-url="<?php echo esc_attr( $ajax_url ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-compatibility-rules="<?php echo esc_attr( wp_json_encode( $compatibility_rules ) ); ?>"<?php echo $pricing_enabled ? ' data-pricing-enabled="1"' : ''; ?>>

<div class="bbb-builder-columns">

<div class="bbb-builder-image-panel">
<div class="bbb-selected-image bbb-image-panel--empty">
<span class="bbb-image-panel-placeholder">Select options to preview your build</span>
</div>
<div class="bbb-selected-summary"></div>
</div>

<div class="bbb-builder-form-panel">

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
<option value="<?php echo esc_attr( $option['id'] ); ?>" data-label="<?php echo esc_attr( $option['label'] ); ?>"<?php echo $pricing_enabled ? ' data-price="' . esc_attr( $option['price_delta'] ) . '"' : ''; ?>>
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

$preview_url = ! empty( $option['image_id'] )
? wp_get_attachment_image_url( $option['image_id'], 'large' )
: '';
?>

<div class="bbb-tile<?php echo $thumb_url ? ' bbb-tile-with-image' : ''; ?>" data-option-id="<?php echo esc_attr( $option['id'] ); ?>" data-value="<?php echo esc_attr( $option['label'] ); ?>" data-image-url="<?php echo esc_url( $preview_url ); ?>"<?php echo $pricing_enabled ? ' data-price="' . esc_attr( $option['price_delta'] ) . '"' : ''; ?>>

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

</div>

</div>

<?php
return ob_get_clean();
}
}
