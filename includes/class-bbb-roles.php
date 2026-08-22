<?php
/**
 * Bespoke Bike Builder — Staff Roles & Capabilities
 *
 * Registers the four roles defined in Blueprint Section 20
 * (Roles and Permissions) and their associated custom capabilities.
 * This is additive and self-contained: it creates/updates WordPress
 * roles on load, but does NOT modify any existing admin screen's
 * permission checks — those still gate on whatever capability they
 * were originally built with (see the note in get_setup_notice()
 * below for what remains to be wired up).
 *
 * Once registered, these roles appear automatically in the native
 * WordPress Users screen (Users → Add New / Edit User → Role
 * dropdown) — no custom UI is needed to assign them.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BBB_Roles {

	const CAPABILITIES = array(
		'manage_bbb_submissions',
		'manage_bbb_options',
		'manage_bbb_rules',
		'manage_bbb_imports',
		'manage_bbb_quotes',
		'manage_bbb_deposit_requests',
		'process_bbb_refunds',
		'manage_bbb_design',
		'manage_bbb_settings',
		'manage_bbb_integrations',
		'view_bbb_logs',
	);

	const ROLE_BUILD_MANAGER    = 'bbb_build_manager';
	const ROLE_SALES_STAFF      = 'bbb_sales_staff';
	const ROLE_OPTION_MANAGER   = 'bbb_option_manager';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_register_roles' ) );
	}

	/**
	 * Creates (or refreshes the capabilities of) the three custom
	 * roles, and ensures the Administrator role has every bbb_
	 * capability. Safe to run on every page load: add_role() is a
	 * no-op if the role already exists, and add_cap() is a no-op if
	 * the role already has that capability.
	 */
	public static function maybe_register_roles() {
		self::ensure_administrator_capabilities();
		self::ensure_role( self::ROLE_BUILD_MANAGER, 'Custom Build Manager', array(
			'read'                         => true,
			'manage_bbb_submissions'       => true,
			'manage_bbb_options'           => true,
			'manage_bbb_rules'             => true,
			'manage_bbb_imports'           => true,
			'manage_bbb_quotes'            => true,
			'manage_bbb_deposit_requests'  => true,
		) );

		self::ensure_role( self::ROLE_SALES_STAFF, 'Custom Build Sales Staff', array(
			'read'                         => true,
			'manage_bbb_submissions'       => true,
			'manage_bbb_quotes'            => true,
			'manage_bbb_deposit_requests'  => true,
		) );

		self::ensure_role( self::ROLE_OPTION_MANAGER, 'Custom Build Option Manager', array(
			'read'                => true,
			'manage_bbb_options'  => true,
			'manage_bbb_imports'  => true,
		) );
	}

	private static function ensure_administrator_capabilities() {
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}
		foreach ( self::CAPABILITIES as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}

	private static function ensure_role( $slug, $display_name, $capabilities ) {
		$role = get_role( $slug );

		if ( ! $role ) {
			add_role( $slug, $display_name, $capabilities );
			return;
		}

		// Role already exists (e.g. from a previous version of this
		// file) — make sure its capability set is up to date without
		// removing any capability an Administrator may have manually
		// granted it beyond what's listed here.
		foreach ( $capabilities as $cap => $grant ) {
			if ( $grant && ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Returns the current wiring status, for display on the BBB
	 * Notices settings page or elsewhere — an honest, visible record
	 * of what still needs manual follow-up rather than a silent gap.
	 */
	public static function get_setup_notice() {
		return 'Roles and capabilities are registered (Custom Build Manager, Custom Build Sales Staff, ' .
			'Custom Build Option Manager — assignable via Users → Add New/Edit User). ' .
			'The existing Manage Build Options, Header Settings and Notices & WhatsApp admin screens ' .
			'still require the "manage_options" (Administrator-only) capability, since they were built ' .
			'before these roles existed. To let Build Managers or Sales Staff access those specific ' .
			'screens, each screen\'s add_menu_page()/add_submenu_page() capability argument needs to be ' .
			'changed from "manage_options" to the matching bbb_ capability above.';
	}
}
