<?php

namespace App\Http\Controllers;

use App\Enums\SaleStatus;
use App\Models\Message;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $hoy = now()->startOfDay();
        $inicioMes = now()->startOfMonth();

        // Aggregates optimizados en una única query por entidad
        $stats = Sale::query()
            ->select([
                DB::raw('sum(case when status = ? and confirmed_at >= ? then total_amount end) as ventas_hoy'),
                DB::raw('sum(case when status = ? and confirmed_at >= ? then total_amount end) as ventas_mes'),
                DB::raw('count(case when status in (?, ?) then 1 end) as pendientes_pago'),
            ])
            ->addBinding([SaleStatus::Confirmado->value, $hoy, SaleStatus::Confirmado->value, $inicioMes, SaleStatus::PagoPendiente->value, SaleStatus::PagoRecibido->value])
            ->first();

        // Queries individuales para métricas de otras entidades
        $conversacionesHoy = Message::query()
            ->where('created_at', '>=', $hoy)
            ->distinct('phone_number')
            ->count('phone_number');

        $productosActivos = Product::query()
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->count();

        $pedidosRecientes = Sale::query()
            ->with('customer')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'product_name' => $sale->product_name,
                'color' => $sale->color,
                'phone_number' => $sale->phone_number,
                'total_amount' => (float) $sale->total_amount,
                'status' => $sale->status->value,
                'status_label' => $sale->status->label(),
                'created_at' => $sale->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'conversaciones_hoy' => $conversacionesHoy,
                'pendientes_pago' => (int) ($stats->pendientes_pago ?? 0),
                'productos_activos' => $productosActivos,
                'ventas_hoy' => (float) ($stats->ventas_hoy ?? 0),
                'ventas_mes' => (float) ($stats->ventas_mes ?? 0),
            ],
            'pedidosRecientes' => $pedidosRecientes,
        ]);
    }
}
