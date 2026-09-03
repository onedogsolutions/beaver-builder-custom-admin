<?php
/**
 * Module: Column Sorting & Filtering.
 *
 * Makes all WordPress admin list table columns sortable and adds
 * a smart filtering sidebar. Automatically discovers columns
 * registered by WooCommerce, Gravity Forms, and Pods addons.
 *
 * @since 1.4.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Column_Sorting
 *
 * @since 1.4.0
 */
final class OneDog_BBCA_Column_Sorting {

	/**
	 * Option key for column sorting settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'onedog_bbca_column_sorting';

	/**
	 * Nonce action for filter forms.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'onedog_bbca_filter';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = '_bbca_filter_nonce';

	/**
	 * Cached settings for the current request.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Column type map populated by integrations.
	 *
	 * Null until first needed: building it touches the Pods, Gravity Forms
	 * and WooCommerce APIs, which has no business running on requests that
	 * never sort or filter a list table. See detect_column_type().
	 *
	 * @var array<string, array>|null column_name => type descriptor
	 */
	private static $type_map = null;

	/**
	 * Runtime cache of discovered columns per screen.
	 *
	 * @var array<string, array>
	 */
	private static $column_cache = [];

	/**
	 * Initializes hooks.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function init() {
		// Register sortable columns on every list screen.
		add_filter( 'manage_posts_columns',         [ __CLASS__, 'tag_post_columns' ], 999, 2 );
		add_filter( 'manage_pages_columns',         [ __CLASS__, 'tag_post_columns' ], 999, 2 );
		add_filter( 'manage_media_columns',         [ __CLASS__, 'tag_post_columns' ], 999, 2 );
		add_filter( 'manage_users_columns',         [ __CLASS__, 'tag_user_columns' ], 999 );
		add_filter( 'manage_edit-comments_columns', [ __CLASS__, 'tag_comment_columns' ], 999 );

		// Register sortable columns.
		add_filter( 'manage_edit-post_sortable_columns',         [ __CLASS__, 'register_sortable' ] );
		add_filter( 'manage_edit-page_sortable_columns',         [ __CLASS__, 'register_sortable' ] );
		add_filter( 'manage_upload_sortable_columns',            [ __CLASS__, 'register_sortable' ] );
		add_filter( 'manage_edit-comments_sortable_columns',     [ __CLASS__, 'register_sortable' ] );
		add_filter( 'manage_users_sortable_columns',             [ __CLASS__, 'register_sortable' ] );

		// Query handlers.
		add_action( 'pre_get_posts',    [ __CLASS__, 'handle_post_sorting' ] );
		add_action( 'pre_user_query',   [ __CLASS__, 'handle_user_sorting' ] );
		add_filter( 'comments_clauses', [ __CLASS__, 'handle_comment_sorting' ], 10, 2 );

		// CPT-specific sortable column filters (registered dynamically).
		add_action( 'current_screen', [ __CLASS__, 'register_cpt_sortable' ] );

		// Enqueue assets on list table screens.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
	}

	/*
	|--------------------------------------------------------------------------
	| Settings
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns the column sorting settings.
	 *
	 * @since 1.4.0
	 * @return array
	 */
	public static function get_settings() {
		if ( null === self::$settings ) {
			$saved = get_option( self::OPTION_KEY, [] );
			self::$settings = wp_parse_args(
				is_array( $saved ) ? $saved : [],
				[ 'screens' => [] ]
			);
		}
		return self::$settings;
	}

