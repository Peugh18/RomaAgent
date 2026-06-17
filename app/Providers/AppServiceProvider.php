<?php

namespace App\Providers;

use App\Models\AgenteConfig;
use App\Models\EmpresaInfoConfig;
use App\Models\HorarioConfig;
use App\Models\MensajeConfig;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VentaConfig;
use App\Observers\AgenteConfigObserver;
use App\Observers\EmpresaInfoConfigObserver;
use App\Observers\HorarioConfigObserver;
use App\Observers\MensajeConfigObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductVariantObserver;
use App\Observers\VentaConfigObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Observers de productos
        Product::observe(ProductObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);

        // Observers de configuraciones (invalidación de caché sin Redis)
        // NOTA: Funcionan con cualquier driver: database, file, array, redis
        AgenteConfig::observe(AgenteConfigObserver::class);
        MensajeConfig::observe(MensajeConfigObserver::class);
        EmpresaInfoConfig::observe(EmpresaInfoConfigObserver::class);
        VentaConfig::observe(VentaConfigObserver::class);
        HorarioConfig::observe(HorarioConfigObserver::class);

        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
