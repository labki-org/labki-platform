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

// --- Object Cache (APCu) ---

$wgMainCacheType = CACHE_ACCEL;
$wgMemCachedServers = [];

// --- Image Uploads ---

$wgEnableUploads = true;
$wgUploadDirectory = "{$IP}/images";
$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";

// --- Default Permissions ---

$wgGroupPermissions['*']['createaccount'] = false;
$wgGroupPermissions['*']['edit'] = false;
