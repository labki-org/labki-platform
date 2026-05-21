<?php
// extensions.platform.php - Curated Platform Extensions
//
// Ordering note: ConfirmEdit must load before ConfirmAccount so that
// captcha trigger settings are picked up when ConfirmAccount registers
// its account-request form. Skins are loaded in skins.platform.php
// (after this file) and site-wide permissions live in LocalSettings.base.php.

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
// See resources/scripts/labki-forum.js for the click-handler entry points
// and resources/styles/labki-forum.less for the topic-page styling.

$wgDiscussionTools_visualenhancements = 'available';
$wgDefaultUserOptions['discussiontools-visualenhancements'] = 1;

// Split modules: styles via addModuleStyles land in a synchronous <head>
// <link>, eliminating FOUC on first paint; the click handler can load async.
$wgResourceModules['ext.labki.forum.styles'] = [
    'styles'         => [ 'resources/styles/labki-forum.less' ],
    'localBasePath'  => $IP,
    'remoteBasePath' => $wgResourceBasePath,
];
$wgResourceModules['ext.labki.forum'] = [
    'scripts'        => [ 'resources/scripts/labki-forum.js' ],
    'dependencies'   => [ 'mediawiki.util', 'mediawiki.Title', 'mediawiki.notification' ],
    'localBasePath'  => $IP,
    'remoteBasePath' => $wgResourceBasePath,
];

// Register custom SMW properties for forum topic pages so #ask queries can
// build a forum-style index (subject, OP, comment count, participants).
//
// Adding a property here is a schema change for the SQL store: existing
// installs must run `php extensions/SemanticMediaWiki/maintenance/setupStore.php`
// once after deploy, then `rebuildData.php -n <ns>` to backfill saved pages.
$wgHooks['SMW::Property::initProperties'][] = static function ( $propertyRegistry ) {
    $propertyRegistry->registerProperty( '___forum_subject',      '_txt', 'Topic subject' );
    $propertyRegistry->registerProperty( '___forum_starter',      '_wpg', 'Topic starter' );
    $propertyRegistry->registerProperty( '___forum_comments',     '_num', 'Comment count' );
    $propertyRegistry->registerProperty( '___forum_participants', '_num', 'Participant count' );
    // Has forum (`_wpg`, page-typed) points each post page back at its
    // containing forum landing (the subject-namespace counterpart of the
    // post's base title). Enables chained queries like
    // `[[Has forum.Has parent forum::Forum:Home]]` for activity feeds on
    // hub pages — see SchemaSync's Category:Forum template.
    $propertyRegistry->registerProperty( '___forum_parent',       '_wpg', 'Has forum' );
    return true;
};

// Job: re-annotate a forum topic's DT-derived SMW properties (Comment
// count, Participant count, Topic starter) after each save.
//
// We can't compute these synchronously in ParserAfterParse — DT's
// CommentParser needs final Parsoid HTML, which isn't available inside
// the parser pipeline. So the flow is:
//
//   PageSaveComplete  →  push LabkiForumDTAnnotateJob
//   Job::run()        →  parseRevisionParsoidHtml() via DT
//                     →  cache {count, people, starter} keyed by rev id
//                     →  run RefreshLinksJob inline to re-parse the page
//   ParserAfterParse  →  reads cache, contributes the 3 props through
//                        SMW's normal parser-data channel
//   SMW LinksUpdate   →  stores the full bundle (title-derived + DT-
//                        derived) in one atomic write
//
// Routing through the parser channel (rather than calling
// Store::updateData directly) avoids data loss: any subsequent re-parse
// — manual purge, parser cache eviction, RefreshLinksJob from another
// extension — also reads the stash and re-emits the same DT-derived
// values, so SMW's stored data stays consistent for the lifetime of
// the revision.
//
// Counts are exactly what DT itself displays in its bell / subscription
// UI: locale-correct (handles non-UTC $wgLocaltimezone, custom
// signatures, transcluded comments, fully-localized month names).
class LabkiForumDTAnnotateJob extends \Job {
    public function __construct( $title, $params = [] ) {
        parent::__construct( 'labkiForumDTAnnotate', $title, $params );
        // Two saves on the same topic can land back-to-back (OP saves,
        // then immediately replies). Deduplicate so the queue carries
        // at most one pending re-annotation per page.
        $this->removeDuplicates = true;
    }

