<?php
// skins.platform.php - Curated Platform Skins
//
// Loaded after extensions.platform.php so that any extension which queries
// the active skin (e.g. VisualEditor's supported-skins list) sees the
// platform skins as already registered.

if (!defined('MEDIAWIKI')) {
    exit;
}

// --- Skin loads ---
//
// Vector ships with MediaWiki and provides both the legacy ('vector') and
// current ('vector-2022') skins. We default to vector-2022 to match stock
// MediaWiki and surface standard navigation (sidebar with Special pages,
// history button, etc.) without any per-skin overrides.
wfLoadSkin('Vector');
wfLoadSkin('Citizen');
wfLoadSkin('Tweeki');

$wgDefaultSkin = 'vector-2022';

// --- Tweeki customization ---
//
// All $wgTweekiSkin* settings only take effect when Tweeki is the active
// skin, so they are no-ops for users on Vector/Citizen. Kept in place for
// users who have selected Tweeki as their preference.

// Register and load custom CSS
$wgResourceModules['skin.labki.tweeki.styles'] = [
    'styles' => [ 'resources/styles/labki-tweeki.css' ],
    'localBasePath' => $IP,
    'remoteBasePath' => $wgResourceBasePath,
];
$wgTweekiSkinCustomCSS[] = 'skin.labki.tweeki.styles';

// Register and load custom JS. Polls the Echo notifications count and
// decorates the user dropdown toggle with an unread badge so logged-in
// users see at-a-glance whether they have notifications without opening
// the dropdown.
$wgResourceModules['skin.labki.tweeki.scripts'] = [
    'scripts' => [ 'resources/scripts/labki-tweeki.js' ],
    'dependencies' => [ 'mediawiki.api', 'mediawiki.user', 'skins.tweeki.scripts' ],
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

// Register message overrides for Tweeki's PERSONAL fallback path.
// TweekiTemplate.php (case 'PERSONAL') checks the OUTER ptool for
// `text`; if missing, it falls back to `wfMessage($key)->text()`.
// MediaWiki's getPersonalToolsForMakeListItem hoists `text` into
// `links[0]`, so the outer level Echo populates is bare by the time
// Tweeki sees it. Defining `notifications-alert` and `notifications-
// notice` messages gives Tweeki's fallback a real string to render
// instead of the raw-key marker `⟨notifications-alert⟩`.
$wgMessagesDirs['LabkiPlatform'] = __DIR__ . '/i18n';

// Echo hands the Notices entry an OOUI icon name `tray` which Tweeki
// blindly emits as `<span class="fa fa-tray">`. FontAwesome Free
// doesn't ship a `fa-tray` glyph, so the span renders empty. We give
// `fa-tray` the inbox glyph in resources/styles/labki-tweeki.css —
// see the comment there.

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

// Tweeki's default navigation does not surface a Special Pages link, so add
// one for logged-in users. Anonymous users would just hit a login wall.
$wgTweekiSkinNavigationalElements['SPECIALPAGES'] = function ( $skin, $context ) {
    if ( $skin->getSkin()->getUser()->isAnon() ) {
        return [];
    }
    return [
        [
            'text' => wfMessage( 'specialpages' )->text(),
            'href' => SpecialPage::getTitleFor( 'Specialpages' )->getLocalURL(),
            'id' => 'pt-specialpages',
        ],
    ];
};

// Light/dark theme toggle. The actual theme switch is driven by JS in
// labki-tweeki.js (toggles `<html data-bs-theme>` and persists the
// choice in localStorage). The button starts with a moon icon; the
// shim swaps to a sun when dark mode is active. Bootstrap 5.3 styles
// most components via `data-bs-theme`; the labki-tweeki.css overrides
// the `--labki-*` palette under `[data-bs-theme="dark"]`.
//
// Inject a tiny <script> at the top of <head> that applies the stored
// theme synchronously before paint, so users with dark preference
// don't see a flash of light content while ResourceLoader catches up.
$wgHooks['BeforePageDisplay'][] = static function ( $out, $skin ) {
    if ( strtolower( $skin->getSkinName() ) !== 'tweeki' ) {
        return;
    }
    $out->addHeadItem(
        'labki-theme-init',
        "<script>(function(){try{var t=localStorage.getItem('labki-theme');"
        . "if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){t='dark';}"
        . "if(t==='dark'){document.documentElement.setAttribute('data-bs-theme','dark');}}"
        . "catch(e){}})();</script>"
    );
};
$wgTweekiSkinNavigationalElements['LABKI-THEME-TOGGLE'] = function ( $skin, $context ) {
    return [ [
        'text'  => '',
        'href'  => '#',
        'id'    => 'labki-theme-toggle',
        'icon'  => 'moon',
        'title' => wfMessage( 'labki-toggle-theme' )->text(),
    ] ];
};

// Override navbar-right to include our custom elements before PERSONAL and search
$wgTweekiSkinCustomNav['navbar-right'] = 'LABKI-LOGIN,SPECIALPAGES,PERSONAL,LABKI-THEME-TOGGLE,SEARCH';

// Hide footer metadata (MW version info) from everyone
$wgTweekiSkinHideAll = [
    'footer-info' => true,
];

// Tweeki hides the footer-custom block from logged-in users by default.
// Override that — our default `tweeki-footer-custom` message renders a
// "Powered by Labki Platform" attribution that should be visible site-
// wide. Operators can blank `MediaWiki:Tweeki-footer-custom` to remove
// it, or replace with their own wikitext list.
$wgTweekiSkinHideLoggedin = [];

// Show real names in user links (academic context)
$wgTweekiSkinUseRealnames = true;

// Use pencil icon for edit-section links
$wgTweekiSkinCustomEditSectionLink = true;
