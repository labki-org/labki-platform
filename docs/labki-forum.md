# Labki Forum Runbook

Forum-style discussion topics built on top of MediaWiki **DiscussionTools**, using Talk-namespace subpages with auto-generated `<UTC-timestamp>_<username>` slugs. No dedicated forum extension; the entire feature is bundled into the platform as a small ResourceLoader module plus three PHP hooks.

## What you get

Bundled into the platform with no extra configuration beyond namespace setup:

- A drop-in **"new topic" button** for any wiki page. Clicking it routes the user to DT's new-topic widget on a fresh subpage of the current page's talk-namespace counterpart.
- **Forum-card styling** on the resulting topic pages (outer card frame, accent-bar first post, indented reply cards, button-styled reply links). Covers Tweeki and Vector-family skins; uses Codex tokens for light/dark theming.
- A **"← parent" breadcrumb** at the top of every topic page, linking back to the subject-namespace landing page.
- **`__DISPLAYTITLE__` auto-set** to the topic's first H2, so browser tabs and inbound wikilinks render the human-readable subject instead of the `<UTC>_<user>` slug.
- **Five custom SMW properties** populated per topic so a landing page can render a forum index via `#ask`:
  - `Topic subject` (Text) — first H2 of the page
  - `Topic starter` (Page) — first signature author, as a `User:Name` link
  - `Comment count` (Number) — count of signed timestamps in the page; matches any `$wgLocaltimezone` (UTC, PDT/PST, EST/EDT, etc.)
  - `Participant count` (Number) — unique authors
  - `Has forum` (Page) — the topic's containing forum landing, derived from the page's base title (`Forum_talk:Hardware/<slug>` → `Forum:Hardware`). Always set, even on freshly-saved empty topics. Enables chained queries like `[[Has forum.Has parent forum::Forum:Home]]` for activity feeds on hub-style landing pages.

## Setup for a new namespace pair

Suppose you want a forum at `Forum:*` with topic pages under `Forum_talk:*`. In your `LocalSettings.user.php`:

```php
define( 'NS_FORUM',      3000 );
define( 'NS_FORUM_TALK', 3001 );

$wgExtraNamespaces[NS_FORUM]      = 'Forum';
$wgExtraNamespaces[NS_FORUM_TALK] = 'Forum_talk';

// Subpages must be enabled on the talk side — that's where topics live.
$wgNamespacesWithSubpages[NS_FORUM]      = true;
$wgNamespacesWithSubpages[NS_FORUM_TALK] = true;

// Optional: make Forum: a content namespace so landing pages count
// for Recent Changes / search defaults.
$wgContentNamespaces[] = NS_FORUM;

// Optional: lock down Forum: so only admins curate landing pages,
// while Forum_talk: stays open to logged-in users for posting topics.
$wgNamespacePermissionLockdown[NS_FORUM]['edit']   = [ 'sysop' ];
$wgNamespacePermissionLockdown[NS_FORUM]['create'] = [ 'sysop' ];
```

The feature is **not Forum-specific**. The hooks fire on any odd-numbered namespace (i.e. any talk-side namespace) with a `/` in the page name — so `Project_talk:Foo/<slug>`, `User_talk:Bob/<slug>`, and plain `Talk:Article/<slug>` all pick up the forum chrome. Pick whatever namespace pair fits your use case.

### Enabling SMW indexing for the forum index

The four custom SMW properties register automatically the moment the platform's extensions load, but for them (and their associated `#ask` queries) to show data, SMW needs to be told the topic namespaces are queryable, plus a few built-in special properties enabled for the forum-index columns:

```php
$smwgNamespacesWithSemanticLinks[NS_FORUM]      = true;
$smwgNamespacesWithSemanticLinks[NS_FORUM_TALK] = true;

// _CDAT (Creation date), _LEDT (Last editor is), _DTITLE (Display title of)
// surface the "Started", "Last by", and "Subject" columns of the forum index.
$smwgPageSpecialProperties = array_merge(
    $smwgPageSpecialProperties ?? [],
    [ '_CDAT', '_LEDT', '_DTITLE' ]
);
```

After changing this list you need to bump the SMW SQL store schema and re-index existing pages:

```bash
docker exec <wiki-container> php /var/www/html/extensions/SemanticMediaWiki/maintenance/setupStore.php
docker exec <wiki-container> php /var/www/html/extensions/SemanticMediaWiki/maintenance/rebuildData.php -n 3000,3001
```

## Placing the "new topic" button

Drop one of these on any page that should become a forum landing page:

```html
<!-- Bundled visual styling (uses the .labki-forum-new-post-btn class shipped in labki-forum.less) -->
<span class="labki-forum-new-post-btn" role="button" tabindex="0">Make new post</span>

<!-- Bring-your-own UI: pure behavioral hook, no styling attached -->
<button class="my-button" data-labki-forum-new-post>Start a discussion</button>
```

On click, the handler:
1. Reads the current page name (`Forum:Miniscopes`).
2. Flips to the talk-namespace counterpart via `mw.Title.getTalkPage()` (`Forum_talk:Miniscopes`).
3. Generates a slug from the current UTC timestamp and the viewer's username (`2026-05-08_120030_Daniel`).
4. Navigates to `Forum_talk:Miniscopes/<slug>?action=edit&section=new`, which DT renders as its new-topic widget.

