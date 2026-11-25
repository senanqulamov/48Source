# 48Source database and complete local workflow fixtures

## One command (local or test database only)

```bash
php artisan migrate:fresh --seed
```

This **destroys every table** in the configured database, recreates migrations,
and executes `DatabaseSeeder` in a dependency-safe order. Never use it on
production, on a shared database, or where you have records to keep.
Before running, confirm `APP_ENV`, `DB_CONNECTION`, `DB_DATABASE`,
and take a verified backup.

### What is seeded

1. **ReferenceCatalogSeeder:** 12 groups, 72 subcategories, 288 detailed
   real-world product types, 3 currency definitions, default feature flags
   and the full role-permission matrix.
2. **BusinessIdentitySeeder:** one local administrator, 105 separate buyers,
   130 supplier businesses and one login for each. 120 approved supplier
   accounts; 10 pending approval for realistic onboarding screens.
3. **MarketplaceInventorySeeder:** 105 markets, 105 associated stores and
   separate seller accounts, 783 offer rows—5 to 10 different catalog products
   per seller—with AZN prices, lead time and available inventory.
   Additional market-worker users are assigned to stores.
4. **ProcurementTransactionSeeder:** 240 approved/draft requisitions and
   product-based RFQs, each with 3 requisition and RFQ lines; supplier
   invitations are matched by the supplier's specialty group. Supplier
   quotations have the same actual items and mathematically correct totals.
   The auction/award cycle leads to ~184 POs, with realistic draft/issued/
   acknowledged/in-transit/delivered/invoiced states, receipts and invoice
   lines linked to their PO lines.
5. **OperationalHistorySeeder:** 170 market orders with real store offer
   lines, pick/deliver worker tasks, 47 frame contracts and releases,
   supplier document files (plain-text synthetic placeholders with disclaimers),
   scorecards/actions, field assessments and workers, RFIs/responses,
   quotation Q&A, auctions/bids, BAFO rounds, messages and read markers,
   attachment files, activity logs, notifications, email outbox records,
   budgets, reminders and document/numbering references.

Each stage reports its progress in the console. The default Laravel
`migrate:fresh --seed` output also lists each migration as it runs.
The seeder is idempotent and refuses to mix synthetic records with an
existing business dataset. A failure rolls back inserted database rows.

### Accounts

Set `WORKFLOW_SEED_PASSWORD` only in your isolated local `.env` if you
want a fixed testing password; otherwise the seed command generates one
and prints it only in a local terminal (never in CI).

- Admin: `admin@workflow.48source.test`
- Buyer: `workflow.buyer.0@example.test` to `workflow.buyer.104@example.test`
- Supplier: `workflow.supplier.0@example.test` to `.129`
- Seller: `workflow.seller.0@example.test` to `.104`
- Store worker: `workflow.market-worker.0@example.test` (even store indexes)
- Field worker: `workflow.field-worker.0@example.test` (every 3rd supplier)

### Important distinction

These are test *scenarios*, not real verified commercial transactions.
Catalog descriptions correspond to identifiable physical product types,
but company names, employee identities, prices, supplier certifications
and purchase/payment events are fictional and internally consistent.
Actual business records require permissioned CRM, accounting or ERP imports.
Files generated under `storage/app/private/` explicitly say they are
synthetic, not authentic supplier documentation.

## Files and responsibilities

- `database/migrations/`: historical immutable Laravel schema changes.
- `database/seeders/DatabaseSeeder.php`: **the only canonical entry point**.
- `ReferenceCatalogSeeder.php`, `BusinessIdentitySeeder.php`,
  `MarketplaceInventorySeeder.php`, `ProcurementTransactionSeeder.php`,
  `OperationalHistorySeeder.php`: one clear phase per functional area.
- `Support/Fixture.php`: shared money, IDs, test passwords, timestamps
  and fixture bookkeeping.
- `database/factories/`: isolated factories for model tests. Never
  mistake Faker output for external business records.
- `RealisticWorkflowSeeder`, `DemoSeeder`, `LargeDemoSeeder`: legacy
  aliases that call the same canonical pipeline. Bulk random generation and
  destructive `--fresh` cleanup are deliberately retired.
- `workflow_fixture_records`: fixture batch tracking.
- `demo_records` / `demo_data_runs`: obsolete legacy tables left in the
  migration history for compatibility. New fixtures never use them.

## Historical migration consolidation

Do **not** delete/rename or manually merge migrations already applied in a
live database. Laravel tracks filenames and rollback batches. This PR
delivers a clean `migrate:fresh --seed` experience through the existing
migration chain and a consolidated seeder pipeline.

For a separate future physical schema baseline: back up every live database,
review migration histories and drift across environments, generate a
supported schema dump from a disposable migrated database, verify
new-install and upgrade paths, and only then consider prune under a
separate reviewed migration-baseline PR.

## Verification

The pull request tests this exact command in isolated SQLite databases
under PHP 8.3 and 8.4:

```bash
php artisan migrate:fresh --seed --force
php artisan test --filter=RealisticWorkflowSeederTest
php artisan test
```

Never add currencies together without verified exchange rates. The
dashboard reports actual AZN values, and identifies mixed-currency
reporting without silently assuming exchange rates.
