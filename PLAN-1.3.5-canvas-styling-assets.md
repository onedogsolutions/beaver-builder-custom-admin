# Plan — v1.3.5: Load Beaver Builder Styling Assets in the Admin Canvas

**Status:** implemented in v1.3.5 — see `STATE.md`. Steps 1–8 shipped; Step 0 (the on-site stylesheet
diff) is now available as `?bbca_debug_styles=1` and still wants running on the target site to confirm which
source was actually missing. Step 7's speculative admin-CSS overrides were deliberately **not** written: the
hover rule turned out to be absent rather than overridden, so there was nothing to out-specify.
**Scope:** `OneDog_BBCA_Dashboard_Canvas` asset loading, `assets/css/admin-canvas.css`, one new opt-in setting
**Symptom that prompted it:** button hover CSS (and other layout styling) does not load on the dashboard canvas in wp-admin.

---

## 1. Why the styling is missing

`OneDog_BBCA_Dashboard_Canvas::enqueue_assets()` (`includes/modules/class-dashboard-canvas.php:196`) currently loads exactly three things:

1. `assets/css/admin-canvas.css` — our own container/reset CSS.
2. `FLBuilder::register_layout_styles_scripts()` — Beaver Builder's shared frontend bundle.
3. `FLBuilder::enqueue_layout_styles_scripts( $layout_id )` — the assigned layout's own cached CSS/JS
   (added in v1.3.3, wrapped in `FLBuilderModel::set_post_id()` / `reset_post_id()`).

On the front end a Beaver Builder layout is styled by more than that. The additional sources all hang off
`wp_enqueue_scripts` or off the active theme — **and `wp_enqueue_scripts` never fires in wp-admin**. Nothing
in the canvas module substitutes for it, so each of these is simply absent on `index.php`:

| Missing source | What it supplies | Why it is absent in admin |
| --- | --- | --- |
| **Global Styles** (BB 2.5+, `FLBuilderGlobalStyles`) | Global colour/typography tokens and the global **button** style — including its **hover** state | Enqueued on `wp_enqueue_scripts` |
| **Global Settings custom CSS** (Tools → Global Settings) | Site-wide overrides authored by the builder | Front-end render path only |
| **Google Fonts** (`FLBuilderFonts`) | The layout's typefaces | Enqueued on `wp_enqueue_scripts`, priority 9999 |
| **Active theme CSS** — on a Beaver Builder Theme site, `fl-automator` / customizer cache | Base typography, link colours, and `.fl-button` / `.fl-button:hover` brand colours | Themes only enqueue on the front end |
| — | — | — |
| **wp-admin's own CSS** (`common.css`, `forms.css`, `colors-*.css`) | *actively competes*: `a:hover` colour, `.button` resets, `input:focus` box-shadow | Always loaded in admin |

The hover symptom is the clearest tell. A Button module's per-node CSS (`.fl-node-xxxx .fl-button:hover {…}`,
specificity 0-2-1) lives in the cached layout file we already enqueue, so it should survive. A button that
inherits its colours from **Global Styles** or from the **theme** has no per-node hover rule at all — the hover
declaration lives in a stylesheet that never reaches the admin document. That is consistent with "button hover
CSS, etc. doesn't load".

Beaver Builder is a commercial plugin and is not vendored in this repo, so the exact class and method names
below cannot be verified from the tree. **Every call must stay behind `class_exists()` / `method_exists()`
guards**, matching the defensive style already used in `enqueue_layout_assets()`.

---

## 2. Step 0 — Diagnose on the target site before writing code (do this first)

This converts the whole feature from inference into a checklist, and it takes about half an hour.

