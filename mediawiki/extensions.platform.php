<?php
// extensions.platform.php - Curated Platform Extensions
if (!defined('MEDIAWIKI')) {
    exit;
}

// --- Semantic MediaWiki Ecosystem (Composer-installed) ---

wfLoadExtension('SemanticMediaWiki');
enableSemantics($wgServer);
$smwgShowFactbox = SMW_FACTBOX_NONEMPTY;

wfLoadExtension('SemanticResultFormats');
wfLoadExtension('SemanticCompoundQueries');
wfLoadExtension('SemanticExtraSpecialProperties');

// --- Core/Utility Extensions (Composer-installed) ---

wfLoadExtension('PageForms');
wfLoadExtension('Maps');
wfLoadExtension('Mermaid');
wfLoadExtension('Bootstrap');

// --- Git-cloned Extensions ---

wfLoadExtension('MsUpload');
wfLoadExtension('Lockdown');

// --- Bundled MediaWiki Extensions (shipped with MW 1.44) ---

wfLoadExtension('Echo');
wfLoadExtension('Linter');
wfLoadExtension('VisualEditor');
$wgDefaultUserOptions['visualeditor-editor'] = "visualeditor";
wfLoadExtension('DiscussionTools');

wfLoadExtension('ConfirmEdit');
$wgCaptchaClass = 'SimpleCaptcha';

wfLoadExtension('WikiForum');
$wgWikiForumAllowAnonymous = false;
$wgCaptchaTriggers['wikiforum'] = false;

wfLoadExtension('ConfirmAccount');

// --- Permissions ---

$wgGroupPermissions['*']['read']            = true;
$wgGroupPermissions['*']['createaccount']   = false;
$wgGroupPermissions['*']['edit']            = false;
$wgGroupPermissions['*']['writeapi']        = false;
$wgGroupPermissions['*']['createpage']      = false;
$wgGroupPermissions['*']['createtalk']      = false;

$wgGroupPermissions['bureaucrat']['createaccount'] = true;

// --- Skins ---

wfLoadSkin('Citizen');
wfLoadSkin('chameleon');
$wgDefaultSkin = 'citizen';
