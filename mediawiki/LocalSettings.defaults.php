<?php

// LocalSettings.defaults.php - Platform-owned overridable defaults
//
// These are reasonable starting values for settings that legitimate
// users might want to change. Platform invariants (DB, cache backend,
// permissions, uploads paths) live in LocalSettings.base.php and are
// not expected to be overridden.

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

// File uploads: allow the common research-wiki set of document and
// data formats on top of MediaWiki's default image extensions. PHP's
// upload_max_filesize / post_max_size in docker/php/labki-tuning.ini
// must be at least as permissive as $wgMaxUploadSize for these to
// take effect.
$wgFileExtensions = array_merge( $wgFileExtensions ?? [], [
    'pdf', 'svg', 'csv', 'tsv', 'txt', 'md', 'json',
    'docx', 'xlsx', 'pptx', 'zip',
] );
$wgMaxUploadSize = 50 * 1024 * 1024; // 50 MiB

// Footer Badge - Powered by Labki
$wgFooterIcons['poweredby']['labki'] = [
    'src' => "$wgResourceBasePath/resources/assets/labki-badge.png",
    'url' => 'https://labki.org',
    'alt' => 'Powered by Labki',
    'height' => '31',
    'width' => '88',
];
