<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

/**
 * Users & Roles. Inside a company: that company's users only.
 * Super Admin outside any company: all users on the platform.
 */
class UserController extends MasterController
{
    protected string $model = User::class;
    protected string $request = UserRequest::class;
    protected string $routeName = 'admin.users';
    protected string $title = 'Users & Roles';
    protected string $singular = 'User';
    protected string $description = 'People who can sign in, and what they are allowed to do.';
    protected string $icon = 'users';
    protected string $orderBy = 'name';
    protected array $with = ['company'];

    protected function platformMode(): bool
    {
        return auth()->user()->isSuperAdmin() && Tenant::id() === null;
    }

    protected function query(): Builder
    {
        $q = User::query()->with($this->with);

        return $this->platformMode() ? $q : $q->forCurrentCompany();
    }

    protected function filters(): array
    {
        $f = ['role' => ['label' => 'Role', 'options' => Role::options($this->platformMode())]];
        if ($this->platformMode()) {
            $f['company_id'] = ['label' => 'Company', 'options' => Company::orderBy('name')->pluck('name', 'id')->all()];
        }

        return $f;
    }

    protected function columns(): array
    {
        $cols = [
            ['label' => 'User', 'value' => fn ($r) => '<div class="flex items-center gap-3"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">'.e($r->initials).'</span>'
                .'<div><div class="font-medium text-slate-800">'.e($r->name).(auth()->id() === $r->id ? ' <span class="text-xs text-slate-400">(you)</span>' : '').'</div><div class="text-xs text-slate-400">'.e($r->email).'</div></div></div>'],
            ['label' => 'Role', 'value' => fn ($r) => self::badge($r->role->label(), $r->role->badgeColor())],
            ['label' => 'Designation', 'value' => fn ($r) => self::muted($r->designation)],
        ];
        if ($this->platformMode()) {
            $cols[] = ['label' => 'Company', 'value' => fn ($r) => self::muted($r->company?->name ?? ($r->isSuperAdmin() ? 'Platform' : null))];
        }
        $cols[] = ['label' => 'Last login', 'value' => fn ($r) => $r->last_login_at ? '<span class="text-xs">'.e($r->last_login_at->timezone(config('app.timezone'))->format('d M Y, h:i A')).'</span>' : self::muted('Never')];

        return $cols;
    }

    protected function fields(Model $record): array
    {
        $fields = [
            ['type' => 'section', 'label' => 'Profile'],
            ['name' => 'name', 'label' => 'Full name', 'required' => true],
            ['name' => 'email', 'label' => 'Email (login)', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'label' => 'Phone'],
            ['name' => 'designation', 'label' => 'Designation'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'required' => true, 'options' => Role::options($this->platformMode()),
                'help' => collect(Role::cases())->filter(fn ($r) => $r !== Role::SuperAdmin || $this->platformMode())->map(fn ($r) => $r->label().': '.$r->description())->implode(' · '), 'span' => 2],
        ];
        if ($this->platformMode()) {
            $fields[] = ['name' => 'company_id', 'label' => 'Company', 'type' => 'select', 'options' => Company::orderBy('name')->pluck('name', 'id')->all(), 'placeholder' => '— None (Super Admin only) —'];
        }
        $fields[] = ['type' => 'section', 'label' => $record->exists ? 'Change password' : 'Password', 'help' => $record->exists ? 'Leave blank to keep the current password.' : null];
        $fields[] = ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => ! $record->exists];
        $fields[] = ['name' => 'password_confirmation', 'label' => 'Confirm password', 'type' => 'password', 'required' => ! $record->exists];

        return $fields;
    }

    protected function prepare(array $data, ?Model $record): array
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (! $this->platformMode()) {
            $data['company_id'] = Tenant::id();
        } elseif (($data['role'] ?? null) === Role::SuperAdmin->value) {
            $data['company_id'] = null;
        }
        // Nobody can demote themselves out of admin access by accident.
        if ($record && $record->id === auth()->id()) {
            $data['role'] = $record->role->value;
        }

        return $data;
    }

    public function update(int $id): RedirectResponse
    {
        return parent::update($id);
    }

    protected function blockDeactivation(Model $record): ?string
    {
        if ($record->id === auth()->id()) {
            return 'You cannot deactivate your own account.';
        }
        if ($record->isSuperAdmin() && ! auth()->user()->isSuperAdmin()) {
            return 'Not allowed.';
        }

        return null;
    }
}
