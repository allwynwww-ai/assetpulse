<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\DepreciationMethod;
use App\Models\Location;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AssetCodeGenerator;
use App\Services\CompanyProvisioner;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /** Takeover date: balances brought forward from the legacy register. */
    private const OPENING_DATE = '2024-03-31';

    public function run(): void
    {
        $pwd = Hash::make('password');

        $this->user(null, Role::SuperAdmin, 'Platform Super Admin', 'superadmin@assetpulse.test', $pwd, 'Platform Owner');

        $pp = $this->precisionPolymers($pwd);
        $this->isolationTestCompany($pwd);

        $this->command?->info("Seeded {$pp->name} and a second isolation-test company. All passwords: password");
    }

    // ------------------------------------------------------------------
    private function user(?int $companyId, Role $role, string $name, string $email, string $pwd, ?string $designation = null): User
    {
        $u = User::create([
            'company_id' => $companyId, 'role' => $role->value, 'name' => $name, 'email' => $email,
            'password' => $pwd, 'designation' => $designation,
        ]);
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    private function precisionPolymers(string $pwd): Company
    {
        $company = Company::create([
            'code' => 'PPPL', 'name' => 'Precision Polymers Pvt Ltd', 'legal_name' => 'Precision Polymers Private Limited',
            'cin' => 'U25209PN2012PTC143215', 'gstin' => '27AAHCP4521K1Z8', 'pan' => 'AAHCP4521K',
            'email' => 'accounts@precisionpolymers.in', 'phone' => '+91 20 2712 4400',
            'address_line1' => 'Plot J-118, MIDC Bhosari', 'address_line2' => 'Telco Road', 'city' => 'Pune', 'state' => 'Maharashtra',
            'pincode' => '411026', 'fy_start_month' => 4, 'depreciation_basis' => 'companies_act',
            'asset_code_separator' => '-', 'asset_code_padding' => 4,
        ]);
        app(CompanyProvisioner::class)->provision($company);

        $this->user($company->id, Role::CompanyAdmin, 'Rohan Kulkarni', 'admin@precisionpolymers.in', $pwd, 'Finance Controller');
        $manager = $this->user($company->id, Role::AssetManager, 'Sneha Patil', 'assets@precisionpolymers.in', $pwd, 'Asset & Maintenance Manager');
        $this->user($company->id, Role::Viewer, 'Amit Deshpande (CA)', 'auditor@precisionpolymers.in', $pwd, 'Statutory Auditor');

        Tenant::run($company->id, function () use ($manager) {
            $slm = DepreciationMethod::where('code', 'SLM')->value('id');
            $wdv = DepreciationMethod::where('code', 'WDV')->value('id');

            // Schedule II (Companies Act 2013, Part C) useful lives
            $cats = [];
            foreach ([
                ['IMM', 'Plant & Machinery – Injection Moulding Machines', 'IMM', 15, 0, 'Part C, II – Plant & Machinery (general, non-continuous process): 15 yrs', 5000, '1201'],
                ['MLD', 'Moulds & Dies', 'MLD', 8, 0, 'Part C, II(xxx) – Rubber & plastic goods: Moulds 8 yrs', 5000, '1202'],
                ['AUX', 'Plant & Machinery – Auxiliaries (Chillers, Dryers, Robots)', 'AUX', 15, 0, 'Part C, II – Plant & Machinery (general): 15 yrs', 5000, '1203'],
                ['COMP', 'Computers', 'CMP', 3, 0, 'Part C, VI(iii) – Computers: end-user devices 3 yrs', 5000, '1301'],
                ['SRV', 'Servers & Networks', 'SRV', 6, 0, 'Part C, VI(i) – Servers and networks: 6 yrs', 5000, '1302'],
                ['VEH', 'Vehicles', 'VEH', 8, 0, 'Part C, IV(ii) – Motor cars / lorries (not on hire): 8 yrs', 5000, '1401'],
                ['FUR', 'Furniture & Fixtures', 'FUR', 10, 0, 'Part C, III(i) – Furniture and fittings: 10 yrs', 5000, '1501'],
                ['ELE', 'Electrical Installations', 'ELE', 10, 0, 'Part C, XIII – Electrical installations & equipment: 10 yrs', 5000, '1601'],
                ['BLD', 'Buildings – Factory', 'BLD', 30, 0, 'Part C, I(i)(a) – Factory buildings: 30 yrs', 0, '1101'],
            ] as [$code, $name, $prefix, $years, $months, $ref, $threshold, $gl]) {
                $cats[$code] = AssetCategory::create([
                    'code' => $code, 'name' => $name, 'asset_code_prefix' => $prefix, 'depreciation_method_id' => $slm,
                    'useful_life_years' => $years, 'useful_life_months' => $months, 'residual_value_percent' => 5,
                    'wdv_rate' => AssetCategory::computeWdvRate($years, 5), 'schedule_ii_reference' => $ref,
                    'capitalization_threshold' => $threshold, 'gl_asset_account' => $gl,
                    'gl_accumulated_depreciation_account' => '1'.substr($gl, 1).'9', 'gl_depreciation_expense_account' => '6101',
                ]);
            }

            // Plants / locations
            $loc = [];
            foreach ([
                ['PL1', 'Plant 1 – Chakan', 'plant', 'Gat No. 412, Chakan Industrial Area Phase II', 'Chakan, Pune', '410501', 'Vikram Jadhav'],
                ['PL2', 'Plant 2 – Ranjangaon', 'plant', 'Plot B-27, MIDC Ranjangaon', 'Ranjangaon, Pune', '412220', 'Prakash More'],
                ['PL3', 'Plant 3 – Bhosari (Tool Room)', 'plant', 'Plot J-118, MIDC Bhosari', 'Pune', '411026', 'Nitin Gaikwad'],
                ['HO', 'Head Office – Bhosari', 'office', 'Plot J-118, MIDC Bhosari, Telco Road', 'Pune', '411026', 'Rohan Kulkarni'],
                ['WH1', 'Finished Goods Warehouse – Chakan', 'warehouse', 'Gat No. 415, Chakan', 'Chakan, Pune', '410501', 'Sagar Pawar'],
            ] as [$code, $name, $type, $addr, $city, $pin, $contact]) {
                $loc[$code] = Location::create(['code' => $code, 'name' => $name, 'type' => $type, 'address' => $addr, 'city' => $city, 'state' => 'Maharashtra', 'pincode' => $pin, 'gstin' => '27AAHCP4521K1Z8', 'contact_person' => $contact]);
            }

            $dep = [];
            foreach ([
                ['PRD', 'Production – Moulding', 'PL1', 'Vikram Jadhav'],
                ['PRD2', 'Production – Assembly', 'PL2', 'Prakash More'],
                ['TOOL', 'Tool Room', 'PL3', 'Nitin Gaikwad'],
                ['MNT', 'Maintenance', 'PL1', 'Suresh Shinde'],
                ['QA', 'Quality Assurance', 'PL1', 'Meera Joshi'],
                ['STR', 'Stores & Logistics', 'WH1', 'Sagar Pawar'],
                ['IT', 'IT', 'HO', 'Kiran Bhosale'],
                ['ADM', 'Admin & Finance', 'HO', 'Rohan Kulkarni'],
            ] as [$code, $name, $l, $head]) {
                $dep[$code] = Department::create(['code' => $code, 'name' => $name, 'location_id' => $loc[$l]->id, 'head_name' => $head]);
            }

            $cc = [];
            foreach ([
                ['CC-1100', 'Moulding Shop – Chakan', 'PRD', '5101'],
                ['CC-1200', 'Assembly Line – Ranjangaon', 'PRD2', '5102'],
                ['CC-1300', 'Tool Room', 'TOOL', '5103'],
                ['CC-2100', 'Plant Maintenance', 'MNT', '5201'],
                ['CC-2200', 'Quality Lab', 'QA', '5202'],
                ['CC-3100', 'Warehouse & Dispatch', 'STR', '5301'],
                ['CC-9100', 'IT Services', 'IT', '5901'],
                ['CC-9200', 'Corporate Admin', 'ADM', '5902'],
            ] as [$code, $name, $d, $gl]) {
                $cc[$code] = CostCenter::create(['code' => $code, 'name' => $name, 'department_id' => $dep[$d]->id, 'gl_code' => $gl]);
            }

            $ven = [];
            foreach ([
                ['MIL', 'Milacron India Pvt Ltd', 'Ahmedabad', 'Gujarat', '24AABCF1234M1Z5', 'sales.india@milacron.com', 45],
                ['ENG', 'Engel Machinery India Pvt Ltd', 'Pune', 'Maharashtra', '27AACCE5678N1Z2', 'sales.in@engel.at', 60],
                ['FER', 'Ferromatik Milacron (Machines Division)', 'Ahmedabad', 'Gujarat', '24AABCF1234M2Z4', 'machines@ferromatik.in', 45],
                ['HTN', 'Haitian Huayuan Machinery (India) Pvt Ltd', 'Chennai', 'Tamil Nadu', '33AADCH7788P1Z9', 'india@haitian-asia.com', 30],
                ['TOS', 'Toshiba Machine (India) Pvt Ltd', 'Chennai', 'Tamil Nadu', '33AACCT4455Q1Z1', 'sales@toshiba-machine.in', 45],
                ['SMT', 'Sai Mould Tech', 'Pune', 'Maharashtra', '27ABCFS9087R1Z6', 'info@saimouldtech.in', 30],
                ['DEL', 'Dell International Services India Pvt Ltd', 'Bengaluru', 'Karnataka', '29AACCD2345S1Z3', 'orders.india@dell.com', 30],
                ['TAT', 'Tata Motors Ltd (via Pune Dealer)', 'Pune', 'Maharashtra', '27AAACT2727Q1ZW', 'fleet@tatamotors.com', 15],
                ['GEN', 'Godrej & Boyce Mfg Co Ltd (Interio)', 'Mumbai', 'Maharashtra', '27AAACG1395D1ZU', 'interio.b2b@godrej.com', 30],
                ['SCH', 'Schneider Electric India Pvt Ltd', 'Pune', 'Maharashtra', '27AAACS7853F1ZQ', 'in-sales@se.com', 45],
                ['BLU', 'Blue Star Ltd', 'Mumbai', 'Maharashtra', '27AAACB4487D1Z2', 'industrial@bluestarindia.com', 30],
                ['LNT', 'L&T Construction (Buildings & Factories)', 'Mumbai', 'Maharashtra', '27AAACL0140P1ZG', 'bf@larsentoubro.com', 60],
            ] as [$code, $name, $city, $state, $gstin, $email, $terms]) {
                $ven[$code] = Vendor::create(['code' => $code, 'name' => $name, 'city' => $city, 'state' => $state, 'gstin' => $gstin, 'pan' => substr($gstin, 2, 10), 'email' => $email, 'payment_terms_days' => $terms]);
            }

            $status = AssetStatus::pluck('id', 'code');
            $gen = app(AssetCodeGenerator::class);

            // [category, name, make, model, location, dept, cost center, vendor, put-to-use, cost, status, method?]
            $rows = [
                ['BLD', 'Factory Building – Chakan Plant 1 (RCC, 4,200 m²)', null, null, 'PL1', 'ADM', 'CC-9200', 'LNT', '2014-06-01', 68500000],
                ['BLD', 'Factory Shed – Ranjangaon Plant 2 (PEB)', null, null, 'PL2', 'ADM', 'CC-9200', 'LNT', '2019-09-15', 42750000],
                ['IMM', 'Injection Moulding Machine 350T', 'Milacron', 'Magna T 350', 'PL1', 'PRD', 'CC-1100', 'MIL', '2016-04-20', 6850000],
                ['IMM', 'Injection Moulding Machine 250T', 'Milacron', 'Magna T 250', 'PL1', 'PRD', 'CC-1100', 'MIL', '2017-08-10', 5420000],
                ['IMM', 'Injection Moulding Machine 180T', 'Ferromatik', 'Elektra Evolution 180', 'PL1', 'PRD', 'CC-1100', 'FER', '2018-11-05', 4675000],
                ['IMM', 'Injection Moulding Machine 450T', 'Engel', 'Victory 450', 'PL1', 'PRD', 'CC-1100', 'ENG', '2020-02-18', 14250000],
                ['IMM', 'Injection Moulding Machine 200T (All-electric)', 'Engel', 'e-mac 200', 'PL1', 'PRD', 'CC-1100', 'ENG', '2021-07-12', 11800000],
                ['IMM', 'Injection Moulding Machine 280T', 'Haitian', 'Mars III MA2800', 'PL2', 'PRD2', 'CC-1200', 'HTN', '2022-01-24', 3985000],
                ['IMM', 'Injection Moulding Machine 160T', 'Haitian', 'Mars III MA1600', 'PL2', 'PRD2', 'CC-1200', 'HTN', '2022-01-24', 2865000],
                ['IMM', 'Injection Moulding Machine 650T', 'Haitian', 'Jupiter III JU6500', 'PL2', 'PRD2', 'CC-1200', 'HTN', '2023-05-30', 9475000],
                ['IMM', 'Injection Moulding Machine 120T (Servo)', 'Toshiba', 'EC130SXIII', 'PL2', 'PRD2', 'CC-1200', 'TOS', '2024-08-19', 3260000],
                ['IMM', 'Injection Moulding Machine 220T (Two-shot)', 'Engel', 'Victory 220 combi', 'PL1', 'PRD', 'CC-1100', 'ENG', '2025-06-16', 16950000],
                ['MLD', 'Mould – Bumper Bracket 4-cavity (P20)', null, null, 'PL1', 'PRD', 'CC-1100', 'SMT', '2019-03-12', 1850000],
                ['MLD', 'Mould – Door Trim Clip 32-cavity (H13)', null, null, 'PL1', 'PRD', 'CC-1100', 'SMT', '2020-10-08', 2275000],
                ['MLD', 'Mould – Battery Housing 1+1 family', null, null, 'PL2', 'PRD2', 'CC-1200', 'SMT', '2021-12-02', 3150000],
                ['MLD', 'Mould – Air Vent Louver 2-cavity hot runner', null, null, 'PL1', 'PRD', 'CC-1100', 'SMT', '2022-09-21', 2680000],
                ['MLD', 'Mould – Washer Tub Cover 1-cavity', null, null, 'PL2', 'PRD2', 'CC-1200', 'SMT', '2023-07-14', 4325000],
                ['MLD', 'Mould – EV Connector Housing 8-cavity', null, null, 'PL3', 'TOOL', 'CC-1300', 'SMT', '2025-01-10', 3890000, 'UNDER_MAINTENANCE'],
                ['MLD', 'Mould – Cap Closure 48-cavity (stack)', null, null, 'PL2', 'PRD2', 'CC-1200', 'SMT', '2025-11-03', 5640000, 'ACTIVE', true],
                ['AUX', 'Screw Chiller 40TR', 'Blue Star', 'BSC-40', 'PL1', 'MNT', 'CC-2100', 'BLU', '2018-05-22', 1425000],
                ['AUX', 'Hopper Dryer & Loader Set (6 nos)', 'Conair', 'CD-150', 'PL1', 'PRD', 'CC-1100', 'MIL', '2020-02-18', 965000],
                ['AUX', 'Sprue Picker Robots (4 nos)', 'Yushin', 'RA-II', 'PL2', 'PRD2', 'CC-1200', 'HTN', '2023-05-30', 1880000],
                ['AUX', 'CNC Vertical Machining Centre', 'BFW', 'BMV 45 TC20', 'PL3', 'TOOL', 'CC-1300', 'SMT', '2021-03-15', 3475000],
                ['AUX', 'Wire-cut EDM', 'Electronica', 'ULTIMA LX', 'PL3', 'TOOL', 'CC-1300', 'SMT', '2022-06-27', 2350000],
                ['AUX', 'CMM – Coordinate Measuring Machine', 'Zeiss', 'Contura G2', 'PL1', 'QA', 'CC-2200', 'GEN', '2023-10-09', 4150000],
                ['ELE', 'HT Substation 1000 kVA with Transformer', 'Schneider Electric', 'Trihal 1000', 'PL1', 'MNT', 'CC-2100', 'SCH', '2016-04-01', 3850000],
                ['ELE', 'Main LT Panel & APFC Panel', 'Schneider Electric', 'Blokset', 'PL2', 'MNT', 'CC-2100', 'SCH', '2019-09-15', 1675000],
                ['ELE', 'Rooftop Solar PV 250 kWp', 'Tata Power Solar', 'TP-250', 'PL2', 'MNT', 'CC-2100', 'SCH', '2024-02-20', 11250000],
                ['ELE', 'DG Set 500 kVA', 'Cummins', 'C500D5', 'PL1', 'MNT', 'CC-2100', 'SCH', '2020-12-07', 2785000],
                ['COMP', 'Laptop – Latitude 5440 (Finance)', 'Dell', 'Latitude 5440', 'HO', 'ADM', 'CC-9200', 'DEL', '2024-04-25', 86500],
                ['COMP', 'Laptop – Latitude 7440 (MD Office)', 'Dell', 'Latitude 7440', 'HO', 'ADM', 'CC-9200', 'DEL', '2024-04-25', 142000],
                ['COMP', 'Workstation – Precision 3660 (Mould Design CAD)', 'Dell', 'Precision 3660', 'PL3', 'TOOL', 'CC-1300', 'DEL', '2023-01-16', 248500],
                ['COMP', 'Desktops – OptiPlex 7010 (10 nos, Shop-floor MES)', 'Dell', 'OptiPlex 7010', 'PL1', 'PRD', 'CC-1100', 'DEL', '2025-08-04', 642000],
                ['COMP', 'Laptops – Latitude 3540 (5 nos, QA)', 'Dell', 'Latitude 3540', 'PL1', 'QA', 'CC-2200', 'DEL', '2021-06-10', 312500],
                ['SRV', 'ERP Server – PowerEdge R750', 'Dell', 'PowerEdge R750', 'HO', 'IT', 'CC-9100', 'DEL', '2022-04-11', 1185000],
                ['SRV', 'Network Backbone & Firewall', 'Cisco / Fortinet', 'FG-100F', 'HO', 'IT', 'CC-9100', 'DEL', '2024-11-18', 685000],
                ['VEH', 'Tata Ultra T.7 Truck (MH-14-KQ-4471)', 'Tata Motors', 'Ultra T.7', 'WH1', 'STR', 'CC-3100', 'TAT', '2021-09-01', 1645000],
                ['VEH', 'Tata Intra V30 Pickup (MH-14-LB-2290)', 'Tata Motors', 'Intra V30', 'PL2', 'STR', 'CC-3100', 'TAT', '2023-03-20', 865000],
                ['VEH', 'Tata Nexon EV – Staff Car (MH-12-XY-8812)', 'Tata Motors', 'Nexon EV Max', 'HO', 'ADM', 'CC-9200', 'TAT', '2024-12-12', 1895000],
                ['VEH', 'Tata 407 Gold (MH-14-EV-1187)', 'Tata Motors', '407 Gold SFC', 'PL1', 'STR', 'CC-3100', 'TAT', '2015-07-07', 945000, 'SCRAPPED'],
                ['FUR', 'Workstations & Cabins – Head Office', 'Godrej Interio', 'Modular', 'HO', 'ADM', 'CC-9200', 'GEN', '2019-01-28', 1385000],
                ['FUR', 'Conference Room Furniture', 'Godrej Interio', 'Exe-Series', 'HO', 'ADM', 'CC-9200', 'GEN', '2024-06-03', 412000],
                ['FUR', 'Tool Room Racks & Mould Storage', 'Godrej', 'Heavy Duty', 'PL3', 'TOOL', 'CC-1300', 'GEN', '2022-08-29', 528000],
            ];

            $parentImm = null;
            foreach ($rows as $i => $r) {
                [$catCode, $name, $make, $model, $l, $d, $c, $v, $date, $cost] = $r;
                $statusCode = $r[10] ?? 'ACTIVE';
                $useWdv = $r[11] ?? false;
                $cat = $cats[$catCode];
                $put = Carbon::parse($date);
                $residual = round($cost * 0.05, 2);
                $lifeYears = $cat->useful_life_years;

                // Takeover balances for assets put to use before 1-Apr-2024 (SLM, day-based)
                $opening = 0;
                $openingDate = null;
                if ($put->lt(Carbon::parse(self::OPENING_DATE))) {
                    $days = $put->diffInDays(Carbon::parse(self::OPENING_DATE)) + 1;
                    $opening = min($cost - $residual, round(($cost - $residual) / $lifeYears * $days / 365.25, 2));
                    $openingDate = self::OPENING_DATE;
                }

                $asset = new Asset([
                    'asset_code' => $gen->next($cat),
                    'name' => $name,
                    'asset_category_id' => $cat->id,
                    'location_id' => $loc[$l]->id,
                    'department_id' => $dep[$d]->id,
                    'cost_center_id' => $cc[$c]->id,
                    'vendor_id' => $ven[$v]->id,
                    'depreciation_method_id' => $useWdv ? $wdv : $slm,
                    'asset_status_id' => $status[$statusCode],
                    'manufacturer' => $make,
                    'model' => $model,
                    'serial_number' => $make ? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $make), 0, 3)).'-'.$put->format('y').str_pad((string) (1000 + $i * 37), 5, '0', STR_PAD_LEFT) : null,
                    'asset_tag' => 'PP/'.$catCode.'/'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'quantity' => 1,
                    'unit_of_measure' => 'Nos',
                    'purchase_date' => $put->copy()->subDays(12)->toDateString(),
                    'capitalization_date' => $put->toDateString(),
                    'invoice_number' => strtoupper($v).'/'.$put->format('y').'-'.($put->format('y') + 1).'/'.(100 + $i * 7),
                    'invoice_date' => $put->copy()->subDays(12)->toDateString(),
                    'purchase_order_number' => 'PO/PPPL/'.$put->format('Y').'/'.str_pad((string) (40 + $i * 3), 4, '0', STR_PAD_LEFT),
                    'original_cost' => $cost,
                    'residual_value' => $residual,
                    'useful_life_years' => $lifeYears,
                    'useful_life_months' => 0,
                    'wdv_rate' => $useWdv ? $cat->wdv_rate : null,
                    'opening_balance_date' => $openingDate,
                    'opening_accumulated_depreciation' => $opening,
                    'custodian' => $dep[$d]->head_name,
                    'warranty_expiry_date' => in_array($catCode, ['IMM', 'AUX', 'COMP', 'SRV', 'ELE']) ? $put->copy()->addYears($catCode === 'COMP' ? 3 : 2)->toDateString() : null,
                    'insurance_policy_number' => in_array($catCode, ['IMM', 'BLD', 'VEH', 'ELE']) ? 'NIA/FIRE/'.$put->format('Y').'/'.(70000 + $i) : null,
                    'insurance_expiry_date' => in_array($catCode, ['IMM', 'BLD', 'VEH', 'ELE']) ? '2027-03-31' : null,
                    'notes' => $statusCode === 'SCRAPPED' ? 'Beyond economic repair; awaiting disposal approval (Phase 2).' : null,
                ]);
                $asset->created_by = $manager->id;
                $asset->updated_by = $manager->id;
                if ($catCode === 'MLD' && $parentImm) {
                    $asset->parent_asset_id = $parentImm->id;
                }
                $asset->save();
                if ($catCode === 'IMM' && $model === 'Magna T 350') {
                    $parentImm = $asset;
                }
            }
        });

        return $company;
    }

    private function isolationTestCompany(string $pwd): void
    {
        $company = Company::create([
            'code' => 'ACME', 'name' => 'Acme Test Industries Ltd', 'city' => 'Ahmedabad', 'state' => 'Gujarat',
            'gstin' => '24AAACA1111A1Z5', 'fy_start_month' => 4,
        ]);
        app(CompanyProvisioner::class)->provision($company);
        $this->user($company->id, Role::CompanyAdmin, 'Acme Admin', 'admin@acme.test', $pwd, 'Admin');

        Tenant::run($company->id, function () {
            $slm = DepreciationMethod::where('code', 'SLM')->value('id');
            $cat = AssetCategory::create(['code' => 'ACMEPM', 'name' => 'ACME Secret Machinery', 'asset_code_prefix' => 'ACM', 'depreciation_method_id' => $slm, 'useful_life_years' => 15, 'residual_value_percent' => 5]);
            $loc = Location::create(['code' => 'ACMELOC', 'name' => 'ACME Ahmedabad Works', 'type' => 'plant', 'city' => 'Ahmedabad']);
            $status = AssetStatus::where('code', 'ACTIVE')->value('id');
            foreach (['ACME Secret Press 500T', 'ACME Secret Lathe'] as $n) {
                Asset::create([
                    'asset_code' => app(AssetCodeGenerator::class)->next($cat), 'name' => $n,
                    'asset_category_id' => $cat->id, 'location_id' => $loc->id, 'depreciation_method_id' => $slm,
                    'asset_status_id' => $status, 'capitalization_date' => '2025-05-01', 'purchase_date' => '2025-04-20',
                    'original_cost' => 2500000, 'residual_value' => 125000, 'useful_life_years' => 15,
                ]);
            }
        });
    }
}
