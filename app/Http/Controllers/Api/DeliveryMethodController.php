<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\DeliveryMethodField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryMethodController extends Controller
{
    public function index()
    {
        return response()->json(DeliveryMethod::with('fields')->orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:delivery_methods',
            'is_active' => 'boolean',
            'fields' => 'array',
            'fields.*.name' => 'required|string|max:255',
            'fields.*.is_required' => 'boolean',
        ]);

        $method = DB::transaction(function () use ($validated) {
            $m = DeliveryMethod::create([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => DeliveryMethod::max('sort_order') + 1,
            ]);

            if (isset($validated['fields'])) {
                foreach ($validated['fields'] as $index => $field) {
                    $m->fields()->create([
                        'name' => $field['name'],
                        'is_required' => $field['is_required'] ?? true,
                        'sort_order' => $index,
                    ]);
                }
            }

            return $m->load('fields');
        });

        return response()->json($method, 201);
    }

    public function update(Request $request, DeliveryMethod $deliveryMethod)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:delivery_methods,name,' . $deliveryMethod->id,
            'is_active' => 'boolean',
            'fields' => 'array',
            'fields.*.id' => 'nullable|exists:delivery_method_fields,id',
            'fields.*.name' => 'required|string|max:255',
            'fields.*.is_required' => 'boolean',
        ]);

        DB::transaction(function () use ($validated, $deliveryMethod) {
            $deliveryMethod->update([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? $deliveryMethod->is_active,
            ]);

            if (isset($validated['fields'])) {
                $keepIds = collect($validated['fields'])->pluck('id')->filter()->toArray();
                $deliveryMethod->fields()->whereNotIn('id', $keepIds)->delete();

                foreach ($validated['fields'] as $index => $field) {
                    $deliveryMethod->fields()->updateOrCreate(
                        ['id' => $field['id'] ?? null],
                        [
                            'name' => $field['name'],
                            'is_required' => $field['is_required'] ?? true,
                            'sort_order' => $index,
                        ]
                    );
                }
            } else {
                $deliveryMethod->fields()->delete();
            }
        });

        return response()->json($deliveryMethod->fresh('fields'));
    }

    public function destroy(DeliveryMethod $deliveryMethod)
    {
        $deliveryMethod->delete();
        return response()->json(null, 204);
    }
}
