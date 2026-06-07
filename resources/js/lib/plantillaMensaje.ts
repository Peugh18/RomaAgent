export const PLANTILLA_VARIABLES = [
    { key: '{nombre}', label: 'Nombre clienta', ejemplo: 'Mariela' },
    { key: '{producto}', label: 'Producto', ejemplo: 'Vestido Mariela' },
    { key: '{color}', label: 'Color', ejemplo: 'Lila' },
    { key: '{total}', label: 'Total', ejemplo: '190.00' },
    { key: '{distrito}', label: 'Distrito', ejemplo: 'Ate' },
    { key: '{metodo_pago}', label: 'Método pago', ejemplo: 'yape' },
] as const;

export const PLANTILLA_EJEMPLO = {
    nombre: 'Mariela',
    producto: 'Vestido Mariela',
    color: 'Lila',
    total: '190.00',
    distrito: 'Ate',
    metodo_pago: 'yape',
};

export function tieneFormatoPlantillaIncorrecto(texto: string): boolean {
    return /\{"[^"]+"\}/.test(texto);
}

export function previsualizarPlantilla(texto: string): string {
    if (!texto.trim() || tieneFormatoPlantillaIncorrecto(texto)) {
        return '';
    }

    return texto
        .replaceAll('{nombre}', PLANTILLA_EJEMPLO.nombre)
        .replaceAll('{producto}', PLANTILLA_EJEMPLO.producto)
        .replaceAll('{color}', PLANTILLA_EJEMPLO.color)
        .replaceAll('{total}', PLANTILLA_EJEMPLO.total)
        .replaceAll('{distrito}', PLANTILLA_EJEMPLO.distrito)
        .replaceAll('{metodo_pago}', PLANTILLA_EJEMPLO.metodo_pago);
}

export const PLANTILLA_PEDIDO_CONFIRMADO_DEFECTO =
    'Listo {nombre}, tu pedido de {producto} en {color} quedó confirmado. Total S/ {total}. Pronto coordinamos el envío.';
