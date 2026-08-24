# State Tracker - Beaver Builder Custom Admin

## Release state

**`main` is at v1.3.1** as of the Welcome Screen removal + minor version bump. Previous: v1.3.0 (Dashboard Canvas & 3rd-Party Squashing), v1.2.0 (Option Cleaner removal + Menu Restrictor fix), v1.1.0 (Option Cleaner), v1.0.1 (settings loading fix), v1.0.0 (Phase 3 - Role Editor, Menu Restrictor, Tailwind CSS), v0.2.0 (Phase 2 - React settings UI), v0.1.0 (Phase 1 - fork and modernization).

## Current Phase: v1.3.1 (Welcome Screen Removal + Patch)

### v1.3.0 Modifications

**New module: Dashboard Canvas (`dashboard-canvas`).** Transforms the plugin from a `welcome_panel` overlay into a full-bleed dashboard canvas replacement on `/wp-admin/index.php`. Simultaneously achieves feature parity with `white-label-cms` by suppressing third-party plugin notices, unauthorized dashboard widgets, toolbar links, and side-menu items for targeted user roles.

**Architecture — Full dashboard body replacement.**

The canvas module hooks into `current_screen` and, when active for the current user on the dashboard:
1. Removes the native WordPress welcome panel (`remove_action('welcome_panel', 'wp_welcome_panel')`)
2. Wipes all dashboard meta boxes (widgets) via `wp_dashboard_setup` at priority `9999`
3. Injects a full-bleed Beaver Builder layout container via `in_admin_header`
4. Enqueues BB layout CSS/JS and canvas-specific full-bleed styles

**3rd-Party Injection Squashing.**

When the squash toggle is enabled:
- **Notice suppression:** Output-buffers and discards all content between `admin_notices` (priority 1) and `all_admin_notices` (priority 9999)
- **Toolbar sanitization:** Whitelist-based removal of top-level admin bar nodes (`wp-logo`, `site-name`, `my-account`, `logout`, `fl-builder-frontend` preserved) at priority `9999`
- **CSS safety net:** Hides `.notice`, `.update-nag`, `.error`, `.updated` via inline styles

**WordPress Branding Removal.**

Optional toggle to strip WP logos, update naggers, and footer credits for target roles on the dashboard.

**Safety Rules & Role Verification.**

- Emergency bypass: `?bbca_bypass=1` for administrators (`manage_options`)
- Dependency check: canvas auto-disables when Beaver Builder is deactivated
- Role-based targeting: only configured roles see the canvas
- Layout existence check: gracefully skips if the assigned layout post is deleted
- Welcome-screen module auto-skips when canvas is active (prevents duplicate rendering)

**New option keys:**

| Option | Type | Purpose |
|--------|------|--------|
| `onedog_bbca_canvas_layout_id` | int | Post ID of the selected BB layout |
| `onedog_bbca_canvas_target_roles` | array | Roles subject to canvas + squashing |
| `onedog_bbca_canvas_enable_squash` | bool | Master toggle for 3rd-party injection suppression |
| `onedog_bbca_canvas_hide_wp_branding` | bool | Toggle to strip WP logos and footer credits |

**REST API — New Endpoints.**

| Route | Method | Purpose |
|-------|--------|--------|
| `/onedog-bbca/v1/dashboard-canvas` | GET | Retrieve canvas settings |
| `/onedog-bbca/v1/dashboard-canvas` | POST | Save canvas settings |

**Existing endpoint changes:**

- `/onedog-bbca/v1/layouts` now includes `id` (post ID) alongside `slug` and `name`
- `/onedog-bbca/v1/export` includes canvas settings (`canvas_layout_id`, `canvas_target_roles`, `canvas_enable_squash`, `canvas_hide_wp_branding`)
- `/onedog-bbca/v1/import` restores canvas settings from exported config

**Files added:**
- `includes/modules/class-dashboard-canvas.php` — Canvas module (dashboard replacement, squashing, branding removal, safety checks)
- `assets/css/admin-canvas.css` — Full-bleed canvas styling
- `src/components/DashboardCanvas.jsx` — React settings component (layout selector, target roles, squash/branding toggles)

**Files changed:**
- `includes/modules/class-module-loader.php` — Registered `dashboard-canvas` module + metadata
- `includes/modules/class-welcome-screen.php` — Conditional skip when canvas is active for user
- `classes/class-onedog-bb-rest.php` — Added `/dashboard-canvas` routes, `id` field in layouts, canvas in import/export
- `src/components/App.jsx` — Added Dashboard Canvas tab
- `beaver-builder-custom-admin.php` — Version bumped to 1.3.0, updated description
- `package.json` — Version bumped to 1.3.0
- `STATE.md` — This section

**Welcome Screen module removed.** The legacy `welcome-screen` module (per-role BB welcome panel overlay via `welcome_panel` action) and its supporting files have been removed, fully superseded by the Dashboard Canvas module:

