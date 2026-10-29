# OneSeller

A multi-vendor marketplace: many independent sellers list products in one storefront, and buyers shop across all of them in a single cart and checkout.

- Functional scope: [`OneSeller-MVP-Functional-Scope.docx`](OneSeller-MVP-Functional-Scope.docx)
- Build plan, decisions and milestones: [`docs/BUILD-PLAN.md`](docs/BUILD-PLAN.md)
- Deploying to shared hosting: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)

Stack: PHP 8.3+, Laravel 13, MySQL 8 (SQLite locally), Blade + Livewire + Tailwind CSS.

## Local setup

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan oneseller:create-admin
php artisan serve
```

Then open:

| Area | URL |
|---|---|
| Storefront / buyer | http://localhost:8000 |
| Seller | http://localhost:8000/seller/login |
| Admin | http://localhost:8000/admin/login |

Emails are written to `storage/logs/laravel.log` locally (`MAIL_MAILER=log`).

## Checks

```bash
php artisan test
vendor/bin/pint --test
```

CI runs both on every pull request, with the tests against SQLite and MySQL.

## Conventions

- **Money** is stored as integer paise and **commission** as basis points (1000 = 10%). Convert only at the edges with `App\Support\Money`.
- **Roles**: one role per account (`admin`, `seller`, `buyer`), each with its own login page and URL area. The `role:` middleware guards each area.
- **Settings** live in the `settings` table, read through `App\Support\Settings`, with defaults in `Settings::DEFAULTS`.
- **Business rules** go in service/action classes, not controllers or Livewire components, so the future mobile API can reuse them.
