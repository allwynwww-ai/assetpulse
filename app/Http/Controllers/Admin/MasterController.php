<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Shared behaviour for all master-data screens: list + search + status filter
 * + pagination, create / edit / show (read-only), deactivate (soft delete)
 * and restore. Each concrete controller declares its model, Form Request,
 * table columns and form schema.
 */
abstract class MasterController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    /** @var class-string<\Illuminate\Foundation\Http\FormRequest> */
    protected string $request;

    protected string $routeName;      // e.g. admin.locations

    protected string $title;          // e.g. Locations / Plants

    protected string $singular;       // e.g. Location

    protected string $description = '';

    protected string $icon = 'cube';

    protected array $with = [];

    protected string $orderBy = 'code';

    protected int $perPage = 15;

    /** Table columns: [['label' => .., 'value' => fn($row) => html, 'class' => ..]] */
    abstract protected function columns(): array;

    /** Form schema for create / edit / show. */
    abstract protected function fields(Model $record): array;

    /** Optional dropdown filters: ['param' => ['label' => .., 'options' => [..], 'column' => ..]] */
    protected function filters(): array
    {
        return [];
    }

    protected function query(): Builder
    {
        return $this->model::query()->with($this->with);
    }

    /** Hook to massage validated data before save. */
    protected function prepare(array $data, ?Model $record): array
    {
        return $data;
    }

    /** Hook after save (e.g. keep a single "default" row). */
    protected function afterSave(Model $record, array $data, bool $created): void {}

    /** Return an error message to block deactivation, or null to allow. */
    protected function blockDeactivation(Model $record): ?string
    {
        return null;
    }

    protected function canWrite(): bool
    {
        return (bool) auth()->user()?->canManageMasters();
    }

    protected function viewData(): array
    {
        return [
            'title' => $this->title,
            'singular' => $this->singular,
            'routeName' => $this->routeName,
            'description' => $this->description,
            'icon' => $this->icon,
            'canWrite' => $this->canWrite(),
        ];
    }

    public function index(Request $request): View
    {
        $status = $request->query('status', 'active');
        $query = $this->query()->search($request->query('q'));

        match ($status) {
            'inactive' => $query->onlyTrashed(),
            'all' => $query->withTrashed(),
            default => null,
        };

        foreach ($this->filters() as $param => $filter) {
            if ($request->filled($param)) {
                $query->where($filter['column'] ?? $param, $request->query($param));
            }
        }

        $rows = $query->orderBy($this->orderBy)->paginate($this->perPage)->withQueryString();

        return view('admin.master.index', $this->viewData() + [
            'rows' => $rows,
            'columns' => $this->columns(),
            'filters' => $this->filters(),
            'status' => $status,
            'counts' => [
                'active' => $this->query()->count(),
                'inactive' => $this->query()->onlyTrashed()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $record = new $this->model;

        return view('admin.master.form', $this->viewData() + [
            'record' => $record,
            'fields' => $this->fields($record),
            'mode' => 'create',
        ]);
    }

    public function store(): RedirectResponse
    {
        $data = app($this->request)->validated();

        $record = DB::transaction(function () use ($data) {
            $record = new $this->model;
            $record->fill($this->prepare($data, null))->save();
            $this->afterSave($record, $data, true);

            return $record;
        });

        return redirect()->route($this->routeName.'.index')
            ->with('success', "{$this->singular} “{$record->auditLabel()}” created.");
    }

    public function show(int $id): View
    {
        $record = $this->query()->withTrashed()->findOrFail($id);

        return view('admin.master.form', $this->viewData() + [
            'record' => $record,
            'fields' => $this->fields($record),
            'mode' => 'show',
            'history' => AuditLog::where('auditable_type', $record::class)->where('auditable_id', $record->getKey())
                ->latest('created_at')->limit(15)->get(),
        ]);
    }

    public function edit(int $id): View
    {
        $record = $this->query()->withTrashed()->findOrFail($id);

        return view('admin.master.form', $this->viewData() + [
            'record' => $record,
            'fields' => $this->fields($record),
            'mode' => 'edit',
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $record = $this->query()->withTrashed()->findOrFail($id);
        $data = app($this->request)->validated();

        DB::transaction(function () use ($record, $data) {
            $record->fill($this->prepare($data, $record))->save();
            $this->afterSave($record, $data, false);
        });

        return redirect()->route($this->routeName.'.index')
            ->with('success', "{$this->singular} “{$record->auditLabel()}” updated.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $record = $this->query()->findOrFail($id);
        if ($msg = $this->blockDeactivation($record)) {
            return back()->with('error', $msg);
        }
        $record->delete();

        return back()->with('success', "{$this->singular} “{$record->auditLabel()}” deactivated. You can restore it from the Inactive tab.");
    }

    public function restore(int $id): RedirectResponse
    {
        $record = $this->query()->onlyTrashed()->findOrFail($id);
        $record->restore();

        return back()->with('success', "{$this->singular} “{$record->auditLabel()}” restored.");
    }

    // Small helpers for column closures ---------------------------------
    protected static function badge(string $text, string $color = 'slate'): string
    {
        return '<span class="badge bg-'.$color.'-50 text-'.$color.'-700 ring-'.$color.'-200">'.e($text).'</span>';
    }

    protected static function muted(?string $text): string
    {
        return $text === null || $text === '' ? '<span class="text-slate-300">—</span>' : e($text);
    }

    protected static function strong(?string $text, ?string $sub = null): string
    {
        return '<div class="font-medium text-slate-800">'.e($text).'</div>'.($sub ? '<div class="text-xs text-slate-400">'.e($sub).'</div>' : '');
    }
}
