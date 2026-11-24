# 48Source

B2B procurement platform. Laravel 12 + Livewire 3, no page reloads (Livewire navigation), no JavaScript build step.

This repository is a new, independent build. It does not touch the original DPanel code or any existing 48Source database.

## Requirements

- PHP 8.2 or newer with the usual Laravel extensions (pdo, mbstring, openssl, intl)
- Composer
- SQLite (default), MySQL 8 or PostgreSQL 14+

## Set up

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # or point DB_* in .env at MySQL / PostgreSQL
php artisan migrate --seed            # tables + categories and feature flags (no user accounts)
php artisan app:create-admin you@company.com "Your Name"   # asks for the password
php artisan serve
```

Open http://127.0.0.1:8000 and sign in.

### Demo data (local only)

```bash
php artisan db:seed --class=DemoSeeder
```

This loads invented companies and people with Azerbaijani names and cities (none of them is a real organisation or a real person), plus requests, quotations and one purchase order. The seeder refuses to run on production. All demo accounts share one password: `DEMO_PASSWORD` from `.env` if you set it, otherwise a random one printed once when the seeder finishes.

| Role | Email |
|---|---|
| Administrator | admin@demo.48source.test |
| Buyer | buyer@demo.48source.test |
| Buyer (second, isolated) | buyer2@demo.48source.test |
| Supplier | supplier@demo.48source.test |
| Supplier (second) | supplier2@demo.48source.test |
| Supplier (pending approval) | supplier5@demo.48source.test |

### Scheduler and mail

- Requests close automatically at their deadline. Run the scheduler: `* * * * * php /path/artisan schedule:run`.
- `MAIL_MAILER=log` by default: invitation, confirmation and reset emails are written to `storage/logs/laravel.log`. Set real SMTP settings in `.env` for production.

## Tests

```bash
php artisan test
```

125 tests cover sign-in, registration, password reset, permissions, the RFQ lifecycle, quotations, scoring, award rules, split awards, the purchase order lifecycle, supplier lifecycle, data isolation and a render check of every page for every role, plus everything in part 2: approval chains, contract ceilings, receipts, the three-way match, supplier risk, field assessments, information requests, questions and answers, best-and-final rounds, the reverse auction and messages, and part 3: marketplace basket and checkout, store steps, item-by-item sourcing, price list import, Excel and CSV export, reports, accounting export, email outbox, reminders, languages and PDF documents.

## What is in part 1

| Area | Included |
|---|---|
| Accounts | Sign in with throttling, supplier self-registration, email confirmation, password reset, invited users set their own password, deactivated accounts are signed out |
| Roles and permissions | Six roles; a View / Create / Edit / Approve table per module that an administrator edits; enforced on routes, menu items, buttons and every action |
| Users | List, invite, edit, deactivate, history per user |
| Suppliers | List, add, edit, lifecycle (pending, active, inactive, blocked) with reasons, history per supplier |
| Categories | Two-level groups and categories |
| RFQs | List with search, status filter, sorting, paging and bulk close; four-step wizard; draft, publish, close, cancel; sealed bids; public tenders; automatic close at the deadline |
| Quotations | Accept or decline an invitation; quote per item, partial quotations allowed; revise and withdraw until the deadline; three currencies |
| Comparison and award | Side-by-side table, lowest price per item, three scoring presets; award rules (closed, full coverage, not expired, active supplier, one currency, written reason above the lowest quotation); item-by-item split award behind a feature flag |
| Purchase orders | Draft, issued, acknowledged, in transit, delivered, invoiced, each step by the right side; cancel before acknowledgement |
| Records | Notifications, activity log, global search, profile and password settings |

## How the money is handled

Amounts are stored as integer cents and quantities as integer thousandths (`App\Support\Money`). A line total is `price x quantity`, rounded half up to the cent with integer arithmetic. No financial value passes through a float.

## Structure

```
app/Models            Eloquent models
app/Services          Business rules: RfqService, QuoteService, QuoteEvaluator, AwardService, PurchaseOrderService, SupplierLifecycleService
app/Support           Money, Perm (permissions), Audit, Notifier, Nav
app/Livewire          One class per page
resources/views       Blade views and components
public/css            The design system from the HTML prototype (app.css) plus additions (extra.css)
tests                 PHPUnit feature and unit tests
```

A business rule that refuses an action throws `App\Exceptions\BusinessRule`; pages turn it into a message for the user.

## What is in part 2

| Area | What it does | Where |
| --- | --- | --- |
| Requisitions | Internal purchase requests. The estimated total builds the approval chain from `config/procurement.php`. Nobody approves their own. An approved requisition becomes a draft RFQ with the same items. | `/requisitions`, `RequisitionService` |
| Contracts | Framework contracts with a fixed price list, a period and an optional value ceiling. Release orders call off quantities at contract prices and cannot go over the ceiling. Contracts expire on their end date. | `/contracts`, `ContractService` |
| Goods receipts | Recorded line by line on a purchase order, with accepted and rejected quantities. Receiving more than is still to come is refused. A full receipt marks the order delivered. | order page, `ReceiptService` |
| Invoices | The supplier sends an invoice from the order. A three-way match compares it with the order prices and the received quantities and lists each difference in a sentence. A mismatch can only be approved with a written reason. | `/invoices`, `InvoiceService` |
| Supplier 360 | One page per supplier: documents with expiry dates and private file storage, quarterly scorecards (the rating is the average of the last four), an action plan, and a risk level with the reasons behind it. Suppliers see their own page as "Company profile". | `/suppliers/{id}`, `SupplierInsightService` |
| Field assessments | The buyer asks, the supplier's field worker answers a checklist and writes findings, the buyer approves or returns it. | `/assessments`, `AssessmentService` |
| Information requests | Questions to suppliers before an RFQ exists. Answers are shown side by side and can start a draft RFQ that invites the suppliers who answered. | `/rfis`, `RfiService` |
| Questions and answers | Suppliers ask on an open request. The answer goes to every invited supplier without naming who asked. | RFQ page, quotation page, `QaService` |
| Best and final | After closing, the buyer can let suppliers that quoted revise once more until a deadline. Awarding waits until the round ends. | comparison page, `BafoService` |
| Reverse auction | Suppliers with complete quotations lower their totals for a fixed time. Each sees its own rank only. A late bid extends the auction. Final totals are spread over the quotation lines in whole cents. | `/rfqs/{number}/auction`, `AuctionService` |
| Messages | Conversations between the buyer side and one supplier, with unread counts. | `/messages`, `MessageService` |

The scheduler (`php artisan schedule:work`, or one cron entry for `schedule:run`) ends auctions, closes best-and-final rounds and information requests at their deadlines, and expires contracts.

## Design

`public/css/app.css` is the stylesheet of the approved HTML prototype, unchanged. The pages use the same markup as the prototype, so both look the same: table cards with search, quick filters, filter chips, column chooser and CSV export (`<x-dt>`), page trail with section, record pager and recently opened records (`<x-ph>`), progress bars (`<x-life>`), fact strips (`<x-facts>`), tabs, verdict cards and the sticky comparison table, dashboard charts drawn on the server as SVG (`App\Support\Chart`).

`public/js/ui.js` (plain script, no build step) replaces native selects and date inputs with the prototype's custom controls, and adds tooltips, toasts, keyboard shortcuts (`/` focuses the table search, `Ctrl K` the global search) and row navigation. It re-applies itself after every Livewire update. Put `data-native` on a wrapper to keep a native control.

## What is in part 3

| Area | What it does | Where |
| --- | --- | --- |
| Marketplace | Products sold by several stores at their own price, stock and smallest order. Search, filter by category and market, open a product to compare its sellers, add to the basket. | `/market`, `MarketService` |
| Basket and orders | The basket can hold offers from many stores. Checkout places one order per store, takes the stock at that moment and fails as a whole if any line no longer fits. Marketplace orders are a separate system from purchase orders. | `/market/basket`, `/market/orders` |
| Item-by-item sourcing | Pick a request and see, for each item, the matching offers (same unit, key words and sizes of the name). Add any seller for any item, or add the best match with stock for all items at once. | `/market/sourcing` |
| Store side | Sellers keep their price list, pause offers and import the list from Excel or CSV. They confirm orders; the market worker picks and delivers through a task list. Cancelling gives the stock back. | `/market/products`, `/market/tasks`, `SellerService` |
| Markets and stores | Administrators manage markets, stores and which seller and worker accounts belong to each store. | `/markets` |
| Spend and reports | Committed spend by month, supplier and category, yearly budgets by category, savings against budget and against the average quotation, and service figures. | `/reports`, `ReportService` |
| Export and import | Every list exports what it shows as Excel or CSV. Cells that start with a formula sign are written as text. | `App\Support\Sheet` |
| Accounting export | Approved and paid invoices of a period as debit and credit journal lines. Exported invoices are marked so they are not sent twice. | `/accounting` |
| Email outbox | Each notification is also written to the outbox and sent through the configured mailer. A mail failure is recorded and never stops the action. Failed emails can be sent again. Switch email off under Settings. | `/outbox`, `App\Support\Notifier` |
| Reminders | Hourly: requests closing within a day without your quotation, orders not acknowledged, late deliveries, invoices due, requisitions waiting for approval, documents about to expire. Each reminder is sent once. | `ReminderService` |
| Languages | English, Azerbaijani and Russian for the whole interface, chosen per user (visitors: `?lang=az`). See "Languages" below. | top bar, Settings, landing page |
| Guided tour | The help button walks through the menu the signed-in role really has, one sentence per entry. | `public/js/ui.js`, `App\Support\Nav::TIPS` |
| PDF documents | Purchase order, request for quotation, invoice record and marketplace order, with the same access rules as their pages. | `PdfController` |

Set `MAIL_MAILER` and the SMTP details in `.env` to deliver real email; with the default `log` mailer the outbox page says so.

## What is in part 4 (closing the gaps to the HTML prototype)

| Area | What it does | Where |
| --- | --- | --- |
| Files | Buyers attach specifications to a request; invited suppliers download them. Suppliers attach files to their own quotation; competitors never see them and the buyer sees them under the same rule as prices (not while bids are sealed). Private disk, served only after an access check. | request "Files" tab, quotation page, `AttachmentService` |
| Request actions | Remind suppliers that have not quoted (at most every 12 hours), duplicate a request as a draft. | request page, `RfqToolsService` |
| Administrator tools | Open a closed request again with a new deadline, assign a request to another buyer, record a negotiated total on a quotation. The original total and the reason are kept and shown to buyer and supplier. | request page, "More actions" |
| Award scenarios | Lowest single supplier, best overall score and cheapest supplier per item, side by side against the budget. Buyer side only. | `/rfqs/{number}/scenarios` |
| Monitoring and SLA | Every active request against its deadline and the 5-day award target, everything that is overdue, and the reminder rules with a "run now" button. | `/monitoring` |
| System | Service checks (database, cache, storage, mail, scheduler), feature flags, currencies and supplier import from Excel or CSV. | `/system` |
| Assistant | Three rule-based helpers: draft a request from a sentence, read the quotations of a request in plain sentences, suggest suppliers for a category. No AI model is involved and nothing is saved until the user confirms. | `/assistant`, `AssistantService` |
| Workers | A seller adds and deactivates the market workers of the store; a supplier does the same for field workers and assigns open field assessments to them. | `/workers` |
| Sign-in code | Optional six-digit code by email after the password. Stored as a hash for ten minutes, usable once, rate limited. | Settings, `Auth\TwoFactor` |
| Preferences | Each user can switch email copies of notifications off. | Settings |
| Linked actions | Start a draft contract from a delivered order; send a message about an order from its page. | order page |
| Supplier qualification | A six-step checklist on the supplier page, computed from status, contact details, verified documents and scorecards. | supplier page |
| Status guide, search, pages | A status legend on the main lists, global search over every kind of record, a privacy page and a public landing page at `/welcome`. | |

Not carried over from the prototype on purpose: the role switcher, the "States" preview and the simple-view toggle, which only make sense in a demo.

## Languages

The views are written in English. For Azerbaijani and Russian the finished page is translated on its way out:

- `app/Support/Translator.php` replaces every text between tags and every hint attribute (`placeholder`, `title`, `aria-label`, `data-tip`) that it finds in the dictionary. Livewire updates pass through the same step (`app/Http/Middleware/TranslatePage.php`).
- `lang/ui/az.php` and `lang/ui/ru.php` hold about 1,440 texts each, keyed by the English text. `lang/ui/patterns.php` holds sentences with a changing part, such as "Sort by ..." or "3 of 5 items".
- Text that is not in the dictionary is never changed. Names, references, titles, notes, anything typed into a field and the contents of text areas stay exactly as entered.
- To add or correct a translation, edit the line in both dictionary files. No code change is needed. `tests/Feature/LanguageTest.php` fails if a key exists in one language only.

Still English in every language: notification and email texts, messages built in PHP with a name or number inside them (for example "Order moved to ...", some toasts and validation messages), the activity log entries, PDF documents and exports. The translations were written without a native reviewer and should be read by one before customers see them.

## Still to do before production

- The four audit reports on the current 48Source code base and its database, and a data migration plan from it. They need read access to that repository.
- Uploaded supplier documents are stored on the `local` disk under `storage/app/private/supplier-documents`. Include that folder in backups, or point the disk at object storage.
- Review `config/session.php`, HTTPS, queue worker and backups for the target server.
