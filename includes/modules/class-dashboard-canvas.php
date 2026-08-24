<?php
/**
 * Module: Dashboard Canvas — Full-Bleed BB Layout Replacement.
 *
 * Replaces the entire WordPress dashboard with a full-bleed Beaver Builder
 * layout. Simultaneously squashes third-party admin notices, toolbar
 * clutter, and dashboard widgets for targeted user roles.
 *
 * @since 1.3.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

/**
 * OneDog_BBCA_Dashboard_Canvas
 *
 * @since 1.3.0
 */
final class OneDog_BBCA_Dashboard_Canvas {

	/**
	 * Option key for the dashboard layout post ID.
	 *
	 * @var string
	 */
	const LAYOUT_OPTION = 'onedog_bbca_canvas_layout_id';

	/**
	 * Option key for roles subject to canvas replacement and squashing.
	 *
	 * @var string
	 */
	const ROLES_OPTION = 'onedog_bbca_canvas_target_roles';

	/**
	 * Option key for the 3rd-party injection squash master toggle.
	 *
	 * @var string
	 */
	const SQUASH_OPTION = 'onedog_bbca_canvas_enable_squash';

	/**
	 * Option key for the WordPress branding removal toggle.
	 *
	 * @var string
	 */
	const BRANDING_OPTION = 'onedog_bbca_canvas_hide_wp_branding';

	/**
	 * Output-buffer nesting level when notice suppression starts.
	 *
	 * @var int|false
	 */
	private static $ob_level = false;

	/**
	 * Initializes hooks.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function init() {
		add_action( 'current_screen', [ __CLASS__, 'setup_dashboard' ] );
	}

	/*
	|--------------------------------------------------------------------------
	| Dashboard Setup
	|--------------------------------------------------------------------------
	*/

	/**
	 * Conditionally sets up dashboard replacement on the dashboard screen.
	 *
	 * Fires on the `current_screen` action which provides a reliable
	 * WP_Screen object. All dashboard-specific hooks are registered here
	 * so they only run when the canvas is active.
	 *
	 * @since 1.3.0
	 * @param WP_Screen $screen The current admin screen.
	 * @return void
	 */
	public static function setup_dashboard( $screen ) {
		if ( ! $screen || 'dashboard' !== $screen->id || ! self::is_active_for_user() ) {
			return;
		}

		// 1. Kill native Welcome Panel.
		remove_action( 'welcome_panel', 'wp_welcome_panel' );

		// 2. Wipe standard core and 3rd-party dashboard widgets.
		add_action( 'wp_dashboard_setup', [ __CLASS__, 'clear_widgets' ], 9999 );

		// 3. Inject custom Beaver Builder layout container.
		add_action( 'in_admin_header', [ __CLASS__, 'render_canvas' ] );

		// 4. Enqueue canvas-specific CSS.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		// 5. Squash 3rd-party injections (when enabled).
		if ( self::should_squash() ) {
			self::setup_squash();
		}

		// 6. Hide WordPress branding (when enabled).
		if ( get_option( self::BRANDING_OPTION, false ) ) {
			self::setup_branding_removal();
		}
	}

	/**
	 * Clears all dashboard meta boxes (widgets).
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function clear_widgets() {
		global $wp_meta_boxes;
		$wp_meta_boxes['dashboard'] = [];
	}

	/**
	 * Renders the Beaver Builder layout into the dashboard canvas.
	 *
	 * Uses FLBuilderShortcode::insert() when available (handles asset
	 * loading internally), falling back to do_shortcode() otherwise.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function render_canvas() {
		$layout_id = absint( get_option( self::LAYOUT_OPTION, 0 ) );

		if ( ! $layout_id || ! class_exists( 'FLBuilder' ) ) {
			return;
		}

		// Verify the layout post still exists.
		if ( ! get_post( $layout_id ) ) {
			return;
		}

		echo '<div id="bbca-custom-dashboard-canvas">';

		if ( class_exists( 'FLBuilderShortcode' ) && method_exists( 'FLBuilderShortcode', 'insert' ) ) {
			echo FLBuilderShortcode::insert( [ 'id' => $layout_id ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo do_shortcode( '[fl_builder_insert_layout id="' . esc_attr( $layout_id ) . '"]' );
		}

		echo '</div>';
	}

	/*
	|--------------------------------------------------------------------------
	| Asset Management
	|--------------------------------------------------------------------------
	*/

	/**
	 * Enqueues canvas CSS and Beaver Builder layout assets on index.php.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function enqueue_assets() {
		// Canvas full-bleed styles.
		wp_enqueue_style(
			'onedog-bbca-canvas',
			BBCA_URL . 'assets/css/admin-canvas.css',
			[],
			BBCA_VER
		);

		// Existing frontend styles (BB content resets).
		wp_enqueue_style(
			'onedog-bbca-frontend',
			BBCA_URL . 'assets/css/frontend.css',
			[],
			BBCA_VER
		);

		// Frontend JS (panel repositioning helper).
		wp_enqueue_script(
			'onedog-bbca-frontend',
			BBCA_URL . 'assets/js/frontend.js',
			[],
			BBCA_VER,
			[
				'strategy'  => 'defer',
				'in_footer' => true,
			]
		);

		// Beaver Builder layout styles and scripts.
		if ( class_exists( 'FLBuilder' ) ) {
			FLBuilder::register_layout_styles_scripts();
		}
	}

	/*
	|--------------------------------------------------------------------------
	| 3rd-Party Injection Squashing
	|--------------------------------------------------------------------------
	*/

