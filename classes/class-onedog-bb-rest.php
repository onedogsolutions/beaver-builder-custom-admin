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
		// Layouts (used by Dashboard Canvas settings).
		register_rest_route( self::NAMESPACE, '/layouts', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'get_layouts' ],
			'permission_callback' => [ __CLASS__, 'check_permission' ],
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

		// Role Editor endpoints.
		register_rest_route( self::NAMESPACE, '/roles', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_roles' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'add_role' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/roles/(?P<role>[a-z0-9_]+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_role_capabilities' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'save_role_capabilities' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
			[
				'methods'             => 'DELETE',
				'callback'            => [ __CLASS__, 'delete_role' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/roles/(?P<role>[a-z0-9_]+)/clear', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'clear_role_capabilities' ],
			'permission_callback' => [ __CLASS__, 'check_permission' ],
		] );

		register_rest_route( self::NAMESPACE, '/roles/(?P<role>[a-z0-9_]+)/rollback', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'rollback_role' ],
			'permission_callback' => [ __CLASS__, 'check_permission' ],
		] );

		register_rest_route( self::NAMESPACE, '/roles/(?P<role>[a-z0-9_]+)/rename', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'rename_role' ],
			'permission_callback' => [ __CLASS__, 'check_permission' ],
		] );

		// Import / Export.
		register_rest_route( self::NAMESPACE, '/export', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'export_config' ],
			'permission_callback' => [ __CLASS__, 'check_permission' ],
		] );

		register_rest_route( self::NAMESPACE, '/import', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'import_config' ],
			'permission_callback' => [ __CLASS__, 'check_permission' ],
		] );

		register_rest_route( self::NAMESPACE, '/import-ure', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'import_ure_config' ],
			'permission_callback' => [ __CLASS__, 'check_permission' ],
		] );

		// Dashboard Canvas.
		register_rest_route( self::NAMESPACE, '/dashboard-canvas', [
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_dashboard_canvas' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'save_dashboard_canvas' ],
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
	| Layouts Endpoint
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
			$templates[] = [
				'id'   => $post->ID,
				'slug' => $post->post_name,
				'name' => $post->post_title,
			];
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

	/*
	|--------------------------------------------------------------------------
	| Role Editor Endpoints
	|--------------------------------------------------------------------------
	*/

	public static function get_roles() {
		if ( ! class_exists( 'OneDog_BBCA_Role_Editor' ) ) {
			return new WP_Error( 'module_disabled', __( 'Role Editor module is not enabled.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}
		return rest_ensure_response( [ 'roles' => OneDog_BBCA_Role_Editor::get_roles() ] );
	}

	public static function get_role_capabilities( $request ) {
		if ( ! class_exists( 'OneDog_BBCA_Role_Editor' ) ) {
			return new WP_Error( 'module_disabled', __( 'Role Editor module is not enabled.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}
		$role = sanitize_key( $request->get_param( 'role' ) );
		return rest_ensure_response( [ 'capabilities' => OneDog_BBCA_Role_Editor::get_role_capabilities( $role ) ] );
	}

	public static function save_role_capabilities( $request ) {
		if ( ! class_exists( 'OneDog_BBCA_Role_Editor' ) ) {
			return new WP_Error( 'module_disabled', __( 'Role Editor module is not enabled.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}
		$role = sanitize_key( $request->get_param( 'role' ) );
		$caps = $request->get_param( 'capabilities' );

		if ( ! is_array( $caps ) ) {
			return new WP_Error( 'invalid_data', __( 'Capabilities must be an array.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		$result = OneDog_BBCA_Role_Editor::save_role_capabilities( $role, $caps );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	public static function clear_role_capabilities( $request ) {
		if ( ! class_exists( 'OneDog_BBCA_Role_Editor' ) ) {
			return new WP_Error( 'module_disabled', __( 'Role Editor module is not enabled.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}
		$role = sanitize_key( $request->get_param( 'role' ) );
		$result = OneDog_BBCA_Role_Editor::clear_role_capabilities( $role );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	public static function rollback_role( $request ) {
		if ( ! class_exists( 'OneDog_BBCA_Role_Editor' ) ) {
			return new WP_Error( 'module_disabled', __( 'Role Editor module is not enabled.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}
		$role = sanitize_key( $request->get_param( 'role' ) );
		$result = OneDog_BBCA_Role_Editor::rollback_role( $role );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( [ 'success' => true, 'capabilities' => $result ] );
	}

	public static function add_role( $request ) {
		if ( ! class_exists( 'OneDog_BBCA_Role_Editor' ) ) {
			return new WP_Error( 'module_disabled', __( 'Role Editor module is not enabled.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}
		$name = sanitize_text_field( $request->get_param( 'name' ) );
		$slug = sanitize_key( $request->get_param( 'slug' ) ?? '' );
		$clone = sanitize_key( $request->get_param( 'clone_from' ) ?? '' );

		$result = OneDog_BBCA_Role_Editor::add_role( $name, $slug, $clone );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function rename_role( $request ) {
		if ( ! class_exists( 'OneDog_BBCA_Role_Editor' ) ) {
			return new WP_Error( 'module_disabled', __( 'Role Editor module is not enabled.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}
		$role = sanitize_key( $request->get_param( 'role' ) );
		$label = sanitize_text_field( $request->get_param( 'label' ) );

		$result = OneDog_BBCA_Role_Editor::rename_role( $role, $label );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function delete_role( $request ) {
		if ( ! class_exists( 'OneDog_BBCA_Role_Editor' ) ) {
			return new WP_Error( 'module_disabled', __( 'Role Editor module is not enabled.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}
		$role = sanitize_key( $request->get_param( 'role' ) );
		$result = OneDog_BBCA_Role_Editor::delete_role( $role );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/*
	|--------------------------------------------------------------------------
	| Import / Export Endpoints
	|--------------------------------------------------------------------------
	*/

	public static function export_config() {
		global $wp_roles;

		$config = [
			'version' => BBCA_VER,
			'exported_at' => gmdate( 'Y-m-d H:i:s' ),
			'roles' => [],
			'menu_rules' => get_option( 'onedog_bbca_menu_visibility', [] ),
			'toolbar_rules' => get_option( 'onedog_bbca_toolbar_visibility', [] ),
			'modules' => get_option( 'onedog_bbca_modules', [] ),
			'notice_cleaner' => get_option( 'onedog_bbca_notice_cleaner', [] ),
			'canvas_layout_id' => absint( get_option( 'onedog_bbca_canvas_layout_id', 0 ) ),
			'canvas_target_roles' => (array) get_option( 'onedog_bbca_canvas_target_roles', [] ),
			'canvas_enable_squash' => (bool) get_option( 'onedog_bbca_canvas_enable_squash', false ),
			'canvas_hide_wp_branding' => (bool) get_option( 'onedog_bbca_canvas_hide_wp_branding', false ),
			'canvas_full_bleed_rows' => (bool) get_option( 'onedog_bbca_canvas_full_bleed_rows', false ),
		];

		// Export all role capabilities.
		foreach ( $wp_roles->roles as $slug => $role ) {
			$config['roles'][ $slug ] = [
				'name' => $role['name'],
				'capabilities' => $role['capabilities'],
			];
		}

		return rest_ensure_response( $config );
	}

	public static function import_config( $request ) {
		$config = $request->get_json_params();

		if ( empty( $config ) || ! is_array( $config ) ) {
			return new WP_Error( 'invalid_data', __( 'Invalid configuration data.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		// Import roles.
		if ( isset( $config['roles'] ) && is_array( $config['roles'] ) ) {
			self::apply_roles( $config['roles'] );
		}

		// Import menu rules.
		if ( isset( $config['menu_rules'] ) && is_array( $config['menu_rules'] ) ) {
			update_option( 'onedog_bbca_menu_visibility', $config['menu_rules'] );
		}

		// Import toolbar rules.
		if ( isset( $config['toolbar_rules'] ) && is_array( $config['toolbar_rules'] ) ) {
			update_option( 'onedog_bbca_toolbar_visibility', $config['toolbar_rules'] );
		}

		// Import modules.
		if ( isset( $config['modules'] ) && is_array( $config['modules'] ) ) {
			update_option( 'onedog_bbca_modules', array_map( 'sanitize_text_field', $config['modules'] ) );
		}

		// Import notice cleaner settings.
		if ( isset( $config['notice_cleaner'] ) && is_array( $config['notice_cleaner'] ) ) {
			update_option( 'onedog_bbca_notice_cleaner', $config['notice_cleaner'] );
		}

		// Import dashboard canvas settings.
		if ( isset( $config['canvas_layout_id'] ) ) {
			update_option( 'onedog_bbca_canvas_layout_id', absint( $config['canvas_layout_id'] ) );
		}
		if ( isset( $config['canvas_target_roles'] ) && is_array( $config['canvas_target_roles'] ) ) {
			update_option( 'onedog_bbca_canvas_target_roles', array_map( 'sanitize_text_field', $config['canvas_target_roles'] ) );
		}
		if ( isset( $config['canvas_enable_squash'] ) ) {
			update_option( 'onedog_bbca_canvas_enable_squash', ! empty( $config['canvas_enable_squash'] ) );
		}
		if ( isset( $config['canvas_hide_wp_branding'] ) ) {
			update_option( 'onedog_bbca_canvas_hide_wp_branding', ! empty( $config['canvas_hide_wp_branding'] ) );
		}
		if ( isset( $config['canvas_full_bleed_rows'] ) ) {
			update_option( 'onedog_bbca_canvas_full_bleed_rows', ! empty( $config['canvas_full_bleed_rows'] ) );
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Imports roles from a User Role Editor (Pro) export file.
	 *
	 * Expects the raw file contents posted as `content`.
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function import_ure_config( $request ) {
		if ( ! class_exists( 'OneDog_BBCA_Role_Editor' ) ) {
			return new WP_Error( 'module_disabled', __( 'Role Editor module is not enabled.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		$content = trim( (string) $request->get_param( 'content' ) );

		if ( '' === $content ) {
			return new WP_Error( 'invalid_data', __( 'No import data received.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		$roles = self::parse_ure_export( $content );

		if ( is_wp_error( $roles ) ) {
			return $roles;
		}

		$result = self::apply_roles( $roles );

		return rest_ensure_response( [
			'success'  => true,
			'imported' => $result['imported'],
			'skipped'  => $result['skipped'],
		] );
	}

	/**
	 * Parses a User Role Editor (Pro) export file into a roles array.
	 *
	 * URE Pro exports are base64-encoded JSON with base64-encoded role
	 * names: { "roles": { slug: { name, capabilities } }, "addons": ... }.
	 * Plain (non base64-wrapped) JSON exports are also accepted. URE Pro
	 * addon data (admin menu access, etc.) is intentionally ignored.
	 *
	 * @since 1.3.0
	 * @param string $content Raw file contents.
	 * @return array|WP_Error Roles shaped as slug => [ name, capabilities ].
	 */
	private static function parse_ure_export( $content ) {
		$decoded = base64_decode( $content, true );

		$data = null;
		if ( false !== $decoded ) {
			$data = json_decode( trim( $decoded ), true );
		}
		if ( ! is_array( $data ) ) {
			$data = json_decode( $content, true );
		}

		if ( ! is_array( $data ) || empty( $data['roles'] ) || ! is_array( $data['roles'] ) ) {
			return new WP_Error(
				'invalid_format',
				__( 'Unrecognized file format. Expected a User Role Editor export (.dat) file.', 'bb-custom-admin' ),
				[ 'status' => 400 ]
			);
		}

		$roles = [];

		foreach ( $data['roles'] as $slug => $role_data ) {
			if ( ! is_array( $role_data ) ) {
				continue;
			}

			$slug = self::sanitize_role_slug( (string) $slug );

			if ( '' === $slug ) {
				continue;
			}

			$name = sanitize_text_field( self::maybe_base64_decode( $role_data['name'] ?? $slug ) );

			$caps = [];
			if ( isset( $role_data['capabilities'] ) && is_array( $role_data['capabilities'] ) ) {
				foreach ( $role_data['capabilities'] as $cap => $granted ) {
					$cap = self::sanitize_cap( (string) $cap );
					if ( '' !== $cap ) {
						$caps[ $cap ] = filter_var( $granted, FILTER_VALIDATE_BOOLEAN );
					}
				}
			}

			$roles[ $slug ] = [
				'name'         => '' !== $name ? $name : $slug,
				'capabilities' => $caps,
			];
		}

		if ( empty( $roles ) ) {
			return new WP_Error(
				'no_roles',
				__( 'No valid roles found in the import file.', 'bb-custom-admin' ),
				[ 'status' => 400 ]
			);
		}

		return $roles;
	}

	/**
	 * Applies an imported roles map to WordPress.
	 *
	 * Existing roles have their capabilities replaced; missing roles are
	 * created. The administrator role is always protected.
	 *
	 * @since 1.3.0
	 * @param array $roles Roles shaped as slug => [ name, capabilities ].
	 * @return array { imported: string[], skipped: array[] }
	 */
	private static function apply_roles( $roles ) {
		$imported = [];
		$skipped  = [];

		foreach ( $roles as $slug => $role_data ) {
			$slug = self::sanitize_role_slug( (string) $slug );

			if ( '' === $slug ) {
				continue;
			}

			// Protect the administrator role from bulk imports.
			if ( 'administrator' === $slug ) {
				$skipped[] = [
					'role'   => $slug,
					'reason' => __( 'The administrator role is protected and was skipped.', 'bb-custom-admin' ),
				];
				continue;
			}

			$name = sanitize_text_field( $role_data['name'] ?? $slug );
			$caps = isset( $role_data['capabilities'] ) && is_array( $role_data['capabilities'] )
				? $role_data['capabilities']
				: [];

			$existing = get_role( $slug );
			if ( $existing ) {
				// Update existing role.
				foreach ( array_keys( $existing->capabilities ) as $cap ) {
					$existing->remove_cap( $cap );
				}
				foreach ( $caps as $cap => $granted ) {
					if ( $granted ) {
						$existing->add_cap( self::sanitize_cap( (string) $cap ) );
					}
				}
			} else {
				// Create new role.
				$sanitized_caps = [];
				foreach ( $caps as $cap => $granted ) {
					if ( $granted ) {
						$sanitized_caps[ self::sanitize_cap( (string) $cap ) ] = true;
					}
				}
				add_role( $slug, '' !== $name ? $name : $slug, $sanitized_caps );
			}

			$imported[] = $slug;
		}

		return [
			'imported' => $imported,
			'skipped'  => $skipped,
		];
	}

	/**
	 * Decodes a value that may be base64-encoded (URE Pro role names).
	 *
	 * @since 1.3.0
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function maybe_base64_decode( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$decoded = base64_decode( trim( $value ), true );

		return false !== $decoded ? $decoded : $value;
	}

	/**
	 * Sanitizes a role slug (lowercase letters, digits, underscores, hyphens).
	 *
	 * Note: sanitize_key() strips hyphens, but roles and capabilities from
	 * other plugins can contain them.
	 *
	 * @since 1.3.0
	 * @param string $slug Raw slug.
	 * @return string
	 */
	private static function sanitize_role_slug( $slug ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $slug ) );
	}

	/**
	 * Sanitizes a capability name.
	 *
	 * @since 1.3.0
	 * @param string $cap Raw capability.
	 * @return string
	 */
	private static function sanitize_cap( $cap ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $cap ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Dashboard Canvas Endpoints
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns the dashboard canvas settings.
	 *
	 * @since 1.3.0
	 * @return WP_REST_Response
	 */
	public static function get_dashboard_canvas() {
		return rest_ensure_response( [
			'settings' => [
				'layout_id'         => absint( get_option( 'onedog_bbca_canvas_layout_id', 0 ) ),
				'target_roles'      => (array) get_option( 'onedog_bbca_canvas_target_roles', [] ),
				'enable_squash'     => (bool) get_option( 'onedog_bbca_canvas_enable_squash', false ),
				'hide_wp_branding'  => (bool) get_option( 'onedog_bbca_canvas_hide_wp_branding', false ),
				'full_bleed_rows'   => (bool) get_option( 'onedog_bbca_canvas_full_bleed_rows', false ),
			],
		] );
	}

	/**
	 * Saves the dashboard canvas settings.
	 *
	 * @since 1.3.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_dashboard_canvas( $request ) {
		$settings = $request->get_param( 'settings' );

		if ( ! is_array( $settings ) ) {
			return new WP_Error( 'invalid_data', __( 'Settings must be an array.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		update_option( 'onedog_bbca_canvas_layout_id', absint( $settings['layout_id'] ?? 0 ) );

		update_option(
			'onedog_bbca_canvas_target_roles',
			isset( $settings['target_roles'] ) && is_array( $settings['target_roles'] )
				? array_map( 'sanitize_text_field', $settings['target_roles'] )
				: []
		);

		update_option( 'onedog_bbca_canvas_enable_squash', ! empty( $settings['enable_squash'] ) );
		update_option( 'onedog_bbca_canvas_hide_wp_branding', ! empty( $settings['hide_wp_branding'] ) );
		update_option( 'onedog_bbca_canvas_full_bleed_rows', ! empty( $settings['full_bleed_rows'] ) );

		return rest_ensure_response( [ 'success' => true ] );
	}
}

OneDog_BB_REST::init();
