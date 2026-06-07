<?php

namespace App\Support;

class MensajesEmpresaDefaults
{
    public static function comprobanteRecibido(): string
    {
        return 'Hermosa, recibimos tu comprobante. En breve lo validamos y te confirmamos tu pedido.';
    }

    public static function comprobanteFueraHorario(): string
    {
        return 'Gracias hermosa, recibimos tu comprobante. Tu pedido quedó registrado; lo confirmamos a primera hora y te avisamos por aquí.';
    }

    public static function pedidoConfirmado(): string
    {
        return 'Listo {nombre}, tu pedido de {producto} quedó confirmado. Total S/ {total}. Pronto coordinamos el envío.';
    }

    public static function pedidoEnviado(): string
    {
        return '¡Buenas noticias {nombre}! Tu pedido de {producto} ya salió hacia {distrito}. Te avisamos cuando esté cerca.';
    }

    public static function pedidoEntregado(): string
    {
        return 'Gracias por tu preferencia, {nombre}. Esperamos verte pronto. Si necesitas algo más, escríbenos.';
    }

    public static function esperaLinkTarjeta(): string
    {
        return 'Te comunico con el equipo para pasarte el link de pago, hermosa. Un momento por favor.';
    }

    public static function recordatorio3Min(): string
    {
        return 'Por favor confirma tu pedido';
    }

    public static function recordatorio15Min(): string
    {
        return 'Gracias por tu interés, nos escribes cuando gustes';
    }
}
