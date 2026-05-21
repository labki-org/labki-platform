<?php
/**
 * probe-forum-page.php - end-to-end smoke probe for the DiscussionForum hooks.
 *
 * Exercises the full save → DT-annotate-job → RefreshLinksJob →
 * ParserAfterParse → SMW write cycle, then reads back the resulting
 * semantic data. Prints KEY=value lines reporting:
 *
 *   DISPLAYTITLE       - first H2 promoted via setDisplayTitle
 *   DEFAULTSORT        - canonical title pinned to neutralize SMW's
 *                        sortkey hijack from the DisplayTitle annotator
 *   FORUM_SUBJECT      - same string as DISPLAYTITLE, captured through
 *                        SMW's custom property channel
 *   FORUM_COMMENTS     - comment count from DT's CommentParser
 *   FORUM_PARTICIPANTS - unique authors from DT's CommentParser
 *   FORUM_STARTER      - oldest reply's author, as User:Name
 *   FORUM_PARENT       - Has forum landing page (title-derived)
 *
 * Architecture note: the DT-derived bundle (FORUM_COMMENTS /
 * FORUM_PARTICIPANTS / FORUM_STARTER) is populated asynchronously via
 * the discussionForumAnnotate job. The smoke test runs the job inline
 * (the dev compose target doesn't start a jobrunner sidecar; see
 * ci/smoke-test.sh), which in turn runs RefreshLinksJob inline so SMW
 * re-stores the page with the DT data flowing through ParserAfterParse.
 *
 * Skips gracefully (exit 0, prints SKIP=...) when run on a target that
 * doesn't have the Forum_talk namespace registered (i.e. the prod CI matrix
 * row, where the dev-config overlay isn't mounted).
 *
 * The page is saved into the test database, which is torn down by
 * `docker compose down -v` at the end of the smoke run — no cleanup needed.
 */

require_once '/var/www/html/maintenance/Maintenance.php';

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class LabkiProbeForumPage extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription( 'Probe the DiscussionForum hooks via a saved Forum_talk subpage.' );
    }

    public function execute() {
        if ( !defined( 'NS_FORUM_TALK' ) ) {
            echo "SKIP=NS_FORUM_TALK not registered (run on dev target only)\n";
            return;
        }

        $title = Title::makeTitle(
            NS_FORUM_TALK,
            'Smoketest/2026-01-01_120000_Bob'
        );

        // Two unique authors across three signed comments. Bob signs both
        // the OP and a followup so DT's author dedup is exercised; 'Bob'
        // uses mixed case so a case-folding regression in the starter
        // extraction would surface as User:bob (or a redlink) rather than
        // User:Bob. Sigs use (UTC) to match the dev compose's default
        // $wgLocaltimezone — DT's locale-time-zone correctness is its own
        // contract; this probe just verifies the hook plumbing end-to-end.
        $wikitext = "== Hello smoke ==\n\n"
            . "Initial post body. [[User:Bob|Bob]] ([[User talk:Bob|talk]]) 12:00, 1 January 2026 (UTC)\n\n"
            . ":Reply from another user. [[User:Alice|Alice]] ([[User talk:Alice|talk]]) 12:05, 1 January 2026 (UTC)\n\n"
            . "::Followup. [[User:Bob|Bob]] ([[User talk:Bob|talk]]) 12:10, 1 January 2026 (UTC)\n";

        $services = MediaWikiServices::getInstance();
        $user = User::newSystemUser( 'Smoke probe', [ 'steal' => true ] );
        $page = $services->getWikiPageFactory()->newFromTitle( $title );

        $updater = $page->newPageUpdater( $user );
        $updater->setContent( SlotRecord::MAIN, new WikitextContent( $wikitext ) );
        $rev = $updater->saveRevision(
            CommentStoreComment::newUnsavedComment( 'smoke probe' )
        );
        if ( !$rev ) {
            $this->fatalError( 'Failed to save smoke-probe revision: '
                . $updater->getStatus()->getWikiText( false, false, 'en' ) );
        }

        // saveRevision schedules SMW's first write (Has forum + Topic
        // subject + special props) as an in-request deferred update.
        // Flush before running the DT annotate job so the job's
        // RefreshLinksJob doesn't race the first write.
        DeferredUpdates::doUpdates();

        // Drain the DT annotate jobs queued by PageSaveComplete. Each
        // job parses Parsoid HTML via DT, caches counts, and runs
        // RefreshLinksJob inline; RefreshLinksJob then re-renders and
        // re-stores SMW data with the DT-derived bundle in place.
        $jqg = $services->getJobQueueGroup();
        $drained = 0;
        while ( ( $job = $jqg->pop( 'discussionForumAnnotate' ) ) ) {
            $job->run();
            $drained++;
            if ( $drained > 5 ) {
                $this->fatalError( 'Runaway discussionForumAnnotate job loop (>5 iterations).' );
            }
        }
        DeferredUpdates::doUpdates();

        // Now re-fetch the page's parser output so we can report
        // DISPLAYTITLE / DEFAULTSORT that survived the cycle.
        $popts = ParserOptions::newFromAnon();
        $popts->setRenderReason( 'smoke-probe-post-dt' );
        $parserOutput = $page->getParserOutput( $popts ) ?: $page->getParserOutput();

        echo 'DISPLAYTITLE=' . ( $parserOutput ? $parserOutput->getDisplayTitle() : '' ) . "\n";
        echo 'DEFAULTSORT='
            . ( $parserOutput ? ( $parserOutput->getPageProperty( 'defaultsort' ) ?? '' ) : '' )
            . "\n";

        $store = \SMW\StoreFactory::getStore();
        $smwData = $store->getSemanticData( \SMW\DIWikiPage::newFromTitle( $title ) );

        $columns = [
            'FORUM_SUBJECT'      => '___forum_subject',
            'FORUM_STARTER'      => '___forum_starter',
            'FORUM_COMMENTS'     => '___forum_comments',
            'FORUM_PARTICIPANTS' => '___forum_participants',
            'FORUM_PARENT'       => '___forum_parent',
        ];
        foreach ( $columns as $key => $pid ) {
            $vals = $smwData->getPropertyValues( new \SMW\DIProperty( $pid ) );
            if ( !$vals ) {
                echo "$key=\n";
                continue;
            }
            $first = reset( $vals );
            if ( $first instanceof \SMWDINumber ) {
                echo "$key=" . (int)$first->getNumber() . "\n";
            } elseif ( $first instanceof \SMWDIBlob ) {
                echo "$key=" . $first->getString() . "\n";
            } elseif ( $first instanceof \SMW\DIWikiPage ) {
                $t = $first->getTitle();
                echo "$key=" . ( $t ? $t->getPrefixedText() : '' ) . "\n";
            } else {
                echo "$key=" . (string)$first . "\n";
            }
        }
    }
}

$maintClass = LabkiProbeForumPage::class;
require_once RUN_MAINTENANCE_IF_MAIN;
