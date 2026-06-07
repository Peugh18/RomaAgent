<?php

namespace App\Jobs;

use App\Actions\GenerarRespuestaAgente;
use App\Models\Message;
use App\Services\Media\AudioTranscriber;
use App\Services\Media\ImageAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMediaThenRespondJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $messageId) {}

    public function handle(AudioTranscriber $transcriber, ImageAnalyzer $analyzer, GenerarRespuestaAgente $agente): void
    {
        $message = Message::find($this->messageId);
        if (! $message) {
            return;
        }

        $meta = is_array($message->metadata) ? $message->metadata : [];
        $type = $meta['type'] ?? 'text';

        try {
            if ($type === 'audio') {
                $audioUrl = $meta['local_url'] ?? $meta['media_url'] ?? null;
                if (is_string($audioUrl) && $audioUrl !== '') {
                    $text = $transcriber->transcribeFromUrl($this->resolveAbsoluteUrl($audioUrl), 'es');
                    if (is_string($text) && $text !== '' && $text !== '[inaudible]') {
                        $meta['transcript'] = $text;
                        $meta['transcript_provider'] = 'gemini';
                        $message->metadata = $meta;
                        $message->content = $text;
                        $message->save();
                    } else {
                        $meta['transcript_failed'] = true;
                        $message->metadata = $meta;
                        $message->save();
                        Log::warning('ProcessMediaThenRespondJob: transcripción vacía o inaudible', [
                            'msg' => $message->id,
                        ]);
                    }
                }
            } elseif ($type === 'image') {
                $imgUrl = $meta['local_url'] ?? $meta['image_url'] ?? $meta['media_url'] ?? null;
                if (is_string($imgUrl) && $imgUrl !== '') {
                    $res = $analyzer->analyzeUrl($this->resolveAbsoluteUrl($imgUrl));
                    if (is_array($res) && ! empty($res['caption'])) {
                        $meta['vision'] = $res;
                        $meta['vision_provider'] = 'gemini';
                        $message->metadata = $meta;
                        // Enriquecer contenido con breve caption
                        $message->content = '[Imagen: '.$res['caption'].']';
                        $message->save();
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('ProcessMediaThenRespondJob error', ['error' => $e->getMessage(), 'msg' => $message->id]);
        }

        // Generar respuesta del agente con el mensaje ya enriquecido
        $message = $message->fresh();

        if ($message === null || ! $agente->debeResponder($message)) {
            Log::info('ProcessMediaThenRespondJob: IA no responde a este mensaje', [
                'msg' => $this->messageId,
                'phone' => $message?->phone_number,
            ]);

            return;
        }

        $agente->ejecutar($message);
    }

    private function resolveAbsoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $base = rtrim((string) config('app.public_url', config('app.url')), '/');

        return $base.'/'.ltrim($url, '/');
    }
}
