# IZZY Website sobre CMS Core

Esta entrega adapta CMS Core a la marca IZZY de ES MULTISERVICIOS e incluye una landing page responsive, planes administrables, galería con zoom, modalidad empresarial y restaurantes, formulario de contacto, WhatsApp, Google Maps, SEO y Cloudflare Turnstile.

## Administración
- **Page content:** textos principales de la landing.
- **Services:** soluciones / funcionalidades de IZZY.
- **Planes IZZY:** precios, beneficios, imagen, destacado, orden y publicación.
- **Projects / Case Studies:** imágenes adicionales del sistema mostradas en la sección de experiencia.
- **Service areas:** ciudades/cobertura y ubicación de Google Maps.
- **Settings:** contacto, WhatsApp, anti-spam y Cloudflare Turnstile.
- **SEO Manager:** título, descripción, robots, imagen social y Google Site Verification.

## Base de datos
- Instalación nueva: `schema.sql` ya incluye todo.
- Instalación existente: ejecutar `database-update.sql`. Es el único actualizador acumulativo y seguro de base de datos incluido en el proyecto.

## Cloudflare Turnstile
Configurar desde **Admin > Settings > Form protection**. Al activarlo deben existir Site Key y Secret Key; si faltan, el formulario público se bloquea de forma segura.

## Google
- Ubicación/Maps: **Admin > Service areas**.
- Google Search Console: **Admin > SEO Manager > Google Site Verification**.
