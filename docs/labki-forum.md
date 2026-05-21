# Labki Forum (extracted)

The forum feature previously documented here has been extracted into a
standalone MediaWiki extension:

> **DiscussionForum** — https://github.com/labki-org/DiscussionForum

All hooks, the SMW property registration, the DT-derived annotation
job, the watchlist subscription fanout, and the DiscussionTools
auto-subscribe timezone-bug workaround now live in that repo. The
extension is registered in [`extensions-git/sources.txt`](../extensions-git/sources.txt)
and loaded by `mediawiki/extensions.platform.php` via
`wfLoadExtension('DiscussionForum')`.

The full runbook — namespace setup, the CSS / data-attr API surface,
SMW property labels for `#ask` queries, the auto-watch subscription
model — lives in the extension's own
[README](https://github.com/labki-org/DiscussionForum#readme).

## Cutover renames (2026)

When migrating from the inline labki-platform vintage to the
extracted extension, consuming wikis (and the SchemaSync templates
that drive them) need these search-and-replaces:

| Old (inline vintage)             | New (DiscussionForum)               |
| :---                             | :---                                |
| `.labki-forum-new-post-btn`      | `.discussionforum-new-post-btn`     |
| `data-labki-forum-new-post`      | `data-discussionforum-new-post`     |
| `.labki-forum-landing`           | `.discussionforum-landing`          |
| RL module `ext.labki.forum*`     | `ext.discussionforum*`              |
| Job `labkiForumDTAnnotate`       | `discussionForumAnnotate`           |

SMW property labels (`Has forum`, `Topic subject`, `Topic starter`,
`Comment count`, `Participant count`) and their internal IDs
(`___forum_*`) are intentionally unchanged across the cutover —
existing on-wiki `#ask` queries and stored data keep working.

For pre-existing forum topics on a deployed wiki, run
`maintenance/run.php extensions/DiscussionForum/maintenance/backfillForumAnnotations.php`
once after the cutover to re-emit DT-derived properties for content
saved before the extension was loaded.
