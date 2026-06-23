<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ZonaEnvio;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ZonaEnvioController extends Controller
{
    public function index(Request $request)
    {
        $query = ZonaEnvio::query();

        if ($request->filled('search')) {
            $search = '%'.$request->search.'%';
            $query->where(function ($q) use ($search) {
                $q->where('distrito', 'like', $search)
                    ->orWhere('provincia', 'like', $search)
                    ->orWhere('departamento', 'like', $search);
            });
        }

        $sortField = $request->input('sort', 'departamento');
        $sortDirection = $request->input('order', 'asc');

        $allowedSorts = ['departamento', 'provincia', 'distrito', 'tipo_envio', 'costo_referencial', 'activo'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        return $query->paginate($request->input('per_page', 20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'departamento' => 'required|string|max:255',
            'provincia' => 'required|string|max:255',
            'distrito' => 'required|string|max:255',
            'tipo_envio' => 'required|string|max:255',
            'costo_referencial' => 'required|numeric|min:0',
            'activo' => 'boolean',
            'observaciones' => 'nullable|string',
            'datos_requeridos' => 'nullable|array',
        ]);

        $zona = ZonaEnvio::create($validated);

        return response()->json($zona, Response::HTTP_CREATED);
    }

    public function show(ZonaEnvio $zonaEnvio)
    {
        return response()->json($zonaEnvio);
    }

    public function update(Request $request, ZonaEnvio $zonaEnvio)
    {
        $validated = $request->validate([
            'departamento' => 'sometimes|required|string|max:255',
            'provincia' => 'sometimes|required|string|max:255',
            'distrito' => 'sometimes|required|string|max:255',
            'tipo_envio' => 'sometimes|required|string|max:255',
            'costo_referencial' => 'sometimes|required|numeric|min:0',
            'activo' => 'boolean',
            'observaciones' => 'nullable|string',
            'datos_requeridos' => 'nullable|array',
        ]);

        $zonaEnvio->update($validated);

        return response()->json($zonaEnvio);
    }

    public function destroy(ZonaEnvio $zonaEnvio)
    {
        $zonaEnvio->delete();

        return response()->noContent();
    }

    public function toggle(ZonaEnvio $zonaEnvio)
    {
        $zonaEnvio->update(['activo' => ! $zonaEnvio->activo]);

        return response()->json($zonaEnvio);
    }
}
