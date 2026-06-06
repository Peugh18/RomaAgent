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
npm install
composer run dev
```

## Módulos

- Chat WhatsApp
- Productos y categorías
- Pipeline de ventas
- Zonas de delivery
- Configuración empresa + agente IA
