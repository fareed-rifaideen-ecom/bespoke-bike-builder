<?php
/**
 * Handles the wp-admin menu pages for Bespoke Bike Builder.
 *
 * This file adds two pages:
 * - "Bespoke Bike Builder" - the original page confirming our build
 *   templates data exists (from Step 7).
 * - "Build Requests" - a real working list of every customer build
 *   submission, where staff can review the full build spec, any
 *   optional Remarks the customer left, and update each request's
 *   status.
 *
 * The top-level menu icon is a bicycle silhouette (a single black
 * fill path, provided as Bespoke-Bike-Builder-Icon.svg) embedded as
 * a base64 data URI. WordPress renders menu icons as a single-color
 * mask, so it recolors this automatically to match the admin colour
 * scheme and the hover/active states, exactly like a built-in
 * Dashicon - no separate image file or enqueue is needed.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
exit; // Exit if accessed directly.
}

class BBB_Admin {

/**
 * The list of valid statuses a build request can move through.
 */
private static $statuses = array( 'new', 'contacted', 'confirmed', 'completed' );

/**
 * This function "switches on" the admin pages feature.
 * It is called once, from the main plugin file.
 */
public static function init() {

add_action( 'admin_menu', array( __CLASS__, 'register_menu_pages' ) );

add_action( 'admin_post_bbb_update_status', array( __CLASS__, 'handle_update_status' ) );
}

/**
 * Builds the base64-encoded bicycle silhouette used as the menu
 * icon (Bespoke-Bike-Builder-Icon.svg - a single black fill path),
 * so register_menu_pages() below stays easy to read.
 *
 * @return string A data: URI ready to pass straight to add_menu_page().
 */
