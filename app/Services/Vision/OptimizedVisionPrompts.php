<?php

namespace App\Services\Vision;

/**
 * Prompts optimizados para análisis de imágenes de moda
 * Diseñados específicamente para catálogo de vestidos y prendas femeninas
 */
class OptimizedVisionPrompts
{
    /**
     * Prompt mejorado para análisis de imágenes de clientes
     */
    public static function promptAnalisisCliente(string $captionCliente = '', string $inventarioActivo = ''): string
    {
        $captionPart = $captionCliente ? "Caption clienta: '{$captionCliente}'." : '';
        $inventarioPart = $inventarioActivo ? "INVENTARIO ACTIVO DISPONIBLE:\n{$inventarioActivo}" : 'No hay inventario activo especificado.';

        return <<<PROMPT
Eres un experto en moda y ventas de ropa femenina. Analiza esta imagen para un sistema de ventas por WhatsApp.

{$captionPart}

{$inventarioPart}

INSTRUCCIONES CRÍTICAS:
1. Revisa detenidamente la imagen enviada por la clienta (puede ser captura de TikTok, foto, etc).
2. Compara la prenda de la imagen con la lista de "INVENTARIO ACTIVO DISPONIBLE".
3. Si la prenda de la imagen COINCIDE claramente con alguno de los vestidos en stock activo, marca encontrado=true y extrae los datos.
4. Si NO coincide, marca encontrado=false.
5. Si es un comprobante de pago, marca encontrado=false y tipo_mensaje=comprobante.

FORMATO JSON OBLIGATORIO (sin markdown, sin comentarios):
{
  "encontrado": true|false,
  "id_producto": X (id del producto si fue encontrado, o null),
  "nombre_vestido": "Nombre exacto del catálogo o null",
  "color": "Color exacto del catálogo o null",
  "tipo_mensaje": "producto|comprobante|otro"
}
PROMPT;
    }

    public static function promptExtractorCaracteristicasPrenda(): string
    {
        return <<<PROMPT
Eres un experto analista de moda. Tu único objetivo es describir la PRENDA PRINCIPAL que viste la persona en la foto.
Ignora por completo el fondo, la cara de la persona, los maniquíes de atrás, las luces o cualquier otra cosa.

Describe la prenda de manera muy breve enfocándote solo en lo visual. 
Debe incluir: Tipo de prenda (ej. vestido, blusa), Color dominante (ej. rojo, camel, lila) y Patrón o Diseño (ej. zig-zag, liso, floral).

FORMATO JSON OBLIGATORIO (sin markdown, sin comentarios):
{
  "es_prenda": true|false,
  "tipo_prenda": "vestido|blusa|pantalón|etc",
  "color": "color dominante",
  "patron": "diseño o textura visible",
  "descripcion_vectorial": "frase corta como: Vestido de mujer, patrón zig-zag, color rojo"
}
PROMPT;
    }

    /**
     * Prompt para generar embeddings de catálogo
     */
    public static function promptEmbeddingCatalogo(string $productName, string $color): string
    {
        return "Prenda de moda femenina: {$productName} color {$color}. Vestido elegante diseño único.";
    }

    /**
     * Prompt para análisis detallado de catálogo
     */
    public static function promptAnalisisCatalogo(string $productName, string $color): string
    {
        return <<<PROMPT
Analiza esta foto profesional de catálogo para "{$productName}" color "{$color}".

Enfócate EXCLUSIVAMENTE en la prenda, no en la modelo ni fondo.

CARACTERÍSTICAS A ANALIZAR:
- TIPO PRENDA: vestido/blusa/pantalón/falda/chompa/accesorio
- MATERIAL: textura y composición visible
- CORTE/SILUETA: forma y longitud
- DISEÑO: estampado, texturas, detalles decorativos
- ESTILO: elegante, casual, deportivo, festivo, bohemio

JSON requerido:
{
  "producto": {
    "tipo_prenda": "vestido|blusa|pantalón|falda|chompa|accesorio|otro",
    "material_aparente": "material visible",
    "silueta": "corta|midi|larga|crop|oversized|ajustada|recta",
    "patron": "liso|estampado|rayas|lunares|geométrico|tie-dye|otro",
    "estilo": "elegante|casual|deportivo|festivo|bohemio|moderno",
    "detalles": ["detalle específico visible"],
    "keywords": ["palabras", "clave", "búsqueda", "producto"]
  },
  "color": {
    "color_canonical": "{$color}",
    "colores_dominantes": ["color1", "color2"],
    "aliases": ["sinónimo1", "sinónimo2"],
    "tono": "claro|medio|oscuro"
  }
}
PROMPT;
    }

    /**
     * Prompt para análisis de color específico
     */
    public static function promptAnalisisColor(string $productName, string $colorRegistrado): string
    {
        return <<<PROMPT
Analiza el COLOR DOMINANTE de esta variante de "{$productName}" (color registrado: "{$colorRegistrado}").

Ignora iluminación, sombras, fondo. Enfócate solo en el color real de la prenda.

JSON requerido:
{
  "color_canonical": "{$colorRegistrado}",
  "colores_dominantes": ["nombre color principal", "secundario si aplica"],
  "aliases": ["sinónimos comunes en español", "variaciones tono"],
  "tono": "claro|medio|oscuro",
  "intensidad": "pálido|normal|vibrante|intenso"
}

Nombres de color estándar: blanco, negro, gris, beige, camel, marrón, rojo, rosado, naranja, amarillo, verde, azul, morado, lila, turquesa, coral, fucsia, mostaza, terracota, azul rey, azul cielo, verde menta, lila pálido, etc.
PROMPT;
    }

    /**
     * Prompt para verificar si es comprobante de pago
     */
    public static function promptDetectorComprobante(): string
    {
        return <<<'PROMPT'
Detecta si esta imagen es un COMPROBANTE DE PAGO.

Busca indicios de Yape, Plin, BCP, BBVA, Scotiabank, Interbank, transferencias, operaciones bancarias.

JSON:
{
  "es_comprobante": boolean,
  "tipo_comprobante": "yape|plin|transferencia|otro|null",
  "banco_visible": "nombre banco o null",
  "monto_visible": "monto o null",
  "confianza": 0.95
}

Si NO es comprobante, es_comprobante=false y demás campos null.
PROMPT;
    }
}
