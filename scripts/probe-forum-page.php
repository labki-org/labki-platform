<?php
/**
 * probe-forum-page.php - end-to-end smoke probe for the labki-forum hooks.
 *
 * Saves a synthetic Forum_talk subpage with a known wikitext shape (which
 * triggers the ParserAfterParse hook on the saved revision), then reads
 * back what landed in MediaWiki's parser output and SMW's store. Prints
 * KEY=value lines reporting:
 *
 *   DISPLAYTITLE       - first H2 promoted via setDisplayTitle
 *   DEFAULTSORT        - canonical title pinned to neutralize SMW's
 *                        sortkey hijack from the DisplayTitle annotator
 *   FORUM_COMMENTS     - count of (UTC) signature timestamps in wikitext
 *   FORUM_PARTICIPANTS - unique [[User:X]] authors
 *   FORUM_STARTER      - first author, as User:Name
 *   FORUM_SUBJECT      - same string as DISPLAYTITLE, captured separately
 *                        through SMW's custom property channel
 *
 * The hook reads wikitext via $parser->getRevisionRecordObject(), so an
 * in-memory Parser::parse() (no revision) doesn't exercise the SMW path.
 * Saving via PageUpdater is the realistic flow that mirrors what happens
 * when a real user creates a topic.
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
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class LabkiProbeForumPage extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription( 'Probe the labki-forum hooks via a saved Forum_talk subpage.' );
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

        // Two unique authors across three signed comments. 'Bob' uses
        // mixed case so a case-folding regression in the starter extraction
        // would surface as User:bob (or a redlink) rather than User:Bob.
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

        // Force a fresh parse so the hook re-runs against the saved revision
        // (the parser cache may already hold a render from a prior smoke run).
        $popts = ParserOptions::newFromAnon();
        $popts->setRenderReason( 'smoke-probe' );
        $parserOutput = $page->getParserOutput( $popts ) ?: $page->getParserOutput();

        echo 'DISPLAYTITLE=' . ( $parserOutput ? $parserOutput->getDisplayTitle() : '' ) . "\n";
        echo 'DEFAULTSORT='
            . ( $parserOutput ? ( $parserOutput->getPageProperty( 'defaultsort' ) ?? '' ) : '' )
            . "\n";

        // SMW persists semantic data via its own hooks during the save flow,
        // so reading from the store gives the authoritative view of what the
        // labki-forum hooks contributed.
        $store = \SMW\StoreFactory::getStore();
        $smwData = $store->getSemanticData( \SMW\DIWikiPage::newFromTitle( $title ) );

        $columns = [
            'FORUM_SUBJECT'      => '___forum_subject',
            'FORUM_STARTER'      => '___forum_starter',
            'FORUM_COMMENTS'     => '___forum_comments',
            'FORUM_PARTICIPANTS' => '___forum_participants',
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
