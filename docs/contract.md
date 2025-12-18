# Labki Platform Contract

This document defines the interface and invariants of the Labki Platform. It serves as the source of truth for how the platform image behaves, how configuration is layered, and what guarantees are provided to users.

## 1. Architectural Invariants

1.  **No DB Server in Image**: The MediaWiki image MUST NOT include a running database server. It must connect to an external MySQL/MariaDB host.
2.  **Persistence via Mounts**: All user changes (uploads, configuration, custom extensions) must persist via Docker volumes or bind mounts. The container filesystem is ephemeral.
3.  **Idempotent Entrypoint**: The container entrypoint must handle:
    -   Starting with a fresh DB (install schema).
    -   Starting with an existing DB (noop or update).
    -   Starting with a fresh DB but existing config (re-install).
    -   Always running `maintenance/update.php --quick`.
4.  **Composer is Platform-Owned**: Users DO NOT run `composer install`. All PHP dependencies are pre-installed in the image.

## 2. Configuration Layering

The configuration is loaded in a strict order of precedence (lowest to highest).

| Layer | File Path (In Container) | Owned By | Purpose |
| :--- | :--- | :--- | :--- |
| **Loader** | `/var/www/html/LocalSettings.php` | Platform | Auto-generated entry point. Loads `bootstrap.php`. |
| **Glue** | `/opt/labki/mediawiki/bootstrap.php` | Platform | Orchestrates the loading sequence. |
| **Base** | `/opt/labki/mediawiki/LocalSettings.base.php` | Platform | DB connection, cache config, jobrunner. **Never edited by users.** |
| **Defaults** | `/opt/labki/mediawiki/LocalSettings.defaults.php` | Platform | Safe defaults (logging, memory limits). |
| **Platform Exts** | `/opt/labki/mediawiki/extensions.platform.php` | Platform | Curated set (SMW, PageForms, etc.). Explicit `wfLoadExtension`. |
| **User Exts** | `/mw-config/extensions.user.php` | User | **Declarative list** of optional extensions to load. |
| **User Config** | `/mw-config/LocalSettings.user.php` | User | Site identity, secrets, overrides. **Highest precedence.** |

## 3. Public Interface (Environment Variables)

The following environment variables constitute the public API for configuring the instance at runtime.

### Database Connection
| Variable | Default | Description |
| :--- | :--- | :--- |
| `MW_DB_HOST` | `db` | Hostname of the MariaDB/MySQL server. |
| `MW_DB_NAME` | `labki` | Name of the database. |
| `MW_DB_USER` | `labki` | Database user. |
| `MW_DB_PASSWORD` | `labki_pass` | Database password. |

### Development / Test Control
| Variable | Default | Description |
| :--- | :--- | :--- |
| `MW_DISABLE_PLATFORM_EXTENSIONS` | `0` (False) | If set to `1`, skips loading the curated extension set (`extensions.platform.php`). Allows for clean-slate testing. |

### Site Identity (Install Time Only)
*These depend on the `install-mediawiki.sh` script logic and are typically used only during the initial installation.*
| Variable | Default | Description |
| :--- | :--- | :--- |
| `MW_SITE_NAME` | `Labki` | Name of the Wiki. |
| `MW_SITE_LANG` | `en` | Language code. |
| `MW_SERVER` | `http://localhost:8080` | Server URL. |
| `MW_SCRIPT_PATH` | `/w` | Script path. |
| `MW_ADMIN_USER` | `admin` | Initial admin username. |
| `MW_ADMIN_PASS` | (Required) | Initial admin password. |

## 4. Runtime Paths (Mount Points)

| Host Path | Container Path | Purpose |
| :--- | :--- | :--- |
| `./config/` | `/mw-config/` | Contains `secrets.env`, `LocalSettings.user.php`, `extensions.user.php`. |
| `./images/` | `/var/www/html/images` | User uploads. |
| `./mw-user-extensions/` | `/mw-user-extensions` | Code for user-supplied extensions not in the platform image. |

## 5. Upgrade & Reset Behavior

-   **Upgrade**: Pull new image -> `docker compose up`. The entrypoint automatically runs `update.php`. User config and DB data are preserved.
-   **Reset (Dev)**: Delete the DB volume and `images` directory. The entrypoint detects a fresh state and re-installs.
