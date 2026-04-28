<?php

// LocalSettings.base.php - Platform-owned base configuration
// This file is immutable and baked into the image.

// Protect against web entry
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
$wgMainCacheType = CACHE_DB;
$wgMemCachedServers = [];

// --- Image Uploads ---

$wgEnableUploads = true;
$wgUploadDirectory = "{$IP}/images";
$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";

// --- Default Permissions ---

$wgGroupPermissions['*']['createaccount'] = false;
$wgGroupPermissions['*']['edit'] = false;
