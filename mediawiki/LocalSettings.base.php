<?php

// LocalSettings.base.php - Platform-owned base configuration
// This file is immutable and baked into the image.

// Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

// 1. Database Configuration (From Env Vars)
// The platform contract ensures these env vars are available or utilize defaults.
$wgDBtype = 'mysql';
$wgDBserver = getenv('MW_DB_HOST') ?: 'db';
$wgDBname = getenv('MW_DB_NAME') ?: 'labki';
$wgDBuser = getenv('MW_DB_USER') ?: 'labki';
$wgDBpassword = getenv('MW_DB_PASSWORD') ?: '';

// 2. Site Identity (Defaults, usually overridden by install or user config)
// These defaults are critical for the first run before the DB is populated.
$wgSitename = getenv('MW_SITE_NAME') ?: 'Labki';
$wgMetaNamespace = "Project"; // Default
$wgScriptPath = getenv('MW_SCRIPT_PATH') ?: "/w";
$wgServer = getenv('MW_SERVER') ?: "http://localhost:8080";

// 3. Object Cache
// Use APCu by default if available (standard for single-container MW)
$wgMainCacheType = CACHE_ACCEL;
$wgMemCachedServers = [];

// 4. Image Uploads
$wgEnableUploads = true;
$wgUploadDirectory = "{$IP}/images";
$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";

// 5. Job Runner
// Default to running jobs on web requests unless we have a dedicated runner.
// In this platform, we usually run a separate jobrunner container, so we might want to set this to 0
// BUT, setting it to 0 without a runner configured breaks things.
// Safe default: let the user invoke the jobrunner script or set this to false in LocalSettings.user.php
$wgJobRunRate = 1; 

// 6. Permissions
$wgGroupPermissions['*']['createaccount'] = false;
$wgGroupPermissions['*']['edit'] = false;
