<?php

namespace App\Support;

use App\Models\Message;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ComprobantePagoMensaje
{
    public static function esImagenEntrante(Message $mensaje): bool
    {
        if ($mensaje->direction !== 'incoming') {
            return false;
        }

        $meta = is_array($mensaje->metadata) ? $mensaje->metadata : [];
        $tipo = (string) ($meta['type'] ?? $meta['whatsapp_message_type'] ?? '');

        if (in_array($tipo, ['image', 'sticker'], true)) {
            return true;
        }

        return self::urlDesdeMensaje($mensaje) !== null;
    }

    public static function marcar(Message $mensaje, Sale $sale): void
    {
        $meta = is_array($mensaje->metadata) ? $mensaje->metadata : [];
        $mensaje->update([
            'metadata' => array_merge($meta, [
                'is_payment_comprobante' => true,
                'sale_id' => $sale->id,
            ]),
        ]);

        $agentMeta = is_array($sale->agent_metadata) ? $sale->agent_metadata : [];
        $sale->update([
            'agent_metadata' => array_merge($agentMeta, [
                'comprobante_message_id' => $mensaje->id,
            ]),
        ]);
    }

    public static function urlDesdeMensaje(?Message $mensaje): ?string
    {
        if ($mensaje === null) {
            return null;
        }

        $meta = is_array($mensaje->metadata) ? $mensaje->metadata : [];

        foreach (['image_url', 'local_url', 'media_url'] as $key) {
            $url = $meta[$key] ?? null;

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Message>  $mensajesPorId
     * @param  Collection<int, Collection<int, Message>>  $etiquetadosPorVenta
     * @param  Collection<string, Collection<int, Message>>  $imagenesEntradaPorTelefono
     */
    public static function resolverParaPedido(
        Sale $sale,
        Collection $mensajesPorId,
        Collection $etiquetadosPorVenta,
        Collection $imagenesEntradaPorTelefono,
    ): ?string {
        $agentMeta = is_array($sale->agent_metadata) ? $sale->agent_metadata : [];
        $messageId = $agentMeta['comprobante_message_id'] ?? null;

        if (is_int($messageId) || (is_string($messageId) && ctype_digit($messageId))) {
            $url = self::urlDesdeMensaje($mensajesPorId->get((int) $messageId));
            if ($url !== null) {
                return $url;
            }
        }

        $etiquetado = $etiquetadosPorVenta->get($sale->id)?->first();
        $url = self::urlDesdeMensaje($etiquetado);
        if ($url !== null) {
            return $url;
        }

        if ($sale->payment_received_at === null) {
            return null;
        }

        $phoneImages = $imagenesEntradaPorTelefono->get($sale->phone_number);
        if ($phoneImages === null || $phoneImages->isEmpty()) {
            return null;
        }

        $corte = Carbon::parse($sale->payment_received_at)->addMinutes(2);

        /** @var Message|null $mensajePago */
        $mensajePago = $phoneImages
            ->filter(fn (Message $mensaje): bool => $mensaje->created_at !== null && $mensaje->created_at <= $corte)
            ->sortByDesc(fn (Message $mensaje) => $mensaje->created_at?->timestamp ?? 0)
            ->first();

        return self::urlDesdeMensaje($mensajePago);
    }
}
