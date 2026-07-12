<?php

namespace App\Console\Commands;

use App\Exceptions\GeminiQuotaExceededException;
use App\Jobs\GenerarPerfilVisionVarianteJob;
use App\Models\ProductVariant;
use App\Services\Vision\AplicadorPerfilVisionVariante;
use Illuminate\Console\Command;

class VisionBackfillCommand extends Command
{
    protected $signature = 'vision:backfill
                            {--sync : Ejecutar en sync sin cola}
                            {--only-missing : Solo variantes sin color_profile o producto sin vision_profile}
                            {--delay=4 : Segundos entre llamadas Gemini en modo sync}
                            {--fallback-only : Sin Gemini; perfiles locales por nombre/color}';

    protected $description = 'Genera vision_profile y color_profile para variantes con foto';

    public function handle(AplicadorPerfilVisionVariante $aplicador): int
    {
        $variants = ProductVariant::query()
            ->with('product')
            ->where(function ($q): void {
                $q->whereNotNull('image_path')->where('image_path', '!=', '')
                    ->orWhere(function ($q2): void {
                        $q2->whereNotNull('image_url')->where('image_url', '!=', '');
                    });
            })
            ->get();

        if ($this->option('only-missing')) {
            $variants = $variants->filter(function (ProductVariant $variant): bool {
                $product = $variant->product;

                $colorMissing = empty($variant->color_profile) || ($variant->color_profile['origen'] ?? '') === 'fallback';
                $visionMissing = $product !== null && (empty($product->vision_profile) || ($product->vision_profile['origen'] ?? '') === 'fallback');

                return $colorMissing || $visionMissing;
            })->values();
        }

        if ($variants->isEmpty()) {
            $this->warn('No hay variantes pendientes con foto.');

            return self::SUCCESS;
        }

        $this->info('Procesando '.$variants->count().' variantes...');

        $delay = max(0, (int) $this->option('delay'));
        $fallbackOnly = (bool) $this->option('fallback-only');
        $ok = 0;
        $recuperados = 0;

        foreach ($variants as $variant) {
            try {
                if ($fallbackOnly) {
                    $aplicador->aplicarSoloFallback($variant);
                    $this->line("  ✓ fallback variante #{$variant->id} ({$variant->color})");
                    $ok++;

                    continue;
                }

                if ($this->option('sync')) {
                    $this->procesarSync($aplicador, $variant, $delay);
                } else {
                    GenerarPerfilVisionVarianteJob::dispatch($variant->id);
                }

                $ok++;
            } catch (\Throwable $e) {
                $recuperados++;
                $this->error("  ✗ variante #{$variant->id}: ".$e->getMessage());
                $aplicador->aplicarSoloFallback($variant->fresh(['product']));
            }
        }

        $this->info("Listo. OK: {$ok}, recuperados con fallback: {$recuperados}.");

        return self::SUCCESS;
    }

    private function procesarSync(
        AplicadorPerfilVisionVariante $aplicador,
        ProductVariant $variant,
        int $delay,
    ): void {
        $intentos = 0;

        while ($intentos < 3) {
            try {
                $resultado = $aplicador->aplicar($variant->fresh(['product']), usarGemini: true);
                $this->line(sprintf(
                    '  ✓ variante #%d (%s) gemini=%s fallback=%s',
                    $variant->id,
                    $variant->color,
                    $resultado['gemini'] ? 'sí' : 'no',
                    $resultado['fallback'] ? 'sí' : 'no',
                ));

                if ($delay > 0) {
                    sleep($delay);
                }

                return;
            } catch (GeminiQuotaExceededException $e) {
                $intentos++;
                $espera = max(15, $e->retryAfterSeconds);
                $this->warn("  ⚠ cuota Gemini variante #{$variant->id}, espera {$espera}s ({$intentos}/3)");
                sleep($espera);
            }
        }

        $aplicador->aplicar($variant->fresh(['product']), usarGemini: false);
        $this->line("  ✓ variante #{$variant->id} ({$variant->color}) solo fallback");
    }
}
