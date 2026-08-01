<?php
/**
 * Module: Orphaned Option Cleaner.
 *
 * Detects and removes leftover wp_options entries from uninstalled
 * plugins. Scans the options table, groups entries by prefix, and
 * flags groups that do not belong to any installed plugin, WordPress
 * core, or the active theme.
 *
 * @since 1.1.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Option_Cleaner
 *
 * Purely on-demand — no runtime hooks. All operations are triggered
 * through the REST API by an administrator.
 *
 * @since 1.1.0
 */
final class OneDog_BBCA_Option_Cleaner {

	/**
	 * Initializes the module.
	 *
	 * No runtime hooks required — scanning and deletion happen
	 * on-demand via REST endpoints.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function init() {
		// On-demand module — nothing to hook at runtime.
	}

	/**
	 * Scans wp_options for orphaned option groups.
	 *
	 * In manual mode ($prefix provided), returns all options matching
	 * that prefix as a single group. In auto mode, groups all options
	 * by prefix and excludes those owned by installed plugins,
	 * WordPress core, or this plugin.
	 *
	 * @since 1.1.0
	 * @param string $prefix Optional. Manual prefix to search for.
	 * @return array {
	 *     @type array $groups        Array of group arrays with prefix, count, size, samples.
	 *     @type int   $total_options Total number of options scanned.
	 * }
	 */
	public static function scan( $prefix = '' ) {
		global $wpdb;

		if ( '' !== $prefix ) {
			return self::scan_prefix( $prefix );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) AS value_length FROM {$wpdb->options}",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return [ 'groups' => [], 'total_options' => 0 ];
		}

		$owned  = self::get_owned_prefixes();
		$safe   = self::get_core_safelist();
		$groups = [];

		foreach ( $rows as $row ) {
			$name = $row['option_name'];
			$size = (int) $row['value_length'];

			// Skip options without an underscore — generic site options.
			if ( false === strpos( $name, '_' ) ) {
				continue;
			}

			$key = self::extract_group_key( $name );

			if ( '' === $key ) {
				continue;
			}

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = [
					'prefix'  => $key,
					'count'   => 0,
					'size'    => 0,
					'samples' => [],
				];
			}

			$groups[ $key ]['count']++;
			$groups[ $key ]['size'] += $size;

