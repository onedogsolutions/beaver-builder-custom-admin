# State Tracker - Beaver Builder Custom Admin

## Release state

**`main` is at v0.2.0** as of Phase 2, the React/audit remediation release. It was at v0.1.0 (commit `9d2a69a`) immediately before. Phase 2 adds the React settings UI, REST API, transient caching, vanilla JS dashboard panel, and resolves all 14 legitimate Plugin Auditor findings.

## Current Phase: Phase 2 (React settings UI, audit remediation, JS modernization)

### Phase 2 Modifications (v0.2.0)

Plugin Auditor scan reported 39 findings across 9 categories. After triage, 14 were legitimate and 25 were false positives (pattern-matching on code that does not exist in this plugin — `eval()`, `unserialize()`, remote requests, etc.).

**Architecture change — settings UI moved from BB's PHP panel to a standalone React app.**

The v0.1.0 settings form was rendered inside Beaver Builder's admin settings panel via `fl_builder_admin_settings_nav_items` / `fl_builder_admin_settings_render_forms` hooks. This created a hard dependency on `FLBuilderAdminSettings::render_form_action()` and mixed PHP template rendering with BB internals.

v0.2.0 replaces this with:
- A dedicated admin page at **Settings → Custom Admin** (`add_options_page`)
- A React app (`src/settings/`) using `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`
- REST endpoints (`onedog-bbca/v1`) for layout retrieval and settings persistence
- Build via `@wordpress/scripts` (webpack, outputs to `build/`)

BB is still required for layout *rendering* on the dashboard (`do_shortcode`), but no longer for the settings *UI*.

**REST API — `classes/class-onedog-bb-rest.php`.**

| Route | Method | Purpose |
|-------|--------|---------|
| `/onedog-bbca/v1/layouts` | GET | Returns BB layout templates + user roles + BB active status |
| `/onedog-bbca/v1/settings` | GET | Returns current role-to-template mapping |
| `/onedog-bbca/v1/settings` | POST | Saves sanitized role-to-template mapping |

All routes require `manage_options` capability. POST validates input is an array of strings.

**Performance — transient caching for layout queries.**

`get_posts()` for BB templates is now cached in a transient (`onedog_bbca_templates`, 12h TTL). Cache is flushed on `save_post_fl-builder-template` and `deleted_post` (when post type matches). Query args include `suppress_filters => true` and `no_found_rows => true` (audit finding S3/P1).

**Security — audit findings resolved.**

- S6 (unsafe file inclusion): `file_exists()` guard before `include` in `welcome_panel()`.
- S8 (XSS via get_selected): Method removed entirely; React escapes by default.
- S3 (get_posts): `suppress_filters`, `no_found_rows` added; no user input reaches query.

**Standards — inline assets eliminated.**

- ST5/ST16/ST17: Inline `<style>` and `<script>` blocks removed from `welcome-panel.php`. Styles moved to `assets/css/frontend.css`, script to `assets/js/frontend.js`. Both enqueued conditionally on `index.php` only when the panel is active.
- ST9: `@package` added to all DocBlocks.
- ST11: Activation function renamed to `onedog_bbca_activate()`.
- ST13: Resolved by React (no PHP-rendered select attributes).

**Compatibility — jQuery removed.**

- C1: Dashboard panel script is now vanilla ES2020+ (`Element.before()`). No jQuery dependency. Loaded with `defer` strategy.
- C2: `Requires at least: 5.0` and `Tested up to: 6.8` added to plugin header.
- C3: `Requires PHP: 7.4` added to plugin header.

**Privacy & Accessibility.**

- PV1: Privacy section added to readme.txt (no data collection, no cookies, no external requests).
- A1: React `SelectControl` provides accessible labels natively (role name is the label).

**Plugin Review.**

- PR2: Non-affiliation disclaimer added to readme.txt.

**Removed:**
- `includes/admin-settings.php` (replaced by React app)
- `bb_nav_items()`, `bb_nav_forms()`, `save_settings()`, `get_selected()`, `get_bb_templates()` from core class
- All `fl_builder_admin_settings_*` hooks
- jQuery dependency for dashboard panel

**Added:**
- `classes/class-onedog-bb-rest.php` — REST controller
- `src/settings/index.js` — React entry point
- `src/settings/app.js` — SettingsApp component
- `assets/js/frontend.js` — vanilla JS panel repositioning
- `assets/css/frontend.css` — dashboard panel styles
- `webpack.config.js` — custom entry point for wp-scripts
- `package.json` — build tooling

**Target structure (v0.2.0):**

```
beaver-builder-custom-admin/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       └── frontend.js
├── build/
│   ├── settings.js
│   └── settings.asset.php
├── classes/
│   ├── class-onedog-bb-custom-admin.php
│   └── class-onedog-bb-rest.php
├── includes/
│   └── welcome-panel.php
├── src/
│   └── settings/
│       ├── index.js
│       └── app.js
├── beaver-builder-custom-admin.php
├── package.json
├── webpack.config.js
├── readme.txt
├── LICENSE
├── .gitignore
└── STATE.md
```

## Historical Phase: Phase 1 (clone, namespace refactor, and modernization)

### Phase 1 Modifications (v0.1.0)

Forked from [helloideabox/beaver-builder-dashboard-welcome](https://github.com/helloideabox/beaver-builder-dashboard-welcome) (v1.0.0, December 2016). Refactored all identifiers to the OneDog namespace, added security hardening (ABSPATH guards, input sanitization, output escaping), PHP 8.x compatibility fixes, and automatic migration from the legacy plugin on activation.

## Project Conventions

- **Prefix:** all constants use `BBCA_`; all options, nonces, and hooks use `onedog_bbca_` or `onedog-bbca-`.
- **Class naming:** `OneDog_` prefix, PascalCase, file names follow WordPress `class-{lowercase-hyphenated}.php` convention.
- **Text domain:** `bb-custom-admin` for all translatable strings.
- **Author:** Ryan Waterbury, One Dog Solutions — https://onedog.solutions/
- **License:** GPL-2.0, matching upstream and WordPress core.
- **Build:** `@wordpress/scripts` (webpack). `build/` is committed; `node_modules/` is not.
- **JS:** React via WordPress packages for admin UI; vanilla ES2020+ for frontend. No jQuery.
- **REST namespace:** `onedog-bbca/v1`.
