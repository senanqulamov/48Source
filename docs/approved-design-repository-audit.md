# 48Source approved-design migration audit

**Reference:** `design/reference/48source-redesign.html` is the accepted source of visual truth.

**Scope:** Every role (admin, buyer, supplier, seller, plus the two worker workspaces), every page, and all interactive components.

## Repository-wide inventory and current conversion

This batch replaces 31 TallStackUI modal templates and 12 slide/drawer templates with approved `.scrim`, `.modal`, `.drawer`, `.ov-h`, `.ov-b` and `.ov-f` structures while preserving Livewire bindings. It also replaces the legacy SLA, notifications, system health, marketplace-orders list, and role-detail page presentation.

The existing shared component layer `48source-control-system.css` provides approved select, input, checkbox, button, filter-bar, table, dialog, tabs, and responsive styles in common layouts. This is transitional: individual nested TallStack controls may still require markup replacement.

## Still outstanding (do not declare complete)

- Full user detail, supplier-worker detail, supplier quote editing, supplier field assessment, import/export, remaining data table and chart views
- Replace every remaining TallStack button/input/select/card/alert/popup, rather than relying only on shared CSS adapters
- Verified end-to-end behavior for each modal/drawer interaction including dialog accessibility, focus, keyboard and modal closure
- Implement prototype-only features such as line-level award explanations, partial supplier quotes, scheduled PO acknowledgement warnings, market daily tasks, messaging, photo checklists, and analytics where still missing
- Desktop and mobile reference screenshots and pixel-difference testing for each authorized role and viewport
- Fix repository-wide feature-test and PHPStan failures and confirm production readiness

## Verification gate

A view is complete only after the authorized role can open it, interact with its forms, save/cancel safely, and match the original approved desktop/mobile reference screenshot. Backend preservation alone and compilation alone do not meet the gate.

No placeholder demo data or simulated actions may substitute for real backend state.

## Additional conversion in the same batch

- Converted five remaining multi-purpose dialogs (supplier approval/rejection/blocking, invite more suppliers, order/log quick views, buyer RFQ wizard)
- Converted remaining account/supplier/field-assessment/quote/role detail card wrappers without deleting their existing forms or business actions
- Converted system health, SLA tracker, notifications, admin order list, role details and import/export shell
- Added unified error-page templates for HTTP 401, 403, 404, 419, 429, 500 and 503
- Deleted the unused pre-redesign dashboard backup after checking for source references

**Still not pixel-perfect:** many nested field components, icons, charts, and multi-step workflow details remain from the prior implementation. Passing Blade compilation is insufficient; browser-driven role/viewport comparisons are still required.
