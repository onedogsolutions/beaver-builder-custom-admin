# State Tracker - Beaver Builder Custom Admin

## Release state

<<<<<<< HEAD
**`main` is at v1.3.6** as of the settings-page return to the Settings menu. Previous: v1.3.5 (admin canvas styling-asset fix), v1.3.4 (settings-page menu relocation), v1.3.3 (Dashboard Canvas admin-menu overlap fix), v1.3.2 (Dashboard Canvas layout fix), v1.3.1 (Welcome Screen removal + minor version bump), v1.3.0 (Dashboard Canvas & 3rd-Party Squashing), v1.2.0 (Option Cleaner removal + Menu Restrictor fix), v1.1.0 (Option Cleaner), v1.0.1 (settings loading fix), v1.0.0 (Phase 3 - Role Editor, Menu Restrictor, Tailwind CSS), v0.2.0 (Phase 2 - React settings UI), v0.1.0 (Phase 1 - fork and modernization).

## Current Phase: v1.3.6 (Settings Page Returned to the Settings Menu)

### v1.3.6 Modifications

**Requested: put the settings page back under Settings, where it lived until v1.3.4.**

v1.3.4 moved it out to a top-level "Custom Admin" sidebar item because on the target site it could not be reached: `add_options_page()` appends, the page was registered at `admin_menu` priority 25 so it was the last entry in the Settings submenu, and that submenu is taller than the viewport on a site carrying a normal plugin load-out. The flyout ran off the bottom of the screen and the final entries — this one among them — were not hoverable. Reverting the parent alone would reproduce that exactly, so the position within the submenu is part of this change.

**Changes applied:**

1. **Back to a Settings submenu** — `add_options_page()` in place of `add_menu_page()`. The dashicon and the `80.7` position argument go with it; a submenu item has neither.
2. **Registered at `admin_menu` priority 9, not 25** — this is the part that keeps 1.3.4's bug from coming back. Core builds its own menus before `admin_menu` fires, and plugins overwhelmingly register on the default priority 10, so priority 9 lands this page immediately after core's Settings entries and ahead of the plugin block instead of at the end of it. Reachability no longer depends on how many other plugins have claimed the Settings menu, only on the flyout's first screenful — which core's own entries already fit inside.
3. **Redirect reversed** — `onedog_bbca_redirect_legacy_settings_url()` now sends `admin.php?page=onedog-bbca-settings` to `options-general.php?page=onedog-bbca-settings`. It is the mirror of the 1.3.4 redirect, and it matters more than the original did: while the page was top-level, `admin.php` was the URL the settings page itself linked to and the one anyone would have bookmarked, and without the redirect that URL is a "Sorry, you are not allowed to access this page" screen rather than a 404. Any Menu Restrictor rule stored against the top-level slug follows the same path.
4. **Asset enqueue hook suffix** — `settings_page_onedog-bbca-settings` is matched first again, with `toplevel_page_*` kept as the fallback. Both suffixes stay in the list, so the page renders its React app whichever parent it ends up under.

**Trade-off, recorded deliberately:** the failure mode 1.3.4 was written to fix is a property of the Settings menu, not of this plugin, and priority 9 mitigates it rather than removing it. If a future plugin also registers ahead of the default block and pushes this entry down, or if a site's Settings menu grows past a screenful of core entries alone, the flyout can clip it again. The top-level registration is one line away in git history, and the enqueue guard still accepts that hook suffix.

### Verification

`php -l` clean. Traced by hand against core's menu construction: `add_options_page()` at priority 9 appends to `$submenu['options-general.php']` after the core entries registered in `wp-admin/menu.php` (which runs before `admin_menu` fires) and before anything registered at priority 10 or later; the resulting hook suffix is `settings_page_onedog-bbca-settings`, which the enqueue guard matches. The redirect cannot loop — it fires only when `$pagenow` is `admin.php` and targets `options-general.php`, where the guard returns early.

**Not yet verified on the target site.** The one thing worth checking there is position: whether "Custom Admin" lands high enough in the Settings flyout to be hoverable on that install's plugin load-out. That is the whole risk of this release.

### Files Modified (v1.3.6)

