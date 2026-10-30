<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Writes an immutable row to audit_logs for create / update / delete / restore,
 * capturing old and new values, the user, IP, URL and timestamp.
 */
trait Auditable
{
    protected static array $auditExcludeDefaults = ['created_at', 'updated_at', 'password', 'remember_token'];

    public static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->writeAudit('created', [], $m->auditFilter($m->getAttributes())));

        static::updated(function ($m) {
            $changes = $m->auditFilter($m->getChanges());
            unset($changes['deleted_at']);
            if (empty($changes)) {
                return;
            }
            $old = array_intersect_key($m->auditFilter($m->getOriginal()), $changes);
            $m->writeAudit('updated', $old, $changes);
        });

        static::deleted(function ($m) {
            $event = method_exists($m, 'isForceDeleting') && ! $m->isForceDeleting() ? 'deactivated' : 'deleted';
            $m->writeAudit($event, $m->auditFilter($m->getAttributes()), []);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($m) => $m->writeAudit('restored', [], ['deleted_at' => null]));
        }
    }

    protected function auditFilter(array $values): array
    {
        $exclude = array_merge(static::$auditExcludeDefaults, property_exists($this, 'auditExclude') ? $this->auditExclude : []);

        return collect($values)->except($exclude)->map(function ($v) {
            return $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : ($v instanceof \BackedEnum ? $v->value : $v);
        })->all();
    }

    public function auditLabel(): string
    {
        foreach (['asset_code', 'code', 'run_number', 'wo_number', 'transfer_number', 'disposal_number', 'name', 'email', 'title'] as $attr) {
            if (! empty($this->attributes[$attr] ?? null)) {
                return (string) $this->attributes[$attr];
            }
        }

        return '#'.$this->getKey();
    }

    protected function writeAudit(string $event, array $old, array $new): void
    {
        $user = Auth::user();
        $companyId = $this->attributes['company_id'] ?? ($this instanceof \App\Models\Company ? $this->getKey() : null);

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'system',
            'event' => $event,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'auditable_label' => mb_substr($this->auditLabel(), 0, 250),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'url' => app()->runningInConsole() ? 'console' : mb_substr(Request::fullUrl(), 0, 2000),
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr((string) Request::userAgent(), 0, 500),
        ]);
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }
}