**Files deleted:**
- `includes/modules/class-welcome-screen.php` — Welcome screen module class
- `includes/welcome-panel.php` — Panel template (BB shortcode renderer)
- `src/components/WelcomeScreen.jsx` — React settings component (per-role template assignment UI)

**Code removed:**
- `OneDog_BBCA_Module_Loader` registry entry and metadata for `welcome-screen`
- `GET /onedog-bbca/v1/settings` and `POST /onedog-bbca/v1/settings` REST endpoints (template read/write)
- `WelcomeScreen` import and `welcome` tab from `App.jsx`
- The `onedog_bbca_template` option key is no longer managed, exported, or imported by any module.

---

**Option Cleaner removed.** The Orphaned Option Cleaner module (`option-cleaner`) was ported to a dedicated standalone plugin and fully removed from this plugin: module class, loader registration, REST endpoints, and React tab.

**Menu Restrictor fixed.** `OneDog_BBCA_Menu_Visibility::get_available_menus()` returned an empty list in REST context because the `$menu`/`$submenu` globals are only built during wp-admin page loads. The method now builds the admin menu on demand (`wp-admin/includes/admin.php` + `wp-admin/menu.php`, with `global $menu, $submenu` declared before the includes) so plugin-registered items appear in the settings UI. Verified live on the test site: 15 top-level menus returned, role-based hiding and direct-URL blocking confirmed end-to-end.

**Files changed:**
- `includes/modules/class-option-cleaner.php` — Deleted
- `src/components/OptionCleaner.jsx` — Deleted
- `includes/modules/class-module-loader.php` — Removed `option-cleaner` registry entry + metadata
- `classes/class-onedog-bb-rest.php` — Removed the four `/option-cleaner/*` routes and handlers
- `includes/modules/class-menu-visibility.php` — On-demand admin menu bootstrap in `get_available_menus()`
- `src/components/App.jsx` — Removed Option Cleaner tab
- `beaver-builder-custom-admin.php` — Version bumped to 1.2.0
- `package.json` — Version bumped to 1.2.0
- `readme.txt` — Stable tag 1.2.0, changelog + upgrade notice entries
- `build/*` — Regenerated

---

## Historical Phase: v1.1.0 (Orphaned Option Cleaner)

### v1.1.0 Modifications

New module: Orphaned Option Cleaner (`option-cleaner`). Detects and removes leftover `wp_options` entries **and** ghost role capabilities from plugins that were deleted without cleaning up after themselves (e.g. WP Amelia, Rank Math).

**Detection logic.**

- Options auto mode: scans all option names, groups them by prefix (first underscore segment, or first two segments when the first token is under 4 chars), then excludes groups owned by installed plugins (derived from `get_plugins()` directory slugs, basenames, and TextDomain headers), WordPress core (static safelist + table-prefix options), and this plugin (`onedog_bbca`). Singleton groups are also excluded.
- Options manual mode: optional prefix input for targeted scans (e.g. `rank_math_`).
- Capabilities mode: iterates all roles, collects non-core capabilities (checked against the full WordPress core capability map), groups by prefix, and excludes prefixes owned by installed plugins. Results show affected roles and sample capability slugs.

**Deletion safety.**

- All queries use `$wpdb->prepare()` with `esc_like()`.
- Prefixes sanitized via `sanitize_key()`.
- Deletion requires explicit two-step confirmation in the UI.
- Matching transients (`_transient_{prefix}%`, `_transient_timeout_{prefix}%`) are removed alongside options.

**REST API — New Endpoints.**

| Route | Method | Purpose |
|-------|--------|---------|
| `/onedog-bbca/v1/option-cleaner/scan` | GET | Scan for orphaned option groups (optional `?prefix=` param) |
| `/onedog-bbca/v1/option-cleaner/delete` | POST | Delete options matching selected prefixes |
| `/onedog-bbca/v1/option-cleaner/capabilities` | GET | Scan all roles for ghost capabilities |
| `/onedog-bbca/v1/option-cleaner/capabilities/delete` | POST | Strip selected capability prefixes from all roles |

All routes require `manage_options` capability and guard with `class_exists( 'OneDog_BBCA_Option_Cleaner' )`.

**Files changed:**
- `includes/modules/class-option-cleaner.php` — New module class (option scan/delete, capability scan/strip, prefix grouping, core safelists)
- `includes/modules/class-module-loader.php` — Registered `option-cleaner` module + metadata
- `classes/class-onedog-bb-rest.php` — Added scan/delete routes and handlers
- `src/components/OptionCleaner.jsx` — New React tab component
- `src/components/App.jsx` — Added Option Cleaner tab
- `beaver-builder-custom-admin.php` — Version bumped to 1.1.0
- `package.json` — Version bumped to 1.1.0
- `readme.txt` — Stable tag 1.1.0, changelog entries for 1.0.0–1.1.0
- `build/*` — Regenerated

