# Changelog

All notable changes to CloudScale SEO AI Optimizer are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [4.14.5] - 2026-03-12
### Fixed
- High: `load_plugin_textdomain()` was never called; text domain now registered on `plugins_loaded`
- High: Raw HTML echo in robots.txt writable status wrapped with `wp_kses_post()`
- Medium: Eight bare `echo` of hardcoded ternary CSS/attribute values wrapped with `esc_attr()`
- Medium: Batch log entry count echo cast to `(int)`
### Changed
- CHANGELOG.md updated to cover 4.13.x–4.14.x releases

## [4.14.4] - 2026-03-12
### Fixed
- Category Drift: `cdRenderDrift()` now uses `post_ids` for matching on page reload, preventing reversion to stale unanalysed count

## [4.14.3] - 2026-03-12
### Changed
- Category Drift: debug fields removed from server response; status line cleaned to `(N move groups, M posts matched)`

## [4.14.2] - 2026-03-12
### Fixed
- Category Drift: PHP title→ID lookup now normalises smart/curly apostrophes, en-dashes, em-dashes, and HTML entities before comparing — resolves 0-match failures on posts with non-ASCII punctuation in titles

## [4.14.1] - 2026-03-12
### Changed
- Category Drift: unmatched titles now always shown in status line (not only when totalIds === 0)

## [4.14.0] - 2026-03-12
### Added
- Category Drift: debug info (unmatched AI titles, known title keys) added to server response to diagnose title→ID mismatches

## [4.13.9] - 2026-03-12
### Fixed
- Category Drift: combined ID + title matching restores correct unanalysed count after fixing regression introduced in 4.13.8

## [4.13.8] - 2026-03-12
### Fixed
- Category Drift: `stillUnassigned` now uses post_ids (ID-based exact match) instead of title fuzzy match for counting remaining unanalysed posts

## [4.13.7] - 2026-03-12
### Added
- Category Drift: PHP server-side title→ID resolution; each move group now returns `post_ids` for reliable client-side matching
- Category Drift: visible `(N move groups, M posts matched)` status line shown after each Analyse run

## [4.13.6] - 2026-03-12
### Fixed
- Category Drift: `▼ N unanalysed posts` toggle button text now updates after analysis (was static from initial render)

## [4.13.5] - 2026-03-12
### Fixed
- Category Drift: `cdMatchPost` was defined inside `cdRenderDrift()` making it invisible to `cdAnalyseRemaining()` — caused silent ReferenceError leaving the Analyse button permanently disabled after each run

## [4.13.4] - 2026-03-12
### Fixed
- Version bump only; no functional changes from 4.13.3

## [4.13.3] - 2026-03-12
### Changed
- Category Drift: elapsed timer moved inside Analyse button text (`🤖 Analysing N posts… (8s)`) for more visible feedback

## [4.13.2] - 2026-03-12
### Added
- Category Drift: Stop button with AbortController on both `cdLoad()` (Run Analysis) and `cdAnalyseRemaining()` (Analyse N remaining)
- Category Drift: elapsed time counter on Run Analysis; post count shown in Analyse button label during run

## [4.13.1] - 2026-03-12
### Fixed
- PHP operator-precedence bug in `defer_font_css()` noscript href — preg_match result was concatenated before ternary, making href always empty; also added `esc_attr()` to href output
### Changed
- i18n: `esc_html__()` / `esc_html_e()` added to user-visible strings in admin notice, metabox labels/buttons, and frontend summary box
- `admin_page_css()`, `llms_preview_js()`, `sitemap_preview_js()` extracted to new `trait-settings-assets.php` (reduces trait-settings-page.php by ~300 lines)

## [4.13.0] - 2026-03-12
### Added
- CHANGELOG.md created in Keep-a-Changelog format
### Changed
- Dashboard widget title wrapped with `wp_kses_post()` and `esc_html()` for the version span
- `@since`, `@param`, `@return` DocBlocks added to all public methods across all 23 trait files

## [4.12.9] - 2026-03-12
### Security
- Removed `wp_ajax_nopriv_` registration from `ajax_download_fonts` (admin-only handler)
- Added `esc_attr()` to three unescaped ternary echo outputs in `render_metabox()`
### Changed
- Replaced all `file_get_contents`/`file_put_contents` calls in font optimizer with WP_Filesystem equivalents

## [4.12.8] - 2026-03-12
### Fixed
- SEO score not returned when post title needed fixing — `call_ai_generate_all()` was overwriting `$json_shape` and omitting `seo_score`/`seo_notes` fields

## [4.12.7] - 2026-03-12
### Changed
- Dashboard widget Posts pill colour changed from grey (`#475569`) to blue (`#2271b1`)

