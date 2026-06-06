<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function updateIaMode(Request $request, string $phoneNumber): JsonResponse
    {
        $validated = $request->validate([
            'ia_paused' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        $customer = Customer::query()->firstOrCreate(
            ['phone_number' => $phoneNumber],
        );

        if ($validated['ia_paused']) {
            $customer->pausarIa($validated['reason'] ?? 'Pausado manualmente desde el panel');
        } else {
            $customer->reanudarIa();
        }

        return response()->json($customer->fresh());
    }

    public function show(string $phoneNumber): JsonResponse
    {
        $customer = Customer::query()
            ->where('phone_number', $phoneNumber)
            ->with('activeSale')
            ->first();

        return response()->json($customer);
    }
}