- `beaver-builder-custom-admin.php` — `onedog_bbca_admin_menu()` uses `add_options_page()` on `admin_menu` priority 9; `onedog_bbca_redirect_legacy_settings_url()` reversed to send `admin.php` to `options-general.php`; enqueue hook-suffix order and comment; version `1.3.6`.
- `package.json` — Version `1.3.6`.
- `readme.txt` — Stable tag, changelog, upgrade notice. The Description, Installation and FAQ copy already said "Settings → Custom Admin" and is accurate again without edits.
- `STATE.md` — This section.

`build/` is unchanged — this release touches no JavaScript, and no settings-UI copy names the menu location.

### Release

Merged to `main` and packaged for testing: `dist/beaver-builder-custom-admin-1.3.6.zip`.

**Testing this build.** Install the zip, then confirm in order: "Custom Admin" appears in the Settings flyout and sits high enough in it to click without the list scrolling off-screen; it opens the React settings app with all tabs intact; `admin.php?page=onedog-bbca-settings` redirects to `options-general.php?page=onedog-bbca-settings` rather than showing a permissions error; and the settings page still loads its CSS and JS (an unstyled or empty page means the hook-suffix match is wrong).

---

## Historical Phase: v1.3.5 (Admin Canvas Styling Assets)

### v1.3.5 Modifications

**Fixed: Beaver Builder styling was incomplete on the dashboard canvas — most visibly, buttons rendered without their hover state.**

**Root cause — the canvas loaded the layout's stylesheet and nothing else.** Since v1.3.3 `enqueue_assets()` has called `FLBuilder::register_layout_styles_scripts()` and `FLBuilder::enqueue_layout_styles_scripts()`, which between them cover the shared frontend bundle and the layout's own cached CSS/JS. On the front end a layout is styled by more than that: Global Styles (BB 2.5+), the Custom CSS from Global Settings, Google Fonts, Beaver Themer's assets, and the active theme's stylesheet. Every one of those is registered on `wp_enqueue_scripts`, or by the theme — and **`wp_enqueue_scripts` never fires in wp-admin**. Nothing in the module substituted for it.

That explains why the symptom was specific to hover rather than general. A Button module with per-node colours has its `.fl-node-xxxx .fl-button:hover` rule inside the cached layout file, which the canvas already loaded, so it worked. A button inheriting its colours from Global Styles has no per-node hover rule at all — the declaration lives in the global stylesheet, which the admin document never received. Admin CSS was not overriding anything; the rule was simply absent.

**Fixes applied:**

1. **Front-end enqueue replay** — `replay_frontend_enqueue()` walks the registered `wp_enqueue_scripts` callbacks in priority order and invokes the ones Beaver Builder owns, inside the assigned layout's post-ID scope. This is deliberately not a list of hardcoded method names: Beaver Builder is commercial, its internals carry no versioning guarantee, and the Global Styles API in particular has moved between releases. Callbacks are matched by **owning class** (`FLBuilder*`, `FLThemeBuilder*`), so an upstream rename costs the canvas one stylesheet instead of fataling. Closures and plain functions are unattributable and are never invoked — replaying an unidentified front-end callback in wp-admin is exactly the side effect this must not introduce.
2. **`FLBuilder::register_layout_styles_scripts()` / `enqueue_layout_styles_scripts()` are skipped during the replay**, since `enqueue_assets()` calls both directly. Without the skip list their inline CSS would be appended twice.
3. **Global Settings Custom CSS** — `enqueue_global_settings_css()` inlines it on the canvas handle. Some Beaver Builder versions fold it into the layout cache file and some do not; when it is already there this is a duplicate of rules that print later and still win, which is harmless.
4. **Cache-miss render** — `maybe_render_layout_css()` calls `FLBuilder::render_css()` when `get_asset_info()` reports the cache file is absent. Beaver Builder regenerates that file lazily on a front-end render, so on a fresh install, or on the first dashboard hit after someone clears the builder cache, the enqueued stylesheet was a 404 and the canvas rendered unstyled. The call is output-buffered: some versions echo the CSS as well as writing it, and `admin_enqueue_scripts` fires inside `<head>`.
5. **Theme Styles option** (`onedog_bbca_canvas_load_theme_styles`, default off) — enqueues `get_stylesheet_uri()` and replays the theme's own front-end callbacks. On a Beaver Builder Theme site the button colours, hover included, are customizer values in the theme's generated stylesheet, so a layout leaning on them cannot be styled correctly without it. It is opt-in because theme CSS is written for a front-end document: its `body`, `a` and heading rules restyle the admin menu, toolbar and footer too. The settings UI says so, and shows a warning once the toggle is on.
6. **`with_post_id()`** — the `set_post_id()` / `reset_post_id()` dance that v1.3.3 inlined in `enqueue_layout_assets()` is now a helper wrapping the whole block, with the pop in a `finally`. Upstream those calls are a stack, so nesting is safe.
7. **`guard()`** — every call into Beaver Builder internals runs through it. This code executes on the dashboard, the one admin screen every user lands on; an upstream rename must cost the site its canvas styling, never its ability to log in and fix the problem. Failures are logged under `WP_DEBUG` and are otherwise silent.
8. **`?bbca_debug_styles=1`** — administrators-only diagnostic, registered on both `admin_print_footer_scripts` and `wp_footer`, printing the request's stylesheet handles as an HTML comment. Diagnosing this class of bug means diffing the dashboard's head against a front-end render of the same layout; this makes that possible without editing code.

