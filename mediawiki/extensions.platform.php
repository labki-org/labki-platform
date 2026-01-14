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

wfLoadExtension( 'DiscussionTools' );

wfLoadExtension( 'ConfirmEdit' );
$wgGroupPermissions['*']['edit'] = false;

// WikiForum
wfLoadExtension('WikiForum');
$wgWikiForumAllowAnonymous = false;
$wgCaptchaTriggers['wikiforum'] = false;


wfLoadExtension('AccessControl');
$wgGroupPermissions['*']['read']            = true;
$wgGroupPermissions['*']['createaccount']   = false;
$wgGroupPermissions['*']['edit']            = false;
$wgGroupPermissions['*']['writeapi']        = false;
$wgGroupPermissions['*']['createpage']      = false;
$wgGroupPermissions['*']['createtalk']      = false;

wfLoadExtension( 'ConfirmAccount' );
$wgGroupPermissions['*']['createaccount'] = false; // REQUIRED to enforce account requests via this extension
$wgGroupPermissions['bureaucrat']['createaccount'] = true; // optional to allow account creation by this trusted user group

// Skins
wfLoadSkin('Citizen');
wfLoadSkin('chameleon');
$wgDefaultSkin = 'citizen';
