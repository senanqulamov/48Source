# 48Source redesign — implementation specification for Codex

You are implementing a finished UI redesign in the real 48Source application (Laravel, server-rendered HTML). This document describes every token, component, page and behaviour of the approved prototype.

**Two inputs go together:**

1. `48source-redesign.html` — the approved interactive prototype. It is the visual source of truth. Open it in a browser and inspect it whenever this document and your result disagree.
2. This file — the explanation of what is in that prototype and how to carry it over.

All pixel, colour and font values below were measured from the rendered prototype at 1440 × 900 in light theme unless stated otherwise.

---

## 0. Ground rules

1. **Design only.** Do not change routes, controllers, models, validation rules, field `name` attributes, permissions or database structure unless a section below says a feature is new and needs backend work.
2. **Do not copy the prototype's JavaScript architecture.** The prototype is a single-page demo that renders HTML strings from in-memory data. In the real app, pages stay server-rendered. Port the **CSS, markup patterns and small behaviour scripts** (popover, modal, drawer, toast, custom select, date picker, tooltips, table controls).
3. **Do not copy demo data.** Company names, people, RFQ numbers, prices and dates in the prototype are invented. "Today" is fixed at 21 November 2026 in the demo.
4. **Old internal names must not appear** in the interface: DPanel, SCMAI.
5. **Interface language is English** in the prototype. Keep all strings translatable (the app has a language menu).
6. **One accent rule:** lime is for the single main action on a screen and for the brand mark. Cobalt blue is for links, selection and focus. Never use lime for selection or blue for the main action.
7. Put the tokens in one CSS file as custom properties and build components on them. Class names in the prototype are short (`.btn`, `.card`, `.tbl`); you may namespace them, but keep one class per component.
8. Where a section is marked **NEW**, the feature does not exist in the current app and needs backend work. Where it is marked **DEMO**, the prototype only simulates it.

---

## 1. Design tokens

### 1.1 Colour — light theme

| Token | Value | Use |
|---|---|---|
| `--bg` | `#E6E9F0` | App frame behind sidebar and around the work sheet |
| `--sheet` | `#F7F8FB` | Background of the main work area |
| `--surface` | `#FFFFFF` | Cards, popovers, modals, drawers |
| `--surface-2` | `#F4F5F9` | Input fill, hover rows, section header rows, drawer footer |
| `--sunken` | `#ECEEF4` | Segmented-control track, tab track, progress track, neutral badge |
| `--line` | `#DFE2EA` | Hairline borders and dividers |
| `--line-2` | `#C5CAD6` | Control borders (inputs, default buttons), switch off |
| `--text` | `#12151C` | Primary text |
| `--text-2` | `#4A5262` | Secondary text, descriptions, inactive nav |
| `--text-3` | `#7B8394` | Tertiary text: table headers, group labels, timestamps, placeholders |
| `--ink` | `#12151C` | Ink surfaces: active nav pill, ink board, active tab, toast, bulk bar, tooltip |
| `--ink-2` | `#A9B1C2` | Secondary text on ink |
| `--brand` | `#C4F53C` | Lime: brand mark, primary button, count badges, done steps |
| `--brand-h` | `#B3E826` | Primary button hover |
| `--brand-edge` | `#9FD215` | Primary button border, "best" column top bar |
| `--on-brand` | `#12151C` | Text on lime |
| `--brand-soft` | `#F3FCD6` | Recommended column tint, guide strip |
| `--primary` | `#2A45F5` | Cobalt: links, focus, selected state, charts |
| `--primary-h` | `#1B33D6` | Link hover |
| `--primary-soft` | `#E7EAFF` | Selected row, selected card, info badge, focus ring |
| `--on-primary` | `#FFFFFF` | Text on cobalt |
| `--ok` / `--ok-soft` | `#0B7F57` / `#DDF5EA` | Success |
| `--warn` / `--warn-soft` | `#8F5A00` / `#FDF0D2` | Warning |
| `--bad` / `--bad-soft` | `#C93126` / `#FCE6E3` | Error, destructive |
| `--info` / `--info-soft` | `#2A45F5` / `#E7EAFF` | Informational (same as primary) |
| `--mute` / `--mute-soft` | `#4A5262` / `#ECEEF4` | Neutral badge |
| `--signal` | `#F2A20C` | Amber fill: rating star, "at risk" bars, warning timeline dot |

Fixed colours used on ink surfaces (same in both themes): bad `#FF9486`, warn `#FFC960`, ok = lime.

### 1.2 Colour — dark theme (only the values that change)

| Token | Value |
|---|---|
| `--bg` | `#0A0C11` |
| `--sheet` | `#11141B` |
| `--surface` | `#181C25` |
| `--surface-2` | `#1D222C` |
| `--sunken` | `#141820` |
| `--line` / `--line-2` | `#272C38` / `#394050` |
| `--text` / `--text-2` / `--text-3` | `#EEF1F6` / `#A9B1C2` / `#737C8E` |
| `--ink` | `#1D222C` |
| `--brand-soft` | `rgba(196,245,60,.09)` |
| `--primary` / `--primary-h` | `#93A3FF` / `#AEBBFF` |
| `--primary-soft` / `--on-primary` | `#1D2550` / `#0B1030` |
| `--ok` / `--ok-soft` | `#58D3A0` / `#10301F` |
| `--warn` / `--warn-soft` | `#F1BE5C` / `#33270C` |
| `--bad` / `--bad-soft` | `#FF8A7D` / `#3A1A16` |
| `--mute-soft` | `#232834` |

Dark-theme inversions (do these explicitly, they are not token swaps):

- Active sidebar item, active tab, active page number, selected calendar day, checked checkbox: **lime background with `#12151C` text** (in light they are ink with white or lime content).
- Count badge inside the active sidebar item: `#12151C` background, lime text.
- Tooltip: `#EEF1F6` background, `#12151C` text.
- Ink board and bulk bar get a `1px` border in `--line` / `--line-2`.
- Theme is set with `data-theme="light|dark"` on `<html>`. Default is light.

### 1.3 Shape, elevation, spacing

| Token | Value |
|---|---|
| `--r` (controls) | `10px` |
| `--r-lg` (cards) | `16px` |
| Pills (buttons, search, badges, tabs, chips) | `999px` |
| Work sheet radius | `22px` |
| Modal and drawer radius | `20px` |
| Popover radius | `14px`; items inside `9px` |
| `--sh` (card) | `0 1px 0 rgba(18,21,28,.04)` (none in dark) |
| `--sh-pop` (popover, modal, drawer, toast) | `0 18px 44px rgba(18,21,28,.16), 0 2px 6px rgba(18,21,28,.08)` |
| `--ease` | `cubic-bezier(.2,.8,.2,1)` |
| Top bar height | `60px` |
| Sidebar width | `236px`, collapsed `60px`, mobile drawer `264px` |

Spacing scale in use: 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 24, 28 px. Page padding is `24px 28px 56px`; grid gap between cards is `16px`; card body padding `18px`; card header padding `14px 18px`.

---

## 2. Typography

### 2.1 Families

- **Interface:** `Onest` (400, 500, 600, 700). Stack: `'Onest', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif`.
- **Display:** `Bricolage Grotesque` (500–800, optical size axis). Stack: `'Bricolage Grotesque', 'Onest', ui-sans-serif, system-ui, sans-serif`.
- No monospace face anywhere. Reference numbers and money use the interface font with `font-variant-numeric: tabular-nums`.
- Loaded from Google Fonts: `family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Onest:wght@400;500;600;700&display=swap`. Self-host them in production.
- Body: `14px / 1.45`, weight 400, colour `--text`, antialiased.
- No ALL-CAPS labels anywhere. Sentence case throughout.

### 2.2 Text styles (measured)

| Style | Font | Size / line | Weight | Letter-spacing | Colour |
|---|---|---|---|---|---|
| Page title | Bricolage | 28 / 31.4 | 700 | −0.7px | `--text` |
| Page description | Onest | 14.5 / 21 | 400 | 0 | `--text-2` |
| Login title | Bricolage | 32 / 35.8 | 700 | −0.8px | `--text` |
| Login panel headline | Bricolage | 44 / 46.2 | 700 | −1.32px | `#FFFFFF` |
| Section heading (`.h2`) | Bricolage | 18 / 21.6 | 700 | −0.18px | `--text` |
| Modal / drawer title | Bricolage | 18 / 21.6 | 700 | −0.18px | `--text` |
| Ink-board number | Bricolage | 34 / 35.7 | 700 | −1.02px | `#FFFFFF` |
| Comparison total | Bricolage | 18 / 21.6 | 700 | −0.18px | `--text` |
| Comparison score | Bricolage | 22 / 22 | 700 | 0 | `--text` |
| Verdict name | Bricolage | 17 / 21.3 | 700 | 0 | `--text` |
| Calendar month | Bricolage | 16 | 700 | 0 | `--text` |
| Brand word "48Source" | Bricolage | 19 / 19 | 700 | −0.38px | `--text` |
| Brand mark "48" | Bricolage | 15 / 15 | 800 | −0.6px | `#12151C` on lime |
| Card title | Onest | 14.5 / 21 | 600 | 0 | `--text` |
| Card subtitle | Onest | 12 / 17.4 | 400 | 0 | `--text-2` |
| Body / table cell | Onest | 14 / 20.3 | 400 | 0 | `--text` |
| Table primary line (`.t1`) | Onest | 14 / 20.3 | 500 | 0 | `--text` |
| Table secondary line (`.t2`) | Onest | 12 / 17.4 | 400 | 0 | `--text-2` |
| Table header | Onest | 12.5 / 18.1 | 500 | 0 | `--text-3` (active sort: `--text`, 600) |
| Sidebar item | Onest | 14 / 20.3 | 500 | 0 | `--text-2`; active `#FFFFFF` |
| Sidebar group label | Onest | 12 / 17.4 | 500 | 0 | `--text-3` |
| Sidebar count | Onest | 11 | 700 | 0 | `#12151C` on lime |
| Breadcrumb | Onest | 14 / 20.3 | 400 (current 600) | 0 | `--text-2` (current `--text`) |
| Workspace pill | Onest | 12 / 17.4 | 600 | 0 | `--text` |
| Button | Onest | 14 / 20.3 | 500 (primary 600) | 0 | see §4.1 |
| Small button | Onest | 12.5 / 18.1 | 500 (primary 600) | 0 | |
| Field label | Onest | 12.5 / 18.1 | 500 | 0 | `--text`; "optional" suffix 400 `--text-3` |
| Input text | Onest | 14 / 20.3 | 400 | 0 | `--text`; placeholder `--text-3` |
| Help text | Onest | 12 / 17.4 | 400 | 0 | `--text-2` |
| Error text | Onest | 12 | 400 | 0 | `--bad` |
| Badge | Onest | 12 / 17.4 | 600 | 0 | per status |
| Tag | Onest | 11.5 / 16.7 | 400 | 0 | `--text-2` |
| Keyboard hint (`kbd`) | Onest | 10.5–11 | 500 | 0 | `--text-3` |
| Ink-board label | Onest | 13 / 18.9 | 500 | 0 | `--ink-2` |
| Ink-board sub-line | Onest | 12.5 / 18.1 | 400 | 0 | `--ink-2` (or bad/warn/ok variants) |
| Facts strip label / value | Onest | 11.5 / 13.5 | 400 / 600 | 0 | `--text-2` / `--text` |
| Key–value term / value | Onest | 14 | 400 / 500 | 0 | `--text-2` / `--text` |
| Timeline timestamp | Onest | 11.5 / 16.7 | 400 | 0 | `--text-3` |
| Popover heading | Onest | 11.5 / 16.7 | 600 | 0 | `--text-2` |
| Popover item | Onest | 14 / 20.3 | 400 (selected 600) | 0 | `--text` |
| Tooltip | Onest | 12 / 17.4 | 500 | 0 | `#FFFFFF` on ink |
| Toast | Onest | 13 / 18.9 | 400 | 0 | `#FFFFFF`; demo sub-line 12px at 70% opacity |
| Chart axis text | Onest | 10.5 | 400 | 0 | `--text-3`; end label 600 `--text` |
| Table footer | Onest | 13 / 18.9 | 400 | 0 | `--text-2` |
| Message time | Onest | 11 | 400 | 0 | inherits at 70% opacity |