    public function run() {
        $title = $this->getTitle();
        if ( !$title ) {
            return true;
        }

        $services = \MediaWiki\MediaWikiServices::getInstance();
        $rev = $services->getRevisionLookup()->getRevisionByTitle( $title );
        if ( !$rev ) {
            return true;
        }

        $status = \MediaWiki\Extension\DiscussionTools\Hooks\HookUtils
            ::parseRevisionParsoidHtml( $rev, __METHOD__ );
        if ( !$status->isOK() ) {
            // Parsoid resource-limit or similar transient failure — leave
            // the DT-derived properties absent for this revision; the
            // next save will retry.
            return true;
        }

        $threads = $status->getValue()->getThreads();
        if ( !$threads ) {
            // Empty topic (no H2, no comments yet) — nothing to annotate.
            return true;
        }
        $heading = $threads[0];

        $oldestReply = $heading->getOldestReply();
        $payload = [
            'count'   => (int)$heading->getCommentCount(),
            'people'  => count( $heading->getAuthorsBelow() ),
            'starter' => $oldestReply ? (string)$oldestReply->getAuthor() : '',
        ];

        $stash = $services->getMainObjectStash();
        $key = $stash->makeKey(
            'labki-forum-dt',
            $title->getArticleID(),
            $rev->getId()
        );
        // TTL_MONTH covers normal parser-cache eviction windows; on the
        // rare cache miss past expiry, the next save (which always
        // re-enqueues this job) repopulates.
        $stash->set( $key, $payload, $stash::TTL_MONTH );

        // Force SMW to re-store the page with the fresh DT data flowing
        // through ParserAfterParse. RefreshLinksJob re-renders, which
        // runs our hook (now with a cache hit) and SMW's LinksUpdate.
        ( new \RefreshLinksJob( $title, [] ) )->run();

        return true;
    }
}
$wgJobClasses['labkiForumDTAnnotate'] = LabkiForumDTAnnotateJob::class;

// Forum-topic save → schedule DT annotation. Detection is the same as
// the ParserAfterParse hook: odd-namespace (talk-side) subpage.
$wgHooks['PageSaveComplete'][] = static function ( $wikiPage, $user, $summary, $flags, $rev ) {
    $title = $wikiPage->getTitle();
    if ( !$title || ( $title->getNamespace() % 2 ) !== 1
        || strpos( $title->getDBkey(), '/' ) === false
    ) {
        return;
    }
    \MediaWiki\MediaWikiServices::getInstance()->getJobQueueGroup()
        ->push( new LabkiForumDTAnnotateJob( $title, [] ) );
};

