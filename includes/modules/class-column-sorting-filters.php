<?php
/**
 * Module: Column Sorting — Smart Filtering Sidebar.
 *
 * Renders filter dropdowns on WordPress list table screens and
 * applies selected filter values to the underlying queries.
 *
 * @since 1.4.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Column_Sorting_Filters
 *
 * @since 1.4.0
 */
final class OneDog_BBCA_Column_Sorting_Filters {

	/**
	 * How long filter dropdown value lists stay cached, in seconds.
	 *
	 * @var int
	 */
	const OPTIONS_CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Initializes hooks.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function init() {
		// Render filter dropdowns on list table screens.
		add_action( 'restrict_manage_posts',  [ __CLASS__, 'render_post_filters' ], 99, 2 );
		add_action( 'restrict_manage_users',  [ __CLASS__, 'render_user_filters' ], 99 );
		add_action( 'manage_comments_nav',    [ __CLASS__, 'render_comment_filters' ], 99 );

		// Apply filter values to queries.
		add_action( 'pre_get_posts',    [ __CLASS__, 'apply_post_filters' ], 5 );
		add_action( 'pre_user_query',   [ __CLASS__, 'apply_user_filters' ], 5 );
		add_filter( 'comments_clauses', [ __CLASS__, 'apply_comment_filters' ], 5, 2 );

		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/*
	|--------------------------------------------------------------------------
	| Filter Rendering — Post Screens
	|--------------------------------------------------------------------------
	*/

	/**
	 * Renders filter dropdowns on post/CPT list tables.
	 *
	 * Fires on the `restrict_manage_posts` action.
	 *
	 * @since 1.4.0
	 * @param string $post_type Current post type.
	 * @param string $which     Position (top/bottom).
	 * @return void
	 */
	public static function render_post_filters( $post_type, $which = 'top' ) {
		if ( 'top' !== $which ) {
			return;
		}

		$screen_id = 'edit-' . $post_type;

		if ( ! OneDog_BBCA_Column_Sorting::is_filtering_enabled( $screen_id ) ) {
			return;
		}

		$columns = self::get_filterable_columns( $screen_id );

		if ( empty( $columns ) ) {
			return;
		}

		foreach ( $columns as $col ) {
			self::render_filter_dropdown( $col['key'], $col['label'], $screen_id );
		}

		self::render_clear_button();
	}

	/**
	 * Renders filter dropdowns on the user list table.
	 *
	 * @since 1.4.0
	 * @param string $which Position (top/bottom).
	 * @return void
	 */
	public static function render_user_filters( $which = 'top' ) {
		if ( 'top' !== $which ) {
			return;
		}

		$screen_id = 'users';

		if ( ! OneDog_BBCA_Column_Sorting::is_filtering_enabled( $screen_id ) ) {
			return;
		}

		$columns = self::get_filterable_columns( $screen_id );

		foreach ( $columns as $col ) {
			self::render_filter_dropdown( $col['key'], $col['label'], $screen_id );
		}

		self::render_clear_button();
	}

	/**
	 * Renders filter dropdowns on the comment list table.
	 *
	 * @since 1.4.0
	 * @param string $which Position (top/bottom).
	 * @return void
	 */
	public static function render_comment_filters( $which = 'top' ) {
		if ( 'top' !== $which ) {
			return;
		}

		$screen_id = 'edit-comments';

		if ( ! OneDog_BBCA_Column_Sorting::is_filtering_enabled( $screen_id ) ) {
			return;
		}

		$columns = self::get_filterable_columns( $screen_id );

		foreach ( $columns as $col ) {
			self::render_filter_dropdown( $col['key'], $col['label'], $screen_id );
		}

		self::render_clear_button();
	}

	/*
	|--------------------------------------------------------------------------
	| Dropdown Rendering
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns the columns that should have filter dropdowns for a screen.
	 *
	 * When no columns are explicitly configured, only the known filterable
	 * set gets dropdowns — see is_auto_filterable_column(). An explicitly
	 * configured list is the administrator's choice and is honoured as-is.
	 *
	 * @since 1.4.0
	 * @param string $screen_id Screen ID.
	 * @return array Array of [ key, label ] items.
	 */
	private static function get_filterable_columns( $screen_id ) {
		$settings = OneDog_BBCA_Column_Sorting::get_settings();
		$screen   = $settings['screens'][ $screen_id ] ?? [];

		// If filter_columns is configured, use only those.
		$filter_keys = $screen['filter_columns'] ?? [];

		// Discover all columns for this screen.
		$screen_obj = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		$all_columns = [];
		if ( $screen_obj ) {
			$all_columns = OneDog_BBCA_Column_Sorting::discover_columns_for_screen( $screen_obj );
		}

		// If no filter_columns configured, filter the known filterable set.
		if ( empty( $filter_keys ) && ! empty( $all_columns ) ) {
			$filter_keys = [];
			foreach ( $all_columns as $key => $label ) {
				if ( self::is_auto_filterable_column( $key, $screen_id ) ) {
					$filter_keys[] = $key;
				}
			}
		}

		$columns = [];
		foreach ( $filter_keys as $key ) {
			$label = $all_columns[ $key ] ?? ucfirst( str_replace( [ '_', '-' ], ' ', $key ) );
			$type  = OneDog_BBCA_Column_Sorting::detect_column_type( $key );

			// Skip non-filterable types.
			if ( 'none' === $type['type'] ) {
				continue;
			}

			$columns[] = [
				'key'   => $key,
				'label' => wp_strip_all_tags( $label ),
			];
		}

		return $columns;
	}

	/**
	 * Checks whether a column should get an automatic filter dropdown.
	 *
	 * Auto dropdowns are limited to columns whose storage is understood:
	 * core fields with a meaningful value set (status, author, role),
	 * taxonomy columns, and meta columns mapped by the type map or an
	 * integration. Unknown columns are usually custom renderers — an icon,
	 * a computed total — and a dropdown for them would query for a meta key
	 * that may not exist, on a table chosen by guesswork.
	 *
	 * @since 1.6.2
	 * @param string $key       Column key.
	 * @param string $screen_id Screen ID.
	 * @return bool
	 */
	private static function is_auto_filterable_column( $key, $screen_id ) {
		if ( ! OneDog_BBCA_Column_Sorting::is_known_column_type( $key ) ) {
			return false;
		}

		$type           = OneDog_BBCA_Column_Sorting::detect_column_type( $key );
		$is_user_screen = ( 'users' === $screen_id );

		switch ( $type['type'] ) {
			case 'taxonomy':
				return ! $is_user_screen;

			case 'post_field':
				return ! $is_user_screen
					&& in_array( $type['orderby'] ?? '', [ 'post_status', 'author' ], true );

			case 'post_meta':
			case 'meta':
				return ! $is_user_screen;

			case 'user_field':
				return $is_user_screen
					&& in_array( $type['orderby'] ?? '', [ 'role', 'capabilities' ], true );

			case 'user_meta':
				return $is_user_screen;
		}

		return false;
	}

	/**
	 * Renders a single filter dropdown.
	 *
	 * @since 1.4.0
	 * @param string $column_key Column key.
	 * @param string $label      Human-readable label.
	 * @param string $screen_id  Screen ID.
	 * @return void
	 */
	private static function render_filter_dropdown( $column_key, $label, $screen_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$current_value = isset( $_GET[ 'bbca_filter_' . $column_key ] )
			? sanitize_text_field( $_GET[ 'bbca_filter_' . $column_key ] )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$type = OneDog_BBCA_Column_Sorting::detect_column_type( $column_key );

		// For taxonomy columns, use WordPress's native taxonomy filter if available.
		if ( 'taxonomy' === $type['type'] && taxonomy_exists( $type['taxonomy'] ?? '' ) ) {
			$tax = get_taxonomy( $type['taxonomy'] );
			if ( $tax ) {
				wp_dropdown_categories( [
					'show_option_all' => $tax->labels->all_items,
					'hide_empty'      => 0,
					'hierarchical'    => 1,
					'show_count'      => 0,
					'orderby'         => 'name',
					'selected'        => $current_value,
					'taxonomy'        => $type['taxonomy'],
					'name'            => 'bbca_filter_' . $column_key,
					'id'              => 'bbca_filter_' . $column_key,
					'class'           => 'bbca-filter-select',
					'value_field'     => 'slug',
				] );
				return;
			}
		}

		$options = self::get_filter_options( $column_key, $screen_id );

		$is_active = '' !== $current_value;
		$css_class = 'bbca-filter-select' . ( $is_active ? ' bbca-filter-active' : '' );

		echo '<select name="' . esc_attr( 'bbca_filter_' . $column_key ) . '" '
			. 'id="' . esc_attr( 'bbca_filter_' . $column_key ) . '" '
			. 'class="' . esc_attr( $css_class ) . '" '
			. 'data-column="' . esc_attr( $column_key ) . '">';

		echo '<option value="">' . esc_html(
			sprintf(
				/* translators: %s: column label */
				__( 'All %s', 'bb-custom-admin' ),
				$label
			)
		) . '</option>';

		foreach ( $options as $value => $option_label ) {
			$selected = selected( $value, $current_value, false );
			echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>'
				. esc_html( $option_label ) . '</option>';
		}

		echo '</select>';
	}

	/**
	 * Returns filter options (distinct values) for a given column.
	 *
	 * Meta columns resolve against the table the screen implies — user
	 * columns against usermeta, comment columns against commentmeta —
	 * rather than always postmeta.
	 *
	 * @since 1.4.0
	 * @param string $column_key Column key.
	 * @param string $screen_id  Screen ID.
	 * @return array value => label pairs.
	 */
	private static function get_filter_options( $column_key, $screen_id ) {
		$type = OneDog_BBCA_Column_Sorting::detect_column_type( $column_key );

		switch ( $type['type'] ) {
			case 'post_field':
				return self::get_post_field_options( $type['orderby'] ?? $column_key );

			case 'taxonomy':
				return self::get_taxonomy_options( $type['taxonomy'] ?? '' );

			case 'user_field':
				return self::get_user_field_options( $type['orderby'] ?? $column_key );

			case 'user_meta':
				// The role column's meta key stores serialized capability
				// arrays; the role list itself is the useful value set.
				$meta_key = $type['meta_key'] ?? $column_key;
				if ( false !== strpos( $meta_key, 'capabilities' ) ) {
					return self::get_user_field_options( 'role' );
				}
				return self::get_meta_options( $meta_key, 'user' );

			case 'meta':
			case 'post_meta':
			default:
				return self::get_meta_options(
					$type['meta_key'] ?? $column_key,
					self::meta_context_for_screen( $screen_id )
				);
		}
	}

	/**
	 * Returns the meta table context implied by a screen.
	 *
	 * @since 1.6.2
	 * @param string $screen_id Screen ID.
	 * @return string 'post', 'user', or 'comment'.
	 */
	private static function meta_context_for_screen( $screen_id ) {
		if ( 'users' === $screen_id ) {
			return 'user';
		}

		if ( 'edit-comments' === $screen_id ) {
			return 'comment';
		}

		return 'post';
	}

	/**
	 * Gets distinct values for a post field column.
	 *
	 * @since 1.4.0
	 * @param string $field Post field name.
	 * @return array
	 */
	private static function get_post_field_options( $field ) {
		$options = [];

		switch ( $field ) {
			case 'post_status':
				$statuses = get_post_stati( [ 'show_in_admin_all_list' => true ], 'objects' );
				foreach ( $statuses as $slug => $status ) {
					$options[ $slug ] = $status->label;
				}
				break;

			case 'author':
				$authors = get_users( [
					'fields'  => [ 'ID', 'display_name' ],
					'orderby' => 'display_name',
					'order'   => 'ASC',
					'number'  => 100,
				] );
				foreach ( $authors as $author ) {
					$options[ $author->ID ] = $author->display_name;
				}
				break;
		}

		return $options;
	}

	/**
	 * Gets all terms for a taxonomy.
	 *
	 * @since 1.4.0
	 * @param string $taxonomy Taxonomy slug.
	 * @return array
	 */
	private static function get_taxonomy_options( $taxonomy ) {
		if ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => 200,
		] );