The user types their subject (H2) and body, hits "Reply", and lands on the saved topic page styled as a forum card.

## Forum index `#ask` query

Paste this on a `Forum:*` landing page to render an index of its topics:

```
{{#ask: [[Forum talk:+]] [[~*Miniscopes/*]]
 |mainlabel=Topic
 |?Topic starter=Started by
 |?Last editor is=Last by
 |?Creation date#LOCL=Started
 |?Modification date#LOCL=Last activity
 |?Comment count=Replies
 |?Participant count=People
 |format=broadtable
 |headers=plain
 |sort=Modification date
 |order=desc
 |limit=20
 |default=No topics yet.
}}
```

Replace `Miniscopes` with the landing page's name. The `Topic` column auto-renders the H2 as link text (DISPLAYTITLE-driven), with the link target being the canonical slug-based URL.

### Why `[[Forum talk:+]] [[~*Miniscopes/*]]` instead of `[[~Forum talk:Miniscopes/*]]`

SMW's `~LIKE` pattern doesn't match the namespace prefix as a single token. Splitting into a namespace condition + a name pattern is the working idiom.

## CSS hooks for customization

The styling sits behind two top-level hooks you can override via `MediaWiki:Common.css` or per-skin custom CSS:

| Selector | Scope |
| :--- | :--- |
| `.labki-forum-new-post-btn` | Bundled "new topic" button. Restyle freely; the click handler lives on the class plus the `[data-labki-forum-new-post]` attribute. |
| `html.labki-forum-topic` | Set server-side on every odd-namespace subpage. All topic-card chrome (frame, accent stripe, reply cards, hidden `#firstHeading` / TOC) is scoped under this. Override individual rules to retune; remove the class server-side to opt out wholesale. |
| `.labki-forum-back` | The "← parent" breadcrumb anchor in `#contentSub`. |

## Known caveats

### English-locale signature heuristic

`Comment count` and `Participant count` are extracted from the saved revision's wikitext: comments by matching the MW English-locale signature timestamp shape (`HH:MM, D MONTHNAME YYYY (TZ)`), participants by counting `[[User:Name]]` wikilinks. The TZ token is open-ended, so non-UTC `$wgLocaltimezone` settings (`(PDT)`, `(PST)`, `(EST)`, …) count correctly. Fully-localized signatures with non-English month names will still undercount. Switching to DT's authoritative `ContentHeadingItem::getCommentCount()` would require a deferred-update model since DT's parser needs already-rendered HTML. Acceptable for English-language deployments; revisit if shipping to a multilingual wiki.

### Counts include the OP

A topic with no replies reads as "1 comment, 1 participant" — Discourse-style. Subtract 1 in your `#ask` view if you want a strict "replies" count.

### DISPLAYTITLE fires on every odd-namespace subpage

Plain `Talk:Article/Archive_1` pages also pick up the forum chrome and DISPLAYTITLE auto-set. That's an acceptable default for a forum-oriented wiki; opt-out per-page by adding a `{{DISPLAYTITLE:...}}` override or overriding the styling locally. To scope strictly to a single namespace, narrow the namespace check in `mediawiki/extensions.platform.php`.

### DEFAULTSORT is pinned

The `ParserAfterParse` hook unconditionally sets `__DEFAULTSORT__` to the canonical prefixed title on forum topic pages. This neutralizes SMW's DisplayTitle annotator, which would otherwise hijack the sortkey to the displaytitle and break LIKE-pattern queries like `[[~*Miniscopes/*]]`. If you need a different sort key on a topic page, override it explicitly in the page's wikitext.

### SESP `_CUSER` is broken on MW 1.44

SESP's "Page author" property would surface "Started by" via a built-in route, but its `CreatorPropertyAnnotator` type-hints the legacy `\Title` class while MW 1.44 emits `\MediaWiki\Title\Title`, throwing a `TypeError` mid-rebuild. The custom `Topic starter` property fills the same role. Re-enable SESP's `_CUSER` once the upstream fix lands.

## Where the wiring lives

| File | Role |
| :--- | :--- |
| `mediawiki/extensions.platform.php` | Hook registrations: `SMW::Property::initProperties` (4 custom properties), `ParserAfterParse` (DISPLAYTITLE / DEFAULTSORT / SMW data), `BeforePageDisplay` (HTML class + breadcrumb subtitle). |
| `resources/scripts/labki-forum.js` | Click handler bound to `.labki-forum-new-post-btn` and `[data-labki-forum-new-post]`. |
| `resources/styles/labki-forum.less` | All visual styling (button + topic-card chrome). Codex tokens with hex fallbacks. |
| `compose/dev-config/LocalSettings.user.php` | Reference deployment of the namespace + SMW configuration above (used by the dev compose target only). |
| `scripts/probe-forum-page.php` | End-to-end smoke probe — saves a synthetic topic and reads back the hook outputs. Covered by `ci/smoke-test.sh`. |
