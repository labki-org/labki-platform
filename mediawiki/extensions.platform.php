<?php

// extensions.platform.php - Platform-curated extension set
// This file loads the extensions that are verified and bundled with the platform.

if (!defined('MEDIAWIKI')) {
    exit;
}

// Semantic MediaWiki Ecosystem
// Note: Composer autoploader has already been included by MediaWiki core (via entrypoint/WebStart)
// IF we use the standard composer install. However, for some extensions we might need explicit enables.
// SMW usually requires 'enableSemantics'.

// Load SMW
wfLoadExtension('SemanticMediaWiki');
enableSemantics($wgServer); // Default to $wgServer

// SMW Satellites
wfLoadExtension('SemanticResultFormats');
wfLoadExtension('SemanticCompoundQueries');
wfLoadExtension('SemanticExtraSpecialProperties');

// Core/Utility Extensions
wfLoadExtension('PageForms');
wfLoadExtension('Maps');
wfLoadExtension('Mermaid');
wfLoadExtension('Bootstrap'); // From bootstrap extension

// Labki Extensions
wfLoadExtension('MsUpload');
wfLoadExtension('PageSchemas');
wfLoadExtension('Lockdown');
wfLoadExtension('LabkiPackManager');

// Skins
wfLoadSkin('Citizen');
$wgDefaultSkin = 'citizen';
