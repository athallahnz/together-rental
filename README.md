# Together Rental

Centralized multi-branch rental management for Together Kamera.

## Stack

- Laravel 13 / PHP 8.4.1+
- Inertia 3
- React 19 + TypeScript
- Tailwind CSS 4
- MySQL 8.4 LTS
- Redis for production queues and cache

This repository uses Laravel's official React starter kit. It is a modern
monolith: Laravel owns routing, authentication, authorization, persistence, and
background jobs, while React renders the interactive application UI. No
separate REST API is required for the web application.

## Requirements

- PHP 8.4.1 or newer
- Composer 2
- Node.js 22 or newer
- MySQL 8.4 or compatible
- Redis for production background jobs

For Windows development, use Laravel Herd or Laravel Sail through WSL2.

## First-time setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database named `together_rental`, review the credentials
in `.env`, then continue:

```bash
php artisan migrate --seed
npm install
npm run build
composer run dev
```

The foundation seeder creates Together Kamera, the `PON` branch, initial
roles/permissions, rate plans, payment methods, and numbering sequences. It does
not create a default password.

To assign an existing account as the initial Super Admin:

```bash
php artisan rental:bootstrap-admin admin@example.com
```

To use Laravel Sail after Composer dependencies are installed:

```bash
php artisan sail:install
```

Select MySQL, Redis, and Mailpit when prompted.

## Access policy

Public registration is disabled. Initial and subsequent user accounts must be
created by an authorized administrator or a controlled seeder/command.

## Domain foundation

1. Company, branch, and scoped settings
2. Users, employees, roles, permissions, and branch access
3. Customers and verified identities
4. Product catalog, physical assets, and branch inventory
5. Rate plans, packages, and promotions
6. Booking and inventory reservations
7. Rental checkout, extensions, partial returns, and collateral
8. Inter-branch asset transfers
9. Payments, cash registers, and cash sessions
10. Asset inspections and maintenance
11. Loyalty and audit trail
12. Legacy Import → Preview → Validation → Mapping → Execute → Verification

## Database conventions

- `BIGINT UNSIGNED` primary and foreign keys
- `utf8mb4` character set
- Monetary values use `DECIMAL(18,2)`
- Datetimes are persisted consistently and displayed in the branch timezone
- Master records may use soft deletes; financial and inventory ledgers do not
- Branch-scoped transactions always carry `branch_id`
- Legacy identifiers are tracked in migration mapping tables, not reused as
  production primary keys

The complete domain overview is documented in
[`docs/database-v2.md`](docs/database-v2.md). The safe legacy pipeline is
documented in [`docs/legacy-import.md`](docs/legacy-import.md). Existing
installations should follow
[`docs/apply-database-v2-update.md`](docs/apply-database-v2-update.md).

## Current scope

The project contains the application foundation, authentication, multi-branch
Rental Management V2 database foundation, initial organization/RBAC seeder, and
legacy import staging schema. Application screens and the legacy parser/executor
will be implemented incrementally on top of this validated schema.
