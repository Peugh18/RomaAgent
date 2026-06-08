<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaProxyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_local_storage_images_for_app_public_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/1/azul-test.jpg', 'fake-image-data');

        config([
            'app.url' => 'https://caravan-cycle-elixir.ngrok-free.dev',
            'app.public_url' => 'https://caravan-cycle-elixir.ngrok-free.dev',
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(
            '/media/proxy?url='.urlencode('https://caravan-cycle-elixir.ngrok-free.dev/storage/products/1/azul-test.jpg'),
        );

        $response->assertOk();
        $response->assertHeader('Content-Type');
    }

    public function test_rejects_unknown_media_hosts(): void
    {
        config([
            'app.public_url' => 'https://app.ngrok-free.dev',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/media/proxy?url='.urlencode('https://evil.example.com/media.jpg'))
            ->assertForbidden();
    }

    public function test_proxies_whatsapp_cdn_with_access_token(): void
    {
        config([
            'services.whatsapp.access_token' => 'test-wa-token',
        ]);

        $metaUrl = 'https://lookaside.fbsbx.com/whatsapp_business/attachments/?mid=123';

        Http::fake([
            $metaUrl => Http::response('jpeg-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(
            '/media/proxy?url='.urlencode($metaUrl),
        );

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');

        Http::assertSent(function ($request) use ($metaUrl) {
            return $request->url() === $metaUrl
                && $request->header('Authorization')[0] === 'Bearer test-wa-token';
        });
    }
}
