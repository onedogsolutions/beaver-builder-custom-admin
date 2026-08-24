<?php
/**
 * Plugin Name: Beaver Builder Custom Admin
 * Description: Modular WordPress admin customization — full-bleed dashboard canvas, role/menu/toolbar visibility, notice cleaner, and 3rd-party squashing by user role.
 * Author: Ryan Waterbury
 * Author URI: https://onedog.solutions/
 * Version: 1.3.5
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GNU General Public License v2.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bb-custom-admin
 * Domain Path: /languages
 *
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

define( 'BBCA_VER', '1.3.5' );
define( 'BBCA_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBCA_URL', plugins_url( '/', __FILE__ ) );
define( 'BBCA_PATH', plugin_basename( __FILE__ ) );

/**
 * The legacy plugin this replaces.
 */
define( 'BBCA_LEGACY_PLUGIN', 'bb-dashboard-welcome/bb-dashboard-welcome.php' );

/**
 * Activation hook: deactivate the legacy plugin if it is still active.
 *
 * @since 0.1.0
 * @package OneDog\BBCustomAdmin
 * @return void
 */
function onedog_bbca_activate() {
	if ( is_plugin_active( BBCA_LEGACY_PLUGIN ) ) {
		deactivate_plugins( BBCA_LEGACY_PLUGIN );
	}
}
register_activation_hook( __FILE__, 'onedog_bbca_activate' );

/**
 * Menu slug for the settings page.
 *
 * @since 1.3.4
 */
define( 'BBCA_MENU_SLUG', 'onedog-bbca-settings' );

/**
 * Registers the settings page as a top-level admin menu item.
 *
 * Was a Settings submenu until 1.3.4. On a site with a normal plugin
 * load-out the Settings flyout is taller than the viewport, and because
 * add_options_page() appends, this page was the last entry in it - below
 * the fold and unreachable by hover. A top-level item does not depend on
 * flyout height or on where the parent sits in the sidebar.
 *
 * @since 0.2.0
 * @return void
 */
function onedog_bbca_admin_menu() {
	add_menu_page(
		__( 'Custom Admin', 'bb-custom-admin' ),
		__( 'Custom Admin', 'bb-custom-admin' ),
		'manage_options',
		BBCA_MENU_SLUG,
		'onedog_bbca_render_settings_page',
		'dashicons-admin-customizer',
		80.7
	);
}
add_action( 'admin_menu', 'onedog_bbca_admin_menu', 25 );

/**
 * Redirects the pre-1.3.4 Settings submenu URL to the top-level page.
 *
 * Keeps existing bookmarks and any stored Menu Restrictor rules that
 * still point at options-general.php working.
 *
 * @since 1.3.4
 * @return void
 */
function onedog_bbca_redirect_legacy_settings_url() {
	if ( 'options-general.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) {
		return;
	}

	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

	if ( BBCA_MENU_SLUG !== $page ) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . BBCA_MENU_SLUG ) );
	exit;
}
add_action( 'admin_init', 'onedog_bbca_redirect_legacy_settings_url' );

/**
 * Renders the settings page root element for React.
 *
 * @since 0.2.0
 * @return void
 */
function onedog_bbca_render_settings_page() {
	echo '<div id="onedog-bbca-settings-root"></div>';
}

/**
 * Enqueues settings page assets (React app + styles).
 *
 * @since 0.2.0
 * @param string $hook_suffix The current admin page hook suffix.
 * @return void
 */
function onedog_bbca_enqueue_settings_assets( $hook_suffix ) {
	// 'toplevel_page_*' since 1.3.4; 'settings_page_*' is the pre-1.3.4
	// hook suffix, kept so the page still loads its assets if anything
	// re-parents it under Settings.
	$hooks = [
		'toplevel_page_' . BBCA_MENU_SLUG,
		'settings_page_' . BBCA_MENU_SLUG,
	];

	if ( ! in_array( $hook_suffix, $hooks, true ) ) {
		return;
	}

	$asset_file = BBCA_DIR . 'build/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require $asset_file;

	wp_enqueue_script(
		'onedog-bbca-settings',
		BBCA_URL . 'build/index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);

	// Localize script with settings data.
	wp_localize_script(
		'onedog-bbca-settings',
		'bbcaSettings',
		[
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'restUrl'  => esc_url_raw( rest_url() ),
			'version'  => BBCA_VER,
		]
	);

	wp_enqueue_style(
		'onedog-bbca-settings',
		BBCA_URL . 'build/index.css',
		[],
		$asset['version']
	);
}
add_action( 'admin_enqueue_scripts', 'onedog_bbca_enqueue_settings_assets' );

// Load REST API and module system.
require_once BBCA_DIR . 'classes/class-onedog-bb-rest.php';
require_once BBCA_DIR . 'includes/modules/class-module-loader.php';
