<?php

namespace App\Jobs;

use App\Actions\GenerarRespuestaAgente;
use App\Exceptions\GeminiQuotaExceededException;
use App\Models\Message;
use App\Services\Media\AudioTranscriber;
use App\Services\Media\DescargadorMediaWhatsapp;
use App\Services\Media\ImageAnalyzer;
use App\Services\Vision\CatalogoImageMatcher;
use App\Support\MessageBroadcaster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMediaThenRespondJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 300;

    public function __construct(public int $messageId) {}

    public function uniqueId(): string
    {
        return 'process-media-'.$this->messageId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 45, 60, 90, 120];
    }

    public function handle(
        AudioTranscriber $transcriber,
        ImageAnalyzer $analyzer,
        CatalogoImageMatcher $matcher,
        GenerarRespuestaAgente $agente,
        DescargadorMediaWhatsapp $descargadorMedia,
    ): void {
        $message = Message::find($this->messageId);
        if (! $message) {
            return;
        }

        $meta = is_array($message->metadata) ? $message->metadata : [];
        $type = $meta['type'] ?? 'text';
        $enriquecido = false;

        try {
            if ($type === 'audio') {
                $enriquecido = $this->procesarAudio($message, $meta, $transcriber, $descargadorMedia);
            } elseif ($type === 'image') {
                $enriquecido = $this->procesarImagen($message, $meta, $analyzer, $matcher, $descargadorMedia);
            }
        } catch (GeminiQuotaExceededException $e) {
            Log::warning('ProcessMediaThenRespondJob: cuota Gemini excedida, reintentando', [
                'msg' => $message->id,
                'retry_after' => $e->retryAfterSeconds,
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($e->retryAfterSeconds);

                return;
            }

            $this->marcarAnalisisFallido($message, $type, 'Cuota API agotada después de '.$this->tries.' intentos');
        } catch (\Throwable $e) {
            Log::error('ProcessMediaThenRespondJob error', [
                'error' => $e->getMessage(),
                'msg' => $message->id,
                'type' => $type,
            ]);

            $this->marcarAnalisisFallido($message, $type, $e->getMessage());
        }

        $message = $message->fresh();
        if ($message === null) {
            return;
        }

        if (! $enriquecido && in_array($type, ['audio', 'image'], true)) {
            if ($this->debeReintentarDescarga($message, $type)) {
                Log::warning('ProcessMediaThenRespondJob: media no descargada, reintentando', [
                    'msg' => $message->id,
                    'type' => $type,
                    'attempt' => $this->attempts(),
                ]);
                $this->release(45);

                return;
            }

            if ($this->faltaArchivoLocal($message, $type)) {
                $this->marcarAnalisisFallido(
                    $message,
                    $type,
                    'No se pudo descargar el archivo de WhatsApp. Renueva WHATSAPP_ACCESS_TOKEN en .env.',
                );
                $message = $message->fresh();
            }
        }

        if ($message === null) {
            return;
        }

        if ($enriquecido) {
            MessageBroadcaster::broadcast($message, 'ProcessMediaThenRespondJob');
        }

        if (! $agente->debeResponder($message)) {
            Log::info('ProcessMediaThenRespondJob: IA no responde a este mensaje', [
                'msg' => $this->messageId,
                'phone' => $message->phone_number,
                'type' => $type,
            ]);

            return;
        }

        GenerarRespuestaAgenteJob::dispatch($message);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function procesarAudio(
        Message $message,
        array $meta,
        AudioTranscriber $transcriber,
        DescargadorMediaWhatsapp $descargadorMedia,
    ): bool {
        $audioUrl = $this->resolverRutaMedia($message, $meta, 'audio', $descargadorMedia);
        if ($audioUrl === null) {
            return false;
        }

        $text = $transcriber->transcribeFromUrl($this->resolveAbsoluteUrl($audioUrl), 'es');
        if (! is_string($text) || $text === '' || $text === '[inaudible]') {
            $meta = is_array($message->metadata) ? $message->metadata : [];
            $meta['transcript_failed'] = true;
            $message->metadata = $meta;
            $message->save();

            Log::warning('ProcessMediaThenRespondJob: transcripción vacía o inaudible', [
                'msg' => $message->id,
            ]);

            return true;
        }

        $meta = is_array($message->metadata) ? $message->metadata : [];
        $meta['transcript'] = $text;
        $meta['transcript_provider'] = 'gemini';
        unset($meta['transcript_failed'], $meta['transcript_error'], $meta['media_download_failed']);
        $message->metadata = $meta;
        $message->content = $text;
        $message->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function procesarImagen(
        Message $message,
        array $meta,
        ImageAnalyzer $analyzer,
        CatalogoImageMatcher $matcher,
        DescargadorMediaWhatsapp $descargadorMedia,
    ): bool {
        $imgUrl = $this->resolverRutaMedia($message, $meta, 'image', $descargadorMedia);
        if ($imgUrl === null) {
            return false;
        }

        $meta = is_array($message->metadata) ? $message->metadata : [];
        $captionCliente = $this->extraerCaptionWhatsapp($meta);

        $res = $analyzer->analyzeUrl($this->resolveAbsoluteUrl($imgUrl), [
            'caption_cliente' => $captionCliente,
        ]);
        if (! is_array($res) || empty($res['caption'])) {
            Log::warning('ProcessMediaThenRespondJob: análisis de imagen vacío', [
                'msg' => $message->id,
                'url' => $imgUrl,
            ]);

            $meta['vision_failed'] = true;
            $meta['vision_error'] = 'No se pudo analizar la imagen';
            $message->metadata = $meta;
            $message->save();

            return true;
        }

        $inboundProfile = is_array($res['inbound_profile'] ?? null) ? $res['inbound_profile'] : [];
        $matchResult = $matcher->match($inboundProfile);

        $meta['vision'] = [
            'caption' => $res['caption'],
            'inbound_profile' => $inboundProfile,
            'matches' => $matchResult['matches'],
            'mejor_match' => $matchResult['mejor_match'],
            'confianza_final' => $matchResult['confianza_final'],
            'nivel' => $matchResult['nivel'],
        ];
        if ($captionCliente !== '') {
            $meta['vision']['caption_cliente'] = $captionCliente;
        }
        $meta['vision_provider'] = 'gemini';
        unset($meta['media_download_failed']);
        $message->metadata = $meta;

        $contenido = $captionCliente !== ''
            ? '[Imagen — clienta: '.$captionCliente.'] '.$res['caption']
            : '[Imagen: '.$res['caption'].']';
        $message->content = $contenido;
        $message->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function resolverRutaMedia(
        Message $message,
        array $meta,
        string $mediaKind,
        DescargadorMediaWhatsapp $descargadorMedia,
    ): ?string {
        $candidatos = match ($mediaKind) {
            'audio' => [$meta['local_url'] ?? null, $meta['media_url'] ?? null],
            default => [$meta['local_url'] ?? null, $meta['image_url'] ?? null, $meta['media_url'] ?? null],
        };

        foreach ($candidatos as $url) {
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return $this->intentarDescargarDesdeWhatsapp($message, $mediaKind, $descargadorMedia);
    }

    private function intentarDescargarDesdeWhatsapp(
        Message $message,
        string $mediaKind,
        DescargadorMediaWhatsapp $descargadorMedia,
    ): ?string {
        $meta = is_array($message->metadata) ? $message->metadata : [];
        $raw = $meta['whatsapp_raw'] ?? null;
        if (! is_array($raw)) {
            return null;
        }

        $waId = (string) ($message->message_id ?? 'unknown');
        $descargado = $descargadorMedia->descargar($waId, $mediaKind, $raw);
        if ($descargado === null) {
            $meta['media_download_failed'] = true;
            $meta['media_download_error'] = 'Descarga Meta fallida (revisa WHATSAPP_ACCESS_TOKEN)';
            $message->metadata = $meta;
            $message->save();

            return null;
        }

        $meta['local_url'] = $descargado['local_url'];
        $meta['media_url'] = $descargado['url'];
        if (! empty($descargado['mime'])) {
            $meta['mime_type'] = $descargado['mime'];
        }
        if ($mediaKind === 'image') {
            $meta['image_url'] = $descargado['local_url'];
        }
        unset($meta['media_download_failed'], $meta['media_download_error']);
        $message->metadata = $meta;
        $message->save();

        Log::info('ProcessMediaThenRespondJob: media descargada en reintento', [
            'msg' => $message->id,
            'type' => $mediaKind,
            'local_url' => $descargado['local_url'],
        ]);

        return $descargado['local_url'];
    }

    private function debeReintentarDescarga(Message $message, string $type): bool
    {
        if ($this->attempts() >= $this->tries) {
            return false;
        }

        return $this->faltaArchivoLocal($message, $type);
    }

    private function faltaArchivoLocal(Message $message, string $type): bool
    {
        $meta = is_array($message->metadata) ? $message->metadata : [];

        if (is_string($meta['local_url'] ?? null) && $meta['local_url'] !== '') {
            return false;
        }

        if ($type === 'audio') {
            return ! is_string($meta['transcript'] ?? null) || $meta['transcript'] === '';
        }

        return empty($meta['vision']['caption'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function extraerCaptionWhatsapp(array $meta): string
    {
        $raw = $meta['whatsapp_raw'] ?? null;
        if (! is_array($raw)) {
            return '';
        }

        foreach (['image', 'sticker'] as $kind) {
            $block = $raw[$kind] ?? null;
            if (is_array($block) && is_string($block['caption'] ?? null)) {
                $caption = trim($block['caption']);
                if ($caption !== '' && ! str_starts_with($caption, '📷')) {
                    return $caption;
                }
            }
        }

        return '';
    }

    private function marcarAnalisisFallido(Message $message, string $type, string $error): void
    {
        $meta = is_array($message->metadata) ? $message->metadata : [];

        if ($type === 'image') {
            $meta['vision_failed'] = true;
            $meta['vision_error'] = $error;
        } elseif ($type === 'audio') {
            $meta['transcript_failed'] = true;
            $meta['transcript_error'] = $error;
        }

        $message->metadata = $meta;
        $message->save();

        Log::info('ProcessMediaThenRespondJob: análisis marcado como fallido', [
            'msg' => $message->id,
            'type' => $type,
            'error' => $error,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('ProcessMediaThenRespondJob: failed permanently', [
            'message_id' => $this->messageId,
            'error' => $exception?->getMessage(),
            'class' => $exception ? get_class($exception) : null,
        ]);
    }

    private function resolveAbsoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $publicBase = rtrim((string) config('app.public_url', config('app.url')), '/');
            $appBase = rtrim((string) config('app.url'), '/');

            if ($appBase !== '' && $publicBase !== $appBase && str_starts_with($url, $appBase)) {
                return $publicBase.substr($url, strlen($appBase));
            }

            return $url;
        }

        $base = rtrim((string) config('app.public_url', config('app.url')), '/');

        return $base.'/'.ltrim($url, '/');
    }
}
