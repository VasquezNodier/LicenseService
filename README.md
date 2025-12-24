# License Service (Laravel)

API para centralizar licencias multi-marca. Incluye onboarding de marcas/productos, provisión de licencias, activación/consulta/desactivación y listado cross-brand.

## Requisitos rápidos
- PHP 8.2+, Composer.
- Base de datos (PostgreSQL por defecto en `.env`).

## Setup
```bash
composer install
cp .env.example .env  # o usar .env ya provisto
php artisan key:generate
php artisan migrate
php artisan serve
```

## Autenticación
- Marcas: header `X-Brand-Key` con token emitido al crear la marca (`brands.api_key_hash` almacena el hash).
- Productos: header `X-Product-Token` (`products.product_token_hash`).

## Endpoints principales
- `GET /api/health` – healthcheck.
- `POST /api/brand/brands` – crear marca (solo `ecosystem_admin`).
- `POST /api/brand/products` – crear producto bajo la marca autenticada.
- `POST /api/brand/license-keys` – generar clave y licencias asociadas a productos.
- `PATCH /api/brand/licenses/{id}` – renew/suspend/resume/cancel.
- `GET /api/brand/licenses?email=` – listar licencias por email (solo `ecosystem_admin`).
- `POST /api/product/activate` – activar licencia para una instancia (respeta asientos).
- `GET /api/product/license-keys/{key}` – estado de la clave y entitlements.
- `DELETE /api/product/deactivate` – revocar instancia (idempotente).

Ejemplos de payloads en `Explanation.md`.

## Tests
Pest: `php artisan test`.
