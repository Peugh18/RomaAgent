<?php

namespace Tests\Feature;

use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryZoneImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_roma_store_import_replaces_zones_and_includes_provincia(): void
    {
        $user = User::factory()->create();

        DeliveryZone::query()->create([
            'district' => 'Zona vieja',
            'cost_motorizado' => 99,
            'cost_shalom' => 99,
        ]);

        $response = $this->actingAs($user)->postJson('/api/delivery-zones/import-roma-store');

        $response->assertOk();
        $response->assertJsonPath('total', 42);

        $this->assertDatabaseMissing('delivery_zones', ['district' => 'Zona vieja']);
        $this->assertDatabaseHas('delivery_zones', [
            'district' => 'Provincia (Shalom)',
            'cost_motorizado' => 0,
            'cost_shalom' => 12,
        ]);
        $this->assertDatabaseHas('delivery_zones', [
            'district' => 'Barranco',
            'cost_motorizado' => 11,
            'cost_shalom' => 10,
        ]);
    }

    public function test_delivery_zones_index_returns_all_zones_for_ui(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/delivery-zones/import-roma-store');

        $response = $this->actingAs($user)->getJson('/api/delivery-zones');

        $response->assertOk();
        $response->assertJsonCount(42);

        $districts = collect($response->json())->pluck('district')->all();

        $this->assertContains('Provincia (Shalom)', $districts);
        $this->assertContains('Miraflores', $districts);
    }
}
