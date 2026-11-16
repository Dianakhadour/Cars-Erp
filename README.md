# Cars ERP

A multi-tenant ERP backend for car dealerships and workshops, built with Laravel 12. Each customer (tenant) gets its own MySQL database, and a single API covers company and branch management, double-entry accounting, vehicle and spare-parts inventory, procurement, and workshop job cards.

## Features

**Multi-tenancy**
- Database-per-tenant isolation: a master database holds tenants, domains, licenses and platform admins; every tenant has its own database (`tenant_<slug>`).
- The tenant is resolved per request from the `X-Tenant-Domain` header, and the database connection is switched automatically.
- One command provisions a tenant: it creates the database, registers the domain and runs the tenant migrations.
- License checks limit what a tenant can create (for example, the number of companies).

**Companies and access control**
- Tenant, then companies, then branches hierarchy.
- Company-level chart of accounts (with a default chart import) and number sequences.
- Roles: `super-admin`, `company-admin`, `branch-manager`, `accountant`, `viewer`, with permissions and per-user branch access.
- Two auth layers: Laravel Sanctum for platform admins (master database) and JWT for tenant users.

**Finance**
- Chart of accounts with account integrations that map business events (vehicle sale, parts sale, service revenue, purchases, and so on) to accounts.
- Journal entries with draft, post and reverse flows, wrapped in database transactions.
- Reports: trial balance, general ledger, balance sheet, income statement, journal entries, account ledger.
- Report export to CSV, PDF and Excel.

**Inventory and procurement**
- Vehicles with status tracking, warehouses and bins, categories.
- Stock movements: receipts, issues, transfers, adjustments, plus stock levels and summaries.
- Spare parts with categories, pricing, reorder and low-stock alerts, movement history and weighted-average costing.
- Purchase orders (vehicles and parts), goods receipt, suppliers, procurement and supplier-performance reports.
- Stock valuation reports (summary, by category, by warehouse) and finance integration (stock valuation export, cost of goods sold, period-end closing).

**Workshop**
- Job cards with status workflow.
- Reserve, issue and return parts against a job, and view part usage per job.

## Tech Stack

| Area | Technology |
|------|------------|
| Framework | Laravel 12, PHP 8.2+ |
| Database | MySQL (master database plus one database per tenant) |
| Authentication | Laravel Sanctum (platform admins), `php-open-source-saver/jwt-auth` (tenant users) |
| Reports export | `maatwebsite/excel`, `barryvdh/laravel-dompdf` |
| Code style | Laravel Pint |
| Testing | PHPUnit 11 |

## Architecture

```
Request
  -> tenant.resolve   reads X-Tenant-Domain, finds the tenant in the master DB,
                      switches the "tenant" connection to that tenant's database
  -> tenant.jwt       authenticates the tenant user (JWT)
  -> Controller -> Service -> Repository / Model
```

- `app/Tenancy` and `app/Http/Middleware`: tenant resolution and JWT middleware.
- `app/Services`: business logic (tenants, licenses, journal entries, chart of accounts, cost calculation, finance reports).
- `app/Repositories`: data access for the chart of accounts and journal entries.
- `app/Services/Finance`: report registry and runner, with one class per report and exporters for CSV, PDF and Excel.
- `app/DTO` and `app/Enums`: typed report context and results, journal statuses, integration types, and similar.
- `app/Models/Master` and `app/Models/Tenant`: models split by database.
- `database/migrations_master` and `database/migrations_tenant`: separate migration paths for the two database levels.

## Getting Started

### Requirements
- PHP 8.2+, Composer
- MySQL 8+ (or MariaDB)

### Installation

```bash
git clone https://github.com/Dianakhadour/Cars-Erp.git
cd Cars-Erp
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

### Configure the database

`.env.example` defaults to SQLite, so switch it to MySQL and point it at the master database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=master_database
DB_USERNAME=root
DB_PASSWORD=
```

Create the empty master database, then run the master migrations:

```bash
mysql -u root -e "CREATE DATABASE master_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --database=master --path=database/migrations_master
```

### Create a tenant

```bash
php artisan tenant:provision "Demo Motors" demo.carerp.io
```

This creates the `tenant_demo_motors` database, registers the domain and runs the tenant migrations (default roles are created by the migrations).

Other tenant commands:

```bash
php artisan tenants:migrate            # run tenant migrations for all tenants (or pass a tenant)
php artisan tenant:rollback --step=1   # roll back tenant migrations
```

### Run the API

```bash
php artisan serve
```

The tenant is selected by the `X-Tenant-Domain` header, so no DNS or hosts file is needed locally. The domain must match the allow-list in `app/Tenancy/TenantResolver.php` (`carerp.io`, `e-carstech.com` and their subdomains), so edit that list if you use other domains.

### First requests

```bash
# 1. Register a tenant user
curl -X POST http://127.0.0.1:8000/api/auth/register-tenant \
  -H "X-Tenant-Domain: demo.carerp.io" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"name":"Admin","email":"admin@demo.test","password":"password123","password_confirmation":"password123","role_slug":"super-admin"}'

# 2. Log in (returns a bearer access_token)
curl -X POST http://127.0.0.1:8000/api/auth/login-tenant \
  -H "X-Tenant-Domain: demo.carerp.io" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@demo.test","password":"password123"}'

# 3. Call a protected endpoint
curl http://127.0.0.1:8000/api/companies \
  -H "X-Tenant-Domain: demo.carerp.io" -H "Accept: application/json" \
  -H "Authorization: Bearer <access_token>"
```

## API Overview

All routes are prefixed with `/api`. Tenant routes require the `X-Tenant-Domain` header and a JWT bearer token.

| Area | Base path | What it covers |
|------|-----------|----------------|
| Tenant auth | `/auth/register-tenant`, `/auth/login-tenant` | Register and log in tenant users |
| Companies | `/companies` | Companies, registrations, branches, chart of accounts, number sequences |
| Finance | `/finance` | Accounts, integrations, journal entries (`/post`, `/reverse`), reports (`/reports/{key}/run`, `/export/{csv\|pdf\|excel}`) |
| Inventory | `/Inventory` | Categories, vehicles, warehouses, stock movements and levels, purchase orders and receipts, suppliers, parts, part pricing, parts movements, valuation and procurement reports |
| Workshop | `/workshop/job-cards` | Job cards and status updates |
| Platform admin | `/auth`, `/tenancy`, `/licenses` | Sanctum-protected: platform login, tenant and domain management, licenses |

Available finance report keys: `trial_balance`, `general_ledger`, `balance_sheet`, `income_statement`, `journal_entries`, `account_ledger`.

### Postman

Ready-made collections are included:
- `car-erp.postman_collection.json` (full API)
- `postmanCollections/` (Inventory, Workshop Integration, Job Cards, Valuation and Finance)

## Documentation

- `laravel11-erp-architecture.md`: multi-tenant architecture, scope and design goals.
- `inventory-module.md`: inventory module requirements, process flows and ER diagrams.



