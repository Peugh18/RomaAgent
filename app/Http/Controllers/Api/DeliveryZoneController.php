<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\DeliveryZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeliveryZoneController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DeliveryZone::all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'district' => 'required|string|max:255',
            'cost_motorizado' => 'required|numeric|min:0',
            'cost_shalom' => 'required|numeric|min:0',
        ]);

        $zone = DeliveryZone::create($validated);
        $this->invalidarCachePrompt();

        return response()->json($zone, 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(DeliveryZone::findOrFail($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $zone = DeliveryZone::findOrFail($id);

        $validated = $request->validate([
            'district' => 'sometimes|string|max:255',
            'cost_motorizado' => 'sometimes|numeric|min:0',
            'cost_shalom' => 'sometimes|numeric|min:0',
        ]);

        $zone->update($validated);
        $this->invalidarCachePrompt();

        return response()->json($zone);
    }

    public function destroy(string $id): JsonResponse
    {
        DeliveryZone::findOrFail($id)->delete();
        $this->invalidarCachePrompt();

        return response()->json(null, 204);
    }

    public function importRomaStore(): JsonResponse
    {
        $seeder = new \Database\Seeders\RomaStoreDeliveryZonesSeeder;
        $seeder->setContainer(app());
        $seeder->run();

        return response()->json([
            'message' => 'Tarifario Roma Store importado correctamente.',
            'total' => DeliveryZone::query()->count(),
            'zones' => DeliveryZone::query()->orderBy('district')->get(),
        ]);
    }

    private function invalidarCachePrompt(): void
    {
        $settings = CompanySetting::query()->first();

        if ($settings !== null) {
            Cache::forget('contexto_prompt_completo_'.$settings->id);
        }
    }
}
