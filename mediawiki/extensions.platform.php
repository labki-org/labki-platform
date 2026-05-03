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

// --- Bundled MediaWiki Extensions (shipped with MW 1.44) ---

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
