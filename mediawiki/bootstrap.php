<?php

/**
 * Labki Platform Bootstrap
 * 
 * This file orchestrates the loading of configuration layers.
 * It is included by the auto-generated LocalSettings.php in the webroot.
 */

if (!defined('MEDIAWIKI')) {
    exit;
}

// 1. Load Platform Base Settings (Immutable)
require_once __DIR__ . '/LocalSettings.base.php';

// 2. Load Platform Defaults (Optional, safe defaults)
if (file_exists(__DIR__ . '/LocalSettings.defaults.php')) {
    require_once __DIR__ . '/LocalSettings.defaults.php';
}

// 3. Load Platform Extensions (Curated set)
// In dev/test modes, we might want to skip these to test in isolation.
// Set MW_DISABLE_PLATFORM_EXTENSIONS=1 in docker-compose to skip.
if (!getenv('MW_DISABLE_PLATFORM_EXTENSIONS') && file_exists(__DIR__ . '/extensions.platform.php')) {
    require_once __DIR__ . '/extensions.platform.php';
}

// 4. User LocalSettings (Highest Precedence)
// Users enable extensions and override settings here.
$userSettings = '/mw-config/LocalSettings.user.php';
if (file_exists($userSettings)) {
    require_once $userSettings;
}
