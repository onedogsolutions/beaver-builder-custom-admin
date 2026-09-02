<?php
/**
 * Module: Dashboard Canvas — Full-Bleed BB Layout Replacement.
 *
 * Replaces the entire WordPress dashboard with a full-bleed Beaver Builder
 * layout, and wipes the dashboard widgets it replaces, for targeted roles.
 *
 * Also hosts 3rd-Party Injection Squashing, which suppresses the notices and
 * popup overlays other plugins inject into the admin body. Squashing shares
 * this module's target-roles list but is otherwise independent: it runs on
 * every admin screen, needs no Beaver Builder layout, and never touches the
 * admin bar or the admin menu.
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
	 * Option key for the full-bleed rows toggle.
	 *
	 * @since 1.3.3
	 * @var string
	 */
	const FULL_BLEED_OPTION = 'onedog_bbca_canvas_full_bleed_rows';

	/**
	 * Option key for the theme stylesheet toggle.
	 *
	 * @since 1.3.5
	 * @var string
	 */
	const THEME_STYLES_OPTION = 'onedog_bbca_canvas_load_theme_styles';

	/**
	 * Query argument that dumps the enqueued stylesheet handles.
	 *
	 * @since 1.3.5
	 * @var string
	 */
	const DEBUG_STYLES_ARG = 'bbca_debug_styles';

	/**
	 * Body class marking the canvas as active for the current request.
	 *
	 * @since 1.3.3
	 * @var string
	 */
	const BODY_CLASS = 'bbca-canvas-active';

	/**
	 * `Class::method` identifiers already replayed this request.
	 *
	 * The theme pass walks the same hook a second time, so without this a
	 * Beaver Builder callback would run twice and any inline CSS it adds
	 * would be appended twice.
	 *
	 * @since 1.3.5
	 * @var array<string, true>
	 */
	private static $replayed = [];

	/**
	 * Initializes hooks.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function init() {
		add_action( 'current_screen', [ __CLASS__, 'setup_dashboard' ] );

		// Squashing is not canvas behaviour and does not require Beaver
		// Builder or an assigned layout, so it gets its own entry point on
		// every admin screen rather than riding along with setup_dashboard().
		add_action( 'current_screen', [ __CLASS__, 'maybe_setup_squash' ], 11 );

		// Diagnostics. Registered on both footers so the dashboard and a
		// front-end render of the same layout can be compared directly.
		add_action( 'admin_print_footer_scripts', [ __CLASS__, 'maybe_debug_styles' ], 9999 );
		add_action( 'wp_footer', [ __CLASS__, 'maybe_debug_styles' ], 9999 );
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

		// 0. Mark the request so canvas CSS can scope to the feature
		// rather than to the dashboard screen.
		add_filter( 'admin_body_class', [ __CLASS__, 'add_body_class' ] );

		// 1. Remove native Welcome Panel.
		remove_action( 'welcome_panel', 'wp_welcome_panel' );

		// 2. Wipe standard core and 3rd-party dashboard widgets.
		add_action( 'wp_dashboard_setup', [ __CLASS__, 'clear_widgets' ], 9999 );

		// 3. Inject custom Beaver Builder layout container inside #wpbody-content.
		// A late priority on all_admin_notices places the canvas after every
		// other notice callback, so it renders last inside #wpbody-content.
		add_action( 'all_admin_notices', [ __CLASS__, 'render_canvas' ], 10000 );

		// 4. Enqueue canvas-specific CSS.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		// 5. Hide WordPress branding (when enabled).
		if ( get_option( self::BRANDING_OPTION, false ) ) {
			self::setup_branding_removal();
		}

		// Squashing is registered separately, in maybe_setup_squash().
		
	}

	/**
	 * Adds the canvas body class.
	 *
	 * Only runs when the canvas is active for the current user, so the
	 * class is a reliable signal for CSS scoping.
	 *
	 * @since 1.3.3
	 * @param string $classes Space-separated admin body classes.
	 * @return string
	 */
	public static function add_body_class( $classes ) {
		return trim( $classes . ' ' . self::BODY_CLASS );
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
	 * Runs on `admin_enqueue_scripts`, which fires before
	 * `admin_print_styles` — so everything registered here reaches the
	 * document head. The canvas markup is rendered much later, on
	 * `all_admin_notices`, which is well past the point where a
	 * stylesheet can still make it into <head>.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function enqueue_assets() {
		$css_rel  = 'assets/css/admin-canvas.css';
		$css_path = BBCA_DIR . $css_rel;

		// Version by file mtime so stylesheet edits bust caches even when
		// the plugin version is unchanged.
		$css_ver = file_exists( $css_path ) ? (string) filemtime( $css_path ) : BBCA_VER;

		wp_enqueue_style( 'onedog-bbca-canvas', BBCA_URL . $css_rel, [], $css_ver );

		// Optional: let rows fill the admin column instead of honouring
		// Beaver Builder's global fixed row width.
		if ( get_option( self::FULL_BLEED_OPTION, false ) ) {
			wp_add_inline_style(
				'onedog-bbca-canvas',
				'#bbca-custom-dashboard-canvas .fl-row-fixed-width { max-width: 100%; }'
			);
		}

		if ( ! class_exists( 'FLBuilder' ) ) {
			return;
		}

		// Core Beaver Builder layout styles and scripts.
		self::guard( [ 'FLBuilder', 'register_layout_styles_scripts' ] );

		$layout_id = absint( get_option( self::LAYOUT_OPTION, 0 ) );

		if ( ! $layout_id || ! get_post( $layout_id ) ) {
			return;
		}

		// Everything below resolves against Beaver Builder's own post-ID
		// state, so scope the whole block to the assigned layout once
		// rather than per call.
		self::guard(
			static function () use ( $layout_id ) {
				self::with_post_id(
					$layout_id,
					static function () use ( $layout_id ) {
						self::enqueue_layout_stack( $layout_id );
					}
				);
			}
		);

		// Opt-in: the active theme's front-end stylesheet.
		if ( get_option( self::THEME_STYLES_OPTION, false ) ) {
			self::guard( [ __CLASS__, 'enqueue_theme_styles' ] );
		}
	}

	/**
	 * Loads every styling source the assigned layout depends on.
	 *
	 * Each source is invoked through guard() so one failure costs the
	 * canvas that stylesheet and no more. Callers are responsible for the
	 * post-ID scope — see with_post_id().
	 *
	 * @since 1.3.5
	 * @param int $layout_id Post ID of the Beaver Builder layout.
	 * @return void
	 */
	private static function enqueue_layout_stack( $layout_id ) {
		// Whatever Beaver Builder would have enqueued on the front end:
		// global styles, Google Fonts, Beaver Themer assets.
		self::guard( [ __CLASS__, 'replay_frontend_enqueue' ] );

		// Global Settings → Custom CSS, in case this Beaver Builder
		// version does not fold it into the layout cache file.
		self::guard( [ __CLASS__, 'enqueue_global_settings_css' ] );

		// Regenerate the layout cache file if it is missing.
		self::guard(
			static function () use ( $layout_id ) {
				self::maybe_render_layout_css( $layout_id );
			}
		);

		// The layout's own cached CSS/JS.
		self::guard(
			static function () use ( $layout_id ) {
				self::enqueue_layout_assets( $layout_id );
			}
		);
	}

	/**
	 * Enqueues the cached assets for a single Beaver Builder layout.
	 *
	 * Without this, the layout stylesheet is first enqueued by
	 * FLBuilderShortcode during render_canvas() and is deferred to the
	 * footer, so the layout's row and column width rules are missing on
	 * first paint.
	 *
	 * Callers are responsible for the post-ID scope — see with_post_id().
	 *
	 * @since 1.3.3
	 * @param int $layout_id Post ID of the Beaver Builder layout.
	 * @return void
	 */
	private static function enqueue_layout_assets( $layout_id ) {
		if ( ! method_exists( 'FLBuilder', 'enqueue_layout_styles_scripts' ) ) {
			return;
		}

		FLBuilder::enqueue_layout_styles_scripts( $layout_id );
	}

	/**
	 * Replays Beaver Builder's own `wp_enqueue_scripts` callbacks.
	 *
	 * A layout is styled by more than its cached stylesheet. Global
	 * Styles (the global button style and its hover state among them),
	 * Google Fonts, and Beaver Themer's layout assets are all registered
	 * on `wp_enqueue_scripts` — a hook that never fires in wp-admin. That
	 * is why a button whose colours come from Global Styles rather than
	 * from per-node settings renders on the dashboard without its hover
	 * rule: the declaration lives in a stylesheet the admin document
	 * never receives.
	 *
	 * Rather than hardcode class and method names — Beaver Builder is a
	 * commercial plugin with no versioning guarantees on its internals,
	 * and the Global Styles API in particular has moved between releases
	 * — this walks the registered `wp_enqueue_scripts` callbacks in
	 * priority order and invokes the ones Beaver Builder owns. A rename
	 * upstream costs us that one callback, not a fatal.
	 *
	 * Callbacks this class already invokes explicitly are skipped so
	 * their inline CSS is not appended twice.
	 *
	 * @since 1.3.5
	 * @param bool $include_theme Also replay Beaver Builder Theme callbacks.
	 * @return void
	 */
	public static function replay_frontend_enqueue( $include_theme = false ) {
		global $wp_filter;

		if ( empty( $wp_filter['wp_enqueue_scripts'] ) || ! ( $wp_filter['wp_enqueue_scripts'] instanceof WP_Hook ) ) {
			return;
		}

		$skip = [
			// Called directly by enqueue_assets().
			'FLBuilder::register_layout_styles_scripts',
			// Called directly by enqueue_layout_assets().
			'FLBuilder::enqueue_layout_styles_scripts',
		];

		$callbacks  = $wp_filter['wp_enqueue_scripts']->callbacks;
		$priorities = array_keys( $callbacks );
		sort( $priorities, SORT_NUMERIC );

		foreach ( $priorities as $priority ) {
			foreach ( $callbacks[ $priority ] as $registered ) {
				$id = self::callback_id( $registered['function'] ?? null );

				if ( null === $id || isset( self::$replayed[ $id ] ) || in_array( $id, $skip, true ) ) {
					continue;
				}

				if ( ! self::owns_layout_assets( strstr( $id, '::', true ), $include_theme ) ) {
					continue;
				}

				self::$replayed[ $id ] = true;

				self::guard( $registered['function'] );
			}
		}
	}

	/**
	 * Resolves a filter callback to a `Class::method` identifier.
	 *
	 * Plain functions and closures cannot be attributed to a plugin, so
	 * they return null and are left alone — replaying an unattributable
	 * front-end callback in wp-admin is exactly the kind of side effect
	 * this feature must not introduce.
	 *
	 * @since 1.3.5
	 * @param mixed $callback Registered callback.
	 * @return string|null
	 */
	private static function callback_id( $callback ) {
		if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) && is_string( $callback[1] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];

			return $class . '::' . $callback[1];
		}

		return null;
	}

	/**
	 * Decides whether a class's front-end enqueues belong on the canvas.
	 *
	 * `FLBuilder*` is the builder plugin and `FLThemeBuilder*` is Beaver
	 * Themer; both style the layout itself and are always replayed.
	 * Everything else under `FLTheme*` is the Beaver Builder *Theme*,
	 * which styles the whole document — it is gated behind the
	 * Theme Styles setting because in wp-admin it also restyles the
	 * admin menu, toolbar, and footer.
	 *
	 * @since 1.3.5
	 * @param string|false $class         Class name, or false when unresolved.
	 * @param bool         $include_theme Whether theme classes are wanted.
	 * @return bool
	 */
	private static function owns_layout_assets( $class, $include_theme ) {
		if ( ! is_string( $class ) || '' === $class ) {
			return false;
		}

		if ( 0 === strpos( $class, 'FLThemeBuilder' ) ) {
			return true;
		}

		if ( 0 === strpos( $class, 'FLTheme' ) || 0 === strpos( $class, 'FLCustomizer' ) ) {
			return (bool) $include_theme;
		}

		return 0 === strpos( $class, 'FLBuilder' );
	}

	/**
	 * Inlines the Custom CSS from Beaver Builder's Global Settings.
	 *
	 * Some Beaver Builder versions fold this into the cached layout
	 * stylesheet and some do not. When it is already in the cache file
	 * this is a duplicate of rules that print later and therefore still
	 * win — harmless. When it is not, this is the only path by which it
	 * reaches the dashboard.
	 *
	 * @since 1.3.5
	 * @return void
	 */
	public static function enqueue_global_settings_css() {
		if ( ! class_exists( 'FLBuilderModel' ) || ! method_exists( 'FLBuilderModel', 'get_global_settings' ) ) {
			return;
		}

		$settings = FLBuilderModel::get_global_settings();
		$css      = is_object( $settings ) && isset( $settings->css ) ? (string) $settings->css : '';

		if ( '' === trim( $css ) ) {
			return;
		}

		wp_add_inline_style( 'onedog-bbca-canvas', $css );
	}

	/**
	 * Renders the layout's cached CSS when the cache file is missing.
	 *
	 * Beaver Builder regenerates its per-layout cache lazily, on a front-
	 * end render. Nothing on an admin request does that, so on a fresh
	 * install — or on the first dashboard hit after someone clears the
	 * builder's cache — the stylesheet enqueued by
	 * enqueue_layout_assets() is a 404 and the canvas renders unstyled.
	 *
	 * Callers are responsible for the post-ID scope — see with_post_id().
	 *
	 * @since 1.3.5
	 * @param int $layout_id Post ID of the Beaver Builder layout.
	 * @return void
	 */
	private static function maybe_render_layout_css( $layout_id ) {
		if ( ! class_exists( 'FLBuilderModel' ) || ! method_exists( 'FLBuilderModel', 'get_asset_info' ) ) {
			return;
		}

		if ( ! method_exists( 'FLBuilder', 'render_css' ) ) {
			return;
		}

		$info = FLBuilderModel::get_asset_info();

		if ( is_object( $info ) ) {
			$info = (array) $info;
		}

		if ( ! is_array( $info ) || empty( $info['css'] ) ) {
			return;
		}

		if ( file_exists( $info['css'] ) ) {
			return;
		}

		// The cache file is the point of this call, not the return value.
		// Some Beaver Builder versions echo the CSS as well, and
		// `admin_enqueue_scripts` fires inside <head> — raw CSS printed
		// there would land in the document as text.
		ob_start();
		FLBuilder::render_css();
		ob_end_clean();
	}

	/**
	 * Enqueues the active theme's front-end stylesheet. Opt-in.
	 *
	 * On a Beaver Builder Theme site the button colours — hover included
	 * — are customizer values held in the theme's generated stylesheet,
	 * so a layout that leans on them cannot be styled correctly on the
	 * dashboard without it.
	 *
	 * The cost is that theme CSS is written for a front-end document. Its
	 * `body`, `a`, and heading rules apply to the whole admin page, not
	 * just to the canvas, so this restyles the admin menu, toolbar, and
	 * footer too. That is why it is a setting and why it defaults to off:
	 * leave it off unless the layout visibly needs it.
	 *
	 * @since 1.3.5
	 * @return void
	 */
	public static function enqueue_theme_styles() {
		$theme   = wp_get_theme();
		$version = $theme->get( 'Version' );

		wp_enqueue_style(
			'onedog-bbca-theme',
			get_stylesheet_uri(),
			[],
			$version ? $version : BBCA_VER
		);

		// A child theme's parent stylesheet, and the Beaver Builder
		// Theme's customizer-generated CSS, are enqueued by the theme's
		// own front-end callbacks.
		self::replay_frontend_enqueue( true );
	}

	/*
	|--------------------------------------------------------------------------
	| Beaver Builder Interop Helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * Runs a callback with Beaver Builder scoped to a given post ID.
	 *
	 * Beaver Builder resolves the layout from its own post-ID state, and
	 * the signature of enqueue_layout_styles_scripts() has varied across
	 * versions, so the post ID is set explicitly and restored afterwards.
	 * `set_post_id()` / `reset_post_id()` are a stack upstream, so nesting
	 * is safe; `finally` guarantees the pop even if the callback throws.
	 *
	 * @since 1.3.5
	 * @param int      $post_id  Post ID to scope to.
	 * @param callable $callback Work to run inside the scope.
	 * @return void
	 */
	private static function with_post_id( $post_id, $callback ) {
		$scoped = class_exists( 'FLBuilderModel' )
			&& method_exists( 'FLBuilderModel', 'set_post_id' )
			&& method_exists( 'FLBuilderModel', 'reset_post_id' );

		if ( $scoped ) {
			FLBuilderModel::set_post_id( $post_id );
		}

		try {
			call_user_func( $callback );
		} finally {
			if ( $scoped ) {
				FLBuilderModel::reset_post_id();
			}
		}
	}

	/**
	 * Invokes a callback, swallowing anything it throws.
	 *
	 * This code calls into a commercial plugin's internals on the
	 * dashboard — the one admin screen every user lands on. A rename or
	 * a signature change upstream must cost the site its canvas styling,
	 * never its ability to log in and fix the problem. Failures are
	 * logged when WP_DEBUG is on and are otherwise silent.
	 *
	 * @since 1.3.5
	 * @param callable $callback Work to run.
	 * @return void
	 */
	private static function guard( $callback ) {
		try {
			call_user_func( $callback );
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					sprintf(
						'OneDog BBCA: canvas asset loading failed — %s in %s:%d',
						$e->getMessage(),
						$e->getFile(),
						$e->getLine()
					)
				);
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Diagnostics
	|--------------------------------------------------------------------------
	*/

	/**
	 * Prints the enqueued stylesheet handles as an HTML comment.
	 *
	 * Diagnosing missing layout styling means comparing what the document
	 * head holds on the dashboard against what it holds on a front-end
	 * render of the same layout; the difference is the problem. Append
	 * `?bbca_debug_styles=1` to either URL to get that list without
	 * editing code.
	 *
	 * Administrators only, and an HTML comment rather than visible output
	 * so it cannot disturb the canvas.
	 *
	 * @since 1.3.5
	 * @return void
	 */
	public static function maybe_debug_styles() {
		if ( ! isset( $_GET[ self::DEBUG_STYLES_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$styles = wp_styles();

		if ( ! $styles instanceof WP_Styles ) {
			return;
		}

		printf(
			"\n<!-- bbca-debug-styles: %s -->\n",
			esc_html( implode( ', ', array_map( 'strval', $styles->done ) ) )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| 3rd-Party Injection Squashing
	|--------------------------------------------------------------------------
	*/

	/**
	 * Registers squash hooks when squashing applies to this request.
	 *
	 * Runs on every admin screen. Squashing suppresses interruptions other
	 * plugins inject into the admin body; it deliberately does not touch the
	 * admin bar or the admin menu.
	 *
	 * @since 1.4.0
	 * @param WP_Screen $screen The current admin screen.
	 * @return void
	 */
	public static function maybe_setup_squash( $screen ) {
		if ( ! self::should_squash( $screen ) ) {
			return;
		}

		// Drop notice callbacks registered by other plugins. Core callbacks
		// are left alone so WordPress's own messages still get through.
		add_action( 'in_admin_header', [ __CLASS__, 'remove_third_party_notices' ] );

		// CSS net for promotional markup that bypasses the notice hooks.
		add_action( 'admin_head', [ __CLASS__, 'squash_notice_css' ] );

		// Hide and remove popup/overlay interruptions.
		add_action( 'admin_head', [ __CLASS__, 'squash_popup_css' ] );
		add_action( 'admin_footer', [ __CLASS__, 'squash_popup_script' ] );
	}

	/**
	 * Returns the admin notice hooks squashing filters.
	 *
	 * @since 1.4.0
	 * @return string[]
	 */
	private static function notice_hooks() {
		return [
			'admin_notices',
			'all_admin_notices',
			'user_admin_notices',
			'network_admin_notices',
		];
	}

	/**
	 * Removes admin notice callbacks that belong to other plugins.
	 *
	 * Each registered callback is resolved to the file that defines it. A
	 * callback defined under the plugins or mu-plugins directory is a
	 * third-party injection and is removed; one defined in wp-admin or
	 * wp-includes is WordPress itself and is kept, so "Settings saved",
	 * activation results and update failures still reach the user. This is
	 * the distinction the pre-1.4.0 output buffer could not make - it
	 * discarded everything printed between two hooks, core messages included.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function remove_third_party_notices() {
		global $wp_filter;

		foreach ( self::notice_hooks() as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) || ! ( $wp_filter[ $hook ] instanceof WP_Hook ) ) {
				continue;
			}

			// Snapshot: remove_action() mutates the live callbacks array.
			$registered = $wp_filter[ $hook ]->callbacks;

			foreach ( $registered as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( self::is_third_party_callback( $callback['function'] ) ) {
						remove_action( $hook, $callback['function'], $priority );
					}
				}
			}
		}
	}

	/**
	 * Determines whether a callback is defined by a third-party plugin.
	 *
	 * Anything that cannot be resolved is treated as not third-party, so an
	 * unrecognised callback is left registered rather than silently dropped.
	 *
	 * @since 1.4.0
	 * @param callable $callback The registered callback.
	 * @return bool
	 */
	private static function is_third_party_callback( $callback ) {
		try {
			if ( $callback instanceof Closure ) {
				$ref = new ReflectionFunction( $callback );
			} elseif ( is_array( $callback ) && 2 === count( $callback ) ) {
				$ref = new ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$ref = new ReflectionMethod( $callback );
			} elseif ( is_string( $callback ) && function_exists( $callback ) ) {
				$ref = new ReflectionFunction( $callback );
			} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
				$ref = new ReflectionMethod( $callback, '__invoke' );
			} else {
				return false;
			}

			$file = $ref->getFileName();
		} catch ( ReflectionException $e ) {
			return false;
		}

		// Internal functions have no defining file.
		if ( ! $file ) {
			return false;
		}

		$file = wp_normalize_path( $file );

		// Never remove this plugin's own notices.
		if ( 0 === strpos( $file, wp_normalize_path( BBCA_DIR ) ) ) {
			return false;
		}

		$dirs = [ WP_PLUGIN_DIR ];

		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$dirs[] = WPMU_PLUGIN_DIR;
		}

		foreach ( $dirs as $dir ) {
			if ( 0 === strpos( $file, trailingslashit( wp_normalize_path( $dir ) ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Outputs CSS hiding promotional notice markup that bypasses the hooks.
	 *
	 * Scoped to promotional wording rather than the blanket
	 * `.notice, .error, .updated` this used before 1.4.0, which hid every
	 * message on the screen including WordPress's own.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function squash_notice_css() {
		$selectors = [
			'.notice[class*="promo"]',
			'.notice[class*="upsell"]',
			'.notice[class*="upgrade"]',
			'.notice[class*="review"]',
			'.notice[class*="rating"]',
			'.notice[class*="deal"]',
			'.notice[class*="sale"]',
			'.update-nag',
		];

		echo '<style id="onedog-bbca-squash-notices">'
			. esc_html( implode( ',', $selectors ) )
			. '{display:none !important;}</style>';
	}

	/**
	 * Returns the popup/overlay selectors squashing hides.
	 *
	 * There is no structural signal separating a plugin's upsell modal from
	 * a legitimate one, so this is a blocklist, not a general rule. The
	 * defaults match on promotional wording in class and id attributes
	 * rather than naming individual plugins, and deliberately exclude core
	 * and Beaver Builder UI (.media-modal, #TB_window, .fl-builder-*).
	 *
	 * Filter `onedog_bbca_squash_selectors` to add exact selectors for
	 * offenders on a given site without needing a plugin release.
	 *
	 * @since 1.4.0
	 * @return string[]
	 */
	public static function popup_selectors() {
		$selectors = [
			'[class*="upsell-modal"]',
			'[class*="promo-modal"]',
			'[class*="review-modal"]',
			'[class*="rating-modal"]',
			'[class*="feedback-modal"]',
			'[class*="review-notice"]',
			'[class*="promo-popup"]',
			'[id*="review-notice"]',
			'[id*="_review_notice"]',
			'[id*="upsell-popup"]',
		];

		return array_filter( array_unique( (array) apply_filters( 'onedog_bbca_squash_selectors', $selectors ) ) );
	}

	/**
	 * Outputs CSS hiding popup overlays, so they never flash before the
	 * script below removes them.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function squash_popup_css() {
		$selectors = self::popup_selectors();

		if ( empty( $selectors ) ) {
			return;
		}

		echo '<style id="onedog-bbca-squash-popups">'
			. esc_html( implode( ',', $selectors ) )
			. '{display:none !important;}</style>';
	}

	/**
	 * Removes matched popups from the DOM and releases any scroll lock.
	 *
	 * Hiding an overlay with CSS is not enough: modals commonly lock page
	 * scrolling via a class on <body>, which would strand the page
	 * unscrollable behind an element nobody can see. The scroll lock is only
	 * released when something actually matched, so a legitimate modal that
	 * this does not hide keeps its lock.
	 *
	 * The observer catches overlays injected after load and stops after ten
	 * seconds rather than watching the document for the life of the page.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function squash_popup_script() {
		$selectors = self::popup_selectors();

		if ( empty( $selectors ) ) {
			return;
		}

		?>
		<script id="onedog-bbca-squash-popups-js">
		( function() {
			var selectors = <?php echo wp_json_encode( array_values( $selectors ) ); ?>;

			function sweep() {
				var hit = false;

				selectors.forEach( function( selector ) {
					try {
						document.querySelectorAll( selector ).forEach( function( el ) {
							el.parentNode.removeChild( el );
							hit = true;
						} );
					} catch ( e ) {}
				} );

				if ( hit && document.body ) {
					[ 'modal-open', 'no-scroll', 'overflow-hidden' ].forEach( function( c ) {
						document.body.classList.remove( c );
					} );
					document.body.style.overflow = '';
				}
			}

			sweep();

			if ( 'undefined' !== typeof MutationObserver && document.body ) {
				var observer = new MutationObserver( sweep );
				observer.observe( document.body, { childList: true, subtree: true } );
				window.setTimeout( function() {
					observer.disconnect();
				}, 10000 );
			}
		} )();
		</script>
		<?php
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
			. 'body.bbca-canvas-active #wp-admin-bar-wp-logo, '
			. 'body.bbca-canvas-active .update-nag, '
			. 'body.bbca-canvas-active #footer-upgrade { '
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
	 * Checks whether 3rd-party injection squashing applies to this request.
	 *
	 * Deliberately does NOT call is_active_for_user(). Squashing shares that
	 * method's target-roles list and bypass, but not its Beaver Builder
	 * dependency, its layout requirement, or its dashboard-only scope -
	 * suppressing another plugin's nag has nothing to do with whether a
	 * canvas layout is assigned, and needs to work on every admin screen.
	 *
	 * @since 1.3.0
	 * @param WP_Screen|null $screen The current admin screen.
	 * @return bool
	 */
	public static function should_squash( $screen = null ) {
		// Emergency bypass for administrators.
		if ( isset( $_GET['bbca_bypass'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}

		if ( ! get_option( self::SQUASH_OPTION, false ) ) {
			return false;
		}

		if ( self::is_exempt_screen( $screen ) ) {
			return false;
		}

		$target_roles = (array) get_option( self::ROLES_OPTION, [] );

		if ( empty( $target_roles ) ) {
			return false;
		}

		$user = wp_get_current_user();

		return (bool) array_intersect( $target_roles, (array) $user->roles );
	}

	/**
	 * Checks whether the current screen is exempt from squashing.
	 *
	 * On these screens the notices are the point: they carry install,
	 * update and activation results, and suppressing them would leave the
	 * user unable to tell whether an action succeeded.
	 *
	 * Beaver Builder's editor needs no entry here - it is a front-end
	 * request, so `current_screen` never fires and squashing never
	 * registers.
	 *
	 * @since 1.4.0
	 * @param WP_Screen|null $screen The current admin screen.
	 * @return bool
	 */
	private static function is_exempt_screen( $screen ) {
		$exempt = [
			'update-core',
			'update',
			'plugins',
			'plugin-install',
			'plugin-editor',
			'themes',
			'theme-install',
			'theme-editor',
			'site-health',
		];

		$exempt = (array) apply_filters( 'onedog_bbca_squash_exempt_screens', $exempt );

		if ( ! $screen instanceof WP_Screen ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		}

		if ( ! $screen instanceof WP_Screen ) {
			return false;
		}

		return in_array( $screen->id, $exempt, true );
	}
}

OneDog_BBCA_Dashboard_Canvas::init();
