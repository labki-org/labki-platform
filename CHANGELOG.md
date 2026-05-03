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

### Changed
- Default skin is now Tweeki (was vector-2022). Tweeki is the curated labki experience and carries platform-specific chrome (labki-tweeki.css/js — theme toggle, sidebar drawer, page-actions relocation, login-state classes, LABKI-LOGIN / LABKI-THEME-TOGGLE nav elements). Vector and Citizen remain loaded; users can switch via Special:Preferences. Existing users with a saved skin preference are unaffected.

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
