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
 * Provides REST routes for fetching BB layouts and managing
 * the role-to-template mapping used by the React settings UI.
 *
 * @since 0.2.0
 */
final class OneDog_BB_REST {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'onedog-bbca/v1';

	/**
	 * Transient key for cached layouts.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'onedog_bbca_templates';

	/**
	 * Cache TTL in seconds (12 hours).
	 *
	 * @var int
	 */
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
	 * Registers REST routes.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/layouts',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_layouts' ],
				'permission_callback' => [ __CLASS__, 'check_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ __CLASS__, 'get_settings' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ __CLASS__, 'save_settings' ],
					'permission_callback' => [ __CLASS__, 'check_permission' ],
					'args'                => [
						'template' => [
							'required'          => true,
							'validate_callback' => [ __CLASS__, 'validate_template' ],
						],
					],
				],
			]
		);
	}

	/**
	 * Permission check: requires manage_options.
	 *
	 * @since 0.2.0
	 * @return bool|WP_Error
	 */
	public static function check_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'bb-custom-admin' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	/**
	 * GET /layouts — returns BB layout templates and user roles.
	 *
	 * @since 0.2.0
	 * @return WP_REST_Response
	 */
	public static function get_layouts() {
		global $wp_roles;

		$bb_active = class_exists( 'FLBuilder' );
		$layouts   = $bb_active ? self::get_cached_templates() : [];

		return rest_ensure_response(
			[
				'layouts'   => $layouts,
				'roles'     => $wp_roles->get_names(),
				'bb_active' => $bb_active,
			]
		);
	}

	/**
	 * GET /settings — returns current role-to-template mapping.
	 *
	 * @since 0.2.0
	 * @return WP_REST_Response
	 */
	public static function get_settings() {
		$template = get_option( 'onedog_bbca_template', [] );

		return rest_ensure_response(
			[
				'template' => is_array( $template ) ? $template : [],
			]
		);
	}

	/**
	 * POST /settings — saves the role-to-template mapping.
	 *
	 * @since 0.2.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_settings( $request ) {
		$template = $request->get_param( 'template' );

		if ( ! is_array( $template ) ) {
			return new WP_Error(
				'invalid_data',
				__( 'Template data must be an array.', 'bb-custom-admin' ),
				[ 'status' => 400 ]
			);
		}

		$sanitized = array_map( 'sanitize_text_field', $template );

		update_option( 'onedog_bbca_template', $sanitized );

		return rest_ensure_response(
			[
				'success'  => true,
				'template' => $sanitized,
			]
		);
	}

	/**
	 * Validates the template parameter is an array of strings.
	 *
	 * @since 0.2.0
	 * @param mixed           $value   Parameter value.
	 * @param WP_REST_Request $request Request object.
	 * @param string          $param   Parameter name.
	 * @return bool
	 */
	public static function validate_template( $value, $request, $param ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $key => $val ) {
			if ( ! is_string( $key ) || ! is_string( $val ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns cached BB layout templates, or fetches and caches them.
	 *
	 * @since 0.2.0
	 * @return array
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

	/**
	 * Fetches BB layout templates from the database.
	 *
	 * No user input reaches this query — all parameters are hardcoded.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	private static function fetch_templates() {
		$templates = [];

		$posts = get_posts(
			[
				'post_type'        => 'fl-builder-template',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'posts_per_page'   => '-1',
				'suppress_filters' => true,
				'no_found_rows'    => true,
				'tax_query'        => [
					[
						'taxonomy' => 'fl-builder-template-type',
						'field'    => 'slug',
						'terms'    => 'layout',
					],
				],
			]
		);

		foreach ( $posts as $post ) {
			$templates[] = [
				'slug' => $post->post_name,
				'name' => $post->post_title,
			];
		}

		return $templates;
	}

	/**
	 * Flushes the template transient cache.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Flushes cache only if the deleted post was a BB template.
	 *
	 * @since 0.2.0
	 * @param int $post_id Deleted post ID.
	 * @return void
	 */
	public static function maybe_flush_cache( $post_id ) {
		if ( 'fl-builder-template' === get_post_type( $post_id ) ) {
			self::flush_cache();
		}
	}
}

OneDog_BB_REST::init();
