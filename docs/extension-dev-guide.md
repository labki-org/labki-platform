# Extension Development Guide

The Labki Platform is designed to be a robust test harness for *any* MediaWiki extension. This guide explains how to use the Platform image to develop and test your own extension in isolation.

## The "Clean Slate" Testing Workflow

When developing an extension, you typically want to test it against a standard MediaWiki installation *without* the interference of other complex platform extensions (like SemanticMediaWiki).

We support this via the `MW_DISABLE_PLATFORM_EXTENSIONS` environment variable.

### Prerequisites

Before you begin, you need the Platform image available locally.
Since the image is not yet published to a public registry, you must build it from the `labki-platform` repo:

```bash
# In labki-platform repo
./scripts/build-image.sh
```

This will tag the image as `labki-wiki:latest`, which the examples below use.

### 1. Basic Setup (Docker Compose)

Create a `docker-compose.yml` in your **extension's repository**:

```yaml
services:
  db:
    image: mariadb:10.11
    environment:
      MARIADB_DATABASE: labki
      MARIADB_USER: labki
      MARIADB_PASSWORD: labki_pass
      MARIADB_ROOT_PASSWORD: root_pass
    volumes:
      - db-data:/var/lib/mysql

  wiki:
    image: labki-wiki:latest  # Or your specific version tag
    ports:
      - "8080:80"
    environment:
      # Connect to DB
      MW_DB_HOST: db
      MW_DB_NAME: labki
      MW_DB_USER: labki
      MW_DB_PASSWORD: labki_pass
      # Admin Credentials for tests
      MW_ADMIN_USER: admin
      MW_ADMIN_PASS: secret
      # CRITICAL: Disable bundled extensions for a clean test env
      MW_DISABLE_PLATFORM_EXTENSIONS: 1
    volumes:
      # Mount YOUR extension code into the container
      - ./:/mw-user-extensions/MyExtension
      # Mount a config file to load it
      - ./tests/LocalSettings.test.php:/mw-config/LocalSettings.user.php
    depends_on:
      - db

volumes:
  db-data:
```

### 2. The Test Config (`tests/LocalSettings.test.php`)

In your extension repo, create a `tests/LocalSettings.test.php`:

```php
<?php
// Load your extension from the mount point
wfLoadExtension( 'MyExtension', '/mw-user-extensions/MyExtension/extension.json' );

// Configure your extension for testing
$wgMyExtensionSetting = 'test_value';

// Optional: Enable debug mode
$wgShowExceptionDetails = true;
```

### 3. Running Tests

1.  **Start the environment**:
    ```bash
    docker compose up -d
    ```
2.  **Run PHPUnit** (inside the container):
    ```bash
    docker compose exec wiki composer phpunit extensions/MyExtension/tests/phpunit
    ```
    *Note: Because we mounted to `/mw-user-extensions/` but PHPUnit might expect `extensions/`, you might need to symlink or adjust paths depending on your test suite. Ideally, the Platform image ensures `wfLoadExtension` registers the path correctly.*

## Integration Testing (Full Platform)

If your extension *depends* on the full Labki Platform (e.g. it integrates with PageForms), simply set:

```yaml
MW_DISABLE_PLATFORM_EXTENSIONS: 0
```

## Recipe: Overriding a Bundled Extension

A common scenario: The Platform bundles `MyExtension` (e.g. at version 1.0), but you want to develop `MyExtension` (version 2.0-dev) locally using the Platform image.

If you just mount it and load it, MediaWiki might crash because the class is defined twice (once in `/var/www/html/extensions` and once in `/mw-user-extensions`).

**The Solution:**
1.  Set `MW_DISABLE_PLATFORM_EXTENSIONS: 1` in your `docker-compose.yml`.
    *   This stops the platform from loading the *bundled* version.
2.  In your `LocalSettings.test.php`, manually load the extensions you need:
    ```php
    <?php
    // 1. Re-enable other platform extensions if you need them (load from default path)
    wfLoadExtension( 'SemanticMediaWiki' );
    wfLoadExtension( 'PageForms' );

    // 2. Load YOUR local version of the extension from the mount
    wfLoadExtension( 'MyExtension', '/mw-user-extensions/MyExtension/extension.json' );
    ```

This gives you full control: you use the Platform's runtime environment, but you decide exactly which code is loaded.
