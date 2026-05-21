# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Labki Forum**: bundled forum-style discussion topics on top of MediaWiki DiscussionTools. Drop-in `.labki-forum-new-post-btn` (or `[data-labki-forum-new-post]`) creates a Talk-namespace subpage with `<UTC-timestamp>_<username>` slug and routes to DT's new-topic widget. Topic pages render as forum cards (outer frame, accent-bar first post, indented reply cards, button-styled reply links) with a `← parent` breadcrumb back-link. First H2 promotes to DISPLAYTITLE. Five custom SMW properties (`Topic subject`, `Topic starter`, `Comment count`, `Participant count`, `Has forum`) populate per topic so a landing page can render a forum index via `#ask` — `Has forum` is set on every topic from the page's base title, enabling chained queries like `[[Has forum.Has parent forum::Forum:Home]]` for cross-forum activity feeds on hub pages. Works with any namespace pair the deployer sets up; documented in [`docs/labki-forum.md`](docs/labki-forum.md).
- Custom Tweeki skin theme with academic color palette and layout defaults
- CONTRIBUTING.md with contributor guidelines
- CHANGELOG.md for tracking releases
- CI badges in README
- Surface and elevation design tokens (`--labki-surface`, `--labki-surface-1`, `--labki-surface-2`, `--labki-shadow-sm`, `--labki-shadow-md`) — schema bundles and per-wiki CSS compose against these and flip cleanly with theme. The surface ramp is parametric: re-tune by changing `--delta-lightness-surface` in `tokens-theme-base.less`.
- Right sidebar collapse drawer: `labki-tweeki.js` injects a viewport-anchored pull-tab button that toggles `body.sidebar-collapsed`; state persists in `localStorage["labki.sidebarCollapsed"]`.
- Page-actions relocation: the action-button cluster (Edit, History, …) is lifted out of `#sidebar-right` and re-anchored at the top-right of the content card, so it stays visible when the sidebar is collapsed and on narrow windows.
- `<html>` is tagged with `is-anon` or `is-logged-in` so per-wiki CSS can render login-conditional UI without a DOM round-trip.
- TemplateStyles extension (bundled with MediaWiki 1.44) loaded by default. Schema bundles can now ship per-template CSS via `<templatestyles src="Template:Foo/styles.css" />` without requiring site-wide `editsitecss` rights for deploy bots.
- Canonical `skin-theme-clientpref-{day|night}` class on `<html>`, applied alongside `data-bs-theme` by both the inline head-script and `setTheme()`. Lets MediaWiki core, Codex components, and any night-mode-aware extension hook in via the upstream-canonical signal without per-element overrides on our side.
- MediaWiki Codex dark-token coverage via `.cdx-mode-dark()` — `tokens-theme-dark.less` imports `mediawiki.skin.codex-design-tokens/theme-wikimedia-ui-mixin-dark.less` and calls the upstream mixin (the same pattern Vector 2022 and Minerva use), redefining `--color-base`, `--background-color-base`, etc. under our dark selector so OOUI / Codex components (Special:Preferences tabs, Special:Search namespace tabs, OOUI dialogs) flip in dark mode without per-component rules. Codex's own `theme-wikimedia-ui-mode-dark.css` isn't loaded by Tweeki; this mixin is the bridge, and tracks Codex bumps automatically.
- Dark-mode coverage for MediaWiki content surfaces that core / Tweeki paint with literal hex values from compiled LESS `@token` (no runtime variable to redefine): wikitable bodies (not just headers), inline `<code>` / `<kbd>` / `<samp>`, `<pre>` blocks, `<hr>`, image thumbnail frames, MediaWiki message boxes, diff tables, the SMW factbox (header + property-name + values), the edit-page footer panel (`.editOptions`, `#editpage-copywarn`), Special:Search namespace tabs, Special:Specialpages legend, the Preferences sticky save bar, Echo `Special:Notifications` (toolbar wrapper, notification cards, page-filter sidebar selection), and the VisualEditor save dialog "Watch this page" row. All token-driven so they flip with theme.
- SyntaxHighlight (Pygments) dark-mode treatment: the gutter behind line numbers (painted via inset `box-shadow` on the `<pre>`, not `background-color`) now uses the subtle-surface token and flips with theme. Also fixes a layout bug where our generic `#content pre` padding was overriding SyntaxHighlight's `padding-left: 3.5em` reservation, causing line numbers to overlap the first character of code.
- Redlink fix and blockquote styling for the Tweeki theme. Bootstrap's Reboot was zeroing `<blockquote>` margins; redlinks were being overridden by the `#content a` accent rule. Both addressed via scoped overrides using existing tokens.
- Print-mode reset: `@media print` redefines the `--labki-*` and Codex token palettes back to light values, regardless of `[data-bs-theme]` / `skin-theme-clientpref-night`. Hides chrome (sidebar toggle, theme toggle, navbar, footer, page-actions) so paper output isn't cluttered or wasteful.