1. On the target site, open a front-end page that renders the same layout (or the layout's BB preview URL) and
   record every stylesheet in `<head>`: each `<link rel="stylesheet">` `id` and each inline `<style>` `id`.
2. Open `/wp-admin/index.php` with the canvas active and record the same list.
3. **The diff is the work list.** Expect to see `fl-builder-global-styles`, a Google Fonts link, the theme's
   `style.css` / `fl-automator`, and possibly a global-settings inline style present on the front end and
   absent in admin.
4. In DevTools, inspect a button that has the wrong hover and note which stylesheet *would* have supplied the
   hover rule, and whether any admin rule is winning a specificity tie.

Temporary one-liner for step 2 (remove before committing):

```php
add_action( 'admin_print_footer_scripts', function () {
    error_log( 'BBCA styles: ' . implode( ', ', wp_styles()->queue ) );
} );
```

Record the diff in `STATE.md` under the v1.3.5 section — it is the evidence for every step below and the
reference for the next person who touches this.

---

## 3. Implementation

### Step 1 — Restructure `enqueue_assets()`

Split the Beaver Builder half of `enqueue_assets()` into named private helpers so each source is guarded and
testable on its own:

```
enqueue_assets()                 // orchestrator: our CSS + inline full-bleed rule, then BB
├── enqueue_global_styles()      // Step 2
├── enqueue_global_settings_css()// Step 3
├── enqueue_layout_fonts( $id )  // Step 4
├── enqueue_layout_assets( $id ) // exists; add the cache-miss guard from Step 6
└── enqueue_theme_styles()       // Step 5, opt-in
```

Keep the ordering: our `onedog-bbca-canvas` handle is registered first so the later helpers can attach inline
CSS to it, and BB's own handles print after it.

### Step 2 — Global Styles (the primary fix)

```php
private static function enqueue_global_styles() {
    if ( ! class_exists( 'FLBuilderGlobalStyles' ) ) {
        return;
    }

    // Preferred: BB's own enqueue path, which registers the cached file.
    if ( method_exists( 'FLBuilderGlobalStyles', 'enqueue_styles' ) ) {
        FLBuilderGlobalStyles::enqueue_styles();
        return;
    }

    // Fallback: capture the rendered CSS and inline it on our handle.
    if ( method_exists( 'FLBuilderGlobalStyles', 'render_css' ) ) {
        ob_start();
        FLBuilderGlobalStyles::render_css();
        $css = trim( ob_get_clean() );

        if ( '' !== $css ) {
            wp_add_inline_style( 'onedog-bbca-canvas', $css );
        }
    }
}
```

Confirm the real method name against the site's `wp-content/plugins/bb-plugin/classes/class-fl-builder-global-styles.php`
during Step 0; the guards make a wrong guess a no-op rather than a fatal.

**Note:** Global Styles emit `:root`-scoped custom properties. Those are document-wide by design and will also
be visible to the admin chrome. That is harmless (custom properties do not apply themselves), but if BB also
emits bare-element rules there, fold them into the Step 7 scoping review.

### Step 3 — Global Settings custom CSS

```php
$settings = FLBuilderModel::get_global_settings();
if ( ! empty( $settings->css ) ) {
    wp_add_inline_style( 'onedog-bbca-canvas', $settings->css );
}
```

Guarded by `class_exists( 'FLBuilderModel' ) && method_exists( 'FLBuilderModel', 'get_global_settings' )`.
**Skip this step entirely if Step 0 shows the global CSS is already baked into the cached layout file** — BB
concatenates it there in some versions, and inlining it twice is pure duplication.

### Step 4 — Google Fonts

Font collection walks the layout's nodes, so it needs the same post-ID scoping as `enqueue_layout_assets()`.
Factor that `set_post_id()` / `reset_post_id()` wrapper out into a small `with_post_id( $id, callable )` helper
and reuse it, rather than repeating it:

```php
FLBuilderFonts::enqueue_google_fonts();   // guarded; inside the post-ID scope
```

Lower priority than Steps 2–3 — wrong typeface is cosmetic, missing hover is a broken affordance — but it is a
few lines once the scoping helper exists.

### Step 5 — Theme stylesheet (opt-in, off by default)

On a Beaver Builder Theme site the button colours *are* theme customizer values, so hover cannot be fully fixed
without theme CSS. But the active theme's stylesheet sets `body`, `a`, and heading styles globally, and in
wp-admin that leaks onto the admin menu, toolbar, and footer.

**Recommendation:** add a new setting, default **off**, that enqueues `get_stylesheet_uri()` (plus BB Theme's
generated customizer stylesheet when `class_exists( 'FLTheme' )`), with the settings-screen copy stating plainly
that it can restyle admin chrome and should be enabled only if the layout depends on theme styling.

Rejected alternative: fetch the theme stylesheet server-side and prefix every selector with
`#bbca-custom-dashboard-canvas`. It is the only leak-free option, but it needs a real CSS parser (`@media`,
`@supports`, `@font-face`, `@keyframes`, `:root`) plus a cache file in uploads — far too much for this item.
Document it here as the escalation path if the opt-in toggle proves too blunt in practice.

**Ship Steps 1–4 and 7 first and re-test.** If the site's buttons draw their colours from Global Styles rather
than the theme — the common case on BB 2.5+ — Step 5 may not be needed at all, and it carries all of the risk
in this plan.

### Step 6 — Cache-miss guard

`FLBuilder::enqueue_layout_styles_scripts()` enqueues a cached file. On a fresh site, or right after BB's cache
is cleared, that file may not exist yet, and nothing on an admin request regenerates it — the dashboard renders
unstyled until someone visits the front end. Add: if `FLBuilderModel::get_asset_info()` reports the CSS file is
missing, call `FLBuilder::render_css()` for the layout inside the post-ID scope before enqueuing. Guarded, and
inside the existing `$layout_id && get_post( $layout_id )` check.

### Step 7 — Neutralise competing wp-admin CSS

Once the right stylesheets are present, confirm they win. In `assets/css/admin-canvas.css`:

- Verify the cascade order in Step 0. If any admin rule wins a **tie** (e.g. `a:hover` at 0-1-1 against BB's
  base `.fl-button:hover` at 0-1-1), the fix is print order, not `!important`: raise our
  `admin_enqueue_scripts` priority so our handles print after `colors`.
- Add narrowly-scoped resets under `#bbca-custom-dashboard-canvas` for the specific admin rules the DevTools
  pass identifies. The file already does this for `input/textarea/select/button:focus` box-shadow — extend the
  same pattern, and keep every addition commented with which admin rule it counters, per the file's existing
  convention.
- Do **not** add blanket `a { color: inherit }` style resets; they would break the layout's own link colours.

### Step 8 — Wire the new setting through the stack (only if Step 5 ships)

`onedog_bbca_canvas_load_theme_styles`, following the `full_bleed_rows` precedent exactly:

| File | Change |
| --- | --- |
| `includes/modules/class-dashboard-canvas.php` | `THEME_STYLES_OPTION` constant; read it in `enqueue_theme_styles()` |
| `classes/class-onedog-bb-rest.php:515` | export payload |
| `classes/class-onedog-bb-rest.php:566` | import handler |
| `classes/class-onedog-bb-rest.php:816` | `get_dashboard_canvas()` |
| `classes/class-onedog-bb-rest.php:839` | `save_dashboard_canvas()` |
| `src/components/DashboardCanvas.jsx:21,42` | initial state, both defaults objects |
| `src/components/DashboardCanvas.jsx:262` | new toggle card, modelled on Full-Bleed Rows |
| `build/` | `npm run build`, commit the output (build artifacts are tracked in this repo) |

---

## 4. Test matrix

| Case | Expectation |
| --- | --- |
| Layout with a Button module using **global** colours | Hover matches the front end |
| Layout with a Button module using **per-node** colours | Unchanged (worked before) |
| BB Theme active, theme-styles toggle **off** | Buttons styled by Global Styles; admin chrome untouched |
| BB Theme active, theme-styles toggle **on** | Hover matches front end; note any admin chrome drift in STATE.md |
| Block/non-BB theme | No regression, no console or PHP errors |
| Admin colour schemes: fresh **and** midnight | Identical canvas rendering (proves no admin colour bleed) |
| BB deactivated | Canvas off, no fatals, no enqueue warnings |
| Assigned layout deleted | No fatals (existing `get_post()` guard) |
| BB cache cleared, admin visited first | Layout still styled (Step 6) |
| Squash on/off; full-bleed on/off; folded menu; < 782px | No regressions from v1.3.3/v1.3.4 |
| `?bbca_bypass=1` as administrator | Native dashboard, canvas assets absent |

Verify with DevTools that no BB stylesheet is applying rules **outside** `#bbca-custom-dashboard-canvas` other
than the `:root` custom properties from Step 2.

---

## 5. Release

- Bump `BBCA_VER` and the plugin header `Version:` to `1.3.5` in `beaver-builder-custom-admin.php`.
- `readme.txt` changelog entry.
- `STATE.md`: new "Current Phase: v1.3.5" section carrying the Step 0 stylesheet diff and the root cause;
  demote v1.3.4 to Historical; update the Release state line.
- `bin/build-zip.sh` needs no change — it copies `assets/`, `build/`, `classes/`, `includes/` wholesale, and
  this plan file is not in that set.

## 6. Risks

- **Front-end CSS in wp-admin can restyle admin chrome.** Mitigated by keeping theme CSS opt-in and default-off
  (Step 5) and by the existing `#wpbody-content > *:not(#bbca-custom-dashboard-canvas) { display: none }` rule,
  which already hides everything in the content column except the canvas.
- **BB internals are unversioned API.** Every new call is `class_exists`/`method_exists` guarded, so a BB
  upgrade that renames a method degrades to today's behaviour rather than fataling.
- **Layout JS in admin.** `fl-builder-layout` JS is already enqueued today, so this plan does not change that
  surface; watch the console during testing regardless.

## 7. Effort

Roughly half a day: ~30 min on-site diagnosis (Step 0), ~2–3 h PHP for Steps 1–4, 6 and 7, ~1 h for the toggle
and rebuild if Step 5 ships, ~1 h testing. Steps 1–4, 6 and 7 are PHP-only and need no `npm run build`.
