<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\Searchable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Users are NOT globally scoped (authentication must resolve them before a
 * tenant is known). Use User::forCurrentCompany() in tenant screens.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, Searchable, HasFactory, Notifiable, SoftDeletes;

    public static function searchable(): array
    {
        return ['name', 'email', 'designation', 'phone'];
    }

    protected $fillable = [
        'company_id', 'name', 'email', 'password', 'role', 'designation', 'phone',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCurrentCompany($query)
    {
        return $query->where('company_id', \App\Support\Tenant::id() ?? 0);
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $value = $role instanceof Role ? $role : Role::from($role);
            if ($this->role === $value) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool { return $this->role === Role::SuperAdmin; }
    public function isCompanyAdmin(): bool { return $this->role === Role::CompanyAdmin; }
    public function isViewer(): bool { return $this->role === Role::Viewer; }

    /** Can manage master data and users of the active company. */
    public function canManageMasters(): bool
    {
        return $this->hasRole(Role::SuperAdmin, Role::CompanyAdmin);
    }

    /** Can create / edit assets and asset transactions. */
    public function canManageAssets(): bool
    {
        return $this->hasRole(Role::SuperAdmin, Role::CompanyAdmin, Role::AssetManager);
    }

    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', $this->name))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }
}
