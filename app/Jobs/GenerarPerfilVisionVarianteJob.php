<?php

namespace App\Jobs;

use App\Exceptions\GeminiQuotaExceededException;
use App\Models\ProductVariant;
use App\Services\Vision\AplicadorPerfilVisionVariante;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerarPerfilVisionVarianteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $variantId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [20, 45, 90, 120];
    }

    public function handle(AplicadorPerfilVisionVariante $aplicador): void
    {
        $variant = ProductVariant::query()->with('product')->find($this->variantId);
        if ($variant === null) {
            return;
        }

        try {
            $aplicador->aplicar($variant, usarGemini: true);
        } catch (GeminiQuotaExceededException $e) {
            if ($this->attempts() < $this->tries) {
                $this->release(max(15, $e->retryAfterSeconds));

                return;
            }

            Log::warning('GenerarPerfilVisionVarianteJob: cuota agotada, aplicando fallback', [
                'variant_id' => $variant->id,
            ]);

            $aplicador->aplicar($variant->fresh(['product']), usarGemini: false);
        }
    }
}