### Changed
- Default skin is now Tweeki (was vector-2022). Tweeki is the curated labki experience and carries platform-specific chrome (`labki-tweeki.less` / `labki-tweeki.js` — theme toggle, sidebar drawer, page-actions relocation, login-state classes, LABKI-LOGIN / LABKI-THEME-TOGGLE nav elements). Vector and Citizen remain loaded; users can switch via Special:Preferences. Existing users with a saved skin preference are unaffected.
- Theme stylesheet refactored from a single `labki-tweeki.css` into a Citizen-style trio: `labki-tweeki.less` (rules) + `tokens-theme-base.less` (light tokens) + `tokens-theme-dark.less` (dark tokens + Codex mirror). Tokens are declared in OKLCH for perceptual uniformity; the dark theme overrides only lightness values so the palette is a parametric mirror of the light palette rather than a hand-tuned alternative. SchemaSync TemplateStyles already consume `var(--labki-*)` with literal fallbacks, so this is a backing change with no template-side migration.
- `compose/docker-compose.dev.yml` now also live-mounts `mediawiki/` so edits to platform PHP config (skins.platform.php, extensions.platform.php, LocalSettings.base.php) take effect without an image rebuild.

### Fixed
- **Labki Forum — `Comment count` / `Participant count` on non-UTC wikis**: the `ParserAfterParse` annotator was matching the literal string `(UTC)` to count signed comments, so wikis configured with a non-UTC `$wgLocaltimezone` (signatures end in `(PDT)` / `(PST)` / `(EST)` / etc.) saw `Comment count = 0` and no `Participant count` annotation — leaving the forum-index `?Comment count=Replies` column stuck at 0 even on active topics. The regex now matches the full MW English-locale signature timestamp shape (`HH:MM, D MONTHNAME YYYY (TZ)`) with an open-ended TZ token, so any locale-time-zone setting counts correctly. Existing topic pages need a null-edit (or `rebuildData.php -n 3000,3001`) for the annotation to backfill.

### Removed
- Timestamp-localization JavaScript in `labki-tweeki.js`. The string-rewrite was overriding MediaWiki user date preferences with a hardcoded format. Date display now defers to SMW query authors (`?Date`, `?Date#-F[...]`) and MediaWiki preferences.
- `--labki-warning` and `--labki-radius` design tokens — defined but unreferenced anywhere in the codebase.
- **WikiForum extension**. Replaced by the new bundled Labki Forum module (DiscussionTools-based). Removed from `extensions-git/sources.txt` and `mediawiki/extensions.platform.php`.

### Notes
- A future MediaWiki version is expected to ship a `darkmode-override()` LESS mixin (currently absent in 1.44.5) for skins to wrap per-element dark-mode overrides while waiting for upstream Codex migration. Adopt once available; until then the per-element rules in `labki-tweeki.less` carry the same intent in plain LESS.

## [0.1.0] - 2026-01-13

### Added
- Initial platform image based on MediaWiki 1.44
- Layered configuration system (bootstrap.php, LocalSettings.base.php, extensions.platform.php)
- Curated extension set: SemanticMediaWiki, PageForms, Maps, Mermaid, and more
- Git-based extension installation (MsUpload, Lockdown, WikiForum, ConfirmAccount, Citizen)
- Docker entrypoint with auto-install and update.php support
- Development harness (`compose/docker-compose.dev.yml`)
- CI/CD with smoke tests and GHCR publishing
- Technical contract documentation (`docs/contract.md`)
- Extension development guide (`docs/extension-dev-guide.md`)
