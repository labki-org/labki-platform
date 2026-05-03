/**
 * Labki Tweeki Scripts
 *
 * Responsibilities:
 *
 * 1. Light/dark theme toggle. Bootstrap 5.3 styles components based
 *    on `<html data-bs-theme>`; we set it from localStorage on first
 *    paint, then swap on click of the navbar toggle button. Falls
 *    back to `prefers-color-scheme` when no preference is stored.
 *
 * 2. Tag <html> with `is-anon` or `is-logged-in` so per-wiki CSS can
 *    render login-conditional UI without a DOM round-trip.
 *
 * 3. Right sidebar collapse drawer. Inject a viewport-anchored
 *    pull-tab button that toggles `body.sidebar-collapsed`. State
 *    persists in localStorage. Styles in labki-tweeki.css section
 *    "Right sidebar collapse drawer".
 *
 * 4. Page actions relocation. Lift the action-button cluster (Edit,
 *    History, …) out of #sidebar-right and re-anchor it at the
 *    top-right of the content card so it stays visible when the
 *    sidebar is collapsed and on narrow windows. Styles in
 *    labki-tweeki.css section "Page actions relocation".
 *
 * 5. Timestamp localization. Convert SMW-rendered UTC ISO timestamps
 *    (semantic <time datetime="..."> elements and bare ISO strings
 *    in wikitext tables) to the viewer's locale via toLocaleString().
 *
 * 6. Echo notifications under Tweeki: notifications live inside the
 *    user (PERSONAL) dropdown rather than as standalone navbar
 *    icons. Per-section counts get lost in transit (core's
 *    getPersonalToolsForMakeListItem moves `text` into links[0] and
 *    Tweeki falls back to `wfMessage($key)`, which produces label-
 *    only strings without the count). Poll the notifications API
 *    and decorate the user toggle + dropdown items with unread-
 *    count badges.
 */