private static function get_menu_icon() {

$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="2000" zoomAndPan="magnify" viewBox="0 0 1500 1499.999933" height="2000" preserveAspectRatio="xMidYMid meet" version="1.0"><defs><clipPath id="8d2a2297c2"><path d="M 51.164062 306.238281 L 1449 306.238281 L 1449 1193.488281 L 51.164062 1193.488281 Z M 51.164062 306.238281 " clip-rule="nonzero"/></clipPath><clipPath id="6221441e20"><path d="M 0.164062 1.054688 L 1397.824219 1.054688 L 1397.824219 886.675781 L 0.164062 886.675781 Z M 0.164062 1.054688 " clip-rule="nonzero"/></clipPath><clipPath id="863c8cb9b9"><rect x="0" width="1398" y="0" height="888"/></clipPath></defs><g clip-path="url(#8d2a2297c2)"><g transform="matrix(1, 0, 0, 1, 51, 306)"><g clip-path="url(#863c8cb9b9)"><g clip-path="url(#6221441e20)"><path fill="#000100" d="M 1010.390625 261.117188 C 893.25 369.375 776.261719 478.019531 658.296875 585.328125 C 645.289062 578.496094 630.484375 574.625 614.765625 574.625 C 601.5 574.625 588.882812 577.386719 577.445312 582.347656 C 572.65625 519.835938 568.921875 457.089844 565.089844 394.539062 C 563.578125 369.828125 562.09375 345.128906 560.609375 320.40625 C 573.199219 318.824219 585.777344 317.171875 598.355469 315.535156 C 721.003906 299.578125 843.691406 283.75 966.257812 267.191406 C 980.972656 265.203125 995.6875 263.207031 1010.386719 261.125 Z M 521.214844 662.675781 C 511.484375 661.789062 501.203125 660.824219 490.566406 659.804688 C 480.605469 711.550781 458.109375 760.246094 423.742188 799.8125 C 378.359375 852.0625 316.089844 883.585938 248.109375 883.585938 C 180.125 883.585938 117.855469 852.0625 72.472656 799.8125 C 24.59375 744.691406 -0.246094 671.851562 -0.246094 597.710938 C -0.246094 523.566406 24.59375 450.730469 72.472656 395.613281 C 117.859375 343.355469 180.125 311.851562 248.109375 311.851562 C 302.722656 311.851562 354.136719 332.199219 396.1875 368.207031 L 501.644531 247.339844 L 497.222656 171.542969 C 486.441406 172.144531 475.632812 172.628906 464.832031 172.792969 C 455.609375 172.945312 434.316406 174.210938 427.503906 166.0625 C 425.566406 163.742188 424.410156 160.75 423.402344 157.890625 C 418.289062 143.414062 414.355469 125.46875 411.859375 110.191406 C 410.171875 99.804688 407.910156 84.195312 413.695312 74.785156 C 422.035156 61.226562 459.851562 63.917969 472.558594 64.171875 C 499.699219 64.730469 527.042969 66.371094 554.109375 68.492188 C 581.585938 70.640625 610.113281 73.183594 637.285156 78.019531 C 652.03125 80.632812 684.964844 85.675781 688.554688 105.230469 C 690.820312 117.515625 684.246094 126.539062 675.402344 133.847656 C 656.339844 149.609375 625.601562 156.710938 602.265625 160.761719 C 585.527344 163.664062 568.53125 165.675781 551.640625 167.355469 L 556.289062 247.171875 C 570.347656 245.433594 584.441406 243.960938 598.535156 242.589844 C 663.007812 236.296875 727.777344 231.476562 792.367188 226.613281 C 856.074219 221.820312 919.804688 217.273438 983.527344 212.734375 C 1006.996094 211.058594 1030.464844 209.359375 1053.9375 207.742188 L 1040.597656 128.609375 L 1008.054688 130.957031 C 966.023438 134 957.460938 72.164062 999.097656 68.332031 C 1019.992188 66.414062 1040.894531 64.539062 1061.789062 62.597656 C 1083.109375 60.625 1104.433594 58.605469 1125.722656 56.335938 C 1140.777344 54.730469 1156.273438 53.152344 1171.203125 50.566406 C 1174.121094 50.0625 1177.03125 49.523438 1179.933594 48.960938 C 1179.726562 47.539062 1179.511719 46.058594 1179.332031 44.589844 C 1176.59375 45.113281 1174.066406 45.5625 1171.925781 45.925781 C 1161.210938 47.746094 1159.882812 47.261719 1159.515625 46.472656 C 1159.152344 45.691406 1159.761719 44.597656 1160.726562 43.753906 C 1161.695312 42.910156 1163.03125 42.296875 1163.511719 40.847656 C 1164.003906 39.398438 1163.632812 37.09375 1163.9375 33.234375 C 1164.242188 29.351562 1165.210938 23.902344 1167.332031 19.546875 C 1169.441406 15.191406 1172.714844 11.925781 1177.011719 9.808594 C 1177.601562 9.519531 1178.203125 9.25 1178.828125 9.007812 L 1178.355469 6.257812 C 1177.976562 3.9375 1177.976562 3.476562 1178.203125 2.953125 C 1178.425781 2.429688 1178.886719 1.8125 1179.714844 1.4375 C 1180.546875 1.058594 1181.765625 0.902344 1182.914062 1.164062 C 1184.058594 1.417969 1185.152344 2.089844 1186.046875 3.796875 C 1186.507812 4.671875 1186.914062 5.824219 1187.269531 6.953125 C 1188.503906 6.8125 1189.699219 6.769531 1190.816406 6.78125 C 1194.992188 6.84375 1198.023438 7.933594 1200.992188 10.046875 C 1203.953125 12.164062 1206.859375 15.316406 1208.738281 19.066406 C 1210.613281 22.816406 1211.460938 27.171875 1212.859375 29.472656 C 1214.25 31.773438 1216.183594 32.015625 1217.695312 32.074219 C 1219.214844 32.136719 1220.300781 32.007812 1221.089844 31.953125 C 1221.871094 31.894531 1222.363281 31.894531 1222.601562 32.554688 C 1222.84375 33.222656 1222.84375 34.558594 1212.796875 37.28125 C 1209.574219 38.15625 1205.320312 39.167969 1200.675781 40.203125 L 1201.894531 44.238281 C 1216.476562 40.910156 1229.472656 37.851562 1244.179688 43.492188 L 1244.152344 43.464844 C 1257.511719 48.582031 1267.449219 59.371094 1274.011719 72.164062 C 1280.441406 84.695312 1283.75 99.097656 1284.828125 113.222656 C 1286.140625 130.375 1284.367188 149.066406 1279.675781 165.605469 C 1275.160156 181.519531 1267.460938 196.941406 1255.527344 208.144531 C 1235.3125 227.101562 1208.894531 229.609375 1183.046875 228.972656 L 1182.523438 228.960938 C 1159.300781 228.375 1160.519531 165.890625 1183.078125 166.457031 L 1183.597656 166.46875 C 1193.804688 166.722656 1213.148438 167.136719 1221.109375 159.679688 C 1224.429688 156.566406 1226.578125 150.65625 1227.808594 146.296875 C 1230.285156 137.582031 1231.101562 127.625 1230.414062 118.597656 C 1230.050781 113.898438 1229.1875 108.15625 1227.027344 103.960938 C 1226.789062 103.492188 1226.6875 103.171875 1226.644531 102.769531 C 1223.871094 102.957031 1211.523438 105.9375 1209.609375 106.367188 C 1199.460938 108.648438 1189.367188 110.796875 1179.125 112.5625 C 1151.488281 117.324219 1123.121094 120.328125 1095.234375 123.222656 L 1127.351562 316.078125 C 1134.703125 315.332031 1142.089844 314.964844 1149.476562 314.964844 C 1217.464844 314.964844 1279.734375 346.464844 1325.113281 398.71875 C 1372.992188 453.84375 1397.835938 526.679688 1397.835938 600.820312 C 1397.835938 674.964844 1372.992188 747.800781 1325.113281 802.933594 C 1279.730469 855.175781 1217.464844 886.691406 1149.476562 886.691406 C 1081.488281 886.691406 1019.21875 855.175781 973.828125 802.933594 C 925.953125 747.800781 901.109375 674.964844 901.109375 600.820312 C 901.109375 526.679688 925.953125 453.84375 973.828125 398.71875 C 1001.308594 367.085938 1035.527344 342.476562 1074.144531 328.347656 L 1067.570312 288.621094 C 945.039062 401.863281 822.703125 515.617188 699.363281 627.925781 C 705.21875 640.144531 708.496094 653.839844 708.496094 668.296875 C 708.496094 702.398438 690.257812 732.25 662.996094 748.632812 C 668.105469 763.320312 673.175781 778.570312 676.226562 789.664062 C 676.871094 791.980469 677.417969 794.117188 677.890625 796.089844 C 685.210938 796.269531 692.066406 796.496094 697.261719 796.695312 C 711.78125 797.269531 713.355469 797.652344 715.613281 800.46875 C 717.878906 803.289062 720.828125 808.53125 722.101562 812.785156 C 723.378906 817.042969 722.957031 820.300781 720.984375 823.355469 C 719 826.410156 715.457031 829.28125 699.34375 829.355469 C 683.238281 829.425781 654.574219 826.714844 639.480469 824.335938 C 624.371094 821.941406 622.828125 819.851562 622.027344 816.820312 C 621.21875 813.785156 621.148438 809.789062 621.433594 806.886719 C 621.71875 803.992188 622.347656 802.171875 623.464844 800.316406 C 624.578125 798.460938 626.183594 796.542969 640.71875 795.960938 C 643.582031 795.84375 646.953125 795.785156 650.625 795.761719 C 643.675781 785.289062 635.835938 772.734375 628.816406 760.921875 C 624.238281 761.609375 619.550781 761.964844 614.78125 761.964844 C 563.023438 761.964844 521.0625 720.03125 521.0625 668.296875 C 521.0625 666.414062 521.117188 664.539062 521.226562 662.683594 Z M 437.304688 654.496094 C 385.160156 649.164062 332.179688 643.371094 297.734375 639.464844 L 293.742188 639.015625 C 282.390625 657.53125 263.378906 669.671875 241.832031 669.671875 C 207.042969 669.671875 178.847656 638.011719 178.847656 598.972656 C 178.847656 559.917969 207.046875 528.257812 241.832031 528.257812 C 253.574219 528.257812 264.5625 531.875 273.976562 538.15625 C 301.648438 499.34375 337.269531 453.921875 358.796875 427.238281 C 361.683594 423.65625 364.3125 420.421875 366.714844 417.492188 C 333.335938 386.769531 291.339844 368.460938 245.71875 368.460938 C 136.980469 368.460938 48.824219 472.46875 48.824219 600.765625 C 48.824219 729.066406 136.972656 833.074219 245.71875 833.074219 C 338.78125 833.074219 416.742188 756.902344 437.316406 654.507812 Z M 304.820312 598.546875 L 314.605469 599.191406 C 346.347656 601.300781 395.8125 604.667969 442.515625 607.734375 C 442.570312 605.414062 442.613281 603.09375 442.613281 600.761719 C 442.613281 545.144531 426.039062 494.101562 398.40625 454.109375 L 395.292969 457.917969 C 371.296875 487.265625 331.480469 536.421875 300.59375 573.476562 C 303.285156 581.261719 304.769531 589.71875 304.820312 598.546875 Z M 496.179688 611.1875 C 506.6875 611.835938 516.476562 612.433594 525.199219 612.9375 C 519.402344 541.734375 515.035156 470.253906 510.671875 398.941406 C 509.238281 375.492188 507.824219 352.042969 506.421875 328.59375 L 435.582031 410.234375 C 475.808594 463.488281 496.453125 530.039062 496.453125 597.714844 C 496.453125 602.21875 496.355469 606.707031 496.179688 611.191406 Z M 1135.972656 380.664062 C 1142.746094 429.054688 1154.722656 517.039062 1160.550781 567.789062 C 1177.53125 575.824219 1189.441406 594.679688 1189.441406 616.660156 C 1189.441406 645.933594 1168.300781 669.664062 1142.226562 669.664062 C 1116.148438 669.664062 1095.003906 645.933594 1095.003906 616.660156 C 1095.003906 595.21875 1106.34375 576.746094 1122.660156 568.398438 C 1112.632812 520.15625 1096.417969 435.054688 1087.171875 389.019531 L 1085.167969 379.070312 C 1007.625 410.269531 951.851562 496.941406 951.851562 599 C 951.851562 727.296875 1040 831.292969 1148.746094 831.292969 C 1257.480469 831.292969 1345.640625 727.292969 1345.640625 599 C 1345.640625 470.699219 1257.484375 366.691406 1148.746094 366.691406 C 1143.816406 366.910156 1138.933594 366.910156 1134.089844 367.328125 Z M 1135.972656 380.664062 " fill-opacity="1" fill-rule="evenodd"/></g></g></g></g></svg>';

return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/**
 * Adds "Bespoke Bike Builder" as a top-level menu item, plus a
 * "Build Requests" page underneath it.
 */
public static function register_menu_pages() {

add_menu_page(
'Bespoke Bike Builder',
'Bespoke Bike Builder',
'manage_options',
'bbb-dashboard',
array( __CLASS__, 'render_dashboard_page' ),
self::get_menu_icon(),
56
);

add_submenu_page(
'bbb-dashboard',
'Build Requests',
'Build Requests',
'manage_options',
'bbb-submissions',
array( __CLASS__, 'render_submissions_page' )
);
}

public static function render_dashboard_page() {

global $wpdb;

$table_name = $wpdb->prefix . 'bbb_templates';

$templates = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A );

?>
<div class="wrap">
<h1>Bespoke Bike Builder</h1>
<p>This page confirms the plugin's database table and initial data were created successfully.</p>

<?php if ( empty( $templates ) ) : ?>

<p><strong>No build templates found yet.</strong></p>

<?php else : ?>

<table class="widefat striped">
<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Brand</th>
<th>Slug</th>
<th>Active</th>
</tr>
</thead>
<tbody>
<?php foreach ( $templates as $template ) : ?>
<tr>
<td><?php echo esc_html( $template['id'] ); ?></td>
<td><?php echo esc_html( $template['name'] ); ?></td>
<td><?php echo esc_html( $template['brand'] ); ?></td>
<td><?php echo esc_html( $template['slug'] ); ?></td>
<td><?php echo $template['is_active'] ? 'Yes' : 'No'; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php endif; ?>

</div>
<?php
}