### Verification

Exercised against a stubbed WordPress + Beaver Builder harness covering: layout assigned, cache file missing, theme toggle on, full-bleed on, no layout assigned, layout deleted, and a degraded install with `FLBuilderModel` absent and no front-end callbacks registered. Confirmed in every case — replay order follows hook priority, the post-ID stack is balanced (including when a callback throws), a throwing callback is logged and the remaining sources still load, third-party and closure callbacks are never invoked, the theme pass does not re-run Beaver Builder callbacks, and `render_css()` output never escapes into the document.

**Not yet verified on the target site.** Which of these sources was actually missing is a question only the live install answers — that is what `?bbca_debug_styles=1` is for. If hover is still wrong after this, the diff between the dashboard and a front-end render names the remaining source, and the Theme Styles toggle is the next thing to try.

### Files Modified (v1.3.5)

- `includes/modules/class-dashboard-canvas.php` — `THEME_STYLES_OPTION` and `DEBUG_STYLES_ARG` constants, `$replayed` registry, `enqueue_layout_stack()`, `replay_frontend_enqueue()`, `callback_id()`, `owns_layout_assets()`, `enqueue_global_settings_css()`, `maybe_render_layout_css()`, `enqueue_theme_styles()`, `with_post_id()`, `guard()`, `maybe_debug_styles()`; `enqueue_assets()` and `enqueue_layout_assets()` rewritten.
- `classes/class-onedog-bb-rest.php` — `load_theme_styles` in the canvas GET/POST endpoints and in the export/import payloads.
- `src/components/DashboardCanvas.jsx` — Theme Styles toggle card and its warning, plus the new key in both defaults objects.
- `build/` — rebuilt.
- `beaver-builder-custom-admin.php`, `package.json`, `readme.txt` — version 1.3.5, changelog.
- `PLAN-1.3.5-canvas-styling-assets.md` — the plan this implements.

### Release

Merged to `main` and packaged for testing: `dist/beaver-builder-custom-admin-1.3.5.zip`, 21 files, 64K. Contents spot-checked — runtime files only, with `src/`, `node_modules/`, `bin/`, `STATE.md` and the plan document all correctly excluded by `bin/build-zip.sh`.

**Testing this build.** Install the zip, then before looking at anything else load `/wp-admin/index.php?bbca_debug_styles=1` and a front-end page rendering the same layout with the same argument, and diff the `bbca-debug-styles` HTML comment in each. That diff is the ground truth for whether the fix reached the right stylesheets, and it is worth capturing here even if the buttons now look correct — the root-cause analysis above is inference from the codebase, not from the live install.

Then check, in order: button hover on a button inheriting global colours; typography against the front end; that the admin menu, toolbar and footer are visually unchanged; and that `?bbca_bypass=1` still returns the native dashboard. If hover is still wrong, the missing source will be named in the stylesheet diff, and the Theme Styles toggle is the next thing to try — expect it to restyle the admin chrome when enabled.

## Historical Phase: v1.3.4 (Settings Page Menu Relocation)

### v1.3.4 Modifications

**Fixed: the settings page could not be reached on ott-dev.onedog.solutions.**

**Root cause — the page was the last entry in a flyout taller than the viewport.** `onedog_bbca_admin_menu()` registered the page with `add_options_page()`, which appends to the Settings submenu, on `admin_menu` at priority 25. On a site carrying a normal plugin load-out (the target site adds Connectors, FluentSMTP, HappyFiles, LiteSpeed Cache, WPCodeBox and others to Settings) that submenu is taller than the screen, so the flyout runs off the bottom of the viewport and the entries at its end — this one among them — are not reachable by hover.

