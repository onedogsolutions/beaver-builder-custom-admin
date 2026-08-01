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
