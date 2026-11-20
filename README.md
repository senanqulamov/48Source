# 48Source

**Procurement and supplier workflows, built with Laravel and Livewire.**

48Source is the independent commercial-development codebase based on an earlier DPanel procurement application. It is **not** an SAP product or an official SAP Ariba integration. The product is under active rebranding and engineering review; it is **not production-ready**.

## Current codebase

Existing implementation includes:

- Buyer, seller, supplier and field-worker experiences
- Requests for quotation (RFQs), supplier invitations, quote submission and comparison
- Purchasing, markets, products and orders
- User roles, permissions, activity logs and alerts
- SLA reminders, reports, PDF and spreadsheet exports
- Field assessments and worker assignments

Some modules have partial test coverage; features and isolation must be verified before commercial deployment.

## Technology

- PHP 8.2+ and Laravel 12
- Livewire 3 and TallStackUI 2
- Vite and Tailwind
- Pest tests, Laravel Pint and Larastan

## Development setup

Prerequisites: PHP, Composer, Node.js, and a configured database.

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Configure `.env` for your database and mail service before starting. The example defaults to SQLite for development.

**Seeding policy:** `php artisan db:seed` generates synthetic demo records and is restricted to `local` and `testing` environments. Never seed the demo dataset into a production database. `RolesAndPermissionsSeeder` provisions roles and permissions; a demo administrator is created only when `SEED_DEMO_ADMIN=true` in a local/test environment, and requires `DEMO_ADMIN_PASSWORD`. A fresh production admin should be provisioned by an explicit, separately reviewed administrative process.

## Checks

```bash
composer run test
composer run analyse
composer run format
npm run build
```

See `.github/workflows/tests.yml` for the CI checks.

## Commercialization checklist

- [ ] Resolve all DPanel, SCMAI, client and starter-kit references in UI, assets and data
- [ ] Review source ownership, licensing and third-party assets
- [ ] Verify registration, authorization, supplier boundaries and sensitive information exposure
- [ ] Design organization/tenant isolation **before** enabling hosted multi-customer access
- [ ] Audit migrations, seeders, role permissions, notifications and exports
- [ ] Run the CI suite and add critical RFQ/quote/order regression tests
- [ ] Create independent visual identity, documentation and sample data
- [ ] Define hosting, backups, support and licensing before selling

## Notes

This repository is private. Keep the original DPanel repository unchanged. Do not add production customer data, secrets or credentials to Git.

**48Source** is a working brand by 48Team, pending name/trademark clearance.
