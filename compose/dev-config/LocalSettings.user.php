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