Nothing was removing the page. It was registered, and `options-general.php?page=onedog-bbca-settings` loaded it correctly the whole time; only the sidebar affordance was missing. This was reported alongside "we do not have the 1.3.3 version", but the site was already running 1.3.3 — the two symptoms were one problem: the 1.3.3 settings (the new Row Width card) were behind a menu entry that could not be clicked.

**Fixes applied:**

1. **Top-level menu item** — `add_menu_page()` with the `dashicons-admin-customizer` icon at position `80.7`, placing "Custom Admin" directly after Settings in the sidebar. A top-level item does not depend on flyout height or on how many other plugins have claimed the parent menu.
2. **Fractional position** — `80.7` rather than `81`, since WordPress keys `$menu` by position and an integer collision silently overwrites whichever plugin registered second.
3. **Legacy URL redirect** — `onedog_bbca_redirect_legacy_settings_url()` on `admin_init` sends `options-general.php?page=onedog-bbca-settings` to `admin.php?page=onedog-bbca-settings`, so existing bookmarks and any stored Menu Restrictor rules pointing at the old parent keep working.
4. **Asset enqueue hook suffix** — `onedog_bbca_enqueue_settings_assets()` matched the literal `settings_page_onedog-bbca-settings`, which the top-level page no longer produces; it now matches `toplevel_page_*` and keeps the old suffix as a fallback. Without this the page would have rendered an empty React root.

The menu slug is now the `BBCA_MENU_SLUG` constant rather than a string repeated across the registration, the redirect, and the enqueue guard.

**Files changed:**
- `beaver-builder-custom-admin.php` — `BBCA_MENU_SLUG`, `add_menu_page()` in place of `add_options_page()`, legacy redirect, hook-suffix match, version `1.3.4`.
- `package.json` — Version `1.3.4`.
- `readme.txt` — Stable tag, changelog, upgrade notice.
- `STATE.md` — This section.

`build/` is unchanged — this release touches no JavaScript.

**Released to `main`** via `claude/admin-menu-version-access-hhxlc0`. A distributable build is produced with `bin/build-zip.sh`.

**Verification on the live site:** "Custom Admin" appears as a top-level sidebar item below Settings; it opens the React settings app with the Dashboard Canvas tab and its 1.3.3 "Row Width" card; and `options-general.php?page=onedog-bbca-settings` redirects to `admin.php?page=onedog-bbca-settings` rather than erroring.

---

## Historical Phase: v1.3.3 (Dashboard Canvas Admin-Menu Overlap Fix)

### v1.3.3 Modifications

**Fixed the Dashboard Canvas overlapping the WordPress admin menu.**

**Root cause — the full-bleed margin was mis-targeted.** `assets/css/admin-canvas.css` carried `margin: -20px -20px 0 -20px` on `#bbca-custom-dashboard-canvas`, commented as countering "default `#wpbody-content` padding." That padding does not exist. WordPress core sets:

```css
#wpcontent      { margin-left: 160px; padding-left: 20px; }   /* the only horizontal gutter */
#wpbody-content { padding-bottom: 65px; float: left; width: 100%; }  /* no horizontal padding */
```

So the left `-20px` pulled the canvas out of the content column and onto the admin menu strip, and the right `-20px` had no padding to consume and simply extended the canvas 20px past the viewport — putting the whole admin document into horizontal overflow.

**Why that reads as "covering the menu."** Core paints the menu in two pieces with different scroll behaviour: `#adminmenuback` (the dark strip) is `position: fixed; z-index: 1` and never scrolls, while `#adminmenuwrap` (the clickable list) is `position: relative; float: left; z-index: 9990` and is in normal flow. Under horizontal overflow the menu items slide out from under their own background. Beaver Builder compounds it — `.fl-row-bg-overlay .fl-row-content` ships `position: relative; z-index: 1`, which ties `#adminmenuback` and wins on DOM order.

**Why removing the margin in `3244e88` appeared to change nothing.** That commit touched only the CSS file. The stylesheet is cache-busted with `BBCA_VER`, which `8b21b2c` had already set to `1.3.2`, so `admin-canvas.css?ver=1.3.2` kept resolving to the cached copy containing the negative margin — in the browser and in LiteSpeed Cache's optimized CSS.

**Fixes applied:**

