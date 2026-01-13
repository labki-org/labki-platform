# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- CONTRIBUTING.md with contributor guidelines
- CHANGELOG.md for tracking releases
- CI badges in README

### Changed
- (None yet)

### Fixed
- (None yet)

## [0.1.0] - 2026-01-13

### Added
- Initial platform image based on MediaWiki 1.44
- Layered configuration system (bootstrap.php, LocalSettings.base.php, extensions.platform.php)
- Curated extension set: SemanticMediaWiki, PageForms, Maps, Mermaid, and more
- Git-based extension installation (MsUpload, PageSchemas, Lockdown, WikiForum, Citizen)
- Docker entrypoint with auto-install and update.php support
- Development harness (`compose/docker-compose.dev.yml`)
- CI/CD with smoke tests and GHCR publishing
- Technical contract documentation (`docs/contract.md`)
- Extension development guide (`docs/extension-dev-guide.md`)
