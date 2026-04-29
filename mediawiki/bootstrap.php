<?php

/**
 * Labki Platform Bootstrap
 *
 * Orchestrates the configuration layers in order of increasing precedence.
 * Included by the auto-generated /var/www/html/LocalSettings.php loader.
 */

if (!defined('MEDIAWIKI')) {
    exit;
}

// 1. Platform base settings (DB, cache, identity, uploads, permissions)
require_once __DIR__ . '/LocalSettings.base.php';

// 2. Platform defaults (timezone, memory, logging, file extensions, branding)
if (file_exists(__DIR__ . '/LocalSettings.defaults.php')) {
    require_once __DIR__ . '/LocalSettings.defaults.php';
}

// 3. Curated extensions (gated for clean-slate testing).
//    Set MW_DISABLE_PLATFORM_EXTENSIONS=1 in docker-compose to skip.
if (!getenv('MW_DISABLE_PLATFORM_EXTENSIONS') && file_exists(__DIR__ . '/extensions.platform.php')) {
    require_once __DIR__ . '/extensions.platform.php';
}

// 4. Curated skins (loaded after extensions so VisualEditor & co. can
//    register skin-specific config). Loaded unconditionally — disabling
//    extensions for testing should not strip the platform skin set.
if (file_exists(__DIR__ . '/skins.platform.php')) {
    require_once __DIR__ . '/skins.platform.php';
}

// 5. User settings (highest precedence; secrets, site identity overrides,
//    additional extension/skin loads).
$userSettings = '/mw-config/LocalSettings.user.php';
if (file_exists($userSettings)) {
    require_once $userSettings;
}