---

## 3. Icons and brand mark

- **Icons:** inline SVG, Lucide style. `viewBox="0 0 24 24"`, `fill="none"`, `stroke="currentColor"`, `stroke-width="1.8"`, round caps and joins. Use the Lucide package in production; names used: layout-dashboard, file-text, columns, package, users, user, building, activity, bar-chart, sliders, shield-check, search, bell, plus, x, check, chevron-down/up/left/right, menu, more-horizontal, sun, moon, globe, download, upload, filter, chevrons-up-down, clock, alert-triangle, info, mail, map-pin, store, truck, clipboard, paperclip, trash, pencil, eye, log-out, panel-left, star, calendar, arrow-right, send, inbox, flag, server, refresh, award, lock, camera, help-circle.
- **Sizes:** 12–13 inside chips and sort arrows, 14 in small buttons, 15–16 default, 17 in sidebar and top bar, 18 in shortcut tiles, 22 in empty states and confirm tiles.
- Icons are always `aria-hidden`; icon-only buttons carry an `aria-label`. No emoji as icons.
- **Brand mark:** a 30 × 30 square, radius 9, fill `--brand`, containing "48" in Bricolage 800 15px `#12151C`. Next to it the word "48Source" (Bricolage 700 19px). Same mark in both themes and on the login page.

---

## 4. Components

### 4.1 Buttons

| Variant | Height | Padding | Radius | Background | Border | Text |
|---|---|---|---|---|---|---|
| Default `.btn` | 36 | 0 15 | 999 | `--surface` | 1px `--line-2` | `--text`, 500 |
| Primary `.btn-p` | 36 | 0 15 | 999 | `--brand` | 1px `--brand-edge` | `#12151C`, 600 |
| Ghost `.btn-g` | 36 | 0 15 | 999 | transparent | transparent | `--text`, 500 |
| Danger outline `.btn-d` | 36 | 0 15 | 999 | `--surface` | 1px `--line-2` | `--bad` |
| Danger solid `.btn-d.solid` | 36 | 0 15 | 999 | `--bad` | 1px `--bad` | `#FFFFFF` |
| Small `.btn-sm` (any variant) | 30 | 0 12 | 999 | — | — | 12.5px |
| Icon button `.icon-btn` | 34 × 34 | — | 999 | transparent | none | `--text-2` |

States: hover on default → `--sunken`; primary hover → `--brand-h` plus glow `0 4px 14px rgba(159,210,21,.35)`; icon button hover → `--sunken`, `--text`. Active: `transform: scale(.96)` (icon button `.92`). Disabled: 45% opacity, `not-allowed`. Icon + label gap 7px. Transitions 120–150ms. Focus-visible: 2px `--primary` outline, 2px offset.

One primary button per screen region. The top bar carries the role's main action as a small primary button ("New RFQ" for admin/buyer, "Quick quote" for supplier).

### 4.2 Text inputs, textarea

- Height 38, radius 10, border 1px `--line-2`, fill `--surface-2`, padding `0 10px`.
- Hover: border `--text-3`. Focus: border `--primary`, ring `0 0 0 3px var(--primary-soft)`, fill `--surface`, no outline.
- Invalid: border `--bad` and a 320ms horizontal shake (±4px). Error text below with a 13px alert icon.
- Textarea: padding `8px 10px`, line-height 1.5, min-height 84, no resize handle, **auto-grows** to fit content on input.
- Number inputs: native spinners hidden. Search inputs: native clear button hidden.
- Input with leading icon: icon absolutely positioned 10–13px from the left, input `padding-left: 32–36px`.
- Field wrapper: label above (12.5px/500), 5px gap, help or error text below. Two-column form grid with 16px gap, single column below 640px.

### 4.3 Custom select

The native `<select>` stays in the DOM (hidden, still submits and validates). A trigger button is inserted after it.

- Trigger: looks like an input (38 high, radius 10, fill `--surface-2`), value text left, 15px chevron right in `--text-3`.
- Opens a popover (see §4.14) at least as wide as the trigger, min 200px, max-height 300px with scroll.
- Each option is a row (34 high, radius 9). The current one is weight 600 with a 15px cobalt check at the right.
- Keyboard: Arrow up/down moves, Enter/Space selects, Escape closes and returns focus to the trigger. Opening focuses the current option.
- Selecting sets the native select's value and fires `input` and `change`.
- Clicking a `<label for>` that points to the hidden select focuses the trigger.
- Variant in the sidebar role switcher: borderless, transparent, weight 600.

### 4.4 Custom date picker

Wraps a hidden `<input type="date">` the same way.

- Trigger: input look, 15px calendar icon left, then the date as "10 Dec 2026" or placeholder "Select a date" in `--text-3`.
- Popover 308px wide, 12px padding:
  - Header: previous / next icon buttons, month and year centred (Bricolage 700 16px).
  - Weekday row, Monday first: "Mo Tu We Th Fr Sa Su", 11.5px/500 `--text-3`.
  - Day grid 7 columns, 2px gap. Day button 38px high, circular, 14px/500.
  - Today: inset ring `1.5px --line-2`. Selected: ink circle with lime number (dark theme: lime circle, ink number). Hover: `--sunken`. Before `min`: 45% opacity, disabled.
  - Footer (top border): "Clear" ghost button, then shortcuts "In a week", "In 2 weeks", "In a month".
- Keyboard: arrows move by day (left/right) and week (up/down), Enter selects, Escape closes.

### 4.5 Checkbox, switch, radio card

- **Checkbox:** 18 × 18, radius 6, border 1.5px `--line-2`, fill `--surface`. Checked: ink fill with a lime tick drawn with borders, 180ms pop-in. Indeterminate: ink fill with a 9 × 2 lime dash. Active: scale .88. Dark theme: lime fill, ink tick.
- **Switch:** 34 × 20, radius 10, off `--line-2`, on `--primary`. Knob 16px white, slides 14px in 120ms. `role="switch"`, `aria-checked`.
- **Radio card:** button with border 1px `--line-2`, radius 14, padding 12, icon + bold title + 12px description. Selected: border and inset 1px ring `--primary`, fill `--primary-soft`. Used for request type and theme choice. `role="radio"` inside a `radiogroup`.
- **Pick row** (supplier selection): same as radio card but with a checkbox square at the left, `role="checkbox"`.

### 4.6 Segmented control and tabs

- **Segmented (`.seg`):** track `--sunken`, radius 999, 3px padding. Buttons 30 high, `0 13px`, 14px/500 `--text-2`. Selected: `--surface` fill, `--text`, small shadow. Optional count after the label: 11px/500 `--text-3`. `aria-pressed` on each.
- **Tabs (`.tabs`):** same track (`--sunken`, radius 999, 3px padding, width fits content). Tab `7px 14px`, 14px/500 `--text-2`. Selected: **ink pill, white text** (dark: lime pill, ink text). Optional count chip 11px in a rounded 8px box. `role="tablist"` / `role="tab"`, `aria-selected`. Tab content fades up 240ms when switched.

### 4.7 Badges, tags, chips

- **Status badge:** height 23, radius 999, padding `0 9px`, 12px/600, a 6px dot before the text in the text colour.

| Variant | Background | Text |
|---|---|---|
| neutral | `--mute-soft` | `--mute` |
| info | `--info-soft` | `--info` |
| ok | `--ok-soft` | `--ok` |
| warn | `--warn-soft` | `--warn` |
| bad | `--bad-soft` | `--bad` |

- **Plain badge** (no dot) for labels such as "Recommended", "Lowest price", "Partial".
- **Tag:** 11.5px, padding `1px 7px`, radius 6, `--mute-soft` / `--text-2`. For types and categories.
- **Toggle chip:** height 28, radius 14, border 1px `--line-2`. On: border `--primary`, fill `--primary-soft`, text `--primary`, leading check. Off: struck through, `--text-3`.

Status → variant mapping:

| Variant | Statuses |
|---|---|
| neutral | Draft, Pending, Disabled, Not started, Not selected, Not sent, No response |
| info | Open, Issued, Acknowledged, Invited, Viewed, Scheduled, New, Confirmed, New invitation |
| warn | Closed, In transit, Under review, Conditional, At risk, In progress, Ready for pickup, Dispatched, Low, Paused, On leave, Draft in progress, Degraded, Under evaluation |
| ok | Awarded, Delivered, Invoiced, Active, Passed, On track, Quoted, Submitted, Completed, In stock, Accepted, Operational |
| bad | Cancelled, Suspended, Failed, Breached, Overdue, Declined, Unavailable, Lost, Rejected |

### 4.8 Card

- Background `--surface`, border 1px `--line`, radius 16, shadow `--sh`.
- Header: min-height 54, padding `14px 18px`, bottom border; title (14.5/600), optional subtitle (12px `--text-2`), spacer, optional action (link or small button).
- Body padding 18. "Flush" cards hold a table or list with no body padding.
- Grids: `2fr 1fr`, `1fr 1fr` and `1fr 1fr 1fr`, gap 16; all collapse to one column below 1180px (two-column grid below 900px).

### 4.9 Ink board (summary strip)

The signature element. Every overview page opens with one.

- Container: fill `--ink`, radius 18, no border, four equal columns.
- Cell: padding `18px 20px`, 4px gap, left divider `1px rgba(255,255,255,.10)` (none on the first).
- Content: label (13/500 `--ink-2`), number (Bricolage 34/700 white), sub-line (12.5 `--ink-2`, or `#FF9486` bad, `#FFC960` warn, lime ok).
- Clickable cells are buttons: hover fill `rgba(255,255,255,.07)` and the number turns lime.
- On page entry, integer numbers count up from 0 over 650ms (ease-out cubic).
- Below 640px: two columns, number 28px.

### 4.10 Shortcut tile

- Button, fill `--surface`, border 1px `--line`, radius 16, padding `14px 16px`, 12px gap.
- Left: 40 × 40 lime square, radius 11, 18px ink icon. Right: title (14.5/600) and one line (12px `--text-2`, ellipsis).
- Hover: lifts 2px, border `--text`, shadow `0 8px 20px rgba(18,21,28,.08)`; the icon square rotates −6° and scales 1.06. Active: scale .98.
- Four per row, two below 1180px, one below 640px.

### 4.11 Facts strip, key–value list, list, timeline, progress, note, empty, skeleton

- **Facts strip:** one bordered white box, radius 16, cells separated by right borders. Cell padding `10px 16px`, label 11.5px `--text-2`, value 13.5/600.
- **Key–value (`dl`):** grid `130px 1fr`, gap `9px 12px`; term `--text-2`, value 500.
- **List:** rows `10px 16px`, bottom border `--line`, flex with 12px gap.
- **Timeline:** left rail 1px `--line`; dot 7px (`--line-2` default; ok, info, bad colours; warn uses `--signal`) with a 3px surface ring; text 14px; timestamp below 11.5px `--text-3`.
- **Progress bar:** track 6px, `--sunken`, radius 3; fill `--primary` by default, or ok / signal / bad. Fills from zero in 800ms on page entry.
- **Note (inline message):** flex, 10px gap, padding `10px 12px`, radius 12, 12.5px text, icon at left. Variants: info (`--info-soft`), warn, ok, bad.
- **Empty state:** centred, padding `48px 24px`; 44 × 44 `--sunken` tile (radius 10) with a 22px icon; title Bricolage 17/700; one sentence max 44 characters wide; optional button. Always says what to do next.
- **Skeleton:** 12px bars, radius 4, shimmer gradient between `--sunken` and `--line`, 1.3s loop.

