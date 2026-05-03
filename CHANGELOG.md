# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Custom Tweeki skin theme with academic color palette and layout defaults
- CONTRIBUTING.md with contributor guidelines
- CHANGELOG.md for tracking releases
- CI badges in README
- Surface and elevation design tokens (`--labki-surface`, `--labki-shadow-sm`, `--labki-shadow-md`, `--labki-radius`) — schema bundles and per-wiki CSS can compose against these and flip cleanly with `[data-bs-theme="dark"]`.
- Right sidebar collapse drawer: `js/labki-tweeki.js` injects a viewport-anchored pull-tab button that toggles `body.sidebar-collapsed`; state persists in `localStorage["labki.sidebarCollapsed"]`.
- Page-actions relocation: the action-button cluster (Edit, History, …) is lifted out of `#sidebar-right` and re-anchored at the top-right of the content card, so it stays visible when the sidebar is collapsed and on narrow windows.
- `<html>` is tagged with `is-anon` or `is-logged-in` so per-wiki CSS can render login-conditional UI without a DOM round-trip.
- Timestamp localization: SMW-rendered UTC ISO timestamps in `<time datetime="...">` elements (preferred) and bare ISO strings inside wikitext tables are converted to the viewer's locale via `toLocaleString()`. Idempotent.

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
