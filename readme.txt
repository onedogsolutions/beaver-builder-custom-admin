=== Beaver Builder Custom Admin ===
Contributors: rwaterbury, onedogsolutions
Tags: beaver builder, dashboard canvas, admin, custom, role editor
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.2
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Replaces the default WordPress dashboard with a full-bleed Beaver Builder canvas for selected user roles.

== Description ==

Beaver Builder Custom Admin gives you full control over the WordPress dashboard using Beaver Builder. Replace the entire dashboard with a full-bleed canvas built with Beaver Builder — headings, contact information, forms, video, images, affiliate links, and more.

Display a different template for each user role.

**How it works:**

1. Create a layout with Beaver Builder.
2. Go to Settings → Custom Admin and select the layout for any user role.
3. Save. Done!

**Legacy plugin:** This plugin replaces "Dashboard Welcome for Beaver Builder" (bb-dashboard-welcome). On activation it automatically deactivates the legacy plugin.

**Disclaimer:** This is an independent plugin and is not affiliated with, endorsed by, or sponsored by Beaver Builder or FastLine Media. "Beaver Builder" is a trademark of FastLine Media, Inc.

== Installation ==

1. Install Beaver Builder Custom Admin either via the WordPress plugin directory or by uploading the files to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. If the legacy "Dashboard Welcome for Beaver Builder" plugin is active, it will be deactivated automatically.
4. Navigate to Settings → Custom Admin to configure your dashboard canvas.

== Requirements ==

* Beaver Builder plugin (active) for layout rendering on the dashboard.
* WordPress 5.0+ (uses the REST API and React-based admin UI).

== Frequently Asked Questions ==

= Do I need coding experience? =

No. All configuration is done through the Settings → Custom Admin page.

= What happens to my old settings? =

On activation, the legacy "Dashboard Welcome for Beaver Builder" plugin is deactivated automatically. Settings from the legacy plugin are not migrated because the welcome-panel feature has been replaced by the Dashboard Canvas.

= Does this work without Beaver Builder? =

The settings page will load, but no layouts will be available. Beaver Builder must be active for layout rendering on the dashboard.

== Privacy ==

This plugin:

* Stores dashboard canvas settings in the WordPress options table.
* Does not collect, transmit, or store any personal data.
* Does not set cookies.
* Does not make external HTTP requests.
* Does not track users or log activity.

All data remains local to your WordPress installation.

== Changelog ==

= 1.3.2 =
* Fixed Dashboard Canvas layout regression after Welcome Screen removal.
* Canvas container is now injected inside #wpbody-content via all_admin_notices, restoring correct alignment and preventing #wpbody collapse.

= 1.3.1 =
* Removed remaining Dashboard Welcome references and dead welcome-panel assets.
* Stopped enqueuing unused frontend.css/frontend.js files.
* Updated readme and settings page copy to describe the Dashboard Canvas.

= 1.3.0 =
* New module: Dashboard Canvas — full-bleed Beaver Builder dashboard replacement.
* Removed the legacy welcome-screen module and its supporting files.
* Added 3rd-party injection squashing and WordPress branding removal options.

= 1.2.0 =
* Removed the Orphaned Option Cleaner module — the feature has been ported to a dedicated standalone plugin.
* Removed the /option-cleaner/* REST endpoints and the "Option Cleaner" settings tab.
* Fixed Menu Restrictor settings UI not populating admin sidebar menus (admin menu is now built on demand in REST context).

= 1.1.0 =
* New module: Orphaned Option Cleaner — detect and remove leftover wp_options entries from uninstalled plugins.
* Ghost capability detection — scan all roles for capabilities left behind by removed plugins and strip them in bulk.
* New REST endpoints: /option-cleaner/scan, /option-cleaner/delete, /option-cleaner/capabilities, /option-cleaner/capabilities/delete.
* New "Option Cleaner" tab in Settings → Custom Admin.

= 1.0.1 =
* Fixed settings page failing to render on WordPress < 6.5 (classic JSX runtime).
* Settings menu registration hardened with priority 25.

= 1.0.0 =
* New module: Role & Capability Editor with rollback support.
* Enhanced Menu Restrictor with direct URL access prevention.
* Settings UI rebuilt with Tailwind CSS v4 and component-based React.
* New Import / Export system for full configuration backup and sync.

= 0.2.0 =
* React-based settings page (Settings → Custom Admin) using WordPress components.
* REST API endpoints for layout retrieval and settings management.
* Transient caching for BB layout queries (12h TTL, auto-flush on template save).
* Modern vanilla JS for dashboard panel (no jQuery dependency).
* All CSS/JS properly enqueued from files (no inline blocks).
* Security: file_exists() guards on includes, suppress_filters on queries.
* Added Requires at least, Tested up to, Requires PHP headers.
* Accessible form controls with proper labels via React SelectControl.
* Removed dependency on FLBuilderAdminSettings for the settings UI.

= 0.1.0 =
* Initial release under the OneDog namespace.
* Refactored from bb-dashboard-welcome 1.0.0.
* Security hardening: input sanitization, output escaping, ABSPATH guards.
* PHP 8.x compatibility fixes.
* Automatic migration from legacy plugin on activation.

== Upgrade Notice ==

= 1.2.0 =
The Option Cleaner has moved to a standalone plugin and is no longer part of this plugin. Also fixes the Menu Restrictor menu list not loading.

= 1.1.0 =
New Option Cleaner module: find and remove ghost options and capabilities left behind by uninstalled plugins.

= 0.2.0 =
Major update: new React settings UI, REST API, performance caching, and full audit remediation. Settings moved from BB panel to Settings → Custom Admin.

= 0.1.0 =
Initial release. Replaces "Dashboard Welcome for Beaver Builder" with automatic settings migration.