### 4.12 Avatar

Circle with initials, 11px/700. Sizes 28 (default) and 44 (15px text). Five colour pairs, chosen by a stable hash of the name:

| # | Background | Text |
|---|---|---|
| 0 | `#E7EAFF` | `#2A45F5` |
| 1 | `#FFE8D6` | `#9A4500` |
| 2 | `#DDF5EA` | `#0B7F57` |
| 3 | `#EEF9C9` | `#4A6800` |
| 4 | `#FCE6F1` | `#A3246B` |

### 4.13 Request progress track (RFQ lifecycle) — NEW

A white bar (radius 16, padding `14px 18px`) under the RFQ header.

- Steps: Draft → Open for quotes → Closed · comparing → Awarded. Each has a 22px circle and a 12.5px label; connectors are 22 × 2px.
- Done: lime circle with ink check; connector after it `--brand-edge`. Current: ink circle, white number, with a slow pulse ring (2s, cobalt at 45% → 0). Future: `--sunken` circle, `--text-3`.
- To the right: one sentence stating the next step, and one button for it:

| Status | Sentence | Button |
|---|---|---|
| Draft | Next step: publish this request so the invited suppliers can quote. | Publish (primary) |
| Open | Now: waiting for quotations — X of N received. Quoting closes {date} ({relative}). | Remind suppliers |
| Closed | Next step: compare the X quotations and accept one by {deadline + 5 days}. | Compare quotations (primary) |
| Awarded | Done: awarded to {supplier}. Follow delivery in the order. | Open {PO number} |
| Cancelled | This request was cancelled. You can duplicate it to start again. | Duplicate as draft |

### 4.14 Popover (menus, select lists, filters, calendar, notifications)

- Fixed position, `z-index` above modals. Fill `--surface`, border 1px `--line`, radius 14, padding 6, shadow `--sh-pop`, max-height 70vh.
- Opens below the anchor, right-aligned by default; flips above if there is no room; kept 8px inside the viewport.
- Entry animation: 160ms, from `opacity 0; translateY(-6px) scale(.97)`.
- Heading row: 11.5px/600 `--text-2`, padding `8px 10px`, optional link at the right ("Clear", "Mark all read").
- Item: min-height 32–34, padding `7px 10px`, radius 9, icon + label, optional right slot. Hover/focus `--sunken`. Destructive item text `--bad`. Divider: 1px `--line`, 4px margin.
- Closes on outside click, Escape (focus returns to the anchor), or item choice. Checkbox items keep it open.
- Arrow up/down moves focus between items.

### 4.15 Modal

- Scrim `rgba(10,18,22,.45)` with `backdrop-filter: blur(1.5px)`, fades in 180ms.
- Dialog: width `min(520px, 100vw − 32px)` (wide variant 720), radius 20, fill `--surface`, shadow `--sh-pop`. Enters 240ms from `translateY(14px) scale(.97)`.
- Header padding `20px 22px 4px`, no border: title (Bricolage 18/700) and close icon button.
- Body padding `10px 22px 20px`. Footer padding `4px 22px 20px`, buttons right-aligned, no border.
- **Confirmation layout:** a 44 × 44 tile (radius 14) at the left of the body — lime with ink check for normal, `--bad-soft` with `--bad` alert icon for destructive. Destructive confirm button is solid red.
- Closing animates out in 170ms. Escape and scrim click close. Tab is trapped inside. Focus goes to the first field or the primary button on open.

### 4.16 Drawer

- Floats at the right: `margin: 8px`, height `calc(100% − 16px)`, width `min(520px, 100vw)`, radius 20. Slides in 280ms from 48px right; out in 170ms.
- Header padding `18px 20px` with bottom border; body padding 20, scrolls; footer padding `14px 20px`, fill `--surface-2`, top border.
- Below 640px it becomes full-screen with no radius.
- Used for: supplier profile, edit RFQ, invite/edit user, edit product, help.

### 4.17 Toast

- Stack at bottom-right (20px inset; full-width with 10px inset on mobile). Width 340.
- Ink fill, white text 13px, radius 14, padding `10px 14px`, leading icon in lime.
- A 3px lime line along the bottom shrinks to zero over 4.4s; then the toast slides right and fades (200ms).
- Enters 320ms with a slight overshoot. `role="status"`.
- DEMO: actions that do nothing real add a second line, "Demonstration only — nothing is sent or saved." Remove that in production.

### 4.18 Tooltip

- Replaces native `title` tooltips. Ink pill, white 12px/500, padding `5px 9px`, radius 8, max-width 260.
- Appears below the element, centred; to the right for collapsed sidebar items; flips above near the bottom edge.
- Shows on hover and on keyboard focus; hides on mouse-down, blur and scroll.

### 4.19 Charts

Inline SVG, no chart library.

- **Grouped bar chart:** bars 16px max, radius 2; series A `--primary`, series B `--line-2`; horizontal grid lines 1px `--line`; y labels right-aligned, x labels centred, 10.5px `--text-3`. Bars grow from the baseline in 700ms.
- **Line chart:** 2px `--primary` line, area below at 8% opacity, last point marked with a ringed dot and a bold value label. The line draws itself in 1.1s.
- **Horizontal bars:** grid `150px 1fr 54px` — label, progress bar (8px), value.
- Legends: 9 × 9 squares, radius 2, 12px text. Every chart has a title and subtitle stating what is measured.

### 4.20 Other

- **Wizard steps:** one bordered bar (radius 16), equal cells; each has a 22px numbered circle and a label (13/600). Current: fill `--surface-2`, 3px lime underline, circle ink with lime number. Done: lime circle with check. Cells are buttons.
- **Drop zone:** dashed 1.5px `--line-2`, radius 16, fill `--surface-2`, centred icon, text and "Add file" button. While a file is dragged over: border `--primary`, fill `--primary-soft`, scale 1.01.
- **File row:** bordered row, radius 10, paperclip, name, remove button.
- **Message bubbles:** radius 16, padding `8px 12px`, max-width 72% (88% on mobile). Other party: `--sunken`. Mine: `--primary` with white text, right-aligned.
- **Task button** (field and market workers): white, border `--line`, radius 14, padding `14px 16px`, large tap target.
- **Selection colour:** `::selection` lime with `#12151C` text.
- **Scrollbars:** thin, thumb `--line-2`, radius 10, transparent track.

---

## 5. Application shell

```
┌─────────────── frame (--bg) ───────────────────────────────────┐
│ sidebar 236px │ ┌──────── work sheet (--sheet, radius 22) ───┐ │
│ (on frame,    │ │ top bar 60px                               │ │
│  no border)   │ │────────────────────────────────────────────│ │
│               │ │ scrolling content, page max-width 1560     │ │
│               │ └────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────┘
```

- Grid: `236px minmax(0,1fr)`, full viewport height. The sheet has `margin: 8px 8px 8px 0`, a 1px `--line` border and hides overflow; only the content area scrolls.
- Narrow pages (worker screens) use max-width 760.

### 5.1 Sidebar

- Sits directly on the frame colour, no border, no fill.
- Top (60px): brand mark + "48Source", linking to the role's home.
- Navigation: groups with a sentence-case label (12/500 `--text-3`, padding `12px 12px 5px`). Items 36 high, radius 10, padding `0 12px`, 17px icon, 10px gap.
  - Hover: `rgba(18,21,28,.06)`, text `--text`.
  - Active: **ink pill, white text, lime icon** (dark: lime pill, ink text). `aria-current="page"`.
  - Count badge at the right: lime pill, ink text, 11/700.
- Footer, top to bottom:
  1. "Simple view" row with a switch (off by default).
  2. Role switcher in a dashed box — **DEMO only, remove in production.**
  3. Settings item.
- Collapsed (60px): only icons, centred; labels, counts, role box and Simple view row hidden; thin dividers between groups; item labels appear as tooltips. Width change animates 260ms.
- Below 900px: off-canvas drawer 264px wide, slides in 280ms over a scrim; opened by a menu button in the top bar; closes on outside tap, Escape, or navigation.

### 5.2 Top bar

Left to right:

1. Menu button (mobile only) / collapse-sidebar button (desktop).
2. Workspace pill: shield icon in cobalt + "{Role} workspace" (hidden below 900px).
3. Breadcrumb: organisation › section › record. Last item bold. Hidden below 640px.
4. Global search (pushed right): pill input 38 high, fill `--surface`, placeholder "Search or jump to a page…", `Ctrl K` hint.
5. Main action for the role (small primary button; label hidden below 900px).
6. Language menu (globe + code), theme toggle (moon/sun), Help (?), Notifications (bell with a red 7px dot when unread), account button (avatar + name).

Below 640px only menu, search, main action, help, notifications and avatar remain; language and theme move into the account menu.

### 5.3 Global search — partly NEW

- Focusing it (click or Ctrl/⌘ K) opens a popover titled "Go to" listing every page the role can open.
- Typing filters pages and adds matching records: RFQs, purchase orders and suppliers for admin/buyer; invitations and orders for suppliers; products and orders for sellers; assignments for field workers; tasks for market workers.
- Each result: icon, bold primary text, 12px secondary text. Clicking navigates. "No results for …" when empty.

### 5.4 Notifications, account, language

- **Notifications popover** (360 wide): heading with "Mark all read"; each row has an unread dot, text and time; clicking opens the related record and marks it read; footer link "View all notifications". There is also a full Notifications page.
- **Account popover** (250 wide): name, title · organisation, role badge; Profile & settings; theme toggle; language; Sign out.
- **Language popover:** English, Azərbaycanca, Русский, Türkçe with a check on the current one. DEMO: the choice is stored but strings stay English.

---

## 6. Data tables

One shared pattern used for RFQs, Orders, Suppliers, Users, Products (admin) and Logs. In production, do filtering, sorting and paging **on the server** with query parameters and keep these controls.

### 6.1 Toolbar (inside the card, bottom border, padding `14px 18px`)

- **Search:** pill input 38 high, max-width 300, fill `--surface-2`, leading 16px icon (turns cobalt on focus), `/` key hint at the right. A round 20px clear button appears when there is text. Pressing `/` anywhere focuses it. Matching text in rows is wrapped in `<mark>` (lime fill, ink text, radius 3).
- **Status tabs:** a segmented control for the table's main field with "All" first and a live count on each.
- **Filter buttons:** one per other field. Height 34, radius 999, **dashed** 1px `--line-2` border, label + chevron, `--text-2`. When values are chosen: solid cobalt border, `--primary-soft` fill, cobalt text, and a cobalt count bubble.
  - Popover (270 wide): heading with "Clear"; one checkbox row per value with a live count at the right; stays open while ticking; multiple values allowed.
- Right side: table-specific buttons, **Choose columns** (popover with a checkbox per column; core columns are locked; "Show all"), and **Export** (PDF, Excel, CSV).
- Below 640px the search takes the full width and the controls scroll sideways in one row.

### 6.2 Active-filter row

Appears only when a search or filter is active: fill `--surface-2`, padding `10px 18px`.

- "**12** of 24 RFQs", then one removable chip per filter ("Buyer: Daniel Okafor ×", "Search: "steel" ×"), then "Clear all".
- Chip: height 26, radius 999, border `--line-2`; on hover the border and × turn red.

### 6.3 Table

