# License Service (Laravel)

Multi-brand **license service** designed as a *single source of truth* for licenses and entitlements.  
It allows multiple brands to provision, manage, and validate licenses centrally through clear and secure APIs.

---

### ✨ Key Features

- **Multi-tenant architecture** (brand-based) using a single database.
- License key provisioning that groups multiple product licenses.
- License activation, validation, and deactivation (with seat support).
- Separate APIs for **Brand systems** and **Product clients**.
- **Idempotency** in critical operations.
- Basic **observability**: structured JSON logs, request correlation via `request_id`, request-level latency metrics.
- Automatically generated **OpenAPI documentation**.

---

### 🧱 Tech Stack

- PHP 8.4 / Laravel 12
- PostgreSQL 16
- Docker + Docker Compose
- Nginx + PHP-FPM
- Structured logging with Monolog (JSON to stdout)

---

### 🚀 Running with Docker (Recommended)

#### Requirements
- Docker 24+
- Docker Compose v2+

#### Start the full stack
```bash
docker compose up --build
```

#### Exposed services
- API: http://localhost:8001
- PostgreSQL: localhost:5433

#### Migrations (first run)
```bash
docker exec -it laravel_app php artisan migrate
```

#### View logs (JSON, stdout)
```bash
docker compose logs -f app
```

All Laravel logs are emitted in **JSON format** to `stdout` to facilitate observability and log aggregation.

---

### 🧪 Running without Docker (Local)

#### Requirements
- PHP 8.2+
- Composer
- PostgreSQL

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --port=8000
```

---

### 📘 API Documentation

#### Interactive UI (Scramble / Stoplight)
Access at:
```
http://localhost:8001/docs/api
```

#### Export OpenAPI specification
```bash
php artisan scramble:export --path=docs/openapi.json
```

---

### 🔐 Authentication

#### Brand APIs
- Header: `X-Brand-Key`
- Validated against `brands.api_key_hash` (SHA-256 hash).
- Allows creation of products, licenses, and license keys.

#### Product APIs
- Header: `X-Product-Token`
- Validated against `products.product_token_hash`.
- Used for license activation, validation, and deactivation.

---

### 🔗 Main Endpoints

#### Health
- `GET /api/health` — service healthcheck.

#### Brand APIs
- `POST /api/brand/brands` — create brand (ecosystem_admin only).
- `POST /api/brand/products` — create product under the authenticated brand.
- `POST /api/brand/license-keys` — generate a license key and associated licenses.
- `PATCH /api/brand/licenses/{id}` — renew / suspend / resume / cancel.
- `GET /api/brand/licenses?email=` — list licenses by email (admin only).

#### Product APIs
- `POST /api/product/activate` — activate license for an instance (seat-aware).
- `GET /api/product/license-keys/{key}` — retrieve license status and entitlements.
- `DELETE /api/product/deactivate` — deactivate instance (idempotent).

Payload examples and full flows are documented in `Explanation.md`.

---

### 📊 Observability

The service includes basic observability tailored for containerized environments:

- Structured JSON logs
- Correlation via `request_id`
- Tenant context (`brand_id`)
- Request latency (`duration_ms`)
- Domain events (`license.provision.*`, `license.activate.*`, etc.)

Example log:
```json
{
  "event": "license.provision.succeeded",
  "request_id": "7c8e5c7d-...",
  "tenant": { "type": "brand", "id": 3, "key_hash_prefix": "..." },
  "http_status": 201,
  "duration_ms": 84,
  "licenses_created": 2,
  "licenses_updated": 0
}
```

Designed for easy integration with **Loki / ELK / Datadog**.

---

### 🧪 Tests

- **Pest**
```bash
php artisan test
```

**Includes:**
- Feature tests per use case (US1–US6)
- Idempotent flow validation
- Error handling and authorization cases

---

### 📄 Design Notes

- A single **license key** may unlock multiple licenses (one per product).
- The service acts as the **single source of truth**.
- Product-facing APIs are designed to be **stateless**.
- Sensitive data is never exposed in logs (hashing applied).
