<?php
/**
 * Module: Column Sorting — Addon Integrations.
 *
 * Lightweight adapters that help the core column sorting module
 * detect column types registered by WooCommerce, Gravity Forms,
 * and Pods. Each adapter only runs when its respective plugin
 * is active.
 *
 * @since 1.4.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Column_Sorting_Integrations
 *
 * @since 1.4.0
 */
final class OneDog_BBCA_Column_Sorting_Integrations {

	/**
	 * Option key for the cached WooCommerce column map.
	 *
	 * @var string
	 */
	const WC_MAP_CACHE = 'onedog_bbca_cs_wc_map';

	/**
	 * Option key for the cached Gravity Forms column map.
	 *
	 * @var string
	 */
	const GF_MAP_CACHE = 'onedog_bbca_cs_gf_map';

	/**
	 * Option key for the cached Pods column map.
	 *
	 * @var string
	 */
	const PODS_MAP_CACHE = 'onedog_bbca_cs_pods_map';

	/**
	 * How long a cached integration map stays fresh, in seconds.
	 *
	 * @var int
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Initializes hooks.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function init() {
		add_filter( 'onedog_bbca_column_type_map', [ __CLASS__, 'register_integrations' ] );

		// Activating or deactivating a plugin changes what each adapter
		// discovers, so the cached maps expire with the plugin set.
		add_action( 'activated_plugin', [ __CLASS__, 'flush_integration_caches' ] );
		add_action( 'deactivated_plugin', [ __CLASS__, 'flush_integration_caches' ] );
	}

	/**
	 * Extends the column type map with addon-specific entries.
	 *
	 * @since 1.4.0
	 * @param array $map Existing type map.
	 * @return array Extended type map.
	 */
	public static function register_integrations( $map ) {
		if ( class_exists( 'WooCommerce' ) ) {
			$map = array_merge( $map, self::get_woocommerce_map() );
		}

		if ( class_exists( 'GFForms' ) ) {
			$map = array_merge( $map, self::get_gravityforms_map() );
		}

		if ( class_exists( 'Pods' ) || class_exists( 'PodsInit' ) ) {
			$map = array_merge( $map, self::get_pods_map() );
		}

		return $map;
	}

	/*
	|--------------------------------------------------------------------------
	| WooCommerce Integration
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns column type mappings for WooCommerce columns.
	 *
	 * Cached in a non-autoloaded option; the underlying discovery only
	 * changes when the plugin set or the store's product attributes do.
	 *
	 * @since 1.4.0
	 * @return array
	 */
	private static function get_woocommerce_map() {
		return self::cached_map( self::WC_MAP_CACHE, 'build_woocommerce_map' );
	}