---

## Historical Phase: Patch v1.0.1 (Settings Loading Fix)

### v1.0.1 Modifications

Fixed settings page failing to render on WordPress < 6.5 and hardened Settings menu registration.

**Bug fix — JSX runtime dependency.**

The `@wordpress/babel-preset-default` hardcodes `@babel/plugin-transform-react-jsx` with `runtime: 'automatic'`, producing a `react-jsx-runtime` script dependency in `build/index.asset.php`. This WordPress script handle only exists in WP 6.5+. On older installs, `window.ReactJSXRuntime` is undefined and the React app throws immediately.

Fix: `webpack.config.js` now overrides the babel-loader rule to use `@babel/preset-react` with `{ runtime: 'classic' }`. The build output uses `wp.element.createElement` (available since WP 5.0) and the asset dependencies are reduced to `wp-api-fetch`, `wp-element`, `wp-i18n`.

**Hardening — Settings menu priority.**

The `admin_menu` hook priority was raised from default (10) to 25, ensuring the Settings > Custom Admin page is registered independently even if Beaver Builder or other plugins manipulate menus at default priority.

**Files changed:**
- `webpack.config.js` — Classic JSX runtime override
- `beaver-builder-custom-admin.php` — `admin_menu` priority 25
- `build/*` — Regenerated

---

## Historical Phase: Phase 3 (Role Editor, Menu Restrictor, Tailwind CSS)

### Phase 3 Modifications (v1.0.0)

Major feature expansion adding Role & Capability Management, enhanced Menu Restrictor with URL access prevention, and complete frontend rebuild with Tailwind CSS v4.

**Architecture change — Tailwind CSS v4 + component-based React.**

The settings UI has been completely rebuilt using Tailwind CSS v4 (matching `onedogsolutions/google-security-for-wordpress` patterns) with a modular component architecture.

- **Build tooling:** `@wordpress/scripts` + `@tailwindcss/postcss` v4 + PostCSS + autoprefixer
- **Entry point:** `src/index.js` → `build/index.js` + `build/index.css`
- **Styling:** Tailwind utility classes, custom animations (`fadeIn`, `slideIn`)
- **Data passing:** `wp_localize_script()` → `window.bbcaSettings` (nonce, restUrl, version)

**New Module: Role & Capability Editor (`role-editor`).**

Full WordPress role management with rollback support:

| Feature | Description |
|---------|-------------|
| Role Selector | Dropdown listing all active user roles |
| Add Role Modal | Create custom roles (blank or clone from existing) |
| Rename Role Modal | Edit display labels for custom roles |
| Delete Role Modal | Delete custom roles (prevents deletion if users assigned) |
| Clear All Capabilities | Strip all granted capabilities for active role |
| Rollback / Reset | Restore core roles to default WordPress capability snapshot |
| Capability Tree | Dynamic categorization (General, Posts, Pages, Themes, Plugins, Users, Deprecated, CPT/Plugin groups) |
| Search | Real-time client-side capability slug/label filter |
| Human Readable Toggle | `edit_others_posts` → "Edit Others Posts" |
| Granted Only Toggle | Filter view to checked capabilities |

**Rollback System:** On initial plugin setup or first modification of a core WP role (`administrator`, `editor`, `author`, `contributor`, `subscriber`), a snapshot of default capabilities is saved to `wp_options` (`onedog_bbca_role_snapshots`) as a recovery baseline.

**Enhanced Module: Menu Restrictor.**

- **Direct URL Access Prevention:** Hook into `admin_init` at priority 9999. If a user attempts to manually navigate to a restricted admin page URL, block access with `wp_die( __( 'You are not allowed to access this page.' ) )`.
- **Filter options:** All Items / Blocked Only / Visible Only

**New: Import / Export System.**

Download full capability configurations and menu restrictions as a `.json` file, and import JSON to sync environments across client sites.

Export includes:
- All role capabilities
- Menu visibility rules
- Toolbar visibility rules
- Module settings
- Welcome screen template assignments
- Notice cleaner settings

**REST API — New Endpoints.**

| Route | Method | Purpose |
|-------|--------|---------|
| `/onedog-bbca/v1/roles` | GET | List all roles |
| `/onedog-bbca/v1/roles` | POST | Create new role |
| `/onedog-bbca/v1/roles/{role}` | GET | Get role capabilities |
| `/onedog-bbca/v1/roles/{role}` | POST | Save role capabilities |
| `/onedog-bbca/v1/roles/{role}` | DELETE | Delete role |
| `/onedog-bbca/v1/roles/{role}/clear` | POST | Clear all capabilities |
| `/onedog-bbca/v1/roles/{role}/rollback` | POST | Reset to defaults |
| `/onedog-bbca/v1/roles/{role}/rename` | POST | Rename role |
| `/onedog-bbca/v1/export` | GET | Export configuration |
| `/onedog-bbca/v1/import` | POST | Import configuration |

