<?php
/**
 * Bespoke Bike Builder - Staff Roles Admin Page
 *
 * A small, self-contained admin screen showing which staff roles
 * exist, which users currently hold them, and what remains to be
 * wired up so non-Administrator staff can access the existing
 * Build Options / Header Settings / Notices screens.
 *
 * As of this update, this admin page lives under the main
 * "Bespoke Bike Builder" admin menu instead of as its own
 * independent top-level item, so every plugin screen is found in
 * one place.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
exit; // Exit if accessed directly.
}

class BBB_Roles_Admin_Page {

public static function init() {
add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
}

/**
 * Registers the "BBB Staff Roles" page as a submenu under the
 * main "Bespoke Bike Builder" admin menu, visible only to
 * Administrators.
 */
public static function add_page() {

add_submenu_page(
'bbb-dashboard',
'BBB Staff Roles',
'Staff Roles',
'manage_options',
'bbb-staff-roles',
array( __CLASS__, 'render_page' )
);
}

private static function role_slugs() {

return array(
BBB_Roles::ROLE_BUILD_MANAGER  => 'Custom Build Manager',
BBB_Roles::ROLE_SALES_STAFF    => 'Custom Build Sales Staff',
BBB_Roles::ROLE_OPTION_MANAGER => 'Custom Build Option Manager',
);
}

public static function render_page() {

if ( ! current_user_can( 'manage_options' ) ) {
return;
}
?>
<div class="wrap">
<h1>Staff Roles</h1>

<div class="notice notice-info" style="padding:14px 16px;">
<p><strong>Setup status:</strong></p>
<p><?php echo esc_html( BBB_Roles::get_setup_notice() ); ?></p>
</div>

<h2>Assign a role to a staff member</h2>
<p>Go to <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">Users</a>, edit or add a user, and choose one of the roles below from the Role dropdown.</p>

<table class="widefat striped" style="max-width:900px;margin-top:16px;">
<thead>
<tr>
<th>Role</th>
<th>Intended use (per approved blueprint)</th>
<th>Users currently assigned</th>
</tr>
</thead>
<tbody>
<?php foreach ( self::role_slugs() as $slug => $label ) : ?>
<?php $users = get_users( array( 'role' => $slug ) ); ?>
<tr>
<td><strong><?php echo esc_html( $label ); ?></strong></td>
<td><?php echo esc_html( self::role_description( $slug ) ); ?></td>
<td>
<?php
if ( empty( $users ) ) {
echo '<em>None assigned yet</em>';
} else {
$names = array_map(
function ( $u ) {
return esc_html( $u->display_name . ' (' . $u->user_email . ')' );
},
$users
);
echo implode( '<br>', $names );
}
?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</div>
<?php
}

private static function role_description( $slug ) {

switch ( $slug ) {
case BBB_Roles::ROLE_BUILD_MANAGER:
return 'Leads, statuses, assignments, options, rules, imports, quotes, deposit requests.';
case BBB_Roles::ROLE_SALES_STAFF:
return 'Leads, notes, assigned builds, status updates, WhatsApp, quotes, deposit request actions.';
case BBB_Roles::ROLE_OPTION_MANAGER:
return 'Options, images, availability and imports only.';
default:
return '';
}
}
}
