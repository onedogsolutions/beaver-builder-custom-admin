<?php
/**
 * Module loader — registers and initializes active modules.
 *
 * @since 0.3.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Module_Loader
 *
 * Manages module registration and conditional loading based on
 * the enabled-modules option. Each module is a standalone class
 * that hooks into WordPress independently.
 *
 * @since 0.3.0
 */
final class OneDog_BBCA_Module_Loader {

	/**
	 * Option key storing enabled module slugs.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'onedog_bbca_modules';

	/**
	 * Registry of available modules.
	 *
	 * @var array<string, string> slug => class file path
	 */
	private static $registry = [];

	/**
	 * Initializes the module system.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function init() {
		self::register_defaults();
		add_action( 'plugins_loaded', [ __CLASS__, 'load_active' ], 20 );
	}

	/**
	 * Registers the default module set.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	private static function register_defaults() {
		self::$registry = [
			'role-editor'      => BBCA_DIR . 'includes/modules/class-role-editor.php',
			'welcome-screen'   => BBCA_DIR . 'includes/modules/class-welcome-screen.php',
			'menu-visibility'  => BBCA_DIR . 'includes/modules/class-menu-visibility.php',
			'notice-cleaner'   => BBCA_DIR . 'includes/modules/class-notice-cleaner.php',
			'option-cleaner'   => BBCA_DIR . 'includes/modules/class-option-cleaner.php',
		];
	}

	/**
	 * Loads all active modules.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function load_active() {
		$enabled = self::get_enabled_modules();

		foreach ( $enabled as $slug ) {
			if ( isset( self::$registry[ $slug ] ) && file_exists( self::$registry[ $slug ] ) ) {
				require_once self::$registry[ $slug ];
			}
		}
	}

	/**
	 * Returns the array of enabled module slugs.
	 *
	 * If no option exists yet, all modules default to enabled.
	 *
	 * @since 0.3.0
	 * @return array
	 */
	public static function get_enabled_modules() {
		$modules = get_option( self::OPTION_KEY );

		if ( false === $modules || ! is_array( $modules ) ) {
			return array_keys( self::$registry );
		}

		return array_values( array_intersect( $modules, array_keys( self::$registry ) ) );
	}

	/**
	 * Returns all registered module slugs with metadata.
	 *
	 * @since 0.3.0
	 * @return array
	 */
	public static function get_all_modules() {
		$enabled = self::get_enabled_modules();

		$meta = [
			'role-editor'     => [
				'label'       => __( 'Role & Capability Editor', 'bb-custom-admin' ),
				'description' => __( 'Manage WordPress roles and capabilities with rollback support.', 'bb-custom-admin' ),
			],
			'welcome-screen'  => [
				'label'       => __( 'Dashboard Welcome Templates', 'bb-custom-admin' ),
				'description' => __( 'Assign Beaver Builder layouts as the dashboard welcome panel per user role.', 'bb-custom-admin' ),
			],
			'menu-visibility' => [
				'label'       => __( 'Admin Menu & Toolbar Visibility', 'bb-custom-admin' ),
				'description' => __( 'Hide specific admin sidebar items and toolbar nodes by user role.', 'bb-custom-admin' ),
			],
			'notice-cleaner'  => [
				'label'       => __( 'Admin UI & Notice Cleaner', 'bb-custom-admin' ),
				'description' => __( 'Hide update notices, core alerts, and toolbar clutter for non-admin roles.', 'bb-custom-admin' ),
			],
			'option-cleaner'  => [
				'label'       => __( 'Orphaned Option Cleaner', 'bb-custom-admin' ),
				'description' => __( 'Detect and remove leftover wp_options entries from uninstalled plugins.', 'bb-custom-admin' ),
			],
		];

		$modules = [];
		foreach ( self::$registry as $slug => $path ) {
			$modules[] = [
				'slug'        => $slug,
				'label'       => $meta[ $slug ]['label'] ?? $slug,
				'description' => $meta[ $slug ]['description'] ?? '',
				'enabled'     => in_array( $slug, $enabled, true ),
			];
		}

		return $modules;
	}

	/**
	 * Saves the enabled modules list.
	 *
	 * @since 0.3.0
	 * @param array $modules Array of module slugs to enable.
	 * @return array Sanitized list of enabled modules.
	 */
	public static function save_enabled_modules( $modules ) {
		if ( ! is_array( $modules ) ) {
			$modules = [];
		}

		$sanitized = array_values(
			array_intersect(
				array_map( 'sanitize_text_field', $modules ),
				array_keys( self::$registry )
			)
		);

		update_option( self::OPTION_KEY, $sanitized );

		return $sanitized;
	}
}

OneDog_BBCA_Module_Loader::init();
