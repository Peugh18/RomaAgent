# Refactorización de Configuraciones - Guía Visual

## ❌ ANTES (Problema)

```
┌─────────────────────────────────────────────────────────────────────┐
│                    TABLA: company_settings                           │
│                         (1 fila, 58 campos)                          │
├─────────────────────────────────────────────────────────────────────┤
│ id | company_name | ruc | email | ... | agente_ia_modelo | ...     │
│     | mensaje_recordatorio_3min | mensaje_recordatorio_15min | ...  │
│     | comision_tarjeta | formato_registro_venta | ... (58 total)   │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              │ CUALQUIER cambio
                              │ (ej: cambiar teléfono)
                              ▼
                    ┌─────────────────┐
                    │  INVALIDA TODO  │
                    │  EL CACHE DEL   │
                    │     PROMPT      │
                    └─────────────────┘
```

**Problemas:**
- 1 fila con 58 campos = "God Object"
- Cambiar un campo cualquiera invalida TODO el caché
- Migrations gigantes y propensas a errores
- Difícil testear - hay que crear objeto con 58 campos

---

## ✅ DESPUÉS (Solución)

```
DATOS SEPARADOS (Base de Datos):
══════════════════════════════════════════════════════════════════════

┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  empresa_info   │    │  agente_config  │    │ mensaje_config  │
│    _configs     │    │                 │    │                 │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ company_name    │    │ activado        │    │ saludo_inicial  │
│ ruc             │    │ modelo          │    │ recordatorio_3m │
│ email           │    │ api_key         │    │ pedido_enviado  │
│ logo_path       │    │ temperatura     │    │ ... (12 campos) │
│ ... (10 campos) │    │ tono_bot        │    └─────────────────┘
└─────────────────┘    │ ... (8 campos)  │
                       └─────────────────┘
                              │
                              │ Relación 1:1
                              ▼
                       ┌─────────────────┐
                       │ company_settings│ (coordinador)
                       │      (id)       │
                       └─────────────────┘
                              │
                              │ También:
                       ┌─────────────────┐    ┌─────────────────┐
                       │  venta_config   │    │  horario_config │
                       │                 │    │                 │
                       │ moneda          │    │ horario_atencion│
                       │ metodos_pago    │    │ politica_devol  │
                       │ comision_tarj   │    │ ... (7 campos)  │
                       │ ... (5 campos)  │    └─────────────────┘
                       └─────────────────┘

CACHE GRANULAR (SIN REDIS - Compatible con cualquier driver):
══════════════════════════════════════════════════════════════════════

✅ FUNCIONA CON: database (MySQL), file, array, redis

Cada sección tiene su propia caché:

┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  Cache: Empresa │    │  Cache: Agente  │    │ Cache: Mensajes │
│  Key: seccion_  │    │  Key: seccion_  │    │  Key: seccion_  │
│   empresa_1     │    │   agente_1      │    │  mensajes_1     │
│                 │    │                 │    │                 │
│ [Datos empresa] │    │ [Config IA]     │    │ [Plantillas]    │
│  TTL: 10 min    │    │  TTL: 10 min    │    │  TTL: 10 min    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
        │                      │                      │
        └──────────────────────┼──────────────────────┘
                               │
                               ▼
                    ┌─────────────────┐
                    │ Cache: Completo │
                    │ Key: prompt_    │
                    │  unificado_v2_1 │
                    │                 │
                    │ [PROMPT FINAL   │
                    │  UNIFICADO]     │
                    └─────────────────┘

INVALIDACIÓN INTELIGENTE (sin tags):
══════════════════════════════════════════════════════════════════════

Ejemplo: Cambias el teléfono de la empresa

ANTES:
  Cambiar phone → Invalida TODO el prompt (todas las secciones)

AHORA:
  Cambiar phone → Cache::forget("seccion_empresa_1")
                   Cache::forget("prompt_unificado_v2_1")
                   
  Resultado: Solo se regenera la sección empresa (20% del prompt)
             Las otras secciones siguen cacheadas

CONFIGURACIÓN (.env):
══════════════════════════════════════════════════════════════════════

# ✅ Funciona con CUALQUIER driver (no requiere Redis!)
CACHE_STORE=database    # Usa tabla 'cache' en MySQL
# o
CACHE_STORE=file        # Archivos en storage/framework/cache
# o  
CACHE_STORE=array       # Solo para testing (no persistente)
# o
CACHE_STORE=redis       # Si en el futuro quieres Redis

# Para producción SIN Redis, recomendado:
CACHE_STORE=database
CACHE_PREFIX=roma_cache_


PROMPT QUE VA A GEMINI:
══════════════════════════════════════════════════════════════════════

Aunque los datos están separados, el prompt que recibe Gemini es UNO SOLO:

┌─────────────────────────────────────────────────────────────────────┐
│                    PROMPT UNIFICADO PARA GEMINI                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  # DATOS DE LA EMPRESA                                              │
│  **Nombre:** Mi Tienda                                              │
│  **Celular:** 999888777                                             │
│  ... (de sección Empresa)                                           │
│                                                                     │
│  ================================================================   │
│                                                                     │
│  # CONFIGURACIÓN DEL AGENTE                                         │
│  **Modelo:** gemini-2.5-flash                                      │
│  **Tono:** Amigable y profesional                                   │
│  ... (de sección Agente)                                            │
│                                                                     │
│  ================================================================   │
│                                                                     │
│  # MENSAJES Y PLANTILLAS                                            │
│  **Saludo:** Hola! Soy el asistente de...                         │
│  ... (de sección Mensajes)                                          │
│                                                                     │
│  ================================================================   │
│                                                                     │
│  # CATÁLOGO DE PRODUCTOS                                            │
│  (Sin caché - siempre actualizado)                                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Flujo de Uso

### 1. Obtener prompt para Gemini:
```php
$promptService = new PromptBuilderService();
$promptCompleto = $promptService->construirPromptCompletoParaAgente();
// ↑ Un string con TODO el contexto
```

### 2. Cuando cambia algo:
```php
// En tu controller
$empresaConfig->update(['celular' => '999000111']);