// On forum topic pages, populate forum metadata via the parser channel:
//   - Has forum         (title-derived, always set)
//   - Topic subject     (first H2)
//   - Comment count     (from DT cache stashed by LabkiForumDTAnnotateJob)
//   - Participant count (ditto)
//   - Topic starter     (ditto)
// Also mirror the subject into DISPLAYTITLE so browser tabs and inbound
// wikilinks render the human-readable subject instead of the
// <UTC>_<user> slug.
//
// === Why ParserAfterParse, not ContentAlterParserOutput ===
// SMW's ParserAfterTidy reads page properties (e.g. displaytitle) before
// the Content layer fires; ParserAfterParse runs earlier in the parser
// pipeline and gets the values into ParserOutput in time for SMW.
//
// === Why a cache + RefreshLinksJob for the DT-derived bundle ===
// DT's CommentParser needs final Parsoid HTML, which isn't available at
// this hook. The job (LabkiForumDTAnnotateJob, above) does the Parsoid
// render after save, caches the result, and triggers a re-parse. This
// hook then becomes a single channel for all forum properties — any
// future re-parse (manual purge, parser-cache eviction) reads the same
// cache and re-emits the same DT-derived values, so SMW's stored data
// stays consistent regardless of which path retriggered the parse.
//
// === Caveats / known limits ===
// 1. Counts include the OP — a fresh topic with no replies reads as
//    "1 comment, 1 participant", matching Discourse-style conventions.
//    If a strict "replies" count is wanted, subtract 1.
// 2. Brief window between save and DT job completion where the page
//    has Has forum + Topic subject but no DT-derived counts. Resolves
//    on the next jobrunner cycle (~seconds in production with the
//    bundled jobrunner sidecar). The forum index shows the topic as
//    "0 replies" during this window.
// 3. If Parsoid fails (resource limit, etc.) the DT-derived bundle is
//    skipped silently for that revision; the next save retries.
$wgHooks['ParserAfterParse'][] = static function ( $parser, &$text, $stripState ) {
    $title = $parser->getTitle();
    if ( !$title || ( $title->getNamespace() % 2 ) !== 1
        || strpos( $title->getDBkey(), '/' ) === false
    ) {
        return;
    }
    $parserOutput = $parser->getOutput();

    // SMW's DisplayTitle annotator hijacks the sortkey to the displaytitle
    // when no explicit defaultsort is set, breaking LIKE-pattern queries
    // like [[~*Miniscopes/*]]. Pin defaultsort unconditionally on forum
    // topic pages so subpage queries always resolve, regardless of whether
    // an H2 has been added yet.
    $parserOutput->setPageProperty( 'defaultsort', $title->getPrefixedText() );

    $sections = $parserOutput->getSections();
    $subject = $sections ? trim( strip_tags( $sections[0]['line'] ?? '' ) ) : '';
    if ( $subject !== '' ) {
        $parserOutput->setDisplayTitle( $subject );
    }

    $parserData = \SMW\Services\ServicesFactory::getInstance()->newParserData( $title, $parserOutput );
    $semanticData = $parserData->getSemanticData();

    // Has forum: the post's containing forum landing page. getBaseTitle()
    // strips just the last subpage segment (the post's slug), so
    // Forum_talk:Hardware/<slug> -> Forum_talk:Hardware and
    // Forum_talk:Hardware/Sub/<slug> -> Forum_talk:Hardware/Sub.
    // getSubjectPage() then flips the talk namespace to its subject pair.
    // Always annotated — derivable from the title alone, no content gate.
    $forumTalk = $title->getBaseTitle();
    if ( $forumTalk ) {
        $forumSubject = $forumTalk->getSubjectPage();
        $semanticData->addPropertyObjectValue(
            new \SMW\DIProperty( '___forum_parent' ),
            \SMW\DIWikiPage::newFromTitle( $forumSubject )
        );
    }

    if ( $subject !== '' ) {
        $semanticData->addPropertyObjectValue(
            new \SMW\DIProperty( '___forum_subject' ),
            new \SMWDIBlob( $subject )
        );
    }

    // DT-derived bundle: read whatever LabkiForumDTAnnotateJob stashed
    // for this revision. Absent on freshly-saved revs before the job
    // has run; present afterward (and on every subsequent re-parse).
    $rev = $parser->getRevisionRecordObject();
    if ( $rev ) {
        $stash = \MediaWiki\MediaWikiServices::getInstance()->getMainObjectStash();
        $dt = $stash->get( $stash->makeKey(
            'labki-forum-dt',
            $title->getArticleID(),
            $rev->getId()
        ) );
        if ( is_array( $dt ) && ( $dt['count'] ?? 0 ) > 0 ) {
            $semanticData->addPropertyObjectValue(
                new \SMW\DIProperty( '___forum_comments' ),
                new \SMWDINumber( (int)$dt['count'] )
            );
            $semanticData->addPropertyObjectValue(
                new \SMW\DIProperty( '___forum_participants' ),
                new \SMWDINumber( (int)$dt['people'] )
            );
            if ( ( $dt['starter'] ?? '' ) !== '' ) {
                $starterTitle = \MediaWiki\Title\Title::makeTitleSafe( NS_USER, $dt['starter'] );
                if ( $starterTitle ) {
                    $semanticData->addPropertyObjectValue(
                        new \SMW\DIProperty( '___forum_starter' ),
                        \SMW\DIWikiPage::newFromTitle( $starterTitle )
                    );
                }
            }
            // Force SMW to re-run the data update even though the revision
            // ID hasn't changed. SMW's LinksUpdateComplete handler skips
            // when the stored associatedRev matches the page's latestRev
            // (DataUpdater::isSkippable in SMW's source). The initial save
            // for this revision already stored a title-derived snapshot
            // (DT-bundle absent, stash hadn't been populated yet) — without
            // this flag the RefreshLinksJob from LabkiForumDTAnnotateJob
            // would silently skip and the DT-derived props would never
            // land in the store. Only set when we actually have DT data
            // to contribute, so we don't pay the cost on every reparse.
            $parserOutput->setExtensionData(
                \SMW\ParserData::OPT_FORCED_UPDATE,
                true
            );
        }
    }
    $parserData->pushSemanticDataToParserOutput();
};

$wgHooks['BeforePageDisplay'][] = static function ( $out, $skin ) {
    $out->addModuleStyles( [ 'ext.labki.forum.styles' ] );
    $out->addModules( [ 'ext.labki.forum' ] );

    // Talk-side namespaces have odd numeric IDs; "/" in the DBkey
    // indicates a subpage, i.e. a topic. Tagging server-side via
    // addHtmlClasses lets the CSS match before first paint without
    // an inline <script>.
    $title = $out->getTitle();
    if ( $title && ( $title->getNamespace() % 2 ) === 1
        && strpos( $title->getDBkey(), '/' ) !== false
    ) {
        $out->addHtmlClasses( 'labki-forum-topic' );

        // Breadcrumb back to the forum landing page. getRootTitle strips
        // all subpage segments; getSubjectPage flips Forum_talk -> Forum,
        // Talk -> Main, etc.
        $parent = $title->getRootTitle()->getSubjectPage();
        $linkRenderer = \MediaWiki\MediaWikiServices::getInstance()->getLinkRenderer();
        $out->addSubtitle( $linkRenderer->makeLink(
            $parent,
            '← ' . $parent->getText(),
            [ 'class' => 'labki-forum-back' ]
        ) );
    }
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
