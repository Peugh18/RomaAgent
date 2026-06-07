<?php

namespace App\Support;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Illuminate\Support\Collection;

class PipelineKanban
{
    public const ENTREGADOS_KANBAN_LIMIT = 15;

    /**
     * IDs de pedidos entregados visibles en la columna del kanban (los más recientes).
     *
     * @return Collection<int, int>
     */
    public static function recentEntregadoIds(): Collection
    {
        return Sale::query()
            ->where('status', SaleStatus::Entregado)
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->limit(self::ENTREGADOS_KANBAN_LIMIT)
            ->pluck('id');
    }

    public static function entregadosTotal(): int
    {
        return Sale::query()
            ->where('status', SaleStatus::Entregado)
            ->count();
    }

    public static function entregadosArchivedCount(): int
    {
        $total = self::entregadosTotal();

        return max(0, $total - min($total, self::ENTREGADOS_KANBAN_LIMIT));
    }
}
