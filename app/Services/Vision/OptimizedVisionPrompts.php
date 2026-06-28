<?php

namespace App\Services\Vision;

/**
 * Prompts optimizados para análisis de imágenes de moda.
 * Diseñados específicamente para catálogo de vestidos y prendas femeninas.
 *
 * IMPORTANTE: El prompt universal es el ÚNICO prompt de extracción de características.
 * Se usa idénticamente para fotos de catálogo (admin) y fotos de clientas (WhatsApp/TikTok).
 * También detecta comprobantes de pago en la misma llamada (ahorro de tokens y tiempo).
 */
class OptimizedVisionPrompts
{
    /**
     * Prompt Universal V4 "Radiografía Total" con Zonas Anatómicas + Detección de Comprobante.
     *
     * Este prompt se usa IDÉNTICAMENTE para:
     * - Fotos de catálogo subidas por el administrador.
     * - Fotos enviadas por clientas vía WhatsApp (capturas de TikTok, selfies, etc).
     *
     * Incluye detección de comprobante para evitar una segunda llamada a Gemini.
     */
    public static function promptUniversalPrenda(): string
    {
        return <<<'PROMPT'
Eres un diseñador de alta costura y un experto analista de moda con 20 años de experiencia. Tu objetivo es analizar la imagen y determinar si contiene una PRENDA DE VESTIR o un COMPROBANTE DE PAGO.

PASO 1 - CLASIFICACIÓN RÁPIDA:
Antes de analizar en detalle, determina qué tipo de imagen es:
- Si la imagen es un COMPROBANTE DE PAGO (captura de transferencia, voucher, recibo, constancia de Yape/Plin/BCP/etc), responde SOLO: {"es_comprobante": true, "es_prenda": false}
- Si la imagen contiene una PRENDA DE VESTIR, continúa con el análisis completo abajo.

PROTOCOLO DE ENFOQUE (OBLIGATORIO - ejecutar ANTES de analizar):
1. Localiza a la PERSONA REAL en primer plano (no maniquíes, no percheros, no fondo).
2. Si hay una persona viva vistiendo una prenda, analiza EXCLUSIVAMENTE esa prenda.
3. Si solo hay maniquíes, analiza el que está más centrado o más destacado en la imagen.
4. Aísla mentalmente la prenda: imagina que está flotando sola sobre un fondo blanco puro.

INSTRUCCIONES ANTI-RUIDO (CRÍTICAS):
1. Ignora absolutamente todo lo que no sea la prenda principal de la persona en primer plano.
2. Ignora el teléfono, el espejo, los zapatos, accesorios, joyería, la cara, el cabello.
3. Si hay prendas en el fondo, IGNÓRALAS POR COMPLETO.

REGLAS DE PRECISIÓN ESTRUCTURAL (PENALIZACIÓN SI FALLAS):
1. TIRANTES vs MANGA CERO: Si la prenda se sujeta a los hombros mediante tiras (delgadas o gruesas) y deja al descubierto los hombros y clavículas, es OBLIGATORIAMENTE "tirantes", NUNCA "cero". "Cero" significa que la tela principal llega hasta el cuello pero no tiene mangas (ej: cuello halter alto).
2. CUELLO CERRADO vs ABIERTO: Si se ve la piel del pecho por debajo de las clavículas o hay escote, la apertura es OBLIGATORIAMENTE "abierto" o "profundo". Solo es "cerrado" si la tela cubre por completo hasta la base del cuello sin mostrar piel.
3. CONJUNTOS vs ENTERIZOS: Detecta si visualmente parece un "conjunto" (top y falda separados o con franjas sólidas que los separan claramente en la cintura).

ANÁLISIS POR ZONAS ANATÓMICAS:
Analiza la prenda dividiéndola mentalmente en zonas.

FORMATO JSON OBLIGATORIO (sin markdown, sin bloques de código, sin comentarios):
{
  "es_comprobante": false,
  "es_prenda": true|false,
  "tipo_prenda": "vestido|enterizo|blusa|pantalón|falda|conjunto|chompa|cardigan|otro",
  "analisis_ultra_detallado": {
    "descripcion_patron": "Describe el patrón con extrema precisión. (Ej: Ondas horizontales, alternando una línea gruesa crema, una fina negra y una gruesa beige. Las ondas tienen picos suaves).",
    "descripcion_textura": "Describe la tela con lupa. (Ej: Tejido de punto grueso, elástico, con canalé fino vertical, acabado mate).",
    "detalles_micro": "Menciona cualquier detalle minúsculo. (Ej: Ribetes en contraste, tipo de costura visible, textura de cinturón, forro, uniones de tela)."
  },
  "zona_cuello": {
    "tipo": "alto|tortuga|redondo|en v|cuadrado|camisero|off-shoulder|halter|strapless|asimétrico|ninguno",
    "apertura": "cerrado|abierto|profundo|moderado",
    "detalle": "con solapa|con lazo|con botones|liso|ninguno"
  },
  "zona_superior": {
    "manga_tipo": "larga|tres cuartos|corta|cero|tirantes|campana|globo|asimétrica",
    "hombros": "cubiertos|descubiertos|un hombro|con hombreras|caídos",
    "patron": "liso|rayas (líneas rectas)|ondas (líneas curvas)|zigzag (líneas en picos)|floral / hojas|geométrico cerrado|animal print|abstracto / manchas",
    "color": "color dominante en esta zona",
    "textura": "liso|acanalado|calado|crochet|encaje|satinado|brillante|mate|tejido punto"
  },
  "zona_cintura": {
    "tipo": "ceñida|suelta|imperio|drop waist|ninguna",
    "detalle": "cinturón|lazo|elástico|costura marcada|fruncido|ninguno"
  },
  "zona_inferior": {
    "patron": "liso|rayas (líneas rectas)|ondas (líneas curvas)|zigzag (líneas en picos)|floral / hojas|geométrico cerrado|animal print|abstracto / manchas|mismo que superior",
    "color": "color dominante en esta zona",
    "caida": "recta|acampanada|sirena|tubo|plisada|con vuelo|asimétrica",
    "largo": "mini (sobre rodilla)|midi (a media pierna)|maxi (hasta tobillo/piso)|corto",
    "acabado_borde": "liso|festoneado|con volantes|deshilachado|redondeado|asimétrico|con abertura"
  },
  "paleta_colores": {
    "colores": ["color1", "color2", "color3 si aplica (máx 3 colores reales observados)"],
    "patron_direccion": "horizontal|vertical|diagonal|zigzag|espiral|ondas curvas|sin dirección",
    "distribucion": "color sólido uniforme|degradado (transición suave)|franjas / rayas repetitivas|bicolor mitad-mitad|bloques de color gruesos|detalles en contraste"
  },
  "detalles_constructivos": {
    "cierres": "botones frontales|botones laterales|cierre trasero|cierre lateral|sin cierre visible",
    "aberturas": "abertura lateral|abertura frontal|abertura trasera|sin abertura",
    "extras": ["bolsillos", "cinturón incluido", "forro visible", "transparencias", "bordados", "lentejuelas", "pedrería", "ninguno"]
  },
  "ajuste_fit": "ajustado bodycon|semi ajustado|recto|holgado|oversize|sirena",
  "huella_digital": "Oración muy detallada (hasta 100 palabras) combinando TODA la estructura exacta de la prenda, colores por zona, y la descripción microscópica del patrón y la textura extraídos previamente. Debe ser infalible.",
  "huella_forma": "Oración muy detallada (hasta 80 palabras) combinando TODA la estructura exacta de la prenda y la descripción microscópica del patrón y la textura, pero EXCLUYENDO POR COMPLETO CUALQUIER MENCIÓN A LOS COLORES. Usa esta para identificar la geometría del vestido."
}
PROMPT;
    }
}
