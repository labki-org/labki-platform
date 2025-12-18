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
if (file_exists(__DIR__ . '/extensions.platform.php')) {
    require_once __DIR__ . '/extensions.platform.php';
}

// 4. User Extension Selection (Declarative List)
// We look for extensions.user.php in the mounted config directory
$userExtListFile = '/mw-config/extensions.user.php';
$userExts = [];

if (file_exists($userExtListFile)) {
    $userExts = require $userExtListFile;
    if (!is_array($userExts)) {
        $userExts = [];
        error_log("Warning: extensions.user.php did not return an array.");
    }
}

// 5. Resolve and Load User Extensions
$bundledDir = $IP . '/extensions';         // /var/www/html/extensions
$userDir = '/mw-user-extensions';       // Mounted user extensions

if (!empty($userExts)) {
    foreach ($userExts as $extName) {
        // Valid extension names are alphanumeric
        if (!preg_match('/^[a-zA-Z0-9]+$/', $extName)) {
            continue;
        }

        if (is_dir("$bundledDir/$extName")) {
            // Extension exists in the image (bundled)
            wfLoadExtension($extName);
        } elseif (is_dir("$userDir/$extName")) {
            // Extension exists in user mount
            // We need to register the path if it's outside $IP/extensions?
            // wfLoadExtension usually looks in $IP/extensions.
            // If loading from arbitrary path, we might need to supply path or symlink.
            // wfLoadExtension second arg allows path in recent MW?
            // Actually wfLoadExtension($name, $path) is NOT standard. 
            // Standard is wfLoadExtension( 'Name' ); which assumes $IP/extensions/Name/extension.json

            // If it's in a different directory, we usually do:
            // require_once "$userDir/$extName/extension.json"; (if we could)
            // OR
            // $wgExtensionDirectory is an array? No, it's a string.

            // Ideally, we symlink user extensions into $IP/extensions at boot, 
            // BUT that modifies the container state which might be okay.
            // AS A FALLBACK for "Platform-style" loading without modifying $IP/extensions:
            // check for extension.json or Name.php

            if (file_exists("$userDir/$extName/extension.json")) {
                wfLoadExtension($extName, "$userDir/$extName/extension.json");
            } elseif (file_exists("$userDir/$extName/$extName.php")) {
                require_once "$userDir/$extName/$extName.php";
            }
        } else {
            error_log("Warning: Requested extension '$extName' not found in bundled ($bundledDir) or user ($userDir) dirs.");
        }
    }
}

// 6. User LocalSettings (Highest Precedence)
$userSettings = '/mw-config/LocalSettings.user.php';
if (file_exists($userSettings)) {
    require_once $userSettings;
}
