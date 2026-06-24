<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaleMultiMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            'https://graph.facebook.com/*' => function () {
                return Http::response(['messages' => [['id' => 'wamid.'.uniqid()]]], 200);
            },
        ]);
    }

    public function test_can_send_multiple_messages_on_payment_confirmation(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Test',
            'price' => 100,
            'status' => 'disponible',
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 10],
        ]);

        $sale = Sale::factory()->forProduct($product, $variant)->create([
            'status' => SaleStatus::PagoRecibido,
            'size' => 'UNICA',
            'quantity' => 1,
            'phone_number' => '51999999999',
        ]);

        $response = $this->actingAs($user)->postJson("/api/sales/{$sale->id}/confirm-payment", [
            'messages' => [
                ['content' => 'First message', 'delay_seconds' => 0],
                ['content' => 'Second message', 'delay_seconds' => 3],
            ],
        ]);

        $response->assertOk();

        // Check messages were created in the database
        $messages = Message::where('phone_number', '51999999999')
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(2, $messages);
        $this->assertEquals('First message', $messages[0]->content);
        $this->assertEquals('Second message', $messages[1]->content);
    }

    public function test_can_send_multiple_messages_on_mark_shipped(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => 'Test',
            'price' => 100,
            'status' => 'disponible',
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'color' => 'Lila',
            'sizes_stock' => ['UNICA' => 10],
        ]);

        $sale = Sale::factory()->forProduct($product, $variant)->create([
            'status' => SaleStatus::Confirmado,
            'size' => 'UNICA',
            'quantity' => 1,
            'phone_number' => '51999999999',
        ]);

        $response = $this->actingAs($user)->postJson("/api/sales/{$sale->id}/mark-shipped", [
            'messages' => [
                ['content' => 'Shipped msg', 'delay_seconds' => 0],
                ['content' => 'Extra shipped msg', 'delay_seconds' => 2],
            ],
        ]);

        $response->assertOk();

        $messages = Message::where('phone_number', '51999999999')
            ->orderBy('id', 'asc')
            ->get();

        $this->assertCount(2, $messages);
        $this->assertEquals('Shipped msg', $messages[0]->content);
        $this->assertEquals('Extra shipped msg', $messages[1]->content);
    }
}
