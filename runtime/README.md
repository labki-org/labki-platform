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
Labki comes with a powerful set of curated extensions (Semantic MediaWiki, PageForms, etc.) enabled by default.

To enable **additional** extensions, add `wfLoadExtension` calls to your `config/LocalSettings.user.php`:

**A. Bundled Extensions** (Built-in but disabled by default)
```php
wfLoadExtension( 'VisualEditor' );
```

**B. Your Custom Extensions**
1.  Download/Clone the extension into the `mw-user-extensions/` folder.
    *   Example: `mw-user-extensions/MycoolExtension`
2.  Load it in `config/LocalSettings.user.php` with the correct path:
    ```php
    wfLoadExtension( 'MycoolExtension', '/mw-user-extensions/MycoolExtension/extension.json' );
    ```

### 4. Adding Skins
Labki includes two skins by default: **Citizen** (the default) and **Chameleon** (a Bootstrap-based skin ideal for customization).

To change the default skin, add to your `config/LocalSettings.user.php`:
```php
$wgDefaultSkin = 'chameleon';
```

**Adding Custom Skins**
1.  Download/Clone the skin into the `mw-user-skins/` folder.
    *   Example: `mw-user-skins/MyCustomSkin`
2.  Load it in `config/LocalSettings.user.php` with the correct path:
    ```php
    wfLoadSkin( 'MyCustomSkin', '/mw-user-skins/MyCustomSkin/skin.json' );
    $wgDefaultSkin = 'mycustomskin';
    ```

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
