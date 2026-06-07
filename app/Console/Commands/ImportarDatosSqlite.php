<?php

namespace App\Console\Commands;

use App\Support\SanitizadorMetodosPago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

class ImportarDatosSqlite extends Command
{
    /**
     * @var string
     */
    protected $signature = 'db:import-sqlite
                            {--path= : Ruta al archivo SQLite (por defecto database/database.sqlite)}
                            {--fresh : Ejecuta migrate:fresh antes de importar}';

    /**
     * @var string
     */
    protected $description = 'Importa datos desde SQLite a la base MySQL activa';

    /**
     * Orden respetando claves foráneas.
     *
     * @var list<string>
     */
    private array $tablas = [
        'users',
        'categories',
        'company_settings',
        'products',
        'product_variants',
        'producto_similares',
        'delivery_zones',
        'customers',
        'sales',
        'messages',
        'logs_ia',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    public function handle(): int
    {
        $ruta = (string) ($this->option('path') ?: database_path('database.sqlite'));

        if (! is_file($ruta)) {
            $this->error("No se encontró SQLite en: {$ruta}");

            return self::FAILURE;
        }

        if (config('database.default') === 'sqlite') {
            $this->error('La conexión activa es SQLite. Configura MySQL en .env antes de importar.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $sqlite = new PDO('sqlite:'.$ruta, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $tablasSqlite = $sqlite
            ->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
            ->fetchAll(PDO::FETCH_COLUMN);

        Schema::disableForeignKeyConstraints();

        try {
            DB::statement('SET SESSION max_allowed_packet=67108864');
        } catch (Throwable) {
            // Algunos hosts no permiten cambiar la variable de sesión.
        }

        $importadas = 0;
        $filas = 0;

        foreach ($this->tablas as $tabla) {
            if (! in_array($tabla, $tablasSqlite, true) || ! Schema::hasTable($tabla)) {
                continue;
            }

            $columnasMysql = Schema::getColumnListing($tabla);

            if ($columnasMysql === []) {
                continue;
            }

            DB::table($tabla)->truncate();

            $statement = $sqlite->query("SELECT * FROM `{$tabla}`");
            $lote = [];
            $contadorTabla = 0;

            while ($fila = $statement->fetch(PDO::FETCH_ASSOC)) {
                $registro = [];

                foreach ($columnasMysql as $columna) {
                    if (array_key_exists($columna, $fila)) {
                        $registro[$columna] = $this->normalizarValorImportado($tabla, $columna, $fila[$columna]);
                    }
                }

                if ($registro !== []) {
                    $lote[] = $registro;
                }

                if (count($lote) >= 100) {
                    $this->insertarLote($tabla, $lote);
                    $contadorTabla += count($lote);
                    $lote = [];
                }
            }

            if ($lote !== []) {
                $this->insertarLote($tabla, $lote);
                $contadorTabla += count($lote);
            }

            if ($contadorTabla > 0) {
                $this->line("  {$tabla}: {$contadorTabla} filas");
                $importadas++;
                $filas += $contadorTabla;
            }
        }

        Schema::enableForeignKeyConstraints();

        try {
            DB::statement('ALTER TABLE users AUTO_INCREMENT = '.((int) DB::table('users')->max('id') + 1));
            DB::statement('ALTER TABLE products AUTO_INCREMENT = '.((int) DB::table('products')->max('id') + 1));
            DB::statement('ALTER TABLE messages AUTO_INCREMENT = '.((int) DB::table('messages')->max('id') + 1));
        } catch (Throwable) {
            // Tablas vacías o sin auto_increment relevante.
        }

        $this->info("Importación completa: {$importadas} tablas, {$filas} filas.");

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $lote
     */
    private function insertarLote(string $tabla, array $lote): void
    {
        foreach ($lote as $registro) {
            DB::table($tabla)->insert($registro);
        }
    }

    private function normalizarValorImportado(string $tabla, string $columna, mixed $valor): mixed
    {
        if ($tabla === 'company_settings' && $columna === 'metodos_pago' && is_string($valor)) {
            $decoded = json_decode($valor, true);

            if (is_array($decoded)) {
                return json_encode(SanitizadorMetodosPago::sanitizar($decoded), JSON_UNESCAPED_UNICODE);
            }
        }

        return $valor;
    }
}
