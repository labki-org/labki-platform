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
// Tweeki is the curated labki experience: it carries platform-specific
// chrome (labki-tweeki.css/js — navbar, footer, theme toggle, sidebar
// drawer, page-actions relocation, login-state classes, custom nav
// elements like LABKI-LOGIN and LABKI-THEME-TOGGLE), so anonymous
// viewers and new accounts land here by default.
//
// Vector ships with MediaWiki (legacy 'vector' and current 'vector-2022')
// and Citizen is loaded too — both stay available for users who prefer a
// different skin via Special:Preferences. Existing users with a saved
// skin preference are unaffected; only the anon/new-account default
// changes here.
wfLoadSkin('Vector');
wfLoadSkin('Citizen');
wfLoadSkin('Tweeki');

$wgDefaultSkin = 'tweeki';

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

// Surface Tweeki's EDIT-EXT split-button dropdown for everyone. By default
// Tweeki gates EDIT-EXT-special on the per-user `tweeki-advanced` pref, so
// the dropdown — which carries Edit source, View history, Move, Delete,
// Watch, Add category (from SemanticSchemas), etc. — is hidden for non-
// "advanced" users. Unhide it globally so the rich dropdown is the default.
//
// Done via $wgExtensionFunctions because Tweeki's skin.json config is
// applied by ExtensionRegistry *after* LocalSettings runs, so a plain
// reassignment here would just be overwritten back to the skin default.
// Using `unset` is targeted: it pulls only the `EDIT-EXT-special` key
// without disturbing any other entries an admin may add to this array.
// We also can't just flip $wgDefaultUserOptions['tweeki-advanced'] — any
// user who's already touched their preferences page has an empty value
// stored in user_properties that takes precedence over the default.
$wgExtensionFunctions[] = static function () {
    unset( $GLOBALS['wgTweekiSkinHideNonAdvanced']['EDIT-EXT-special'] );
};

// Hide Tweeki's edit button for users who can't edit the current page (anon
// users on a private wiki, viewer-only groups, protected pages, etc.). By
// default Tweeki swaps `edit` for `viewsource` and shows a "View source"
// button — useful on Wikipedia, but in our context it's a dead end that
// just prompts a login wall, so we drop the whole element. Admins who can
// edit see the full dropdown unchanged.
$wgHooks['SkinTweekiCheckVisibility'][] = static function ( $template, $item ) {
    if ( $item !== 'EDIT' && $item !== 'EDIT-EXT' && $item !== 'EDIT-EXT-special' ) {
        return true;
    }
    $skin = $template->getSkin();
    $title = $skin->getTitle();
    if ( $title === null ) {
        return true;
    }
    $pm = MediaWiki\MediaWikiServices::getInstance()->getPermissionManager();
    return $pm->userCan( 'edit', $skin->getUser(), $title );
};

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

// Customize the user dropdown for logged-in Tweeki users:
//  1. Surface a Special Pages link (Tweeki's default has none, and we
//     pulled it out of navbar-right). Insert after `mytalk` so it lands
//     in the top group with userpage and Echo's Notices/Alerts, before
//     Tweeki's divider that precedes `preferences`.
//  2. Replace the OOUI icon names MediaWiki sets by default (e.g.
//     `userAvatar`, `userTalk`, `settings`) with FontAwesome names.
//     Tweeki emits `<span class="fa fa-{icon}"></span>` and ships FA 5
//     Free Solid, which has no glyphs for the OOUI names — so without
//     overriding, every entry renders as a blank span. Scoped to Tweeki
//     because Vector 2022 / Citizen render those OOUI names via their
//     own sprites and would lose their icons if we clobbered them.
// Anonymous users would just hit a login wall and PERSONAL is hidden
// for them via $wgTweekiSkinHideAnon, so skip them entirely.
$wgHooks['SkinTemplateNavigation::Universal'][] = static function ( $sktemplate, &$links ) {
    if ( strtolower( $sktemplate->getSkinName() ) !== 'tweeki' ) {
        return;
    }
    if ( $sktemplate->getUser()->isAnon() ) {
        return;
    }
    $specialpages = [
        'text' => wfMessage( 'specialpages' )->text(),
        'href' => SpecialPage::getTitleFor( 'Specialpages' )->getLocalURL(),
        'id'   => 'pt-specialpages',
        'icon' => 'list',
    ];
    if ( isset( $links['user-menu']['mytalk'] ) ) {
        $links['user-menu'] = wfArrayInsertAfter(
            $links['user-menu'],
            [ 'specialpages' => $specialpages ],
            'mytalk'
        );
    } else {
        $links['user-menu']['specialpages'] = $specialpages;
    }

    $iconMap = [
        'userpage'    => 'user',
        'mytalk'      => 'comments',
        'preferences' => 'cog',
        'watchlist'   => 'star',
        'mycontris'   => 'history',
        'logout'      => 'sign-out-alt',
    ];
    foreach ( $iconMap as $key => $icon ) {
        if ( isset( $links['user-menu'][$key] ) ) {
            $links['user-menu'][$key]['icon'] = $icon;
        }
    }
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
$wgTweekiSkinCustomNav['navbar-right'] = 'LABKI-LOGIN,PERSONAL,LABKI-THEME-TOGGLE,SEARCH';

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
