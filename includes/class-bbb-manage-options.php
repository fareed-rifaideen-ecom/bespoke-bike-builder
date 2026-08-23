<?php
/**
 * Admin screen for managing every build option (Frame Colour,
 * Groupset, Wheelset, Frame Size, Cockpit, and Build Type) across
 * all option groups, including uploading a product photo for each
 * option straight from the WordPress Media Library.
 *
 * This is what lets staff add new Pinarello colourways (or any other
 * option) themselves going forward, without ever touching the
 * database directly.
 *
 * As of this update, each group's card also has a "Compatibility
 * Rules" section, implementing Blueprint Section 21 (Option
 * Management and Compatibility): staff can say "IF this option in
 * another group is selected, THEN only these options in this group
 * remain allowed." Rules are stored in wp_bbb_compatibility_rules
 * and exposed to the customer-facing builder as JSON (see
 * class-bbb-shortcodes.php) for the builder script to enforce.
 */

// If this file is opened directly in a browser (not through WordPress), stop everything.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BBB_Manage_Options {

	/**
	 * This function "switches on" the Manage Options screen.
	 * It is called once, from the main plugin file.
	 */
	public static function init() {

		add_action( 'admin_menu', array( __CLASS__, 'register_menu_page' ) );
		add_action( 'admin_post_bbb_save_option', array( __CLASS__, 'handle_save_option' ) );
		add_action( 'admin_post_bbb_toggle_option_active', array( __CLASS__, 'handle_toggle_active' ) );
		add_action( 'admin_post_bbb_add_compatibility_rule', array( __CLASS__, 'handle_add_compatibility_rule' ) );
		add_action( 'admin_post_bbb_delete_compatibility_rule', array( __CLASS__, 'handle_delete_compatibility_rule' ) );
	}

	/**
	 * Adds "Manage Options" as a submenu page under the main
	 * Bespoke Bike Builder menu.
	 */
	public static function register_menu_page() {

		$hook = add_submenu_page(
			'bbb-dashboard',
			'Manage Options',
			'Manage Options',
			'manage_options',
			'bbb-manage-options',
			array( __CLASS__, 'render_page' )
		);

		// Only load the Media Library JavaScript on this specific page,
		// not on every wp-admin screen.
		add_action( 'load-' . $hook, array( __CLASS__, 'enqueue_media_library' ) );
	}

	/**
	 * Loads WordPress's built-in Media Library popup, so the "Choose
	 * Image" buttons below can open it.
	 */
	public static function enqueue_media_library() {

		wp_enqueue_media();
	}

	/**
	 * Displays every option group and its options, with forms to add
	 * a new option or edit an existing one.
	 */
	public static function render_page() {

		global $wpdb;

		$groups_table  = $wpdb->prefix . 'bbb_option_groups';
		$options_table = $wpdb->prefix . 'bbb_options';

		$groups = $wpdb->get_results( "SELECT * FROM {$groups_table} ORDER BY sort_order ASC", ARRAY_A );

		?>
		<div class="wrap">
			<h1>Manage Build Options</h1>
			<p>Add, edit, or deactivate the choices customers see in each step of the build wizard. Upload a product photo for any option using the Media Library button.</p>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Option saved.</p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['rule_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Compatibility rule saved.</p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['rule_deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Compatibility rule removed.</p></div>
			<?php endif; ?>

			<?php foreach ( $groups as $group ) : ?>

				<?php
				$options = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$options_table} WHERE group_id = %d ORDER BY sort_order ASC",
						$group['id']
					),
					ARRAY_A
				);
				?>

				<div class="card" style="max-width: 900px; margin: 20px 0; padding: 16px 20px;">

					<h2><?php echo esc_html( $group['label'] ); ?> <small style="color:#999;">(<?php echo esc_html( $group['display_type'] ); ?>)</small></h2>

					<table class="widefat striped" style="margin-bottom: 16px;">
						<thead>
							<tr>
								<th style="width:70px;">Image</th>
								<th>Label</th>
								<th>Price Delta</th>
								<th>Sort Order</th>
								<th>Active</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $options as $option ) : ?>

								<?php $thumb_url = $option['image_id'] ? wp_get_attachment_image_url( $option['image_id'], 'thumbnail' ) : ''; ?>

								<tr>
									<td>
										<?php if ( $thumb_url ) : ?>
											<img src="<?php echo esc_url( $thumb_url ); ?>" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">
										<?php else : ?>
											<span style="color:#999;">-</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $option['label'] ); ?></td>
									<td><?php echo esc_html( $option['price_delta'] ); ?></td>
									<td><?php echo esc_html( $option['sort_order'] ); ?></td>
									<td><?php echo $option['is_active'] ? 'Yes' : 'No'; ?></td>
									<td>
										<button type="button" class="button bbb-toggle-edit" data-target="bbb-edit-row-<?php echo esc_attr( $option['id'] ); ?>">Edit</button>

										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
											<input type="hidden" name="action" value="bbb_toggle_option_active">
											<input type="hidden" name="option_id" value="<?php echo esc_attr( $option['id'] ); ?>">
											<?php wp_nonce_field( 'bbb_toggle_option_active_' . $option['id'] ); ?>
											<button type="submit" class="button">
												<?php echo $option['is_active'] ? 'Deactivate' : 'Activate'; ?>
											</button>
										</form>
									</td>
								</tr>

								<tr id="bbb-edit-row-<?php echo esc_attr( $option['id'] ); ?>" class="bbb-edit-row" style="display:none;">
									<td colspan="6">
										<?php self::render_option_form( $group, $option ); ?>
									</td>
								</tr>

							<?php endforeach; ?>
						</tbody>
					</table>

					<h3 style="margin-top:0;">Add New Option to <?php echo esc_html( $group['label'] ); ?></h3>
					<?php self::render_option_form( $group, null ); ?>

					<?php self::render_compatibility_rules_section( $group ); ?>

				</div>

			<?php endforeach; ?>

		</div>

		<script>
		document.addEventListener( 'DOMContentLoaded', function () {

			// Toggles an option's inline edit row open/closed.
			document.querySelectorAll( '.bbb-toggle-edit' ).forEach( function ( button ) {

				button.addEventListener( 'click', function () {

					var row = document.getElementById( button.dataset.target );

					if ( row ) {
						row.style.display = ( row.style.display === 'none' ) ? '' : 'none';
					}
				} );
			} );

			// Wires up every "Choose Image" button on the page to WordPress's
			// built-in Media Library popup.
			document.querySelectorAll( '.bbb-media-button' ).forEach( function ( button ) {

				button.addEventListener( 'click', function ( event ) {

					event.preventDefault();

					var wrapper  = button.closest( '.bbb-image-field' );
					var hidden   = wrapper.querySelector( '.bbb-image-id-input' );
					var preview  = wrapper.querySelector( '.bbb-image-preview' );
					var removeBtn = wrapper.querySelector( '.bbb-remove-image' );

					var frame = wp.media( {
						title: 'Select a product image',
						button: { text: 'Use this image' },
						multiple: false
					} );

					frame.on( 'select', function () {

						var attachment = frame.state().get( 'selection' ).first().toJSON();

						hidden.value = attachment.id;

						var thumbUrl = ( attachment.sizes && attachment.sizes.thumbnail )
							? attachment.sizes.thumbnail.url
							: attachment.url;

						preview.src = thumbUrl;
						preview.style.display = 'inline-block';

						if ( removeBtn ) {
							removeBtn.style.display = 'inline-block';
						}
					} );

					frame.open();
				} );
			} );

			// Clears a chosen image without opening the Media Library.
			document.querySelectorAll( '.bbb-remove-image' ).forEach( function ( button ) {

				button.addEventListener( 'click', function ( event ) {

					event.preventDefault();

					var wrapper = button.closest( '.bbb-image-field' );
					var hidden  = wrapper.querySelector( '.bbb-image-id-input' );
					var preview = wrapper.querySelector( '.bbb-image-preview' );

					hidden.value = '';
					preview.style.display = 'none';
					preview.src = '';
					button.style.display = 'none';
				} );
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Renders one Add/Edit form for a single option. Passing null for
	 * $option renders a blank "Add New" form for the given group.
	 */
	private static function render_option_form( $group, $option ) {

		$is_edit    = ( null !== $option );
		$label      = $is_edit ? $option['label'] : '';
		$price      = $is_edit ? $option['price_delta'] : '0';
		$sort_order = $is_edit ? $option['sort_order'] : 0;
		$image_id   = $is_edit ? $option['image_id'] : '';
		$thumb_url  = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">

			<input type="hidden" name="action" value="bbb_save_option">
			<input type="hidden" name="group_id" value="<?php echo esc_attr( $group['id'] ); ?>">

			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="option_id" value="<?php echo esc_attr( $option['id'] ); ?>">
				<?php wp_nonce_field( 'bbb_save_option_' . $option['id'] ); ?>
			<?php else : ?>
				<?php wp_nonce_field( 'bbb_save_option_new_' . $group['id'] ); ?>
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th style="width:140px;"><label>Label</label></th>
					<td><input type="text" name="label" value="<?php echo esc_attr( $label ); ?>" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label>Price Delta</label></th>
					<td><input type="number" step="0.01" name="price_delta" value="<?php echo esc_attr( $price ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th><label>Sort Order</label></th>
					<td><input type="number" name="sort_order" value="<?php echo esc_attr( $sort_order ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th><label>Product Image</label></th>
					<td class="bbb-image-field">
						<input type="hidden" class="bbb-image-id-input" name="image_id" value="<?php echo esc_attr( $image_id ); ?>">
						<img class="bbb-image-preview" src="<?php echo esc_url( $thumb_url ); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:4px;<?php echo $thumb_url ? '' : 'display:none;'; ?>vertical-align:middle;margin-right:10px;">
						<button type="button" class="button bbb-media-button">Choose Image</button>
						<button type="button" class="button bbb-remove-image" style="<?php echo $thumb_url ? '' : 'display:none;'; ?>">Remove</button>
					</td>
				</tr>
			</table>

			<button type="submit" class="button button-primary"><?php echo $is_edit ? 'Save Changes' : 'Add Option'; ?></button>

		</form>
		<?php
	}

	/**
	 * Renders the "Compatibility Rules" section for one group,
	 * implementing Blueprint Section 21. A rule says: "IF this
	 * trigger option (from any other group) is selected, THEN only
	 * these specific options remain allowed in this group."
	 *
	 * If a group has no rules at all, every one of its active
	 * options stays available to every customer, exactly as today -
	 * rules only ever narrow things down once an Administrator
	 * actually creates one for this group.
	 *
	 * Note: this section manages and stores the rules. Enforcing
	 * them live as a customer moves through the builder happens in
	 * assets/js/builder.js, which reads the JSON this plugin outputs
	 * via class-bbb-shortcodes.php (see get_compatibility_rules_json()
	 * in that file). That script has not been reviewed as part of
	 * this change, so live enforcement should be verified separately.
	 */
	private static function render_compatibility_rules_section( $group ) {

		global $wpdb;

		$rules_table   = $wpdb->prefix . 'bbb_compatibility_rules';
		$options_table = $wpdb->prefix . 'bbb_options';
		$groups_table  = $wpdb->prefix . 'bbb_option_groups';

		$rules = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.id, r.trigger_option_id, r.allowed_option_id,
					trig.label AS trigger_label, trig_group.label AS trigger_group_label,
					allowed.label AS allowed_label
				FROM {$rules_table} r
				INNER JOIN {$options_table} trig ON trig.id = r.trigger_option_id
				INNER JOIN {$groups_table} trig_group ON trig_group.id = trig.group_id
				INNER JOIN {$options_table} allowed ON allowed.id = r.allowed_option_id
				WHERE r.target_group_id = %d
				ORDER BY trig.label ASC, allowed.label ASC",
				$group['id']
			),
			ARRAY_A
		);

		// Every active option in every OTHER group can act as a trigger.
		$trigger_candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT o.id, o.label, g.label AS group_label
				FROM {$options_table} o
				INNER JOIN {$groups_table} g ON g.id = o.group_id
				WHERE o.group_id != %d AND o.is_active = 1
				ORDER BY g.sort_order ASC, o.sort_order ASC",
				$group['id']
			),
			ARRAY_A
		);

		// Only this group's own active options can be "allowed" targets.
		$allowed_candidates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, label FROM {$options_table} WHERE group_id = %d AND is_active = 1 ORDER BY sort_order ASC",
				$group['id']
			),
			ARRAY_A
		);

		?>
		<h3>Compatibility Rules for <?php echo esc_html( $group['label'] ); ?></h3>
		<p class="description">Restrict which <?php echo esc_html( $group['label'] ); ?> options a customer can pick, based on an earlier selection in another step. If no rules exist here, every active option above stays available to everyone.</p>

		<?php if ( ! empty( $rules ) ) : ?>
			<table class="widefat striped" style="max-width:700px; margin-bottom:16px;">
				<thead>
					<tr>
						<th>If this is selected</th>
						<th>Then <?php echo esc_html( $group['label'] ); ?> allows</th>
						<th style="width:90px;">Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rules as $rule ) : ?>
						<tr>
							<td><?php echo esc_html( $rule['trigger_group_label'] . ': ' . $rule['trigger_label'] ); ?></td>
							<td><?php echo esc_html( $rule['allowed_label'] ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="bbb_delete_compatibility_rule">
									<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ); ?>">
									<?php wp_nonce_field( 'bbb_delete_compatibility_rule_' . $rule['id'] ); ?>
									<button type="submit" class="button">Remove</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><em>No compatibility rules yet for this group.</em></p>
		<?php endif; ?>

		<?php if ( empty( $trigger_candidates ) || empty( $allowed_candidates ) ) : ?>
			<p><em>Add active options to this group and at least one other group before creating a rule.</em></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:700px;">
				<input type="hidden" name="action" value="bbb_add_compatibility_rule">
				<input type="hidden" name="target_group_id" value="<?php echo esc_attr( $group['id'] ); ?>">
				<?php wp_nonce_field( 'bbb_add_compatibility_rule_' . $group['id'] ); ?>

				<table class="form-table">
					<tr>
						<th style="width:180px;"><label>If this is selected</label></th>
						<td>
							<select name="trigger_option_id" required>
								<option value="">Select an option</option>
								<?php foreach ( $trigger_candidates as $candidate ) : ?>
									<option value="<?php echo esc_attr( $candidate['id'] ); ?>">
										<?php echo esc_html( $candidate['group_label'] . ': ' . $candidate['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label>Then allow this <?php echo esc_html( $group['label'] ); ?> option</label></th>
						<td>
							<select name="allowed_option_id" required>
								<option value="">Select an option</option>
								<?php foreach ( $allowed_candidates as $candidate ) : ?>
									<option value="<?php echo esc_attr( $candidate['id'] ); ?>">
										<?php echo esc_html( $candidate['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<button type="submit" class="button button-secondary">Add Rule</button>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Handles both "Add New Option" and "Save Changes" form
	 * submissions, since they both post to the same action and are
	 * told apart by whether option_id is present.
	 */
	public static function handle_save_option() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do this.' );
		}

		$group_id    = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$option_id   = isset( $_POST['option_id'] ) ? absint( $_POST['option_id'] ) : 0;
		$label       = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$price_delta = isset( $_POST['price_delta'] ) ? (float) $_POST['price_delta'] : 0;
		$sort_order  = isset( $_POST['sort_order'] ) ? intval( $_POST['sort_order'] ) : 0;
		$image_id    = isset( $_POST['image_id'] ) && '' !== $_POST['image_id'] ? absint( $_POST['image_id'] ) : null;

		if ( $option_id ) {
			check_admin_referer( 'bbb_save_option_' . $option_id );
		} else {
			check_admin_referer( 'bbb_save_option_new_' . $group_id );
		}

		if ( ! $group_id || '' === $label ) {
			wp_die( 'A label is required.' );
		}

		global $wpdb;

		$options_table = $wpdb->prefix . 'bbb_options';

		$data = array(
			'group_id'    => $group_id,
			'label'       => $label,
			'price_delta' => $price_delta,
			'sort_order'  => $sort_order,
			'image_id'    => $image_id,
		);

		if ( $option_id ) {

			$wpdb->update( $options_table, $data, array( 'id' => $option_id ) );

		} else {

			$data['is_active'] = 1;
			$wpdb->insert( $options_table, $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=bbb-manage-options&saved=1' ) );
		exit;
	}

	/**
	 * Toggles an option between active and inactive. Inactive options
	 * are hidden from the customer-facing builder (see the WHERE
	 * is_active = 1 condition in class-bbb-shortcodes.php) but stay in
	 * the database, so past submissions that used them still make
	 * sense.
	 */
	public static function handle_toggle_active() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do this.' );
		}

		$option_id = isset( $_POST['option_id'] ) ? absint( $_POST['option_id'] ) : 0;

		check_admin_referer( 'bbb_toggle_option_active_' . $option_id );

		if ( ! $option_id ) {
			wp_die( 'Invalid request.' );
		}

		global $wpdb;

		$options_table = $wpdb->prefix . 'bbb_options';

		$current = $wpdb->get_var( $wpdb->prepare( "SELECT is_active FROM {$options_table} WHERE id = %d", $option_id ) );

		$wpdb->update(
			$options_table,
			array( 'is_active' => $current ? 0 : 1 ),
			array( 'id' => $option_id )
		);

		wp_safe_redirect( admin_url( 'admin.php?page=bbb-manage-options&saved=1' ) );
		exit;
	}

	/**
	 * Saves one new compatibility rule: "IF trigger_option_id is
	 * selected, THEN allowed_option_id becomes an allowed choice in
	 * its own group." Rejects a rule if the trigger and the allowed
	 * option are somehow in the same group (a group can't gate
	 * itself) or if either option doesn't exist.
	 */
	public static function handle_add_compatibility_rule() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do this.' );
		}

		$target_group_id   = isset( $_POST['target_group_id'] ) ? absint( $_POST['target_group_id'] ) : 0;
		$trigger_option_id = isset( $_POST['trigger_option_id'] ) ? absint( $_POST['trigger_option_id'] ) : 0;
		$allowed_option_id = isset( $_POST['allowed_option_id'] ) ? absint( $_POST['allowed_option_id'] ) : 0;

		check_admin_referer( 'bbb_add_compatibility_rule_' . $target_group_id );

		if ( ! $target_group_id || ! $trigger_option_id || ! $allowed_option_id ) {
			wp_die( 'Invalid request.' );
		}

		global $wpdb;

		$options_table = $wpdb->prefix . 'bbb_options';
		$rules_table   = $wpdb->prefix . 'bbb_compatibility_rules';

		$trigger_group_id = $wpdb->get_var( $wpdb->prepare( "SELECT group_id FROM {$options_table} WHERE id = %d", $trigger_option_id ) );
		$allowed_group_id = $wpdb->get_var( $wpdb->prepare( "SELECT group_id FROM {$options_table} WHERE id = %d", $allowed_option_id ) );

		if ( ! $trigger_group_id || ! $allowed_group_id ) {
			wp_die( 'One of the selected options no longer exists.' );
		}

		if ( (int) $allowed_group_id !== $target_group_id ) {
			wp_die( 'The allowed option must belong to this group.' );
		}

		if ( (int) $trigger_group_id === $target_group_id ) {
			wp_die( 'A group cannot gate itself - the trigger must come from a different group.' );
		}

		$wpdb->insert(
			$rules_table,
			array(
				'trigger_option_id' => $trigger_option_id,
				'target_group_id'   => $target_group_id,
				'allowed_option_id' => $allowed_option_id,
				'created_at'        => current_time( 'mysql' ),
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=bbb-manage-options&rule_saved=1' ) );
		exit;
	}

	/**
	 * Deletes one compatibility rule.
	 */
	public static function handle_delete_compatibility_rule() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to do this.' );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;

		check_admin_referer( 'bbb_delete_compatibility_rule_' . $rule_id );

		if ( ! $rule_id ) {
			wp_die( 'Invalid request.' );
		}

		global $wpdb;

		$rules_table = $wpdb->prefix . 'bbb_compatibility_rules';

		$wpdb->delete( $rules_table, array( 'id' => $rule_id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=bbb-manage-options&rule_deleted=1' ) );
		exit;
	}
}