( function () {
	'use strict';

	// === Login state HTML tagging ===============================
	// Tag <html> with `is-anon` or `is-logged-in` so per-wiki CSS
	// can render login-conditional UI without a DOM round-trip.
	// Runs immediately so the class is in place before any layout-
	// affecting init.
	document.documentElement.classList.add(
		mw.user.isAnon() ? 'is-anon' : 'is-logged-in'
	);

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

	// === Sidebar collapse drawer ================================
	var SIDEBAR_STORAGE_KEY = 'labki.sidebarCollapsed';
	var SVG_NS = 'http://www.w3.org/2000/svg';

	function readSidebarCollapsed() {
		try {
			return localStorage.getItem( SIDEBAR_STORAGE_KEY ) === 'true';
		} catch ( e ) {
			return false;
		}
	}

	function writeSidebarCollapsed( collapsed ) {
		try {
			localStorage.setItem( SIDEBAR_STORAGE_KEY, collapsed ? 'true' : 'false' );
		} catch ( e ) {
			// localStorage unavailable; non-fatal.
		}
	}

	// Feather-style chevron-left icon, built via DOM. CSS rotates it
	// 180deg when sidebar is collapsed so it reads "pull me out".
	function buildSidebarChevron() {
		var svg = document.createElementNS( SVG_NS, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'fill', 'none' );
		svg.setAttribute( 'stroke', 'currentColor' );
		svg.setAttribute( 'stroke-width', '2.5' );
		svg.setAttribute( 'stroke-linecap', 'round' );
		svg.setAttribute( 'stroke-linejoin', 'round' );
		svg.setAttribute( 'aria-hidden', 'true' );

		var polyline = document.createElementNS( SVG_NS, 'polyline' );
		polyline.setAttribute( 'points', '15 18 9 12 15 6' );
		svg.appendChild( polyline );
		return svg;
	}

	// True when #sidebar-right has rendered content worth collapsing.
	// Called after relocatePageActions(), so the action cluster has
	// already been lifted out — what remains is TOC, portals, etc.
	// If the sidebar's only content was the action cluster, there's
	// nothing left to hide and the toggle is pointless.
	function sidebarHasContent( sidebar ) {
		if ( sidebar.textContent.trim().length > 0 ) {
			return true;
		}
		// Catch icon-only / embed-only sidebars that have no text.
		return !!sidebar.querySelector( 'img, svg, hr, video, iframe' );
	}

	function installSidebarToggle() {
		var sidebar = document.getElementById( 'sidebar-right' );
		if ( !sidebar || !sidebarHasContent( sidebar ) ) {
			return;
		}

		if ( readSidebarCollapsed() ) {
			document.body.classList.add( 'sidebar-collapsed' );
		}

		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'sidebar-toggle';
		btn.setAttribute( 'aria-label', 'Toggle side panel' );
		btn.setAttribute( 'title', 'Toggle side panel' );
		btn.setAttribute(
			'aria-expanded',
			document.body.classList.contains( 'sidebar-collapsed' ) ? 'false' : 'true'
		);
		btn.appendChild( buildSidebarChevron() );

		btn.addEventListener( 'click', function () {
			var collapsed = document.body.classList.toggle( 'sidebar-collapsed' );
			btn.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			writeSidebarCollapsed( collapsed );
		} );

		document.body.appendChild( btn );
	}

	// === Page actions relocation ================================
	// Find the action-button cluster (Edit + dropdown) inside the
	// sidebar. Tweeki wraps page actions in different elements
	// depending on config; try a sequence of likely selectors.
	function findActionGroup( sidebar ) {
		var selectors = [
			'#p-cactions',
			'#p-views',
			'.tweeki-cactions',
			'.btn-group',
			'.dropdown'
		];
		for ( var i = 0; i < selectors.length; i++ ) {
			var match = sidebar.querySelector( selectors[ i ] );
			if ( match ) {
				return match;
			}
		}
		return null;
	}

	function relocatePageActions() {
		var sidebar = document.getElementById( 'sidebar-right' );
		if ( !sidebar ) {
			return;
		}
		var group = findActionGroup( sidebar );
		if ( !group ) {
			return;
		}
		var contentBody = document.querySelector( '.mw-body' ) ||
			document.getElementById( 'content' );
		if ( !contentBody ) {
			return;
		}

		var holder = document.createElement( 'div' );
		holder.className = 'page-actions';
		holder.appendChild( group );
		contentBody.insertBefore( holder, contentBody.firstChild );
	}

	// === Timestamp localization =================================
	// SMW's #-F[Y-m-d\TH:i:s\Z] format renders dates as raw UTC ISO
	// strings, which read confusingly to viewers in other timezones.
	// Convert to the viewer's locale via toLocaleString().
	//
	// Two paths:
	//   1. Semantic <time datetime="..."> elements anywhere on the
	//      page — preferred. Visible text is replaced; the datetime
	//      attribute stays machine-readable.
	//   2. Bare ISO strings inside any wikitext-rendered table cell
	//      — backstop for legacy SMW table conventions. The regex
	//      is strictly anchored, so false positives are not a real
	//      concern.
	// Idempotent via data-localized="true" marker.
	function formatLocalDateTime( d ) {
		return d.toLocaleString( undefined, {
			year:         'numeric',
			month:        'short',
			day:          'numeric',
			hour:         '2-digit',
			minute:       '2-digit',
			timeZoneName: 'short'
		} );
	}

	function localizeTimestamps() {
		var i, el, d, text;

		var times = document.querySelectorAll( 'time[datetime]' );
		for ( i = 0; i < times.length; i++ ) {
			el = times[ i ];
			if ( el.dataset.localized === 'true' ) {
				continue;
			}
			d = new Date( el.getAttribute( 'datetime' ) );
			if ( isNaN( d.getTime() ) ) {
				continue;
			}
			el.textContent = formatLocalDateTime( d );
			el.dataset.localized = 'true';
		}

		var iso = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/;
		var cells = document.querySelectorAll(
			'.mw-parser-output table td, .mw-parser-output table th'
		);
		for ( i = 0; i < cells.length; i++ ) {
			el = cells[ i ];
			if ( el.dataset.localized === 'true' ) {
				continue;
			}
			text = el.textContent.trim();
			if ( !iso.test( text ) ) {
				continue;
			}
			d = new Date( text );
			if ( isNaN( d.getTime() ) ) {
				continue;
			}
			el.textContent = formatLocalDateTime( d );
			el.dataset.localized = 'true';
		}
	}

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

		// relocatePageActions runs first so installSidebarToggle sees the
		// sidebar in its final state — if the action cluster was the
		// sidebar's only content, the toggle won't install.
		relocatePageActions();
		installSidebarToggle();
		localizeTimestamps();

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
