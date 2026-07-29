# State Tracker - Beaver Builder Custom Admin

## Release state

**`main` is at v0.1.0** (commit `dcf6f5e`) as of Phase 1, the initial refactor from the upstream source. No prior releases exist; this is the first versioned state of the plugin under the OneDog namespace. Committed 2026-07-28.

## Current Phase: Phase 1 (clone, namespace refactor, and modernization)

### Phase 1 Modifications (v0.1.0)

Forked from [helloideabox/beaver-builder-dashboard-welcome](https://github.com/helloideabox/beaver-builder-dashboard-welcome) (v1.0.0, December 2016). The upstream plugin replaces the default WordPress dashboard welcome panel with a Beaver Builder layout, selectable per user role. It has not been updated since initial release and carries several outdated patterns.

**Namespace refactor — all identifiers moved to the OneDog namespace.**

| Old | New |
|-----|-----|
| Plugin name: *Dashboard Welcome for Beaver Builder* | *Beaver Builder Custom Admin* |
| Main file: `bb-dashboard-welcome.php` | `beaver-builder-custom-admin.php` |
| Text domain: `bbpd` | `bb-custom-admin` |
| Constants: `DWBB_VER`, `DWBB_DIR`, `DWBB_URL`, `DWBB_PATH` | `BBCA_VER`, `BBCA_DIR`, `BBCA_URL`, `BBCA_PATH` |
| Class: `BB_Power_Dashboard_Admin` | `OneDog_BB_Custom_Admin` |
| Class file: `classes/class-dw-admin.php` | `classes/class-onedog-bb-custom-admin.php` |
| Option: `bbpd_template` | `onedog_bbca_template` |
| Nonce: `bbpd-settings` / `bbpd-settings-nonce` | `onedog-bbca-settings` / `onedog-bbca-settings-nonce` |
| CSS/HTML prefix: `bbpd-*`, `bb-dashboard-welcome` | `onedog-bbca-*`, `onedog-bbca-panel` |
| Author: *Beaver Addons, Achal Jain / IdeaBox Creations* | *Ryan Waterbury, One Dog Solutions* |

**Security hardening — the 2016 code had no input sanitization or output escaping.**

- Added `defined( 'ABSPATH' ) || exit;` guard to every PHP file. The upstream had none; any file could be loaded directly.
- `save_settings()` now sanitizes each value in the `$_POST` template array with `sanitize_text_field()` before writing to the database. Upstream wrote raw `$_POST` directly to `update_option()`.
- `$_GET['page']` comparison in `load_scripts()` now uses a strict `===` check. Upstream used loose `==`.
- All dynamic output in `admin-settings.php` and `welcome-panel.php` wrapped in `esc_html()`, `esc_attr()`, or `esc_url()` as appropriate. Upstream echoed `$value`, `$template['name']`, and `$template['slug']` unescaped.
- Nonce verification retained but renamed; early return on failure was already present.

**Modernization — outdated patterns corrected.**

- Asset versioning: replaced `rand()` with `BBCA_VER` constant for CSS cache-busting. Upstream used `rand()`, which defeats browser caching entirely and generates a new version string on every page load.
- Replaced `array_shift( $user->roles )` with `array_values( $user->roles )[0]` — `array_shift()` requires a variable reference and modifies the array in place; on a property fetch this triggers a notice in PHP 8.x.
- Short array syntax `[]` throughout, replacing `array()`.
- Added `FLBuilder` class-existence guard before calling `FLBuilder::register_layout_styles_scripts` to prevent a fatal on sites where BB is deactivated but the plugin remains active.
- `welcome-panel.php` shortcode output now passed through `do_shortcode()` with the slug escaped via `esc_attr()`.

**Structure — target layout:**

```
beaver-builder-custom-admin/
├── assets/
│   └── css/
│       └── admin.css
├── classes/
│   └── class-onedog-bb-custom-admin.php
├── includes/
│   ├── admin-settings.php
│   └── welcome-panel.php
├── beaver-builder-custom-admin.php
├── readme.txt
├── LICENSE (GPL-2.0)
├── .gitignore
└── STATE.md
```

**Known limitations carried forward from upstream (not addressed in Phase 1):**

- Static-class architecture retained. This matches Beaver Builder ecosystem conventions and is not a defect, but a future phase could introduce instance-based loading if the plugin grows.
- The plugin depends on `FLBuilderAdminSettings::render_form_action()` for its settings form action URL. If Beaver Builder removes or renames this method in a future release, the settings form will break. A fallback (`admin_url( 'options-general.php?page=fl-builder-settings' )`) should be added if that occurs.
- Only the first role in a multi-role user's role array is considered (`array_values( $user->roles )[0]`). WordPress assigns roles in insertion order, so this is typically the highest-priority role, but it is not guaranteed. Upstream had the same limitation.
- No uninstall routine. The `onedog_bbca_template` option is not cleaned up on plugin deletion. Candidate for a future phase.

**Not changed:** core behaviour is identical to upstream — the plugin still hooks `welcome_panel`, removes `wp_welcome_panel`, and renders a Beaver Builder layout via `do_shortcode('[fl_builder_insert_layout slug="..."]')`. The per-role template selection UI still lives inside Beaver Builder's own settings panel under a custom tab.

## Project Conventions

- **Prefix:** all constants use `BBCA_`; all options, nonces, and hooks use `onedog_bbca_` or `onedog-bbca-`.
- **Class naming:** `OneDog_` prefix, PascalCase, file names follow WordPress `class-{lowercase-hyphenated}.php` convention.
- **Text domain:** `bb-custom-admin` for all translatable strings.
- **Author:** Ryan Waterbury, One Dog Solutions — https://onedog.solutions/
- **License:** GPL-2.0, matching upstream and WordPress core.
- **No build step:** the plugin is plain PHP + one CSS file. No Composer, no npm.
