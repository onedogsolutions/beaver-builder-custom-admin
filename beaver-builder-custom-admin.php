<?php
/**
 * Plugin Name: Beaver Builder Custom Admin
 * Description: Modular WordPress admin customization — full-bleed dashboard canvas, role/menu/toolbar visibility, notice cleaner, and 3rd-party squashing by user role.
 * Author: Ryan Waterbury
 * Author URI: https://onedog.solutions/
 * Version: 1.3.1
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

define( 'BBCA_VER', '1.3.1' );
define( 'BBCA_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBCA_URL', plugins_url( '/', __FILE__ ) );
define( 'BBCA_PATH', plugin_basename( __FILE__ ) );

/**
 * The legacy plugin this replaces.
 */
define( 'BBCA_LEGACY_PLUGIN', 'bb-dashboard-welcome/bb-dashboard-welcome.php' );
define( 'BBCA_LEGACY_OPTION', 'bbpd_template' );

/**
 * Activation hook: migrate legacy settings and deactivate the old plugin.
 *
 * @since 0.1.0
 * @package OneDog\BBCustomAdmin
 * @return void
 */
function onedog_bbca_activate() {
	$legacy = get_option( BBCA_LEGACY_OPTION );
	$new    = get_option( 'onedog_bbca_template' );

	if ( false !== $legacy && false === $new ) {
		update_option( 'onedog_bbca_template', $legacy );
		delete_option( BBCA_LEGACY_OPTION );
	}

	if ( is_plugin_active( BBCA_LEGACY_PLUGIN ) ) {
		deactivate_plugins( BBCA_LEGACY_PLUGIN );
	}
}
register_activation_hook( __FILE__, 'onedog_bbca_activate' );

/**
 * Registers the settings admin page under Settings menu.
 *
 * @since 0.2.0
 * @return void
 */
function onedog_bbca_admin_menu() {
	add_options_page(
		__( 'Custom Admin', 'bb-custom-admin' ),
		__( 'Custom Admin', 'bb-custom-admin' ),
		'manage_options',
		'onedog-bbca-settings',
		'onedog_bbca_render_settings_page'
	);
}
add_action( 'admin_menu', 'onedog_bbca_admin_menu', 25 );

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
	if ( 'settings_page_onedog-bbca-settings' !== $hook_suffix ) {
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
