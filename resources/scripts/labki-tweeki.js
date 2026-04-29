/**
 * Labki Tweeki Scripts
 *
 * Echo notifications under Tweeki live inside the user (PERSONAL)
 * dropdown rather than as standalone navbar icons. Echo's per-section
 * count is included in its message text but lost in transit (core's
 * getPersonalToolsForMakeListItem moves `text` into links[0] and Tweeki
 * falls back to `wfMessage($key)`, which gives us countless labels).
 *
 * This script polls notification counts and:
 *   - decorates the user dropdown toggle with a total-unread badge so
 *     the unread state is visible without opening the menu;
 *   - appends per-section counts ("Alerts (2)", "Notices (1)") to the
 *     dropdown items themselves.
 */
( function () {
	'use strict';

	if ( mw.user.isAnon() ) {
		return;
	}

	var POLL_INTERVAL_MS = 60 * 1000;
	var SECTION_TO_PT_ID = {
		alert: 'pt-notifications-alert',
		message: 'pt-notifications-notice'
	};

	function findUserToggle() {
		var navbar = document.getElementById( 'mw-navigation' );
		if ( !navbar ) {
			return null;
		}
		var toggles = navbar.querySelectorAll( '.dropdown-toggle' );
		for ( var i = 0; i < toggles.length; i++ ) {
			if ( toggles[ i ].closest( '.navbar-right, .ms-auto, .nav, .navbar-nav' ) ) {
				return toggles[ i ];
			}
		}
		return toggles[ 0 ] || null;
	}

	function setBadge( el, count, prefix ) {
		if ( !el ) {
			return;
		}
		var existing = el.querySelector( ':scope > .labki-notif-badge' );
		if ( count > 0 ) {
			var label = ( prefix || '' ) + ( count > 99 ? '99+' : String( count ) );
			if ( existing ) {
				existing.textContent = label;
			} else {
				var badge = document.createElement( 'span' );
				badge.className = 'labki-notif-badge';
				badge.textContent = label;
				el.appendChild( badge );
			}
		} else if ( existing ) {
			existing.remove();
		}
	}

	function refresh() {
		var api = new mw.Api();
		api.get( {
			action: 'query',
			meta: 'notifications',
			notprop: 'count',
			notgroupbysection: 1
		} ).then( function ( data ) {
			var n = ( data && data.query && data.query.notifications ) || {};
			var total = typeof n.rawcount === 'number' ? n.rawcount : 0;
			setBadge( findUserToggle(), total );

			Object.keys( SECTION_TO_PT_ID ).forEach( function ( section ) {
				var sectionCount = n[ section ] && typeof n[ section ].rawcount === 'number'
					? n[ section ].rawcount
					: 0;
				var li = document.getElementById( SECTION_TO_PT_ID[ section ] );
				if ( !li ) {
					return;
				}
				// Tweeki renders the dropdown item as <li id="pt-…"><a>…</a></li>;
				// place the badge inside the <a> so it inherits link styling.
				var anchor = li.querySelector( 'a' ) || li;
				setBadge( anchor, sectionCount );
			} );
		} );
	}

	function start() {
		refresh();
		setInterval( refresh, POLL_INTERVAL_MS );
	}

	if ( document.readyState !== 'loading' ) {
		start();
	} else {
		document.addEventListener( 'DOMContentLoaded', start );
	}
}() );