	/**
	 * Saves column sorting settings.
	 *
	 * @since 1.4.0
	 * @param array $settings Settings array.
	 * @return array Sanitized settings.
	 */
	public static function save_settings( $settings ) {
		if ( ! is_array( $settings ) || ! isset( $settings['screens'] ) || ! is_array( $settings['screens'] ) ) {
			return self::get_settings();
		}

		$sanitized = [ 'screens' => [] ];

		foreach ( $settings['screens'] as $screen_id => $screen_settings ) {
			$screen_id = sanitize_key( $screen_id );

			if ( ! is_array( $screen_settings ) ) {
				continue;
			}

			$sanitized['screens'][ $screen_id ] = [
				'sorting'         => ! empty( $screen_settings['sorting'] ),
				'filtering'       => ! empty( $screen_settings['filtering'] ),
				'default_sort'    => sanitize_text_field( $screen_settings['default_sort'] ?? '' ),
				'default_order'   => in_array( strtolower( $screen_settings['default_order'] ?? '' ), [ 'asc', 'desc' ], true )
					? strtolower( $screen_settings['default_order'] )
					: 'desc',
				'filter_columns'  => isset( $screen_settings['filter_columns'] ) && is_array( $screen_settings['filter_columns'] )
					? array_map( 'sanitize_text_field', $screen_settings['filter_columns'] )
					: [],
			];
		}

		update_option( self::OPTION_KEY, $sanitized );
		self::$settings = $sanitized;

		return $sanitized;
	}

	/**
	 * Checks whether sorting is enabled for a given screen.
	 *
	 * @since 1.4.0
	 * @param string $screen_id Screen ID.
	 * @return bool
	 */
	public static function is_sorting_enabled( $screen_id ) {
		$settings = self::get_settings();
		return ! empty( $settings['screens'][ $screen_id ]['sorting'] );
	}

	/**
	 * Checks whether filtering is enabled for a given screen.
	 *
	 * @since 1.4.0
	 * @param string $screen_id Screen ID.
	 * @return bool
	 */
	public static function is_filtering_enabled( $screen_id ) {
		$settings = self::get_settings();
		return ! empty( $settings['screens'][ $screen_id ]['filtering'] );
	}

	/*
	|--------------------------------------------------------------------------
	| Column Type Map
	|--------------------------------------------------------------------------
	*/

	/**
	 * Builds the column type map, including integration-provided entries.
	 *
	 * Runs lazily on the first detect_column_type() call rather than on
	 * every admin request. The REST handler and tests may also call it
	 * directly.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function build_type_map() {
		self::$type_map = [];

		// Core post field mappings.
		$core_post_fields = [
			'title'       => [ 'type' => 'post_field', 'orderby' => 'title' ],
			'date'        => [ 'type' => 'post_field', 'orderby' => 'date' ],
			'modified'    => [ 'type' => 'post_field', 'orderby' => 'modified' ],
			'author'      => [ 'type' => 'post_field', 'orderby' => 'author' ],
			'parent'      => [ 'type' => 'post_field', 'orderby' => 'parent' ],
			'comments'    => [ 'type' => 'post_field', 'orderby' => 'comment_count' ],
			'cb'          => [ 'type' => 'none' ],
			'ID'          => [ 'type' => 'post_field', 'orderby' => 'ID' ],
			'post_status' => [ 'type' => 'post_field', 'orderby' => 'post_status' ],
		];

		self::$type_map = array_merge( self::$type_map, $core_post_fields );

		// Core user field mappings.
		$core_user_fields = [
			'username'     => [ 'type' => 'user_field', 'orderby' => 'login' ],
			'name'         => [ 'type' => 'user_field', 'orderby' => 'display_name' ],
			'email'        => [ 'type' => 'user_field', 'orderby' => 'email' ],
			'url'          => [ 'type' => 'user_field', 'orderby' => 'url' ],
			'registered'   => [ 'type' => 'user_field', 'orderby' => 'registered' ],
			'role'         => [ 'type' => 'user_meta', 'meta_key' => $GLOBALS['wpdb']->prefix . 'capabilities' ],
			'posts'        => [ 'type' => 'post_count' ],
		];

		self::$type_map = array_merge( self::$type_map, $core_user_fields );

		// Taxonomy columns (taxonomy-{slug} pattern).
		$taxonomies = get_taxonomies( [ 'show_admin_column' => true ], 'names' );
		foreach ( $taxonomies as $tax_slug ) {
			self::$type_map[ 'taxonomy-' . $tax_slug ] = [
				'type'     => 'taxonomy',
				'taxonomy' => $tax_slug,
			];
		}

		// Allow integrations to extend the type map.
		self::$type_map = apply_filters( 'onedog_bbca_column_type_map', self::$type_map );
	}

	/**
	 * Detects the type descriptor for a given column.
	 *
	 * @since 1.4.0
	 * @param string $column_name Column name.
	 * @return array Type descriptor with at minimum a 'type' key.
	 */
	public static function detect_column_type( $column_name ) {
		// Build the map on first use; see the $type_map docblock.
		if ( null === self::$type_map ) {
			self::build_type_map();
		}

		// Direct match in type map.
		if ( isset( self::$type_map[ $column_name ] ) ) {
			return self::$type_map[ $column_name ];
		}

		// Taxonomy pattern: taxonomy-{slug}.
		if ( 0 === strpos( $column_name, 'taxonomy-' ) ) {
			$tax_slug = substr( $column_name, 9 );
			return [
				'type'     => 'taxonomy',
				'taxonomy' => $tax_slug,
			];
		}

		// Common meta key pattern: many plugins use the column name as the meta key.
		return [
			'type'     => 'meta',
			'meta_key' => $column_name,
			'numeric'  => false,
		];
	}

