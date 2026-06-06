<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedProxyAssetUrlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_helper_uses_https_when_request_is_proxied_over_https(): void
    {
        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.1',
            'X-Forwarded-Host' => 'caravan-cycle-elixir.ngrok-free.dev',
        ])->get('/');

        $this->assertSame(
            'https://caravan-cycle-elixir.ngrok-free.dev',
            rtrim(url('/'), '/')
        );
    }

    public function test_built_vite_assets_use_https_when_manifest_exists_and_proxied(): void
    {
        if (! file_exists(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Production Vite build not available.');
        }

        if (file_exists(public_path('hot'))) {
            $this->markTestSkipped('Vite dev server is running.');
        }

        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.1',
            'X-Forwarded-Host' => 'caravan-cycle-elixir.ngrok-free.dev',
        ])->get('/');

        $response->assertOk();
        $response->assertSee('https://caravan-cycle-elixir.ngrok-free.dev/build/assets/', false);
        $response->assertDontSee('http://caravan-cycle-elixir.ngrok-free.dev/build/assets/', false);
    }
}
