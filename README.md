# AssetPulse — Fixed Asset Management

Multi-tenant fixed asset register for Indian manufacturers. It follows Schedule II (Companies Act 2013) and is ready for Ind AS 16.
Stack: Laravel 13, PHP 8.4, MariaDB/MySQL, Breeze (Blade), Tailwind 3, Alpine, Chart.js, PhpSpreadsheet (Excel), dompdf (PDF) and endroid/qr-code.

## Run it

```bash
sudo service mariadb start && sudo service php8.4-fpm start && sudo service nginx start
cd /workspace/assetpulse
composer install && npm install && npm run build      # production assets, no Vite dev server needed
php artisan migrate:fresh --seed
php artisan storage:link
chmod -R 777 storage bootstrap/cache
```

Nginx serves `public/` at **http://localhost:8080**.

**Verification.** `scripts/verify.sh` runs about 330 HTTP and database checks against a freshly seeded database and exits non-zero on any failure. Because it writes test data (an asset, a transfer, a work order, a disposal, and an Oct-2026 run that it posts and then reverses), reseed afterwards. It covers:
- every page for every role;
- export content types;
- hand-computed SLM, WDV, UOP and low-value figures;
- period locks;
- tenant isolation.

`php artisan test` runs the PHPUnit suite. It uses the `assetpulse_test` database and includes the depreciation calculator unit tests.

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

## Modules

### Depreciation (`/depreciation`)
**Methods.**
- **SLM** = (cost − residual) / life.
- **WDV** = rate × opening WDV. The rate comes from the method master (Schedule II rate, 1 − (residual/cost)^(1/n), e.g. 31.2344% for 8 years at 5%).
- **UOP** = (cost − residual) × units used / total capacity. Units come from `units_consumed`, i.e. meter readings.

**Proration.**
- Proration is by days: days in the period ÷ days in the financial year (365 or 366).
- The first period starts on the put-to-use (capitalisation) date.
- A period that crosses a financial-year boundary is split per FY.
- WDV takes its opening value at the FY start. In the year of capitalisation the base is cost.

**Guards.**
- The charge never takes NBV below residual.
- Depreciation stops at the end of useful life, and the last charge trues the asset up to exactly residual.
- An asset whose cost is at or below the run parameter's low-value threshold (default ₹5,000) is charged in full down to residual in its first period.
- Disposed, scrapped, CWIP and inactive assets are skipped.
- Assets that are Under Maintenance keep depreciating (Schedule II is time-based).

**Workflow.**
1. **Preview:** a dry run that shows the line items and validation messages.
2. **Post:** writes the entries and updates the asset balances.
3. **Finalize** (Company Admin only): creates a period lock.

**Locks and corrections.**
- Only the newest run that is not finalized can be **reversed**. Reversing soft-deletes the entries and restores the balances.
- A locked period refuses:
  - a new run;
  - back-dated transfers or disposals;
  - edits to cost, capitalisation date, life or method.
- Only a Company Admin can **unlock** a period, and the unlock needs a reason. The run then returns to "posted".
- Runs must be continuous (no gaps or overlaps) and cannot be for a future period.
- Every action is written to the audit log, and each entry stores its calculation details (base, rate, days, FY split).

### Transfers (`/transfers`)
- A transfer moves an asset between location, department, cost centre or custodian. Each one gets a document number (`TRF/2627/0001`) and is kept in the history on the asset page.
- Transfers cannot be future-dated and cannot fall in a locked period. They also cannot be dated before the asset's last transfer.
- **Cost-centre split.** Each entry is tagged with the asset's cost centre, location and department as they stood in that period, so back-dated history keeps reporting correctly. When a transfer marked "affects depreciation" falls mid-period, the entry also records a day-based split of the charge between the old and new cost centres. The run screen flags these entries as "split".

### Maintenance (`/pm-schedules`, `/work-orders`)
**PM schedules.**
- A PM schedule can run every N **days**, **months** or **running hours**.
- For hours-based schedules, the next-due date comes from the current meter reading and the average daily hours.
- Completing a PM work order rolls `last_done` and `next_due` forward automatically.
- Overdue PMs and PMs due within their alert window appear on the dashboard. The sidebar shows a badge with the count.

**Work orders.**
- Work orders can be preventive, breakdown, corrective or inspection. Each one tracks priority, assignee, vendor, labour, parts and external costs, downtime and root cause.
- An open **breakdown** sets the asset to **Under Maintenance**. Completing or cancelling the last open breakdown sets it back to Active.

### Disposal (`/disposals`)
Disposal types are sale, scrap, write-off, donation, theft/loss and insurance claim.

**Preview.** Before you confirm, the screen shows:
- the pro-rata depreciation from the last run up to the disposal date;
- NBV at the disposal date;
- **P/L = (proceeds − disposal cost) − NBV**.

