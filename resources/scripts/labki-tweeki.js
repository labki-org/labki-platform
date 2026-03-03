/**
 * Labki Tweeki Scripts
 *
 * Adds notification badge counts for Echo alerts/notices.
 * Only loaded when Tweeki is the active skin (via $wgTweekiSkinCustomScriptModule).
 */
( function () {
	'use strict';

	function updateNotificationBadges() {
		var api = new mw.Api();

		Promise.all( [
			api.get( {
				action: 'query',
				meta: 'notifications',
				notprop: 'count',
				notsections: 'alert'
			} ),
			api.get( {
				action: 'query',
				meta: 'notifications',
				notprop: 'count',
				notsections: 'message'
			} )
		] ).then( function ( results ) {
			var alertCount = results[ 0 ].query &&
				results[ 0 ].query.notifications &&
				results[ 0 ].query.notifications.rawcount || 0;
			var noticeCount = results[ 1 ].query &&
				results[ 1 ].query.notifications &&
				results[ 1 ].query.notifications.rawcount || 0;

			setBadge( 'pt-notifications-alert', alertCount );
			setBadge( 'pt-notifications-notice', noticeCount );
		} );
	}

	function setBadge( elementId, count ) {
		var el = document.getElementById( elementId );
		if ( !el ) {
			return;
		}

		var existing = el.querySelector( '.labki-notif-badge' );
		if ( existing ) {
			existing.remove();
		}

		if ( count > 0 ) {
			var badge = document.createElement( 'span' );
			badge.className = 'labki-notif-badge';
			badge.textContent = count > 99 ? '99+' : count;
			el.appendChild( badge );
			el.classList.add( 'labki-notif-unread' );
		} else {
			el.classList.remove( 'labki-notif-unread' );
		}
	}

	if ( !mw.user.isAnon() ) {
		updateNotificationBadges();
	}

}() );
