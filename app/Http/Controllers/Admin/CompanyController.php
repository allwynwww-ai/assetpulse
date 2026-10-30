<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Requests\Admin\CompanyRequest;
use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyProvisioner;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Super Admin: full CRUD of tenant companies + "enter" a company to work in it.
 * Company Admin: edit own company profile only (profile / updateProfile).
 */
class CompanyController extends MasterController
{
    protected string $model = Company::class;
    protected string $request = CompanyRequest::class;
    protected string $routeName = 'admin.companies';
    protected string $title = 'Companies';
    protected string $singular = 'Company';
    protected string $description = 'Tenant companies on the platform. Enter a company to manage its masters and assets.';
    protected string $icon = 'building';

    public const MONTHS = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
    public const BASIS = ['companies_act' => 'Companies Act 2013 (Schedule II)', 'income_tax' => 'Income Tax Act (Block of assets)', 'ind_as' => 'Ind AS 16'];

    protected function canWrite(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    protected function query(): Builder
    {
        return Company::query()->withCount([
            'users',
            'assets' => fn ($q) => $q->withoutGlobalScopes()->whereNull('assets.deleted_at'),
        ]);
    }

    protected function columns(): array
    {
        $active = Tenant::id();

        return [
            ['label' => 'Code', 'value' => fn ($r) => '<span class="font-mono text-xs font-semibold text-slate-700">'.e($r->code).'</span>'],
            ['label' => 'Company', 'value' => fn ($r) => self::strong($r->name, $r->city ? $r->city.($r->state ? ', '.$r->state : '') : null)],
            ['label' => 'GSTIN', 'value' => fn ($r) => '<span class="font-mono text-xs">'.self::muted($r->gstin).'</span>'],
            ['label' => 'Users', 'class' => 'text-right', 'value' => fn ($r) => $r->users_count],
            ['label' => 'Assets', 'class' => 'text-right', 'value' => fn ($r) => $r->assets_count],
            ['label' => 'Status', 'value' => fn ($r) => $r->is_active ? self::badge('Active', 'emerald') : self::badge('Suspended', 'amber')],
            ['label' => 'Workspace', 'value' => function ($r) use ($active) {
                if ($r->trashed() || ! $r->is_active) {
                    return self::muted(null);
                }
                if ($active === $r->id) {
                    return self::badge('Current', 'sky');
                }

                return '<form method="POST" action="'.e(route('admin.companies.enter', $r->id)).'">'.csrf_field()
                    .'<button class="btn-secondary btn-sm">Enter ›</button></form>';
            }],
        ];
    }

    protected function fields(Model $record): array
    {
        $isSuper = auth()->user()->isSuperAdmin();
        $fields = [
            ['type' => 'section', 'label' => 'Company'],
            ['name' => 'code', 'label' => 'Code', 'required' => true, 'disabled' => ! $isSuper, 'placeholder' => 'PPPL'],
            ['name' => 'name', 'label' => 'Display name', 'required' => true, 'span' => 2],
            ['name' => 'legal_name', 'label' => 'Legal name', 'span' => 2],
            ['name' => 'cin', 'label' => 'CIN'],
            ['name' => 'gstin', 'label' => 'GSTIN'],
            ['name' => 'pan', 'label' => 'PAN'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['name' => 'phone', 'label' => 'Phone'],
            ['type' => 'section', 'label' => 'Registered address'],
            ['name' => 'address_line1', 'label' => 'Address line 1', 'span' => 2],
            ['name' => 'address_line2', 'label' => 'Address line 2'],
            ['name' => 'city', 'label' => 'City'],
            ['name' => 'state', 'label' => 'State'],
            ['name' => 'pincode', 'label' => 'PIN code'],
            ['name' => 'country', 'label' => 'Country', 'required' => true, 'value' => old('country', $record->country ?? 'India')],
            ['type' => 'section', 'label' => 'Accounting settings'],
            ['name' => 'currency', 'label' => 'Currency', 'required' => true, 'value' => old('currency', $record->currency ?? 'INR'), 'maxlength' => 3],
            ['name' => 'fy_start_month', 'label' => 'Financial year starts', 'type' => 'select', 'options' => self::MONTHS, 'placeholder' => false, 'value' => old('fy_start_month', $record->fy_start_month ?? 4)],
            ['name' => 'depreciation_basis', 'label' => 'Primary depreciation book', 'type' => 'select', 'options' => self::BASIS, 'placeholder' => false],
            ['name' => 'asset_code_separator', 'label' => 'Asset code separator', 'value' => old('asset_code_separator', $record->asset_code_separator ?? '-'), 'maxlength' => 3],
            ['name' => 'asset_code_padding', 'label' => 'Asset code digits', 'type' => 'number', 'min' => 2, 'max' => 8, 'value' => old('asset_code_padding', $record->asset_code_padding ?? 4), 'help' => 'e.g. 4 → IMM-0001'],
        ];
        if ($isSuper) {
            $fields[] = ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'value' => old('is_active', $record->exists ? $record->is_active : true), 'help' => 'Suspended companies cannot log in.'];
        }
        if (! $record->exists) {
            $fields = array_merge($fields, [
                ['type' => 'section', 'label' => 'Company administrator', 'help' => 'The first user of this company. They can add more users.'],
                ['name' => 'admin_name', 'label' => 'Full name', 'required' => true],
                ['name' => 'admin_email', 'label' => 'Email (login)', 'type' => 'email', 'required' => true],
                ['name' => 'admin_password', 'label' => 'Password', 'type' => 'password', 'required' => true, 'help' => 'Minimum 8 characters.'],
            ]);
        }

        return $fields;
    }

    public function store(): RedirectResponse
    {
        $data = app($this->request)->validated();

        $company = DB::transaction(function () use ($data) {
            $company = Company::create(collect($data)->except(['admin_name', 'admin_email', 'admin_password'])->all());
            app(CompanyProvisioner::class)->provision($company);
            User::create([
                'company_id' => $company->id,
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
                'role' => Role::CompanyAdmin,
                'designation' => 'Company Administrator',
            ]);

            return $company;
        });

        return redirect()->route('admin.companies.index')
            ->with('success', "Company “{$company->name}” created with default methods, statuses, financial years and its company admin.");
    }

    public function update(int $id): RedirectResponse
    {
        $company = Company::withTrashed()->findOrFail($id);
        $company->fill(app($this->request)->validated())->save();

        return redirect()->route('admin.companies.index')->with('success', "Company “{$company->name}” updated.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $company->delete();
        if (Tenant::id() === $company->id) {
            session()->forget(Tenant::SESSION_KEY);
        }

        return back()->with('success', "Company “{$company->name}” deactivated. Its users can no longer sign in.");
    }

    public function enter(int $id): RedirectResponse
    {
        $company = Company::where('is_active', true)->findOrFail($id);
        session([Tenant::SESSION_KEY => $company->id]);

        return redirect()->route('dashboard')->with('success', "You are now working in {$company->name}.");
    }

    public function exit(Request $request): RedirectResponse
    {
        $request->session()->forget(Tenant::SESSION_KEY);

        return redirect()->route('admin.companies.index')->with('success', 'Left company workspace.');
    }

    // ---- Company Admin: own company profile -------------------------------
    public function profile(): View
    {
        $record = Company::findOrFail(Tenant::id());

        return view('admin.master.form', array_merge($this->viewData(), [
            'title' => 'Company Profile', 'routeName' => 'admin.company-profile', 'canWrite' => true,
            'record' => $record, 'fields' => $this->fields($record), 'mode' => 'edit',
            'action' => route('admin.company-profile.update'), 'backUrl' => route('dashboard'),
        ]));
    }

    public function updateProfile(): RedirectResponse
    {
        $company = Company::findOrFail(Tenant::id());
        $data = app($this->request)->validated();
        unset($data['code'], $data['is_active']);
        $company->fill($data)->save();

        return redirect()->route('admin.company-profile.edit')->with('success', 'Company profile updated.');
    }
}