**On confirm.**
- The pro-rata charge is posted as a "disposal" depreciation run.
- The asset becomes **Disposed** or **Scrapped** and is read-only: no edits, transfers, work orders or deletion.
- Its PM schedules are deactivated and its open work orders are cancelled.
- Disposals dated inside a locked period are refused.

### Dashboard and reports
**Dashboard.**
- KPIs: gross block, NBV, accumulated depreciation, FY-to-date depreciation, asset count, maintenance alerts and disposals this FY.
- Charts: monthly depreciation, NBV by category, assets by location, and maintenance cost.
- Lists of recent runs, upcoming and overdue PMs, and open breakdowns.

**Reports** (`/reports`). Each report takes filters for date or FY, category, location, cost centre and status, and exports to **Excel (.xlsx)** or **PDF**. The PDF export is capped at 1,500 rows; use Excel for larger results. The reports are:
- Asset register
- Assets by location
- Assets by category
- Depreciation detail (per asset per period)
- Depreciation summary (Schedule II style: opening, additions, deductions and closing, for both gross block and depreciation)
- Asset movement (transfers)
- Maintenance history
- Disposal register (with P/L)
- Audit trail

### Roles (Phase 2 additions)
| Action | Admin | Asset Manager | Viewer |
|---|---|---|---|
| View runs, transfers, maintenance, disposals, reports and exports | ✓ | ✓ | ✓ |
| Preview, post or reverse a depreciation run | ✓ | ✓ | – |
| Finalize a run or unlock a period | ✓ | – | – |
| Transfers, PM schedules, work orders, disposals | ✓ | ✓ | – |

## Seeded data (Precision Polymers Pvt Ltd)
- **Assets.** 49 assets across 13 categories, put to use between 2016 and Oct-2026. They include IMMs, moulds, auxiliaries, utilities, the tool room, QA equipment, IT, vehicles and furniture. Methods:
  - Cap Closure mould: **WDV**.
  - EV Connector mould: **UOP** (1.5 million shots).
  - Vernier caliper: low-value (₹4,800).
  - Everything else: SLM.
- **Depreciation.** Runs every month from **Apr-2024 to Sep-2026** in the order a real user would post them:
  - Apr-2024 to Aug-2026 are finalized and locked (29 locks).
  - Sep-2026 is **posted** and not finalized, so you can try finalizing it or reversing it.
  - Oct-2026 is open.
- **Transfers.** 7 transfers, including a temporary move and return of a mould to the tool room.
- **Disposals.**
  - Tata 407: scrapped Dec-2024, gain ₹33,250.
  - Tata Intra V30: sold Feb-2026, loss ₹50,756.30, after pro-rata depreciation of ₹2,814.21.
- **Maintenance.**
  - 14 PM schedules (days, months and running hours) with about 90 completed PM work orders.
  - Breakdowns with downtime and costs.
  - Overdue PMs (two on the seed date; more fall due as days pass).
  - An open breakdown that keeps the EV Connector mould *Under Maintenance*.
- **Totals.** Live gross block ₹26,61,83,800 · NBV ₹17,41,95,214.28 · FY 2026-27 depreciation (Apr–Sep) ₹84,66,848.59.
- **Isolation tenant.** A second company, Acme, has its own runs, a transfer, a PM, a work order and a disposal, all marked "ACME Secret". This lets you check that nothing leaks between tenants.

## Judgement calls
- **Proration basis.** Proration is by days rather than by month, and leap years use 366 days. The engine cuts periods by calendar month and splits any period that crosses a financial year.
- **WDV rate.** The WDV rate comes from the method master, not per asset. This mirrors how Schedule II rates are applied.
- **Low-value assets.** Low-value treatment is a threshold on the run parameter. It is not a separate method.
- **Shift basis.** The shift basis (single, double or triple, giving factors of 1, 1.5 or 2) is set on the run parameter and applies to the whole run. The seed uses single shift.
- **Under Maintenance.** Assets keep depreciating while Under Maintenance.
- **Disposal and the period lock.** A disposal posts its own small run, which is finalized together with the period lock. Disposals are therefore always dated after the last posted periodic run.
- **Reversal order.** Only the latest run can be reversed, which keeps balances consistent. To correct an older period, reverse the later runs first.
- **Unlocking.** Unlocking needs a reason and is audited. It does not delete anything.

## Known gaps
- No maker-checker approval on transfers or disposals; they take effect immediately.
- No per-asset shift tracking (shift basis applies to the whole run), impairment (Ind AS 36), revaluation or Income-Tax-Act block depreciation (the book is "companies_act" only).
- No partial disposal or splitting an asset into components.
- PDF exports are capped at 1,500 rows.
- Reports run synchronously; there are no queued exports.
- No email or notification delivery for PM alerts; they appear in the app only.
