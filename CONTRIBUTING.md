# Contributing to Labki Platform

Thank you for your interest in contributing to the Labki Platform! This document provides guidelines for contributing.

## Getting Started

1. **Fork** this repository
2. **Clone** your fork locally
3. **Build** the development environment:
   ```bash
   docker compose -f compose/docker-compose.dev.yml up --build
   ```
4. Access the wiki at `http://localhost:8080`

## Development Workflow

### Making Changes

1. Create a feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
2. Make your changes
3. Test locally using the dev compose file
4. Commit with clear, descriptive messages

### Types of Contributions

| Area | Directory | Description |
|------|-----------|-------------|
| **Platform Config** | `mediawiki/` | Core MediaWiki settings |
| **Extensions** | `extensions-git/sources.txt` | Git-based extensions |
| **Composer Deps** | `composer/` | PHP dependencies |
| **Docker** | `docker/` | Dockerfile and entrypoint |
| **Documentation** | `docs/` | Technical documentation |

### Adding a New Extension

**Via Composer** (preferred for packagist extensions):
1. Add to `composer/composer.platform.json`
2. Add `wfLoadExtension()` call to `mediawiki/extensions.platform.php`

**Via Git** (for non-packagist extensions):
1. Add entry to `extensions-git/sources.txt`
2. Add `wfLoadExtension()` call to `mediawiki/extensions.platform.php`

## Code Style

- **Shell scripts**: Follow [Google Shell Style Guide](https://google.github.io/styleguide/shellguide.html)
- **PHP**: Follow [MediaWiki coding conventions](https://www.mediawiki.org/wiki/Manual:Coding_conventions/PHP)
- Use LF line endings (not CRLF)

## Testing

Before submitting a PR:

```bash
# Run the smoke test
./ci/smoke-test.sh
```

## Pull Request Process

1. Ensure CI passes
2. Update documentation if needed
3. Add entry to CHANGELOG.md under "Unreleased"
4. Request review from maintainers

## Reporting Issues

When reporting bugs, please include:
- Steps to reproduce
- Expected vs actual behavior
- Docker/OS version
- Relevant logs (`docker compose logs wiki`)

## Questions?

Open a [Discussion](https://github.com/labki-org/labki-platform/discussions) for questions or ideas.
