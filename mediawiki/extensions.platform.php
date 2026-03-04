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

// Workaround: ConfirmAccount renders OOUI forms before the skin sets the theme.
// Ensure the OOUI theme singleton is initialized early to prevent RuntimeException.
$wgHooks['SetupAfterCache'][] = static function () {
    try {
        \OOUI\Theme::singleton();
    } catch ( \RuntimeException $e ) {
        \OOUI\Theme::setSingleton( new \OOUI\WikimediaUITheme() );
    }
};

// --- Permissions (private wiki by default) ---
// To make your wiki public, add $wgGroupPermissions['*']['read'] = true;
// to your LocalSettings.user.php

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

// --- Skins ---

wfLoadSkin('Citizen');
wfLoadSkin('chameleon');
wfLoadSkin('Tweeki');
$wgDefaultSkin = 'tweeki';

// --- Labki Tweeki Defaults ---

// Register and load custom CSS
$wgResourceModules['skin.labki.tweeki.styles'] = [
    'styles' => [ 'resources/styles/labki-tweeki.css' ],
    'localBasePath' => $IP,
    'remoteBasePath' => $wgResourceBasePath,
];
$wgTweekiSkinCustomCSS[] = 'skin.labki.tweeki.styles';

// Register and load custom JS (notification badges, etc.)
$wgResourceModules['skin.labki.tweeki.scripts'] = [
    'scripts' => [ 'resources/scripts/labki-tweeki.js' ],
    'dependencies' => [ 'mediawiki.api', 'skins.tweeki.scripts' ],
    'localBasePath' => $IP,
    'remoteBasePath' => $wgResourceBasePath,
];
$wgTweekiSkinCustomScriptModule = 'skin.labki.tweeki.scripts';

// Full-width content when no sidebars are active
$wgTweekiSkinGridNone = [
    'mainoffset' => 0,
    'mainwidth'  => 12,
];

// Disable footer icons (text links are cleaner; Labki badge still renders via $wgFooterIcons)
$wgTweekiSkinFooterIcons = false;

// Enable Bootstrap tooltips
$wgTweekiSkinUseTooltips = true;

// Hide UI clutter from anonymous users (private wiki context)
$wgTweekiSkinHideAnon = [
    'subnav'   => true,
    'PERSONAL' => true,
    'TOOLBOX'  => true,
];

// Custom navbar element: prominent "Log in" and "Request Account" buttons for anon users.
// Tweeki's PERSONAL element has text-rendering bugs with login-private + createaccount,
// so we bypass it with a clean custom element and hide PERSONAL for anon (above).
$wgTweekiSkinNavigationalElements['LABKI-LOGIN'] = function ( $skin, $context ) {
    if ( !$skin->getSkin()->getUser()->isAnon() ) {
        return [];
    }
    $returnto = $skin->getSkin()->getTitle()->getPrefixedDBkey();
    return [
        [
            'text' => wfMessage( 'login' )->text(),
            'href' => SpecialPage::getTitleFor( 'Userlogin' )->getLocalURL( [ 'returnto' => $returnto ] ),
            'id' => 'pt-login-private',
        ],
        [
            'text' => wfMessage( 'requestaccount' )->text(),
            'href' => SpecialPage::getTitleFor( 'RequestAccount' )->getLocalURL(),
            'id' => 'pt-createaccount',
        ],
    ];
};

// Override navbar-right to include our login element before PERSONAL and search
$wgTweekiSkinCustomNav['navbar-right'] = 'LABKI-LOGIN,PERSONAL,SEARCH';

// Hide footer metadata (MW version info) from everyone
$wgTweekiSkinHideAll = [
    'footer-info' => true,
];

// Show real names in user links (academic context)
$wgTweekiSkinUseRealnames = true;

// Use pencil icon for edit-section links
$wgTweekiSkinCustomEditSectionLink = true;
