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

// On forum topic pages, populate forum metadata: Has forum (containing
// forum, derived from the page's base title), Topic subject (first H2),
// Topic starter (first signature author), Comment count (signed comments),
// Participant count (unique authors). Also mirror the subject into
// DISPLAYTITLE so browser tabs and inbound wikilinks render the human-
// readable subject instead of the <UTC>_<user> slug.
//
// Has forum is derivable from the title alone (no wikitext parsing
// needed), so it's always set on every topic page regardless of whether
// the page has any signed comments yet — this means freshly-saved empty
// topics still surface in cross-forum activity feeds.
//
// === Why ParserAfterParse, not ContentAlterParserOutput ===
// SMW's ParserAfterTidy reads page properties (e.g. displaytitle) before
// the Content layer fires; ParserAfterParse runs earlier in the parser
// pipeline and gets the values into ParserOutput in time for SMW.
//
// === Caveats / known limits ===
// 1. English-locale only. Comments are detected by counting "(UTC)"
//    signature timestamps; authors by counting [[User:Name]] wikilinks.
//    Localized signatures (e.g. "(MEZ)" on de.wiki, "(コメント)" patterns)
//    will undercount. Acceptable for an English-language dev wiki; revisit
//    if this ships to a multilingual deployment.
// 2. Counts include the OP — a fresh topic with no replies reads as
//    "1 comment, 1 participant", matching Discourse-style conventions.
//    If a strict "replies" count is wanted, subtract 1.
// 3. DT has authoritative APIs (ContentHeadingItem::getCommentCount,
//    getAuthorsBelow) that handle locale and edge cases correctly, but
//    they need DT's CommentParser to run on already-parsed HTML, which
//    isn't available at this hook. Migrating to those would mean a
//    deferred-update model (job-queue annotation after page render),
//    significantly more code for marginal accuracy gain at our scale.
// 4. Wikitext is pulled from the revision rather than the $text param
//    because $text at ParserAfterParse is half-parsed (strip markers
//    in, link-rendering pending) — neither wikitext nor HTML regex
//    matches reliably against it.
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

    // $text at ParserAfterParse is in a half-parsed intermediate state —
    // strip-state markers in place, link-rendering not yet finalized — so
    // matching either wikitext or HTML against it is unreliable. Pull the
    // raw wikitext from the revision being parsed instead.
    $wikitext = '';
    $rev = $parser->getRevisionRecordObject();
    if ( $rev ) {
        $content = $rev->getContent( \MediaWiki\Revision\SlotRecord::MAIN );
        if ( $content instanceof \TextContent ) {
            $wikitext = $content->getText();
        }
    }
    $commentCount = preg_match_all( '/\(UTC\)/', $wikitext );
    preg_match_all( '/\[\[User:([^|\]\/]+)/i', $wikitext, $matches );
    $rawAuthors = array_values( array_filter(
        array_map( 'trim', $matches[1] ),
        static fn( $a ) => $a !== ''
    ) );
    // Lowercase only for dedupe — MediaWiki user-page titles past the first
    // letter are case-sensitive (alice and Alice are distinct), so the
    // unique-count is fine on lowercased values, but the starter name has to
    // keep its original case before flowing into Title::makeTitleSafe.
    $participantCount = count( array_unique( array_map( 'strtolower', $rawAuthors ) ) );
    $starter = $rawAuthors[0] ?? '';

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

    // Wikitext-derived annotations only fire when the page has substance —
    // skip the SMW work on freshly-saved empty topics that have neither
    // an H2 nor a signed comment yet.
    if ( $subject !== '' || $commentCount > 0 ) {
        if ( $subject !== '' ) {
            $semanticData->addPropertyObjectValue(
                new \SMW\DIProperty( '___forum_subject' ),
                new \SMWDIBlob( $subject )
            );
        }
        if ( $commentCount > 0 ) {
            $semanticData->addPropertyObjectValue(
                new \SMW\DIProperty( '___forum_comments' ),
                new \SMWDINumber( $commentCount )
            );
            $semanticData->addPropertyObjectValue(
                new \SMW\DIProperty( '___forum_participants' ),
                new \SMWDINumber( $participantCount )
            );
        }
        if ( $starter !== '' ) {
            $starterTitle = \MediaWiki\Title\Title::makeTitleSafe( NS_USER, $starter );
            if ( $starterTitle ) {
                $semanticData->addPropertyObjectValue(
                    new \SMW\DIProperty( '___forum_starter' ),
                    \SMW\DIWikiPage::newFromTitle( $starterTitle )
                );
            }
        }
    }
    $parserData->pushSemanticDataToParserOutput();
};

// Auto-watch propagation: when a fresh topic page is saved under a forum
// landing (the page's subject-namespace counterpart, derived from the
// base title), every user who watches the landing page becomes a watcher
// of the new topic too. With $wgEnotifWatchlist enabled, this gives a
// Forum its missing piece — clicking the Watch star on Forum:Miniscopes
// (or 2026_Paris_Workshop:Forum) now reliably delivers a bell + email
// notification when anyone posts a new topic there, because the topic
// page lands on the watcher's watchlist before MediaWiki's deferred
// EmailNotification job queries the watcher set.
//
// Leaf-only by design: we walk one subpage segment up via getBaseTitle()
// (matching the Has forum annotator above), not the full Has parent forum
// chain. Watching Forum:Home does NOT auto-watch posts in Forum:Hardware
// — users subscribe per-forum, mirroring DT's per-page subscription model.
//
// Existing posts predating this hook are NOT backfilled — they stay
// invisible to landing-page watchers until the next save touches them.
// Acceptable for forums that are still warming up; a one-time backfill
// script would be straightforward if needed later.
//
// We use WatchedItemStore::addWatchBatchForUser rather than
// WatchlistManager::addWatch so the propagation isn't gated on the
// landing-page watcher's editmywatchlist rights at this moment — they
// already opted in by watching the landing.
$wgHooks['PageSaveComplete'][] = static function (
    $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
) {
    // Creations only — replies / edits don't re-fan out. Checking the
    // parent revision id (0 / null on the first revision of a brand new
    // page) is more reliable than relying on EDIT_NEW being set by every
    // caller path (API, maintenance scripts, etc.).
    $parentId = $revisionRecord->getParentId();
    if ( $parentId !== null && $parentId !== 0 ) {
        return;
    }
    $title = $wikiPage->getTitle();
    if ( !$title || ( $title->getNamespace() % 2 ) !== 1
        || strpos( $title->getDBkey(), '/' ) === false
    ) {
        return;
    }

    // Foo_talk:Bar/<slug> -> Foo:Bar — same derivation as Has forum above.
    $landingTalk = $title->getBaseTitle();
    if ( !$landingTalk ) {
        return;
    }
    $landing = $landingTalk->getSubjectPage();
    if ( !$landing->exists() ) {
        return;
    }

    $services = \MediaWiki\MediaWikiServices::getInstance();
    $dbr = $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
    $userIds = $dbr->newSelectQueryBuilder()
        ->select( 'wl_user' )
        ->from( 'watchlist' )
        ->where( [
            'wl_namespace' => $landing->getNamespace(),
            'wl_title'     => $landing->getDBkey(),
        ] )
        ->caller( __METHOD__ )
        ->fetchFieldValues();
    if ( !$userIds ) {
        return;
    }

    $userFactory = $services->getUserFactory();
    $watchedItemStore = $services->getWatchedItemStore();
    foreach ( $userIds as $uid ) {
        $watcher = $userFactory->newFromId( (int)$uid );
        $watchedItemStore->addWatchBatchForUser( $watcher, [ $title ] );
    }
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
