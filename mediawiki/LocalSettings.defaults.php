<?php

// LocalSettings.defaults.php - Safe defaults
// These settings are reasonable defaults that legitimate users might want to override.

// Protect against web entry
if (!defined('MEDIAWIKI')) {
    exit;
}

// Timezone
$wgLocaltimezone = "UTC";
date_default_timezone_set($wgLocaltimezone);

// Memory Limit (Generous for SMW)
ini_set('memory_limit', '512M');

// Logging (StdErr for Docker)
$wgDebugLogFile = "php://stderr";

// Display Errors (Off in prod/default, helpful to know it's controlled here)
ini_set('display_errors', 0);
$wgShowExceptionDetails = false;

// Cookie Secure (Auto-detect)
// $wgCookieSecure = 'detect'; // MW default is usually fine