	/**
	 * Registers squash hooks for notice suppression, toolbar cleanup,
	 * and admin menu decluttering.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private static function setup_squash() {
		// Output-buffer notice suppression.
		add_action( 'admin_notices', [ __CLASS__, 'start_notice_buffer' ], 1 );
		add_action( 'all_admin_notices', [ __CLASS__, 'end_notice_buffer' ], 9999 );

		// Whitelist-based toolbar sanitization.
		add_action( 'wp_before_admin_bar_render', [ __CLASS__, 'squash_toolbar' ], 9999 );

		// CSS safety net for notices that bypass hooks.
		add_action( 'admin_head', [ __CLASS__, 'squash_notice_css' ] );
	}

	/**
	 * Starts output buffering to capture and discard admin notices.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function start_notice_buffer() {
		self::$ob_level = ob_get_level();
		ob_start();
	}

	/**
	 * Ends output buffering, discarding any captured notice output.
	 *
	 * Includes a nesting-level safety check in case another plugin
	 * interfered with the buffer stack between the two hooks.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function end_notice_buffer() {
		if ( false !== self::$ob_level && ob_get_level() > self::$ob_level ) {
			ob_end_clean();
		}
		self::$ob_level = false;
	}

	/**
	 * Outputs CSS as a safety net to hide any notices that bypass hooks.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function squash_notice_css() {
		echo '<style id="onedog-bbca-squash-notices">'
			. 'body.index-php .notice, body.index-php .update-nag, '
			. 'body.index-php .error, body.index-php .updated { '
			. 'display: none !important; }</style>';
	}

	/**
	 * Removes all top-level admin bar nodes except a safe whitelist.
	 *
	 * Preserves child nodes of allowed parents (e.g., sub-items under
	 * site-name or my-account).
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function squash_toolbar() {
		global $wp_admin_bar;

		if ( ! is_object( $wp_admin_bar ) ) {
			return;
		}

		$allowed = [
			'wp-logo',
			'site-name',
			'my-account',
			'logout',
			'fl-builder-frontend',
		];

		$nodes = $wp_admin_bar->get_nodes();

		if ( empty( $nodes ) ) {
			return;
		}

		foreach ( $nodes as $node_id => $node ) {
			if ( ! in_array( $node_id, $allowed, true ) && empty( $node->parent ) ) {
				$wp_admin_bar->remove_node( $node_id );
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| WordPress Branding Removal
	|--------------------------------------------------------------------------
	*/

	/**
	 * Registers hooks to strip WordPress logos, update naggers,
	 * and footer credits.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private static function setup_branding_removal() {
		add_action( 'wp_before_admin_bar_render', [ __CLASS__, 'remove_wp_logo' ], 0 );
		add_action( 'admin_head', [ __CLASS__, 'hide_branding_css' ] );
		add_filter( 'admin_footer_text', [ __CLASS__, 'clear_footer_text' ] );
		add_filter( 'update_footer', [ __CLASS__, 'clear_footer_version' ], 9999 );
	}

	/**
	 * Removes the WordPress logo from the admin bar.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function remove_wp_logo() {
		global $wp_admin_bar;

		if ( is_object( $wp_admin_bar ) ) {
			$wp_admin_bar->remove_node( 'wp-logo' );
		}
	}

	/**
	 * Outputs CSS to hide remaining WordPress branding elements.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function hide_branding_css() {
		echo '<style id="onedog-bbca-hide-branding">'
			. 'body.index-php #wp-admin-bar-wp-logo, '
			. 'body.index-php .update-nag, '
			. 'body.index-php #footer-upgrade { '
			. 'display: none !important; }</style>';
	}

	/**
	 * Clears the admin footer text.
	 *
	 * @since 1.3.0
	 * @param string $text Original footer text.
	 * @return string
	 */
	public static function clear_footer_text( $text ) {
		return '';
	}

	/**
	 * Clears the WordPress version string from the admin footer.
	 *
	 * @since 1.3.0
	 * @param string $content Original footer version content.
	 * @return string
	 */
	public static function clear_footer_version( $content ) {
		return '';
	}

	/*
	|--------------------------------------------------------------------------
	| Safety Rules & Role Verification
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks whether the dashboard canvas is active for the current user.
	 *
	 * Resolution order:
	 * 1. Emergency bypass (?bbca_bypass=1 for administrators).
	 * 2. Beaver Builder dependency check.
	 * 3. Layout ID existence.
	 * 4. Role membership in the target roles array.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function is_active_for_user() {
		// Emergency bypass for administrators.
		if ( isset( $_GET['bbca_bypass'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		// Dependency check.
		if ( ! class_exists( 'FLBuilder' ) ) {
			return false;
		}

		// Layout must be assigned.
		$layout_id = absint( get_option( self::LAYOUT_OPTION, 0 ) );
		if ( ! $layout_id ) {
			return false;
		}

		// Role check.
		$user         = wp_get_current_user();
		$target_roles = (array) get_option( self::ROLES_OPTION, [] );

		if ( empty( $target_roles ) ) {
			return false;
		}

		return (bool) array_intersect( $target_roles, (array) $user->roles );
	}

	/**
	 * Checks whether 3rd-party injection squashing should be active.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public static function should_squash() {
		return (bool) get_option( self::SQUASH_OPTION, false ) && self::is_active_for_user();
	}
}

OneDog_BBCA_Dashboard_Canvas::init();
