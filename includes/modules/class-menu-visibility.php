<?php
/**
 * Module: Admin Menu & Toolbar Visibility.
 *
 * Hides specific admin sidebar menu items and top admin bar nodes
 * based on the current user's role.
 *
 * @since 0.3.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Menu_Visibility
 *
 * @since 0.3.0
 */
final class OneDog_BBCA_Menu_Visibility {

	/**
	 * Option key for hidden menu items per role.
	 *
	 * Structure: [ 'role' => [ 'menu-slug', 'submenu:parent>slug', ... ] ]
	 *
	 * @var string
	 */
	const MENU_OPTION = 'onedog_bbca_menu_visibility';

	/**
	 * Option key for hidden toolbar nodes per role.
	 *
	 * Structure: [ 'role' => [ 'node-id', ... ] ]
	 *
	 * @var string
	 */
	const TOOLBAR_OPTION = 'onedog_bbca_toolbar_visibility';

	/**
	 * Initializes hooks.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function init() {
		// Late priority so all menus are registered before we remove them.
		add_action( 'admin_menu', [ __CLASS__, 'remove_menus' ], 9999 );
		add_action( 'wp_before_admin_bar_render', [ __CLASS__, 'remove_toolbar_nodes' ], 9999 );
	}

	/**
	 * Removes admin menu/submenu items for the current user's role.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function remove_menus() {
		$role  = self::get_current_role();
		$rules = get_option( self::MENU_OPTION, [] );

		if ( ! is_array( $rules ) || ! isset( $rules[ $role ] ) || ! is_array( $rules[ $role ] ) ) {
			return;
		}

		foreach ( $rules[ $role ] as $item ) {
			$item = sanitize_text_field( $item );

			if ( str_starts_with( $item, 'submenu:' ) ) {
				// Format: submenu:parent.php>child-slug
				$path   = substr( $item, 8 );
				$parts  = explode( '>', $path, 2 );

				if ( 2 === count( $parts ) ) {
					remove_submenu_page( $parts[0], $parts[1] );
				}
			} else {
				remove_menu_page( $item );
			}
		}
	}

	/**
	 * Removes admin bar nodes for the current user's role.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function remove_toolbar_nodes() {
		global $wp_admin_bar;

		if ( ! is_object( $wp_admin_bar ) ) {
			return;
		}

		$role  = self::get_current_role();
		$rules = get_option( self::TOOLBAR_OPTION, [] );

		if ( ! is_array( $rules ) || ! isset( $rules[ $role ] ) || ! is_array( $rules[ $role ] ) ) {
			return;
		}

		foreach ( $rules[ $role ] as $node_id ) {
			$wp_admin_bar->remove_node( sanitize_text_field( $node_id ) );
		}
	}

	/**
	 * Returns the current user's primary role.
	 *
	 * @since 0.3.0
	 * @return string
	 */
	private static function get_current_role() {
		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		return ! empty( $roles ) ? array_values( $roles )[0] : '';
	}

	/**
	 * Returns the list of registered top-level admin menu items.
	 *
	 * Used by the REST API to populate the settings UI.
	 *
	 * @since 0.3.0
	 * @return array
	 */
	public static function get_available_menus() {
		global $menu, $submenu;

		$items = [];

		if ( ! is_array( $menu ) ) {
			return $items;
		}

		foreach ( $menu as $item ) {
			// Skip separators.
			if ( empty( $item[2] ) || 'wp-menu-separator' === ( $item[4] ?? '' ) ) {
				continue;
			}

			$slug  = $item[2];
			$label = strip_tags( $item[0] );

			$entry = [
				'slug'    => $slug,
				'label'   => $label,
				'type'    => 'menu',
				'children' => [],
			];

			// Gather submenu items.
			if ( isset( $submenu[ $slug ] ) && is_array( $submenu[ $slug ] ) ) {
				foreach ( $submenu[ $slug ] as $sub ) {
					$entry['children'][] = [
						'slug'  => 'submenu:' . $slug . '>' . $sub[2],
						'label' => strip_tags( $sub[0] ),
						'type'  => 'submenu',
					];
				}
			}

			$items[] = $entry;
		}

		return $items;
	}

	/**
	 * Returns common admin bar node IDs for the settings UI.
	 *
	 * @since 0.3.0
	 * @return array
	 */
	public static function get_available_toolbar_nodes() {
		return [
			[ 'id' => 'wp-logo', 'label' => __( 'WordPress Logo', 'bb-custom-admin' ) ],
			[ 'id' => 'site-name', 'label' => __( 'Site Name', 'bb-custom-admin' ) ],
			[ 'id' => 'updates', 'label' => __( 'Updates', 'bb-custom-admin' ) ],
			[ 'id' => 'comments', 'label' => __( 'Comments', 'bb-custom-admin' ) ],
			[ 'id' => 'new-content', 'label' => __( 'New Content (+)', 'bb-custom-admin' ) ],
			[ 'id' => 'edit', 'label' => __( 'Edit', 'bb-custom-admin' ) ],
			[ 'id' => 'my-account', 'label' => __( 'My Account', 'bb-custom-admin' ) ],
			[ 'id' => 'search', 'label' => __( 'Search', 'bb-custom-admin' ) ],
			[ 'id' => 'customize', 'label' => __( 'Customize', 'bb-custom-admin' ) ],
			[ 'id' => 'themes', 'label' => __( 'Themes', 'bb-custom-admin' ) ],
			[ 'id' => 'widgets', 'label' => __( 'Widgets', 'bb-custom-admin' ) ],
			[ 'id' => 'menus', 'label' => __( 'Menus', 'bb-custom-admin' ) ],
		];
	}
}

OneDog_BBCA_Menu_Visibility::init();
