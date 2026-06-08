<?php

namespace Tests\Unit;

use App\Models\CompanySetting;
use App\Services\Media\ImageAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImageAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_local_storage_image_using_agent_model(): void
    {
        $relativePath = 'inbound-media/test-comprobante.jpg';
        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'fake-jpeg-bytes');

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [[
                        'text' => json_encode([
                            'tipo_prenda' => 'vestido',
                            'material_aparente' => 'punto',
                            'keywords' => ['vestido', 'punto'],
                        ]),
                    ]]]],
                ],
            ]),
        ]);

        $result = app(ImageAnalyzer::class)->analyzeUrl('/storage/'.$relativePath);

        @unlink($fullPath);

        $this->assertIsArray($result);
        $this->assertSame('vestido', $result['inbound_profile']['tipo_prenda'] ?? null);
        $this->assertArrayHasKey('caption', $result);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'gemini-2.5-flash'));
    }
}
