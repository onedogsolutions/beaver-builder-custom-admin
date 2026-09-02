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
	 * Option key for hidden supplemental menu items per role.
	 *
	 * Stores restrictions for menus that are not discovered dynamically because
	 * their registering plugin guards admin_menu behind is_admin(). Uses the
	 * same slug format as MENU_OPTION.
	 *
	 * Structure: [ 'role' => [ 'menu-slug', 'submenu:parent>slug', ... ] ]
	 *
	 * @var string
	 */
	const EXTRA_MENU_OPTION = 'onedog_bbca_menu_visibility_extra';

	/**
	 * Option key for manually defined supplemental menu item definitions.
	 *
	 * Stores label/slug metadata for custom menu items added through the UI
	 * that are not provided by built-in plugin mappings or the filter hook.
	 *
	 * Structure: [ [ 'slug' => '...', 'label' => '...', 'type' => 'menu|submenu' ], ... ]
	 *
	 * @var string
	 */
	const CUSTOM_MENU_ITEMS_OPTION = 'onedog_bbca_menu_visibility_custom_items';

	/**
	 * Initializes hooks.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function init() {
		// Late priority so all menus are registered before we remove them.
		add_action( 'admin_menu', [ __CLASS__, 'remove_menus' ], 9999 );
		// Final pass after any plugin that re-adds menus at PHP_INT_MAX (e.g. WP fail2ban, Freemius).
		add_action( 'admin_head', [ __CLASS__, 'remove_menus' ], 1 );
		add_action( 'wp_before_admin_bar_render', [ __CLASS__, 'remove_toolbar_nodes' ], 9999 );

		// Block direct URL access to restricted pages.
		add_action( 'admin_init', [ __CLASS__, 'block_direct_access' ], 9999 );
	}

	/**
	 * Removes admin menu/submenu items for the current user's role.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function remove_menus() {
		$role  = self::get_current_role();
		$rules = self::get_merged_menu_rules( $role );

		if ( empty( $rules ) ) {
			return;
		}

		foreach ( $rules as $item ) {
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
	 * Blocks direct URL access to restricted admin pages.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function block_direct_access() {
		// Administrators bypass restrictions.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$role  = self::get_current_role();
		$hidden = self::get_merged_menu_rules( $role );

		if ( empty( $hidden ) ) {
			return;
		}

		// Get current page.
		$pagenow = $GLOBALS['pagenow'] ?? '';

		// Check top-level menu pages.
		foreach ( $hidden as $item ) {
			$item = sanitize_text_field( $item );

			// Skip submenu items for direct access check.
			if ( str_starts_with( $item, 'submenu:' ) ) {
				$path  = substr( $item, 8 );
				$parts = explode( '>', $path, 2 );

				if ( 2 === count( $parts ) ) {
					$submenu_slug = $parts[1];

					// Check if current page matches submenu slug.
					if ( self::is_current_page( $submenu_slug ) ) {
						wp_die( esc_html__( 'You are not allowed to access this page.', 'bb-custom-admin' ) );
					}
				}
			} else {
				// Top-level menu page.
				if ( self::is_current_page( $item ) ) {
					wp_die( esc_html__( 'You are not allowed to access this page.', 'bb-custom-admin' ) );
				}
			}
		}
	}

	/**
	 * Checks if the current admin page matches a menu slug.
	 *
	 * @since 1.0.0
	 * @param string $slug Menu slug.
	 * @return bool
	 */
	private static function is_current_page( $slug ) {
		global $plugin_page;

		$pagenow = $GLOBALS['pagenow'] ?? '';

		// Direct match with pagenow.
		if ( $pagenow === $slug ) {
			return true;
		}

		// Check plugin_page for custom menu pages.
		if ( isset( $plugin_page ) && $plugin_page === $slug ) {
			return true;
		}

		// Check $_GET['page'] for submenu pages.
		if ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === $slug ) {
			return true;
		}

		return false;
	}

	/**
	 * Cleans a menu label by stripping WordPress update/notification count spans.
	 *
	 * WordPress embeds counts (e.g. plugin updates) inside the label as HTML spans.
	 * Removing the spans before `strip_tags()` prevents trailing numbers such as "Plugins 0".
	 *
	 * @since 1.6.1
	 * @param string $label Raw menu label.
	 * @return string
	 */
	private static function sanitize_menu_label( $label ) {
		$label = preg_replace( '/\s?<span[^>]*class="[^"]*(?:update-plugins|awaiting-mod|menu-counter)[^"]*"[^>]*>.*?<\/span>/i', '', $label );
		$label = strip_tags( $label );
		return trim( $label );
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
	 * Merges dynamic and supplemental menu restriction rules for a role.
	 *
	 * @since 1.6.0
	 * @param string $role Role slug.
	 * @return array
	 */
	private static function get_merged_menu_rules( $role ) {
		$dynamic = get_option( self::MENU_OPTION, [] );
		$extra   = get_option( self::EXTRA_MENU_OPTION, [] );

		$merged = [];
		foreach ( [ $dynamic, $extra ] as $source ) {
			if ( is_array( $source ) && isset( $source[ $role ] ) && is_array( $source[ $role ] ) ) {
				foreach ( $source[ $role ] as $item ) {
					$item = sanitize_text_field( $item );
					if ( ! in_array( $item, $merged, true ) ) {
						$merged[] = $item;
					}
				}
			}
		}

		return $merged;
	}

	/**
	 * Returns supplemental menu items that are not discovered dynamically.
	 *
	 * Built-in mappings cover premium plugins that guard admin_menu behind
	 * is_admin(). The list is filterable via `onedog_bbca_menu_visibility_extra_items`.
	 *
	 * @since 1.6.0
	 * @return array
	 */
	public static function get_extra_menu_items() {
		$extra = [];

		// SEOPress (free or pro) — active check by main plugin file.
		if ( self::is_plugin_active( 'wp-seopress/seopress.php' ) || self::is_plugin_active( 'seopress/seopress.php' ) ) {
			$extra[] = [
				'slug'     => 'seopress-option',
				'label'    => __( 'SEO', 'bb-custom-admin' ),
				'type'     => 'menu',
				'children' => [
					[
						'slug'  => 'submenu:seopress-option>seopress-option',
						'label' => __( 'Dashboard', 'bb-custom-admin' ),
						'type'  => 'submenu',
					],
					[
						'slug'  => 'submenu:seopress-option>seopress-titles',
						'label' => __( 'Titles & Metas', 'bb-custom-admin' ),
						'type'  => 'submenu',
					],
					[
						'slug'  => 'submenu:seopress-option>seopress-xml-sitemap',
						'label' => __( 'XML - HTML Sitemap', 'bb-custom-admin' ),
						'type'  => 'submenu',
					],
					[
						'slug'  => 'submenu:seopress-option>seopress-social',
						'label' => __( 'Social Networks', 'bb-custom-admin' ),
						'type'  => 'submenu',
					],
					[
						'slug'  => 'submenu:seopress-option>seopress-google-analytics',
						'label' => __( 'Analytics', 'bb-custom-admin' ),
						'type'  => 'submenu',
					],
					[
						'slug'  => 'submenu:seopress-option>seopress-instant-indexing',
						'label' => __( 'Instant Indexing', 'bb-custom-admin' ),
						'type'  => 'submenu',
					],
					[
						'slug'  => 'submenu:seopress-option>seopress-advanced',
						'label' => __( 'Advanced', 'bb-custom-admin' ),
						'type'  => 'submenu',
					],
					[
						'slug'  => 'submenu:seopress-option>seopress-import-export',
						'label' => __( 'Tools', 'bb-custom-admin' ),
						'type'  => 'submenu',
					],
				],
			];
		}

		// LiteSpeed Cache — guards admin_menu behind is_admin(), so it is not discovered via REST.
		if ( self::is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
			$extra[] = [
				'slug'  => 'litespeed',
				'label' => __( 'LiteSpeed Cache', 'bb-custom-admin' ),
				'type'  => 'menu',
			];
		}

		/**
		 * Filter supplemental menu items available for restriction.
		 *
		 * @since 1.6.0
		 *
		 * @param array $extra Menu items with the same shape as get_available_menus().
		 */
		$extra = apply_filters( 'onedog_bbca_menu_visibility_extra_items', $extra );

		// Append any manually defined custom items.
		$custom = self::get_custom_menu_items();
		if ( ! empty( $custom ) && is_array( $custom ) ) {
			foreach ( $custom as $item ) {
				if ( ! empty( $item['slug'] ) ) {
					$extra[] = $item;
				}
			}
		}

		return $extra;
	}

	/**
	 * Reads saved manual custom menu item definitions.
	 *
	 * @since 1.6.0
	 * @return array
	 */
	public static function get_custom_menu_items() {
		$items = get_option( self::CUSTOM_MENU_ITEMS_OPTION, [] );
		return is_array( $items ) ? $items : [];
	}

	/**
	 * Saves manual custom menu item definitions.
	 *
	 * @since 1.6.0
	 * @param array $items List of custom item definitions.
	 * @return void
	 */
	public static function save_custom_menu_items( $items ) {
		if ( ! is_array( $items ) ) {
			$items = [];
		}

		$sanitized = [];
		foreach ( $items as $item ) {
			if ( empty( $item['slug'] ) || empty( $item['label'] ) ) {
				continue;
			}
			$sanitized[] = [
				'slug'  => sanitize_text_field( $item['slug'] ),
				'label' => sanitize_text_field( $item['label'] ),
				'type'  => sanitize_text_field( $item['type'] ?? 'menu' ),
			];
		}

		update_option( self::CUSTOM_MENU_ITEMS_OPTION, $sanitized );
	}

	/**
	 * Reads saved supplemental menu restriction rules.
	 *
	 * @since 1.6.0
	 * @return array
	 */
	public static function get_extra_menu_rules() {
		$rules = get_option( self::EXTRA_MENU_OPTION, [] );
		return is_array( $rules ) ? $rules : [];
	}

	/**
	 * Saves supplemental menu restriction rules.
	 *
	 * @since 1.6.0
	 * @param array $rules Rules array keyed by role slug.
	 * @return void
	 */
	public static function save_extra_menu_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			$rules = [];
		}

		$sanitized = [];
		foreach ( $rules as $role => $items ) {
			$role_key = sanitize_text_field( $role );
			$sanitized[ $role_key ] = is_array( $items )
				? array_map( 'sanitize_text_field', $items )
				: [];
		}

		update_option( self::EXTRA_MENU_OPTION, $sanitized );
	}

	/**
	 * Checks whether a plugin is active.
	 *
	 * Wrapper that works before `is_plugin_active()` is available.
	 *
	 * @since 1.6.0
	 * @param string $plugin Plugin basename.
	 * @return bool
	 */
	private static function is_plugin_active( $plugin ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin );
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

		// REST/AJAX requests skip the wp-admin bootstrap, so the $menu and
		// $submenu globals are never built there. Load the admin API files and
		// the menu builder on demand so plugin-registered pages are included.
		// Note: plugins that guard their admin_menu callbacks behind is_admin()
		// will not appear, since is_admin() is false in a REST context.
		if ( ! is_array( $menu ) ) {
			require_once ABSPATH . 'wp-admin/includes/admin.php';
			require_once ABSPATH . 'wp-admin/menu.php';
		}

		if ( ! is_array( $menu ) ) {
			return $items;
		}

		foreach ( $menu as $item ) {
			// Skip separators.
			if ( empty( $item[2] ) || 'wp-menu-separator' === ( $item[4] ?? '' ) ) {
				continue;
			}

			$slug  = $item[2];
			$label = self::sanitize_menu_label( $item[0] );

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
						'label' => self::sanitize_menu_label( $sub[0] ),
						'type'  => 'submenu',
					];
				}
			}

			$items[] = $entry;
		}

		$items = self::merge_extra_menu_items( $items );

		return $items;
	}

	/**
	 * Merges supplemental menu items into dynamically discovered menus.
	 *
	 * Supplemental items cover plugins (e.g. SEOPress) that do not register
	 * their admin menus in REST context. Dynamic discovery wins on slug
	 * collision so live labels are preserved.
	 *
	 * @since 1.6.0
	 * @param array $items Dynamically discovered menu items.
	 * @return array
	 */
	private static function merge_extra_menu_items( $items ) {
		$extra = self::get_extra_menu_items();
		if ( empty( $extra ) ) {
			return $items;
		}

		$slugs = array_column( $items, 'slug' );

		foreach ( $extra as $entry ) {
			if ( empty( $entry['slug'] ) ) {
				continue;
			}

			$existing = array_search( $entry['slug'], $slugs, true );
			if ( false !== $existing ) {
				// Merge children from the supplemental entry into the live entry
				// without duplicating slugs.
				if ( ! empty( $entry['children'] ) && is_array( $entry['children'] ) ) {
					$child_slugs = array_column( $items[ $existing ]['children'], 'slug' );
					foreach ( $entry['children'] as $child ) {
						if ( empty( $child['slug'] ) || in_array( $child['slug'], $child_slugs, true ) ) {
							continue;
						}
						$items[ $existing ]['children'][] = $child;
						$child_slugs[] = $child['slug'];
					}
				}
				continue;
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