- Header row 40 high, no fill, bottom border; cells 12.5/500 `--text-3`.
- Sortable headers are buttons with a small arrow: neutral double-arrow at 45% opacity; active arrow cobalt, pointing up for ascending and rotated 180° for descending; active label `--text`/600. Click cycle: ascending → descending → back to the default order. `aria-sort` set on the `<th>`.
- Body cells: padding `11px 12px` (18px at row edges), bottom border `--line`. Row hover `--surface-2` (120ms). Rows that open a record are focusable and open on Enter.
- Numeric columns right-aligned with tabular numbers.
- Selected row: `--primary-soft` fill and a 3px cobalt bar at the left edge.
- First column: checkbox. Header checkbox selects the page; shows the indeterminate dash when only some rows are selected. Shift-click selects a range.
- Last column: a "⋯" row menu where the table has row actions.
- When filters change, the body fades in over 220ms.

### 6.4 Footer

- Left: "1–10 of 24" and an optional total (orders show the money total).
- Right: "Rows" with a compact pill select (10, 25, 50), then pagination: previous / numbered pages with "…" / next. Page buttons are 32px circles; current page is an ink circle with white text.

### 6.5 Bulk action bar

Appears when at least one row is selected.

- Sticks 18px above the bottom of the scrolling area, centred. Ink pill, radius 999, height 48, shadow `0 14px 34px rgba(18,21,28,.28)`. Rises 24px with overshoot in 340ms on first appearance only.
- Content: lime count bubble, "selected", link "Select all N" (lime), divider, action buttons (32 high, `rgba(255,255,255,.10)` fill; destructive text `#FF9486`), and a clear (×) button.

| Table | Status tabs | Filters | Bulk actions |
|---|---|---|---|
| RFQs | Status | Type, Buyer, Category | Export, Close quoting, Cancel (confirm) |
| Orders | Status | Supplier, Buyer | Export, Send reminder |
| Suppliers | Status | Category, Rating band, On-time delivery | Invite to RFQ, Export |
| Users | — | Role, Status, Organisation | Disable, Resend invitation, Export |
| Products | Availability | Seller, Category | Export, Hide from markets |
| Logs | — | Area, User | — |

### 6.6 Table states

Loading: skeleton rows. Empty (no records at all): empty state with the create action. No matches: empty state with "Clear all filters". Error: "We could not load your RFQs", explanation, "Try again".

### 6.7 Responsive columns

Columns are hidden by priority, not squeezed: some below 1180px, more below 900px, more below 640px. On phones the RFQ table shows only title (with reference, category and status badge stacked under it), deadline and the row menu. The checkbox column is hidden on phones.

---

## 7. Motion

All motion respects `prefers-reduced-motion: reduce` (everything is switched off).

| Event | Animation |
|---|---|
| Page change | Content rises 10px and fades in, 340ms `--ease` |
| Page entry data | Ink-board numbers count up 650ms; progress and timeline bars grow from the left 800ms; chart bars grow up 700ms; line chart draws 1.1s |
| Tab switch | Tab content rises and fades, 240ms |
| Popover | 160ms pop from the anchor |
| Modal | 240ms rise + scale in; 170ms out |
| Drawer | 280ms slide from right; 170ms out |
| Toast | 320ms in with overshoot; 200ms slide-right out; 4.4s countdown line |
| Sidebar collapse | Column width 260ms |
| Mobile menu | 280ms slide, scrim fade 250ms |
| Theme switch | Background, border and text colours cross-fade 320ms |
| Buttons, tiles, checkboxes | Press scale .88–.98; hover transitions 120–180ms |
| Shortcut tile hover | Lift 2px, icon tilt −6° |
| Invalid field | 320ms shake |
| Expanding quotation | Content drops in 240ms; chevron rotates 180° |
| Current lifecycle step | 2s pulse ring, looping (the only looping animation) |
| Bulk bar | 340ms rise on first selection |

---

## 8. Roles, navigation and access

Six roles share the shell and components; each sees only its own menu. Opening a page outside the role's list shows a "You do not have access to this page" empty state with a button to the role's home.

| Role | Menu (group: items) | Home |
|---|---|---|
| Administrator | Overview: Dashboard · Sourcing: RFQs, Compare quotations, Orders · Catalog: Products, Categories, Markets · People: Suppliers, Users & roles · Monitoring: RFQ monitoring & SLA, KPI reports · System: Notifications, Logs, System health | Dashboard |
| Buyer | Workspace: My dashboard · Sourcing: My RFQs, Compare quotations, Purchase orders · Network: Suppliers | Dashboard |
| Supplier | Workspace: Dashboard · Sales: Invitations & quotes, Orders · Team: Field workers, Messages | Dashboard |
| Seller | Workspace: Dashboard · Catalog: Products, Markets · Operations: Orders, Workers | Dashboard |
| Market worker | My work: Today, Messages | Today |
| Supplier field worker | My work: Assignments, Messages | Assignments |

Settings and Notifications are available to every role. Buyers see only their own RFQs and orders. Suppliers see only orders issued to them and RFQs they are invited to. Market workers have no seller-admin screens.

---

## 9. Pages

Every page starts with a header: title (Bricolage 28), one-sentence description, and actions at the right.

### 9.1 Login

- Two columns (form left, ink panel right); the panel is hidden below 900px.
- Form (max-width 480, padding 48): brand mark + name; title "Sign in to your workspace"; description; Work email; Password with "Forgot password?" link; "Keep me signed in" checkbox; primary "Sign in" (40 high, full width); "Continue with single sign-on" (default button with shield icon); small footer line.
- Validation: invalid email or empty password shows a red note and marks both fields.
- Ink panel (`#12151C`, padding 56): headline "Every request, quotation and award on one accountable record." and a quiet four-row ledger of sample records with hairlines at 10% white.
- DEMO: a "Demo role" select on the form. Remove it. Single sign-on is NEW if not present.

### 9.2 Administrator dashboard

1. Header: "Procurement overview", date and counts sentence, "New RFQ".
2. Four shortcut tiles: Create an RFQ, Compare quotations (count ready), Track orders (count), Manage users.
3. Ink board: Open RFQs · RFQs requiring attention · Pending supplier quotations · Orders needing action.
4. Two columns (2:1):
   - Left: **Requires attention** table (record, what needs attention with severity icon, due, one action button) and **Deadlines approaching** table (RFQ, buyer, deadline with relative time, quotations as "2/4" with a progress bar).
   - Right: **Procurement activity** grouped bar chart (RFQs published vs quotations received, last 8 weeks); **Supplier participation** (big percentage and bars per category); **Recent procurement events** timeline with links.

### 9.3 Buyer dashboard

Greeting header with "Create RFQ"; shortcut tiles; ink board (My active requests · Quotes awaiting review · Closing within 7 days · Awarded this quarter). Left: Quotes awaiting review (list with price range, award-due date, SLA badge, "Compare"), Upcoming deadlines, Recently active requests. Right: Needs my action, Recent supplier responses, Awarded requests with order links.

### 9.4 RFQ list

Header + the data table of §6. Extra toolbar buttons: "Status guide" (popover explaining each status in one line). Row menu: Open, Compare quotations, Duplicate as draft, Publish (drafts), Close quoting (open), Cancel RFQ (red, drafts and open).

Columns: Reference, Title (+ category), Buyer, Type, Status, Deadline (+ relative, red at 3 days or less), Items, Quotes ("2/4"), Created.

### 9.5 RFQ detail

1. Header: reference, status badge, type tag, category tag, SLA badge when at risk; title; actions Export, Edit (drawer), and one primary action by status.
2. Facts strip: Quotation deadline · Buyer · Budget · Items · Quotations "X of N invited".
3. Request progress track (§4.13).
4. Tabs: Overview · Items · Suppliers · Quotations · Files · Activity (with counts).

- **Overview** (2:1): *Admin controls* card (admins only: Change status, Request type, Field evaluator, "Requires field assessment" checkbox); Description + Requirements list; Field assessment status table (supplier, evaluator, result, score). Right: Important dates, Buyer card, Latest activity.
- **Items:** #, item (+ specification line under the name), quantity, unit, target unit price, estimated total; total row; budget in the subtitle.
- **Suppliers:** invited suppliers with invitation status (Quoted, Viewed, Invited, Declined, No response), rating, on-time %, "Remind" button; row opens the supplier drawer.
- **Quotations:** see 9.6.
- **Files:** name, size, uploader, date, Download; Upload button.
- **Activity:** full timeline.

### 9.6 Quotations tab (accept / reject / price adjustment)

- Intro line and "Compare side by side" primary button.
- One expandable block per quotation (white, radius 16). Summary row: supplier name, "Lowest price" / "Partial · 3/5" labels, submitted date · delivery days · payment terms, total excluding tax, status badge (Pending, Accepted, Rejected), **Accept** (primary) and **Reject** (danger) buttons while pending, chevron.
- Expanded:
  - **Price adjustment tool** (admins only; exists in the current app): dashed box with "Target total (USD)" input and "Generate adjusted prices". It spreads the target across lines with up to ±10% variation per item and shows "New unit price" and "New total" columns in cobalt next to the originals. "Clear" removes them.
  - Line table: Item, Qty, Unit price, Tax %, Total (+ the two new columns when adjusted); total row.
  - Footer line: valid until, field assessment badge, over/under budget, tax amount.
- Accept opens the award dialog (9.8). Reject marks the quotation Rejected and can be undone while the RFQ is not awarded.

### 9.7 Create RFQ (four steps) — wizard is NEW

Steps bar, then one card per step, then a footer row: Back · Save draft · Continue / Publish RFQ. "Discard" in the header asks for confirmation.

1. **Request:** Title*; Request type (two radio cards: Public tender, Internal request, each with one explanatory sentence); Category; Quotation deadline* (date picker, at least 3 days ahead); Delivery location; Budget (optional) with "Show the budget to suppliers" checkbox; Description; Requirements (one per line).
2. **Items:** editable table — #, description, quantity, unit (pcs, t, kg, m, m², m³, hours, months, lot), target unit price (optional), line estimate (live), remove. "Add item", "Import from Excel", and a live estimated total with over/under budget.
3. **Suppliers & files:** suppliers of the chosen category as pick rows (name, city, awards, response rate, rating, status; suspended ones disabled), "Select all active"; attachments drop zone.
4. **Review:** read-only summary of all three with "Edit" links and a note stating how many suppliers will be notified.

Validation runs when moving forward; the wizard jumps to the first step with an error and shows messages under the fields. Internal requests need at least one supplier.

### 9.8 Quotation comparison — matrix and scoring are NEW

Without an RFQ in the address: a table of requests that have quotations (status, quote count, lowest total, price spread, award-due date).

With an RFQ:

1. Header with Export and "Open RFQ"; a note if the request is still open or already awarded.
2. Three summary boxes: **Best overall value** (lime-tinted, lime left bar), **Lowest price**, **Fastest delivery** — each with the supplier and one explanatory line.
3. Card toolbar: one toggle chip per supplier (show/hide), and a "Weighting" segmented control (Balanced, Price-led, Schedule-critical).
4. Matrix table — first column sticky (item names), header row sticky, horizontal scroll:
   - Header cell per supplier: labels (Best value, Lowest price, Partial, Awarded), name (opens profile), city and quote date, score (Bricolage 22) with a bar, "Select for award" button.
   - **Line items:** unit price, line total beneath, and either "Lowest" in green or "+6.8%" in grey; "Not quoted" in italics.
   - **Commercial:** total value, against budget (green under / red over), delivery days and date ("Fastest" tag), payment terms, quote validity.
   - **Assurance:** field assessment badge and score, compliance documents (three ticks or red "missing"), past performance (rating, on-time %, awards).
   - **Score breakdown:** four mini bars (Price, Delivery, Assurance, Performance) with their weights, and the overall score.
   - Column tints: recommended column `--brand-soft` with a 4px lime top bar; selected column `--primary-soft`.
