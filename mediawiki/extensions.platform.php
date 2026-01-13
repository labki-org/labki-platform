<?php
// extensions.platform.php - Curated Platform Extensions
if (!defined('MEDIAWIKI')) {
    exit;
}

// Semantic MediaWiki Ecosystem
// Note: Installed via Composer into vendor/ directory.
// We still need to call enableSemantics/wfLoadExtension to activate them in MW registry.

// Load SMW
wfLoadExtension('SemanticMediaWiki');
enableSemantics($wgServer); // Required to activate SMW

// SMW Satellites
wfLoadExtension('SemanticResultFormats');
// SemanticCompoundQueries might be autoloaded by SMW or Composer, but explicit load is safe if in vendor
wfLoadExtension('SemanticCompoundQueries');
wfLoadExtension('SemanticExtraSpecialProperties');

// Core/Utility Extensions
wfLoadExtension('PageForms');
wfLoadExtension('Maps');
wfLoadExtension('Mermaid');
wfLoadExtension('Bootstrap');

// Labki Extensions (Git Cloned into extensions/)
wfLoadExtension('MsUpload');
wfLoadExtension('PageSchemas');
wfLoadExtension('Lockdown');

// WikiForum
wfLoadExtension('WikiForum');
$wgWikiForumAllowAnonymous = false;

// Skins
wfLoadSkin('Citizen');
wfLoadSkin('chameleon');
$wgDefaultSkin = 'citizen';
