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
    public static function promptAnalisisCliente(string $captionCliente = ''): string
    {
        $captionPart = $captionCliente ? "Caption clienta: '{$captionCliente}'." : '';

        return <<<PROMPT
Eres un experto en moda y ventas de ropa femenina. Analiza esta imagen para un sistema de ventas por WhatsApp.

{$captionPart}

Contexto: Clientas envían fotos de prendas que quieren comprar (capturas de redes sociales, fotos de productos, etc).

INSTRUCCIONES CRÍTICAS:
1. Si es COMPROBANTE DE PAGO (Yape, Plin, transferencia, BCP) -> tipo=comprobante
2. Si es PRENDA DE VESTIR o CAPTURA con prenda visible -> tipo=producto
3. Si es CAPTURA DE PANTALLA (TikTok, Instagram, Facebook) -> es_captura_redes=true
4. Ignora UI, marcas de agua, textos superpuestos, logos de redes sociales

ANÁLISIS DE PRENDA (solo si tipo=producto):
- Identificar TIPO exacto: vestido, blusa, pantalón, falda, chompa, accesorio, otro
- MATERIAL visible: punto, algodón, lino, poliéster, denim, seda, lana, mezclilla, otro
- SILUETA/TALLA: corta, midi, larga, crop, oversized, ajustada, recta
- DISEÑO/PATRÓN: liso, estampado floral, rayas, lunares, geométrico, tie-dye, otro
- COLORES: identificar color dominante y secundarios (usar nombres estándar español)
- DETALLES específicos: escote, mangas, cinturón, bolsillos, transparencias, bordados

FORMATO JSON OBLIGATORIO (sin markdown, sin comentarios):
{
  "tipo": "producto|comprobante|otro",
  "es_comprobante": boolean,
  "es_captura_redes": boolean,
  "tipo_prenda": "vestido|blusa|pantalón|falda|chompa|accesorio|otro|null",
  "material_aparente": "punto|algodón|lino|poliéster|denim|seda|lana|mezclilla|otro|null",
  "silueta": "corta|midi|larga|crop|oversized|ajustada|recta|null",
  "patron": "liso|estampado|rayas|lunares|geométrico|tie-dye|otro|null",
  "color_dominante": "color principal en español",
  "colores_dominantes": ["color1", "color2"],
  "descripcion_prenda": "descripción detallada 1-2 frases",
  "detalles_visibles": ["detalle1", "detalle2"],
  "texto_visible": "texto OCR relevante o vacío",
  "caption_cliente": "{$captionCliente}",
  "confianza_analisis": 0.95
}

EJEMPLOS:
- Vestido azul liso manga larga -> tipo=producto, tipo_prenda=vestido, color_dominante=azul
- Captura TikTok vestido rojo -> tipo=producto, es_captura_redes=true
- Foto comprobante Yape -> tipo=comprobante, es_comprobante=true
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
