# `mediawiki/` — Platform Configuration

This directory holds the PHP configuration that gets baked into the Labki
Docker image. At runtime, `bootstrap.php` is sourced from the
auto-generated `/var/www/html/LocalSettings.php` and includes each of the
files below in a defined order. User overrides live outside this
directory in `/mw-config/LocalSettings.user.php`.

For the architectural contract (paths, env vars, invariants), see
[`docs/contract.md`](../docs/contract.md). This README is the practical
"which file do I edit?" guide.

## Load order

`bootstrap.php` requires each file in turn — later files can override
earlier ones:

1. `LocalSettings.base.php` — platform invariants
2. `LocalSettings.defaults.php` — overridable defaults
3. `extensions.platform.php` — curated extensions (skippable via env flag)
4. `skins.platform.php` — curated skins
5. `/mw-config/LocalSettings.user.php` — user overrides (loaded by bootstrap, not in this directory)

## Files

### `bootstrap.php`

Glue. Includes the four platform files in order, optionally followed by
the user-supplied LocalSettings. Should rarely change.

### `LocalSettings.base.php` — platform invariants

Settings the platform treats as load-bearing and not user-overridable.

- Database connection (`$wgDBserver`, `$wgDBname`, etc., from env vars)
- Site identity (`$wgServer`, `$wgScriptPath`, `$wgArticlePath`)
- Object cache backend (`$wgMainCacheType`, plus session/message/parser caches — all CACHE_DB)
- Image upload paths and ImageMagick binding
- "Private wiki by default" permission baseline and read-whitelist

If you find yourself wanting to change something here from a user
override, the override probably belongs in `LocalSettings.user.php`
instead — base settings are intentionally not flexible.

### `LocalSettings.defaults.php` — overridable defaults

Reasonable starting values that legitimate deployments may tune.

- Timezone, PHP memory limit
- Logging routing (stderr → Docker logs)
- Job-runner rate (defers to the `wiki-jobrunner` container)
- Allowed file extensions and `$wgMaxUploadSize`
- "Powered by Labki" footer badge

If you want to change one of these per-deployment, set it in your
`LocalSettings.user.php`.

### `extensions.platform.php` — curated extensions

`wfLoadExtension(...)` calls for the platform's curated set, plus
extension-specific configuration that's tied directly to those loads
(captcha triggers, VisualEditor user options, ConfirmAccount OOUI
workaround, etc.).

This file is skipped at startup when `MW_DISABLE_PLATFORM_EXTENSIONS=1`
is set in the environment — used during clean-slate extension testing.

Ordering matters: ConfirmEdit must come before WikiForum/ConfirmAccount.
The header comment in the file flags the constraint.

### `skins.platform.php` — curated skins

`wfLoadSkin(...)` calls for Vector, Citizen, and Tweeki, plus the
Tweeki-specific customization (custom CSS/JS modules, navbar elements
including the LABKI-LOGIN and SPECIALPAGES entries).

Loaded **after** `extensions.platform.php` so any extension that queries
the active skin (e.g. VisualEditor's supported-skins list) sees skins as
already registered. Loaded unconditionally — disabling the extensions
flag for testing should not strip the skin set.

`$wgDefaultSkin` is set here. The Tweeki customization globals
(`$wgTweekiSkin*`) are no-ops for users on other skins, so they cost
nothing for non-Tweeki users.

## Where do I put a new setting?

| Kind of setting | Goes in |
| :--- | :--- |
| New extension load | `extensions.platform.php` |
| New skin load | `skins.platform.php` |
| Per-deployment toggle a user might want to flip | `LocalSettings.defaults.php` |
| Site infrastructure (DB, cache, security baseline) | `LocalSettings.base.php` |
| Personal/instance secrets, branding overrides, custom ext loads | `/mw-config/LocalSettings.user.php` (user-side, not this directory) |