All routes require `manage_options` capability.

**Removed:**
- `src/settings/` directory (replaced by `src/components/`)
- `@wordpress/components` dependency (replaced by Tailwind-styled native elements)
- `assets/css/admin.css` (replaced by compiled Tailwind CSS)

**Added:**
- `src/index.js` — New entry point
- `src/styles/index.css` — Tailwind CSS v4 import
- `src/components/App.jsx` — Main app with tab navigation
- `src/components/RoleEditor.jsx` — Role & capability management
- `src/components/MenuRestrictor.jsx` — Menu/toolbar visibility
- `src/components/WelcomeScreen.jsx` — BB template assignment
- `src/components/ModuleSettings.jsx` — Module toggles
- `src/components/ImportExport.jsx` — JSON import/export
- `includes/modules/class-role-editor.php` — Role editor PHP controller
- `postcss.config.js` — PostCSS with Tailwind v4 plugin

**Target structure (v1.0.0):**

```
beaver-builder-custom-admin/
├── assets/
│   ├── css/
│   │   └── frontend.css
│   └── js/
│       └── frontend.js
├── build/
│   ├── index.js
│   ├── index.css
│   ├── index-rtl.css
│   └── index.asset.php
├── classes/
│   └── class-onedog-bb-rest.php
├── includes/
│   ├── modules/
│   │   ├── class-module-loader.php
│   │   ├── class-role-editor.php
│   │   ├── class-menu-visibility.php
│   │   ├── class-welcome-screen.php
│   │   └── class-notice-cleaner.php
│   └── welcome-panel.php
├── src/
│   ├── index.js
│   ├── styles/
│   │   └── index.css
│   └── components/
│       ├── App.jsx
│       ├── RoleEditor.jsx
│       ├── MenuRestrictor.jsx
│       ├── WelcomeScreen.jsx
│       ├── ModuleSettings.jsx
│       └── ImportExport.jsx
├── beaver-builder-custom-admin.php
├── package.json
├── postcss.config.js
├── webpack.config.js
├── readme.txt
├── LICENSE
├── .gitignore
└── STATE.md
```

## Historical Phase: Phase 2 (React settings UI, audit remediation)

### Phase 2 Modifications (v0.2.0)

Plugin Auditor scan reported 39 findings across 9 categories. After triage, 14 were legitimate and 25 were false positives.

**Architecture change — settings UI moved from BB's PHP panel to a standalone React app.**

v0.2.0 replaced the BB-dependent settings form with:
- A dedicated admin page at **Settings → Custom Admin** (`add_options_page`)
- A React app using `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`
- REST endpoints (`onedog-bbca/v1`) for layout retrieval and settings persistence
- Build via `@wordpress/scripts` (webpack, outputs to `build/`)

**Module system added (v0.3.0):**
- `includes/modules/class-module-loader.php` — Module registration and conditional loading
- `includes/modules/class-welcome-screen.php` — Dashboard welcome templates
- `includes/modules/class-menu-visibility.php` — Admin menu/toolbar visibility
- `includes/modules/class-notice-cleaner.php` — Admin notice cleanup

## Historical Phase: Phase 1 (clone, namespace refactor, and modernization)

### Phase 1 Modifications (v0.1.0)

Forked from [helloideabox/beaver-builder-dashboard-welcome](https://github.com/helloideabox/beaver-builder-dashboard-welcome) (v1.0.0, December 2016). Refactored all identifiers to the OneDog namespace, added security hardening (ABSPATH guards, input sanitization, output escaping), PHP 8.x compatibility fixes, and automatic migration from the legacy plugin on activation.

## Project Conventions

- **Prefix:** all constants use `BBCA_`; all options, nonces, and hooks use `onedog_bbca_` or `onedog-bbca-`.
- **Class naming:** `OneDog_` prefix, PascalCase, file names follow WordPress `class-{lowercase-hyphenated}.php` convention.
- **Text domain:** `bb-custom-admin` for all translatable strings.
- **Author:** Ryan Waterbury, One Dog Solutions — https://onedog.solutions/
- **License:** GPL-2.0, matching upstream and WordPress core.
- **Build:** `@wordpress/scripts` + Tailwind CSS v4. `build/` is committed; `node_modules/` is not.
- **JS:** React via WordPress packages for admin UI; vanilla ES2020+ for frontend. No jQuery.
- **CSS:** Tailwind CSS v4 utility-first framework.
- **REST namespace:** `onedog-bbca/v1`.
- **Storage:** `WP_Roles` API and `wp_options` only. No custom database tables.
