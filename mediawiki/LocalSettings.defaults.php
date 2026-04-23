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
// $wgMainCacheType is set in LocalSettings.base.php to CACHE_ACCEL
// (APCu). Route session and message caches to the same fast in-process
// store. ParserCache entries can be multi-MB and benefit from
// durability across worker restarts, so keep it on the database.
$wgSessionCacheType = CACHE_ACCEL;
$wgMessageCacheType = CACHE_ACCEL;
$wgParserCacheType  = CACHE_DB;

// SMW caches default to CACHE_ANYTHING which resolves through
// $wgMainCacheType anyway, but making it explicit is helpful for ops
// and protects against surprises if a user overrides $wgMainCacheType
// to something SMW's CompositeCache can't use.
$smwgMainCacheType = CACHE_ACCEL;
$smwgQueryResultCacheType = CACHE_ACCEL;

// Footer Badge - Powered by Labki
$wgFooterIcons['poweredby']['labki'] = [
    'src' => "$wgResourceBasePath/resources/assets/labki-badge.png",
    'url' => 'https://labki.org',
    'alt' => 'Powered by Labki',
    'height' => '31',
    'width' => '88',
];
