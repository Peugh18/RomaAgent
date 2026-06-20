<?php

namespace Tests\Unit;

use App\Models\CompanySetting;
use App\Services\Media\AudioTranscriber;
use App\Services\Media\CargadorBytesMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AudioTranscriberTest extends TestCase
{
    use RefreshDatabase;

    public function test_transcribe_from_local_storage_using_gemini(): void
    {
        $relativePath = 'inbound-media/test.ogg';
        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'fake-ogg-bytes');

        CompanySetting::factory()->withIaEnabled()->create([
            'agente_ia_modelo' => 'gemini-2.5-flash-lite',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Quiero el vestido Aurora en talla M']]]],
                ],
            ]),
        ]);

        $transcriber = app(AudioTranscriber::class);
        $texto = $transcriber->transcribeFromUrl('/storage/'.$relativePath, 'es');

        @unlink($fullPath);

        $this->assertSame('Quiero el vestido Aurora en talla M', $texto);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }

    public function test_cargador_lee_archivo_local_sin_http(): void
    {
        $relativePath = 'inbound-media/audio-local.ogg';
        $fullPath = storage_path('app/public/'.$relativePath);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        file_put_contents($fullPath, 'bytes-locales');

        $cargador = app(CargadorBytesMedia::class);
        $resultado = $cargador->desdeUrl('/storage/'.$relativePath);

        @unlink($fullPath);

        $this->assertNotNull($resultado);
        $this->assertSame('bytes-locales', $resultado['bytes']);
    }
}
