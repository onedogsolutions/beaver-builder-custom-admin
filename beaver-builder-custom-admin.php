<?php
/**
 * Plugin Name: Beaver Builder Custom Admin
 * Description: Replaces the default WordPress dashboard welcome panel with a Beaver Builder template, selectable per user role.
 * Author: Ryan Waterbury
 * Author URI: https://onedog.solutions/
 * Version: 0.1.0
 * License: GNU General Public License v2.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bb-custom-admin
 * Domain Path: /languages
 *
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

define( 'BBCA_VER', '0.1.0' );
define( 'BBCA_DIR', plugin_dir_path( __FILE__ ) );
define( 'BBCA_URL', plugins_url( '/', __FILE__ ) );
define( 'BBCA_PATH', plugin_basename( __FILE__ ) );

/**
 * The legacy plugin this replaces.
 * Used for migration and deactivation on activation.
 */
define( 'BBCA_LEGACY_PLUGIN', 'bb-dashboard-welcome/bb-dashboard-welcome.php' );
define( 'BBCA_LEGACY_OPTION', 'bbpd_template' );

/**
 * Activation hook: migrate legacy settings and deactivate the old plugin.
 *
 * @since 0.1.0
 * @return void
 */
function bbca_activate() {
	// Migrate legacy option if it exists and the new one does not.
	$legacy = get_option( BBCA_LEGACY_OPTION );
	$new    = get_option( 'onedog_bbca_template' );

	if ( false !== $legacy && false === $new ) {
		update_option( 'onedog_bbca_template', $legacy );
		delete_option( BBCA_LEGACY_OPTION );
	}

	// Deactivate the legacy plugin if active.
	if ( is_plugin_active( BBCA_LEGACY_PLUGIN ) ) {
		deactivate_plugins( BBCA_LEGACY_PLUGIN );
	}
}
register_activation_hook( __FILE__, 'bbca_activate' );

require_once BBCA_DIR . 'classes/class-onedog-bb-custom-admin.php';
