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
    public function index(\Illuminate\Http\Request $request): Response
    {
        $period = $request->input('period', 'hoy');

        if ($period === '7d') {
            $inicio = now()->subDays(6)->startOfDay();
            $fin = now()->endOfDay();
            $anteriorInicio = now()->subDays(13)->startOfDay();
            $anteriorFin = now()->subDays(7)->endOfDay();
        } elseif ($period === '30d') {
            $inicio = now()->subDays(29)->startOfDay();
            $fin = now()->endOfDay();
            $anteriorInicio = now()->subDays(59)->startOfDay();
            $anteriorFin = now()->subDays(30)->endOfDay();
        } elseif ($period === 'mes_actual') {
            $inicio = now()->startOfMonth();
            $fin = now()->endOfMonth();
            $anteriorInicio = now()->subMonth()->startOfMonth();
            $anteriorFin = now()->subMonth()->endOfMonth();
        } else {
            $inicio = now()->startOfDay();
            $fin = now()->endOfDay();
            $anteriorInicio = now()->subDay()->startOfDay();
            $anteriorFin = now()->subDay()->endOfDay();
        }

        $ventasHoy = (float) Sale::query()
            ->where('status', '!=', SaleStatus::Cancelado)
            ->whereBetween('confirmed_at', [$inicio, $fin])
            ->sum('total_amount');

        $ventasAyer = (float) Sale::query()
            ->where('status', '!=', SaleStatus::Cancelado)
            ->whereBetween('confirmed_at', [$anteriorInicio, $anteriorFin])
            ->sum('total_amount');

        $ventasMes = (float) Sale::query()
            ->where('status', '!=', SaleStatus::Cancelado)
            ->where('confirmed_at', '>=', now()->startOfMonth())
            ->sum('total_amount');

        $pendientesPago = Sale::query()
            ->whereIn('status', [SaleStatus::PagoPendiente, SaleStatus::PagoRecibido])
            ->count();

        $conversacionesHoy = Message::query()
            ->whereBetween('created_at', [$inicio, $fin])
            ->distinct('phone_number')
            ->count('phone_number');

        $productosActivos = Product::query()
            ->where('status', Product::ESTADO_DISPONIBLE)
            ->count();

        $pedidosActivos = Sale::query()
            ->whereNotIn('status', [SaleStatus::Cancelado, SaleStatus::Entregado])
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

        $chartData = Sale::query()
            ->select([
                DB::raw('DATE(confirmed_at) as date'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('COUNT(*) as orders'),
            ])
            ->where('status', '!=', SaleStatus::Cancelado)
            ->whereNotNull('confirmed_at')
            ->whereBetween('confirmed_at', [$inicio, $fin])
            ->groupBy(DB::raw('DATE(confirmed_at)'))
            ->orderBy(DB::raw('DATE(confirmed_at)'))
            ->get()
            ->keyBy('date');

        $diferenciaDias = (int) $inicio->diffInDays($fin);
        $daysArray = $diferenciaDias > 0 ? range(0, $diferenciaDias) : [0];
        
        $chart = collect($daysArray)->map(function (int $daysAgo) use ($chartData, $fin): array {
            $date = $fin->copy()->subDays($daysAgo)->startOfDay();
            $dateStr = $date->toDateString();
            $data = $chartData->get($dateStr);

            return [
                'date' => $dateStr,
                'label' => $date->locale('es')->isoFormat('ddd D'),
                'sales' => (float) ($data->total ?? 0),
                'orders' => (int) ($data->orders ?? 0),
            ];
        })->reverse()->values();

        $pipelineOverview = Sale::query()
            ->where('status', '!=', SaleStatus::Cancelado)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status->value,
                'label' => $row->status->label(),
                'count' => (int) $row->count,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $lowStockProducts = Product::query()
            ->with('variants')
            ->where('status', '!=', Product::ESTADO_OCULTO)
            ->get()
            ->map(function (Product $product): array {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'stock' => $product->stockTotal(),
                ];
            })
            ->filter(fn (array $p): bool => $p['stock'] < 5)
            ->sortBy('stock')
            ->values()
            ->take(10)
            ->all();

        return Inertia::render('Dashboard', [
            'stats' => [
                'conversaciones_hoy' => $conversacionesHoy,
                'pendientes_pago' => $pendientesPago,
                'productos_activos' => $productosActivos,
                'ventas_hoy' => $ventasHoy,
                'ventas_ayer' => $ventasAyer,
                'ventas_mes' => $ventasMes,
                'pedidos_activos' => $pedidosActivos,
                'ventas_trend' => $this->calcularTendenciaPorcentual($ventasHoy, $ventasAyer),
            ],
            'pedidosRecientes' => $pedidosRecientes,
            'chartData' => $chart,
            'pipelineOverview' => $pipelineOverview,
            'lowStockProducts' => $lowStockProducts,
        ]);
    }

    private function calcularTendenciaPorcentual(float $actual, float $anterior): ?float
    {
        if ($anterior <= 0) {
            return $actual > 0 ? 100.0 : null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }
}
