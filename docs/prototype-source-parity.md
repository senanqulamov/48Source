# Approved redesign: source-to-code parity

**Sources**
- User-approved `48source-redesign(1).html` (static interactive reference)
- `Pasted text(2).txt` (implementation specification)

These uploaded files are authoritative. The prototype uses invented RFQs, suppliers, people, counts and a frozen date, none of which should enter application tables or runtime metrics.

## Baseline failures identified

1. Theme state was split across `localStorage.theme`, `48source-theme`, html `.dark`, and a nested `data-theme` on the app div. This caused unreadable dark-mode combinations.
2. `48source-control-system.css` overrode the actual reference lime button with cobalt. An authoritative scoped style layer restores lime for actions and cobalt for focus/links.
3. Dashboard rendered a different information architecture from §9.2: no quick-action tiles, no eight-week activity bars, no deadline table, and no recent activity timeline.
4. Admin dashboard Livewire mounted unrelated order charts and system-health queries; the approved overview now queries its own data once per visit.
5. Internal navigation did not opt into `wire:navigate`, so link navigation forced full document reloads.

## This focused foundation batch

- Unified theme using `html[data-theme]` plus `.dark` for legacy Tailwind compatibility; one persisted key, default light
- Light/dark token control fix for content, buttons, text and panels
- SPA navigation for role menu, role home and header navigation links, without rewriting Laravel/Livewire into the demo's JS SPA
- Approved admin dashboard hierarchy with live RFQ/deadline queries, actual supplier participation and recent workflow events, and eight-week grouped SVG bars from real dates
- Reduced admin dashboard mount work by omitting unrelated overview/chart/log/product data

## Remaining acceptance gates (unfinished)

The full app still needs page-by-page parity at 1440/1280/768/390 in both themes, correct verified APIs and controls, real browser input/modal/drawer behavior, access-control tests for all roles, and measurement of actual Livewire request latency/JS main-thread responsiveness. `wire:navigate` removes full document reloads on selected links but cannot by itself fix slow PHP/SQL queries or third-party scripts.

Do not merge this batch as evidence of pixel-perfect completion.

- Replaced slate-coloured global search overlay with approved modal, keyboard navigation, source-aligned result rows, and `wire:navigate` links
- Removed Alpine `window.addEventListener` and `document.addEventListener` handlers from search initialization to prevent listener accumulation during SPA navigation
- Added prototype-matching sidebar collapse toggle with local device preference