5. Sticky award bar at the bottom: hint text, or the selected supplier with total, days, score and "Award to this supplier" (primary).
6. **Award dialog:** supplier, value, delivery and terms; warnings for partial quotes; if the choice is not the lowest price, a justification text area is required; checkboxes "Create a draft purchase order" and "Notify N unsuccessful suppliers". Confirming sets the RFQ to Awarded and creates a draft order.
7. Phones: a select above the table shows all suppliers (scroll sideways) or focuses on one.

Scoring rule used: Price 40 / Delivery 20 / Assurance 25 / Performance 15 (Balanced); 60/10/20/10 (Price-led); 25/40/20/15 (Schedule-critical). Price score = lowest prices for the quoted lines ÷ this supplier's total, multiplied by the share of lines quoted. Delivery score = fastest days ÷ this supplier's days. Assurance = field assessment score (50 if pending). Performance = on-time % × 0.6 + rating ÷ 5 × 40. The highest score is a recommendation only.

### 9.9 Purchase orders

- **List:** an amber note listing orders that need the user's action, then the data table. Columns: Order, Supplier / request, Related RFQ, Buyer, Amount, Issued, Delivery due, Status. Footer shows the money total.
- **Detail** (2:1): header with number, badge, title, summary line, Export and the next-step button. Left: Line items (#, item, qty, unit price, line total, order total) and Status history (timeline of the six steps; past in green, current in cobalt and bold, future greyed). Right: Order details (related RFQ link, issued, delivery due, payment terms, Incoterms), Buyer card, Supplier card.
- Status flow (NEW if different from yours): Draft → Issued → Acknowledged → In transit → Delivered → Invoiced. Buyer/admin buttons: "Issue to supplier" (Draft), "Confirm receipt & invoice" (Delivered). Supplier buttons: "Acknowledge order", "Mark as dispatched", "Mark as delivered".

### 9.10 Suppliers — metrics are NEW

- **Directory:** data table. Columns: Supplier (avatar, name, city · since year), Status, Category, Participation (invited · awarded, response rate), Rating (star + number), On time (% + bar), Contact.
- **Profile drawer:** avatar, name, city, status; facts strip (Rating, On-time, Response, Awards); Contact; Performance bars (on-time delivery, invitation response rate, latest field assessment); Participation history (last RFQs with outcome badges). Footer: Suspend / Reinstate (admins, with confirmation), Message, Invite to RFQ.

### 9.11 Monitoring & SLA — NEW rules

- Ink board: Active RFQs · At risk · Overdue or breached · Due within 7 days.
- Card with a "SLA table / Timeline" switch.
  - Table: RFQ, stage, owner, due, SLA progress bar, SLA status badge, risk reason, action ("Nudge" or "Decide").
  - Timeline: one row per RFQ on a date axis; a solid bar for the quoting period (cobalt, amber when at risk, red when overdue), a hatched bar for the 5-day award window, and a dashed red "today" line; legend below.
- "Actions required" table underneath.
- Rules: an open RFQ is *At risk* when 5 days or less remain and fewer than 3 suppliers (or fewer than all invited, if less than 3) have quoted; *Overdue* when the deadline has passed while still open. A closed RFQ has 5 days to be awarded: *At risk* from day 3, *Breached* after day 5. A purchase order not acknowledged for 3 days raises an alert.

### 9.12 KPI reports

Header with a 3 / 6 / 12 months switch. Ink board: RFQs published (award rate) · Quotation participation · Purchasing volume · Request-to-award time. Four chart cards (2 × 2): RFQ trends (published vs awarded), Quotation participation (line), Purchasing activity (bars), Spend by category (horizontal bars). Then a Supplier comparison table (invited, response, win rate with bar, on-time, rating).

### 9.13 Users & roles

Tabs: Users · Roles & permissions.

- **Users:** data table (User, Role, Organisation, Status, Last active). Row or "Invite user" opens a drawer: Full name, Work email, Organisation, Role, Account status (edit only).
- **Roles:** left list of the six roles with user counts; right card with the role description and a read-only matrix of nine modules × View / Create / Edit / Approve (green check or grey dash).

### 9.14 Catalog (administrator)

- **Products:** data table (SKU, Product, Category, Seller, Price per unit, Availability, Markets).
- **Categories:** table (Category, Suppliers, Open RFQs, All RFQs, "View suppliers" which opens the directory filtered).
- **Markets:** table (Market + region, Seller, Products, Open orders, Workers, Status, Pause/Resume).

### 9.15 System (administrator)

- **Notifications:** full list with unread dots, "Mark all read".
- **Logs:** data table (When, User, Area, Event, Record).
- **System health:** ink board (Overall, Errors today, Events today, Mail delivery); Services list with status badges; Feature flags list with switches; Import & export buttons.

### 9.16 Settings

Tabs: Profile (avatar, name, title, read-only email, phone, change password) · Preferences (language, date format, currency display, start page, table density: Compact / Comfortable — comfortable adds 13px vertical cell padding) · Appearance (Light / Dark radio cards, collapse-sidebar switch) · Notifications (four switches).

### 9.17 Supplier workspace

- **Dashboard:** header with "Quick quote"; tiles; ink board (New invitations · Quotations in progress · Submitted quotations · Active orders). Left: Invitations requiring response (badge, reference, title, items, closing date, "Start quote" / "Continue draft"), Submitted quotations table (our total, deadline, outcome). Right: Active orders, Deadlines timeline, Messages preview.
- **Invitations & quotes:** tabs Requiring response · Submitted · Not quoted; one table.
- **Quotation form:** two steps, live totals, last-price hint, error and warning rules, review, submit. Fully specified in Appendix G.
- **Orders:** the purchase-order list and detail limited to this supplier, with the supplier's next-step buttons.
- **Field workers:** workers table, "Assign evaluator" dialog, open assignments table.
- **Messages:** two panes (thread list 300px, conversation); stacked on phones; send box at the bottom.

### 9.18 Seller workspace

- **Dashboard:** tiles; ink board (New orders · In fulfilment · Availability to review · Active markets); Orders needing action table with a next-step button per row; Markets table; Availability watch list (labelled "Proposed feature"); Workers today.
- **Products:** table; row opens an edit drawer (name, SKU read-only, category, price, unit, availability: In stock / Low / On request / Unavailable). Availability is an indicator only, not stock tracking.
- **Markets:** one card per market with three facts, assigned workers and Pause/Resume.
- **Orders:** table with a next-step button (New → Confirmed → Ready for pickup → Dispatched → Completed).
- **Workers:** table.

### 9.19 Market worker and field worker

Narrow single column (max 760), phone-first, large tap targets. Fully specified in Appendix G: "Today" checklist with progress; assignments list and detail with Pass/Fail checklist, notes, photos and submit.

---

## 10. Simple view, help and tour — all NEW

Full content of the Help drawer, page guides, welcome dialog and tour is in Appendix D.

- **Simple view** is **off by default**. The switch is in the sidebar footer and in the Help drawer. Store the choice per user.
- When on:
  - Administrator menu shows six plain-named items — Home, Requests (RFQs), Compare quotes, Orders, Suppliers, Users — and a "More areas" item that expands the rest. Other roles get plain names only.
  - Administrator and buyer home becomes: greeting, "What would you like to do?" (three tiles), "Waiting for you" (numbered list of up to five tasks with one button each; the first is primary), and "Show all figures and charts".
  - Main pages show a **"How this page works"** strip under the header: lime-tinted box (radius 16) with one sentence and three numbered steps, dismissible per page.
  - In the comparison, "Best value" reads "Recommended" and the weighting control and score breakdown are hidden.
- **Help drawer** (? in the top bar): "How do I…?" task buttons for the role, a five-term glossary (RFQ, Quotation, Award, Purchase order, SLA), the Simple view switch, and "Take the tour again".
- **Welcome dialog** on first sign-in: one sentence about the product, three numbered points, "Skip, I'll explore" and "Show me around (30 seconds)".
- **Tour:** four stops (menu, search, the green main action, Help). The target is highlighted with a lime 3px ring and the rest dimmed at 60%; a 320px card shows "Step n of 4", title, one sentence, Skip / Back / Next. Store "tour seen" per user.

---

## 11. Responsive behaviour

| Width | Changes |
|---|---|
| ≥ 1440 | Full layout, page max-width 1560 |
| < 1180 | Multi-column card grids become one column; lowest-priority table columns hidden; shortcut tiles two per row; guide strip stacks |
| < 900 | Sidebar becomes a drawer; sheet loses margin, border and radius; workspace pill and user name hidden; login panel hidden; two-column grids stack; wizard step labels hidden; messages stack |
| < 640 | Page padding `16px 14px`; breadcrumb, language and theme buttons hidden; page title 23px; header actions go full width under the title; ink board two columns; forms one column; drawers full-screen; tables keep only their key columns; comparison offers a one-supplier focus select; toasts full width |

Never squeeze a wide table into narrow columns: hide columns by priority or allow horizontal scroll inside the card.

---

## 12. Accessibility

- Semantic landmarks: `nav` (primary, breadcrumb, pages), `header`, `main`, `aside`; a "Skip to content" link.
- All controls reachable by keyboard; visible focus ring 2px `--primary` with 2px offset (inputs use the 3px soft ring).
- Dialogs: `role="dialog"`, `aria-modal`, labelled, focus trapped, Escape closes, focus returns.
- Custom select and date picker keep the native element for forms and assistive technology; triggers have `aria-haspopup` and a label.
- Status is never colour alone: every badge has text.
- Toast container is `aria-live="polite"`. Tables use `<th scope>` and `aria-sort`.
- Text contrast: body `#12151C` on white; secondary `#4A5262`; tertiary `#7B8394` is used only for non-essential text at 11.5px and above.

---

## 13. What is new for the backend

These screens or rules do not exist in the current app and need server work before the UI can be wired:

1. RFQ creation wizard: budget visibility flag, draft saving per step.
2. Comparison matrix: weighted scoring, compliance-document checklist, award justification, automatic draft purchase order, notification of unsuccessful suppliers.
3. Request progress track and its next-step actions; "Remind suppliers".
4. SLA rules, risk states, timeline view, "Requires attention" lists.
5. Purchase-order status flow with supplier-side steps and the not-acknowledged alert.
6. Supplier metrics: rating, on-time %, response rate, participation history; suspend / reinstate.
7. Supplier quotation checks: last-price lookup, partial-quote and typo warnings, review step.
8. Field assignments: creation, checklist, notes, photos, submit, link to the assessment score.
9. Market worker tasks generated from orders and availability.
10. Seller availability indicator; market pause.
11. Simple view preference, tour-seen flag, help content.
12. Server-side search, filter, sort, paging and bulk actions for the six tables; global search endpoint.
13. Message threads.

Already in the current app and only restyled: RFQ list and detail, admin controls on the RFQ, items with specifications, accept / reject quotations, price adjustment tool, orders, products, categories, markets, users, roles, logs, notifications, system health, feature flags, import / export, settings, login.

---

## 14. Known issues in the current app to fix while porting

Seen on the current RFQ #15 screen:

1. The comparison summary lists a supplier at USD 3,753.80 while the same supplier's detailed quote totals $105,766.02.
2. "Best" and rank #1 are attached to the highest quote.
3. EUR and USD amounts are compared directly in the summary cards.

---

## 15. Suggested order of work

1. Tokens, fonts, base styles, both themes.
2. Shell: frame, sidebar, sheet, top bar, mobile drawer.
3. Core components: buttons, inputs, custom select, date picker, checkbox, switch, tabs, segmented, badges, cards, ink board.
4. Overlays: popover, modal, drawer, toast, tooltip.
5. Data table pattern, then RFQ list.
6. RFQ detail with progress track and quotations tab.
7. Comparison.
8. Dashboards, orders, suppliers.
9. Other roles' workspaces.
10. Administration pages, settings, login.
11. Simple view, help and tour.

**Acceptance check for each page:** compare side by side with the prototype at 1440, 1280, 768 and 390 pixels wide, in light and dark themes, with the keyboard only, and with reduced motion switched on.

---

# Appendices — complete inventory

Sections 1–15 describe the design. These appendices list **everything that exists in the prototype**, extracted from its code, so nothing is left to interpretation. Appendix lists marked "extracted" were generated from the prototype's own data, not typed by hand.

Legend for the "Kind" column: **Real** = changes data for the session in the prototype and must be a real server action; **UI** = interface behaviour only, no server work; **Demo** = shows a "demonstration only" toast and does nothing; needs building.

## Appendix A — Every page (extracted)

| Address | Roles | Screen |
|---|---|---|
| `#/dashboard` | admin | Administrator dashboard (full) or Simple home |
| `#/dashboard` | buyer | Buyer dashboard (full) or Simple home |
| `#/dashboard` | supplier | Supplier dashboard |
| `#/dashboard` | seller | Seller dashboard |
| `#/rfqs` | admin, buyer | RFQ list (buyer sees own only) |
| `#/rfqs/new` | admin, buyer | Create RFQ wizard |
| `#/rfqs/{id}` | admin, buyer | RFQ detail with six tabs |
| `#/compare` | admin, buyer | Requests that have quotations |
| `#/compare/{id}` | admin, buyer | Quotation comparison matrix |
| `#/orders` | admin, buyer, supplier | Purchase order list |
| `#/orders/{id}` | admin, buyer, supplier | Purchase order detail |
| `#/suppliers` | admin, buyer | Supplier directory; `#/suppliers/{id}` opens the profile drawer |
| `#/monitoring` | admin | RFQ monitoring & SLA (table and timeline) |
| `#/reports` | admin | KPI reports |
| `#/users` | admin | Users / Roles & permissions |
| `#/products` | admin | All products across sellers |
| `#/products` | seller | Own products with edit drawer |
| `#/categories` | admin | Categories |
| `#/markets` | admin | All markets |
| `#/markets` | seller | Own markets as cards |
| `#/logs` | admin | Activity logs |
| `#/health` | admin | System health, feature flags, import/export |
| `#/invitations` | supplier | Invitations & quotations (three tabs) |
| `#/quote/{id}` | supplier | Quotation form (two steps) |
| `#/workers` | supplier | Field workers and assignments |
| `#/workers` | seller | Market workers |
| `#/sorders` | seller | Customer orders |
| `#/tasks` | market worker | Today checklist |
| `#/assignments` | field worker | Assignment list |
| `#/assignments/{id}` | field worker | Assignment detail |
| `#/messages` | supplier, field worker, market worker | Message threads |
| `#/notifications` | all | Notification list |
| `#/settings` | all | Settings (four tabs) |
| `(signed out)` | — | Login |

Special states available on every address: **Not found** (unknown address or record) and **No access** (page outside the role's list). Both are empty-state cards with one button back to the role's home.

Access list per role (extracted):

- **admin:** dashboard, monitoring, rfqs, compare, orders, suppliers, reports, users, settings, products, categories, markets, logs, health, notifications
- **buyer:** dashboard, rfqs, compare, orders, suppliers, settings, notifications
- **supplier:** dashboard, invitations, quote, orders, workers, messages, settings, notifications
- **seller:** dashboard, products, markets, sorders, workers, settings, notifications
- **market:** tasks, messages, settings, notifications
- **field:** assignments, messages, settings, notifications

## Appendix B — Every function (all 117 action handlers, extracted and described)

Names are the prototype's internal handler names; use them to find the code in `48source-redesign.html` (search for `name(` inside the `ACT` object).

### B.1 Shell and global

| Handler | Trigger | What it does | Kind |
|---|---|---|---|
| `collapse` | Sidebar toggle button; Settings switch | Collapses / expands the sidebar with a width animation | UI (store per user) |
| `navopen` | Menu button (mobile) | Opens the sidebar drawer | UI |
| `theme`, `setTheme` | Moon/sun button; account menu; Settings → Appearance | Switches light / dark with a colour cross-fade | UI (store per user) |
| `lang`, `langCycle` | Language menu; account menu | Stores the chosen language (EN, AZ, RU, TR) | Demo (strings stay English) |
| `readAll` | Notifications popover and page | Marks all notifications read | Real |
| `ntf` | Click a notification | Marks it read and opens the related record | Real |
| `logout` | Account menu | Signs out, shows Login | Real |
| `login` | Login form submit; SSO button | Validates email and password, signs in | Real (SSO is Demo) |
| `export` | Any Export menu (PDF, Excel, CSV) | Shows "export prepared" | Demo |
| `tab` | Any tab or view switch | Changes the active tab; content fades in | UI |
| `demo` | Buttons not built yet | Shows a toast naming what would open | Demo |
| `closeOv` | Close button, Cancel, scrim, Escape | Closes the open modal or drawer with exit animation | UI |
| `help` | ? button; Simple home | Opens the Help drawer (Appendix D) | UI |
| `tourStart`, `tourStep`, `tourEnd` | Welcome dialog; Help drawer; tour card | Runs the four-stop tour | UI (store "seen" per user) |
| `simple` | Sidebar switch; Help drawer switch | Turns Simple view on / off | UI (store per user) |
| `more` | "More areas" in the Simple menu | Expands / collapses the rest of the menu | UI |
| `dashFull` | Simple home buttons | Switches between Simple home and the full dashboard | UI |
| `guideOff` | × on a "How this page works" strip | Hides that guide for that page | UI (store per user) |

### B.2 RFQs

| Handler | Trigger | What it does | Kind |
|---|---|---|---|
| `rdup` | Row menu; lifecycle track (cancelled) | Creates a draft copy with a new number and a deadline 21 days ahead | Real |
| `rpublish` | Row menu; RFQ header; lifecycle track | Draft → Open, notifies invited suppliers | Real |
| `rclose`, `rcloseOk` | Row menu; RFQ header | Confirmation, then Open → Closed | Real |
| `rcancel`, `rcancelOk` | Row menu | Red confirmation, then → Cancelled | Real |
| `rbulkCancel` | Bulk bar → Cancel | Cancels the selected Draft and Open RFQs | Real |
| `redit`, `reditOk` | "Edit" on RFQ detail | Drawer to edit title, deadline, description; saves | Real |
| `nudge`, `nudgeOk` | "Nudge / Remind suppliers" (dashboard, monitoring, RFQ) | Lists the suppliers who have not quoted; sends a reminder | Demo |
| `rview` | "States" menu | Previews loading, empty and error states of the table | Demo (remove) |
| `qaccept` | "Accept" on a quotation | Opens the award dialog for that supplier | Real |
| `qreject` | "Reject" / "Undo reject" | Marks the quotation Rejected or back to Pending | Real |
| `adj`, `adjClear` | Price adjustment tool (admin) | Generates new unit prices for a target total (±10% per line); clears them | Real (exists today) |
| `catGo` | "View suppliers" on Categories | Opens the supplier directory filtered by that category | UI |

Admin controls on the RFQ (Change status, Request type, Field evaluator, Requires field assessment) are plain form fields bound to the record — Real.

### B.3 Create RFQ wizard

| Handler | Trigger | What it does | Kind |
|---|---|---|---|
| `dstep` | Step bar; Continue; Back; "Edit" links | Moves between steps; validates when moving forward | UI + validation |
| `dtype` | Request-type cards | Sets Public tender / Internal request | UI |
| `ditemAdd`, `ditemDel` | "Add item"; bin icon | Adds / removes an item row (at least one row stays) | UI |
| `dsup`, `dsupAll` | Supplier rows; "Select all active" | Toggles invited suppliers | UI |
| `dfile`, `dfileDel` | "Add file"; × on a file | Attaches / removes a file | Demo (sample names) |
| `dsample` | "Fill with sample request" | Fills the form with example data | Demo (remove) |
| `dcancel`, `dcancelOk` | "Discard" | Confirmation, then clears the form and returns to the list | UI |
| `dsave` | "Save draft"; "Publish RFQ" | Creates the RFQ as Draft or Open and opens it | Real |

Validation messages: "Give the request a title suppliers will recognise." · "Choose a quotation deadline." · "Allow suppliers at least 3 days to respond." · "Add at least one item with a name and quantity." · "Every item needs a name and a quantity greater than zero. Remove empty rows." · "Internal requests are only visible to invited suppliers. Select at least one." · (draft) "A draft needs at least a title."

### B.4 Comparison and award

| Handler | Trigger | What it does | Kind |
|---|---|---|---|
| `ctoggle` | Supplier chips | Shows / hides a supplier column (at least one stays); scores recalculate | UI |
| `cpreset` | Weighting control | Balanced / Price-led / Schedule-critical | UI |
| `cpick` | "Select for award"; "Clear" | Selects or clears the supplier for award | UI |
| `caward` | "Award to this supplier"; "Accept" | Opens the award dialog (justification required if not the lowest price) | UI |
| `cawardOk` | "Confirm award" | RFQ → Awarded; creates a draft purchase order with the next number and ETA = today + delivery days | Real |

### B.5 Orders, suppliers, users

| Handler | Trigger | What it does | Kind |
|---|---|---|---|
| `poNext` | Next-step button on an order | Moves the order one status forward; "Issue" sets the issue date to today | Real |
| `supOpen` | Supplier row; supplier names elsewhere | Opens the supplier profile drawer | UI |
| `supFlip`, `supFlipOk` | Suspend / Reinstate in the drawer | Confirmation, then changes supplier status | Real |
| `roleSel` | Role list | Shows that role's permission matrix | UI |
| `userOpen` | User row; "Invite user" | Opens the edit / invite drawer | UI |
| `userSave` | "Save changes" / "Send invitation" | Validates name and email; saves or adds the user | Real (invitation email Demo) |
| `pref` | Notification switches | Toggles a preference | Real |
| `density` | Settings → Preferences | Compact / Comfortable table rows | UI (store per user) |
| `flag` | Feature-flag switches | Turns a feature flag on / off | Real (exists today) |

### B.6 Supplier, seller, workers

| Handler | Trigger | What it does | Kind |
|---|---|---|---|
| `qstep` | Step control; Review; Back | Switches between editing and review | UI |
| `qsave` | "Save draft" | Marks the invitation "Draft in progress" | Real |
| `qfile`, `qfileDel` | Attachments | Adds / removes a file | Demo |
| `qsubmit`, `qsubmitOk` | "Submit quotation" | Confirmation, then saves the quotation and counts the supplier as quoted | Real |
| `assign`, `assignOk` | "Assign evaluator" | Dialog, then creates a field assignment | Real |
| `thread` | Thread list; dashboard message preview | Opens a conversation | UI |
| (form submit) | Message box, Enter or send button | Appends the message to the thread | Real |
| `soNext` | Button on a seller order | New → Confirmed → Ready for pickup → Dispatched → Completed | Real |
| `mktFlip` | Pause / Resume on a market | Toggles Active / Paused | Real |
| `prodOpen`, `prodSave` | Product row; "Add product" | Edit drawer; validates name and price; saves | Real |
| `mtask` | Market worker task | Ticks / unticks a task | Real |
| `fstart` | "Start assessment" | Assignment → In progress | Real |
| `fcrit` | Pass / Fail on a checklist line | Sets or clears the answer; moves to In progress | Real |
| `fnote` | "Add note" | Adds a note with a timestamp (empty refused) | Real |
| `fphoto` | "Add photo" | Adds a file name | Demo |
| `fsubmit`, `fsubmitOk` | "Submit assessment" | Blocks until all lines are answered; confirmation; → Submitted, read-only | Real |

### B.7 Custom controls and tables

| Handler | Trigger | What it does |
|---|---|---|
| `csOpen`, `csPick` | Custom select trigger / option | Opens the list; sets the native select and fires `input` + `change` |
| `dpOpen`, `dpNav`, `dpPick` | Date trigger / month arrows / a day or shortcut | Opens the calendar; changes month; sets the native date input |
| `dtSort` | Column header | Ascending → descending → default order |
| `dtPage` | Page buttons | Goes to a page and scrolls the card into view |
| `dtSel`, `dtAll`, `dtSelAll`, `dtNone` | Row checkbox (Shift = range); header checkbox; "Select all N"; × | Selection |
| `dtQuick` | Status tabs | Single-value filter on the table's main field |
| `dtF`, `dtFx`, `dtFclear`, `dtClear` | Filter checkbox; chip ×; "Clear" in a filter; "Clear all" | Multi-value filters |
| `dtQx` | Search clear button; search chip × | Clears the search |
| `dtCol`, `dtColAll` | Column menu | Hides / shows optional columns |
| `dtBulk` | Bulk-bar buttons | Runs that table's bulk action on the selection |

### B.8 Leftover handlers — do not port

`rsort`, `rstatus`, `rpage`, `rsel`, `rselAll`, `rselNone`, `rclear`, `rbulkClose`, `spAdv`, `spClear`, `tipOff` and the render regions `rfqTable`, `rfqSeg`, `poTable`, `supTable`, `userTable`, `apTable`, `logTable` belong to earlier versions of the tables and are no longer reachable from the interface. Ignore them.

### B.9 Popovers (extracted)

| Name | Opened from | Content |
|---|---|---|
| `notif` | Bell | Notifications, "Mark all read", "View all notifications" |
| `acct` | Account button | Identity, Profile & settings, theme, language, Sign out |
| `lang` | Globe button | Four languages |
| `export` | Export buttons | PDF document, Excel workbook (.xlsx), CSV |
| `rrow` | ⋯ on an RFQ row | Open, Compare quotations, Duplicate as draft, Publish, Close quoting, Cancel RFQ |
| `statusGuide` | "Status guide" | One line per RFQ status (Appendix D.4) |
| `rview` | "States" | Loaded, Loading skeleton, Empty, Load error (Demo, remove) |
| `dtF` | Filter buttons | Checkbox list with counts and "Clear" |
| `dtCols` | Columns button | Checkbox per column and "Show all" |

## Appendix C — Data tables (extracted)

`*` marks columns that cannot be hidden.

| Table | Search placeholder | Default sort | Status tabs on | Columns | Filters | Bulk actions |
|---|---|---|---|---|---|---|
| RFQs | Search reference, title, buyer or category | created descending | status | Reference*, Title*, Buyer, Type, Status*, Deadline*, Items, Quotes, Created | Status, Type, Buyer, Category | Export, Close quoting, Cancel |
| Orders | Search order, RFQ, supplier or buyer | date descending | status | Order*, Supplier / request*, Related RFQ, Buyer, Amount*, Issued, Delivery due, Status* | Status, Supplier, Buyer | Export, Send reminder |
| Suppliers | Search name, city, contact or category | name ascending | status | Supplier*, Status*, Category, Participation, Rating*, On time*, Contact | Status, Category, Rating, On-time delivery | Invite to RFQ, Export |
| Users | Search name, email or organisation | name ascending | — | User*, Role*, Organisation, Status*, Last active (no sort) | Role, Status, Organisation | Disable, Resend invitation, Export |
| Products (admin) | Search product, SKU or category | name ascending | avail | SKU*, Product*, Category, Seller, Price*, Availability*, Markets | Availability, Seller, Category | Export, Hide from markets |
| Logs | Search user, event or record | as recorded (newest first) | — | When* (no sort), User*, Area, Event*, Record | Area, User | — |

## Appendix D — Help, guidance and tour: full content

### D.1 Help button and drawer

- **Entry points:** the **?** icon button in the top bar (between the theme toggle and the bell; tooltip "Help: how do I…?"), and "How do I…?" on the Simple home. Present for every role on every page.
- **Container:** the standard drawer (§4.16), title "How do I…?".
- **Section 1 — task buttons.** A stack of large buttons (white, border `--line`, radius 14, padding `14px 16px`, 8px apart). Each has a bold title, a one-line explanation in 12px `--text-2`, and a chevron at the right. Clicking closes the drawer and opens the page.
- **Section 2 — "Words you will see".** Heading in Bricolage 18/700. A definition list: term in 14px/600, meaning in 14px `--text-2`, 10px between entries.
- **Section 3 — "Simple view".** A bordered row (radius 12): bold "Show only the essentials", a sentence ("A shorter menu, a to-do home page and a guide on each page. Switch it off when you know your way around."), and the switch.
- **Footer:** "Take the tour again" (default button with eye icon) at the left, "Done" (primary) at the right.

Task buttons per role (extracted):


**Administrator**

| Title | Explanation | Opens |
|---|---|---|
| Ask suppliers for prices | Create a request (RFQ), list the items, pick suppliers. | `#/rfqs/new` |
| Choose the best quotation | Compare what suppliers sent and accept one. | `#/compare` |
| See where an order is | Every order shows its current step. | `#/orders` |
| Find a supplier | Search the directory and open a profile. | `#/suppliers` |
| Add a colleague | Invite a user and choose their role. | `#/users` |

**Buyer**

| Title | Explanation | Opens |
|---|---|---|
| Ask suppliers for prices | Create a request (RFQ), list the items, pick suppliers. | `#/rfqs/new` |
| Choose the best quotation | Compare what suppliers sent and accept one. | `#/compare` |
| See where an order is | Every order shows its current step. | `#/orders` |
| Find a supplier | Search the directory and open a profile. | `#/suppliers` |

**Supplier**

| Title | Explanation | Opens |
|---|---|---|
| Send my prices | Open an invitation and fill in the quotation. | `#/invitations` |
| Update an order | Acknowledge, dispatch or mark as delivered. | `#/orders` |
| Send someone to a site | Assign a field worker to a survey or inspection. | `#/workers` |

**Seller**

| Title | Explanation | Opens |
|---|---|---|
| Confirm a new order | Move orders forward one step at a time. | `#/sorders` |
| Change a price or availability | Click the product and save. | `#/products` |
| See who works where | Workers and their markets. | `#/workers` |

**Market worker**

| Title | Explanation | Opens |
|---|---|---|
| See what to do today | Tick tasks off as you finish them. | `#/tasks` |
| Ask the owner something | Send a message. | `#/messages` |

**Field worker**

| Title | Explanation | Opens |
|---|---|---|
| Do a site check | Open the assignment, answer the checklist, submit. | `#/assignments` |
| Ask the office something | Send a message. | `#/messages` |

Glossary (extracted):

| Term | Meaning |
|---|---|
| RFQ (request for quotation) | A request you send to suppliers asking for their prices. |
| Quotation | A supplier's answer: prices, delivery time and terms. |
| Award | Accepting one quotation. The other suppliers are told they were not chosen. |
| Purchase order | The order created for the supplier you accepted. |
| SLA | The time limit for a step, such as 5 days to choose a quotation. |

### D.2 "How this page works" strips (Simple view only, extracted)

Shown under the page header. Left: bold "How this page works" with the ? icon and one sentence. Right: three numbered steps (ink circle with lime number, 13px text). × hides it for that page.

| Page | Sentence | Step 1 | Step 2 | Step 3 |
|---|---|---|---|---|
| `#/rfqs` | A request (RFQ) asks suppliers for their prices. | Press **New RFQ** to ask suppliers for prices. | Click any row to open a request and see its next step. | Use the status buttons above the table to narrow the list. |
| `#/rfqs/new` | Four short steps. You can save a draft at any time. | Say what you need and when quotes are due. | List the items and quantities. | Pick suppliers, check the summary, then publish. |
| `#/rfqs/{id}` | The coloured line shows where this request is and what to do next. | Read the **next step** line and press its button. | Open the **Quotations** tab to see what suppliers sent. | Accept one quotation — the order is created for you. |
| `#/compare` | Pick a request to see its quotations side by side. | Requests marked **Closed** are ready for a decision. | Click a row to open the comparison. | Choose a supplier and confirm. |
| `#/compare/{id}` | Each column is one supplier. The recommended one is marked. | Read the three summary boxes first. | Check prices line by line — the lowest is marked. | Press **Select for award**, then **Award** at the bottom. |
| `#/orders` | An order is created when you accept a quotation. | Click an order to see its items and progress. | The yellow note lists orders that need you. | Press the green button on an order to move it forward. |
| `#/suppliers` | Everyone you can ask for prices. | Search by name, city or category. | Click a supplier to see contact details and past results. | Use **Invite to RFQ** to add them to a request. |
| `#/users` | People who can sign in, and what they are allowed to do. | Press **Invite user** to add a colleague. | Click a person to change their role or disable them. | Open **Roles & permissions** to see what each role can do. |
| `#/invitations` | Buyers invite you here to send your prices. | Open a request under **Requiring response**. | Fill in your prices and delivery time. | Review and submit before the deadline. |
| `#/quote/{id}` | Fill in what you can supply. Empty lines are allowed. | Type your unit price for each item. | Add delivery time and payment terms. | Press **Review quotation**, check the total, then submit. |
| `#/assignments` | Site visits and checks assigned to you. | Open the assignment that is due first. | Mark each check Pass or Fail and add notes or photos. | Press **Submit assessment** when everything is answered. |
| `#/products` | What you sell, with price and availability. | Click a product to change its price or availability. | Use **Add product** for something new. | Buyers see the availability label, not a stock count. |
| `#/sorders` | Customer orders from all your markets. | New orders are at the top. | Press the button on a row to move it to the next step. | Completed orders stay in the list for reference. |

### D.3 Welcome dialog and tour (extracted)

Welcome dialog, shown once after first sign-in: title "Welcome to 48Source"; sentence "This is where you ask suppliers for prices, compare their quotations and follow the orders."; three numbered points — "The menu on the left lists every area, grouped by task." · "Search finds any page, request, order or supplier." · "New here? Switch on Simple view at the bottom of the menu for a shorter menu and step-by-step guides."; buttons "Skip, I'll explore" and "Show me around (30 seconds)" (primary).

Tour stops (a stop is skipped when its target is not on screen; on phones stop 1 points at the menu button):

| # | Highlights | Title | Text |
|---|---|---|---|
| 1 | Sidebar menu | Everything starts in this menu | Every area is listed here, grouped by what you want to do. Prefer fewer choices? Turn on Simple view at the bottom of the menu. |
| 2 | Global search | Lost? Search | Type a page name, a request number or a supplier. It finds all three. |
| 3 | The main green button in the top bar | The green button is the main action | On every page, the one green button is the usual next step. |
| 4 | Help button | Help is always here | Step-by-step answers, plain meanings of the words, and this tour again. |

Tour mechanics: the highlight is a box around the target with a 3px lime ring and a 60% dark surround; it moves between stops in 350ms. The card (320 wide, radius 16) sits to the right of tall targets or below short ones, and shows "Step n of N", title, text, and Skip / Back / Next (Finish on the last). Escape ends the tour. It repositions on window resize.

### D.4 Status guide (RFQ list)

| Status | Explanation shown |
|---|---|
| Draft | Being prepared. Suppliers cannot see it yet. |
| Open | Published. Suppliers can submit and revise quotations until the deadline. |
| Closed | Quoting has ended. Quotations are being compared and one is accepted. |
| Awarded | A quotation was accepted and an order was created. |
| Cancelled | Withdrawn. No quotation will be accepted. |

### D.5 Simple view menu names (extracted)


Administrator essentials, in order: Home, Requests (RFQs), Compare quotes, Orders, Suppliers, Users. Everything else sits under "More areas".

Plain names used when Simple view is on: dashboard → "Home"; rfqs → "Requests (RFQs)"; compare → "Compare quotes"; orders → "Orders"; suppliers → "Suppliers"; users → "Users"; invitations → "Invitations & my quotes"; sorders → "Orders"; tasks → "Today"; assignments → "My assignments".

## Appendix E — Permissions matrix shown on "Roles & permissions" (extracted)

V = View, C = Create, E = Edit, A = Approve. Empty = no access.

| Module | Administrator | Buyer | Supplier | Seller | Market worker | Field worker |
|---|---|---|---|---|---|---|
| RFQs | VCEA | VCE | V | — | — | V |
| Quotations | VCEA | VA | VCE | — | — | V |
| Purchase orders | VCEA | VC | VE | VE | V | — |
| Suppliers | VCEA | V | — | — | — | — |
| Products & markets | VCEA | V | — | VCE | VE | — |
| Field assessments | VCEA | VA | VCE | — | — | VCE |
| Reports | VCEA | V | — | V | — | — |
| Users & roles | VCEA | — | — | — | — | — |
| System settings | VCEA | — | — | — | — | — |

Role descriptions shown above the matrix:

- **Administrator:** Full oversight of users, requests, orders, reporting, permissions and system operations.
- **Buyer:** Creates and manages requests, reviews quotations and follows orders through delivery.
- **Supplier:** Responds to invitations, submits quotations, manages orders and coordinates field workers.
- **Seller:** Manages products, markets, orders and the workers assigned to them.
- **Market worker:** Works on orders and availability for assigned markets only. No seller administration.
- **Field worker:** Carries out assigned assessments and field activities and reports the results.

## Appendix F — Fixed lists used in forms (extracted)

- **Categories:** Construction materials, IT hardware, Office equipment, Industrial equipment, Facility maintenance, Logistics services, Professional services.
- **Item units:** pcs, t, kg, m, m², m³, hours, months, lot.
- **Purchase-order statuses, in order:** Draft → Issued → Acknowledged → In transit → Delivered → Invoiced.
- **Seller-order statuses, in order:** New → Confirmed → Ready for pickup → Dispatched → Completed. Button labels: New: "Confirm", Confirmed: "Mark ready", Ready for pickup: "Dispatch", Dispatched: "Complete".
- **Compliance documents checked per quotation:** Quality certification, Material / service certificates, Insurance cover.
- **Scoring weights (Price / Delivery / Assurance / Performance):** Balanced 40/20/25/15; Price-led 60/10/20/10; Schedule-critical 25/40/20/15.
- **Supplier quotation payment terms:** Net 30, Net 45, Net 60, 30% advance with balance Net 30. **Validity:** 14, 21, 30, 45, 60 days.
- **Feature flags shown:** Public tender portal (on); Field assessments (on); Split awards (off); Excel import for RFQ items (on); Supplier self-registration (off).
- **Services on System health:** Application, Queue workers, Mail delivery, PDF / Excel export, Scheduled tasks.
- **Languages:** English, Azərbaycanca, Русский, Türkçe.

## Appendix G — Supplier quotation, evaluator assignment, field worker and market worker: full detail

### G.1 Supplier quotation form (`#/quote/{RFQ id}`)

**Access.** Only a supplier invited to the RFQ. Otherwise "Invitation not found". RFQ not Open → "Quoting is closed". Already submitted → "Quotation submitted" with total, delivery days and outcome.

**Two steps**, switchable in the header: "1 · Prices & terms" and "2 · Review & submit".

**Step 1, left column**

- *Itemised prices* table: # · Item · Quantity (with unit, read-only) · Your last price (read-only hint, hidden on phones) · Unit price (USD) — number input, minimum 0, step 0.01, placeholder 0.00, right-aligned · Line total (unit price × quantity, updates on every keystroke). Text below: a line may be left empty if the supplier cannot supply it; prices exclude tax and include delivery to site.
- *Delivery & terms:* Delivery lead time (days), minimum 1, help "Calendar days from purchase order to delivery on site." · Payment terms (default Net 45) · Quotation valid for (default 30 days), help "The buyer asked for at least 30 days." · Tax rate (%), 0–40, default 20 · Notes to the buyer (optional).
- *Attachments:* "Add file", remove per file. Demo only.

**Step 1, right column** (stays in view while scrolling)

- *Totals:* Items priced n of N · Subtotal · Tax (rate %) · Total incl. tax (17px). Below: the error and warning notes, or a green "All lines priced and within your usual range."
- *Request summary:* reference, buyer and organisation, type, deadline with relative time (red at 3 days or less), delivery location, description, first three RFQ files.

**"Your last price"** means the unit price this supplier quoted for the same item last time. The prototype simulates it; build a lookup of the supplier's most recent submitted quote line for the same product.

**Errors (block submission):** "Enter a unit price for at least one item." · "Enter the delivery lead time in days."

**Warnings (do not block):**
- Partial: "X of N items have no price. The quotation will be submitted as partial."
- Possible typo, when a price is more than 25% above or below the last price: "Line N is P% above/below your last quoted price ($X). Check for a typing error."
- Lead time above 60 days: "A lead time above 60 days is unusual for this buyer."

Make the 25% and 60-day thresholds configurable.

**Step 2 — review.** Banner: red "Fix before submitting", amber "Please confirm" with the warnings, or green "Everything looks complete. Review the figures below and submit." Read-only price table ("Not quoted" for empty lines) with Subtotal, Tax, Total incl. tax. Delivery & terms summary (red "Missing" if no lead time). "Edit" links back to step 1. Buttons: Back to edit · Save draft · Submit quotation (disabled while there are errors).

**Submit.** Confirmation with the amount excluding tax, delivery days, RFQ reference, the number of warnings acknowledged and a note that the quote can be revised until the deadline. On confirm: the quotation is saved, the supplier counts as having quoted, the RFQ's quotation count rises by one, and the supplier lands on Invitations & quotes → Submitted. In the buyer's comparison, unpriced lines show "Not quoted" and the column carries a "Partial" label.

**Quote states on the supplier side:** New invitation → Draft in progress → Submitted (RFQ open) → Under evaluation (RFQ closed) → Awarded or Lost.

**Gaps to close when building:** the prototype keeps only prices, lead time, payment terms and validity on submit (not tax rate, notes or attachments); "Save draft" only changes the label; revising a submitted quotation is not built; the form defaults tax to 20% while the buyer's quotation view shows 18% per line — choose one rule.

### G.2 Assign evaluator (`#/workers`, supplier)

**Dialog fields:** Evaluator (each field worker with their open count) · Activity (Site access & unloading survey, Stockyard sample verification, Delivery inspection) · Related request or order (RFQs this supplier is invited to that are Open or Closed) · Due date (default today + 4 days).

**On "Assign"** an assignment is created: next FA- number; title = activity; reference = the RFQ; site taken from the RFQ; site contact = the RFQ's buyer; status Not started; a three-line checklist (Access and unloading conditions · Storage area suitability · Site contact and induction confirmed). It appears in "Open assignments" and in the field worker's workspace.

**The page also shows** a workers table (name and title, area, status, workload, Message button) and an open-assignments table (title and number, reference, evaluator, due date, status).

**Gaps:** the chosen evaluator is ignored (everything goes to one worker); purchase orders are not offered in the "related" list; the checklist is the same for every activity (should be a template per activity type); the administrator's "Field evaluator" and "Requires field assessment" controls on the RFQ are not connected to this.

### G.3 Field worker workspace

**List (`#/assignments`).** Header with the open count and next due date. "Open" cards, then "Submitted". Card: status badge, number, title, site with a pin icon, due date with relative time (red at 3 days or less), chevron.

**Detail (`#/assignments/{id}`).**
- *Task details:* reference, site, due date, site contact, Message button.
- *Assessment checklist:* each line has Pass and Fail buttons (34 high); pressing the chosen one again clears it; counter "x of n answered"; answering any line moves the assignment to In progress.
- *Notes:* dated list, text box, "Add note" (empty refused: "Write a note first.").
- *Attachments:* file list (camera icon for photos), "Add photo" (demo).
- *Main button* (full width, 44 high): "Start assessment" when Not started or Scheduled; "Submit assessment" when In progress.

**Submitting.** Blocked until every line is answered: "Answer the remaining N checklist items before submitting." Confirmation: it goes to the account manager and the buyer and cannot be edited afterwards. Then the page is read-only: lines show Passed / Failed badges, note and photo inputs disappear, and a green note reads "all checks passed" or "N check(s) failed".

**Statuses:** Not started, Scheduled, In progress, Submitted. **Data:** number, title, reference, site, due date, status, site contact, checklist (text + pass / fail / empty), notes (text + time), files.

**Gaps:** the assessment score and Passed / Conditional / Failed result shown to buyers is not calculated from this checklist — define the rule; no buyer approval step; no offline mode, location capture or real photo upload.

### G.4 Market worker "Today" (`#/tasks`)

- Header: the date and the worker's assigned markets.
- Progress card: "x of y tasks done", progress bar, number remaining.
- Task list: large buttons, each with a 24px tick box, title, detail line (market, time, what to do) and a type tag (Order, Availability, Price). Tapping toggles done; done tasks are struck through and dimmed to 60%.
- "My markets" card: each assigned market with open orders, products listed and status.
- Permission note: "You can update orders and availability for your assigned markets. Products, prices and workers are managed by the seller."

**Data per task:** number, title, detail, type, done flag.

**Gaps:** tasks are fixed demo data, not generated from orders or availability; ticking a task does not change the order or the product; the seller cannot create or assign tasks; "My markets" is not a real assignment.

## Appendix H — Rules and calculations in the prototype

- **Budget** when not entered: estimated value (Σ quantity × target price) × 1.045, rounded to the nearest 5,000.
- **Invited suppliers** default to all non-suspended suppliers in the RFQ's category.
- **Award target:** quotation deadline + 5 days. **Clarifications by:** deadline − 4 days. **Delivery required:** deadline + 35 days (placeholder).
- **SLA and attention rules:** section 9.11.
- **Scoring:** section 9.8 and Appendix F.
- **Price adjustment:** each line's price is multiplied by a random factor between 0.9 and 1.1, then all lines are scaled so the total matches the target; unit prices are rounded to cents, so the total can differ from the target by a few dollars.
- **Next numbers:** RFQ and purchase-order numbers continue from the highest existing number.
- **Relative dates:** "Today", "Tomorrow", "Yesterday", "in N days", "N days ago".
- **Money:** `$1,234` with no decimals; unit prices under 100 show two decimals.
- **Dates:** "21 Nov 2026"; without the year in tables.

## Appendix I — Honest limits of this document

- Values in sections 1–4 were measured in light theme at 1440 × 900. Dark-theme component values follow from the token table; they were not measured one by one.
- Hover, focus and pressed states are described from the stylesheet, not measured.
- The prototype was tested without access to Google Fonts, so the two typefaces were never seen rendered. Check headings and table widths once fonts load.
- Appendices A–F are extracted from the code. Sections 5–10 and Appendix G are written descriptions; where they and the prototype differ, the prototype wins.
- Exact wording of every toast and empty state is not listed here. Read it from the prototype.