1. **Cache-busting** — `enqueue_assets()` now versions the canvas stylesheet with `filemtime()`, so CSS edits bust caches without a version bump.
2. **Correct full-bleed** — negative margins are gone. `body.bbca-canvas-active #wpcontent { padding-left: 0 }` removes the gutter from the column instead of out-denting the canvas. No menu width is assumed anywhere, so this is also correct when the menu is folded (`.folded #wpcontent { margin-left: 36px }`).
3. **Head-time Beaver Builder assets** — `enqueue_assets()` now calls `FLBuilder::enqueue_layout_styles_scripts()` for the assigned layout (wrapped in `FLBuilderModel::set_post_id()` / `reset_post_id()`, all guarded by `method_exists`). Previously the layout stylesheet was first enqueued by `FLBuilderShortcode::insert()` during `render_canvas()` on `all_admin_notices` (`admin-header.php:321`), long after `admin_print_styles` (`:137`), so it was deferred to the footer and the layout's row/column width rules were missing on first paint.
4. **Feature-scoped CSS** — a new `admin_body_class` filter adds `bbca-canvas-active`. All canvas, squash, and branding CSS is scoped to that class rather than `body.index-php`, which is the screen rather than the feature.
5. **Footer clearance restored** — `padding-bottom: 0 !important` on `#wpbody-content` removed the 65px core reserves for `#wpfooter` (`position: absolute; bottom: 0`), which then sat on top of the canvas.
6. **Responsive admin bar** — `min-height` now reads `var( --wp-admin--admin-bar--height, 32px )`, which core defines on `html` and switches to `46px` below 782px, instead of hardcoding 32px.
7. **Overflow guard** — `overflow-x: clip` on the canvas contains any layout wider than the admin column. `clip` rather than `hidden` deliberately: `overflow-x: hidden` forces `overflow-y` to `auto`, which would give the canvas its own scrollbar and break `position: sticky` inside the layout.
8. **New "Full-Bleed Rows" option** — the ~157px gutters on each side of the dashboard were Beaver Builder's own global `.fl-row-fixed-width { max-width: <row_width>px }` (`class-fl-builder.php:1951`), not a bug. The new toggle emits `#bbca-custom-dashboard-canvas .fl-row-fixed-width { max-width: 100% }` as inline style so rows fill the admin column. Defaults to off; the layout is unaffected on the front end.

**Known limitation (not fixed):** Beaver Builder's responsive breakpoints are viewport-width media queries, but the canvas is 160px (or 36px folded) narrower than the viewport, so the layout switches to its medium/mobile treatment about a menu-width late. There is no clean fix short of container queries — set the layout's BB global row width with that offset in mind and test the folded menu.

**Correction to the v1.3.2 record below:** that entry attributes the breakage to the `in_admin_header` hook placement and says the negative margins were "countering the wrong container's padding." The hook change was correct and remains in place, but there was never a right container to counter — `#wpbody-content` has no horizontal padding on any WordPress version. The margins were wrong in both directions from the day they were written.

**Files changed:**
- `assets/css/admin-canvas.css` — Rewritten layout rules: no negative margins, feature-scoped selectors, footer clearance, responsive admin-bar height, overflow guard.
- `assets/css/admin.css` — Deleted. Unreferenced since the Tailwind rebuild in v1.0.0.
- `includes/modules/class-dashboard-canvas.php` — `FULL_BLEED_OPTION` and `BODY_CLASS` constants, `add_body_class()`, `enqueue_layout_assets()`, rewritten `enqueue_assets()`, body-class-scoped squash and branding CSS.
- `classes/class-onedog-bb-rest.php` — `full_bleed_rows` in the `/dashboard-canvas` GET/POST handlers and in export/import.
- `src/components/DashboardCanvas.jsx` — "Row Width" card with the Full-Bleed Rows toggle.
- `beaver-builder-custom-admin.php`, `package.json` — Version `1.3.3`.
- `readme.txt` — Stable tag, changelog, upgrade notice.
- `build/*` — Regenerated.
- `STATE.md` — This section.
=======
**`main` is at v1.4.0** as of the Column Sorting & Filtering module. Previous: v1.3.2 (Dashboard Canvas layout fix), v1.3.1 (Welcome Screen removal + minor version bump), v1.3.0 (Dashboard Canvas & 3rd-Party Squashing), v1.2.0 (Option Cleaner removal + Menu Restrictor fix), v1.1.0 (Option Cleaner), v1.0.1 (settings loading fix), v1.0.0 (Phase 3 - Role Editor, Menu Restrictor, Tailwind CSS), v0.2.0 (Phase 2 - React settings UI), v0.1.0 (Phase 1 - fork and modernization).

