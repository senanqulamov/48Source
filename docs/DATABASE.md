# 48Source database — migration and fixture contract

## Structure (24 November 2026)

- `database/migrations/` contains **historical Laravel migrations**. Applied
  migrations are immutable: do not rename, merge, edit, delete, or renumber them
  in an already-initialized environment.
- `DatabaseSeeder` creates only idempotent categories and feature flags. It is
  appropriate for `migrate --seed` and production deployments.
- `RealisticWorkflowSeeder` is the **only active business-history fixture**.
  `DemoSeeder` and `LargeDemoSeeder` are tiny compatibility aliases. New code
  must call `RealisticWorkflowSeeder` directly.
- `database/factories/` contains isolated **test model factories**, not bulk
  fixture or production-data logic. Do not treat Faker outputs as real businesses.
- `workflow_fixture_records` tracks each inserted fixture row, but no automated
  destructive cleanup is provided. This avoids touching non-fixture data.
- The existing `demo_records` and `demo_data_runs` tables and their migrations
  remain for compatibility with databases that previously applied them. They
  are **legacy**, not a supported mechanism for seeding new records.

## Setup: new disposable local database

Configure a separate `DB_DATABASE` in your local `.env`, not your existing
development/production database. Verify that database name first.

```bash
php artisan migrate --seed
php artisan db:seed --class=RealisticWorkflowSeeder
php artisan test --filter=RealisticWorkflowSeederTest
```

These synthetic records are purpose-built to test live application workflows:

RFQ → invitation → complete quote lines → award → matching PO lines →
received quantities → invoice items → payment dates.

The fixture uses AZN consistently and integer minor-unit arithmetic.
The rows reflect *plausible scenarios*; they are not verified market prices,
suppliers, compliance certifications, or commercial facts.

The seeder **refuses** to run on production/staging, when existing procurement,
marketplace or contract records would be mixed into its charts, or before its
tracking migration is applied. An idempotent repeated run makes no changes.
Set `WORKFLOW_SEED_PASSWORD` **only in a disposable local environment** if UI
login is needed; otherwise fixture accounts use random unprinted passwords.

Accounts:
- `workflow.buyer.0@example.test` through `.2` (buyers)
- `workflow.supplier.0@example.test` through `.7` (suppliers)

### Existing populated local database

**Do not** run a destructive cleanup on it. Back up and point your local
environment at an entirely *new* SQLite/MySQL/PostgreSQL database, migrate it,
then seed there. Legacy bulk demo rows are not silently deleted or rewritten.
No import/migration of historical user data is performed by this PR.

## Migrations: why these were not squashed into one file

The active repository has already published the foundational `0001_...` and
`2026_...` migrations. Laravel records their **filenames** in the `migrations`
table. Editing or deleting them may cause fresh installs to diverge from
existing installs and makes rollback or disaster recovery unpredictable.

A single baseline for fresh installations is possible, but it should be
produced from a real, **fully migrated database**, not manually concatenated:
1. Inventory the `migrations` table across every deployed environment.
2. Check every database for schema drift and back up records and assets.
3. On a clean disposable environment, run `php artisan migrate:fresh` **only
   after verifying the target database**, run all tests, then
   `php artisan schema:dump` to produce a supported schema snapshot.
4. Compare the dump to all deployed schemas; consider `schema:dump --prune`
   **only in a separately reviewed migration-baseline PR** with a documented
   compatibility and rollback plan.
5. Retain historical migrations until every initialized environment is
   verified compatible with the chosen new-install baseline.

**This PR intentionally does not prune applied migrations.** It consolidates
the seeding behavior and tests without risking your current business database.

## Required checks

```bash
php artisan migrate --seed
php artisan migrate:status
php artisan db:seed --class=RealisticWorkflowSeeder
php artisan test --filter=RealisticWorkflowSeederTest
php artisan test --filter=LargeDemoSeederTest
vendor/bin/pint --test
php artisan test
```

Never conflate currency values: AZN + EUR + USD is not a valid money total.
The dashboard must show a single-currency series or a mixed-currency notice
until a separately verified FX conversion is implemented.