## [4.12.6] - 2026-03-12
### Fixed
- PHP parse error (invalid heredoc body indentation) in `admin_page_css()`, `llms_preview_js()`, `sitemap_preview_js()` — nowdoc closing markers moved to column 0
### Security
- Dashboard widget health-refresh and health-run `<script>` blocks moved to `wp_add_inline_script` via `ob_start` capture
- Post editor metabox `csSeoGenOne` and `csSeoSumGenOne` `<script>` blocks moved to `wp_add_inline_script` via `ob_start` capture
- Registered `cs-seo-dashboard-js` (dashboard) and `cs-seo-metabox-js` (post edit screens) handles for inline script delivery
### Added
- `uninstall.php` — cleans up all options, post meta, transients, and cron on plugin deletion

## [4.12.5] - 2026-03-12
### Security
- HTTPS scanner script moved from echoed `<script>` block to `wp_add_inline_script` via `ob_start` capture
- Font optimizer script moved from echoed `<script>` block to `wp_add_inline_script` via `ob_start` capture
- Main settings page script (abTab, abState, category fixer, related articles) moved to `wp_add_inline_script` via `ob_start` capture
- PHP values `abNonce`, `abAjax`, `abMinChar`, `abMaxChar`, `abHasApiKey`, `cfNonce`, `chNonce`, `cdNonce`, `rcNonce` now passed via `csSeoAdmin` (`wp_localize_script`)

## [4.12.4] - 2026-03-12
### Security
- Settings page CSS moved from echoed `<style>` block to `wp_add_inline_style()`
- llms.txt preview JS moved from echoed `<script>` block to `wp_add_inline_script()`
- Sitemap preview JS moved from echoed `<script>` block to `wp_add_inline_script()`
- PHP values `ajaxUrl`, `nonce`, `sitemapIndexUrl` now passed via `wp_localize_script`

## [4.12.3] - 2026-03-11
### Added
- ABSPATH direct access protection on all trait files
### Changed
- Refactored monolithic plugin into 23 trait files for maintainability
- AI SEO scoring per post added to admin panel

## [4.11.38] - 2026-03-11
### Changed
- Panel UI: bold blue drop shadow, contrast body background
- Post list pagination set to 50 posts/page with consistent page numbers

## [4.11.33] - 2026-03-11
### Changed
- Post titles in AI Image ALT Text Generator and AI Summary Box panels are now clickable links to the post editor

## [4.11.32] - 2026-03-11
### Fixed
- Noindex posts now excluded from the Update Posts with AI Descriptions panel and post count stats

## [4.11.26] - 2026-03-11
### Added
- Dashboard widget SEO Health pills: Posts, SEO, Images, Links, Summaries with colour coding
- Health data timestamp and Refresh link added to widget
- Run Health Check CTA shown when no cache exists
- Health cache auto-rebuilds after bulk runs complete

## [4.10.68] - 2026-03-11
### Added
- Category Fixer colour legend above table

## [4.10.66] - 2026-03-11
### Added
- AI Analyse All button added to Category Fixer toolbar
- Per-row reanalyse now calls AI instead of local scorer

## [4.10.65] - 2026-03-11
### Added
- `ajax_catfix_ai_one()` handler: analyses a single post via Claude and returns proposed category IDs

## [4.10.59] - 2026-03-11
### Added
- Category Fixer tab with local keyword scoring engine, paginated review table, bulk Apply All Changed, filter bar

## [4.10.50] - 2026-03-11
### Added
- AI Summary Box generator panel completed with paginated post list and Force Regenerate All button

## [4.10.46] - 2026-03-11
### Added
- AI Summary Box renderer — prepends styled summary card to singular post content

## [4.10.44] - 2026-03-11
### Added
- Gutenberg sidebar panel with custom SEO title, description, OG image, AI summary fields

## [4.10.34] - 2026-03-11
### Added
- OG image 1200×630 crop with `og:image:secure_url` for WhatsApp scraper
- ALT text scanner now includes featured images

## [4.10.22] - 2026-03-11
### Fixed
- Converted inline style echo to `wp_add_inline_style`
- Moved inline script blocks to `wp_add_inline_script`
- Added `wp_localize_script` for PHP values

## [4.10.18] - 2026-03-11
### Added
- Deactivation hook removes stale asset files
- Version change detector cleans leftover assets and resets OPcache

## [4.10.12] - 2026-03-11
### Changed
- All performance controls moved into the Performance tab

## [4.10.0] - 2026-03-11
### Added
- Font display optimisation with font-display swap, metric overrides, and CDN download
- JavaScript deferral with configurable exclusions
- HTML, CSS, JS minification
- HTTPS mixed content scanner and one-click fixer

## [4.9.14] - 2026-03-11
### Added
- WordPress dashboard widget

## [4.9.7] - 2026-03-11
### Added
- ALT text article excerpt length now configurable (100–2000 chars)

## [4.9.3] - 2026-03-11
### Added
- Defer render-blocking JavaScript feature with configurable exclusions

## [4.2.0] - 2026-03-11
### Changed
- Character range decoupled from system prompt

## [4.1.0] - 2026-03-11
### Added
- Automatic correction pass for out-of-range descriptions

## [4.0.0] - 2026-03-11
### Added
- Initial release of AI Meta Writer tab
- Bulk meta description generation with live progress log
- Per-post generation from post editor metabox