## Current Phase: v1.4.0 (Column Sorting & Filtering)

### v1.4.0 Modifications

**New module: Column Sorting & Filtering (`column-sorting`).** Makes all WordPress admin list table columns sortable and adds a smart filtering sidebar. Inspired by Admin Columns Pro sorting and filtering features.

**Sortable columns on all list screens.**

Hooks into `manage_*_sortable_columns` filters to register all detected columns as sortable on post, page, CPT, user, comment, and media list tables. Sort handlers:
- `pre_get_posts` for post-type screens (meta, taxonomy, post field sorting)
- `pre_user_query` for user screens (user field, user meta sorting)
- `comments_clauses` for comment screens (comment field, comment meta sorting)

**Column type detection.** Auto-detects the data type of each column to choose the correct sort strategy: post meta (meta_value/meta_value_num), taxonomy (JOIN on terms tables), user meta, post fields, and native WP_Query orderby values. Unknown columns fall back to meta_value sorting.

**Smart filtering sidebar.** Renders filter dropdowns above list tables via `restrict_manage_posts`, `restrict_manage_users`, and `manage_comments_nav`. Filter values are applied to the underlying queries. Taxonomy columns use WordPress's native `wp_dropdown_categories`. Active filters are highlighted and a "Clear Filters" button appears when any filter is active.

**Addon integrations.** Lightweight adapters in `class-column-sorting-integrations.php` map column types for:
- **WooCommerce**: product price, SKU, stock status, categories, tags; order total, status, date; coupon amount, usage limit, expiry. Product attributes registered as taxonomy columns are also detected.
- **Gravity Forms**: entry fields (ID, date, IP, status); form field columns dynamically discovered from `GFFormsModel::get_forms()`.
- **Pods**: custom field columns dynamically discovered from `pods_api()->load_pods()`.

Each adapter only activates when its respective plugin class is detected.

**Per-screen settings UI.** New `ColumnSorting` tab in Settings → Custom Admin with:
- Screen selector dropdown (all registered post types, users, comments, GF forms)
- Sorting enable/disable toggle per screen
- Filtering enable/disable toggle per screen
- Optional default sort column + direction (ASC/DESC) per screen
- Filter column selection (checkboxes to include/exclude specific columns from dropdowns)

**REST API — New Endpoints.**

| Route | Method | Purpose |
|-------|--------|--------|
| `/onedog-bbca/v1/column-sorting` | GET | Retrieve settings + available screens with columns |
| `/onedog-bbca/v1/column-sorting` | POST | Save per-screen sorting/filtering settings |

**Import/Export.** Column sorting settings are included in the full configuration export and restored on import.
>>>>>>> e5f408e (feat(column-sorting): add column sorting and filtering module)

**New option key:**

| Option | Type | Purpose |
<<<<<<< HEAD
|--------|------|---------|
| `onedog_bbca_canvas_full_bleed_rows` | bool | Override Beaver Builder's fixed row width inside the canvas |

**Released to `main`** via `claude/dashboard-content-offset-menu-hvihlr`. A distributable test build is produced with `bin/build-zip.sh`.

**Deployed to ott-dev.onedog.solutions, canvas checklist still unrun.** The site was confirmed running 1.3.3 during the v1.3.4 investigation, so these changes are live. The "admin menu is covered" report that followed the deploy turned out to be the settings-page access problem fixed in v1.3.4, not a canvas regression — it is not evidence either way about the layout fix below, which was made from static analysis of the plugin, WordPress core (`common.css`, `admin-menu.css`, `admin-header.php`), Beaver Builder's CSS generator, and White Label CMS. Verification checklist, still to run as a target role on `/wp-admin/index.php`:

- `document.documentElement.scrollWidth - document.documentElement.clientWidth === 0`
- Canvas left edge at x=160 unfolded and x=36 folded, via `getBoundingClientRect()`
- Beaver Builder layout stylesheet present in `<head>`, no reflow on load
- Admin footer below the canvas, not overlapping it
- At a 700px viewport the min-height resolves against a 46px bar and the menu auto-folds without overlap
- `?bbca_bypass=1` restores the native dashboard with core gutters intact
- A non-target role has no `bbca-canvas-active` class on `<body>`

