<?php
/**
 * Bespoke Bike Builder — Notices & WhatsApp Settings
 *
 * Adds a WordPress Administrator-only settings screen where the
 * disclaimer/notice wording, the required Notes-step checkbox
 * wording, and the business WhatsApp number can all be edited
 * without touching any code. Defaults match the values agreed
 * for launch.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBB_Notices_Settings {

	const OPTION_WHATSAPP_NUMBER   = 'bbb_whatsapp_number';
	const OPTION_DISCLAIMER_TEXT   = 'bbb_disclaimer_text';
	const OPTION_CHECKBOX_TEXT     = 'bbb_notes_checkbox_text';
	const OPTION_WHATSAPP_MESSAGE  = 'bbb_whatsapp_default_message';
	const OPTION_WHATSAPP_SIZE_MSG = 'bbb_whatsapp_size_message';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function add_settings_page() {
		// Registered as its own independent top-level menu item, rather
		// than as a submenu of the existing Custom Builds/Dogma F admin
		// pages, so this never depends on knowing that menu's exact slug.
		add_menu_page(
			'BBB Notices & WhatsApp',
			'BBB Notices',
			'manage_options',
			'bbb-notices-settings',
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-format-chat',
			58
		);
	}

	public static function register_settings() {
		register_setting( 'bbb_notices_settings_group', self::OPTION_WHATSAPP_NUMBER, array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '+971524752638',
		) );

		register_setting( 'bbb_notices_settings_group', self::OPTION_DISCLAIMER_TEXT, array(
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => 'Your selected configuration is subject to availability, technical review and final confirmation by The Cycle Hub team. Submitting this request does not reserve any component or confirm an order.',
		) );

		register_setting( 'bbb_notices_settings_group', self::OPTION_CHECKBOX_TEXT, array(
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => 'I understand that this configuration is subject to availability and confirmation by The Cycle Hub team. Deposit terms apply.',
		) );

		register_setting( 'bbb_notices_settings_group', self::OPTION_WHATSAPP_MESSAGE, array(
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => 'Hi, I need help with my Dogma F build.',
		) );

		register_setting( 'bbb_notices_settings_group', self::OPTION_WHATSAPP_SIZE_MSG, array(
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => 'Hi, I need help choosing the right frame size for my Dogma F build.',
		) );
	}

	public static function get_whatsapp_number() {
		return get_option( self::OPTION_WHATSAPP_NUMBER, '+971524752638' );
	}

	public static function get_disclaimer_text() {
		return get_option( self::OPTION_DISCLAIMER_TEXT, 'Your selected configuration is subject to availability, technical review and final confirmation by The Cycle Hub team. Submitting this request does not reserve any component or confirm an order.' );
	}

	public static function get_checkbox_text() {
		return get_option( self::OPTION_CHECKBOX_TEXT, 'I understand that this configuration is subject to availability and confirmation by The Cycle Hub team. Deposit terms apply.' );
	}

	public static function get_whatsapp_message() {
		return get_option( self::OPTION_WHATSAPP_MESSAGE, 'Hi, I need help with my Dogma F build.' );
	}

	public static function get_whatsapp_size_message() {
		return get_option( self::OPTION_WHATSAPP_SIZE_MSG, 'Hi, I need help choosing the right frame size for my Dogma F build.' );
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Notices &amp; WhatsApp Settings</h1>
			<p>Controls the disclaimer wording shown to customers, the required agreement checkbox text, and the WhatsApp number/messages used across the Dogma F builder.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'bbb_notices_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bbb_whatsapp_number">WhatsApp Number</label></th>
						<td>
							<input type="text" id="bbb_whatsapp_number" name="<?php echo esc_attr( self::OPTION_WHATSAPP_NUMBER ); ?>" value="<?php echo esc_attr( self::get_whatsapp_number() ); ?>" class="regular-text" />
							<p class="description">Include the country code, e.g. +971524752638.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbb_disclaimer_text">Disclaimer / Review Notice</label></th>
						<td>
							<textarea id="bbb_disclaimer_text" name="<?php echo esc_attr( self::OPTION_DISCLAIMER_TEXT ); ?>" rows="4" class="large-text"><?php echo esc_textarea( self::get_disclaimer_text() ); ?></textarea>
							<p class="description">Shown at the top of the builder, on the Review step, and on the confirmation screen.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbb_notes_checkbox_text">Notes Step Agreement Checkbox</label></th>
						<td>
							<textarea id="bbb_notes_checkbox_text" name="<?php echo esc_attr( self::OPTION_CHECKBOX_TEXT ); ?>" rows="3" class="large-text"><?php echo esc_textarea( self::get_checkbox_text() ); ?></textarea>
							<p class="description">Shown next to the required agreement checkbox on the Notes step.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbb_whatsapp_default_message">Default WhatsApp Message</label></th>
						<td>
							<textarea id="bbb_whatsapp_default_message" name="<?php echo esc_attr( self::OPTION_WHATSAPP_MESSAGE ); ?>" rows="2" class="large-text"><?php echo esc_textarea( self::get_whatsapp_message() ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bbb_whatsapp_size_message">Frame Size Help — WhatsApp Message</label></th>
						<td>
							<textarea id="bbb_whatsapp_size_message" name="<?php echo esc_attr( self::OPTION_WHATSAPP_SIZE_MSG ); ?>" rows="2" class="large-text"><?php echo esc_textarea( self::get_whatsapp_size_message() ); ?></textarea>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
