# Popover browser checklist

Run this checklist against MySQL with `app:demo-data` loaded. It is intentionally
Dusk-free because the application has no browser-test build step.

| Control | Open | Outside click | Escape | Scroll or resize | Open another popover |
| --- | --- | --- | --- | --- | --- |
| Table filters | Pass | Pass | Pass, trigger focused | Pass | Pass |
| Column chooser | Pass | Pass | Pass, trigger focused | Pass | Pass |
| Export menu | Pass | Pass | Pass, trigger focused | Pass | Pass |
| Status guide | Pass | Pass | Pass, trigger focused | Pass | Pass |
| RFQ More actions | Pass | Pass | Pass, trigger focused | Pass | Pass |
| Help menu | Pass | Pass | Pass, trigger focused | Pass | Pass |
| Language menu | Pass | Pass | Pass, trigger focused | Pass | Pass |
| Account menu | Pass | Pass | Pass, trigger focused | Pass | Pass |
| Recent records | Pass | Pass | Pass, trigger focused | Pass | Pass |
| Custom select | Pass | Pass | Pass, trigger focused | Pass | Pass |
| Date and time picker | Pass | Pass | Pass, trigger focused | Pass | Pass |

Livewire navigation and morphs close every open popover. Notifications are a
navigation link, not a dropdown in the current application.
