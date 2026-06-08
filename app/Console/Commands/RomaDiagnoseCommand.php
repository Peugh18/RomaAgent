<?php

namespace App\Console\Commands;

use App\Support\WhatsappDiagnostics;
use Illuminate\Console\Command;

class RomaDiagnoseCommand extends Command
{
    protected $signature = 'roma:diagnose {phone? : Número destino para prueba (ej. 51959166911)}';

    protected $description = 'Diagnostica la conexión WhatsApp directa de RomaAgent con Meta';

    public function handle(): int
    {
        $report = WhatsappDiagnostics::run($this->argument('phone'));

        $this->info('Diagnóstico WhatsApp RomaAgent');
        $this->line('Phone Number ID: '.($report['phone_number_id'] ?: '(vacío)'));
        $this->line('PUBLIC_APP_URL: '.($report['public_url'] ?: '(vacía)'));
        $this->line('Webhook URL: '.($report['webhook_url'] ?: '(vacía)'));

        if (is_array($report['send_probe'] ?? null)) {
            $this->newLine();
            $this->info('Prueba de envío:');
            $this->line(json_encode($report['send_probe'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $this->newLine();
        $this->warn('Estado:');
        foreach ($report['issues'] as $issue) {
            $this->line('- '.$issue);
        }

        $this->newLine();
        $this->comment('Registra en Meta Developer → Webhook: '.$report['webhook_url']);

        return self::SUCCESS;
    }
}
