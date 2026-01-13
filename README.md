# Labki Platform

[![CI](https://github.com/labki-org/labki-platform/actions/workflows/ci.yml/badge.svg)](https://github.com/labki-org/labki-platform/actions/workflows/ci.yml)
[![Publish](https://github.com/labki-org/labki-platform/actions/workflows/publish.yml/badge.svg)](https://github.com/labki-org/labki-platform/actions/workflows/publish.yml)
[![License](https://img.shields.io/github/license/labki-org/labki-platform)](LICENSE)

The **Labki Platform** (`labki-platform`) is the foundational repository for the Labki MediaWiki distribution. It serves two distinct but related purposes:

1.  **Development Harness**: A centralized environment where developers can mount extensions, test changes, and reset the instance quickly.
2.  **Build Context**: The source for producing versioned, reproducible MediaWiki Docker images used by end-users.

## Architecture Overview

The platform defines a single authoritative MediaWiki environment. All platform-owned logic (core configuration, curated extensions, Composer dependencies) is **baked into the Docker image**. All instance-owned logic (secrets, site identity, optional extensions) is **layered at runtime** via mounted files.

### Key Decisions
-   **No DB in Image**: The MediaWiki image contains code only. The database always runs in a separate container (or external service).
-   **Immutable Platform Config**: `LocalSettings.base.php` and `extensions.platform.php` are burned into the image.
-   **Layered User Config**: Users invoke `LocalSettings.user.php` and `extensions.user.php` to customize their instance without forking the platform.

## Documentation

-   **[Technical Contract](docs/contract.md)**: Specifications for environment variables, paths, and platform behavior.
-   **[User Guide](runtime/README.md)**: How to deploy and configure the runtime distribution.
-   **[Extension Dev Guide](docs/extension-dev-guide.md)**: How to use this platform to test your own extensions.
-   **[Changelog](CHANGELOG.md)**: Release history and notable changes.

## Repository Structure

-   `docker/`: Dockerfile and entrypoint logic.
-   `mediawiki/`: Platform configuration files (`LocalSettings.base.php`, `bootstrap.php`, etc.).
-   `composer/`: Platform-level Composer dependencies.
-   `extensions-git/`: Definitions for git-only extensions included in the platform.
-   `scripts/`: Helper scripts for installation, DB waiting, and resets.
-   `runtime/`: **(Preview)** The source code for the user-facing `labki` repository.
-   `compose/`: Developer harness compose files.

## Development Workflow

Developers should use the tools in this repo to build and verify the platform image. Use the "Dev Harness" (Pattern A) to develop extensions against this platform.

1.  Clone this repo.
2.  Run `docker compose -f compose/docker-compose.dev.yml up --build`.
3.  Access Wiki at `http://localhost:8080`.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:
-   **Platform Changes**: Edit `mediawiki/` or `composer/` to change the base platform behavior.
-   **New Extensions**: Add to `composer/composer.platform.json` or `extensions-git/sources.txt`.

