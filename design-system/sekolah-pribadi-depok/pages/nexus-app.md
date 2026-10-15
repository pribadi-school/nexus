# Page override: Nexus, IT infrastructure dashboard (nexus.pribadidepok.sch.id)

> Per MASTER.md's LOGIC rule, this file extends/overrides `../MASTER.md` for
> Nexus only. Everything not listed here follows MASTER.md unchanged: the 8px
> spacing rhythm, the bold-label + lighter-supporting-line hierarchy, the
> reduced-motion rule, "no emojis as icons", visible focus, and "every colour
> traces to a role".
> Tokens live in `public/assets/css/tokens.css` (`:root`), the single place to
> change them.
>
> Nexus uses the same **flat app rules** as the other Pribadi apps (uniform
> inventory, parent–teacher consultation), restated in §2 because those files
> are not in this repo. What is new: the Nexus brand palette (§1), device
> status colours (§4) and the logo rules (§6).

## 1. Brand palette (owner decision, Oct 2026)

Nexus uses its own brand colours, taken from the school emblem, in place of
MASTER's blue/teal. MASTER's neutrals stay.

| Role | Hex / value | Token | Usage |
|---|---|---|---|
| Brand blue | `#1F4E9F` | `--color-brand-blue` | Logo, wordmark |
| Action | = brand blue | `--color-action` | Every button fill, link, active nav item, focus ring. White on it: 7.9:1 |
| Action hover | color-mix(action 80%, navy) | `--color-action-hover` | Hover/pressed fill |
| Action tint | color-mix(action 8%, surface) | `--color-action-tint` | Active nav background, info notices |
| Brand red (hub) | `#B91C1C` | `--color-brand-red` | The logo hub |
| Problem | = brand red | `--color-problem` | "Has problem" status, problem counts. 6.5:1 on white |
| Destructive | = brand red | `--color-destructive` | Delete buttons, errors. Replaces MASTER's `#dc2626` here, so the app has one red |
| Navy | `#0F2A5C` | `--color-navy` | Dark surfaces only: the login backdrop |
| Success | `#15803d` | `--color-success` | "Running well" / "OK" status, "Fix" notes. App extension (same green as the uniform app's "Masuk") |
| Warning | color-mix(accent 70%, foreground) ≈ `#af512a` | `--color-warning` | Warning notices (e.g. "the database is behind the app") |
| Tagline grey | `#4B5568` | `--color-tagline` | The tagline under the wordmark only |

Neutrals are MASTER's: foreground `#12233c`, body `#1c2733`, muted `#5a6472`,
surface `#ffffff`, page `#f2f5f9`, surface-muted `#e9eef5`, border
`rgba(18,35,60,.08)`, plus the app additions border-strong `rgba(18,35,60,.16)`
and overlay `rgba(18,35,60,.45)`.

MASTER's orange accent (`#f26522`) is not used as a fill or a status colour.
It only feeds the warning mix above.

## 2. Flat app rules (same as the other Pribadi apps)

- **Flat surfaces.** No decorative shadows or gradients. Cards, tables and
  inputs are separated by 1px `--color-border-strong`; hairline dividers inside
  them use `--color-border`. Only things that float above the page (toast,
  dialog, open menu) cast one soft shadow, `--shadow-e4`.
- **Corners:** 6 / 8 / 12 / 16px (`--radius-sm…xl`). Buttons and inputs are 8px
  rectangles, cards 12px, dialogs 16px. No pills, except status chips (6px).
- **Interaction:** hover and press change colour, background or border only.
  No lifts, no scale. Transitions 150ms (`--motion-ui`).
- **Tabs:** underline tabs (2px `action` bar), not filled pills.
- **Icons:** monochrome Lucide line icons in the current text colour, 16–20px,
  **never inside a coloured tile, circle or badge**. Only where they help
  scanning: navigation, buttons, row actions, notices, note kinds.
- **Touch targets** ≥44px on touch screens. Text in form fields is 16px on
  phones so the browser does not zoom on focus.

## 3. Type

- **UI font:** Inter (variable, self-hosted in `public/assets/fonts`).
- **Wordmark:** "nexus", always lowercase, Space Grotesk 600, letter-spacing
  −0.03em, `--color-brand-blue`. Space Grotesk is used for nothing else.
- **Tagline:** "Connecting to Pribadi School", IBM Plex Sans 400,
  `--color-tagline`. IBM Plex Sans is used for nothing else.

Data-dense scale (nothing below 12px):

| Role | Size / weight |
|---|---|
| Page title | 24px / 700 |
| Card title | 16px / 700 |
| Body, table cells | 14px / 400 |
| Controls, secondary text | 13px / 500–600 |
| Captions, table headers, chips | 12px / 600 |
| Metric figures (stat tiles) | 32px / 700 |
| Hero figure (one per view: the dashboard's problem count) | 48px / 700 |

Big standalone numbers (hero, stat tiles) keep Inter's proportional figures;
tabular figures only where numbers line up in a column (table cells, counts in
a list, serial numbers, IPs).

## 4. Device status (new)

Every status shows a word, never colour alone.

| Status | Stored | Label (StudyTouch, CCTV) | Label (Chromebooks) | Treatment |
|---|---|---|---|---|
| OK | `ok` | Running well | OK | `success` dot + word |
| Problem | `problem` | Has problem | Has problem | `problem` word on `problem-tint` chip; the row/card gets a `problem-tint` background and a 3px `problem` left border |

**Status meter** (dashboard tiles): one 8px bar split into Running well and
Has problem, with a 2px surface gap between the parts and labelled counts
underneath. Has problem = `--color-problem`; Running well =
`--color-success-track` (color-mix(success 40%, surface) ≈ `#a1ccb1`), a
lighter step on purpose: solid green next to red fails red–green colour
blindness (ΔE 4.2), the lighter step passes (ΔE 29.7, checked with the
dataviz palette validator). Its low contrast on white is why the labels under
the bar are mandatory.

**Status toggle:** a switch. On (green track, knob right) = Running well/OK,
off (red track, knob left) = Has problem. The visible word next to it always
names the current state. 44px tall hit area.

**Note kinds** (notes log + dashboard feed): Report (`message-square-text`,
neutral), Problem (`triangle-alert`, problem), Fix (`wrench`, success),
Firmware (`cpu`, action), Status (`refresh-cw`, neutral), Reassigned
(`arrow-left-right`, neutral). Shown as icon + word in the kind's colour, no
tile.

## 5. Layout

- **≥1024px:** fixed 248px sidebar (white, 1px right border). Mark + wordmark
  at the top, nav, then the signed-in user and Sign out at the bottom.
- **<1024px:** 56px top bar (mark + wordmark, account button) and a bottom nav
  with the four sections (icon + 12px label, 2px `action` bar on the active
  item), padded for the phone's safe area.
- Content: page padding 16px on phones, 32px on desktop, max width 1200px.
- Cards on the page canvas (`--color-surface-page`); lists inside one bordered
  card with hairline dividers.
- **Loading:** skeleton blocks in `surface-muted` with a 1.2s opacity pulse
  (none under reduced motion); buttons show a spinning `loader-circle` and are
  disabled while they work.
- **Empty states:** a muted line icon (32px, no tile), a 16px/600 title, one
  supporting line, and the primary action if there is one.

## 6. Logo

- Master: `public/assets/brand/logo-mark.svg` (pentagon from the school emblem;
  the red hub is where every device connects).
- White version for dark backgrounds: `logo-mark-white.svg`. The hub's stroke
  matches the background (navy `#0F2A5C`).
- 32px and below: `favicon.svg` (pentagon outline stroke 11 + red hub r=18, no
  spokes or nodes).
- App icons: `app-icon-512.png`, `app-icon-192.png`, `apple-touch-icon.png`
  (mark at 78% on white), built by `npm run build:assets`.
- Lockup: mark + wordmark, mark height ≈ 1.3× the wordmark's font size. The
  login page adds the tagline underneath.
- The only hex values outside the token file are the brand SVG files and
  the chevron inside the select arrow (a data URI cannot read tokens).

## 7. Pre-delivery checklist (in addition to MASTER's)

- [ ] No icon inside a coloured tile or circle
- [ ] Interactive blue is `--color-action`
- [ ] No shadow except `--shadow-e4` on floating layers
- [ ] Every status shows a word, not only a colour
- [ ] Touch targets ≥44px on touch screens; works one-handed at 375px
- [ ] Empty and loading states present for every list
