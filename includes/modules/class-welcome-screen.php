<?php
/**
 * Module: Dashboard Welcome Templates by Role.
 *
 * Replaces the default WordPress welcome panel with a Beaver Builder
 * layout. Supports per-role assignment with a default fallback.
 *
 * @since 0.3.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Welcome_Screen
 *
 * @since 0.3.0
 */
final class OneDog_BBCA_Welcome_Screen {

	/**
	 * Option key for the role-to-template mapping.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'onedog_bbca_template';

	/**
	 * Saved template mapping.
	 *
	 * @var array
	 */
	private static $template;

	/**
	 * Current user's primary role.
	 *
	 * @var string
	 */
	private static $current_role;

	/**
	 * Additional CSS classes for the panel wrapper.
	 *
	 * @var string
	 */
	private static $classes = '';

	/**
	 * Initializes hooks.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'maybe_replace_panel' ] );
		add_action( 'plugins_loaded', [ __CLASS__, 'register_assets' ], 30 );
	}

	/**
	 * Conditionally replaces the welcome panel on admin_init.
	 *
	 * Resolution order:
	 * 1. Role-specific template (e.g., 'editor' => 'my-layout')
	 * 2. Default fallback template ('_default' key)
	 * 3. No replacement (default WP welcome panel remains)
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function maybe_replace_panel() {
		self::$current_role = self::get_current_role();
		self::$template     = get_option( self::OPTION_KEY, [] );

		if ( ! is_array( self::$template ) ) {
			return;
		}

		$layout = self::resolve_layout();

		if ( empty( $layout ) || 'none' === $layout ) {
			return;
		}

		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		add_action( 'welcome_panel', [ __CLASS__, 'render_panel' ] );

		// For users who cannot dismiss the panel natively, reposition via JS.
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			self::$classes = 'welcome-panel';
			add_action( 'admin_notices', [ __CLASS__, 'render_panel' ] );
		}
	}

	/**
	 * Resolves the layout slug for the current user.
	 *
	 * @since 0.3.0
	 * @return string Layout slug or empty string.
	 */
	private static function resolve_layout() {
		// 1. Role-specific assignment.
		if ( isset( self::$template[ self::$current_role ] )
			&& 'none' !== self::$template[ self::$current_role ] ) {
			return self::$template[ self::$current_role ];
		}

		// 2. Default fallback.
		if ( isset( self::$template['_default'] )
			&& 'none' !== self::$template['_default'] ) {
			return self::$template['_default'];
		}

		return '';
	}

	/**
	 * Registers frontend asset hooks for the dashboard.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function register_assets() {
		global $pagenow;

		if ( 'index.php' === $pagenow && class_exists( 'FLBuilder' ) ) {
			add_action( 'admin_enqueue_scripts', 'FLBuilder::register_layout_styles_scripts' );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		}
	}

	/**
	 * Enqueues dashboard panel CSS and JS.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function enqueue_assets() {
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
	 * Renders the custom welcome panel.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function render_panel() {
		$panel_file = BBCA_DIR . 'includes/welcome-panel.php';

		if ( file_exists( $panel_file ) ) {
			$layout_slug = self::resolve_layout();
			$classes     = self::$classes;
			include $panel_file;
		}
	}

	/**
	 * Returns the current user's primary role slug.
	 *
	 * @since 0.3.0
	 * @return string
	 */
	private static function get_current_role() {
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		return ! empty( $roles ) ? array_values( $roles )[0] : '';
	}
}

OneDog_BBCA_Welcome_Screen::init();
