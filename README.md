# AssetPulse — Fixed Asset Management (Phase 1)

Multi-tenant fixed asset register for Indian manufacturers. It follows Schedule II (Companies Act 2013) and is ready for Ind AS 16.
Stack: Laravel 13, PHP 8.4, MariaDB/MySQL, Breeze (Blade), Tailwind 3, Alpine, and endroid/qr-code.

## Run it

```bash
sudo service mariadb start && sudo service php8.4-fpm start && sudo service nginx start
cd /workspace/assetpulse
composer install && npm install && npm run build      # production assets, no Vite dev server needed
php artisan migrate:fresh --seed
php artisan storage:link
chmod -R 777 storage bootstrap/cache
```

Nginx serves `public/` at **http://localhost:8080**. To smoke-test every role over HTTP, run `scripts/verify.sh`. It exits non-zero on failure.

## Logins (password for all: `password`)

| Role | Email | Company |
|---|---|---|
| Super Admin | superadmin@assetpulse.test | Platform. Use **Companies → Enter** to work inside a company |
| Company Admin | admin@precisionpolymers.in | Precision Polymers Pvt Ltd, Pune |
| Asset Manager | assets@precisionpolymers.in | Precision Polymers |
| Viewer / Auditor (read-only) | auditor@precisionpolymers.in | Precision Polymers |
| Company Admin (isolation test) | admin@acme.test | Acme Test Industries Ltd |

## What each role can do
- **Super Admin:**
  - Creates and edits companies. Creating a company also provisions its system masters and its first Company Admin.
  - Sees all users and the platform audit log.
  - "Enters" a company to work on that company's data.
- **Company Admin:**
  - Full CRUD on every master and on users for their own company.
  - Edits the company profile.
  - Full access to the asset register.
- **Asset Manager:** creates, edits, deactivates and restores assets and manages attachments. Masters are read-only.
- **Viewer / Auditor:** read-only everywhere, including the audit log. Any write attempt returns 403.

## Design notes
- **Tenancy:**
  - Every business table has a `company_id` column.
  - The `BelongsToCompany` trait adds a global `CompanyScope` and fills `company_id` automatically.
  - The scope fails closed: an authenticated request with no company context sees no rows.
  - Form Requests check every foreign key with `existsInCompany`, so IDs from another tenant are rejected.
  - Route model binding is scoped, so URLs pointing at another tenant's records return 404.
- **Audit:** the `Auditable` trait records created, updated, deactivated and restored events (plus logins). Each record holds old and new values, the user, IP, user agent, URL and timestamp. You can see it on every show page under History, and under Configuration → Audit Log.
- **Soft deletes:** "Deactivate" soft-deletes a record and "Restore" brings it back. A master can't be deactivated while assets still reference it.
- **Asset code:** the category prefix plus a sequence per company, e.g. `IMM-0007`. Sequences use row locks. Type a code on the form to override it.
- **Money:** amounts are stored as `decimal(18,2)` and shown in Indian digit grouping (₹12,34,567.00).
- **Opening balances:** the seeded assets put to use before 1 Apr 2024 carry SLM opening accumulated depreciation as of 31 Mar 2024. NBV = cost − accumulated depreciation.

## Phase 2 (schema and models already in place)
These tables and models exist but have no engine or UI yet:
- `depreciation_runs`, `depreciation_entries` and `period_locks`
- `asset_transfers`
- `pm_schedules` and `work_orders`
- `asset_disposals`

Still to build: the dashboard, reports and Excel/PDF exports.
