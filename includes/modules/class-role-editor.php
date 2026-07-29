<?php
/**
 * Module: Role & Capability Editor.
 *
 * Manages WordPress roles and capabilities with rollback support.
 *
 * @since 1.0.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Role_Editor
 *
 * @since 1.0.0
 */
final class OneDog_BBCA_Role_Editor {

	/**
	 * Option key for storing default capability snapshots.
	 *
	 * @var string
	 */
	const SNAPSHOT_OPTION = 'onedog_bbca_role_snapshots';

	/**
	 * Core WordPress roles.
	 *
	 * @var array
	 */
	const CORE_ROLES = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];

	/**
	 * Default capabilities for core roles.
	 *
	 * @var array
	 */
	private static $default_capabilities = [
		'administrator' => [
			'switch_themes' => true,
			'edit_themes' => true,
			'activate_plugins' => true,
			'edit_plugins' => true,
			'edit_users' => true,
			'edit_files' => true,
			'manage_options' => true,
			'moderate_comments' => true,
			'manage_categories' => true,
			'manage_links' => true,
			'upload_files' => true,
			'import' => true,
			'unfiltered_html' => true,
			'edit_posts' => true,
			'edit_others_posts' => true,
			'edit_published_posts' => true,
			'publish_posts' => true,
			'edit_pages' => true,
			'read' => true,
			'level_10' => true,
			'level_9' => true,
			'level_8' => true,
			'level_7' => true,
			'level_6' => true,
			'level_5' => true,
			'level_4' => true,
			'level_3' => true,
			'level_2' => true,
			'level_1' => true,
			'level_0' => true,
			'edit_others_pages' => true,
			'edit_published_pages' => true,
			'publish_pages' => true,
			'delete_pages' => true,
			'delete_others_pages' => true,
			'delete_published_pages' => true,
			'delete_posts' => true,
			'delete_others_posts' => true,
			'delete_published_posts' => true,
			'delete_private_posts' => true,
			'edit_private_posts' => true,
			'read_private_posts' => true,
			'delete_private_pages' => true,
			'edit_private_pages' => true,
			'read_private_pages' => true,
			'delete_users' => true,
			'create_users' => true,
			'unfiltered_upload' => true,
			'edit_dashboard' => true,
			'update_plugins' => true,
			'delete_plugins' => true,
			'install_plugins' => true,
			'update_themes' => true,
			'install_themes' => true,
			'update_core' => true,
			'list_users' => true,
			'remove_users' => true,
			'promote_users' => true,
			'edit_theme_options' => true,
			'delete_themes' => true,
			'export' => true,
		],
		'editor' => [
			'moderate_comments' => true,
			'manage_categories' => true,
			'manage_links' => true,
			'upload_files' => true,
			'unfiltered_html' => true,
			'edit_posts' => true,
			'edit_others_posts' => true,
			'edit_published_posts' => true,
			'publish_posts' => true,
			'edit_pages' => true,
			'read' => true,
			'level_7' => true,
			'level_6' => true,
			'level_5' => true,
			'level_4' => true,
			'level_3' => true,
			'level_2' => true,
			'level_1' => true,
			'level_0' => true,
			'edit_others_pages' => true,
			'edit_published_pages' => true,
			'publish_pages' => true,
			'delete_pages' => true,
			'delete_others_pages' => true,
			'delete_published_pages' => true,
			'delete_posts' => true,
			'delete_others_posts' => true,
			'delete_published_posts' => true,
			'delete_private_posts' => true,
			'edit_private_posts' => true,
			'read_private_posts' => true,
			'delete_private_pages' => true,
			'edit_private_pages' => true,
			'read_private_pages' => true,
		],
		'author' => [
			'upload_files' => true,
			'edit_posts' => true,
			'edit_published_posts' => true,
			'publish_posts' => true,
			'read' => true,
			'level_2' => true,
			'level_1' => true,
			'level_0' => true,
			'delete_posts' => true,
			'delete_published_posts' => true,
		],
		'contributor' => [
			'edit_posts' => true,
			'read' => true,
			'level_1' => true,
			'level_0' => true,
			'delete_posts' => true,
		],
		'subscriber' => [
			'read' => true,
			'level_0' => true,
		],
	];

	/**
	 * Initializes hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'maybe_create_snapshots' ] );
	}

	/**
	 * Creates default capability snapshots on first run.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function maybe_create_snapshots() {
		$snapshots = get_option( self::SNAPSHOT_OPTION, [] );

		if ( ! is_array( $snapshots ) ) {
			$snapshots = [];
		}

		$updated = false;

		foreach ( self::CORE_ROLES as $role_slug ) {
			if ( ! isset( $snapshots[ $role_slug ] ) ) {
				$snapshots[ $role_slug ] = self::$default_capabilities[ $role_slug ] ?? [];
				$updated = true;
			}
		}

		if ( $updated ) {
			update_option( self::SNAPSHOT_OPTION, $snapshots );
		}
	}

	/**
	 * Returns all roles with their display names.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_roles() {
		global $wp_roles;
		return $wp_roles->get_names();
	}

	/**
	 * Returns all capabilities for a role.
	 *
	 * @since 1.0.0
	 * @param string $role_slug Role slug.
	 * @return array
	 */
	public static function get_role_capabilities( $role_slug ) {
		$role = get_role( $role_slug );

		if ( ! $role ) {
			return [];
		}

		// Get all known capabilities.
		$all_caps = self::get_all_capabilities();

		// Merge with role capabilities.
		$caps = [];
		foreach ( $all_caps as $cap ) {
			$caps[ $cap ] = $role->has_cap( $cap );
		}

		// Include any role-specific caps not in the global list.
		foreach ( $role->capabilities as $cap => $granted ) {
			if ( ! isset( $caps[ $cap ] ) ) {
				$caps[ $cap ] = (bool) $granted;
			}
		}

		return $caps;
	}

	/**
	 * Returns all known capabilities from all roles.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_all_capabilities() {
		global $wp_roles;

		$caps = [];

		foreach ( $wp_roles->roles as $role ) {
			foreach ( array_keys( $role['capabilities'] ) as $cap ) {
				$caps[ $cap ] = true;
			}
		}

		return array_keys( $caps );
	}

	/**
	 * Saves capabilities for a role.
	 *
	 * @since 1.0.0
	 * @param string $role_slug Role slug.
	 * @param array  $capabilities Capabilities map.
	 * @return bool|WP_Error
	 */
	public static function save_role_capabilities( $role_slug, $capabilities ) {
		$role = get_role( $role_slug );

		if ( ! $role ) {
			return new WP_Error( 'invalid_role', __( 'Invalid role.', 'bb-custom-admin' ), [ 'status' => 404 ] );
		}

		// Snapshot before first modification of core role.
		self::maybe_snapshot_role( $role_slug );

		// Remove all existing capabilities.
		foreach ( array_keys( $role->capabilities ) as $cap ) {
			$role->remove_cap( $cap );
		}

		// Add granted capabilities.
		foreach ( $capabilities as $cap => $granted ) {
			$cap = sanitize_key( $cap );
			if ( $granted ) {
				$role->add_cap( $cap );
			}
		}

		return true;
	}

	/**
	 * Clears all capabilities for a role.
	 *
	 * @since 1.0.0
	 * @param string $role_slug Role slug.
	 * @return bool|WP_Error
	 */
	public static function clear_role_capabilities( $role_slug ) {
		$role = get_role( $role_slug );

		if ( ! $role ) {
			return new WP_Error( 'invalid_role', __( 'Invalid role.', 'bb-custom-admin' ), [ 'status' => 404 ] );
		}

		self::maybe_snapshot_role( $role_slug );

		foreach ( array_keys( $role->capabilities ) as $cap ) {
			$role->remove_cap( $cap );
		}

		return true;
	}

	/**
	 * Rolls back a role to its default capabilities.
	 *
	 * @since 1.0.0
	 * @param string $role_slug Role slug.
	 * @return array|WP_Error Capabilities after rollback.
	 */
	public static function rollback_role( $role_slug ) {
		if ( ! in_array( $role_slug, self::CORE_ROLES, true ) ) {
			return new WP_Error( 'not_core_role', __( 'Only core roles can be reset.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		$role = get_role( $role_slug );

		if ( ! $role ) {
			return new WP_Error( 'invalid_role', __( 'Invalid role.', 'bb-custom-admin' ), [ 'status' => 404 ] );
		}

		$defaults = self::$default_capabilities[ $role_slug ] ?? [];

		// Remove all existing capabilities.
		foreach ( array_keys( $role->capabilities ) as $cap ) {
			$role->remove_cap( $cap );
		}

		// Add default capabilities.
		foreach ( $defaults as $cap => $granted ) {
			if ( $granted ) {
				$role->add_cap( $cap );
			}
		}

		return self::get_role_capabilities( $role_slug );
	}

	/**
	 * Creates a new role.
	 *
	 * @since 1.0.0
	 * @param string $name Role display name.
	 * @param string $slug Role slug (optional).
	 * @param string $clone_from Role to clone from (optional).
	 * @return array|WP_Error
	 */
	public static function add_role( $name, $slug = '', $clone_from = '' ) {
		$name = sanitize_text_field( $name );

		if ( empty( $name ) ) {
			return new WP_Error( 'empty_name', __( 'Role name is required.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		// Generate slug if not provided.
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $name );
		} else {
			$slug = sanitize_key( $slug );
		}

		if ( empty( $slug ) ) {
			return new WP_Error( 'empty_slug', __( 'Invalid role slug.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		// Check if role exists.
		if ( get_role( $slug ) ) {
			return new WP_Error( 'role_exists', __( 'Role already exists.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		// Get capabilities to clone.
		$capabilities = [];
		if ( ! empty( $clone_from ) ) {
			$clone_role = get_role( sanitize_key( $clone_from ) );
			if ( $clone_role ) {
				$capabilities = $clone_role->capabilities;
			}
		}

		add_role( $slug, $name, $capabilities );

		return [
			'slug' => $slug,
			'roles' => self::get_roles(),
		];
	}

	/**
	 * Renames a role's display label.
	 *
	 * @since 1.0.0
	 * @param string $role_slug Role slug.
	 * @param string $label New display label.
	 * @return array|WP_Error
	 */
	public static function rename_role( $role_slug, $label ) {
		global $wp_roles;

		$role_slug = sanitize_key( $role_slug );
		$label = sanitize_text_field( $label );

		if ( in_array( $role_slug, self::CORE_ROLES, true ) ) {
			return new WP_Error( 'core_role', __( 'Core roles cannot be renamed.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		if ( ! isset( $wp_roles->roles[ $role_slug ] ) ) {
			return new WP_Error( 'invalid_role', __( 'Invalid role.', 'bb-custom-admin' ), [ 'status' => 404 ] );
		}

		if ( empty( $label ) ) {
			return new WP_Error( 'empty_label', __( 'Display name is required.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		$wp_roles->roles[ $role_slug ]['name'] = $label;
		update_option( $wp_roles->role_key, $wp_roles->roles );

		return [ 'roles' => self::get_roles() ];
	}

	/**
	 * Deletes a role.
	 *
	 * @since 1.0.0
	 * @param string $role_slug Role slug.
	 * @return array|WP_Error
	 */
	public static function delete_role( $role_slug ) {
		global $wp_roles;

		$role_slug = sanitize_key( $role_slug );

		if ( in_array( $role_slug, self::CORE_ROLES, true ) ) {
			return new WP_Error( 'core_role', __( 'Core roles cannot be deleted.', 'bb-custom-admin' ), [ 'status' => 400 ] );
		}

		if ( ! get_role( $role_slug ) ) {
			return new WP_Error( 'invalid_role', __( 'Invalid role.', 'bb-custom-admin' ), [ 'status' => 404 ] );
		}

		// Check if users are assigned to this role.
		$users = get_users( [ 'role' => $role_slug, 'count_total' => true, 'fields' => 'ID' ] );

		if ( ! empty( $users ) ) {
			return new WP_Error(
				'users_assigned',
				sprintf(
					/* translators: %d: number of users */
					__( '%d user(s) are assigned to this role. Reassign them before deleting.', 'bb-custom-admin' ),
					count( $users )
				),
				[ 'status' => 400 ]
			);
		}

		remove_role( $role_slug );

		return [ 'roles' => self::get_roles() ];
	}

	/**
	 * Snapshots a role's capabilities before first modification.
	 *
	 * @since 1.0.0
	 * @param string $role_slug Role slug.
	 * @return void
	 */
	private static function maybe_snapshot_role( $role_slug ) {
		if ( ! in_array( $role_slug, self::CORE_ROLES, true ) ) {
			return;
		}

		$snapshots = get_option( self::SNAPSHOT_OPTION, [] );

		if ( ! is_array( $snapshots ) ) {
			$snapshots = [];
		}

		if ( ! isset( $snapshots[ $role_slug ] ) ) {
			$role = get_role( $role_slug );
			if ( $role ) {
				$snapshots[ $role_slug ] = $role->capabilities;
				update_option( self::SNAPSHOT_OPTION, $snapshots );
			}
		}
	}
}

OneDog_BBCA_Role_Editor::init();
