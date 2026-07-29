<?php
/**
 * Module: Admin UI & Notice Cleaner.
 *
 * Hides WordPress update notices, core admin alerts, and toolbar
 * clutter for non-administrator roles.
 *
 * @since 0.3.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Notice_Cleaner
 *
 * @since 0.3.0
 */
final class OneDog_BBCA_Notice_Cleaner {

	/**
	 * Option key for notice cleaner settings.
	 *
	 * Structure: {
	 *   'hide_update_notices': bool,
	 *   'hide_core_alerts': bool,
	 *   'remove_wp_logo': bool,
	 *   'remove_toolbar_dropdowns': bool,
	 *   'excluded_roles': string[]  (roles that still see everything)
	 * }
	 *
	 * @var string
	 */
	const OPTION_KEY = 'onedog_bbca_notice_cleaner';

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Initializes hooks.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'maybe_clean' ] );
	}

	/**
	 * Conditionally hooks removal actions based on settings and role.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function maybe_clean() {
		$settings = self::get_settings();

		// Administrators always see everything.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if the current role is excluded from cleaning.
		if ( self::is_role_excluded() ) {
			return;
		}

		if ( $settings['hide_update_notices'] ) {
			remove_action( 'admin_notices', 'update_nag', 3 );
			remove_action( 'admin_notices', 'maintenance_nag', 10 );
			add_filter( 'show_admin_bar', '__return_true' ); // Keep bar, just remove notices.
			add_action( 'admin_head', [ __CLASS__, 'hide_update_css' ] );
		}

		if ( $settings['hide_core_alerts'] ) {
			add_action( 'admin_head', [ __CLASS__, 'hide_core_alerts_css' ] );
			remove_action( 'admin_notices', 'wp_admin_notice', 10 );
		}

		if ( $settings['remove_wp_logo'] ) {
			add_action( 'wp_before_admin_bar_render', [ __CLASS__, 'remove_logo' ], 0 );
		}

		if ( $settings['remove_toolbar_dropdowns'] ) {
			add_action( 'wp_before_admin_bar_render', [ __CLASS__, 'remove_toolbar_dropdowns' ], 9998 );
		}
	}

	/**
	 * Removes the WordPress logo from the admin bar.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function remove_logo() {
		global $wp_admin_bar;

		if ( is_object( $wp_admin_bar ) ) {
			$wp_admin_bar->remove_node( 'wp-logo' );
		}
	}

	/**
	 * Removes common toolbar dropdown menus (comments, new content).
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function remove_toolbar_dropdowns() {
		global $wp_admin_bar;

		if ( ! is_object( $wp_admin_bar ) ) {
			return;
		}

		$wp_admin_bar->remove_node( 'comments' );
		$wp_admin_bar->remove_node( 'new-content' );
	}

	/**
	 * Outputs CSS to hide update notices that may still render.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function hide_update_css() {
		echo '<style id="onedog-bbca-hide-updates">.update-nag, .notice-warning.is-dismissible { display: none !important; }</style>';
	}

	/**
	 * Outputs CSS to hide core admin alert notices.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function hide_core_alerts_css() {
		echo '<style id="onedog-bbca-hide-alerts">.notice, .error, .updated { display: none !important; }</style>';
	}

	/**
	 * Returns the notice cleaner settings with defaults.
	 *
	 * @since 0.3.0
	 * @return array
	 */
	public static function get_settings() {
		if ( null !== self::$settings ) {
			return self::$settings;
		}

		$defaults = [
			'hide_update_notices'      => false,
			'hide_core_alerts'         => false,
			'remove_wp_logo'           => false,
			'remove_toolbar_dropdowns' => false,
			'excluded_roles'           => [],
		];

		$saved = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		self::$settings = wp_parse_args( $saved, $defaults );

		return self::$settings;
	}

	/**
	 * Checks if the current user's role is in the excluded list.
	 *
	 * @since 0.3.0
	 * @return bool
	 */
	private static function is_role_excluded() {
		$settings = self::get_settings();
		$user     = wp_get_current_user();
		$roles    = (array) $user->roles;

		if ( empty( $roles ) || empty( $settings['excluded_roles'] ) ) {
			return false;
		}

		return ! empty( array_intersect( $roles, (array) $settings['excluded_roles'] ) );
	}
}

OneDog_BBCA_Notice_Cleaner::init();
