/**
 * Labki Tweeki Scripts
 *
 * Tweeki's PERSONAL navbar renderer drops the link-level class attribute
 * Echo adds in BeforePersonalUrls, so Echo's flyout init (which binds to
 * `.mw-echo-notification-badge-nojs`) finds nothing to attach to and
 * clicking the bell just navigates to Special:Notifications. This shim
 * re-adds the expected class on the two notification anchors so the
 * stock flyout works on Tweeki.
 */
( function () {
	'use strict';

	if ( mw.user.isAnon() ) {
		return;
	}

	function patchEchoBadges() {
		// Echo registers `notifications-alert` and `notifications-notice`
		// (older builds also use `notifications-message`). Cover both.
		[ 'pt-notifications-alert', 'pt-notifications-notice', 'pt-notifications-message' ].forEach( function ( id ) {
			var li = document.getElementById( id );
			if ( !li ) {
				return;
			}
			var anchor = li.querySelector( 'a' );
			if ( anchor && !anchor.classList.contains( 'mw-echo-notification-badge-nojs' ) ) {
				anchor.classList.add( 'mw-echo-notification-badge-nojs' );
			}
		} );
	}

	if ( document.readyState !== 'loading' ) {
		patchEchoBadges();
	} else {
		document.addEventListener( 'DOMContentLoaded', patchEchoBadges );
	}
}() );