Purge LiteSpeed Cache once after deploying — mtime versioning fixes future edits, but the already-optimized combined CSS has to be dropped by hand.

**Also check the settings UI**, since the Full-Bleed Rows toggle is new: Custom Admin → Dashboard Canvas (a top-level sidebar item since v1.3.4; it was Settings → Custom Admin when this was written) should show a "Row Width" card, and toggling it should persist across a reload and survive an export/import round trip.
=======
|--------|------|--------|
| `onedog_bbca_column_sorting` | array | Per-screen sorting/filtering configuration |

**Filter hook:** `onedog_bbca_column_type_map` — allows extending the column type map for future plugin integrations.

**Files added:**
- `includes/modules/class-column-sorting.php` — Core module (discovery, sortable registration, post/user/comment sorting handlers, type detection)
- `includes/modules/class-column-sorting-filters.php` — Filter sidebar rendering and query application
- `includes/modules/class-column-sorting-integrations.php` — WooCommerce, Gravity Forms, and Pods adapters
- `assets/css/column-sorting.css` — Filter dropdown styles
- `src/components/ColumnSorting.jsx` — React settings component

**Files changed:**
- `includes/modules/class-module-loader.php` — Registered `column-sorting` module + metadata
- `classes/class-onedog-bb-rest.php` — Added `/column-sorting` routes, `column_sorting` in import/export
- `src/components/App.jsx` — Added Column Sorting tab
- `beaver-builder-custom-admin.php` — Version bumped to 1.4.0, updated description
- `package.json` — Version bumped to 1.4.0
- `readme.txt` — Stable tag 1.4.0, changelog entries
- `STATE.md` — This section
>>>>>>> e5f408e (feat(column-sorting): add column sorting and filtering module)

---

## Historical Phase: v1.3.2 (Dashboard Canvas Layout Fix)

### v1.3.2 Modifications

**Fixed Dashboard Canvas layout broken after Welcome Screen removal.**

After the legacy Welcome Screen module was removed, the Dashboard Canvas module was the sole renderer for `/wp-admin/index.php`. The canvas was being injected via `in_admin_header`, which on the current WordPress core fires **before** `#wpbody` and `#wpbody-content` are opened. This placed `#bbca-custom-dashboard-canvas` as a direct child of `#wpcontent` instead of `#wpbody-content`, causing:

- `#wpbody` and `#wpbody-content` to collapse to `height: 0` because the native `.wrap` content was hidden by canvas CSS.
- The full-bleed negative margins in `assets/css/admin-canvas.css` to counter the wrong container's padding, resulting in misaligned dashboard content.

**Fix:** Changed the canvas injection hook in `OneDog_BBCA_Dashboard_Canvas::setup_dashboard()` from `in_admin_header` to `all_admin_notices` at priority `10000`. This fires inside `#wpbody-content`, after the notice-squash output buffer ends (when squashing is enabled), so the canvas is rendered in the correct location and is not captured as a notice.

**Files changed:**
- `includes/modules/class-dashboard-canvas.php` — Canvas now hooks to `all_admin_notices` priority `10000` instead of `in_admin_header`.
- `beaver-builder-custom-admin.php` — Version bumped to `1.3.2`.
- `package.json` — Version bumped to `1.3.2`.
- `readme.txt` — Stable tag and changelog updated for v1.3.2.
- `STATE.md` — This section.

**Verified on ott-dev.onedog.solutions:** Canvas is now a child of `#wpbody-content`, `#wpbody`/`#wpbody-content` have visible height, and the dashboard renders in the correct admin content area.

---

## Historical Phase: v1.3.1 (Welcome Screen Removal + Patch)

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
- **Packaging:** `bin/build-zip.sh` produces `dist/beaver-builder-custom-admin-<version>.zip` from the working tree — runtime files only (`build/`, `classes/`, `includes/`, `assets/`, the bootstrap, `readme.txt`, `LICENSE`). Source, tooling, and `STATE.md` are excluded. `*.zip` and `dist/` are gitignored.
- **JS:** React via WordPress packages for admin UI; vanilla ES2020+ for frontend. No jQuery.
- **CSS:** Tailwind CSS v4 utility-first framework.
- **REST namespace:** `onedog-bbca/v1`.
- **Storage:** `WP_Roles` API and `wp_options` only. No custom database tables.
