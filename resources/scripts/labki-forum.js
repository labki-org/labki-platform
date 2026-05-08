/**
 * Labki Forum new-topic click handler.
 *
 * Binds to .labki-forum-new-post-btn (bundled styling) and to any
 * [data-labki-forum-new-post] element (bring-your-own UI). Derives the
 * talk-namespace counterpart of the current page via mw.Title.getTalkPage()
 * so the button has no hard-coded base path: a click on Forum:Miniscopes
 * lands the user at Forum_talk:Miniscopes/<UTC-timestamp>_<username> with
 * DT's new-topic widget pre-opened.
 *
 * The .labki-forum-topic class that triggers topic-page styling is added
 * server-side via OutputPage::addHtmlClasses (see extensions.platform.php),
 * not here.
 */
( function () {
	'use strict';

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