	/**
	 * Builds the WooCommerce column type map.
	 *
	 * Maps WooCommerce's registered column names to their underlying
	 * storage (post meta or custom table fields).
	 *
	 * @since 1.6.2
	 * @return array
	 */
	private static function build_woocommerce_map() {
		$map = [
			// Product list table columns.
			'price'              => [
				'type'      => 'post_meta',
				'meta_key'  => '_price',
				'numeric'   => true,
				'meta_type' => 'DECIMAL',
			],
			'sku'                => [
				'type'     => 'post_meta',
				'meta_key' => '_sku',
				'numeric'  => false,
			],
			'is_in_stock'        => [
				'type'     => 'post_meta',
				'meta_key' => '_stock_status',
				'numeric'  => false,
			],
			'product_cat'        => [
				'type'     => 'taxonomy',
				'taxonomy' => 'product_cat',
			],
			'product_tag'        => [
				'type'     => 'taxonomy',
				'taxonomy' => 'product_tag',
			],
			'featured'           => [
				'type'     => 'post_meta',
				'meta_key' => '_featured',
				'numeric'  => false,
			],
			'product_type'       => [
				'type'     => 'taxonomy',
				'taxonomy' => 'product_type',
			],

			// Order list table columns (HPOS and legacy).
			'order_number'       => [
				'type'    => 'post_field',
				'orderby' => 'ID',
			],
			'order_date'         => [
				'type'    => 'post_field',
				'orderby' => 'date',
			],
			'order_status'       => [
				'type'    => 'post_field',
				'orderby' => 'post_status',
			],
			'order_total'        => [
				'type'      => 'post_meta',
				'meta_key'  => '_order_total',
				'numeric'   => true,
				'meta_type' => 'DECIMAL',
			],
			'billing_address'    => [
				'type'     => 'post_meta',
				'meta_key' => '_billing_last_name',
				'numeric'  => false,
			],
			'shipping_address'   => [
				'type'     => 'post_meta',
				'meta_key' => '_shipping_last_name',
				'numeric'  => false,
			],
			'customer_message'   => [
				'type'    => 'post_field',
				'orderby' => 'none',
			],
			'order_notes'        => [
				'type' => 'none',
			],
			'order_actions'      => [
				'type' => 'none',
			],
			'wc_actions'         => [
				'type' => 'none',
			],

			// Coupon list table columns.
			'coupon_code'        => [
				'type'    => 'post_field',
				'orderby' => 'title',
			],
			'coupon_type'        => [
				'type'     => 'post_meta',
				'meta_key' => 'discount_type',
				'numeric'  => false,
			],
			'coupon_amount'      => [
				'type'      => 'post_meta',
				'meta_key'  => 'coupon_amount',
				'numeric'   => true,
				'meta_type' => 'DECIMAL',
			],
			'usage_limit'        => [
				'type'      => 'post_meta',
				'meta_key'  => 'usage_limit',
				'numeric'   => true,
			],
			'expiry_date'        => [
				'type'     => 'post_meta',
				'meta_key' => 'date_expires',
				'numeric'  => false,
			],
		];

		// WooCommerce product attributes registered as columns.
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			$attributes = wc_get_attribute_taxonomies();
			if ( is_array( $attributes ) ) {
				foreach ( $attributes as $attr ) {
					$tax_name = wc_attribute_taxonomy_name( $attr->attribute_name );
					$map[ $tax_name ] = [
						'type'     => 'taxonomy',
						'taxonomy' => $tax_name,
					];
				}
			}
		}

