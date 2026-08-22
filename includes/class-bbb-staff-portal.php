<?php
/**
 * Bespoke Bike Builder — Staff Frontend Portal
 *
 * Implements Blueprint Section 19 (Staff Frontend): a separate,
 * login-gated, non-wp-admin operational portal for staff. Registered
 * as a shortcode [bbb_staff_portal] — an Administrator places this on
 * any normal WordPress page (e.g. /staff-portal/) and staff visit that
 * page URL directly. They log in there, never touching /wp-admin/.
 *
 * Access is still governed by real WordPress users/roles/capabilities
 * (the Custom Build Manager / Sales Staff / Option Manager roles
 * registered by class-bbb-roles.php) — this is a different *door*
 * into the same account system, not a separate, weaker login.
 *
 * Because this class cannot see the internals of class-bbb-ajax.php
 * or the exact submissions table schema, it reads the table's actual
 * columns at runtime (SHOW COLUMNS) rather than hardcoding field
 * names that might not match what's really there — so it displays
 * real data correctly regardless of the exact schema in use.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBB_Staff_Portal {

	const NONCE_ACTION = 'bbb_staff_portal_nonce';
	const REQUIRED_CAPS = array( 'manage_bbb_submissions', 'manage_options' );

	public static function init() {
		add_shortcode( 'bbb_staff_portal', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_ajax_bbb_staff_login', array( __CLASS__, 'ajax_login' ) );
		add_action( 'wp_ajax_nopriv_bbb_staff_login', array( __CLASS__, 'ajax_login' ) );
		add_action( 'wp_ajax_bbb_staff_logout', array( __CLASS__, 'ajax_logout' ) );
		add_action( 'wp_ajax_bbb_staff_update_status', array( __CLASS__, 'ajax_update_status' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
	}

	public static function maybe_enqueue_assets() {
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'bbb_staff_portal' ) ) {
			return;
		}

		$css_path = BBB_PLUGIN_DIR . 'assets/css/staff-portal.css';
		$js_path  = BBB_PLUGIN_DIR . 'assets/js/staff-portal.js';

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'bbb-staff-portal', BBB_PLUGIN_URL . 'assets/css/staff-portal.css', array(), filemtime( $css_path ) );
		}
		if ( file_exists( $js_path ) ) {
			wp_enqueue_script( 'bbb-staff-portal', BBB_PLUGIN_URL . 'assets/js/staff-portal.js', array(), filemtime( $js_path ), true );
			wp_localize_script( 'bbb-staff-portal', 'bbbStaffPortal', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			) );
		}
	}

	private static function current_user_authorized() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		foreach ( self::REQUIRED_CAPS as $cap ) {
			if ( current_user_can( $cap ) ) {
				return true;
			}
		}
		return false;
	}

	public static function render_shortcode() {
		ob_start();

		if ( ! self::current_user_authorized() ) {
			self::render_login_form();
		} else {
			self::render_dashboard();
		}

		return ob_get_clean();
	}

	private static function render_login_form() {
		$error = isset( $_GET['bbb_login_error'] ) ? sanitize_text_field( wp_unslash( $_GET['bbb_login_error'] ) ) : '';
		?>
		<div class="bbb-staff-portal bbb-staff-login-wrap">
			<div class="bbb-staff-login-box">
				<h2>The Cycle Hub — Staff Portal</h2>
				<?php if ( is_user_logged_in() ) : ?>
					<p class="bbb-staff-error">Your account doesn't have staff portal access. Contact your Administrator to be assigned a Custom Build role.</p>
				<?php else : ?>
					<?php if ( $error ) : ?>
						<p class="bbb-staff-error"><?php echo esc_html( $error ); ?></p>
					<?php endif; ?>
					<form class="bbb-staff-login-form" method="post">
						<label>Username or Email
							<input type="text" name="bbb_staff_username" required />
						</label>
						<label>Password
							<input type="password" name="bbb_staff_password" required />
						</label>
						<?php wp_nonce_field( self::NONCE_ACTION, 'bbb_staff_login_nonce' ); ?>
						<button type="submit" name="bbb_staff_login_submit" class="bbb-staff-btn-primary">Log In</button>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
		self::maybe_handle_login_post();
	}

	/**
	 * Handles the plain (non-AJAX) POST from the login form above.
	 * Uses wp_signon() — the same core WordPress authentication used
	 * by wp-login.php — so staff are real WordPress users signing in
	 * through a different-looking door, not a separate weaker system.
	 */
	private static function maybe_handle_login_post() {
		if ( empty( $_POST['bbb_staff_login_submit'] ) ) {
			return;
		}

		if ( ! isset( $_POST['bbb_staff_login_nonce'] ) || ! wp_verify_nonce( $_POST['bbb_staff_login_nonce'], self::NONCE_ACTION ) ) {
			return;
		}

		$creds = array(
			'user_login'    => isset( $_POST['bbb_staff_username'] ) ? sanitize_text_field( wp_unslash( $_POST['bbb_staff_username'] ) ) : '',
			'user_password' => isset( $_POST['bbb_staff_password'] ) ? $_POST['bbb_staff_password'] : '',
			'remember'      => true,
		);

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			$redirect = add_query_arg( 'bbb_login_error', rawurlencode( 'Incorrect username or password.' ), self::current_url() );
			wp_safe_redirect( $redirect );
			exit;
		}

		wp_safe_redirect( self::current_url() );
		exit;
	}

	private static function current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * Renders the logged-in staff dashboard: a submissions table read
	 * dynamically from whatever columns actually exist on
	 * wp_bbb_submissions, so it never guesses at a schema it can't see.
	 */
	private static function render_dashboard() {
		global $wpdb;
		$table = $wpdb->prefix . 'bbb_submissions';
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		$user = wp_get_current_user();
		?>
		<div class="bbb-staff-portal bbb-staff-dashboard">
			<div class="bbb-staff-topbar">
				<h2>The Cycle Hub — Staff Portal</h2>
				<div class="bbb-staff-topbar-right">
					<span>Logged in as <strong><?php echo esc_html( $user->display_name ); ?></strong></span>
					<button type="button" class="bbb-staff-logout-btn">Log Out</button>
				</div>
			</div>

			<?php if ( ! $table_exists ) : ?>
				<p class="bbb-staff-error">Submissions table not found. Ask your Administrator to check the plugin setup.</p>
			<?php else : ?>
				<?php self::render_submissions_table( $table ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_submissions_table( $table ) {
		global $wpdb;

		$columns = $wpdb->get_col( "SHOW COLUMNS FROM $table", 0 );
		if ( empty( $columns ) ) {
			echo '<p>No columns found on the submissions table.</p>';
			return;
		}

		// Prefer a sensible, readable subset if these common column
		// names exist; otherwise fall back to showing every column so
		// nothing is ever silently hidden.
		$preferred = array( 'id', 'reference', 'customer_name', 'name', 'email', 'whatsapp', 'phone', 'build_type', 'status', 'created_at' );
		$display_columns = array_values( array_intersect( $preferred, $columns ) );
		if ( empty( $display_columns ) ) {
			$display_columns = $columns;
		}

		$has_status_column = in_array( 'status', $columns, true );

		$order_col = in_array( 'created_at', $columns, true ) ? 'created_at' : ( in_array( 'id', $columns, true ) ? 'id' : $columns[0] );
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY $order_col DESC LIMIT 200", ARRAY_A );

		echo '<p>' . count( $rows ) . ' most recent submissions (showing up to 200).</p>';
		echo '<div class="bbb-staff-table-wrap"><table class="bbb-staff-table"><thead><tr>';
		foreach ( $display_columns as $col ) {
			echo '<th>' . esc_html( ucwords( str_replace( '_', ' ', $col ) ) ) . '</th>';
		}
		if ( $has_status_column ) {
			echo '<th>Update Status</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $display_columns as $col ) {
				$value = isset( $row[ $col ] ) ? $row[ $col ] : '';
				echo '<td>' . esc_html( wp_trim_words( (string) $value, 12 ) ) . '</td>';
			}
			if ( $has_status_column ) {
				$id_col = in_array( 'id', $columns, true ) ? 'id' : $columns[0];
				echo '<td>';
				echo '<select class="bbb-staff-status-select" data-submission-id="' . esc_attr( $row[ $id_col ] ) . '">';
				foreach ( self::status_options() as $status ) {
					$selected = ( isset( $row['status'] ) && $row['status'] === $status ) ? 'selected' : '';
					echo '<option value="' . esc_attr( $status ) . '" ' . $selected . '>' . esc_html( $status ) . '</option>';
				}
				echo '</select>';
				echo '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	private static function status_options() {
		return array( 'New', 'Under Review', 'Revision Requested', 'Approved', 'Deposit Requested', 'Deposit Paid', 'Awaiting Customer Approval', 'Finalised', 'Ordered', 'Ready', 'Completed', 'Cancelled' );
	}

	public static function ajax_logout() {
		wp_logout();
		wp_send_json_success();
	}

	/**
	 * Updates the status column on a submission row, if that column
	 * exists. Requires manage_bbb_submissions or manage_options.
	 */
	public static function ajax_update_status() {
		if ( ! self::current_user_authorized() ) {
			wp_send_json_error( array( 'message' => 'Not authorized.' ), 403 );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => 'Invalid request.' ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bbb_submissions';
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM $table", 0 );

		if ( ! in_array( 'status', $columns, true ) ) {
			wp_send_json_error( array( 'message' => 'This submissions table has no status column.' ), 400 );
		}

		$id_col = in_array( 'id', $columns, true ) ? 'id' : $columns[0];
		$submission_id = isset( $_POST['submission_id'] ) ? absint( $_POST['submission_id'] ) : 0;
		$new_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $submission_id || ! in_array( $new_status, self::status_options(), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid submission or status.' ), 400 );
		}

		$updated = $wpdb->update( $table, array( 'status' => $new_status ), array( $id_col => $submission_id ) );

		if ( $updated === false ) {
			wp_send_json_error( array( 'message' => 'Could not update status.' ), 500 );
		}

		wp_send_json_success( array( 'message' => 'Status updated.' ) );
	}
}
