#!/usr/bin/env bash
# HTTP smoke test: logs in as each role through nginx and checks status codes.
BASE=${BASE:-http://localhost:8080}
PASS=0; FAIL=0
tmp=$(mktemp -d)

login() { # $1 email -> cookie jar path
  local jar="$tmp/$1.jar"; rm -f "$jar"
  local tok=$(curl -s -c "$jar" -b "$jar" "$BASE/login" | grep -oP 'name="_token" value="\K[^"]+' | head -1)
  local code=$(curl -s -o /dev/null -w '%{http_code}' -c "$jar" -b "$jar" -X POST "$BASE/login" \
     --data-urlencode "_token=$tok" --data-urlencode "email=$1" --data-urlencode "password=password")
  echo "$jar"
  [ "$code" = 302 ] || echo "LOGIN FAILED for $1 ($code)" >&2
}
token() { curl -s -b "$1" -c "$1" "$BASE/$2" | grep -oP 'name="csrf-token" content="\K[^"]+' | head -1; }
expect() { # jar method path expected [data...]
  local jar=$1 m=$2 p=$3 want=$4; shift 4
  local code
  if [ "$m" = GET ]; then code=$(curl -s -o "$tmp/body" -w '%{http_code}' -b "$jar" -c "$jar" "$BASE$p")
  else local t=$(token "$jar" profile); code=$(curl -s -o "$tmp/body" -w '%{http_code}' -b "$jar" -c "$jar" -X POST "$BASE$p" --data-urlencode "_token=$t" --data-urlencode "_method=$m" "$@"); fi
  if [ "$code" = "$want" ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "  FAIL $m $p -> $code (want $want)"; grep -oP '(?<=<title>).*(?=</title>)' "$tmp/body" | head -1; fi
}
body_has() { if grep -q "$1" "$tmp/body"; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "  FAIL body lacks: $1"; fi; }
body_lacks() { if grep -q "$1" "$tmp/body"; then FAIL=$((FAIL+1)); echo "  FAIL body leaks: $1"; else PASS=$((PASS+1)); fi; }

MASTERS="asset-categories locations departments cost-centers vendors depreciation-methods asset-statuses financial-years depreciation-run-parameters users audit-logs"
A1=$(cd "$(dirname "$0")/.." && php artisan tinker --execute='echo \App\Models\Asset::withoutGlobalScopes()->where("company_id",1)->value("id");' 2>/dev/null | tail -1)
A2=$(cd "$(dirname "$0")/.." && php artisan tinker --execute='echo \App\Models\Asset::withoutGlobalScopes()->where("company_id",2)->value("id");' 2>/dev/null | tail -1)
C2=$(cd "$(dirname "$0")/.." && php artisan tinker --execute='echo \App\Models\AssetCategory::withoutGlobalScopes()->where("company_id",2)->value("id");' 2>/dev/null | tail -1)

echo "== Guest"
expect "$tmp/none" GET /dashboard 302
expect "$tmp/none" GET /register 404

echo "== Company admin (admin@precisionpolymers.in)"
J=$(login admin@precisionpolymers.in)
expect $J GET /dashboard 200
for m in $MASTERS; do expect $J GET /admin/$m 200; done
for m in asset-categories locations departments cost-centers vendors financial-years; do expect $J GET /admin/$m/create 200; done
expect $J GET /admin/asset-categories/1 200
expect $J GET /admin/asset-categories/1/edit 200
expect $J GET /admin/company-profile 200
expect $J GET /admin/companies 403
expect $J GET "/assets?q=IMM-0001" 200; body_has "IMM-0001"; body_lacks "ACME Secret"
expect $J GET "/assets?q=Engel&category=1" 200
expect $J GET /assets/create 200
expect $J GET /assets/$A1 200; body_has "<svg"
expect $J GET /assets/$A1/edit 200
expect $J GET /assets/$A1/qr 200
expect $J GET "/assets/$A1/qr?format=png" 200
expect $J GET /assets/$A1/label 200
expect $J GET /profile 200
echo "   -- cross-tenant probes"
expect $J GET /assets/$A2 404
expect $J GET /assets/$A2/edit 404
expect $J GET /admin/asset-categories/$C2 404
expect $J GET "/assets?q=ACME" 200; body_lacks "ACME Secret"
expect $J GET /admin/audit-logs 200; body_lacks "ACME"
echo "   -- writes"
expect $J POST /admin/vendors 302 --data-urlencode "code=VTEST" --data-urlencode "name=Verify Vendor" --data-urlencode "country=India"
VID=$(cd "$(dirname "$0")/.." && php artisan tinker --execute='echo \App\Models\Vendor::withoutGlobalScopes()->where("code","VTEST")->value("id");' 2>/dev/null | tail -1)
expect $J DELETE /admin/vendors/$VID 302
expect $J PATCH /admin/vendors/$VID/restore 302
expect $J GET /admin/vendors/$VID 200; body_has "Verify Vendor"
expect $J POST /assets 302 --data-urlencode "asset_category_id=2" --data-urlencode "name=Verify Mould" --data-urlencode "asset_status_id=1" --data-urlencode "depreciation_method_id=1" --data-urlencode "useful_life_years=8" --data-urlencode "capitalization_date=2026-10-01" --data-urlencode "original_cost=150000" --data-urlencode "residual_value=7500" --data-urlencode "quantity=1"
expect $J POST /assets 302 --data-urlencode "asset_category_id=$C2" --data-urlencode "name=Cross tenant" --data-urlencode "asset_status_id=1" --data-urlencode "depreciation_method_id=1" --data-urlencode "useful_life_years=8" --data-urlencode "capitalization_date=2026-10-01" --data-urlencode "original_cost=1000"
(cd "$(dirname "$0")/.." && php artisan tinker --execute='echo "   created: ".\App\Models\Asset::withoutGlobalScopes()->where("name","Verify Mould")->value("asset_code")." | cross-tenant rows: ".\App\Models\Asset::withoutGlobalScopes()->where("name","Cross tenant")->count();' 2>/dev/null | tail -1)

echo "== Asset manager (assets@precisionpolymers.in)"
J=$(login assets@precisionpolymers.in)
expect $J GET /dashboard 200
expect $J GET /assets 200
expect $J GET /assets/create 200
expect $J GET /assets/$A1/edit 200
expect $J GET /admin/locations 200
expect $J GET /admin/locations/create 403
expect $J GET /admin/users/create 403

echo "== Viewer / auditor (auditor@precisionpolymers.in)"
J=$(login auditor@precisionpolymers.in)
expect $J GET /dashboard 200
for m in $MASTERS; do expect $J GET /admin/$m 200; done
expect $J GET /assets 200
expect $J GET /assets/$A1 200
expect $J GET /assets/create 403
expect $J GET /assets/$A1/edit 403
expect $J GET /admin/vendors/create 403
expect $J POST /admin/vendors 403 --data-urlencode "code=HACK" --data-urlencode "name=Hack"
expect $J DELETE /assets/$A1 403
expect $J GET /admin/company-profile 403

# ---------------------------------------------------------------- Phase 2
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
envv() { grep -E "^$1=" "$ROOT/.env" | cut -d= -f2- | tr -d '"'; }
sql() { mysql -h"$(envv DB_HOST)" -P"$(envv DB_PORT)" -u"$(envv DB_USERNAME)" -p"$(envv DB_PASSWORD)" "$(envv DB_DATABASE)" -N -B -e "$1" 2>/dev/null; }
eq() { # label actual expected
  if [ "$2" = "$3" ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "  FAIL $1: got '$2' want '$3'"; fi; }
post_follow() { # jar path [data...] -> follows redirect into $tmp/body
  local jar=$1 p=$2; shift 2; local t=$(token "$jar" profile)
  curl -s -L -o "$tmp/body" -b "$jar" -c "$jar" "$BASE$p" --data-urlencode "_token=$t" "$@" >/dev/null; }
export_check() { # jar report ext mime magic
  local code ct
  code=$(curl -s -o "$tmp/exp" -D "$tmp/hdr" -w '%{http_code}' -b "$1" "$BASE/reports/$2?export=$3")
  ct=$(grep -i '^content-type:' "$tmp/hdr" | head -1 | tr -d '\r' | cut -d' ' -f2 | cut -d';' -f1)
  if [ "$code" = 200 ] && [ "$ct" = "$4" ] && head -c 4 "$tmp/exp" | grep -q "$5"; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "  FAIL export $2.$3 -> $code $ct"; fi; }
REPORTS="asset-register assets-by-location assets-by-category depreciation-detail depreciation-summary asset-movement maintenance-history disposal-register audit-trail"
XLSX=application/vnd.openxmlformats-officedocument.spreadsheetml.sheet

asset_id() { sql "select id from assets where company_id=1 and name like '%$1%' and deleted_at is null limit 1"; }
entry() { sql "select e.depreciation_amount from depreciation_entries e join assets a on a.id=e.asset_id where a.company_id=1 and a.name like '%$1%' and e.period_start='$2' and e.deleted_at is null"; }

echo "== Depreciation maths (expected values computed independently by hand / Python)"
eq "SLM IMM 350T Apr-2024 (6,850,000×95%/15×30/365)"        "$(entry 'Machine 350T' 2024-04-01)" 35657.53
eq "SLM IMM 120T Aug-2024 part month (13 days from 19-Aug)"  "$(entry 'Machine 120T' 2024-08-01)" 7353.61
eq "WDV Cap Closure Nov-2025 (5,640,000×31.2344%×28/365)"    "$(entry 'Cap Closure' 2025-11-01)" 135137.98
eq "WDV Cap Closure Apr-2026 (opening WDV 4,920,872.89)"     "$(entry 'Cap Closure' 2026-04-01)" 126329.19
eq "SLM Tail Lamp mould Sep-2026 (21 days, FY of 365 days)"  "$(entry 'Tail Lamp' 2026-09-01)" 31974.66
eq "UOP EV mould Jan-2025 (3,695,500×26,000/15,00,000)"      "$(entry 'EV Connector' 2025-01-01)" 64055.33
eq "Low-value caliper fully written off (to residual)"       "$(entry 'Caliper' 2025-08-01)" 4560.00
eq "Never below residual (no asset NBV < residual)"          "$(sql "select count(*) from assets where company_id=1 and current_book_value < residual_value - 0.01")" 0
eq "Asset acc. dep. = opening + sum(entries)" "$(sql "select count(*) from assets a where company_id=1 and abs(a.accumulated_depreciation - a.opening_accumulated_depreciation - (select coalesce(sum(depreciation_amount),0) from depreciation_entries e where e.asset_id=a.id and e.deleted_at is null)) > 0.01")" 0
eq "Disposal P/L = proceeds − cost − NBV" "$(sql "select count(*) from asset_disposals where abs(profit_loss - (sale_value - disposal_cost - nbv_at_disposal)) > 0.01")" 0
eq "Seeded locks Apr-2024..Aug-2026" "$(sql "select count(*) from period_locks where company_id=1 and is_locked=1")" 29

J=$(login admin@precisionpolymers.in)
CLOSE=$(cd "$ROOT" && php artisan tinker --execute='\App\Support\Tenant::set(1); echo inr(\App\Models\Asset::whereHas("status", fn($q)=>$q->whereNotIn("code",["DISPOSED","SCRAPPED"]))->sum("accumulated_depreciation"), 2, false);' 2>/dev/null | tail -1)
expect $J GET "/reports/depreciation-summary" 200; body_has "$CLOSE"
echo "   (Schedule II closing accumulated depreciation reconciles to register: $CLOSE)"

echo "== Period locks & run workflow (admin)"
SEP=$(sql "select id from depreciation_runs where company_id=1 and run_type='periodic' and period_start='2026-09-01' and status='posted'")
AUG=$(sql "select id from depreciation_runs where company_id=1 and run_type='periodic' and period_start='2026-08-01'")
post_follow $J /depreciation/runs --data-urlencode from=2026-08-01 --data-urlencode to=2026-08-31; body_has "is locked"
post_follow $J /depreciation/runs/$AUG/reverse --data-urlencode "reason=verify reverse locked"; eq "finalised run cannot be reversed" "$(sql "select status from depreciation_runs where id=$AUG")" finalized
IMM=$(asset_id 'Machine 250T'); CMM=$(asset_id 'CMM'); PL2=$(sql "select id from locations where company_id=1 and code='PL2'")
post_follow $J /transfers --data-urlencode asset_id=$CMM --data-urlencode transfer_date=2026-07-15 --data-urlencode to_location_id=$PL2 --data-urlencode "reason=back-dated test" --data-urlencode confirm=1; body_has "locked"
post_follow $J /disposals --data-urlencode asset_id=$CMM --data-urlencode disposal_date=2026-07-15 --data-urlencode disposal_type=scrap --data-urlencode sale_value=0 --data-urlencode "reason=back-dated test" --data-urlencode confirm=1; body_has "locked"
eq "back-dated transfer/disposal rejected" "$(sql "select count(*) from asset_transfers where asset_id=$CMM")/$(sql "select count(*) from asset_disposals where asset_id=$CMM")" "0/0"
A350=$(asset_id 'Machine 350T')
post_follow $J /assets/$A350 --data-urlencode _method=PUT --data-urlencode "name=Injection Moulding Machine 350T" --data-urlencode asset_category_id=$(sql "select asset_category_id from assets where id=$A350") \
  --data-urlencode asset_status_id=$(sql "select asset_status_id from assets where id=$A350") --data-urlencode depreciation_method_id=$(sql "select depreciation_method_id from assets where id=$A350") \
  --data-urlencode capitalization_date=2016-04-20 --data-urlencode purchase_date=2016-04-08 --data-urlencode original_cost=6900000 --data-urlencode residual_value=342500 --data-urlencode useful_life_years=15 \
  --data-urlencode opening_balance_date=2024-03-31 --data-urlencode opening_accumulated_depreciation=$(sql "select opening_accumulated_depreciation from assets where id=$A350") --data-urlencode quantity=1
eq "cost edit blocked once depreciation posted" "$(sql "select original_cost from assets where id=$A350")" 6850000.00
expect $J GET "/depreciation/run?from=2026-10-01&to=2026-10-31&preview=1" 200; body_has "Tail Lamp"
post_follow $J /depreciation/runs --data-urlencode from=2026-10-01 --data-urlencode to=2026-10-31; body_has "posted"
OCT=$(sql "select id from depreciation_runs where company_id=1 and period_start='2026-10-01' and status='posted'")
eq "Oct-2026 run posted" "$([ -n "$OCT" ] && echo yes)" yes
post_follow $J /depreciation/runs/$SEP/reverse --data-urlencode "reason=verify out of order"; eq "only newest run can be reversed" "$(sql "select status from depreciation_runs where id=$SEP")" posted
post_follow $J /depreciation/runs/$OCT/reverse --data-urlencode "reason=verify reversal"
eq "Oct run reversed, entries soft-deleted" "$(sql "select status from depreciation_runs where id=$OCT")/$(sql "select count(*) from depreciation_entries where depreciation_run_id=$OCT and deleted_at is null")" "reversed/0"
expect $J POST /depreciation/runs/$SEP/finalize 302
eq "Sep-2026 finalised + locked" "$(sql "select status from depreciation_runs where id=$SEP")/$(sql "select count(*) from period_locks where depreciation_run_id=$SEP and is_locked=1")" "finalized/1"
LOCK=$(sql "select id from period_locks where depreciation_run_id=$SEP and is_locked=1")
expect $J POST /depreciation/locks/$LOCK/unlock 302 --data-urlencode "reason=verify unlock"
eq "unlock returns run to posted" "$(sql "select status from depreciation_runs where id=$SEP")" posted
expect $J POST /depreciation/runs/$SEP/finalize 302
eq "audit trail records run events" "$(sql "select count(*)>0 from audit_logs where company_id=1 and auditable_type like '%DepreciationRun' and event in ('finalized','reversed','unlocked')")" 1

echo "== Transfers, maintenance, disposal (admin)"
expect $J POST /transfers 302 --data-urlencode asset_id=$CMM --data-urlencode transfer_date=$(date +%F) --data-urlencode to_location_id=$PL2 --data-urlencode "reason=Verify transfer to Plant 2"
eq "transfer moved asset + history row" "$(sql "select location_id from assets where id=$CMM")/$(sql "select count(*) from asset_transfers where asset_id=$CMM")" "$PL2/1"
expect $J POST /work-orders 302 --data-urlencode asset_id=$IMM --data-urlencode type=breakdown --data-urlencode priority=high --data-urlencode "title=Verify breakdown" --data-urlencode reported_date=$(date +%F)
WO=$(sql "select id from work_orders where title='Verify breakdown'")
eq "breakdown sets Under Maintenance" "$(sql "select s.code from assets a join asset_statuses s on s.id=a.asset_status_id where a.id=$IMM")" UNDER_MAINTENANCE
expect $J POST /work-orders/$WO/complete 302 --data-urlencode "completed_at=$(date '+%F %H:%M' -d '-1 min')" --data-urlencode labour_cost=1000 --data-urlencode parts_cost=2500 --data-urlencode downtime_hours=3 --data-urlencode "root_cause=Verify" --data-urlencode "action_taken=Verify fix"
eq "completion restores Active, total cost" "$(sql "select s.code from assets a join asset_statuses s on s.id=a.asset_status_id where a.id=$IMM")/$(sql "select total_cost from work_orders where id=$WO")" "ACTIVE/3500.00"
expect $J POST /pm-schedules 302 --data-urlencode asset_id=$IMM --data-urlencode "title=Verify PM" --data-urlencode frequency_unit=months --data-urlencode frequency_interval=3 --data-urlencode start_date=2026-09-15 --data-urlencode alert_days_before=7
eq "PM next due auto-computed" "$(sql "select next_due_date from pm_schedules where title='Verify PM'")" 2026-12-15
LAP=$(asset_id 'Latitude 7440')
expect $J GET "/disposals/preview?asset_id=$LAP&disposal_date=$(date +%F)&sale_value=20000" 200
expect $J POST /disposals 302 --data-urlencode asset_id=$LAP --data-urlencode disposal_date=$(date +%F) --data-urlencode disposal_type=sale --data-urlencode "buyer_name=Verify Buyer" --data-urlencode sale_value=20000 --data-urlencode "reason=Verify disposal" --data-urlencode confirm=1
eq "disposal: status + P/L = proceeds − NBV" "$(sql "select s.code from assets a join asset_statuses s on s.id=a.asset_status_id where a.id=$LAP")/$(sql "select round(20000 - nbv_at_disposal,2) = round(profit_loss,2) from asset_disposals where asset_id=$LAP")" "DISPOSED/1"
expect $J GET /assets/$LAP/edit 302
post_follow $J /transfers --data-urlencode asset_id=$LAP --data-urlencode transfer_date=$(date +%F) --data-urlencode to_location_id=$PL2 --data-urlencode "reason=transfer disposed"; body_has "disposed"
expect $J DELETE /assets/$LAP 302; eq "disposed asset not deleted" "$(sql "select deleted_at is null from assets where id=$LAP")" 1

echo "== Role pages"
for u in admin@precisionpolymers.in assets@precisionpolymers.in auditor@precisionpolymers.in; do
  J=$(login $u)
  for p in /dashboard /depreciation /depreciation/runs/$SEP /transfers /pm-schedules /work-orders /disposals /reports /assets/$LAP; do expect $J GET $p 200; done
  for r in $REPORTS; do expect $J GET "/reports/$r" 200; done
done
J=$(login assets@precisionpolymers.in)
for p in /depreciation/run /transfers/create /pm-schedules/create /work-orders/create /disposals/create; do expect $J GET $p 200; done
expect $J POST /depreciation/runs/$OCT/finalize 403
expect $J POST /depreciation/locks/$LOCK/unlock 403 --data-urlencode "reason=manager unlock"
J=$(login auditor@precisionpolymers.in)
for p in /depreciation/run /transfers/create /pm-schedules/create /work-orders/create /disposals/create; do expect $J GET $p 403; done
expect $J POST /depreciation/runs 403 --data-urlencode from=2026-10-01 --data-urlencode to=2026-10-31
expect $J POST /transfers 403 --data-urlencode asset_id=$CMM
expect $J POST /work-orders/$WO/cancel 403
echo "   -- exports (viewer)"
for r in $REPORTS; do export_check $J $r xlsx $XLSX PK; export_check $J $r pdf application/pdf %PDF; done
expect $J GET "/reports/nope" 404
expect $J GET "/reports/asset-register?export=csv" 302

echo "== Phase 2 tenant isolation"
ACRUN=$(sql "select id from depreciation_runs where company_id=2 limit 1"); ACTR=$(sql "select id from asset_transfers where company_id=2 limit 1")
ACWO=$(sql "select id from work_orders where company_id=2 limit 1"); ACDS=$(sql "select id from asset_disposals where company_id=2 limit 1"); ACPM=$(sql "select id from pm_schedules where company_id=2 limit 1")
ACPOST=$(sql "select id from depreciation_runs where company_id=2 and status='posted' and run_type='periodic' limit 1")
J=$(login admin@precisionpolymers.in)
expect $J GET /depreciation/runs/$ACRUN 404
expect $J GET /transfers/$ACTR 404
expect $J GET /work-orders/$ACWO 404
expect $J GET /disposals/$ACDS 404
expect $J GET /pm-schedules/$ACPM/edit 404
expect $J POST /depreciation/runs/$ACPOST/finalize 404
expect $J POST /work-orders/$ACWO/cancel 404
eq "ACME run untouched" "$(sql "select status from depreciation_runs where id=$ACPOST")" posted
for p in /transfers /work-orders /disposals /pm-schedules /dashboard; do expect $J GET $p 200; body_lacks "ACME"; done
for r in $REPORTS; do expect $J GET "/reports/$r" 200; body_lacks "ACME Secret"; done
J=$(login admin@acme.test)
for p in /depreciation /transfers /work-orders /disposals /pm-schedules /dashboard; do expect $J GET $p 200; body_lacks "Injection Moulding"; body_lacks "Precision Polymers"; done
expect $J GET /depreciation/runs/$SEP 404
for r in $REPORTS; do expect $J GET "/reports/$r" 200; body_lacks "Injection Moulding"; done
curl -s -o "$tmp/acme.xlsx" -b $J "$BASE/reports/depreciation-detail?export=xlsx"
if unzip -p "$tmp/acme.xlsx" xl/sharedStrings.xml | grep -q "Injection Moulding\|Precision"; then FAIL=$((FAIL+1)); echo "  FAIL ACME xlsx leaks PPPL"; else PASS=$((PASS+1)); fi

echo "== Second company admin (admin@acme.test)"
J=$(login admin@acme.test)
expect $J GET /assets 200; body_has "ACME Secret"; body_lacks "Engel"; body_lacks "IMM-0001"
expect $J GET /assets/$A1 404
expect $J GET /admin/vendors 200; body_lacks "Milacron"
expect $J GET /admin/users 200; body_lacks "precisionpolymers"
expect $J GET /admin/audit-logs 200; body_lacks "Precision"
expect $J GET /dashboard 200; body_lacks "Injection Moulding"

echo "== Super admin (superadmin@assetpulse.test)"
J=$(login superadmin@assetpulse.test)
expect $J GET /dashboard 302
expect $J GET /admin/companies 200; body_has "Precision Polymers"
expect $J GET /admin/companies/create 200
expect $J GET /admin/companies/1 200
expect $J GET /admin/companies/1/edit 200
expect $J GET /admin/users 200
expect $J GET /admin/audit-logs 200
expect $J GET /assets 302
expect $J POST /admin/companies/1/enter 302
expect $J GET /dashboard 200
expect $J GET "/assets?q=IMM-0001" 200; body_has "IMM-0001"; body_lacks "ACME Secret"
for m in $MASTERS; do expect $J GET /admin/$m 200; done
expect $J POST /admin/companies/exit 302
expect $J GET /assets 302

echo; echo "PASSED: $PASS  FAILED: $FAIL"
rm -rf "$tmp"
[ $FAIL -eq 0 ]
