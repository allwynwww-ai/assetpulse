<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends TenantModel
{
    public static function searchable(): array { return ['code', 'name', 'contact_person', 'gstin', 'city', 'email']; }

    public function assets(): HasMany { return $this->hasMany(Asset::class); }
    public function workOrders(): HasMany { return $this->hasMany(WorkOrder::class); }
    public function pmSchedules(): HasMany { return $this->hasMany(PmSchedule::class); }
}
