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
5. Si es un comprobante de pago o voucher, marca encontrado=false y tipo_mensaje=comprobante. Extrae método de pago, monto y nombre del titular si están visibles.

FORMATO JSON OBLIGATORIO (sin markdown, sin comentarios):
{
  "encontrado": true|false,
  "id_producto": X (id del producto si fue encontrado, o null),
  "nombre_vestido": "Nombre exacto del catálogo o null",
  "color": "Color exacto del catálogo o null",
  "tipo_mensaje": "producto|comprobante|otro",
  "metodo_pago": "Yape|Plin|BCP|BBVA|Interbank|etc o null si no aplica",
  "monto": "monto numérico en texto o null",
  "nombre_titular": "nombre de quien recibe o envía el pago o null"
}
PROMPT;
    }

    /**
     * Prompt Universal para extraer las 12 dimensiones anatómicas de cualquier prenda.
     * Funciona idénticamente para fotos de catálogo de estudio o selfies en el espejo (WhatsApp/TikTok).
     */
    public static function promptUniversalPrenda(): string
    {
        return <<<'PROMPT'
Eres un diseñador de alta costura y un experto analista de moda. Tu único objetivo es describir con precisión absoluta la PRENDA PRINCIPAL que viste la persona en la foto.

INSTRUCCIONES ANTI-RUIDO (CRÍTICAS):
1. Ignora absolutamente todo lo que no sea la prenda principal.
2. Finge que la prenda está sobre un maniquí invisible flotando sobre un fondo blanco.
3. Ignora el teléfono, el espejo, los zapatos, las carteras, accesorios adicionales, la cara de la modelo, las paredes y la iluminación.
4. Ignora CUALQUIER texto superpuesto, logo, o interfaz de usuario que aparezca en la imagen (como corazones, íconos, o logos de TikTok/Instagram).

Descompón visualmente la prenda en las siguientes 12 dimensiones y devuelve el análisis en el formato JSON obligatorio.

FORMATO JSON OBLIGATORIO (sin markdown, sin bloques de código ```json, sin comentarios):
{
  "es_prenda": true|false,
  "tipo_prenda": "vestido|enterizo|blusa|pantalón|falda|conjunto|otro",
  "largo_prenda": "maxi|midi|corto|crop|regular",
  "manga": "larga|tres cuartos|corta|cero|tirantes|asimétrica",
  "cuello": "alto|tortuga|redondo|en v|cuadrado|camisero|off-shoulder|asimétrico",
  "ajuste_fit": "ajustado|sirena|holgado|recto|acampanado|oversize",
  "patron": "liso|sólido|floral|rayas verticales|rayas horizontales|ondas|geométrico|animal print|cuadros",
  "textura_tejido": "liso|acanalado|calado|crochet|peluche|jean|encaje",
  "distribucion_color": "entero|degradado|multicolor|detalles en contraste",
  "color_principal": "Color dominante (ej. Rojo, Azul, Marrón, Blanco, Negro, etc)",
  "acabados_bordes": ["festoneados", "ondas", "puños contraste", "dobladillo volantes", "ninguno"],
  "detalles_clave": ["botones", "cinturón", "aberturas", "cierres", "cuello con doblez", "bolsillos", "ninguno"],
  "descripcion_vectorial": "Oración fluida que concatena todo. Ej: Vestido maxi ajustado de tejido acanalado, sin mangas, cuello alto, con patrón de rayas en ondas horizontales en tonos rojo y rosado degradado."
}
PROMPT;
    }

    /**
     * Prompt para verificar si es comprobante de pago
     */
    public static function promptDetectorComprobante(): string
    {
        return <<<'PROMPT'
Detecta si esta imagen es un COMPROBANTE DE PAGO.

Responde JSON:
{
  "es_comprobante": true|false
}
PROMPT;
    }
}