		return $map;
	}

	/*
	|--------------------------------------------------------------------------
	| Gravity Forms Integration
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns column type mappings for Gravity Forms entry columns.
	 *
	 * Cached in a non-autoloaded option; form fields only change when
	 * forms are edited, which the TTL absorbs.
	 *
	 * GF entries are stored in the `wp_gf_entry` and `wp_gf_entry_meta`
	 * tables. Column names typically follow the pattern `field_id_{n}`
	 * or are core entry fields.
	 *
	 * @since 1.4.0
	 * @return array
	 */
	private static function get_gravityforms_map() {
		return self::cached_map( self::GF_MAP_CACHE, 'build_gravityforms_map' );
	}

	/**
	 * Builds the Gravity Forms column type map.
	 *
	 * @since 1.6.2
	 * @return array
	 */
	private static function build_gravityforms_map() {
		$map = [
			// Core GF entry fields.
			'entry_id'      => [
				'type' => 'gf_entry_field',
				'field' => 'id',
			],
			'date_created'  => [
				'type' => 'gf_entry_field',
				'field' => 'date_created',
			],
			'date_updated'  => [
				'type' => 'gf_entry_field',
				'field' => 'date_updated',
			],
			'ip'            => [
				'type' => 'gf_entry_field',
				'field' => 'ip',
			],
			'source_url'    => [
				'type' => 'gf_entry_field',
				'field' => 'source_url',
			],
			'created_by'    => [
				'type' => 'gf_entry_field',
				'field' => 'created_by',
			],
			'status'        => [
				'type' => 'gf_entry_field',
				'field' => 'status',
			],
		];

		// Dynamically discover GF form field columns.
		if ( class_exists( 'GFFormsModel' ) && method_exists( 'GFFormsModel', 'get_forms' ) ) {
			$forms = \GFFormsModel::get_forms( true );

			if ( is_array( $forms ) ) {
				foreach ( $forms as $form ) {
					if ( ! isset( $form->fields ) || ! is_array( $form->fields ) ) {
						continue;
					}

					foreach ( $form->fields as $field ) {
						$key = 'field_id_' . $field->id;
						$map[ $key ] = [
							'type'      => 'gf_entry_meta',
							'meta_key'  => (string) $field->id,
							'form_id'   => $form->id,
							'numeric'   => in_array( $field->type ?? '', [ 'number', 'quantity', 'total' ], true ),
						];
					}
				}
			}
		}

		return $map;
	}

	/*
	|--------------------------------------------------------------------------
	| Pods Integration
	|--------------------------------------------------------------------------
	*/

	/**
	 * Returns column type mappings for Pods custom fields.
	 *
	 * Pods stores custom fields either as post meta (table storage)
	 * or in custom Pods tables. This integration detects both patterns.
	 *
	 * Cached in a non-autoloaded option; pod definitions only change when
	 * they are edited, which the TTL absorbs.
	 *
	 * @since 1.4.0
	 * @return array
	 */
	private static function get_pods_map() {
		return self::cached_map( self::PODS_MAP_CACHE, 'build_pods_map' );
	}

	/**
	 * Builds the Pods column type map.
	 *
	 * One full load_pods() call returns every pod with its fields attached.
	 * The previous shape — a names/ids list followed by one load_pod() per
	 * pod — cost a query per pod on sites without a persistent object cache.
	 *
	 * Pods returns either plain arrays (legacy) or ArrayAccess objects
	 * (Pods\Whatsit\Pod / Field); the array access syntax below serves both.
	 *
	 * @since 1.6.2
	 * @return array
	 */
	private static function build_pods_map() {
		$map = [];

		if ( ! function_exists( 'pods_api' ) ) {
			return $map;
		}

		$api = pods_api();

		if ( ! $api || ! method_exists( $api, 'load_pods' ) ) {
			return $map;
		}

		$pods_list = $api->load_pods();

		if ( ! is_array( $pods_list ) ) {
			return $map;
		}

		foreach ( $pods_list as $pod ) {
			$fields = $pod['fields'] ?? null;

			if ( empty( $fields ) || ! is_array( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field_name => $field_data ) {
				// Only map if not already in the type map.
				if ( isset( $map[ $field_name ] ) ) {
					continue;
				}

				$is_numeric = in_array( $field_data['type'] ?? '', [ 'number', 'currency', 'pick' ], true );

				$map[ $field_name ] = [
					'type'     => 'post_meta',
					'meta_key' => $field_name,
					'numeric'  => $is_numeric,
				];
			}
		}

		return $map;
	}

	/**
	 * Returns an integration map, cached across requests.
	 *
	 * Discovery hits the plugin APIs — and, on sites without a persistent
	 * object cache, the database — and the results only change when the
	 * plugin set does, so a cache entry absorbs the cost. Maps are stored
	 * as non-autoloaded options: an object-cache drop-in without a
	 * persistent backend makes transients per-request on affected hosts.
	 *
	 * @since 1.6.2
	 * @param string $cache_key Option key.
	 * @param string $builder   Map builder method name.
	 * @return array
	 */
	private static function cached_map( $cache_key, $builder ) {
		$cached = get_option( $cache_key );

		if ( is_array( $cached ) && is_array( $cached['map'] ?? null )
			&& (int) ( $cached['ts'] ?? 0 ) + self::CACHE_TTL > time() ) {
			return $cached['map'];
		}

		$map = self::$builder();

		$value = [
			'ts'  => time(),
			'map' => $map,
		];

		// add_option() first so the row is created with autoload off; the
		// update path leaves the existing autoload flag untouched.
		if ( ! add_option( $cache_key, $value, '', false ) ) {
			update_option( $cache_key, $value );
		}

		return $map;
	}

	/**
	 * Deletes all cached integration maps.
	 *
	 * @since 1.6.2
	 * @return void
	 */
	public static function flush_integration_caches() {
		delete_option( self::WC_MAP_CACHE );
		delete_option( self::GF_MAP_CACHE );
		delete_option( self::PODS_MAP_CACHE );
	}
}

OneDog_BBCA_Column_Sorting_Integrations::init();