			if ( count( $groups[ $key ]['samples'] ) < 5 ) {
				$groups[ $key ]['samples'][] = $name;
			}
		}

		// Filter out owned, safelisted, and singleton groups.
		$orphans = [];

		foreach ( $groups as $key => $group ) {
			// Skip groups owned by installed plugins or this plugin.
			if ( in_array( $key, $owned, true ) ) {
				continue;
			}

			// Skip WordPress core / infrastructure prefixes.
			if ( in_array( $key, $safe, true ) ) {
				continue;
			}

			// Skip single-option groups — likely one-off settings.
			if ( $group['count'] < 2 ) {
				continue;
			}

			$orphans[] = $group;
		}

		// Sort by count descending.
		usort(
			$orphans,
			static function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return [
			'groups'        => $orphans,
			'total_options' => count( $rows ),
		];
	}

	/**
	 * Scans all roles for orphaned (ghost) capabilities.
	 *
	 * Groups non-core capabilities by prefix and excludes those owned
	 * by installed plugins. Returns groups with the roles they appear in.
	 *
	 * @since 1.1.0
	 * @return array {
	 *     @type array $groups Array of group arrays with prefix, count, roles, samples.
	 * }
	 */
	public static function scan_orphaned_capabilities() {
		global $wp_roles;

		$core_caps = self::get_core_capabilities();
		$owned     = self::get_owned_prefixes();
		$groups    = [];

		foreach ( $wp_roles->roles as $role_slug => $role_data ) {
			if ( empty( $role_data['capabilities'] ) || ! is_array( $role_data['capabilities'] ) ) {
				continue;
			}

			foreach ( array_keys( $role_data['capabilities'] ) as $cap ) {
				// Skip WordPress core capabilities.
				if ( isset( $core_caps[ $cap ] ) ) {
					continue;
				}

				// Determine the group key (first prefix segment).
				$key = self::extract_group_key( $cap );

				if ( '' === $key ) {
					// No underscore — treat the whole cap as the key.
					$key = strtolower( $cap );
				}

				// Skip capabilities owned by installed plugins.
				if ( in_array( $key, $owned, true ) ) {
					continue;
				}

				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = [
						'prefix'  => $key,
						'count'   => 0,
						'roles'   => [],
						'samples' => [],
					];
				}

				$groups[ $key ]['count']++;

				if ( ! in_array( $role_slug, $groups[ $key ]['roles'], true ) ) {
					$groups[ $key ]['roles'][] = $role_slug;
				}

				if ( count( $groups[ $key ]['samples'] ) < 5 && ! in_array( $cap, $groups[ $key ]['samples'], true ) ) {
					$groups[ $key ]['samples'][] = $cap;
				}
			}
		}

		$orphans = array_values( $groups );

		// Sort by count descending.
		usort(
			$orphans,
			static function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return [ 'groups' => $orphans ];
	}

	/**
	 * Removes all capabilities matching the given prefixes from every role.
	 *
	 * @since 1.1.0
	 * @param array $prefixes Array of capability prefixes to strip.
	 * @return int Total number of capability removals.
	 */
	public static function delete_capabilities_by_prefix( array $prefixes ) {
		global $wp_roles;

		$removed = 0;

		$sanitized = array_filter( array_map( 'sanitize_key', $prefixes ) );

		if ( empty( $sanitized ) ) {
			return 0;
		}

		foreach ( $wp_roles->roles as $role_slug => $role_data ) {
			$role = get_role( $role_slug );

			if ( ! $role || empty( $role_data['capabilities'] ) ) {
				continue;
			}

			foreach ( array_keys( $role_data['capabilities'] ) as $cap ) {
				foreach ( $sanitized as $prefix ) {
					if ( 0 === strpos( $cap, $prefix ) ) {
						$role->remove_cap( $cap );
						$removed++;
						break;
					}
				}
			}
		}

		return $removed;
	}

	/**
	 * Deletes all options matching the given prefixes.
	 *
	 * Also removes matching transients (_transient_{prefix}% and
	 * _transient_timeout_{prefix}%).
	 *
	 * @since 1.1.0
	 * @param array $prefixes Array of option prefixes to delete.
	 * @return int Total number of rows deleted.
	 */
	public static function delete_prefixes( array $prefixes ) {
		global $wpdb;

		$deleted = 0;

		foreach ( $prefixes as $prefix ) {
			$prefix = sanitize_key( $prefix );

			if ( '' === $prefix ) {
				continue;
			}

			$like = $wpdb->esc_like( $prefix ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$deleted += (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$like
				)
			);

			// Clean up matching transients.
			$transient_like = $wpdb->esc_like( '_transient_' . $prefix ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$deleted += (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$transient_like
				)
			);

			$timeout_like = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$deleted += (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$timeout_like
				)
			);
		}

		return $deleted;
	}

	/**
	 * Scans for all options matching a specific prefix (manual mode).
	 *
	 * @since 1.1.0
	 * @param string $prefix The prefix to search for.
	 * @return array Same shape as scan() return value.
	 */
	private static function scan_prefix( $prefix ) {
		global $wpdb;

		$prefix = sanitize_key( $prefix );

		if ( '' === $prefix ) {
			return [ 'groups' => [], 'total_options' => 0 ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, LENGTH(option_value) AS value_length FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return [ 'groups' => [], 'total_options' => 0 ];
		}

		$group = [
			'prefix'  => $prefix,
			'count'   => count( $rows ),
			'size'    => 0,
			'samples' => [],
		];

		foreach ( $rows as $row ) {
			$group['size'] += (int) $row['value_length'];

			if ( count( $group['samples'] ) < 5 ) {
				$group['samples'][] = $row['option_name'];
			}
		}

		return [
			'groups'        => [ $group ],
			'total_options' => count( $rows ),
		];
	}

	/**
	 * Extracts the group key from an option name.
	 *
	 * Uses the first underscore-delimited segment when it is 4+ chars;
	 * otherwise joins the first two segments (handles short tokens
	 * like "wp" in wp_mail_smtp_*).
	 *
	 * @since 1.1.0
	 * @param string $option_name The option name.
	 * @return string Group key or empty string.
	 */
	private static function extract_group_key( $option_name ) {
		$parts = explode( '_', $option_name );

		if ( count( $parts ) < 2 ) {
			return '';
		}

		if ( strlen( $parts[0] ) >= 4 ) {
			return strtolower( $parts[0] );
		}

		// Short first token — join first two segments.
		if ( isset( $parts[1] ) ) {
			return strtolower( $parts[0] . '_' . $parts[1] );
		}

		return '';
	}

	/**
	 * Builds the list of option prefixes owned by installed plugins.
	 *
	 * Derives prefixes from each plugin's directory slug and TextDomain
	 * header, normalized to underscores. Always includes this plugin's
	 * own prefix.
	 *
	 * @since 1.1.0
	 * @return array Normalized prefix strings.
	 */
	private static function get_owned_prefixes() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$owned   = [];
		$plugins = get_plugins();

		foreach ( $plugins as $file => $data ) {
			// Directory slug (e.g. "woocommerce" or "seo-by-rank-math").
			$dir = dirname( $file );

			if ( '.' !== $dir ) {
				$owned[] = str_replace( '-', '_', strtolower( $dir ) );
			}

			// TextDomain header (e.g. "rank-math").
			if ( ! empty( $data['TextDomain'] ) ) {
				$owned[] = str_replace( '-', '_', strtolower( $data['TextDomain'] ) );
			}

			// Plugin basename without extension (e.g. "woocommerce").
			$base = strtolower( basename( $file, '.php' ) );

			if ( ! empty( $base ) ) {
				$owned[] = str_replace( '-', '_', $base );
			}
		}

		// Always protect this plugin's own options.
		$owned[] = 'onedog_bbca';

		return array_unique( $owned );
	}

	/**
	 * Returns the full set of WordPress core capabilities as a lookup map.
	 *
	 * Any capability not in this map is considered non-core (plugin- or
	 * custom-added).
	 *
	 * @since 1.1.0
	 * @return array Associative array of cap => true.
	 */
	private static function get_core_capabilities() {
		$caps = [
			// Meta / general.
			'read',
			'exist',
			'activate_plugins',
			'create_users',
			'delete_plugins',
			'delete_themes',
			'delete_users',
			'edit_dashboard',
			'edit_files',
			'edit_plugins',
			'edit_theme_options',
			'edit_themes',
			'edit_users',
			'export',
			'import',
			'install_plugins',
			'install_themes',
			'list_users',
			'manage_categories',
			'manage_links',
			'manage_options',
			'moderate_comments',
			'promote_users',
			'remove_users',
			'switch_themes',
			'unfiltered_html',
			'unfiltered_upload',
			'update_core',
			'update_plugins',
			'update_themes',
			'upload_plugins',
			'upload_themes',
			'customize',
			'delete_site',
			// Posts.
			'edit_posts',
			'edit_others_posts',
			'edit_private_posts',
			'edit_published_posts',
			'publish_posts',
			'read_private_posts',
			'delete_posts',
			'delete_others_posts',
			'delete_private_posts',
			'delete_published_posts',
			// Pages.
			'edit_pages',
			'edit_others_pages',
			'edit_private_pages',
			'edit_published_pages',
			'publish_pages',
			'read_private_pages',
			'delete_pages',
			'delete_others_pages',
			'delete_private_pages',
			'delete_published_pages',
			// Media.
			'upload_files',
			// Network / multisite.
			'manage_network',
			'manage_sites',
			'manage_network_users',
			'manage_network_plugins',
			'manage_network_themes',
			'manage_network_options',
			'upgrade_network',
			'setup_network',
		];

		return array_fill_keys( $caps, true );
	}

	/**
	 * Returns the safelist of WordPress core / infrastructure prefixes
	 * that must never be flagged as orphaned.
	 *
	 * @since 1.1.0
	 * @return array
	 */
	private static function get_core_safelist() {
		global $wpdb;

		$safelist = [
			// Widgets & sidebars.
			'widget',
			'sidebars',
			// Themes & nav menus.
			'theme_mods',
			'nav_menu',
			'template',
			'stylesheet',
			// Core scheduling & rewrites.
			'cron',
			'rewrite_rules',
			'permalink_structure',
			'category_base',
			// Roles & plugins.
			'wp_user_roles',
			'active_plugins',
			'recently_activated',
			'uninstall_plugins',
			'wp_force_deactivated_plugins',
			// Transients infrastructure.
			'transient',
			'site_transient',
			// Updates & recovery.
			'auto_updater',
			'recovery_keys',
			'https_detection_errors',
			'ftp_credentials',
			// Site settings.
			'blogname',
			'admin_email',
			'default_role',
			'date_format',
			'timezone_string',
			'mailserver',
			'links_updated',
			// Comments.
			'comment_moderation',
			'moderation_keys',
			'blacklist_keys',
			'default_comment_status',
			'default_ping_status',
			'default_pingback_flag',
			'require_name_email',
			'comment_registration',
			'show_avatars',
			'avatar_default',
			// Content settings.
			'posts_per_page',
			'posts_per_rss',
			'rss_use_excerpt',
			'default_post_format',
			'use_smilies',
			'use_balancetags',
			'link_manager_enabled',
			// Misc core.
			'initial_db_version',
			'db_upgraded',
			'can_compress_scripts',
			'finished_splitting_shared_terms',
			'wp_attachment_pages_enabled',
			'wp_user_roles',
		];

		// Dynamic: table-prefix options (e.g. "wp_" grouped keys).
		$db_prefix = str_replace( '-', '_', strtolower( $wpdb->prefix ) );
		$db_prefix = rtrim( $db_prefix, '_' );

		if ( ! empty( $db_prefix ) ) {
			$safelist[] = $db_prefix;
		}

		return array_unique( $safelist );
	}
}

OneDog_BBCA_Option_Cleaner::init();
