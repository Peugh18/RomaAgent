# RomaAgent

CRM WhatsApp con agente IA (Gemini) para Roma Store — Laravel 12, Inertia/Vue 3.

## Stack

- PHP 8.2 · Laravel 12 · Inertia v2 · Vue 3 · Tailwind CSS 3

## Desarrollo local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
npm install
composer run dev
```

## WhatsApp (un solo servidor)

RomaAgent se conecta **directo a Meta**. No necesitas roma-api.

En `.env`:

```env
PUBLIC_APP_URL=https://tu-ngrok-8000.ngrok-free.dev
APP_URL=https://tu-ngrok-8000.ngrok-free.dev
WHATSAPP_ACCESS_TOKEN=tu_token_meta
WHATSAPP_PHONE_NUMBER_ID=tu_phone_number_id
WHATSAPP_VERIFY_TOKEN=tu_verify_token
```

En Meta Developer → Webhook URL:

`https://tu-ngrok-8000.ngrok-free.dev/api/whatsapp/webhook`

Diagnóstico: `php artisan roma:diagnose`

## Módulos

- Chat WhatsApp
- Productos y categorías
- Pipeline de ventas
- Zonas de delivery
- Configuración empresa + agente IA