public static function render_submissions_page() {

global $wpdb;

$submissions_table      = $wpdb->prefix . 'bbb_submissions';
$submission_items_table = $wpdb->prefix . 'bbb_submission_items';
$groups_table           = $wpdb->prefix . 'bbb_option_groups';
$options_table          = $wpdb->prefix . 'bbb_options';
$templates_table        = $wpdb->prefix . 'bbb_templates';

$submissions = $wpdb->get_results(
"SELECT s.*, t.name AS template_name
FROM {$submissions_table} s
LEFT JOIN {$templates_table} t ON t.id = s.template_id
ORDER BY s.created_at DESC",
ARRAY_A
);

?>
<div class="wrap">
<h1>Build Requests</h1>

<?php if ( isset( $_GET['updated'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p>Status updated.</p></div>
<?php endif; ?>

<?php if ( empty( $submissions ) ) : ?>

<p>No build requests have been submitted yet.</p>

<?php else : ?>

<table class="widefat striped">
<thead>
<tr>
<th>Reference</th>
<th>Build</th>
<th>Customer</th>
<th>Contact</th>
<th>Build Details</th>
<th>Remarks</th>
<th>Status</th>
<th>Submitted</th>
</tr>
</thead>
<tbody>
<?php foreach ( $submissions as $submission ) : ?>

<?php
$items = $wpdb->get_results(
$wpdb->prepare(
"SELECT g.label AS group_label, o.label AS option_label
FROM {$submission_items_table} si
LEFT JOIN {$groups_table} g ON g.id = si.group_id
LEFT JOIN {$options_table} o ON o.id = si.option_id
WHERE si.submission_id = %d",
$submission['id']
),
ARRAY_A
);
?>

<tr>
<td><strong><?php echo esc_html( $submission['reference_code'] ); ?></strong></td>
<td><?php echo esc_html( $submission['template_name'] ); ?></td>
<td><?php echo esc_html( $submission['customer_name'] ); ?></td>
<td>
<?php echo esc_html( $submission['customer_email'] ); ?><br>
<?php echo esc_html( $submission['customer_whatsapp'] ); ?>
</td>
<td>
<details>
<summary>View build</summary>
<ul style="margin: 8px 0 0 0;">
<?php foreach ( $items as $item ) : ?>
<li><strong><?php echo esc_html( $item['group_label'] ); ?>:</strong> <?php echo esc_html( $item['option_label'] ); ?></li>
<?php endforeach; ?>
</ul>
</details>
</td>
<td style="max-width: 220px;">
<?php echo ! empty( $submission['customer_message'] ) ? esc_html( $submission['customer_message'] ) : '<span style="color:#999;">-</span>'; ?>
</td>
<td>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
<input type="hidden" name="action" value="bbb_update_status">
<input type="hidden" name="submission_id" value="<?php echo esc_attr( $submission['id'] ); ?>">
<?php wp_nonce_field( 'bbb_update_status_' . $submission['id'] ); ?>
<select name="status" onchange="this.form.submit()">
<?php foreach ( self::$statuses as $status ) : ?>
<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $submission['status'], $status ); ?>>
<?php echo esc_html( ucfirst( $status ) ); ?>
</option>
<?php endforeach; ?>
</select>
</form>
</td>
<td><?php echo esc_html( $submission['created_at'] ); ?></td>
</tr>

<?php endforeach; ?>
</tbody>
</table>

<?php endif; ?>

</div>
<?php
}

public static function handle_update_status() {

if ( ! current_user_can( 'manage_options' ) ) {
wp_die( 'You do not have permission to do this.' );
}

$submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
$new_status    = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

check_admin_referer( 'bbb_update_status_' . $submission_id );

if ( ! $submission_id || ! in_array( $new_status, self::$statuses, true ) ) {
wp_die( 'Invalid request.' );
}

global $wpdb;

$submissions_table = $wpdb->prefix . 'bbb_submissions';

$wpdb->update(
$submissions_table,
array(
'status'     => $new_status,
'updated_at' => current_time( 'mysql' ),
),
array( 'id' => $submission_id )
);

wp_safe_redirect( admin_url( 'admin.php?page=bbb-submissions&updated=1' ) );
exit;
}
}
