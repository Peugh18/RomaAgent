<?php

namespace App\Console\Commands;

use App\Support\RomaApiDiagnostics;
use Illuminate\Console\Command;

class RomaDiagnoseCommand extends Command
{
    protected $signature = 'roma:diagnose {phone? : Número destino para prueba (ej. 51959166911)}';

    protected $description = 'Diagnostica la conexión entre RomaAgent y roma-api / Meta WhatsApp';

    public function handle(): int
    {
        $report = RomaApiDiagnostics::run($this->argument('phone'));

        $this->info('Diagnóstico RomaAgent → roma-api');
        $this->line('URL: '.($report['roma_api_url'] ?: '(vacía)'));
        $this->line('ROMA_SYNC_TOKEN: '.($report['sync_token_configured'] ? 'configurado' : 'FALTA'));

        if (is_array($report['health'])) {
            $this->newLine();
            $this->info('Health roma-api: HTTP '.$report['health']['status']);
            $this->line(json_encode($report['health']['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (is_array($report['send_probe'])) {
            $this->newLine();
            $this->info('Prueba de envío: HTTP '.$report['send_probe']['status']);
            $this->line(json_encode($report['send_probe']['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->newLine();
        $this->warn('Problemas detectados:');
        foreach ($report['issues'] as $issue) {
            $this->line('- '.$issue);
        }

        $this->newLine();
        $this->comment('Nota: el token de Meta va en roma-api (.env), NO en RomaAgent.');
        $this->comment('RomaAgent solo usa ROMA_SYNC_TOKEN para autenticarse con roma-api.');

        return self::SUCCESS;
    }
}
