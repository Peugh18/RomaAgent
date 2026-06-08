<?php

namespace Tests\Unit;

use App\Services\Media\DescargadorMediaWhatsapp;
use App\Services\ServicioResolucionMediaEntrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServicioResolucionMediaEntranteTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_reutiliza_storage_local_sin_http(): void
    {
        Http::fake();

        config([
            'app.url' => 'https://caravan-cycle-elixir.ngrok-free.dev',
            'app.public_url' => 'https://caravan-cycle-elixir.ngrok-free.dev',
        ]);

        $localUrl = '/storage/inbound-media/wamid_test_image_123.jpg';
        $absoluteUrl = 'https://caravan-cycle-elixir.ngrok-free.dev'.$localUrl;

        $service = app(ServicioResolucionMediaEntrante::class);

        $resolved = $service->resolver([
            'media_url' => $absoluteUrl,
            'image_url' => $localUrl,
            'local_url' => $localUrl,
            'mime_type' => 'image/jpeg',
        ], 'image', 'wamid_test');

        $this->assertIsArray($resolved);
        $this->assertSame($localUrl, $resolved['local_url']);
        $this->assertSame($absoluteUrl, $resolved['url']);
        $this->assertSame('image/jpeg', $resolved['mime']);

        Http::assertNothingSent();
    }

    public function test_resolver_descarga_desde_meta_cuando_no_hay_url_local(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['url' => 'https://lookaside.fbsbx.com/media/test.jpg']),
            'lookaside.fbsbx.com/*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456789',
            'app.url' => 'https://example.test',
            'app.public_url' => 'https://example.test',
        ]);

        $service = new ServicioResolucionMediaEntrante(new DescargadorMediaWhatsapp);

        $resolved = $service->resolver([
            'raw' => [
                'type' => 'image',
                'image' => ['id' => 'media-123'],
            ],
        ], 'image', 'wamid_meta_test');

        $this->assertIsArray($resolved);
        $this->assertNotNull($resolved['local_url']);
        $this->assertStringStartsWith('/storage/inbound-media/', $resolved['local_url']);
    }
}
