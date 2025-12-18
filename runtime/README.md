# Labki Runtime

Welcome to **Labki**, a pre-configured, production-ready MediaWiki distribution designed for ease of use and robustness.

## How it Works (Mental Model)

Labki provides a "fixed" MediaWiki foundation. You don't verify or install the core software; you simply **plug in** your configuration and content.

-   **The Code** is immutable (inside the Docker image).
-   **Your Data** (Database, Uploads) lives in persistent volumes.
-   **Your Config** (Secrets, Settings) lives in the `config/` folder.

## Quick Start

1.  **Copy the Configuration Template**:
    ```bash
    cp config/secrets.env.example config/secrets.env
    ```
2.  **Start the System**:
    ```bash
    docker compose up -d
    ```
3.  **Visit your Wiki**:
    Open [http://localhost:8080](http://localhost:8080).

## Configuration

### 1. Basic Settings (`secrets.env`)
Edit `config/secrets.env` to set your passwords and basic site info (`MW_SITE_NAME`, `MW_ADMIN_PASS`).
**Note**: Changes to `MW_SITE_NAME` only take effect during the *first* installation. To change it later, modify `LocalSettings.user.php` or reset the database.

### 2. User Settings (`LocalSettings.user.php`)
Use `config/LocalSettings.user.php` for your customizations. This file is loaded **last**, so it overrides platform defaults.

```php
<?php
// Example: Change the Logo
$wgLogo = "$wgScriptPath/resources/assets/my-logo.png";

// Example: Enable file uploads
$wgEnableUploads = true;
```

### 3. Adding Extensions
Labki comes with a powerful set of bundled extensions (Semantic MediaWiki, PageForms, etc.).
To enable **additional** extensions:

1.  **Bundled but disabled**: If the extension is inside the image but not on by default, just add it to `config/extensions.user.php`:
    ```php
    return [
       'VisualEditor', // Example
    ];
    ```
2.  **User-supplied**: Download the extension into `mw-user-extensions/MyExtension` and add it to `config/extensions.user.php`.

## Maintenance

### Upgrading
To upgrade Labki to the latest version:
```bash
docker compose pull
docker compose up -d
```
The system will automatically run database updates (`update.php`) on startup.

### Resetting (Development Only)
To completely wipe your wiki and start over:
```bash
docker compose down -v
rm -rf images/*
```
**WARNING**: This deletes all your data!
