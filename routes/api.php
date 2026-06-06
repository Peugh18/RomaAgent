<?php



use App\Http\Controllers\Api\CategoryController as ApiCategoryController;

use App\Http\Controllers\Api\CompanySettingController as ApiCompanySettingController;

use App\Http\Controllers\Api\DeliveryZoneController as ApiDeliveryZoneController;

use App\Http\Controllers\Api\ProductController as ApiProductController;

use App\Http\Controllers\Api\ProductoSimilarController;

use App\Http\Controllers\Api\ProductVariantPhotoController;

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EstadoIAController;
use App\Http\Controllers\Api\RomaMessageController;
use App\Http\Controllers\Api\SaleController;

use Illuminate\Support\Facades\Route;



Route::prefix('roma')->middleware(['throttle:roma-webhook'])->group(function () {

    Route::post('/messages', [RomaMessageController::class, 'receive']);

});



Route::middleware(['web', 'auth'])->group(function () {

    Route::get('/messages', [RomaMessageController::class, 'index']);

    Route::get('/conversations', [RomaMessageController::class, 'conversations']);

    Route::post('/send-message', [RomaMessageController::class, 'send']);

    Route::post('/messages/{message}/resend', [RomaMessageController::class, 'resend']);



    Route::apiResource('products', ApiProductController::class);

    Route::post('product-variants/{variant}/photo', [ProductVariantPhotoController::class, 'store']);

    Route::apiResource('categories', ApiCategoryController::class);

    Route::apiResource('delivery-zones', ApiDeliveryZoneController::class);
    Route::post('delivery-zones/import-roma-store', [ApiDeliveryZoneController::class, 'importRomaStore']);

    Route::get('products/{product}/similares', [ProductoSimilarController::class, 'show']);

    Route::put('products/{product}/similares', [ProductoSimilarController::class, 'update']);

    Route::get('company-settings', [ApiCompanySettingController::class, 'index']);

    Route::put('company-settings', [ApiCompanySettingController::class, 'update']);

    Route::delete('company-settings', [ApiCompanySettingController::class, 'destroy']);

    Route::get('sales', [SaleController::class, 'index']);
    Route::get('sales/active/{phoneNumber}', [SaleController::class, 'activeForPhone'])
        ->where('phoneNumber', '[0-9+]+');
    Route::post('sales/{sale}/confirm-payment', [SaleController::class, 'confirmPayment']);
    Route::post('sales/{sale}/mark-shipped', [SaleController::class, 'markShipped']);

    Route::get('customers/{phoneNumber}', [CustomerController::class, 'show'])
        ->where('phoneNumber', '[0-9+]+');
    Route::put('customers/{phoneNumber}/ia-mode', [CustomerController::class, 'updateIaMode'])
        ->where('phoneNumber', '[0-9+]+');

    Route::get('estado-ia', [EstadoIAController::class, 'verificar']);
    Route::get('alerta-cuota-gemini', [EstadoIAController::class, 'alertaCuota']);

});

