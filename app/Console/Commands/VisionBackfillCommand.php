<?php

namespace App\Console\Commands;

use App\Jobs\GenerarPerfilVisionVarianteJob;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class VisionBackfillCommand extends Command
{
    protected $signature = 'vision:backfill {--sync : Ejecutar en sync sin cola}';

    protected $description = 'Genera vision_profile y color_profile para variantes con foto';

    public function handle(): int
    {
        $variants = ProductVariant::query()
            ->where(function ($q): void {
                $q->whereNotNull('image_path')->where('image_path', '!=', '')
                    ->orWhere(function ($q2): void {
                        $q2->whereNotNull('image_url')->where('image_url', '!=', '');
                    });
            })
            ->pluck('id');

        if ($variants->isEmpty()) {
            $this->warn('No hay variantes con foto.');

            return self::SUCCESS;
        }

        $this->info('Procesando '.$variants->count().' variantes...');

        foreach ($variants as $variantId) {
            if ($this->option('sync')) {
                GenerarPerfilVisionVarianteJob::dispatchSync($variantId);
            } else {
                GenerarPerfilVisionVarianteJob::dispatch($variantId);
            }
        }

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
