/**
 * Labki Forum
 *
 * Forum-style discussion topics on top of DiscussionTools, without a
 * dedicated forum extension. Two responsibilities:
 *
 *   1. New-topic click handler. Generates a slug from the current UTC
 *      timestamp and the viewer's username, then sends them to the
 *      corresponding Talk-namespace subpage with DT's new-topic widget
 *      pre-opened (action=edit&section=new). Works in any namespace: on
 *      Forum:Miniscopes the new page lands at
 *      Forum_talk:Miniscopes/<timestamp>_<user>; on Project:Foo it lands
 *      at Project_talk:Foo/...; etc. We derive the talk page from the
 *      current page via mw.Title.getTalkPage() so the button has no
 *      hard-coded base path.
 *
 *      Two ways to wire it up — the handler binds to either:
 *
 *        * .labki-forum-new-post-btn  — gives you the bundled visual
 *          styling from labki-forum.less. The canonical "drop a button
 *          on a page" path.
 *        * [data-labki-forum-new-post] — pure behavioral hook with no
 *          styling attached. Use this when you want to bring your own
 *          button UI (Bootstrap btn, OOUI widget, hand-rolled HTML)
 *          and only want the click behavior.
 *
 *   2. Tag Talk-namespace subpages with `labki-forum-topic` on <html> so
 *      labki-forum.less can render them as forum topic cards. Talk
 *      namespaces have odd numeric IDs in MediaWiki — combined with a
 *      "/" in the page name (i.e., a subpage), this is a reliable
 *      heuristic for "this is a forum topic page" without any per-page
 *      annotation. Edge cases like Talk:Article/Archive_1 also pick up
 *      the styling; that's an acceptable default for a forum-oriented
 *      wiki and is opt-out via per-page CSS if a deployment needs it.
 */
( function () {
	'use strict';

	$( function () {
		var ns = mw.config.get( 'wgNamespaceNumber' );
		var pn = mw.config.get( 'wgPageName' );

		if ( typeof ns === 'number' && ns % 2 === 1 && pn && pn.indexOf( '/' ) > -1 ) {
			document.documentElement.classList.add( 'labki-forum-topic' );
		}
	} );

	$( function () {
		$( '.labki-forum-new-post-btn, [data-labki-forum-new-post]' ).on( 'click keypress', function ( e ) {
			if ( e.type === 'keypress' && e.which !== 13 && e.which !== 32 ) {
				return;
			}
			e.preventDefault();

			var user = mw.config.get( 'wgUserName' );
			if ( !user ) {
				mw.notify( 'Please log in to post.' );
				return;
			}

			var current = mw.Title.newFromText( mw.config.get( 'wgPageName' ) );
			var talk = current ? current.getTalkPage() : null;
			if ( !talk ) {
				mw.notify( 'This page does not have a talk-namespace counterpart.' );
				return;
			}

			var d = new Date();
			var pad = function ( n ) { return String( n ).padStart( 2, '0' ); };
			var slug = d.getUTCFullYear() + '-' +
				pad( d.getUTCMonth() + 1 ) + '-' +
				pad( d.getUTCDate() ) + '_' +
				pad( d.getUTCHours() ) +
				pad( d.getUTCMinutes() ) +
				pad( d.getUTCSeconds() );

			var path = talk.getPrefixedText() + '/' + slug + '_' + user;

			location.href = mw.util.getUrl( path, {
				action: 'edit',
				section: 'new'
			} );
		} );
	} );
}() );
