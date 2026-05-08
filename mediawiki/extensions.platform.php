<?php
// extensions.platform.php - Curated Platform Extensions
//
// Ordering note: ConfirmEdit must load before WikiForum and ConfirmAccount
// so that captcha trigger settings are picked up when those extensions
// register their forms. Skins are loaded in skins.platform.php (after this
// file) and site-wide permissions live in LocalSettings.base.php.

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

// --- Git-cloned Extensions ---

wfLoadExtension('MsUpload');
wfLoadExtension('Lockdown');

// PageForms add-on: registers labki-datetime / labki-date / labki-time
// input types backed by flatpickr. Must load after PageForms (above) so
// the FormPrinterSetup hook fires correctly. To make it the wiki-wide
// default for all SMW Date properties, set in LocalSettings.user.php:
//   $wgSemanticSchemasDatatypeInputOverrides['Date'] = 'labki-datetime';
wfLoadExtension('LabkiPageFormsInputs');

// --- Bundled MediaWiki Extensions (shipped with MW 1.44) ---

wfLoadExtension('CategoryTree');
wfLoadExtension('Echo');
wfLoadExtension('Linter');
wfLoadExtension('SyntaxHighlight_GeSHi');

// TemplateStyles lets templates ship sanitized, scoped CSS via
// <templatestyles src="Template:Foo/styles.css" /> without needing
// site-wide editsitecss rights. Schema bundles (e.g. SchemaSync) use
// this to package per-template CSS alongside their templates.
wfLoadExtension('TemplateStyles');

wfLoadExtension('VisualEditor');
$wgDefaultUserOptions['visualeditor-editor'] = 'visualeditor';
// MediaWiki's default $wgVisualEditorSupportedSkins covers
// vector / vector-2022 / monobook / minerva. Opt in our other
// platform skins so users on Citizen or Tweeki get the VE edit
// tab instead of falling back to source mode.
$wgVisualEditorSupportedSkins[] = 'citizen';
$wgVisualEditorSupportedSkins[] = 'tweeki';

wfLoadExtension('DiscussionTools');

// --- Labki Forum: forum-style discussion topics on top of DiscussionTools ---
//
// Drop a "new topic" entry point on any page. Two equivalent ways:
//
//   * Bundled look (uses the styling shipped in labki-forum.less):
//     <span class="labki-forum-new-post-btn" role="button" tabindex="0">Make new post</span>
//
//   * Bring your own UI (any element with the data attribute is a hook):
//     <button class="my-button" data-labki-forum-new-post>Start a discussion</button>
//
// On click, labki-forum.js derives the talk-namespace counterpart of the
// current page (e.g. Forum:Miniscopes -> Forum_talk:Miniscopes), generates
// a <UTC-timestamp>_<username> slug, and sends the user to the DT new-
// topic widget at action=edit&section=new on that fresh subpage.
//
// labki-forum.less also tags Talk-namespace subpages (.labki-forum-topic
// on <html>) so they render as forum cards: outer frame, accent-bar first
// post, indented reply cards, button-styled reply links, and the
// page-title bar / TOC / DT page-frame chrome hidden as redundant on a
// single-topic-per-page layout.
//
// Enables DT's visual enhancements default-on so the section bar (comment
// count + latest activity) renders without per-user opt-in.
$wgDiscussionTools_visualenhancements = 'available';
$wgDefaultUserOptions['discussiontools-visualenhancements'] = 1;

$wgResourceModules['ext.labki.forum'] = [
    'styles'         => [ 'resources/styles/labki-forum.less' ],
    'scripts'        => [ 'resources/scripts/labki-forum.js' ],
    'dependencies'   => [ 'mediawiki.util', 'mediawiki.Title', 'mediawiki.notify' ],
    'localBasePath'  => $IP,
    'remoteBasePath' => $wgResourceBasePath,
];
$wgHooks['BeforePageDisplay'][] = static function ( $out, $skin ) {
    $out->addModules( [ 'ext.labki.forum' ] );
};

wfLoadExtension('ConfirmEdit');
$wgCaptchaClass = 'SimpleCaptcha';

// Captchas only guard account-creation flows (ConfirmAccount requests, signup,
// failed logins). Editing pages — including edits that add external links —
// must never show a captcha to logged-in users.
$wgCaptchaTriggers['edit']            = false;
$wgCaptchaTriggers['create']          = false;
$wgCaptchaTriggers['addurl']          = false;
$wgCaptchaTriggers['sendemail']       = false;
$wgCaptchaTriggers['createaccount']   = true;
$wgCaptchaTriggers['badlogin']        = true;
$wgCaptchaTriggers['badloginperuser'] = true;

wfLoadExtension('WikiForum');
$wgWikiForumAllowAnonymous = false;

wfLoadExtension('ConfirmAccount');

// Workaround: ConfirmAccount renders OOUI forms before the skin sets the
// theme, which raises "Cannot use object of type ... as singleton" from
// \OOUI\Theme::singleton(). We pre-initialize the singleton with the
// stock WikimediaUITheme. Narrow the catch to the missing-singleton case
// and log anything else so an unrelated regression doesn't go silent.
$wgHooks['SetupAfterCache'][] = static function () {
    try {
        \OOUI\Theme::singleton();
    } catch ( \RuntimeException $e ) {
        if ( strpos( $e->getMessage(), 'singleton' ) !== false ) {
            \OOUI\Theme::setSingleton( new \OOUI\WikimediaUITheme() );
            return;
        }
        wfLogWarning( 'OOUI theme init: unexpected RuntimeException: ' . $e->getMessage() );
        throw $e;
    }
};
