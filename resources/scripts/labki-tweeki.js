/**
 * Labki Tweeki Scripts
 *
 * Two responsibilities:
 *
 * 1. Echo notifications under Tweeki live inside the user (PERSONAL)
 *    dropdown rather than as standalone navbar icons. Per-section
 *    counts get lost in transit (core's getPersonalToolsForMakeListItem
 *    moves `text` into links[0] and Tweeki falls back to
 *    `wfMessage($key)`, which produces label-only strings without the
 *    count). Poll the notifications API and decorate the user toggle
 *    + dropdown items with unread-count badges.
 *
 * 2. Light/dark theme toggle. Bootstrap 5.3 styles components based
 *    on `<html data-bs-theme>`; we set it from localStorage on first
 *    paint, then swap on click of the navbar toggle button. Falls
 *    back to `prefers-color-scheme` when no preference is stored.
 */
( function () {
	'use strict';

	// === Theme toggle ===========================================
	var THEME_STORAGE_KEY = 'labki-theme';
	var TOGGLE_ID = 'labki-theme-toggle';

	function readStoredTheme() {
		try {
			return localStorage.getItem( THEME_STORAGE_KEY );
		} catch ( e ) {
			return null;
		}
	}

	function persistTheme( theme ) {
		try {
			localStorage.setItem( THEME_STORAGE_KEY, theme );
		} catch ( e ) {
			// localStorage unavailable (private browsing, quota); silent.
		}
	}

	function preferredTheme() {
		var stored = readStoredTheme();
		if ( stored === 'light' || stored === 'dark' ) {
			return stored;
		}
		if ( window.matchMedia && window.matchMedia( '(prefers-color-scheme: dark)' ).matches ) {
			return 'dark';
		}
		return 'light';
	}

	function applyTheme( theme ) {
		document.documentElement.setAttribute( 'data-bs-theme', theme );
		var toggle = document.getElementById( TOGGLE_ID );
		if ( toggle ) {
			var icon = toggle.querySelector( 'span.fa' );
			if ( icon ) {
				icon.classList.remove( 'fa-sun', 'fa-moon' );
				icon.classList.add( theme === 'dark' ? 'fa-sun' : 'fa-moon' );
			}
		}
	}

	function bindThemeToggle() {
		var toggle = document.getElementById( TOGGLE_ID );
		if ( !toggle ) {
			return;
		}
		toggle.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var current = document.documentElement.getAttribute( 'data-bs-theme' ) || 'light';
			var next = current === 'dark' ? 'light' : 'dark';
			applyTheme( next );
			persistTheme( next );
		} );
	}

	// Apply the user's theme before the page paints to avoid a flash
	// of light content under a dark preference.
	applyTheme( preferredTheme() );

	// === Echo notification badges ================================
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

	function setBadge( el, count ) {
		if ( !el ) {
			return;
		}
		var existing = el.querySelector( ':scope > .labki-notif-badge' );
		if ( count > 0 ) {
			var label = count > 99 ? '99+' : String( count );
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

	function refreshBadges() {
		if ( mw.user.isAnon() ) {
			return;
		}
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

	// === Init ===================================================
	function start() {
		// Re-apply theme now that the toggle button is in the DOM, so
		// its icon is in sync with the current `data-bs-theme`.
		applyTheme( preferredTheme() );
		bindThemeToggle();

		if ( !mw.user.isAnon() ) {
			refreshBadges();
			setInterval( refreshBadges, POLL_INTERVAL_MS );
		}
	}

	if ( document.readyState !== 'loading' ) {
		start();
	} else {
		document.addEventListener( 'DOMContentLoaded', start );
	}
}() );
