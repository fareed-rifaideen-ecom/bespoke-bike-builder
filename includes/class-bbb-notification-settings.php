<?php
/**
 * Bespoke Bike Builder — Notification Recipient Settings
 *
 * Implements the "notification recipients" requirement from Blueprint
 * Section 28 (Internal Notifications) and Section 34 (Required Client
 * Inputs): the shop's Sales Manager and any additional staff who
 * should be emailed whenever a new build request comes in should be
 * configurable by an Administrator, not hardcoded into plugin code.
 *
 * This class only owns the *setting* (where recipients are stored and
 * how they're validated). It doesn't send any email itself - other
 * classes (like BBB_Ajax) call BBB_Notification_Settings::get_recipients()
 * whenever they need to know who to notify.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBB_Notification_Settings {

	const OPTION_NAME = 'bbb_notification_recipients';

	// Used only the very first time this setting is read, before an
	// Administrator has saved anything - so behaviour never silently
	// changes for the one recipient who was already receiving these
	// emails via the old hardcoded address.
	const DEFAULT_RECIPIENT = 'fareed@thecyclehub.com';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
	}

	public static function register_settings_page() {
		add_options_page(
			'BBB Notifications',
			'BBB Notifications',
			'manage_options',
			'bbb-notifications',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function register_setting() {
		register_setting(
			'bbb_notification_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_recipients' ),
				'default'           => self::DEFAULT_RECIPIENT,
			)
		);
	}

	/**
	 * Accepts a comma-separated list of email addresses from the
	 * settings form, keeps only the ones that are actually valid
	 * emails, and stores them back as a clean comma-separated string.
	 * Silently dropping invalid entries (rather than rejecting the
	 * whole save) means one typo can't lock an Administrator out of
	 * saving the rest of a correct list.
	 *
	 * @param string $raw_value
	 * @return string
	 */
	public static function sanitize_recipients( $raw_value ) {

		$candidates = array_map( 'trim', explode( ',', (string) $raw_value ) );
		$valid      = array();

		foreach ( $candidates as $candidate ) {
			if ( is_email( $candidate ) ) {
				$valid[] = sanitize_email( $candidate );
			}
		}

		if ( empty( $valid ) ) {
			return self::DEFAULT_RECIPIENT;
		}

		return implode( ', ', array_unique( $valid ) );
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>Bespoke Bike Builder — Notifications</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'bbb_notification_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bbb_notification_recipients">Notify these email addresses</label></th>
						<td>
							<input
								type="text"
								id="bbb_notification_recipients"
								name="<?php echo esc_attr( self::OPTION_NAME ); ?>"
								value="<?php echo esc_attr( self::get_raw_value() ); ?>"
								class="regular-text"
							/>
							<p class="description">Separate multiple addresses with commas, e.g. sales@thecyclehub.com, manager@thecyclehub.com. Everyone listed here is emailed whenever a customer submits a new bike build request.</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns the raw, stored comma-separated string, for display in
	 * the settings field.
	 *
	 * @return string
	 */
	private static function get_raw_value() {
		$value = get_option( self::OPTION_NAME, self::DEFAULT_RECIPIENT );
		return $value ? $value : self::DEFAULT_RECIPIENT;
	}

	/**
	 * Returns the configured recipients as a clean array of email
	 * addresses, ready to pass straight into wp_mail(). Falls back to
	 * the original hardcoded address if nothing has been configured
	 * yet, so existing behaviour never breaks on upgrade.
	 *
	 * @return array
	 */
	public static function get_recipients() {

		$raw   = self::get_raw_value();
		$parts = array_map( 'trim', explode( ',', $raw ) );
		$valid = array_filter( $parts, 'is_email' );

		if ( empty( $valid ) ) {
			return array( self::DEFAULT_RECIPIENT );
		}

		return array_values( $valid );
	}
}
