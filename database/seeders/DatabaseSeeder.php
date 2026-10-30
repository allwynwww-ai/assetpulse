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
use App\Models\PmSchedule;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AssetCodeGenerator;
use App\Services\CompanyProvisioner;
use App\Services\DisposalService;
use App\Services\MaintenanceService;
use App\Services\TransferService;
use App\Services\Depreciation\DepreciationService;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $admin = $this->user($company->id, Role::CompanyAdmin, 'Rohan Kulkarni', 'admin@precisionpolymers.in', $pwd, 'Finance Controller');
        $manager = $this->user($company->id, Role::AssetManager, 'Sneha Patil', 'assets@precisionpolymers.in', $pwd, 'Asset & Maintenance Manager');
        $this->user($company->id, Role::Viewer, 'Amit Deshpande (CA)', 'auditor@precisionpolymers.in', $pwd, 'Statutory Auditor');

        Tenant::run($company->id, function () use ($manager) {
            $slm = DepreciationMethod::where('code', 'SLM')->value('id');
            $wdv = DepreciationMethod::where('code', 'WDV')->value('id');
            $uop = DepreciationMethod::where('code', 'UOP')->value('id');
            $calc = app(DepreciationService::class)->calculator();

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
                ['INS', 'Tools, Gauges & Measuring Instruments', 'INS', 15, 0, 'Part C, II – Plant & Machinery (general): 15 yrs; items ≤ ₹5,000 written off (low-value policy)', 0, '1204'],
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
                ['ATL', 'Atlas Copco (India) Ltd', 'Pune', 'Maharashtra', '27AAACA1534D1ZT', 'compressor.sales@in.atlascopco.com', 45],
                ['MIT', 'Mitutoyo South Asia Pvt Ltd', 'Pune', 'Maharashtra', '27AABCM3344E1Z1', 'pune@mitutoyo.co.in', 30],
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
                ['MLD', 'Mould – EV Connector Housing 8-cavity', null, null, 'PL3', 'TOOL', 'CC-1300', 'SMT', '2025-01-10', 3890000, 'ACTIVE', 'UOP'],
                ['MLD', 'Mould – Cap Closure 48-cavity (stack)', null, null, 'PL2', 'PRD2', 'CC-1200', 'SMT', '2025-11-03', 5640000, 'ACTIVE', 'WDV'],
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
                ['VEH', 'Tata 407 Gold (MH-14-EV-1187)', 'Tata Motors', '407 Gold SFC', 'PL1', 'STR', 'CC-3100', 'TAT', '2015-07-07', 945000],
                ['FUR', 'Workstations & Cabins – Head Office', 'Godrej Interio', 'Modular', 'HO', 'ADM', 'CC-9200', 'GEN', '2019-01-28', 1385000],
                ['FUR', 'Conference Room Furniture', 'Godrej Interio', 'Exe-Series', 'HO', 'ADM', 'CC-9200', 'GEN', '2024-06-03', 412000],
                ['FUR', 'Tool Room Racks & Mould Storage', 'Godrej', 'Heavy Duty', 'PL3', 'TOOL', 'CC-1300', 'GEN', '2022-08-29', 528000],
                ['AUX', 'Air Compressor – Atlas Copco GA37 VSD+', 'Atlas Copco', 'GA37 VSD+', 'PL1', 'MNT', 'CC-2100', 'ATL', '2025-04-07', 2185000],
                ['INS', 'Digital Vernier Caliper 0–300 mm', 'Mitutoyo', '500-193-30', 'PL1', 'QA', 'CC-2200', 'MIT', '2025-08-18', 4800],
                ['AUX', 'Servo Sprue Picker Robot (IMM 220T cell)', 'Yushin', 'YCII-150', 'PL1', 'PRD', 'CC-1100', 'HTN', '2026-05-14', 685000],
                ['COMP', 'Laptop – Latitude 5450 (HR)', 'Dell', 'Latitude 5450', 'HO', 'ADM', 'CC-9200', 'DEL', '2026-07-08', 92500],
                ['MLD', 'Mould – Tail Lamp Housing 2-cavity (hot runner)', null, null, 'PL1', 'PRD', 'CC-1100', 'SMT', '2026-09-10', 4680000],
                ['AUX', 'Air-cooled Chiller 25TR (Plant 2)', 'Blue Star', 'BSAC-25', 'PL2', 'MNT', 'CC-2100', 'BLU', '2026-10-12', 975000],
            ];

            $parentImm = null;
            foreach ($rows as $i => $r) {
                [$catCode, $name, $make, $model, $l, $d, $c, $v, $date, $cost] = $r;
                $statusCode = $r[10] ?? 'ACTIVE';
                $methodCode = $r[11] ?? 'SLM';
                $useWdv = $methodCode === 'WDV';
                $cat = $cats[$catCode];
                $put = Carbon::parse($date);
                $residual = round($cost * 0.05, 2);
                $lifeYears = $cat->useful_life_years;

                // Takeover balances for assets put to use before 1-Apr-2024 (SLM, day-based)
                // computed with the engine itself so the brought-forward balance matches its maths exactly
                $opening = 0;
                $openingDate = null;
                if ($put->lt(Carbon::parse(self::OPENING_DATE))) {
                    $res = $calc->calculate([
                        'method' => 'SLM', 'cost' => $cost, 'residual' => $residual, 'life_months' => $lifeYears * 12,
                        'cap_date' => $put->toDateString(), 'acc_before' => 0, 'acc_at_fy_start' => 0, 'depreciated_through' => null,
                    ], $put->toDateString(), self::OPENING_DATE);
                    $opening = $res->amount;
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
                    'depreciation_method_id' => match ($methodCode) { 'WDV' => $wdv, 'UOP' => $uop, default => $slm },
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
                    'total_units_capacity' => $methodCode === 'UOP' ? 1500000 : null,
                    'units_consumed' => 0,
                    'notes' => $methodCode === 'UOP' ? 'Depreciated on units of production: lifetime 15,00,000 shots (mould maker guarantee). Shot counter read at each month end.' : null,
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

        $this->ppplHistory($company, $admin, $manager);
        $this->ppplMaintenance($company, $manager);

        return $company;
    }

    /**
     * Replays 30 months of activity through the real services, in date order:
     * transfers and disposals inside each month, then that month's depreciation
     * run. Apr-2024 … Aug-2026 are finalised (and therefore locked); Sep-2026 is
     * left posted so the "awaiting finalisation" flow can be demonstrated.
     */
    private function ppplHistory(Company $company, User $admin, User $manager): void
    {
        Tenant::run($company->id, function () use ($admin, $manager) {
            $dep = app(DepreciationService::class);
            $trf = app(TransferService::class);
            $dsp = app(DisposalService::class);
            $asset = fn (string $like) => Asset::where('name', 'like', "%{$like}%")->firstOrFail();
            $loc = fn (string $c) => Location::where('code', $c)->value('id');
            $dept = fn (string $c) => Department::where('code', $c)->value('id');
            $cc = fn (string $c) => CostCenter::where('code', $c)->value('id');
            $move = function (string $date, string $like, array $to, string $reason, bool $affects = false, ?string $remarks = null) use ($trf, $asset, $loc, $dept, $cc) {
                $trf->transfer($asset($like), [
                    'transfer_date' => $date,
                    'to_location_id' => isset($to[0]) ? $loc($to[0]) : null,
                    'to_department_id' => isset($to[1]) ? $dept($to[1]) : null,
                    'to_cost_center_id' => isset($to[2]) ? $cc($to[2]) : null,
                    'to_custodian' => $to[3] ?? null,
                    'reason' => $reason, 'affects_depreciation' => $affects, 'remarks' => $remarks,
                ]);
            };

            $events = [
                ['2024-11-05', fn () => $move('2024-11-05', 'Machine 160T', ['PL1', 'PRD', 'CC-1100', 'Vikram Jadhav'], 'Capacity balancing – tooling for Chakan clip programme moved to Plant 1', true, 'Machine shifted on low-bed trailer; re-levelled and commissioned on 07-Nov-2024.')],
                ['2024-12-16', fn () => $dsp->dispose($asset('Tata 407'), [
                    'disposal_date' => '2024-12-16', 'disposal_type' => 'scrap', 'buyer_name' => 'Om Sai Scrap Traders, Bhosari',
                    'sale_invoice_number' => 'PPPL/SCR/2425/0003', 'sale_value' => 85000, 'disposal_cost' => 4500,
                    'reason' => 'Vehicle beyond economic repair (chassis corrosion, failed fitness test). RTO registration cancelled; sold as scrap against Board approval BR/2024/11.',
                ])],
                ['2025-03-18', fn () => $move('2025-03-18', 'Hopper Dryer', ['PL2', 'PRD2', 'CC-1200', 'Prakash More'], 'Dryers re-deployed to Ranjangaon for the 650T washer-tub cell')],
                ['2025-07-21', fn () => $move('2025-07-21', 'Latitude 5440', ['HO', 'IT', 'CC-9100', 'Kiran Bhosale'], 'Re-assigned to IT for the ERP upgrade project', true)],
                ['2025-10-14', fn () => $move('2025-10-14', 'Door Trim Clip', ['PL3', 'TOOL', 'CC-1300', 'Nitin Gaikwad'], 'Sent to tool room for cavity refurbishment and texture re-work', true)],
                ['2025-12-02', fn () => $move('2025-12-02', 'Door Trim Clip', ['PL1', 'PRD', 'CC-1100', 'Vikram Jadhav'], 'Refurbishment complete – returned to production after PPAP trial', true)],
                ['2026-02-10', fn () => $dsp->dispose($asset('Intra V30'), [
                    'disposal_date' => '2026-02-10', 'disposal_type' => 'sale', 'buyer_name' => 'Shree Ganesh Motors, Pune',
                    'buyer_gstin' => '27AAKFS1234L1Z9', 'sale_invoice_number' => 'PPPL/SV/2526/0007', 'sale_value' => 520000, 'disposal_cost' => 3500,
                    'reason' => 'Replaced by third-party logistics contract for inter-plant movement; vehicle sold to dealer (best of three quotes).',
                ])],
                ['2026-09-12', fn () => $move('2026-09-12', 'Sprue Picker Robots (4 nos)', ['PL1', 'PRD', 'CC-1100', 'Vikram Jadhav'], 'Robots moved to Chakan for the tail-lamp programme automation', true)],
                ['2026-10-06', fn () => $move('2026-10-06', 'Nexon EV', ['PL1', 'PRD', 'CC-1100', 'Vikram Jadhav'], 'Staff car re-allocated to Plant 1 head (Chakan)')],
            ];
            usort($events, fn ($x, $y) => strcmp($x[0], $y[0]));

            $mould = $asset('EV Connector');
            $month = CarbonImmutable::parse('2024-04-01');
            $i = 0;
            while ($month->lte(CarbonImmutable::parse('2026-09-01'))) {
                $end = $month->endOfMonth()->startOfDay();
                Auth::setUser($manager);
                while ($i < count($events) && $events[$i][0] <= $end->toDateString()) {
                    ($events[$i][1])();
                    $i++;
                }
                // Month-end shot counter reading for the units-of-production mould
                if ($mould->capitalization_date->lte($end)) {
                    $shots = $mould->capitalization_date->isSameMonth($end) ? 26000 : 38000 + (($month->month * 7919 + $month->year) % 9000);
                    Asset::whereKey($mould->id)->update(['units_consumed' => DB::raw('units_consumed + '.$shots)]);
                }
                $run = $dep->post($month, $end, null, 'Monthly depreciation – '.$month->format('F Y'));
                if ($end->toDateString() <= '2026-08-31') {
                    Auth::setUser($admin);
                    $dep->finalize($run);
                }
                $month = $month->addMonth();
            }
            Auth::setUser($manager);
            for (; $i < count($events); $i++) {
                ($events[$i][1])();
            }
            // October shots to date (not yet depreciated – next run)
            Asset::whereKey($mould->id)->update(['units_consumed' => DB::raw('units_consumed + 31250')]);
            Auth::forgetUser();
        });
    }

    /** PM schedules with completion history, breakdowns and a few open work orders. */
    private function ppplMaintenance(Company $company, User $manager): void
    {
        Tenant::run($company->id, function () use ($manager) {
            Auth::setUser($manager);
            mt_srand(20240401);
            $svc = app(MaintenanceService::class);
            $asset = fn (string $like) => Asset::where('name', 'like', "%{$like}%")->firstOrFail();
            $ven = fn (string $c) => Vendor::where('code', $c)->value('id');
            $today = CarbonImmutable::now()->startOfDay();

            // [asset, code, title, unit, interval, start, daily avg, start reading, est cost, est hrs, assigned, vendor, stop completions after, checklist]
            $defs = [
                ['Machine 350T', 'PM-IMM-01', 'Monthly PM – hydraulics, lubrication & heater bands', 'months', 1, '2025-10-05', 0, 0, 8500, 4, 'Suresh Shinde', null, null, "Check hydraulic oil level & temperature\nGrease toggle & tie-bar bushes\nMeasure heater band currents\nClean oil cooler strainer"],
                ['Machine 450T', 'PM-IMM-02', 'Monthly PM – hydraulics, lubrication & heater bands', 'months', 1, '2025-10-08', 0, 0, 11000, 5, 'Suresh Shinde', 'ENG', null, "Check hydraulic oil level & temperature\nGrease toggle & tie-bar bushes\nMeasure heater band currents\nCheck screw & barrel wear (quarterly)"],
                ['Machine 650T', 'PM-IMM-03', 'Monthly PM – hydraulics, lubrication & heater bands', 'months', 1, '2025-10-12', 0, 0, 14000, 6, 'Rahul Kadam', 'HTN', '2026-08-31', "Check hydraulic oil level & temperature\nGrease toggle & tie-bar bushes\nMeasure heater band currents"],
                ['Machine 220T (Two-shot)', 'PM-IMM-04', '2,000-hour service – oil filter, accumulator & rotary table', 'running_hours', 2000, '2025-07-01', 19, 1200, 32000, 8, 'Suresh Shinde', 'ENG', null, "Replace oil filter elements\nCheck accumulator pre-charge\nInspect rotary table bearings"],
                ['Machine 280T', 'PM-IMM-05', '2,000-hour service – oil filter & screw inspection', 'running_hours', 2000, '2025-04-01', 20, 14500, 24000, 6, 'Rahul Kadam', 'HTN', '2026-06-30', "Replace oil filter elements\nScrew & barrel wear check\nNozzle and check-ring inspection"],
                ['Bumper Bracket', 'PM-MLD-01', 'Mould PM every 1.5 lakh shots – clean, polish, ejector check', 'shots', 150000, '2026-03-01', 5200, 2860000, 9500, 6, 'Nitin Gaikwad', null, null, "Clean vents & parting line\nCheck ejector pins & return springs\nPolish cavity, check cooling flow"],
                ['EV Connector', 'PM-MLD-02', 'Mould PM every 2.5 lakh shots – clean, polish, ejector check', 'shots', 250000, '2025-01-10', 1400, 0, 12500, 8, 'Nitin Gaikwad', 'SMT', null, "Clean vents & parting line\nCheck ejector pins & sleeves\nInspect core pins for wear"],
                ['Screw Chiller', 'PM-AUX-01', 'Quarterly chiller service – condenser, refrigerant, controls', 'months', 3, '2025-01-15', 0, 0, 18500, 5, 'Blue Star AMC', 'BLU', null, "Clean condenser coils\nCheck refrigerant pressures\nInspect compressor oil & contactors"],
                ['GA37', 'PM-AUX-02', '2,000-hour compressor service – oil, air & separator filters', 'running_hours', 2000, '2025-04-07', 21, 0, 27500, 4, 'Atlas Copco Service', 'ATL', null, "Change oil, oil filter & air filter\nCheck separator element ΔP\nDrain & test auto-drain valve"],
                ['DG Set', 'PM-ELE-01', 'DG set 250-hour service – oil, filters, battery', 'running_hours', 250, '2025-01-05', 2.4, 3120, 9800, 3, 'Suresh Shinde', null, null, "Change engine oil & filters\nCheck coolant & battery\nLoad test 30 minutes"],
                ['HT Substation', 'PM-ELE-02', 'Annual HT substation maintenance & statutory testing', 'years', 1, '2024-11-18', 0, 0, 65000, 10, 'Schneider Electric', 'SCH', null, "Transformer oil BDV test\nRelay testing & calibration\nEarth-pit resistance test"],
                ['CMM', 'PM-QA-01', 'Half-yearly CMM calibration (NABL)', 'months', 6, '2024-10-25', 0, 0, 42000, 6, 'Meera Joshi', null, '2026-09-30', "Probe qualification\nVolumetric accuracy check (ISO 10360)\nIssue calibration certificate"],
                ['Ultra T.7', 'PM-VEH-01', 'Quarterly vehicle service & PUC', 'months', 3, '2024-10-01', 0, 0, 12500, 4, 'Sagar Pawar', 'TAT', null, "Engine oil & filters\nBrake inspection\nPUC certificate renewal check"],
                ['Nexon EV', 'PM-VEH-02', 'Quarterly EV service – battery health & brakes', 'months', 3, '2025-03-12', 0, 0, 4500, 2, 'Admin – Fleet', 'TAT', null, "Battery health report\nBrake pads & tyre rotation\nSoftware update"],
            ];

            $pms = [];
            foreach ($defs as $d) {
                [$like, $code, $title, $unit, $interval, $start, $avg, $reading, $estCost, $estHrs, $assigned, $vendor, $stop, $checklist] = $d;
                $a = $asset($like);
                $pm = new PmSchedule([
                    'asset_id' => $a->id, 'vendor_id' => $vendor ? $ven($vendor) : null, 'code' => $code, 'title' => $title,
                    'description' => 'Preventive maintenance as per OEM manual.', 'frequency_unit' => $unit, 'frequency_interval' => $interval,
                    'start_date' => $start, 'meter_last_service_reading' => $reading, 'meter_current_reading' => $reading,
                    'meter_daily_average' => $avg ?: null, 'alert_days_before' => in_array($unit, ['years'], true) ? 30 : 7,
                    'estimated_cost' => $estCost, 'estimated_hours' => $estHrs, 'assigned_to' => $assigned,
                    'checklist' => $checklist, 'is_active' => true,
                ]);
                $pm->next_due_date = $avg > 0
                    ? CarbonImmutable::parse($start)->addDays((int) floor($interval / $avg))->toDateString()
                    : $pm->computeNextDue(Carbon::parse($start))->toDateString();
                $pm->save();
                $pms[] = ['pm' => $pm, 'stop' => $stop, 'avg' => (float) $avg];
            }

            // Unplanned work (date order is preserved through the queue below)
            $adhoc = [
                ['2025-02-21', 'Machine 180T', 'corrective', 'medium', 'Barrel zone-3 heater band replaced', 'Zone 3 temperature not reaching set point.', 'Heater band open circuit', 'Replaced 2 heater bands and thermocouple', 2400, 6800, 0, 5, null, '2025-02-21 16:30'],
                ['2025-08-11', 'Machine 450T', 'breakdown', 'critical', 'Main hydraulic pump failure – machine down', 'Loud noise from pump; pressure not building.', 'Hydraulic pump shaft seal failure; contaminated oil', 'Pump overhauled by Engel service; oil flushed and replaced; filters changed', 12000, 185000, 25000, 36, 'ENG', '2025-08-12 20:00'],
                ['2025-12-04', 'Wire-cut EDM', 'corrective', 'low', 'Wire feed roller replaced', 'Wire breakage frequent.', 'Worn feed roller', 'Roller and guide replaced', 1500, 9200, 0, 3, null, '2025-12-04 15:00'],
                ['2026-01-19', 'Machine 180T', 'breakdown', 'high', 'Mould clamp proximity switch failure', 'Machine not completing clamp cycle.', 'Proximity sensor water ingress', 'Sensor replaced, cable gland sealed', 1800, 4200, 0, 6, null, '2026-01-19 17:00'],
                ['2026-05-06', 'Screw Chiller', 'breakdown', 'critical', 'Chiller trip – compressor contactor burnt', 'Chiller tripped on overload; mould temperatures rising.', 'Compressor contactor burnt due to loose terminal', 'Contactor & overload relay replaced; terminals re-torqued', 6000, 38500, 9000, 14, 'BLU', '2026-05-07 01:00'],
                ['2026-09-24', 'Solar PV', 'inspection', 'low', 'Post-monsoon solar plant inspection', 'Inspect strings, inverters and earthing after monsoon.', null, 'IR scan done; 2 MC4 connectors replaced', 3500, 1800, 0, 0, null, '2026-09-25 13:00'],
            ];
            $open = [
                ['2026-10-22', 'EV Connector', 'breakdown', 'critical', 'Ejector pin breakage – flash on cavities 5 & 6', 'Two ejector pins broken; flash on cavities 5 and 6. Mould pulled from machine.', 'in_progress', 'SMT'],
                ['2026-10-28', 'DG Set', 'corrective', 'medium', 'Radiator seepage at bottom hose', 'Coolant seepage observed during weekly test run.', 'open', null],
                ['2026-10-26', 'Hopper Dryer', 'corrective', 'low', 'Loader No. 4 vacuum switch faulty', 'Loader not cutting off at full level.', 'on_hold', null],
            ];

            $queue = [];
            foreach ($adhoc as $x) {
                $queue[] = [$x[0], 'adhoc', $x];
            }
            $cutoff = $today->subDays(2);
            while (true) {
                // earliest due PM that should already have been done
                $best = null;
                foreach ($pms as $k => $p) {
                    $pm = $p['pm'];
                    if (! $pm->next_due_date || $pm->next_due_date->gt($cutoff) || ($p['stop'] && $pm->next_due_date->toDateString() > $p['stop'])) {
                        continue;
                    }
                    if ($best === null || $pm->next_due_date->lt($pms[$best]['pm']->next_due_date)) {
                        $best = $k;
                    }
                }
                usort($queue, fn ($x, $y) => strcmp($x[0], $y[0]));
                $nextAdhoc = $queue[0] ?? null;
                if ($best === null && ! $nextAdhoc) {
                    break;
                }
                if ($nextAdhoc && ($best === null || $nextAdhoc[0] <= $pms[$best]['pm']->next_due_date->toDateString())) {
                    array_shift($queue);
                    [$date, $like, $type, $prio, $title, $desc, $cause, $action, $lab, $parts, $ext, $down, $vendor, $done] = $nextAdhoc[2];
                    $wo = $svc->createWorkOrder($asset($like), [
                        'type' => $type, 'priority' => $prio, 'title' => $title, 'description' => $desc, 'reported_date' => $date,
                        'scheduled_date' => $date, 'assigned_to' => $vendor ? null : 'Suresh Shinde', 'vendor_id' => $vendor ? $ven($vendor) : null,
                    ]);
                    $svc->start($wo, $date.' 09:30');
                    $svc->complete($wo->fresh(), ['completed_at' => $done, 'labour_cost' => $lab, 'parts_cost' => $parts, 'external_cost' => $ext,
                        'downtime_hours' => $down, 'root_cause' => $cause, 'action_taken' => $action]);

                    continue;
                }
                $p = $pms[$best];
                $pm = $p['pm'];
                $due = CarbonImmutable::parse($pm->next_due_date);
                $doneAt = $due->addDays(mt_rand(0, 2))->setTime(mt_rand(10, 17), 0);
                if ($doneAt->gt($cutoff->endOfDay())) {
                    $doneAt = $due->setTime(15, 0);
                }
                $wo = $svc->createWorkOrder($pm->asset, [
                    'pm_schedule_id' => $pm->id, 'type' => 'preventive', 'priority' => 'medium', 'title' => $pm->title,
                    'description' => $pm->checklist, 'reported_date' => $due->toDateString(), 'scheduled_date' => $due->toDateString(),
                    'assigned_to' => $pm->assigned_to, 'vendor_id' => $pm->vendor_id,
                ]);
                $data = ['completed_at' => $doneAt->toDateTimeString(), 'labour_cost' => round($pm->estimated_cost * mt_rand(25, 40) / 100, -1),
                    'parts_cost' => round($pm->estimated_cost * mt_rand(45, 80) / 100, -1), 'external_cost' => $pm->vendor_id ? round($pm->estimated_cost * 0.2, -1) : 0,
                    'downtime_hours' => $pm->estimated_hours, 'action_taken' => 'Checklist completed; no abnormality found.'];
                if ($p['avg'] > 0) {
                    $days = $pm->last_done_date ? $pm->last_done_date->diffInDays($doneAt) : CarbonImmutable::parse($pm->start_date)->diffInDays($doneAt);
                    $data['meter_reading'] = round((float) $pm->meter_last_service_reading + max($pm->frequency_interval, $p['avg'] * $days), 0);
                }
                $svc->complete($wo->fresh(), $data);
                $pm->refresh();
            }

            // Bring meter readings up to today and forecast the next due date
            foreach ($pms as $p) {
                $pm = $p['pm']->fresh();
                if ($p['avg'] > 0) {
                    $since = ($pm->last_done_date ?? $pm->start_date)->diffInDays($today);
                    $pm->meter_current_reading = round((float) $pm->meter_last_service_reading + $p['avg'] * $since, 0);
                    $svc->refreshNextDue($pm);
                    $pm->save();
                }
            }

            foreach ($open as [$date, $like, $type, $prio, $title, $desc, $status, $vendor]) {
                $wo = $svc->createWorkOrder($asset($like), [
                    'type' => $type, 'priority' => $prio, 'title' => $title, 'description' => $desc, 'reported_date' => $date,
                    'scheduled_date' => $date, 'assigned_to' => $vendor ? 'Sai Mould Tech (tool maker)' : 'Suresh Shinde', 'vendor_id' => $vendor ? $ven($vendor) : null,
                ]);
                if ($status !== 'open') {
                    $svc->start($wo, $date.' 11:00');
                    if ($status === 'on_hold') {
                        $wo->update(['status' => 'on_hold', 'action_taken' => 'Awaiting vacuum switch from OEM (PO raised).']);
                    }
                }
            }
            Auth::forgetUser();
        });
    }

    private function isolationTestCompany(string $pwd): void
    {
        $company = Company::create([
            'code' => 'ACME', 'name' => 'Acme Test Industries Ltd', 'city' => 'Ahmedabad', 'state' => 'Gujarat',
            'gstin' => '24AAACA1111A1Z5', 'fy_start_month' => 4,
        ]);
        app(CompanyProvisioner::class)->provision($company);
        $admin = $this->user($company->id, Role::CompanyAdmin, 'Acme Admin', 'admin@acme.test', $pwd, 'Admin');

        // A little of everything, so tenant-isolation checks have data to (not) leak
        Tenant::run($company->id, function () use ($admin) {
            Auth::setUser($admin);
            $slm = DepreciationMethod::where('code', 'SLM')->value('id');
            $cat = AssetCategory::create(['code' => 'ACMEPM', 'name' => 'ACME Secret Machinery', 'asset_code_prefix' => 'ACM', 'depreciation_method_id' => $slm, 'useful_life_years' => 15, 'residual_value_percent' => 5]);
            $loc = Location::create(['code' => 'ACMELOC', 'name' => 'ACME Ahmedabad Works', 'type' => 'plant', 'city' => 'Ahmedabad']);
            $loc2 = Location::create(['code' => 'ACMELOC2', 'name' => 'ACME Secret Annexe', 'type' => 'warehouse', 'city' => 'Sanand']);
            $status = AssetStatus::where('code', 'ACTIVE')->value('id');
            $assets = [];
            foreach (['ACME Secret Press 500T', 'ACME Secret Lathe'] as $n) {
                $assets[] = Asset::create([
                    'asset_code' => app(AssetCodeGenerator::class)->next($cat), 'name' => $n,
                    'asset_category_id' => $cat->id, 'location_id' => $loc->id, 'depreciation_method_id' => $slm,
                    'asset_status_id' => $status, 'capitalization_date' => '2026-08-01', 'purchase_date' => '2026-07-20',
                    'original_cost' => 2500000, 'residual_value' => 125000, 'useful_life_years' => 15,
                ]);
            }
            $dep = app(DepreciationService::class);
            $dep->finalize($dep->post('2026-08-01', '2026-08-31', null, 'ACME secret run'));
            $dep->post('2026-09-01', '2026-09-30', null, 'ACME secret run');
            app(TransferService::class)->transfer($assets[0], ['transfer_date' => '2026-10-05', 'to_location_id' => $loc2->id, 'reason' => 'ACME secret transfer']);
            $svc = app(MaintenanceService::class);
            PmSchedule::create(['asset_id' => $assets[0]->id, 'code' => 'ACME-PM', 'title' => 'ACME secret PM', 'frequency_unit' => 'months', 'frequency_interval' => 1,
                'start_date' => '2026-08-01', 'next_due_date' => '2026-09-01', 'is_active' => true]);
            $svc->createWorkOrder($assets[0], ['type' => 'breakdown', 'priority' => 'high', 'title' => 'ACME secret breakdown', 'reported_date' => '2026-10-20']);
            app(DisposalService::class)->dispose($assets[1], ['disposal_date' => '2026-10-15', 'disposal_type' => 'sale', 'buyer_name' => 'ACME Secret Buyer',
                'sale_value' => 2400000, 'reason' => 'ACME secret disposal']);
            Auth::forgetUser();
        });
    }
}
