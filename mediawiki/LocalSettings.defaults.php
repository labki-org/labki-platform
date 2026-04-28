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

// Logging (stderr for Docker log collection)
$wgDebugLogFile = "php://stderr";

// Display Errors
ini_set('display_errors', 0);
$wgShowExceptionDetails = false;

// Job Queue - defer to background runner (avoids UI lag).
// The labki deployment repo ships a `wiki-jobrunner` container that
// drains the queue continuously; user-facing requests do not run jobs.
// If you disable that container, restore $wgJobRunRate = 1 in your
// user config so saves still process eventually (at the cost of
// slower UX).
$wgJobRunRate = 0;

// Cache routing.
//
// All caches route to CACHE_DB to match $wgMainCacheType (set in
// LocalSettings.base.php). Being explicit protects against surprises if
// a user override flips $wgMainCacheType to something a given subsystem
// can't use. SMW caches are left at their defaults (CACHE_ANYTHING),
// which resolves through $wgMainCacheType.
$wgSessionCacheType = CACHE_DB;
$wgMessageCacheType = CACHE_DB;
$wgParserCacheType  = CACHE_DB;

// Footer Badge - Powered by Labki
$wgFooterIcons['poweredby']['labki'] = [
    'src' => "$wgResourceBasePath/resources/assets/labki-badge.png",
    'url' => 'https://labki.org',
    'alt' => 'Powered by Labki',
    'height' => '31',
    'width' => '88',
];
