# Tweeki Runbook

A "wiki-as-website" guide for switching a Labki Platform deployment to use **Tweeki** as the default skin. Vector 2022 stays the platform default; switch to Tweeki when you want a more website-like top-navbar layout.

## What you get with Tweeki on Labki

Bundled into the platform with no extra configuration:

- Top-fixed Bootstrap 5.3 navbar; full-width content (no left rail)
- Echo notifications inside the user menu, with an unread-count badge on the user dropdown toggle and per-section count badges on the dropdown items
- VisualEditor wired up (via `$wgVisualEditorSupportedSkins[]`)
- DiscussionTools renders as expected on talk pages
- Light / dark theme toggle in the navbar; respects `prefers-color-scheme` on first load and persists choice in `localStorage`
- Custom academic-blue palette via `--labki-*` CSS variables (overridable per deployment in `MediaWiki:Tweeki.css`)
- "Powered by Labki Platform" custom footer block (overridable at `MediaWiki:Tweeki-footer-custom`)

## Switching a deployment to Tweeki

### Site-wide

In your `LocalSettings.user.php`:

```php
$wgDefaultSkin = 'tweeki';
```

Restart the wiki container. New visitors and any user who hasn't explicitly chosen a skin in their preferences will get Tweeki.

### Per-user

Users can opt in independently via **Special:Preferences → Appearance → Skin → Tweeki**, regardless of `$wgDefaultSkin`.

### Quick preview without switching

Append `?useskin=tweeki` to any wiki URL to render that page with Tweeki for the current request only — useful for smoke-checking before flipping the default.

## MediaWiki: pages worth populating

These pages drive Tweeki's chrome. Edit them like any wiki page; changes take effect immediately.

| Page | What it controls |
| :--- | :--- |
| `MediaWiki:Sidebar` | Tweeki reads this for the **left side of the top navbar** (top-level menu groups). Each `* heading` line becomes a navbar dropdown; nested `** Page\|Label` lines become its items. |
| `MediaWiki:Tweeki-footer-custom` | Wikitext list rendered in the custom footer block. Default value is "Powered by Labki Platform" — replace or extend with About / Contact / Privacy links. |
| `MediaWiki:Tweeki.css` | Site-level CSS overrides for Tweeki. Use this to retune the `--labki-*` palette for your brand without forking the platform. |

## Known caveats

### VisualEditor under Tweeki

Tweeki isn't in MediaWiki core's default `$wgVisualEditorSupportedSkins`. The platform opts it in (see `mediawiki/extensions.platform.php`), but if you upgrade VE or override that variable, double-check Tweeki is still in the list — otherwise users will fall back to source-mode editing.

### ConfirmAccount + OOUI theme initialization

ConfirmAccount instantiates OOUI widgets before the active skin sets the OOUI theme, which can raise `Cannot use object of type ... as singleton` from `\OOUI\Theme::singleton()`. The platform pre-initializes the singleton in a `SetupAfterCache` hook (`mediawiki/extensions.platform.php`). If you see OOUI singleton errors, check that hook still fires.

### Echo notifications: labels / icons / unread state

Tweeki's `PERSONAL` renderer doesn't surface Echo's per-entry `text` or `icon` directly. The platform handles three workarounds:

- `mediawiki/i18n/en.json` registers the missing `notifications-alert` and `notifications-notice` message keys so Tweeki's `wfMessage($key)` fallback resolves to "Alerts" / "Notices".
- `resources/styles/labki-tweeki.css` maps `fa-tray::before` to FontAwesome's inbox glyph (FA Free has no `fa-tray`).
- `resources/scripts/labki-tweeki.js` polls the notifications API and decorates the user dropdown toggle + items with unread-count badges.

If a future Echo or Tweeki upgrade changes the rendering path, those workarounds may stop being needed — re-test on upgrade.

### Dark mode

The toggle button writes `<html data-bs-theme="dark|light">` and persists the choice in `localStorage` under key `labki-theme`. A small inline `<script>` in `<head>` applies the stored preference before paint to avoid a flash of light content.

If you add custom CSS for content elements, make sure it works in both themes — most Bootstrap components adapt automatically, but raw color values you write yourself won't.

### Anonymous users on private wikis

The platform configures Tweeki to hide the user menu (`PERSONAL`), the sidebar tools (`TOOLBOX`), and the subnav from anonymous users on private deployments. Anonymous users see a streamlined navbar with prominent "Log in" and "Request Account" buttons. If you're running a public wiki and want anon users to see the full navbar, override `$wgTweekiSkinHideAnon` in `LocalSettings.user.php`.

## Reverting

To roll back a deployment to Vector 2022:

```php
$wgDefaultSkin = 'vector-2022';
```

Users who explicitly chose Tweeki in their preferences will keep Tweeki — they need to switch back via Special:Preferences. The `$wgDefaultSkin` change only affects users with no explicit skin preference.
