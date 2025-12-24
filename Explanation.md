# Centralized License Service

## Problem & requirements

A multi-tenant service that centralizes licenses for multiple brands/products. It must allow license provisioning (key + multiple products), lifecycle management, activation/deactivation of instances (seats), checking key status, and listing licenses via email at an ecosystem level. Core implemented in Laravel (PHP).

Non-goals:

-   payment/subscription logic
-   End-user management UI.

## Scope (designed vs implemented)

| User Story                      | Designed | Implemented |
| ------------------------------- | :------: | :---------: |
| US1 Provision license           |    ✅    |     ✅      |
| US2 Change lifecycle            |    ✅    |     ✅      |
| US3 Activate license            |    ✅    |     ✅      |
| US4 Check status                |    ✅    |     ✅      |
| US5 Deactivate seat             |    ✅    |     ✅      |
| US6 List by email across brands |    ✅    |     ✅      |

## Architecture & data model

-   **Tenancy:** `brands` (role `standard` or `ecosystem_admin`) → `products` → `license_keys` (by email + brand) → `licenses` (per product, status, expiration, seats) → `activations` (active/revoked instances).
-   **Auth:** brands via `X-Brand-Key` (hash stored in `brands.api_key_hash`); products via `X-Product-Token` (hash in `products.product_token_hash`).
-   **APIs:** brand-side create brand/product, provision key + licenses, change lifecycle, list licenses by email (ecosystem only). Product-side activate, check status, deactivate instance.
-   **Seat concurrency:** activation uses transaction + `lockForUpdate` on license and active-seat count; uniqueness constraint per instance keeps idempotency.

## Domain model

-   Brand(id, name, api_key_hash, role)
-   Product(id, brand_id, code, name, product_token_hash)
-   LicenseKey(id, brand_id, key, customer_email)
-   License(id, license_key_id, product_id, status, expires_at, max_seats)
-   Activation(id, license_id, instance_type, instance_identifier, activated_at, revoked_at)

Key rules:

-   LicenseKey belongs to one brand;
-   A key can unlock multiple product licenses in that brand;
-   Each license links to one product;
-   valid = status `valid` and `expires_at` in future;
-   Activation is idempotent per (license_id, instance_identifier) while active.

## API design

### Authentication

-   Brand API: `X-Brand-Key` (hashed lookup)
-   Product API: `X-Product-Token` (shared secret)

## Onboarding

-   **POST** `/api/brand/brands` — create brand (ecosystem-only); returns plaintext brand token once.
-   **POST** `/api/brand/products` — create product under brand; returns plaintext product token once.

### Brand APIs

-   **POST** `/api/brand/license-keys` — provision; payload `{customer_email, licenses:[{product_code, expires_at, max_seats?}]}`; reuses key per (brand,email); upsert licenses.
-   **PATCH** `/api/brand/licenses/{license_id}` — lifecycle `renew|suspend|resume|cancel`; renew requires `expires_at`; conflict (409) if renew cancelled.
-   **GET** `/api/brand/licenses?email=` — list by email; requires brand role `ecosystem_admin`.

### Product APIs

-   **POST** `/api/product/activate` — activate; idempotent per instance; enforces `max_seats` when set.
-   **GET** `/api/product/license-keys/{key}` — key status + entitlements.
-   **DELETE** `/api/product/deactivate` — revoke an active instance; idempotent.

## Core flows

-   **Provision:** brand auth → find/create LicenseKey by (brand,email) → validate product belongs to brand → upsert License → return key + licenses.
-   **Lifecycle:** brand auth → load License by id and tenant-check → apply action → return normalized view.
-   **Activate:** product token → load key → find license by product_code → validate status/expiration → upsert Activation with lock and seat limit → return decision + entitlements.
-   **Check:** product token → load key → map entitlements with validity/seats → aggregate validity.
-   **Deactivate:** product token → load key → find license and active activation → set `revoked_at` if exists → idempotent response.
-   **List-by-email:** ecosystem brand auth → query keys by email across brands → return grouped licenses.

## Seat management

-   Each active activation (license_id + instance_identifier + revoked_at NULL) consumes a seat.
-   Deactivation frees the seat; `max_seats` per license is optional (`null` = unlimited).

## Decisions & trade-offs

-   Email regex validation to ensure consistency in the structure.
-   Hash-only tokens (brand/product) to avoid storing secrets.
-   Explicit tenant checks on every flow.
-   Seat control via lock + count; could add stronger constraints/queue if contention grows.
-   JSON-only API; `/api/health` + `/api/ready` for liveness/readiness.
-   API keys chosen over OAuth for S2S simplicity; single relational DB for consistency; can evolve to events/outbox later.

## Run locally

-   `cp .env.example .env` and configure your DB (Postgres by default).
-   Install deps and generate key: `composer install && php artisan key:generate`.
-   Migrate and seed (prints plaintext keys/tokens once): `php artisan migrate --seed`.
-   Start server: `php artisan serve`.
-   Health: `GET /api/health` (alive), `GET /api/ready` (DB connectivity).

### Example cURL (replace with seeded secrets)

-   Provision (brand): `curl -X POST http://localhost:8000/api/brand/license-keys -H "X-Brand-Key: <brand-key>" -H "Content-Type: application/json" -d '{"customer_email":"user@example.com","licenses":[{"product_code":"rankmath","expires_at":"2026-01-01","max_seats":2}]}'`
-   Lifecycle (brand): `curl -X PATCH http://localhost:8000/api/brand/licenses/1 -H "X-Brand-Key: <brand-key>" -H "Content-Type: application/json" -d '{"action":"suspend"}'`
-   Cross-brand query (ecosystem_admin): `curl "http://localhost:8000/api/brand/licenses?email=user@example.com" -H "X-Brand-Key: <ecosystem-admin-key>"`
-   Activate (product): `curl -X POST http://localhost:8000/api/product/activate -H "X-Product-Token: <product-token>" -H "Content-Type: application/json" -d '{"license_key":"...","product_code":"rankmath","instance_type":"url","instance_identifier":"https://site.com"}'`
-   Status (product): `curl "http://localhost:8000/api/product/license-keys/<license>" -H "X-Product-Token: <product-token>"`
-   Deactivate (product): `curl -X DELETE http://localhost:8000/api/product/deactivate -H "X-Product-Token: <product-token>" -H "Content-Type: application/json" -d '{"license_key":"...","product_code":"rankmath","instance_identifier":"https://site.com"}'`

## Limitations & next steps

-   No CI/linting pipeline yet.
-   Basic observability (logs + health); missing metrics/traces. Expand metrics/structured logging (currently minimal event logs only)
-   Seat control could be hardened with stronger DB constraints/queueing if contention grows.
-   Add caching/rate limiting, CI, broader tests, and API versioning/OpenAPI docs.
