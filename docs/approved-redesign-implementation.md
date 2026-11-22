# Approved 48Source redesign — implementation tracker

**Source of truth:** user-approved `48source-redesign.html` (22 November 2026) and accompanying supplier/worker behavior notes. Prototype sample data and its client-only actions are **not** production truth.

## Release rule
Ship in stages; do not replace existing marketplace `orders`, weaken gates, or expose tenant/supplier information. The application remains an independent 48Source product, not an SAP Ariba integration. Deploy UI under `SOURCE48_REDESIGN` until pages have been reviewed. Default: ON in local APP_ENV; OFF otherwise.

## Implemented on this branch
- Scoped v3-v6 design palette foundation (ink, lime, cobalt, paper frame), Onest/Bricolage fonts, mobile workspace layout and shared nav visual tokens.
- First-pass Blade/Livewire-compatible shell using real routes and existing authorization; existing layout retained behind feature flag.
- Configurable proposed thresholds: award target 5 days, acknowledgment alert 3 days, price-warning deviation 25%.
- Pure server-side weighted quote evaluator with balanced, price-led, schedule-critical presets, line minima, deltas, partial-quote handling, and unit tests. Not yet connected to real RFQ pages.

## Required product deliveries (not yet implemented)
| Order | Feature | Backend/integration contract | Critical test |
|---|---|---|---|
| 1 | Approved design page-by-page | Replace dashboard, RFQ, comparison, quote, order, supplier, seller and worker Livewire markup, preserve server-bound data | Screenshots at desktop/mobile, all six role permissions |
| 2 | RFQ wizard and lifecycle | Four-step draft, item validation, budget plus supplier visibility flag, suggestions by category, duplicate, bulk close, reminder outbox and next-step timeline | Buyer ownership, invalid item rejection, no confidential budget leakage |
| 3 | Comparisons and awards | Load actual quote lines and supplier qualifications into evaluator, persisted scoring snapshot/preset, compliance documents, audited award decisions | Partial quotes, mixed currencies, incomplete compliance, cannot award uninvited supplier |
| 4 | Award → procurement PO | **New** procurement purchase-order model and lines; transaction saves award/justification and draft PO exactly once | Non-cheapest award requires reason; rollback and concurrent idempotency |
| 5 | Procurement PO lifecycle | Draft → Issued → Acknowledged → In transit → Delivered → Invoiced, supplier-owned transitions, timestamp/history, 3-day unacknowledged alert | Unauthorized transition rejected; duplicate alert suppressed |
| 6 | Supplier intelligence | Ratings, on-time and response rates from real events; participation history; suspend/reinstate with reason | Suspended suppliers cannot accept new invitations; historical quotes retained |
| 7 | Supplier quote composer | Persist drafts, line prices, taxes, notes, files, previous price, configurable warnings, review and submit transaction | Validation and repeated submit; private quote visibility |
| 8 | Field assessments | Persist evaluator assignment to selected worker, task templates, pass/fail, notes, real photo uploads, submit/approve with auditable score | Worker assignment isolation, rejected dangerous files, checklist completion |
| 9 | Market operations | Seller-controlled market pause and product availability, assigned worker daily tasks and completion records | Worker cannot touch unassigned market |
| 10 | Messaging, role matrix | Persist threads/read receipts; resource/action-level View/Create/Edit/Approve grants | Cross-company isolation, forbidden messages, permission regression |
| 11 | KPIs | Materialized or well-indexed event-driven participation, request-to-award, spend/category reporting; filtered exports | Real data consistency, restricted supplier data |
| 12 | Authentication/localization | SSO only after an IdP/OIDC provider is chosen and configured; EN/AZ/RU/TR fully translated (not mere menu labels) | OIDC state/nonce/redirect validation, translated workflows |

## Decisions requiring explicit policy before backend implementation
- Currency normalization: score prices only in same currency; define FX conversion source, effective rate and tax treatment for mixed-currency comparisons.
- Scoring: weights are prototype defaults; define normalized, auditable assurance and performance metrics, approval thresholds, missing-data policy, and locked award-time scoring snapshots. A calculated score is not itself procurement approval.
- Partial awards: prototype split-award flag is OFF; prohibit award of an incomplete whole RFQ until separate line-award workflow exists.
- Award SLA: target 5 calendar days after *actual* close timestamp, not RFQ deadline when closed early or late; calculate in configured timezone; set at-risk threshold.
- Supplier ratings: derive measures from recorded deliveries and invitations, not random demo fields.
- Existing `orders` represent marketplace buying and selling. Create separate procurement POs; don't rename existing statuses globally.
- Scope of organizations/tenancy must be established before any hosted multi-customer use.
- Keep audit logs, file authorization, secure upload storage, and clear rate limits on reminders, quote and messaging actions.

## Verify this foundation
```bash
npm ci
npm run build
php artisan view:cache
php artisan test
```
Set `SOURCE48_REDESIGN=true` on a local copy to inspect the shell, compare to the HTML, and confirm actions/permissions. Set false to revert UI without code rollback.

**Status:** Commits created; local PHP/Node builds and browsers have not been executed by this assistant. Do not merge until CI and manual role review pass.
