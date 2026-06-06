<?php

namespace App\Http\Controllers\Api;

use App\Actions\Pedidos\ConfirmarPagoPedido;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Sale::query()
            ->with(['customer', 'product', 'productVariant'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('phone_number')) {
            $query->where('phone_number', $request->string('phone_number'));
        }

        if ($request->boolean('pipeline')) {
            $query->whereNotIn('status', [
                SaleStatus::Cancelado->value,
            ]);
        }

        return response()->json($query->limit(200)->get());
    }

    public function activeForPhone(string $phoneNumber): JsonResponse
    {
        $customer = Customer::query()->where('phone_number', $phoneNumber)->first();

        if ($customer === null || $customer->active_sale_id === null) {
            $sale = Sale::query()
                ->where('phone_number', $phoneNumber)
                ->whereIn('status', [
                    SaleStatus::PagoPendiente,
                    SaleStatus::PagoRecibido,
                    SaleStatus::DatosListos,
                    SaleStatus::Cotizando,
                ])
                ->latest()
                ->with(['customer', 'product', 'productVariant'])
                ->first();

            return response()->json($sale);
        }

        $sale = Sale::query()
            ->with(['customer', 'product', 'productVariant'])
            ->find($customer->active_sale_id);

        return response()->json($sale);
    }

    public function confirmPayment(Sale $sale, ConfirmarPagoPedido $confirmarPago): JsonResponse
    {
        $this->authorize('confirmPayment', $sale);

        // Validación de negocio: solo PagoPendiente o PagoRecibido pueden confirmarse
        if (! in_array($sale->status, [SaleStatus::PagoPendiente, SaleStatus::PagoRecibido], true)) {
            return response()->json([
                'message' => 'Solo se pueden confirmar pagos de pedidos pendientes.',
            ], 422);
        }

        try {
            $sale = $confirmarPago->handle($sale, request()->user());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($sale);
    }

    public function markShipped(Sale $sale): JsonResponse
    {
        $this->authorize('markShipped', $sale);

        // Validación de negocio: solo Confirmado puede marcarse como enviado
        if ($sale->status !== SaleStatus::Confirmado) {
            return response()->json([
                'message' => 'Solo se pueden enviar pedidos confirmados.',
            ], 422);
        }

        $sale->update([
            'status' => SaleStatus::Enviado,
            'shipped_at' => now(),
        ]);

        return response()->json($sale->fresh(['customer', 'product', 'productVariant']));
    }
}