// AUTOMÁTICO (gracias al Observer):
// - Se invalida solo el caché de Empresa
// - Agente, Mensajes, Ventas, Horarios siguen cacheados
// - El prompt se regenera rápidamente
```

### 3. Migrar datos existentes:
```bash
# Ver preview de lo que se migraría
php artisan config:migrar --dry-run

# Ejecutar migración real
php artisan config:migrar --execute
```

---

## 📊 Comparación de Performance

| Escenario | ANTES | DESPUÉS |
|-----------|-------|---------|
| Cargar prompt (cache hit) | 1 query + parse 58 campos | 5 queries paralelas (más rápido) |
| Cambiar teléfono | Invalida TODO | Invalida solo Empresa (20% del prompt) |
| Cambiar modelo IA | Invalida TODO | Invalida solo Agente (15% del prompt) |
| Query de verificación | 1 tabla gigante | Queries pequeñas y enfocadas |

---

## 🎯 Beneficios Clave

1. **Separación de Responsabilidades**: Cada tabla tiene un propósito claro
2. **Caché Granular**: Cambios en una área no afectan otras
3. **Testing Fácil**: Puedes testear Agente sin crear datos de Empresa
4. **Escalabilidad**: Más fácil agregar campos a una sección específica
5. **Mantenibilidad**: 5 tablas pequeñas > 1 tabla gigante

---

## ⚠️ Nota Importante

**El prompt que recibe Gemini sigue siendo UNO SOLO y COMPLETO.**

Lo que cambió es:
- **CÓMO se guardan los datos** → Separado en tablas
- **CÓMO se cachea** → Por secciones
- **CÓMO se invalida** → Solo lo necesario

Lo que NO cambió:
- **El prompt final** → Sigue siendo un string unificado con todo el contexto
