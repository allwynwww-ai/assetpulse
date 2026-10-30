<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case CompanyAdmin = 'company_admin';
    case AssetManager = 'asset_manager';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::CompanyAdmin => 'Company Admin',
            self::AssetManager => 'Asset Manager',
            self::Viewer => 'Viewer / Auditor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Platform owner. Creates companies and assigns company admins.',
            self::CompanyAdmin => 'Full control of one company: masters, users, assets, depreciation.',
            self::AssetManager => 'Maintains the asset register, transfers, maintenance and disposals.',
            self::Viewer => 'Read-only access for auditors and management.',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::SuperAdmin => 'violet',
            self::CompanyAdmin => 'blue',
            self::AssetManager => 'emerald',
            self::Viewer => 'slate',
        };
    }

    /** Roles a company admin may assign inside a company. */
    public static function companyRoles(): array
    {
        return [self::CompanyAdmin, self::AssetManager, self::Viewer];
    }

    public static function options(bool $includeSuperAdmin = false): array
    {
        $roles = $includeSuperAdmin ? self::cases() : self::companyRoles();

        return collect($roles)->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
