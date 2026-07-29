<?php
/**
 * Handles logic for the WordPress dashboard and admin settings.
 *
 * @since 0.1.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BB_Custom_Admin
 *
 * Replaces the default WordPress welcome panel with a Beaver Builder
 * layout, configurable per user role via BB's admin settings panel.
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
	 * Holds the Beaver Builder user templates data.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected static $templates;

	/**
	 * Holds all registered user roles.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected static $roles;

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
	 * Runs on admin_init: saves settings and conditionally replaces the welcome panel.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function admin_init() {
		self::save_settings();

		global $wp_roles;

		self::$roles        = $wp_roles->get_names();
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
	 * Registers front-end and settings hooks on plugins_loaded.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function init_hooks() {
		if ( ! is_admin() && ! class_exists( 'FLBuilder' ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'load_scripts' ] );

		global $pagenow;
		if ( 'index.php' === $pagenow && class_exists( 'FLBuilder' ) ) {
			add_action( 'admin_enqueue_scripts', 'FLBuilder::register_layout_styles_scripts' );
		}

		// Register settings tab inside Beaver Builder's admin settings.
		add_filter( 'fl_builder_admin_settings_nav_items', [ __CLASS__, 'bb_nav_items' ] );
		add_action( 'fl_builder_admin_settings_render_forms', [ __CLASS__, 'bb_nav_forms' ] );

		// Save settings via BB's settings save action.
		add_action( 'fl_builder_admin_settings_save', [ __CLASS__, 'save_settings' ] );
	}

	/**
	 * Enqueues admin CSS on the BB settings page.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function load_scripts() {
		if ( isset( $_GET['page'] ) && 'fl-builder-settings' === $_GET['page'] ) {
			wp_enqueue_style( 'onedog-bbca-style', BBCA_URL . 'assets/css/admin.css', [], BBCA_VER );
		}
	}

	/**
	 * Adds the "Custom Admin" tab to BB's settings navigation.
	 *
	 * @since 0.1.0
	 * @param array $items Existing nav items.
	 * @return array
	 */
	public static function bb_nav_items( $items ) {
		$items['onedog-bbca'] = [
			'title'    => __( 'Custom Admin', 'bb-custom-admin' ),
			'show'     => true,
			'priority' => 750,
		];

		return $items;
	}

	/**
	 * Renders the settings form inside BB's settings panel.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function bb_nav_forms() {
		self::$templates = self::get_bb_templates();
		require_once BBCA_DIR . 'includes/admin-settings.php';
	}

	/**
	 * Outputs the custom welcome panel content.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function welcome_panel() {
		include BBCA_DIR . 'includes/welcome-panel.php';
	}

	/**
	 * Saves the role-to-template mapping.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function save_settings() {
		if ( ! isset( $_POST['onedog-bbca-settings-nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['onedog-bbca-settings-nonce'] ) ), 'onedog-bbca-settings' ) ) {
			return;
		}

		$raw = isset( $_POST['onedog_bbca_template'] ) ? $_POST['onedog_bbca_template'] : [];

		if ( ! is_array( $raw ) ) {
			return;
		}

		$sanitized = array_map( 'sanitize_text_field', wp_unslash( $raw ) );

		update_option( 'onedog_bbca_template', $sanitized );
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

	/**
	 * Retrieves Beaver Builder layout templates.
	 *
	 * @since 0.1.0
	 * @param string $type Template taxonomy slug.
	 * @return array
	 */
	private static function get_bb_templates( $type = 'layout' ) {
		$templates = [];

		$posts = get_posts( [
			'post_type'      => 'fl-builder-template',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'posts_per_page' => '-1',
			'tax_query'      => [
				[
					'taxonomy' => 'fl-builder-template-type',
					'field'    => 'slug',
					'terms'    => $type,
				],
			],
		] );

		foreach ( $posts as $post ) {
			$templates[] = [
				'slug' => $post->post_name,
				'name' => $post->post_title,
			];
		}

		return $templates;
	}

	/**
	 * Returns the selected attribute for a select option.
	 *
	 * @since 0.1.0
	 * @param string $key   Role key.
	 * @param string $value Option value to compare.
	 * @param array  $data  Saved template data.
	 * @return string
	 */
	public static function get_selected( $key = '', $value = '', $data = [] ) {
		if ( is_array( $data ) && isset( $data[ $key ] ) && $data[ $key ] === $value ) {
			return ' selected="selected"';
		}

		if ( ( ! is_array( $data ) || count( $data ) === 0 ) && $key === $value ) {
			return ' selected="selected"';
		}

		return '';
	}
}

OneDog_BB_Custom_Admin::init();
