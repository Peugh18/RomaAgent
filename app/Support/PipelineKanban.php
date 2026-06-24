<?php

namespace App\Support;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Illuminate\Support\Collection;

class PipelineKanban
{
    public const HOURS_LIMIT = 24;

    /**
     * IDs de pedidos entregados visibles en la columna del kanban (últimas 24 horas).
     *
     * @return Collection<int, int>
     */
    public static function recentEntregadoIds(): Collection
    {
        return Sale::query()
            ->where('status', SaleStatus::Entregado)
            ->where('delivered_at', '>=', now()->subHours(self::HOURS_LIMIT))
            ->pluck('id');
    }

    /**
     * IDs de pedidos cancelados visibles en la columna del kanban (últimas 24 horas).
     *
     * @return Collection<int, int>
     */
    public static function recentCanceladoIds(): Collection
    {
        return Sale::query()
            ->where('status', SaleStatus::Cancelado)
            ->where('updated_at', '>=', now()->subHours(self::HOURS_LIMIT))
            ->pluck('id');
    }

    public static function entregadosTotal(): int
    {
        return Sale::query()
            ->where('status', SaleStatus::Entregado)
            ->count();
    }

    public static function canceladosTotal(): int
    {
        return Sale::query()
            ->where('status', SaleStatus::Cancelado)
            ->count();
    }

    public static function entregadosArchivedCount(): int
    {
        $total = self::entregadosTotal();
        $recent = self::recentEntregadoIds()->count();

        return max(0, $total - $recent);
    }

    public static function canceladosArchivedCount(): int
    {
        $total = self::canceladosTotal();
        $recent = self::recentCanceladoIds()->count();

        return max(0, $total - $recent);
    }
}
