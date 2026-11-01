<?php

namespace Tests\Feature;

use App\Models\DepreciationRun;
use App\Models\PmSchedule;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Top-bar notification panel: real counts, correct plurals, links and empty state. */
class NotificationPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $pp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->pp = User::where('email', 'admin@precisionpolymers.in')->firstOrFail();
    }

    private function openBreakdowns()
    {
        return WorkOrder::withoutGlobalScope(CompanyScope::class)->where('company_id', $this->pp->company_id)
            ->where('type', 'breakdown')->whereIn('status', ['open', 'in_progress', 'on_hold']);
    }

    private function panel(): string
    {
        $html = $this->actingAs($this->pp)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('id="ap-notif-panel"', $html);
        $start = strpos($html, 'id="ap-notif-panel"');

        return substr($html, $start, strpos($html, '</section>', $start) - $start);
    }

    public function test_breakdown_row_is_pluralised_and_links_to_open_breakdowns(): void
    {
        $base = WorkOrder::withoutGlobalScope(CompanyScope::class)->where('company_id', $this->pp->company_id)->firstOrFail();
        foreach ([1, 2] as $i) {
            $wo = $base->replicate();
            $wo->forceFill(['wo_number' => $base->wo_number.'-T'.$i, 'type' => 'breakdown', 'status' => 'open'])->saveQuietly();
        }
        $ids = $this->openBreakdowns()->pluck('id');
        $panel = $this->panel();
        $this->assertStringContainsString($ids->count().' open breakdowns', $panel);
        $this->assertStringContainsString(e(route('work-orders.index', ['type' => 'breakdown', 'status' => 'open'])), $panel);

        WorkOrder::withoutGlobalScope(CompanyScope::class)->whereIn('id', $ids->slice(1))->update(['status' => 'completed']);
        $panel = $this->panel();
        $this->assertStringContainsString('1 open breakdown<', $panel);
        $this->assertStringNotContainsString('1 open breakdowns', $panel);
    }

    public function test_rows_have_counts_and_relevant_links(): void
    {
        $panel = $this->panel();
        $this->assertStringContainsString('Close notifications', $panel);
        $pmOverdue = PmSchedule::withoutGlobalScope(CompanyScope::class)->where('company_id', $this->pp->company_id)
            ->where('is_active', true)->whereNotNull('next_due_date')->where('next_due_date', '<', now()->toDateString())->count();
        if ($pmOverdue) {
            $this->assertStringContainsString(e(route('pm-schedules.index', ['view' => 'overdue'])), $panel);
        }
        $posted = DepreciationRun::withoutGlobalScope(CompanyScope::class)->where('company_id', $this->pp->company_id)
            ->where('status', 'posted')->where('run_type', 'periodic')->orderBy('period_end')->get();
        if ($posted->count() === 1) {
            $this->assertStringContainsString(route('depreciation.show', $posted->first()), $panel);
            $this->assertStringContainsString('Depreciation '.$posted->first()->period_label.' awaiting finalisation', $panel);
        }
        $this->assertGreaterThan(0, substr_count($panel, 'ap-notif-item'));
    }

    public function test_empty_state_when_nothing_needs_attention(): void
    {
        $cid = $this->pp->company_id;
        $this->openBreakdowns()->update(['status' => 'completed']);
        PmSchedule::withoutGlobalScope(CompanyScope::class)->where('company_id', $cid)->update(['is_active' => false]);
        DepreciationRun::withoutGlobalScope(CompanyScope::class)->where('company_id', $cid)->where('status', 'posted')->update(['status' => 'finalized']);

        // Put the clock inside the month after the latest run so "depreciation not run yet" doesn't fire either
        $last = DepreciationRun::withoutGlobalScope(CompanyScope::class)->where('company_id', $cid)->where('run_type', 'periodic')->max('period_end');
        $this->travelTo(\Illuminate\Support\Carbon::parse($last)->addDays(10));
        PmSchedule::withoutGlobalScope(CompanyScope::class)->where('company_id', $cid)->update(['is_active' => false]);

        $panel = $this->panel();
        $this->assertStringContainsString("You're all caught up", $panel);
        $this->assertStringNotContainsString('ap-notif-item', $panel);
    }
}
