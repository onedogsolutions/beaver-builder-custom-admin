<?php
/**
 * Handles logic for the WordPress dashboard welcome panel.
 *
 * @since 0.1.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BB_Custom_Admin
 *
 * Replaces the default WordPress welcome panel with a Beaver Builder
 * layout, configurable per user role via the plugin settings page.
 *
 * @since 0.1.0
 */
final class OneDog_BB_Custom_Admin {

	/**
	 * Holds the saved template mapping (role => layout slug).
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected static $template;

	/**
	 * Holds the current user's primary role.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected static $current_role;

	/**
	 * Holds additional CSS classes for the panel wrapper.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected static $classes;

	/**
	 * Initializes hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'admin_init' ] );
		add_action( 'plugins_loaded', [ __CLASS__, 'init_hooks' ] );
	}

	/**
	 * Runs on admin_init: conditionally replaces the welcome panel.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function admin_init() {
		self::$current_role = self::get_current_role();
		self::$template     = get_option( 'onedog_bbca_template' );

		if ( is_array( self::$template )
			&& isset( self::$template[ self::$current_role ] )
			&& 'none' !== self::$template[ self::$current_role ] ) {

			remove_action( 'welcome_panel', 'wp_welcome_panel' );
			add_action( 'welcome_panel', [ __CLASS__, 'welcome_panel' ] );

			if ( ! current_user_can( 'edit_theme_options' ) ) {
				self::$classes = 'welcome-panel';
				add_action( 'admin_notices', [ __CLASS__, 'welcome_panel' ] );
			}
		}
	}

	/**
	 * Registers front-end asset hooks on plugins_loaded.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init_hooks() {
		global $pagenow;

		if ( 'index.php' === $pagenow && class_exists( 'FLBuilder' ) ) {
			add_action( 'admin_enqueue_scripts', 'FLBuilder::register_layout_styles_scripts' );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_frontend_assets' ] );
		}
	}

	/**
	 * Enqueues dashboard panel CSS and JS.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public static function enqueue_frontend_assets() {
		wp_enqueue_style(
			'onedog-bbca-frontend',
			BBCA_URL . 'assets/css/frontend.css',
			[],
			BBCA_VER
		);

		wp_enqueue_script(
			'onedog-bbca-frontend',
			BBCA_URL . 'assets/js/frontend.js',
			[],
			BBCA_VER,
			[
				'strategy'  => 'defer',
				'in_footer' => true,
			]
		);
	}

	/**
	 * Outputs the custom welcome panel content.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function welcome_panel() {
		$panel_file = BBCA_DIR . 'includes/welcome-panel.php';

		if ( file_exists( $panel_file ) ) {
			include $panel_file;
		}
	}

	/**
	 * Returns the current user's primary role slug.
	 *
	 * @since 0.1.0
	 * @return string
	 */
	private static function get_current_role() {
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		return ! empty( $roles ) ? array_values( $roles )[0] : '';
	}
}

OneDog_BB_Custom_Admin::init();