	/**
	 * Checks whether a column has an explicit, known type.
	 *
	 * Columns absent from the type map are usually custom renderer columns
	 * (an icon, a button, a value computed at render time), not stored meta,
	 * so a filter dropdown built for them would query the database for a
	 * meta key that may not exist — on a table chosen by guesswork. Callers
	 * that need only trustworthy columns use this instead of
	 * detect_column_type(), whose meta fallback is deliberately optimistic.
	 *
	 * @since 1.6.2
	 * @param string $column_name Column name.
	 * @return bool
	 */
	public static function is_known_column_type( $column_name ) {
		if ( null === self::$type_map ) {
			self::build_type_map();
		}

		return isset( self::$type_map[ $column_name ] )
			|| 0 === strpos( (string) $column_name, 'taxonomy-' );
	}

	/*
	|--------------------------------------------------------------------------
	| Column Tagging — Mark Columns As Discovered
	|--------------------------------------------------------------------------
	*/

	/**
	 * Tags post-type columns so we know which screen they belong to.
	 *
	 * @since 1.4.0
	 * @param array  $columns   Column definitions.
	 * @param string $post_type Post type slug.
	 * @return array Unmodified columns.
	 */
	public static function tag_post_columns( $columns, $post_type = '' ) {
		if ( empty( $post_type ) && is_admin() ) {
			$screen = get_current_screen();
			$post_type = $screen ? $screen->post_type : 'post';
		}
		self::store_columns( 'edit-' . $post_type, $columns );
		return $columns;
	}

	/**
	 * Tags user columns.
	 *
	 * @since 1.4.0
	 * @param array $columns Column definitions.
	 * @return array Unmodified columns.
	 */
	public static function tag_user_columns( $columns ) {
		self::store_columns( 'users', $columns );
		return $columns;
	}

	/**
	 * Tags comment columns.
	 *
	 * @since 1.4.0
	 * @param array $columns Column definitions.
	 * @return array Unmodified columns.
	 */
	public static function tag_comment_columns( $columns ) {
		self::store_columns( 'edit-comments', $columns );
		return $columns;
	}

	/**
	 * Stores discovered columns for a screen in a static cache.
	 *
	 * @since 1.4.0
	 * @param string $screen_id Screen ID.
	 * @param array  $columns   Column definitions.
	 * @return void
	 */
	private static function store_columns( $screen_id, $columns ) {
		self::$column_cache[ $screen_id ] = $columns;
	}

	/**
	 * Returns the cached columns for a screen.
	 *
	 * @since 1.4.0
	 * @param string $screen_id Screen ID.
	 * @return array
	 */
	public static function get_cached_columns( $screen_id ) {
		return self::$column_cache[ $screen_id ] ?? [];
	}

	/*
	|--------------------------------------------------------------------------
	| Sortable Column Registration
	|--------------------------------------------------------------------------
	*/

	/**
	 * Registers all columns as sortable for the current screen.
	 *
	 * @since 1.4.0
	 * @param array $sortable Existing sortable columns.
	 * @return array Merged sortable columns.
	 */
	public static function register_sortable( $sortable ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return $sortable;
		}

