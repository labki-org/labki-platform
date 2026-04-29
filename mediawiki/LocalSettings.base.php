<?php

// LocalSettings.base.php - Platform-owned base configuration
//
// This file holds settings that the platform treats as invariants:
// database connection, site identity, object cache backend, upload
// paths, and the private-by-default permission baseline. It is loaded
// first by bootstrap.php and is not expected to be overridden by users.
// Per-user-overridable preferences (timezone, memory, file extensions,
// branding, etc.) live in LocalSettings.defaults.php instead.

if (!defined('MEDIAWIKI')) {
    exit;
}

// --- Database Configuration ---

$wgDBtype = 'mysql';
$wgDBserver = getenv('MW_DB_HOST') ?: 'db';
$wgDBname = getenv('MW_DB_NAME') ?: 'labki';
$wgDBuser = getenv('MW_DB_USER') ?: 'labki';
$wgDBpassword = getenv('MW_DB_PASSWORD') ?: '';

// --- Site Identity ---

$wgSitename = getenv('MW_SITE_NAME') ?: 'Labki';
$wgMetaNamespace = "Project";
$wgScriptPath = getenv('MW_SCRIPT_PATH') ?: "";
$wgArticlePath = getenv('MW_ARTICLE_PATH') ?: "/wiki/$1";
$wgServer = getenv('MW_SERVER') ?: "http://localhost:8080";

// --- Object Cache ---
//
// CACHE_DB routes through the MediaWiki object cache table on the same
// MariaDB instance. It's durable across worker restarts and consistent
// between web workers and CLI maintenance scripts (runJobs, rebuildData).
// CACHE_ACCEL (APCu) is faster per-op but splits state across SAPIs:
// CLI maintenance scripts and Apache workers see different caches, which
// produces hard-to-diagnose inconsistencies in MW + SMW workloads.
// Revisit if/when Redis or Memcached is added to the deployment.
//
// All caches are pinned to CACHE_DB explicitly so the choice doesn't
// silently fall through $wgMainCacheType for some subsystems and not
// others. SMW caches inherit via their CACHE_ANYTHING defaults, which
// resolve through $wgMainCacheType.
$wgMainCacheType    = CACHE_DB;
$wgSessionCacheType = CACHE_DB;
$wgMessageCacheType = CACHE_DB;
$wgParserCacheType  = CACHE_DB;
$wgMemCachedServers = [];

// --- Image Uploads ---

$wgEnableUploads = true;
$wgUploadDirectory = "{$IP}/images";
$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";

// --- Permissions: private wiki by default ---
//
// To make a wiki public, override `$wgGroupPermissions['*']['read'] = true;`
// in /mw-config/LocalSettings.user.php. New-account creation is gated
// behind ConfirmAccount: anonymous visitors cannot self-create accounts;
// bureaucrats approve requests via Special:ConfirmAccounts.

$wgGroupPermissions['*']['read']            = false;
$wgGroupPermissions['*']['createaccount']   = false;
$wgGroupPermissions['*']['edit']            = false;
$wgGroupPermissions['*']['writeapi']        = false;
$wgGroupPermissions['*']['createpage']      = false;
$wgGroupPermissions['*']['createtalk']      = false;

$wgGroupPermissions['bureaucrat']['createaccount'] = true;

$wgWhitelistRead = [
    'Special:UserLogin',
    'Special:CreateAccount',
    'Special:RequestAccount',
    'Special:PasswordReset',
    'Main Page',
];
