#!/usr/bin/env bash
# HTTP smoke test: logs in as each role through nginx and checks status codes.
BASE=${BASE:-http://localhost:8080}
PASS=0; FAIL=0
tmp=$(mktemp -d)

login() { # $1 email -> cookie jar path
  local jar="$tmp/$1.jar"
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
