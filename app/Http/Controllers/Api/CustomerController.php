<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 20);
        $perPage = min(max((int) $perPage, 1), 100);

        $search = trim((string) $request->input('search', ''));
        $minSpent = $request->input('min_spent');
        $minPurchases = $request->input('min_purchases');
        $sortBy = $request->input('sort_by', 'last_inbound_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $customers = Customer::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('phone_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->withSum('sales as total_spent', 'total_amount')
            ->with(['sales' => fn ($q) => $q->latest()->limit(5)])
            ->withCount('sales')
            ->when($minSpent !== null, function ($query) use ($minSpent) {
                $query->having('total_spent', '>=', (float) $minSpent);
            })
            ->when($minPurchases !== null, function ($query) use ($minPurchases) {
                $query->having('sales_count', '>=', (int) $minPurchases);
            })
            ->when($sortBy === 'total_spent', function ($query) use ($sortDir) {
                $query->orderBy('total_spent', $sortDir);
            })
            ->when($sortBy === 'sales_count', function ($query) use ($sortDir) {
                $query->orderBy('sales_count', $sortDir);
            })
            ->when(!in_array($sortBy, ['total_spent', 'sales_count']), function ($query) use ($sortBy, $sortDir) {
                // Previene SQL injection validando contra lista blanca
                $validColumns = ['last_inbound_at', 'created_at', 'name'];
                $column = in_array($sortBy, $validColumns) ? $sortBy : 'last_inbound_at';
                $query->orderBy($column, $sortDir === 'asc' ? 'asc' : 'desc');
                if ($column !== 'created_at') {
                    $query->orderByDesc('created_at');
                }
            })
            ->paginate($perPage);

        return CustomerResource::collection($customers)->response();
    }

    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = Customer::query()->findOrFail($id);
        $customer->update($request->validated());

        return response()->json($customer->fresh());
    }

    public function updateIaMode(Request $request, string $phoneNumber)
    {
        $customer = Customer::where('phone_number', $phoneNumber)->firstOrFail();

        $validated = $request->validate([
            'ia_paused' => 'required|boolean',
        ]);

        if ($validated['ia_paused']) {
            $customer->pausarIa('Pausado manualmente por el asesor desde el panel.');
        } else {
            $customer->reanudarIa();
        }

        return response()->json([
            'message' => 'Modo IA actualizado correctamente',
            'customer' => [
                'id' => $customer->id,
                'phone_number' => $customer->phone_number,
                'ia_paused' => $customer->ia_paused,
            ],
        ]);
    }

    public function syncLabels(Request $request, string $phoneNumber)
    {
        $customer = Customer::where('phone_number', $phoneNumber)->firstOrFail();

        $validated = $request->validate([
            'label_ids' => 'array',
            'label_ids.*' => 'exists:labels,id',
        ]);

        $customer->labels()->sync($validated['label_ids'] ?? []);

        return response()->json([
            'message' => 'Etiquetas actualizadas',
            'labels' => $customer->labels()->get(),
        ]);
    }

    public function show(string $phoneNumber): JsonResponse
    {
        $customer = Customer::query()
            ->where('phone_number', $phoneNumber)
            ->with(['activeSale', 'labels'])
            ->first();

        return response()->json($customer);
    }
}
