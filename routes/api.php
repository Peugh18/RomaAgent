<?php

use App\Http\Controllers\Api\CategoryController as ApiCategoryController;
use App\Http\Controllers\Api\CompanySettingController as ApiCompanySettingController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DeliveryZoneController as ApiDeliveryZoneController;
use App\Http\Controllers\Api\EstadoIAController;
use App\Http\Controllers\Api\ProductController as ApiProductController;
use App\Http\Controllers\Api\ProductoSimilarController;
use App\Http\Controllers\Api\ProductVariantPhotoController;
use App\Http\Controllers\Api\RomaMessageController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/whatsapp/webhook', [WhatsappWebhookController::class, 'handle'])
    ->middleware(['throttle:roma-webhook']);

// Alias compatible con la URL que usaba roma-api (/api/webhook)
Route::match(['get', 'post'], '/webhook', [WhatsappWebhookController::class, 'handle'])
    ->middleware(['throttle:roma-webhook']);

Route::prefix('roma')->middleware(['throttle:roma-webhook'])->group(function () {

    Route::post('/messages', [RomaMessageController::class, 'receive']);

});

Route::middleware(['web', 'auth', 'throttle:api'])->group(function () {

    Route::get('/messages', [RomaMessageController::class, 'index']);

    Route::get('/conversations', [RomaMessageController::class, 'conversations']);

    Route::post('/send-message', [RomaMessageController::class, 'send']);

    Route::post('/messages/{message}/resend', [RomaMessageController::class, 'resend']);

    Route::post('products/generate-embeddings', [ApiProductController::class, 'generateEmbeddings']);
    Route::apiResource('products', ApiProductController::class);

    Route::post('product-variants/{variant}/photo', [ProductVariantPhotoController::class, 'store']);

    Route::apiResource('categories', ApiCategoryController::class);

    Route::apiResource('delivery-zones', ApiDeliveryZoneController::class);
    Route::post('delivery-zones/import-roma-store', [ApiDeliveryZoneController::class, 'importRomaStore']);

    Route::get('products/{product}/similares', [ProductoSimilarController::class, 'show']);

    Route::put('products/{product}/similares', [ProductoSimilarController::class, 'update']);

    Route::get('company-settings', [ApiCompanySettingController::class, 'index']);

    Route::get('company-settings/prompt-completo', [ApiCompanySettingController::class, 'promptCompleto']);

    Route::put('company-settings', [ApiCompanySettingController::class, 'update']);

    Route::delete('company-settings', [ApiCompanySettingController::class, 'destroy']);

    Route::get('sales', [SaleController::class, 'index']);
    Route::get('sales/active/{phoneNumber}', [SaleController::class, 'activeForPhone'])
        ->where('phoneNumber', '[0-9+]+');
    Route::get('sales/{sale}/transition-preview', [SaleController::class, 'transitionPreview']);
    Route::post('sales/{sale}/confirm-payment', [SaleController::class, 'confirmPayment']);
    Route::post('sales/{sale}/send-payment-link', [SaleController::class, 'sendPaymentLink']);
    Route::post('sales/{sale}/mark-shipped', [SaleController::class, 'markShipped']);
    Route::post('sales/{sale}/mark-delivered', [SaleController::class, 'markDelivered']);
    Route::post('sales/{sale}/revert-delivered', [SaleController::class, 'revertDelivered']);
    Route::post('sales/{sale}/revert-shipped', [SaleController::class, 'revertShipped']);
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel']);

    Route::get('customers', [CustomerController::class, 'index']);
    Route::get('customers/{phoneNumber}', [CustomerController::class, 'show'])
        ->where('phoneNumber', '[0-9+]+');
    Route::put('customers/{id}', [CustomerController::class, 'update'])
        ->where('id', '[0-9]+');
    Route::put('customers/{phoneNumber}/ia-mode', [CustomerController::class, 'updateIaMode'])
        ->where('phoneNumber', '[0-9+]+');

    Route::get('estado-ia', [EstadoIAController::class, 'verificar']);
    Route::get('alerta-cuota-gemini', [EstadoIAController::class, 'alertaCuota']);

});
