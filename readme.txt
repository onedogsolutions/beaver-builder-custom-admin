=== Beaver Builder Custom Admin ===
Contributors: rwaterbury, onedogsolutions
Tags: beaver builder, dashboard canvas, admin, custom, role editor
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.6.2
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

= 1.6.2 =
* Dashboard Canvas: the rendered Beaver Builder layout HTML is cached for 15 minutes in a non-autoloaded option and invalidated when the layout is saved, the Beaver Builder cache is cleared, or the layout assignment changes — the render cost ~950ms and 15 queries on every dashboard load before.
* Menu Restrictor: the discovered admin menu tree is cached for an hour in a non-autoloaded option instead of bootstrapping the entire wp-admin menu builder (every plugin's admin_menu callbacks) on each settings request. Cleared when plugins or the theme change and when restrictions are saved or imported.
* Column Sorting: column type discovery for WooCommerce, Gravity Forms, and Pods now runs only when a list screen actually sorts or filters, and the integration maps are cached for an hour in non-autoloaded options. Pods discovery uses a single load_pods() call instead of one query per pod.
* Column Sorting filters: automatic dropdowns only appear for columns with a known backing store, and their value lists are cached for 15 minutes in non-autoloaded options. Unknown columns on the users screen no longer query the postmeta table.
* Caches use non-autoloaded options rather than transients, so they survive hosts whose object-cache drop-in has no persistent backend (an inactive LiteSpeed drop-in makes transients per-request).
* Fixed: sortable columns never registered on custom post type screens — a literal hook name WordPress never fires was being used. Registration is now per screen.
* New filters: onedog_bbca_canvas_cache, onedog_bbca_canvas_cache_ttl, onedog_bbca_menu_cache_ttl.

= 1.6.1 =
* Fixed trailing numbers on menu labels in the Menu Restrictor (e.g. "Plugins 0", "Site Health 0") by stripping WordPress update/notification count spans before removing remaining HTML tags.
* Added built-in supplemental mapping for LiteSpeed Cache so it appears in the "Premium / Custom Menus" section and can be hidden per role.
* Hardened menu removal against plugins that re-add or manipulate admin menus after the admin_menu hook by running a final cleanup pass on admin_head.

= 1.6.0 =
* New "Premium / Custom Menus" section in the Menu Restrictor for plugins whose admin menus are not discovered automatically because they guard admin_menu behind is_admin().
* Built-in mapping for SEOPress: the SEO top-level menu and its submenus (Dashboard, Titles & Metas, XML/HTML Sitemap, Social Networks, Analytics, Instant Indexing, Advanced, Tools) now appear and can be hidden per role.
* Added onedog_bbca_menu_visibility_extra_items filter so developers can register additional supplemental menu items.
* Added manual custom menu item form: specify a label, slug, and optional parent slug to hide arbitrary admin pages per role.
* Supplemental menu restrictions are enforced by the existing remove_menus() and block_direct_access() logic.
* Premium/custom menu settings are included in configuration export/import.

= 1.5.0 =
* 3rd-Party Injection Squashing no longer modifies the admin bar. It removed every top-level node outside a small whitelist, which also caught the two container groups WordPress registers (root-default and top-secondary) — so the whitelisted items were orphaned at render time and the toolbar was gutted rather than trimmed. Squashing now only suppresses interruptions in the admin body, which is what it was meant to do.
* Squashing now applies across wp-admin instead of only the dashboard, where the canvas already hid everything anyway.
* Squashing no longer discards every admin notice. It now removes only notice callbacks defined by other plugins, so WordPress's own messages — settings saved, plugin activated, update failures — still reach the user.
* Squashing no longer depends on Beaver Builder being active or a canvas layout being assigned.
* Added popup/overlay suppression: promotional modals are hidden, removed from the DOM, and any page scroll lock they left behind is released.
* Install, update, plugin, theme and Site Health screens are exempt from squashing, since their notices carry the results of what you just did.
* New filters: onedog_bbca_squash_selectors to add popup selectors for a specific site, and onedog_bbca_squash_exempt_screens to change the exempt screen list.

= 1.4.0 =
* New module: Column Sorting & Filtering — make all WordPress list table columns sortable and add smart filtering dropdowns.
* Post, page, CPT, user, comment, and media list tables now support clickable column header sorting.
* Smart filter sidebar with dropdown selectors for narrowing results by column values.
* Automatic column type detection: post meta, taxonomy, user meta, and post fields.
* WooCommerce integration: sortable product price, SKU, stock status; sortable order total, status.
* Gravity Forms integration: sortable entry fields and form field columns.
* Pods integration: sortable custom field columns.
* Per-screen settings UI in Settings > Custom Admin > Column Sorting tab.
* Column sorting settings included in configuration export/import.
* New REST endpoints: GET/POST /onedog-bbca/v1/column-sorting.

= 1.3.6 =
* The settings page has moved back to Settings → Custom Admin. It was a top-level "Custom Admin" sidebar item in 1.3.4 and 1.3.5.
* It is now registered earlier than most plugins' Settings pages, so it appears near the top of the Settings submenu rather than at the end — that end position, in a flyout taller than the screen, is what made it unreachable before 1.3.4.
* The 1.3.4-1.3.5 admin.php?page=onedog-bbca-settings URL now redirects to options-general.php?page=onedog-bbca-settings, so bookmarks made while the page was top-level keep working.

= 1.3.5 =
* Fixed missing Beaver Builder styling on the dashboard canvas — most visibly, buttons rendering without their hover state. The canvas loaded the layout's own cached stylesheet but nothing else: global styles, Google Fonts and Beaver Themer assets are all registered on wp_enqueue_scripts, which never fires in wp-admin, so a button taking its colours from global styles arrived with no hover rule at all.
* Beaver Builder's front-end asset callbacks are now replayed on the dashboard, scoped to the assigned layout. Callbacks are matched by owning class rather than by hardcoded method name, so a Beaver Builder update that renames an internal costs the canvas that one stylesheet instead of fataling.
* Custom CSS from Beaver Builder's Global Settings is now applied to the canvas.
* The layout's cached stylesheet is regenerated if it is missing, so the dashboard is no longer unstyled on a fresh install or after clearing the builder cache.
* New "Theme Styles" option, off by default: loads the active theme's stylesheet on the dashboard. Needed when button colours come from the Beaver Builder Theme customizer rather than from Beaver Builder's global styles. It also restyles the admin menu, toolbar and footer, which is why it is opt-in.
* All calls into Beaver Builder internals are individually guarded — a failure disables canvas styling and is logged under WP_DEBUG, it never takes the dashboard down.
* New diagnostic: administrators can append ?bbca_debug_styles=1 to the dashboard or to a front-end page to list the stylesheets that page loaded. Comparing the two lists identifies anything still missing.

= 1.3.4 =
* The settings page has moved from Settings → Custom Admin to its own top-level "Custom Admin" item in the admin sidebar. As a Settings submenu it was registered last, so on a site with a normal plugin load-out it sat below the bottom of the viewport in a flyout taller than the screen and could not be reached by hover.
* The old options-general.php?page=onedog-bbca-settings URL now redirects to the new location, so existing bookmarks keep working.

= 1.3.3 =
* Fixed the Dashboard Canvas overlapping the WordPress admin menu. The full-bleed negative margins were sized to cancel a horizontal padding that #wpbody-content does not have — the gutter belongs to #wpcontent — so the canvas hung 20px past the column on both sides and put the admin page into horizontal overflow.
* Full bleed is now achieved by zeroing #wpcontent's padding for the canvas only, which is also correct when the admin menu is folded.
* The canvas stylesheet is now versioned by file modification time, so CSS changes bust browser and page caches without a plugin version bump.
* Beaver Builder layout assets are now enqueued on admin_enqueue_scripts instead of during render, so the layout stylesheet reaches the document head and the dashboard no longer reflows on load.
* New "Full-Bleed Rows" option: let rows fill the admin content column instead of Beaver Builder's global fixed row width.
* Canvas CSS is now scoped to a dedicated body class (bbca-canvas-active) rather than body.index-php.
* Restored the 65px bottom padding that WordPress reserves for the absolutely positioned admin footer.
* The canvas minimum height now reads the admin bar height from core's --wp-admin--admin-bar--height custom property (46px on small screens) instead of hardcoding 32px.
* Removed the unused assets/css/admin.css left over from the pre-Tailwind settings UI.

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

= 1.6.2 =
Admin performance release: the dashboard canvas layout and the Menu Restrictor's menu discovery are now cached, and Column Sorting no longer runs integration discovery on every admin page. No settings change is required.

= 1.6.1 =
Fixes trailing update-count numbers on Menu Restrictor labels, adds LiteSpeed Cache to the hideable menu list, and ensures blocked menus stay hidden when other plugins manipulate the menu late. Recommended for all sites using the Menu Restrictor.

= 1.6.0 =
The Menu Restrictor can now hide admin menus from plugins that were not detected automatically, starting with SEOPress. No action is required unless you want to use the new Premium / Custom Menus section.

= 1.5.0 =
3rd-Party Injection Squashing no longer touches the admin bar, and now applies across wp-admin rather than only the dashboard. It also stops swallowing WordPress's own messages — only notices registered by other plugins are removed. If you had squashing enabled, review it after updating: its scope has changed.

= 1.4.0 =
Adds the Column Sorting & Filtering module: sortable columns and filter dropdowns on post, page, CPT, user, comment, and media list tables, with WooCommerce, Gravity Forms, and Pods integrations. Configure it under Settings → Custom Admin → Column Sorting.

= 1.3.6 =
The settings page returns to Settings → Custom Admin, registered near the top of the Settings submenu. Bookmarks to the 1.3.4-1.3.5 top-level URL redirect.

= 1.3.4 =
The settings page is now a top-level "Custom Admin" item in the admin sidebar instead of a Settings submenu, which was unreachable on sites where the Settings flyout runs past the bottom of the screen. Old URLs redirect.

= 1.3.3 =
Fixes the Dashboard Canvas covering the admin menu, and makes canvas stylesheet updates cache-bust correctly. If you are upgrading from 1.3.x, purge any page/CSS caching plugin once after updating.

= 1.2.0 =
The Option Cleaner has moved to a standalone plugin and is no longer part of this plugin. Also fixes the Menu Restrictor menu list not loading.

= 1.1.0 =
New Option Cleaner module: find and remove ghost options and capabilities left behind by uninstalled plugins.

= 0.2.0 =
Major update: new React settings UI, REST API, performance caching, and full audit remediation. Settings moved from BB panel to Settings → Custom Admin.

= 0.1.0 =
Initial release. Replaces "Dashboard Welcome for Beaver Builder" with automatic settings migration.
