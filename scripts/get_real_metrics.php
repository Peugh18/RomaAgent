<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$output = [];

// 1. Modelo Gemini
$settings = DB::table('company_settings')->first();
$output['modelo_gemini'] = $settings->agente_ia_modelo ?? $settings->modelo ?? 'No configurado en BD';

// 2. Driver de Colas
$output['queue_driver'] = env('QUEUE_CONNECTION', 'sync');

// 3. Número de workers activos (Windows)
$workers = shell_exec('wmic process where "commandline like \'%queue:work%\'" get processid 2>nul');
$workerCount = substr_count(strtoupper((string)$workers), 'PROCESSID') ? (count(array_filter(explode("\n", trim((string)$workers)))) - 1) : 0;
$output['workers_activos'] = $workerCount;

// 4. Configuración de Horizon / Supervisor
$hasHorizon = file_exists(config_path('horizon.php')) ? 'Sí (Instalado)' : 'No Instalado';
$output['horizon_configurado'] = $hasHorizon;

// 5 y 6. Métricas de mensajes de IA
$aiMessages = DB::table('messages')
    ->where('direction', 'outgoing')
    ->whereRaw("JSON_EXTRACT(metadata, '$.generated_by') = 'ai'")
    ->orderBy('id', 'desc')
    ->limit(100)
    ->pluck('metadata')
    ->map(function ($meta) {
        $data = json_decode($meta, true);
        return [
            'tokens_in' => $data['prompt_tokens'] ?? 0,
            'tokens_out' => $data['completion_tokens'] ?? 0,
            'duration_ms' => $data['processing_time_ms'] ?? 0,
        ];
    })
    ->filter(fn($m) => $m['tokens_in'] > 0 || $m['duration_ms'] > 0)
    ->values()
    ->toArray();

if (count($aiMessages) > 0) {
    $durations = array_column($aiMessages, 'duration_ms');
    sort($durations);
    $count = count($durations);
    
    $output['gemini_metrics'] = [
        'muestras' => $count,
        'promedio_ms' => round(array_sum($durations) / $count),
        'p95_ms' => $durations[floor($count * 0.95)] ?? end($durations),
        'p99_ms' => $durations[floor($count * 0.99)] ?? end($durations),
        'promedio_tokens_in' => round(array_sum(array_column($aiMessages, 'tokens_in')) / $count),
        'promedio_tokens_out' => round(array_sum(array_column($aiMessages, 'tokens_out')) / $count),
    ];
} else {
    $output['gemini_metrics'] = 'No hay datos de tokens/tiempos en los metadatos recientes.';
}

// 7. Jobs fallidos (últimos 7 días)
if (Schema::hasTable('failed_jobs')) {
    $failedJobs = DB::table('failed_jobs')
        ->where('failed_at', '>=', now()->subDays(7))
        ->count();
    $output['jobs_fallidos_7_dias'] = $failedJobs;
} else {
    $output['jobs_fallidos_7_dias'] = 'Tabla failed_jobs no existe.';
}

// 8. Longitud de la cola actual
if (Schema::hasTable('jobs')) {
    $queueLength = DB::table('jobs')->count();
    $output['longitud_cola_actual'] = $queueLength;
} else {
    $output['longitud_cola_actual'] = 'Tabla jobs no existe.';
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