		$screen_id = $screen->id;

		if ( ! self::is_sorting_enabled( $screen_id ) ) {
			return $sortable;
		}

		// Get all columns registered for this screen.
		$columns = self::discover_columns_for_screen( $screen );

		foreach ( $columns as $key => $label ) {
			$type = self::detect_column_type( $key );

			// Skip non-sortable types (checkbox, etc.).
			if ( 'none' === $type['type'] ) {
				continue;
			}

			if ( ! isset( $sortable[ $key ] ) ) {
				$sortable[ $key ] = $key;
			}
		}

		return $sortable;
	}

	/**
	 * Dynamically registers sortable columns for CPT screens.
	 *
	 * @since 1.4.0
	 * @param WP_Screen $screen Current screen object.
	 * @return void
	 */
	public static function register_cpt_sortable( $screen ) {
		if ( ! $screen || 'edit' !== $screen->base || ! $screen->post_type ) {
			return;
		}

		if ( ! self::is_sorting_enabled( $screen->id ) ) {
			return;
		}

		$filter = "manage_edit-{$screen->post_type}_sortable_columns";
		add_filter( $filter, [ __CLASS__, 'register_sortable' ] );
	}

	/**
	 * Discovers all columns for a given screen.
	 *
	 * @since 1.4.0
	 * @param WP_Screen $screen Screen object.
	 * @return array Column definitions.
	 */
	public static function discover_columns_for_screen( $screen ) {
		if ( ! $screen ) {
			return [];
		}

		$columns = [];

		switch ( $screen->base ) {
			case 'edit':
				if ( $screen->post_type ) {
					$columns = apply_filters( "manage_edit-{$screen->post_type}_columns", [] );
				}
				break;

			case 'users':
				$columns = apply_filters( 'manage_users_columns', [] );
				break;

			case 'edit-comments':
				$columns = apply_filters( 'manage_edit-comments_columns', [] );
				break;

			case 'upload':
				$columns = apply_filters( 'manage_media_columns', [] );
				break;
		}

		// Also check the static cache from tagging filters.
		if ( empty( $columns ) ) {
			$columns = self::get_cached_columns( $screen->id );
		}

		return is_array( $columns ) ? $columns : [];
	}

	/**
	 * Returns available screens with their columns.
	 *
	 * Used by the REST API and settings UI.
	 *
	 * @since 1.4.0
	 * @return array
	 */
	public static function get_available_screens() {
		$screens = [];

		// Standard post types.
		$post_types = get_post_types( [ 'show_ui' => true ], 'objects' );

		foreach ( $post_types as $pt ) {
			$screen_id = 'edit-' . $pt->name;
			$columns   = apply_filters( "manage_edit-{$pt->name}_columns", [] );

			// If filters haven't run yet, use a basic set.
			if ( empty( $columns ) ) {
				$columns = self::get_default_post_columns( $pt->name );
			}

			$label = $pt->labels->name ?? $pt->label;

			// Append plugin source for CPTs from known plugins.
			if ( class_exists( 'WooCommerce' ) && in_array( $pt->name, [ 'product', 'shop_order', 'shop_coupon' ], true ) ) {
				$label .= ' (WooCommerce)';
			}

			$screens[] = [
				'id'      => $screen_id,
				'label'   => $label,
				'columns' => self::shape_columns( $columns ),
			];
		}

		// Users screen.
		$user_columns = apply_filters( 'manage_users_columns', [] );
		if ( empty( $user_columns ) ) {
			$user_columns = [
				'username'   => __( 'Username', 'bb-custom-admin' ),
				'name'       => __( 'Name', 'bb-custom-admin' ),
				'email'      => __( 'Email', 'bb-custom-admin' ),
				'role'       => __( 'Role', 'bb-custom-admin' ),
				'posts'      => __( 'Posts', 'bb-custom-admin' ),
				'registered' => __( 'Registered', 'bb-custom-admin' ),
			];
		}
		$screens[] = [
			'id'      => 'users',
			'label'   => __( 'Users', 'bb-custom-admin' ),
			'columns' => self::shape_columns( $user_columns ),
		];

		// Comments screen.
		$comment_columns = apply_filters( 'manage_edit-comments_columns', [] );
		if ( empty( $comment_columns ) ) {
			$comment_columns = [
				'author'   => __( 'Author', 'bb-custom-admin' ),
				'comment'  => __( 'Comment', 'bb-custom-admin' ),
				'date'     => __( 'Date', 'bb-custom-admin' ),
				'response' => __( 'In Response To', 'bb-custom-admin' ),
			];
		}
		$screens[] = [
			'id'      => 'edit-comments',
			'label'   => __( 'Comments', 'bb-custom-admin' ),
			'columns' => self::shape_columns( $comment_columns ),
		];

		// Gravity Forms entries screen (if active).
		if ( class_exists( 'GFForms' ) ) {
			$gf_forms = \GFFormsModel::get_forms( true );

			if ( is_array( $gf_forms ) ) {
				foreach ( $gf_forms as $form ) {
					$screen_id = 'gf_edit_entries_' . $form->id;
					$screens[] = [
						'id'      => $screen_id,
						'label'   => sprintf(
							/* translators: %s: Gravity Forms form title */
							__( 'GF Entries: %s', 'bb-custom-admin' ),
							$form->title
						),
						'columns' => self::shape_columns( self::get_gf_form_columns( $form ) ),
					];
				}
			}
		}

		return $screens;
	}

	/**
	 * Shapes column definitions into a simple key=>label array.
	 *
	 * @since 1.4.0
	 * @param array $columns Raw columns.
	 * @return array
	 */
	private static function shape_columns( $columns ) {
		$shaped = [];
		foreach ( $columns as $key => $label ) {
			if ( 'cb' === $key ) {
				continue;
			}
			$shaped[] = [
				'key'   => $key,
				'label' => wp_strip_all_tags( $label ),
			];
		}
		return $shaped;
	}

	/**
	 * Returns a default set of columns for a post type when filters
	 * haven't populated them yet (e.g., REST context).
	 *
	 * @since 1.4.0
	 * @param string $post_type Post type slug.
	 * @return array
	 */
	private static function get_default_post_columns( $post_type ) {
		$columns = [
			'cb'       => '<input type="checkbox" />',
			'title'    => __( 'Title', 'bb-custom-admin' ),
			'author'   => __( 'Author', 'bb-custom-admin' ),
		];

		// Add taxonomy columns that show for this post type.
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $tax ) {
			if ( $tax->show_admin_column ) {
				$columns[ 'taxonomy-' . $tax->name ] = $tax->labels->singular_name;
			}
		}

		$columns['comments'] = '<span class="vers comment-grey-bubble" title="' . esc_attr__( 'Comments', 'bb-custom-admin' ) . '"><span class="screen-reader-text">' . __( 'Comments', 'bb-custom-admin' ) . '</span></span>';
		$columns['date']     = __( 'Date', 'bb-custom-admin' );

		return $columns;
	}

	/**
	 * Returns columns for a Gravity Forms form.
	 *
	 * @since 1.4.0
	 * @param object $form GF form object.
	 * @return array
	 */
	private static function get_gf_form_columns( $form ) {
		$columns = [
			'cb'             => '<input type="checkbox" />',
			'entry_id'       => __( 'Entry Id', 'bb-custom-admin' ),
			'date_created'   => __( 'Date', 'bb-custom-admin' ),
			'ip'             => __( 'IP', 'bb-custom-admin' ),
			'source_url'     => __( 'Source', 'bb-custom-admin' ),
		];

		if ( isset( $form->fields ) && is_array( $form->fields ) ) {
			foreach ( $form->fields as $field ) {
				$columns[ 'field_id_' . $field->id ] = $field->label ?? "Field {$field->id}";
			}
		}

		return $columns;
	}

	/*
	|--------------------------------------------------------------------------
	| Post Sorting Handler
	|--------------------------------------------------------------------------
	*/

	/**
	 * Handles sorting for post-type list tables via pre_get_posts.
	 *
	 * @since 1.4.0
	 * @param WP_Query $query The query object.
	 * @return void
	 */
	public static function handle_post_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( empty( $orderby ) ) {
			// Apply default sort if configured.
			$screen_id = 'edit-' . ( $query->get( 'post_type' ) ?: 'post' );

			if ( self::is_sorting_enabled( $screen_id ) ) {
				$settings = self::get_settings();
				$default  = $settings['screens'][ $screen_id ]['default_sort'] ?? '';

				if ( ! empty( $default ) && empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$orderby = $default;
					$query->set( 'order', $settings['screens'][ $screen_id ]['default_order'] ?? 'desc' );
				}
			}
		}

		if ( empty( $orderby ) ) {
			return;
		}

		$screen_id = 'edit-' . ( $query->get( 'post_type' ) ?: 'post' );

		if ( ! self::is_sorting_enabled( $screen_id ) ) {
			return;
		}

		$type = self::detect_column_type( $orderby );

		switch ( $type['type'] ) {
			case 'post_field':
				$query->set( 'orderby', $type['orderby'] );
				break;

			case 'meta':
			case 'post_meta':
				$meta_key = $type['meta_key'] ?? $orderby;
				$numeric  = ! empty( $type['numeric'] );

				$query->set( 'meta_key', $meta_key );
				$query->set( 'orderby', $numeric ? 'meta_value_num' : 'meta_value' );

				// Use LEFT JOIN so posts without the meta still appear.
				if ( ! empty( $type['meta_type'] ) ) {
					$query->set( 'meta_type', $type['meta_type'] );
				}

				add_filter( 'posts_clauses', [ __CLASS__, 'meta_left_join' ], 10, 2 );
				break;

			case 'taxonomy':
				self::handle_taxonomy_sort( $query, $type['taxonomy'] ?? $orderby );
				break;

			case 'none':
				break;

			default:
				// Treat unknown types as meta.
				$query->set( 'meta_key', $orderby );
				$query->set( 'orderby', 'meta_value' );
				add_filter( 'posts_clauses', [ __CLASS__, 'meta_left_join' ], 10, 2 );
				break;
		}
	}

	/**
	 * Forces a LEFT JOIN on postmeta so posts without the key still show.
	 *
	 * @since 1.4.0
	 * @param array    $clauses SQL clauses.
	 * @param WP_Query $query   Query object.
	 * @return array Modified clauses.
	 */
	public static function meta_left_join( $clauses, $query ) {
		global $wpdb;

		// Replace INNER JOIN with LEFT JOIN for postmeta.
		$clauses['join'] = preg_replace(
			"/INNER\s+JOIN\s+{$wpdb->postmeta}/",
			"LEFT JOIN {$wpdb->postmeta}",
			$clauses['join'],
			1
		);

		remove_filter( 'posts_clauses', [ __CLASS__, 'meta_left_join' ], 10 );

		return $clauses;
	}

	/**
	 * Handles taxonomy column sorting by joining term tables.
	 *
	 * @since 1.4.0
	 * @param WP_Query $query    The query object.
	 * @param string   $taxonomy Taxonomy slug.
	 * @return void
	 */
	private static function handle_taxonomy_sort( $query, $taxonomy ) {
		global $wpdb;

		add_filter( 'posts_clauses', function ( $clauses ) use ( $wpdb, $taxonomy ) {
			$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS bbca_tr ON ({$wpdb->posts}.ID = bbca_tr.object_id)"
				. " LEFT JOIN {$wpdb->term_taxonomy} AS bbca_tt ON (bbca_tr.term_taxonomy_id = bbca_tt.term_taxonomy_id AND bbca_tt.taxonomy = '" . esc_sql( $taxonomy ) . "')"
				. " LEFT JOIN {$wpdb->terms} AS bbca_t ON (bbca_tt.term_id = bbca_t.term_id)";

			$clauses['orderby'] = 'bbca_t.name ' . ( 'ASC' === strtoupper( $query->get( 'order' ) ?: 'ASC' ) ? 'ASC' : 'DESC' ) . ', ' . $clauses['orderby'];

			$clauses['groupby'] = "{$wpdb->posts}.ID";

			return $clauses;
		}, 10, 1 );
	}

	/*
	|--------------------------------------------------------------------------
	| User Sorting Handler
	|--------------------------------------------------------------------------
	*/

	/**
	 * Handles sorting for the user list table via pre_user_query.
	 *
	 * @since 1.4.0
	 * @param WP_User_Query $query User query object.
	 * @return void
	 */
	public static function handle_user_sorting( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! self::is_sorting_enabled( 'users' ) ) {
			return;
		}

		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Apply default sort if no explicit orderby.
		if ( empty( $orderby ) ) {
			$settings = self::get_settings();
			$default  = $settings['screens']['users']['default_sort'] ?? '';

			if ( ! empty( $default ) ) {
				$orderby = $default;
			}
		}

		if ( empty( $orderby ) ) {
			return;
		}

		$type = self::detect_column_type( $orderby );

		switch ( $type['type'] ) {
			case 'user_field':
				$query->set( 'orderby', $type['orderby'] );
				break;

			case 'user_meta':
			case 'meta':
			case 'post_meta':
				$meta_key = $type['meta_key'] ?? $orderby;
				$numeric  = ! empty( $type['numeric'] );

				$query->set( 'meta_key', $meta_key );
				$query->set( 'orderby', $numeric ? 'meta_value_num' : 'meta_value' );

				// Force LEFT JOIN so users without meta still appear.
				add_action( 'pre_user_query', function ( $q ) use ( $wpdb ) {
					// No-op; WP_User_Query handles meta_key JOINs.
				}, 20 );
				break;

			case 'post_count':
				$query->set( 'orderby', 'post_count' );
				break;

			default:
				// Treat as user meta.
				$query->set( 'meta_key', $orderby );
				$query->set( 'orderby', 'meta_value' );
				break;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Comment Sorting Handler
	|--------------------------------------------------------------------------
	*/

	/**
	 * Handles sorting for the comment list table via comments_clauses.
	 *
	 * @since 1.4.0
	 * @param array             $clauses Comment query clauses.
	 * @param WP_Comment_Query $query   Comment query object.
	 * @return array Modified clauses.
	 */
	public static function handle_comment_sorting( $clauses, $query ) {
		if ( ! is_admin() ) {
			return $clauses;
		}

		if ( ! self::is_sorting_enabled( 'edit-comments' ) ) {
			return $clauses;
		}

		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $orderby ) ) {
			return $clauses;
		}

		global $wpdb;
		$order = 'ASC' === strtoupper( $_GET['order'] ?? 'ASC' ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$type = self::detect_column_type( $orderby );

		switch ( $type['type'] ) {
			case 'post_field':
				// Map common comment column names to actual DB fields.
				$map = [
					'author'   => "{$wpdb->comments}.comment_author",
					'date'     => "{$wpdb->comments}.comment_date_gmt",
					'comment'  => "{$wpdb->comments}.comment_content",
					'response' => "{$wpdb->comments}.comment_post_ID",
				];

				$field = $map[ $type['orderby'] ] ?? "{$wpdb->comments}.comment_date_gmt";
				$clauses['orderby'] = "{$field} {$order}";
				break;

			case 'meta':
			case 'comment_meta':
				$meta_key = $type['meta_key'] ?? $orderby;

				$clauses['join']   .= " LEFT JOIN {$wpdb->commentmeta} AS bbca_cm ON ({$wpdb->comments}.comment_ID = bbca_cm.comment_id AND bbca_cm.meta_key = '" . esc_sql( $meta_key ) . "')";
				$clauses['orderby'] = "bbca_cm.meta_value {$order}";
				break;
		}

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
	public static function enqueue_admin_assets( $hook_suffix ) {
		// Only load on list table pages.
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

OneDog_BBCA_Column_Sorting::init();

// Load supporting files.
require_once __DIR__ . '/class-column-sorting-filters.php';
require_once __DIR__ . '/class-column-sorting-integrations.php';
