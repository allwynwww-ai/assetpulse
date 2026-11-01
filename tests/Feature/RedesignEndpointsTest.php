<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Location;
use App\Models\AssetTransfer as Transfer;
use App\Models\User;
use App\Models\Scopes\CompanyScope;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Command-palette search, QR labels, register export and bulk transfer – tenant isolation and role checks. */
class RedesignEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $pp;
    private User $acme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        auth()->logout();
        $this->pp = User::where('email', 'admin@precisionpolymers.in')->firstOrFail();
        $this->acme = User::where('email', 'admin@acme.test')->firstOrFail();
    }

    private function assets(User $u)
    {
        return Asset::withoutGlobalScope(CompanyScope::class)->where('company_id', $u->company_id)->orderBy('asset_code');
    }

    public function test_palette_search_is_tenant_scoped(): void
    {
        $this->actingAs($this->pp)->getJson(route('assets.search', ['q' => 'Secret']))
            ->assertOk()->assertJsonCount(0, 'data');

        $own = $this->assets($this->pp)->first();
        $this->actingAs($this->pp)->getJson(route('assets.search', ['q' => $own->asset_code]))
            ->assertOk()->assertJsonPath('data.0.code', $own->asset_code);

        $res = $this->actingAs($this->acme)->getJson(route('assets.search', ['q' => 'Secret']))->assertOk();
        $this->assertCount(2, $res->json('data'));
        $this->assertStringNotContainsString($own->asset_code, $res->getContent());
    }

    public function test_palette_search_requires_login(): void
    {
        $this->getJson(route('assets.search', ['q' => 'a']))->assertUnauthorized();
    }

    public function test_qr_labels_only_render_own_assets(): void
    {
        $own = $this->assets($this->pp)->take(2)->get();
        $foreign = $this->assets($this->acme)->first();

        $this->actingAs($this->pp)->get(route('assets.labels', ['ids' => $own->pluck('id')->push($foreign->id)->all()]))
            ->assertOk()->assertSee($own[0]->asset_code)->assertSee($own[1]->asset_code)->assertDontSee($foreign->asset_code);

        $this->actingAs($this->pp)->get(route('assets.labels', ['ids' => [$foreign->id]]))->assertNotFound();
        $this->actingAs($this->pp)->get(route('assets.labels'))->assertNotFound();
    }

    public function test_register_export_honours_selected_ids(): void
    {
        [$a, $b] = $this->assets($this->pp)->take(2)->get()->all();
        $foreign = $this->assets($this->acme)->first();

        $this->actingAs($this->pp)->get(route('reports.show', ['report' => 'asset-register', 'ids' => $a->id.','.$foreign->id]))
            ->assertOk()->assertSee($a->asset_code)->assertDontSee($b->asset_code)->assertDontSee($foreign->asset_code);

        $this->actingAs($this->pp)->get(route('reports.show', ['report' => 'asset-register', 'ids' => [$a->id], 'export' => 'xlsx']))
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    private function liveAssets(User $u)
    {
        $active = AssetStatus::withoutGlobalScope(CompanyScope::class)->where('code', AssetStatus::ACTIVE)->pluck('id');

        return $this->assets($u)->whereIn('asset_status_id', $active);
    }

    public function test_bulk_transfer_moves_every_selected_asset(): void
    {
        $assets = $this->liveAssets($this->pp)->take(3)->get();
        $to = Location::withoutGlobalScope(CompanyScope::class)->where('company_id', $this->pp->company_id)
            ->whereNotIn('id', $assets->pluck('location_id'))->firstOrFail();
        $before = Transfer::withoutGlobalScope(CompanyScope::class)->count();

        $this->actingAs($this->pp)->post(route('transfers.store'), [
            'asset_ids' => $assets->pluck('id')->all(), 'transfer_date' => now()->toDateString(),
            'to_location_id' => $to->id, 'reason' => 'Bulk relocation test',
        ])->assertRedirect(route('transfers.index'))->assertSessionHasNoErrors();

        $this->assertSame($before + 3, Transfer::withoutGlobalScope(CompanyScope::class)->count());
        foreach ($assets as $a) {
            $this->assertSame($to->id, (int) $a->fresh()->location_id);
        }
    }

    public function test_bulk_transfer_is_all_or_nothing(): void
    {
        $ok = $this->liveAssets($this->pp)->first();
        $gone = AssetStatus::withoutGlobalScope(CompanyScope::class)->whereIn('code', [AssetStatus::DISPOSED, AssetStatus::SCRAPPED])->pluck('id');
        $disposed = $this->assets($this->pp)->whereIn('asset_status_id', $gone)->firstOrFail();
        $to = Location::withoutGlobalScope(CompanyScope::class)->where('company_id', $this->pp->company_id)
            ->whereNotIn('id', [$ok->location_id, $disposed->location_id])->firstOrFail();
        $before = Transfer::withoutGlobalScope(CompanyScope::class)->count();

        $this->actingAs($this->pp)->postJson(route('transfers.store'), [
            'asset_ids' => [$ok->id, $disposed->id], 'transfer_date' => now()->toDateString(),
            'to_location_id' => $to->id, 'reason' => 'Bulk relocation test',
        ])->assertStatus(422)->assertJsonValidationErrors('asset_ids');

        $this->assertSame($before, Transfer::withoutGlobalScope(CompanyScope::class)->count());
        $this->assertSame((int) $ok->location_id, (int) $ok->fresh()->location_id);
    }

    public function test_bulk_transfer_rejects_foreign_assets_and_viewers(): void
    {
        $own = $this->liveAssets($this->pp)->first();
        $foreign = $this->assets($this->acme)->first();
        $to = Location::withoutGlobalScope(CompanyScope::class)->where('company_id', $this->pp->company_id)
            ->where('id', '!=', $own->location_id)->firstOrFail();
        $payload = ['asset_ids' => [$own->id, $foreign->id], 'transfer_date' => now()->toDateString(), 'to_location_id' => $to->id, 'reason' => 'Bulk relocation test'];

        $this->actingAs($this->pp)->postJson(route('transfers.store'), $payload)->assertStatus(422);
        $this->assertSame((int) $foreign->location_id, (int) $foreign->fresh()->location_id);

        $viewer = User::where('email', 'auditor@precisionpolymers.in')->firstOrFail();
        $payload['asset_ids'] = [$own->id];
        $this->actingAs($viewer)->postJson(route('transfers.store'), $payload)->assertForbidden();
        $this->assertSame((int) $own->location_id, (int) $own->fresh()->location_id);
    }
}
