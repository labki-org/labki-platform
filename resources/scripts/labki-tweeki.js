/**
 * Labki Tweeki Scripts
 *
 * Adds notification badge counts for Echo alerts/notices.
 * Only loaded when Tweeki is the active skin.
 */
( function () {
	'use strict';

	var api = new mw.Api();

	function updateNotificationBadges() {
		api.get( {
			action: 'query',
			meta: 'notifications',
			notprop: 'count',
			notsections: 'alert|message',
			notgroupbythrottlegroup: 1
		} ).then( function ( data ) {
			var notif = data.query && data.query.notifications;
			if ( !notif ) {
				return;
			}

			var alertCount = notif.rawcount || 0;
			var noticeCount = notif[ 'message' ] ? notif[ 'message' ].rawcount || 0 : 0;

			// Parse counts from the sections if available
			if ( notif.list ) {
				// Fallback: use total count split isn't available
				alertCount = 0;
				noticeCount = 0;
			}

			// Try section-based counts
			if ( typeof notif.rawcount !== 'undefined' ) {
				// Single combined count - we'll query sections separately
				updateSectionCounts();
				return;
			}

			setBadge( 'pt-notifications-alert', alertCount );
			setBadge( 'pt-notifications-notice', noticeCount );
		} );
	}

	function updateSectionCounts() {
		api.get( {
			action: 'query',
			meta: 'notifications',
			notprop: 'count',
			notsections: 'alert'
		} ).then( function ( data ) {
			var count = data.query && data.query.notifications && data.query.notifications.rawcount || 0;
			setBadge( 'pt-notifications-alert', count );
		} );

		api.get( {
			action: 'query',
			meta: 'notifications',
			notprop: 'count',
			notsections: 'message'
		} ).then( function ( data ) {
			var count = data.query && data.query.notifications && data.query.notifications.rawcount || 0;
			setBadge( 'pt-notifications-notice', count );
		} );
	}

	function setBadge( elementId, count ) {
		var el = document.getElementById( elementId );
		if ( !el ) {
			return;
		}

		// Remove existing badge
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

	// Run on page load if user is logged in
	if ( mw.config.get( 'wgUserName' ) ) {
		mw.loader.using( 'mediawiki.api' ).then( updateNotificationBadges );
	}

}() );
