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

        $ventasHoy = (float) Sale::query()
            ->where('status', '!=', SaleStatus::Cancelado)
            ->where('confirmed_at', '>=', $hoy)
            ->sum('total_amount');

        $ventasMes = (float) Sale::query()
            ->where('status', '!=', SaleStatus::Cancelado)
            ->where('confirmed_at', '>=', $inicioMes)
            ->sum('total_amount');

        $pendientesPago = Sale::query()
            ->whereIn('status', [SaleStatus::PagoPendiente, SaleStatus::PagoRecibido])
            ->count();

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
            ->where('status', '!=', SaleStatus::Cancelado)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'product_name' => $sale->product_name,
                'color' => $sale->color,
                'phone_number' => $sale->phone_number,
                'customer_name' => $sale->customer?->name,
                'total_amount' => (float) $sale->total_amount,
                'status' => $sale->status->value,
                'status_label' => $sale->status->label(),
                'created_at' => $sale->created_at?->toIso8601String(),
            ]);

        // Chart data: sales last 7 days
        $hace7Dias = now()->subDays(6)->startOfDay();
        $chartData = Sale::query()
            ->select([
                DB::raw('DATE(confirmed_at) as date'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('COUNT(*) as orders'),
            ])
            ->where('status', '!=', SaleStatus::Cancelado)
            ->whereNotNull('confirmed_at')
            ->where('confirmed_at', '>=', $hace7Dias)
            ->groupBy(DB::raw('DATE(confirmed_at)'))
            ->orderBy(DB::raw('DATE(confirmed_at)'))
            ->get()
            ->keyBy('date');

        // Fill missing days with zeros
        $chart = collect(range(0, 6))->map(function (int $daysAgo) use ($chartData): array {
            $date = now()->subDays($daysAgo)->startOfDay();
            $dateStr = $date->toDateString();
            $data = $chartData->get($dateStr);

            return [
                'date' => $dateStr,
                'label' => $date->format('D j'),
                'sales' => (float) ($data->total ?? 0),
                'orders' => (int) ($data->orders ?? 0),
            ];
        })->reverse()->values();

        return Inertia::render('Dashboard', [
            'stats' => [
                'conversaciones_hoy' => $conversacionesHoy,
                'pendientes_pago' => $pendientesPago,
                'productos_activos' => $productosActivos,
                'ventas_hoy' => $ventasHoy,
                'ventas_mes' => $ventasMes,
            ],
            'pedidosRecientes' => $pedidosRecientes,
            'chartData' => $chart,
        ]);
    }
}
