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
- `resources/styles/labki-tweeki.less` maps `fa-tray::before` to FontAwesome's inbox glyph (FA Free has no `fa-tray`).
- `resources/scripts/labki-tweeki.js` polls the notifications API and decorates the user dropdown toggle + items with unread-count badges.

If a future Echo or Tweeki upgrade changes the rendering path, those workarounds may stop being needed — re-test on upgrade.

### Dark mode

The toggle button stamps two co-applied signals on `<html>`, both maintained on every flip and pre-applied by an inline `<script>` in `<head>` so dark-mode users don't see a flash of light content on first paint:

| Signal | Used by |
| :--- | :--- |
| `data-bs-theme="dark"` (attribute) | Bootstrap 5.3 — drives the navbar, dropdowns, modals, alerts, btn variants. |
| `skin-theme-clientpref-night` (class) | MediaWiki core / Codex / extensions — Vector 2022, Minerva, and any night-mode-aware extension look at this class. |

The light-mode equivalents are `data-bs-theme="light"` and `skin-theme-clientpref-day`. The choice persists in `localStorage["labki-theme"]`.

#### Theming token vocabulary

`resources/styles/tokens-theme-base.less` declares the `--labki-*` design tokens in OKLCH for the light theme; `tokens-theme-dark.less` overrides them with dark values. Both are imported by `labki-tweeki.less`. To retune for a deployment without forking the platform, redefine the tokens in `MediaWiki:Tweeki.css`:

```css
:root {
    --labki-accent: oklch(50% 0.15 30);   /* warmer accent */
    --labki-primary: oklch(25% 0.08 280); /* purple navbar */
}
```

The same tokens are referenced by SchemaSync TemplateStyles (`Template:UI/card/styles.css` etc.), so retuning here propagates to schema-driven templates too.

#### Content-author dark-mode classes

When writing wikitext or TemplateStyles, two MediaWiki classes opt content out of automatic dark treatment:

- `notheme` — preserves an element's original colors across all themes. Use on diagrams, screenshots, brand-locked images, or any content where the colors carry semantic meaning.
- `skin-invert` — applied to dark-on-transparent images that should invert in dark mode (e.g., a black-line diagram on transparent bg). The skin filters the image so it reads on the dark surface.

For per-template dark-mode rules, target the canonical class directly:

```css
/* In a TemplateStyles styles.css */
.skin-theme-clientpref-night .my-template-class {
    background: var(--labki-bg-subtle);
}
```

If you add custom CSS for content elements, make sure it works in both themes — most Bootstrap components adapt automatically, but raw color values you write yourself won't. Reference the `--labki-*` tokens (with literal fallback) instead of hard-coding hex:

```css
.my-element {
    background: var(--labki-bg-subtle, #f4f6f8);
    color: var(--labki-text, #2c2c2c);
}
```

### Anonymous users on private wikis

The platform configures Tweeki to hide the user menu (`PERSONAL`), the sidebar tools (`TOOLBOX`), and the subnav from anonymous users on private deployments. Anonymous users see a streamlined navbar with prominent "Log in" and "Request Account" buttons. If you're running a public wiki and want anon users to see the full navbar, override `$wgTweekiSkinHideAnon` in `LocalSettings.user.php`.

## Reverting

To roll back a deployment to Vector 2022:

```php
$wgDefaultSkin = 'vector-2022';
```

Users who explicitly chose Tweeki in their preferences will keep Tweeki — they need to switch back via Special:Preferences. The `$wgDefaultSkin` change only affects users with no explicit skin preference.
