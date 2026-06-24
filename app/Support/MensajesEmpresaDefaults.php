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

    public static function recordatorioMotorizado(): string
    {
        return "🛵 Tu pedido ya está en camino hacia ti.\n⏱ El tiempo estimado de llegada es entre 1 a 3 horas (referencial, puede variar).\n💰 El costo del envío se paga al recibir el pedido.\n¡Pronto estarás con tu pedido! 🎉";
    }

    public static function recordatorioShalom(): string
    {
        return "📦 Tu pedido fue enviado por Shalom Courier.\n⏱ El tiempo estimado de llegada es 2 a 4 días hábiles (referencial, puede variar).\n💰 El costo del envío se paga al recoger en la agencia.\n¡Gracias por tu preferencia! 🌟";
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
        return "Te comunico con un asesor para generar tu link de pago seguro.\n\nPor favor, envíame los siguientes datos:\n- Nombre completo:\n- Correo electrónico:\n- Número de Celular:\n- Monto a pagar:\n\nUna vez realizado el pago, envíame tu comprobante por aquí para confirmar tu pedido.";
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
