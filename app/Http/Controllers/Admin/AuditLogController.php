<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Read-only, immutable audit trail of the current company. */
class AuditLogController extends Controller
{
    public const EVENTS = ['created', 'updated', 'deactivated', 'restored', 'deleted', 'login', 'logout', 'login_failed'];

    /** Super Admin outside any company sees the platform-wide trail. */
    protected function base()
    {
        $platform = auth()->user()->isSuperAdmin() && Tenant::id() === null;

        return $platform ? AuditLog::withoutGlobalScopes()->with('company') : AuditLog::query();
    }

    public function index(Request $request): View
    {
        $query = $this->base()->latest('created_at')->latest('id');

        if ($t = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('auditable_label', 'like', "%$t%")->orWhere('user_name', 'like', "%$t%")->orWhere('ip_address', 'like', "%$t%"));
        }
        if ($e = $request->query('event')) {
            $query->where('event', $e);
        }
        if ($m = $request->query('model')) {
            $query->where('auditable_type', 'App\\Models\\'.class_basename($m));
        }
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $models = $this->base()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type')
            ->mapWithKeys(fn ($t) => [class_basename($t) => \Illuminate\Support\Str::headline(class_basename($t))])->all();

        return view('admin.audit-logs.index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'events' => self::EVENTS,
            'models' => $models,
            'company' => Tenant::company(),
        ]);
    }

    public function show(int $id): View
    {
        return view('admin.audit-logs.show', ['log' => $this->base()->findOrFail($id)]);
    }
}
