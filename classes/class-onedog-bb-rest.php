<?php
/**
 * REST API endpoints for the settings React app.
 *
 * @since 0.2.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BB_REST
 *
 * @since 0.2.0
 */
final class OneDog_BB_REST {

	const NAMESPACE = 'onedog-bbca/v1';
	const CACHE_KEY = 'onedog_bbca_templates';
	const CACHE_TTL = 43200;

	/**
	 * Registers hooks.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
		add_action( 'save_post_fl-builder-template', [ __CLASS__, 'flush_cache' ] );
		add_action( 'deleted_post', [ __CLASS__, 'maybe_flush_cache' ] );
	}

	/**
	 * Registers all REST routes.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public static function register_routes() {
		// Welcome screen: layouts + settings.
		register_rest_route( self::NAMESPACE, '/layouts', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_layouts' ],
			'permission_callback' => [ __CLASS__, 'check_permission' ],
		] );

		register_rest_route( self::NAMESPACE, '/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_settings' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'save_settings' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
		] );

		// Modules.
		register_rest_route( self::NAMESPACE, '/modules', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_modules' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'save_modules' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
		] );

		// Menu & toolbar visibility.
		register_rest_route( self::NAMESPACE, '/menu-visibility', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_menu_visibility' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'save_menu_visibility' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
		] );

		// Notice cleaner.
		register_rest_route( self::NAMESPACE, '/notice-cleaner', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_notice_cleaner' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'save_notice_cleaner' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
		] );
	}

	/**
	 * Permission check.
	 *
	 * @since 0.2.0
	 * @return bool|WP_Error
	 */
	public static function check_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Permission denied.', 'bb-custom-admin' ), [ 'status' => 403 ] );
		}
		return true;
	}

	/*
	|--------------------------------------------------------------------------
	| Welcome Screen Endpoints
	|--------------------------------------------------------------------------
	*/

	public static function get_layouts() {
		global $wp_roles;
		$bb_active = class_exists( 'FLBuilder' );
		$layouts   = $bb_active ? self::get_cached_templates() : [];

		return rest_ensure_response( [
			'layouts'   => $layouts,
			'roles'     => $wp_roles->get_names(),
			'bb_active' => $bb_active,
		] );
	}

	public static function get_settings() {
		$template = get_option( 'onedog_bbca_template', [] );
		return rest_ensure_response( [ 'template' => is_array( $template ) ? $template : [] ] );
	}

	public static function save_settings( $request ) {
		$template = $request->get_param( 'template' );

		if ( ! is_array( $template ) ) {
			return new WP_Error( 'invalid_data', __( 'Template data must be an array.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		$sanitized = array_map( 'sanitize_text_field', $template );
		update_option( 'onedog_bbca_template', $sanitized );

		return rest_ensure_response( [ 'success' => true, 'template' => $sanitized ] );
	}

	/*
	|--------------------------------------------------------------------------
	| Module Endpoints
	|--------------------------------------------------------------------------
	*/

	public static function get_modules() {
		return rest_ensure_response( [ 'modules' => OneDog_BBCA_Module_Loader::get_all_modules() ] );
	}

	public static function save_modules( $request ) {
		$modules = $request->get_param( 'modules' );

		if ( ! is_array( $modules ) ) {
			return new WP_Error( 'invalid_data', __( 'Modules must be an array.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		$saved = OneDog_BBCA_Module_Loader::save_enabled_modules( $modules );
		return rest_ensure_response( [ 'success' => true, 'modules' => $saved ] );
	}

	/*
	|--------------------------------------------------------------------------
	| Menu & Toolbar Visibility Endpoints
	|--------------------------------------------------------------------------
	*/

	public static function get_menu_visibility() {
		global $wp_roles;

		$menu_rules    = get_option( 'onedog_bbca_menu_visibility', [] );
		$toolbar_rules = get_option( 'onedog_bbca_toolbar_visibility', [] );

		// Available items for the UI.
		$available_menus    = [];
		$available_toolbar  = [];

		if ( class_exists( 'OneDog_BBCA_Menu_Visibility' ) ) {
			$available_menus   = OneDog_BBCA_Menu_Visibility::get_available_menus();
			$available_toolbar = OneDog_BBCA_Menu_Visibility::get_available_toolbar_nodes();
		}

		return rest_ensure_response( [
			'menu_rules'        => is_array( $menu_rules ) ? $menu_rules : [],
			'toolbar_rules'     => is_array( $toolbar_rules ) ? $toolbar_rules : [],
			'available_menus'   => $available_menus,
			'available_toolbar' => $available_toolbar,
			'roles'             => $wp_roles->get_names(),
		] );
	}

	public static function save_menu_visibility( $request ) {
		$menu_rules    = $request->get_param( 'menu_rules' );
		$toolbar_rules = $request->get_param( 'toolbar_rules' );

		if ( is_array( $menu_rules ) ) {
			$sanitized_menu = [];
			foreach ( $menu_rules as $role => $items ) {
				$role_key = sanitize_text_field( $role );
				$sanitized_menu[ $role_key ] = is_array( $items )
					? array_map( 'sanitize_text_field', $items )
					: [];
			}
			update_option( 'onedog_bbca_menu_visibility', $sanitized_menu );
		}

		if ( is_array( $toolbar_rules ) ) {
			$sanitized_toolbar = [];
			foreach ( $toolbar_rules as $role => $nodes ) {
				$role_key = sanitize_text_field( $role );
				$sanitized_toolbar[ $role_key ] = is_array( $nodes )
					? array_map( 'sanitize_text_field', $nodes )
					: [];
			}
			update_option( 'onedog_bbca_toolbar_visibility', $sanitized_toolbar );
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	/*
	|--------------------------------------------------------------------------
	| Notice Cleaner Endpoints
	|--------------------------------------------------------------------------
	*/

	public static function get_notice_cleaner() {
		global $wp_roles;

		$settings = get_option( 'onedog_bbca_notice_cleaner', [] );

		$defaults = [
			'hide_update_notices'      => false,
			'hide_core_alerts'         => false,
			'remove_wp_logo'           => false,
			'remove_toolbar_dropdowns' => false,
			'excluded_roles'           => [],
		];

		$settings = wp_parse_args( is_array( $settings ) ? $settings : [], $defaults );

		return rest_ensure_response( [
			'settings' => $settings,
			'roles'    => $wp_roles->get_names(),
		] );
	}

	public static function save_notice_cleaner( $request ) {
		$settings = $request->get_param( 'settings' );

		if ( ! is_array( $settings ) ) {
			return new WP_Error( 'invalid_data', __( 'Settings must be an array.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		$sanitized = [
			'hide_update_notices'      => ! empty( $settings['hide_update_notices'] ),
			'hide_core_alerts'         => ! empty( $settings['hide_core_alerts'] ),
			'remove_wp_logo'           => ! empty( $settings['remove_wp_logo'] ),
			'remove_toolbar_dropdowns' => ! empty( $settings['remove_toolbar_dropdowns'] ),
			'excluded_roles'           => isset( $settings['excluded_roles'] ) && is_array( $settings['excluded_roles'] )
				? array_map( 'sanitize_text_field', $settings['excluded_roles'] )
				: [],
		];

		update_option( 'onedog_bbca_notice_cleaner', $sanitized );

		return rest_ensure_response( [ 'success' => true, 'settings' => $sanitized ] );
	}

	/*
	|--------------------------------------------------------------------------
	| Template Cache Helpers
	|--------------------------------------------------------------------------
	*/

	public static function get_cached_templates() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}
		$templates = self::fetch_templates();
		set_transient( self::CACHE_KEY, $templates, self::CACHE_TTL );
		return $templates;
	}

	private static function fetch_templates() {
		$templates = [];
		$posts = get_posts( [
			'post_type'        => 'fl-builder-template',
			'orderby'          => 'title',
			'order'            => 'ASC',
			'posts_per_page'   => '-1',
			'suppress_filters' => true,
			'no_found_rows'    => true,
			'tax_query'        => [ [ 'taxonomy' => 'fl-builder-template-type', 'field' => 'slug', 'terms' => 'layout' ] ],
		] );

		foreach ( $posts as $post ) {
			$templates[] = [ 'slug' => $post->post_name, 'name' => $post->post_title ];
		}
		return $templates;
	}

	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	public static function maybe_flush_cache( $post_id ) {
		if ( 'fl-builder-template' === get_post_type( $post_id ) ) {
			self::flush_cache();
		}
	}
}

OneDog_BB_REST::init();
