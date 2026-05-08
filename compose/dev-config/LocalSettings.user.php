<?php
// LocalSettings.user.php — overlay loaded by the dev compose at
// compose/docker-compose.dev.yml (mounted into the container at
// /mw-config/LocalSettings.user.php).
//
// Sets up a Forum / Forum_talk namespace pair so the bundled labki-forum
// module (registered in mediawiki/extensions.platform.php) can be exercised
// end-to-end. Drop the .labki-forum-new-post-btn span on a Forum:* page;
// the resulting topic lands at Forum_talk:.../<UTC-timestamp>_<username>.
//
// Forum: subject pages are sysop-only (curated landing pages); Forum_talk:
// subpages inherit MediaWiki's standard talk-namespace edit rights — i.e.
// any logged-in user can post topics and reply. This file is intended for
// the dev test environment only; production deployments choose their own
// namespace policy via runtime/config/LocalSettings.user.php.

if ( !defined( 'MEDIAWIKI' ) ) {
    exit;
}

define( 'NS_FORUM',      3000 );
define( 'NS_FORUM_TALK', 3001 );

$wgExtraNamespaces[NS_FORUM]      = 'Forum';
$wgExtraNamespaces[NS_FORUM_TALK] = 'Forum_talk';

$wgNamespacesWithSubpages[NS_FORUM]      = true;
$wgNamespacesWithSubpages[NS_FORUM_TALK] = true;

$wgContentNamespaces[] = NS_FORUM;

$wgNamespacePermissionLockdown[NS_FORUM]['edit']   = [ 'sysop' ];
$wgNamespacePermissionLockdown[NS_FORUM]['create'] = [ 'sysop' ];

// Enable SMW so #ask / property annotations work in Forum landing pages
// and inside topic threads (e.g. an index of Forum_talk:* subpages on
// Forum:Miniscopes).
$smwgNamespacesWithSemanticLinks[NS_FORUM]      = true;
$smwgNamespacesWithSemanticLinks[NS_FORUM_TALK] = true;

// Built-in SMW special properties (default ships only _MDAT). Adding
// Creation date + Last editor + Display title of lets a forum landing page
// render a "Started / Last activity / Last by / Subject" index over
// Forum_talk subpages.
//
// Schema note: changes to this list (or to the custom-property registry in
// extensions.platform.php) are SQL-store schema changes. After updating,
// run inside the wiki container:
//   php extensions/SemanticMediaWiki/maintenance/setupStore.php
//   php extensions/SemanticMediaWiki/maintenance/rebuildData.php -n 3000,3001
//
// SESP note: _CUSER ("Page author", first author) would surface
// "Started by" via SESP, but SESP's CreatorPropertyAnnotator type-hints
// the legacy \Title class while MW 1.44 emits \MediaWiki\Title\Title —
// runs throw a TypeError. Re-enable once SESP upstreams the namespaced
// type. The custom ___forum_starter property in extensions.platform.php
// fills the same role in the meantime.
$smwgPageSpecialProperties = array_merge(
    $smwgPageSpecialProperties ?? [],
    [ '_CDAT', '_LEDT', '_DTITLE' ]
);