		if ( is_wp_error( $terms ) ) {
			return [];
		}

		$options = [];
		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}

		return $options;
	}

	/**
	 * Gets distinct meta values for a given meta key.
	 *
	 * Results are cached in a non-autoloaded option: the DISTINCT + ORDER BY
	 * query runs on every list screen render otherwise, and the value set is
	 * slow-moving enough that a short TTL is fine.
	 *
	 * @since 1.4.0
	 * @param string $meta_key Meta key.
	 * @param string $context  'post', 'user', or 'comment'.
	 * @return array
	 */
	private static function get_meta_options( $meta_key, $context = 'post' ) {
		global $wpdb;

		$meta_key = sanitize_text_field( $meta_key );

		if ( empty( $meta_key ) ) {
			return [];
		}

		$cache_key = 'onedog_bbca_filter_opts_' . md5( $context . '|' . $meta_key );
		$cached    = get_option( $cache_key );

		if ( is_array( $cached ) && is_array( $cached['opts'] ?? null )
			&& (int) ( $cached['ts'] ?? 0 ) + self::OPTIONS_CACHE_TTL > time() ) {
			return $cached['opts'];
		}

		if ( 'user' === $context ) {
			$table = $wpdb->usermeta;
		} elseif ( 'comment' === $context ) {
			$table = $wpdb->commentmeta;
		} else {
			$table = $wpdb->postmeta;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_value FROM {$table} WHERE meta_key = %s AND meta_value != '' ORDER BY meta_value ASC LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_key
			)
		);

		$options = [];
		foreach ( (array) $results as $value ) {
			// Skip serialized arrays/objects (not useful for filtering).
			if ( is_serialized( $value ) ) {
				continue;
			}

			$display = mb_strlen( $value ) > 40 ? mb_substr( $value, 0, 40 ) . '…' : $value;
			$options[ $value ] = $display;
		}

		$value = [
			'ts'   => time(),
			'opts' => $options,
		];

		// add_option() first so the row is created with autoload off; the
		// update path leaves the existing autoload flag untouched.
		if ( ! add_option( $cache_key, $value, '', false ) ) {
			update_option( $cache_key, $value );
		}

		return $options;
	}

	/**
	 * Gets distinct values for a user field.
	 *
	 * @since 1.4.0
	 * @param string $field User field name.
	 * @return array
	 */
	private static function get_user_field_options( $field ) {
		$options = [];

		if ( 'role' === $field || 'capabilities' === $field ) {
			global $wp_roles;
			foreach ( $wp_roles->get_names() as $slug => $name ) {
				$options[ $slug ] = $name;
			}
		}

		return $options;
	}

	/**
	 * Renders a "Clear Filters" link when any filter is active.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	private static function render_clear_button() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$has_active = false;
		foreach ( $_GET as $key => $value ) {
			if ( 0 === strpos( $key, 'bbca_filter_' ) && '' !== $value ) {
				$has_active = true;
				break;
			}
		}

		if ( ! $has_active ) {
			return;
		}

		// Build URL without filter params.
		$url = remove_query_arg(
			array_filter(
				array_keys( $_GET ),
				function ( $k ) {
					return 0 === strpos( $k, 'bbca_filter_' );
				}
			)
		);

		echo ' <a href="' . esc_url( $url ) . '" class="bbca-clear-filters button">'
			. esc_html__( 'Clear Filters', 'bb-custom-admin' )
			. '</a>';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/*
	|--------------------------------------------------------------------------
	| Query Modification — Apply Filters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Applies filter values to post-type queries.
	 *
	 * @since 1.4.0
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public static function apply_post_filters( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$post_type = $query->get( 'post_type' ) ?: 'post';
		$screen_id = 'edit-' . $post_type;

		if ( ! OneDog_BBCA_Column_Sorting::is_filtering_enabled( $screen_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( $_GET as $key => $raw_value ) {
			if ( 0 !== strpos( $key, 'bbca_filter_' ) ) {
				continue;
			}

			$column_key = substr( $key, 12 ); // Remove 'bbca_filter_' prefix.
			$value      = sanitize_text_field( $raw_value );

			if ( '' === $value ) {
				continue;
			}

			$type = OneDog_BBCA_Column_Sorting::detect_column_type( $column_key );

			switch ( $type['type'] ) {
				case 'post_field':
					self::apply_post_field_filter( $query, $type, $value );
					break;

				case 'taxonomy':
					// Taxonomy filters are handled natively by WP when using tax_query.
					// Our dropdown uses the same parameter name as WP's native filter.
					break;

				case 'meta':
				case 'post_meta':
					$meta_key = $type['meta_key'] ?? $column_key;
					$existing = $query->get( 'meta_query' );

					if ( ! is_array( $existing ) ) {
						$existing = [];
					}

					$existing[] = [
						'key'     => $meta_key,
						'value'   => $value,
						'compare' => 'LIKE',
					];

					$query->set( 'meta_query', $existing );
					break;

				default:
					// Treat as meta.
					$existing = $query->get( 'meta_query' );
					if ( ! is_array( $existing ) ) {
						$existing = [];
					}
					$existing[] = [
						'key'     => $column_key,
						'value'   => $value,
						'compare' => 'LIKE',
					];
					$query->set( 'meta_query', $existing );
					break;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Applies a post-field-specific filter to a query.
	 *
	 * @since 1.4.0
	 * @param WP_Query $query Query object.
	 * @param array    $type  Type descriptor.
	 * @param string   $value Filter value.
	 * @return void
	 */
	private static function apply_post_field_filter( $query, $type, $value ) {
		$field = $type['orderby'] ?? '';

		switch ( $field ) {
			case 'post_status':
				$query->set( 'post_status', $value );
				break;

			case 'author':
				$query->set( 'author', absint( $value ) );
				break;
		}
	}

	/**
	 * Applies filter values to user queries.
	 *
	 * @since 1.4.0
	 * @param WP_User_Query $query User query object.
	 * @return void
	 */
	public static function apply_user_filters( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! OneDog_BBCA_Column_Sorting::is_filtering_enabled( 'users' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( $_GET as $key => $raw_value ) {
			if ( 0 !== strpos( $key, 'bbca_filter_' ) ) {
				continue;
			}

			$column_key = substr( $key, 12 );
			$value      = sanitize_text_field( $raw_value );

			if ( '' === $value ) {
				continue;
			}

			$type = OneDog_BBCA_Column_Sorting::detect_column_type( $column_key );

			switch ( $type['type'] ) {
				case 'user_field':
					if ( 'role' === ( $type['orderby'] ?? '' ) || 'capabilities' === ( $type['meta_key'] ?? '' ) ) {
						$query->set( 'role', $value );
					}
					break;

				case 'user_meta':
				case 'meta':
					$meta_key = $type['meta_key'] ?? $column_key;
					$query->set( 'meta_key', $meta_key );
					$query->set( 'meta_value', $value );
					$query->set( 'meta_compare', 'LIKE' );
					break;

				default:
					$query->set( 'meta_key', $column_key );
					$query->set( 'meta_value', $value );
					$query->set( 'meta_compare', 'LIKE' );
					break;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Applies filter values to comment queries.
	 *
	 * @since 1.4.0
	 * @param array             $clauses Comment query clauses.
	 * @param WP_Comment_Query $query   Comment query object.
	 * @return array Modified clauses.
	 */
	public static function apply_comment_filters( $clauses, $query ) {
		if ( ! is_admin() ) {
			return $clauses;
		}

		if ( ! OneDog_BBCA_Column_Sorting::is_filtering_enabled( 'edit-comments' ) ) {
			return $clauses;
		}

		global $wpdb;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		foreach ( $_GET as $key => $raw_value ) {
			if ( 0 !== strpos( $key, 'bbca_filter_' ) ) {
				continue;
			}

			$column_key = substr( $key, 12 );
			$value      = sanitize_text_field( $raw_value );

			if ( '' === $value ) {
				continue;
			}

			$type = OneDog_BBCA_Column_Sorting::detect_column_type( $column_key );

			if ( 'meta' === $type['type'] || 'comment_meta' === $type['type'] ) {
				$meta_key = $type['meta_key'] ?? $column_key;

				$clauses['join'] .= $wpdb->prepare(
					" INNER JOIN {$wpdb->commentmeta} AS bbca_cf ON ({$wpdb->comments}.comment_ID = bbca_cf.comment_id AND bbca_cf.meta_key = %s AND bbca_cf.meta_value LIKE %s)",
					$meta_key,
					'%' . $wpdb->esc_like( $value ) . '%'
				);
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $clauses;
	}

	/*
	|--------------------------------------------------------------------------
	| Asset Enqueueing
	|--------------------------------------------------------------------------
	*/

	/**
	 * Enqueues column sorting CSS on list table screens.
	 *
	 * @since 1.4.0
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		$list_pages = [ 'edit.php', 'upload.php', 'users.php', 'edit-comments.php' ];

		if ( ! in_array( $hook_suffix, $list_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'onedog-bbca-column-sorting',
			BBCA_URL . 'assets/css/column-sorting.css',
			[],
			BBCA_VER
		);
	}
}

OneDog_BBCA_Column_Sorting_Filters::init();
