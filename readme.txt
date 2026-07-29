=== Beaver Builder Custom Admin ===
Contributors: rwaterbury, onedogsolutions
Tags: beaver builder, dashboard, welcome panel, admin, custom
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Replaces the default WordPress dashboard welcome panel with a custom Beaver Builder template, selectable per user role.

== Description ==

Beaver Builder Custom Admin gives you full control over the WordPress dashboard welcome panel using Beaver Builder. Personalize the dashboard with content and design built with Beaver Builder — headings, contact information, forms, video, images, affiliate links, and more.

Display a different template for each user role.

**How it works:**

1. Create a layout with Beaver Builder.
2. Go to Beaver Builder Settings → Custom Admin and select the layout for any user role.
3. Save. Done!

**Migration:** This plugin replaces "Dashboard Welcome for Beaver Builder" (bb-dashboard-welcome). On activation it automatically migrates your existing settings and deactivates the legacy plugin.

== Installation ==

1. Install Beaver Builder Custom Admin either via the WordPress plugin directory or by uploading the files to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. If the legacy "Dashboard Welcome for Beaver Builder" plugin is active, it will be deactivated automatically and its settings migrated.

== Requirements ==

* Beaver Builder plugin (active) for layout rendering and the settings panel.

== Frequently Asked Questions ==

= Do I need coding experience? =

No. All configuration is done through the Beaver Builder settings panel.

= What happens to my old settings? =

On activation, settings from "Dashboard Welcome for Beaver Builder" are migrated automatically. The old plugin is deactivated.

== Changelog ==

= 0.1.0 =
* Initial release under the OneDog namespace.
* Refactored from bb-dashboard-welcome 1.0.0.
* Security hardening: input sanitization, output escaping, ABSPATH guards.
* PHP 8.x compatibility fixes.
* Automatic migration from legacy plugin on activation.

== Upgrade Notice ==

= 0.1.0 =
Initial release. Replaces "Dashboard Welcome for Beaver Builder" with automatic settings migration.
