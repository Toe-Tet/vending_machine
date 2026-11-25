# Vending Machine — Specification

Source brief: *Vending Machine – PHP* (17 tasks + deployment). Vocabulary follows [GLOSSARY.md](../GLOSSARY.md); architectural decisions are in [docs/adr/](adr/).

## 1. Scope

A server-rendered PHP web app plus a JSON API where Guests browse Products, Users and Admins buy them, and Admins manage the catalogue. Every Purchase is recorded.

Scope follows the brief strictly. Out of scope: unique Product names, purchase history pages/endpoints, API registration, integration tests, CI, money handling (balances, coins, change), restock history, restoring Archived Products, refresh tokens, login rate limiting.

## 2. Stack & runtime

| Concern | Decision |
|---|---|
| Language | PHP 8.5, no framework ([ADR-0001](adr/0001-plain-php-no-framework.md)) |
| Core | Hand-built: attribute router (`#[Route]`), PDO connection class, DI container, view renderer |
| Composer deps | `phpunit/phpunit` (dev), `firebase/php-jwt` — nothing else |
| PHP extensions | `pdo_mysql`, `bcmath` (exact decimal arithmetic) |
| Database | MySQL 8, `utf8mb4` |
| Container | Single `php:8.5-apache` image, `mod_rewrite`, docroot `public/` |
| Local dev | `docker compose` (app + MySQL) |
| Hosting | Render web service (Docker, free tier) + Aiven MySQL over TLS ([ADR-0004](adr/0004-render-web-aiven-mysql.md)) |
| UI | PHP templates + Pico.css (CDN), minimal vanilla JS |

### Configuration

Variables: `APP_ENV`, `APP_URL`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_SSL_CA` (optional), `JWT_SECRET`.

| Environment | Where values come from | Files |
|---|---|---|
| local | `.env` (git-ignored), copied from `.env.example` | `.env.example` committed |
| testing | `<php><env>` in `phpunit.xml.dist` (`APP_ENV=testing`; no DB settings — unit tests use no database) | `phpunit.xml.dist` committed |
| production | Real env vars set in the Render dashboard | none — no `.env.production` |

No `.env.test` or `.env.production` files exist; no secrets are ever committed.

**Loader:** a hand-written `.env` reader (~30 lines, no `vlucas/phpdotenv`) parses `KEY=VALUE` lines, ignores blanks and `#` comments, and strips optional surrounding quotes. Real environment variables always take precedence over `.env`. A missing `.env` is silently skipped (normal on Render).

**`APP_ENV` behaviour:**

| | `local` | `testing` | `production` |
|---|---|---|---|
| Error display | full details in browser | full details | generic error page; details to log (stderr) |
| Session cookie `Secure` | only on HTTPS | only on HTTPS | always |
| `JWT_SECRET` | any non-empty | any non-empty | required, ≥ 32 chars, else app refuses to boot |
| Database | `vending_machine` (compose) | none | Aiven |

**Local dev:** `docker compose up` runs the app on `http://localhost:8080` and MySQL published on host port `3307` (avoids clashing with a Homebrew MySQL on 3306).

## 3. Data model (brief task 1)

### `users`
| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| name | VARCHAR(100) NOT NULL | |
| email | VARCHAR(255) NOT NULL UNIQUE | login identifier |
| password_hash | VARCHAR(255) NOT NULL | `password_hash(PASSWORD_DEFAULT)` |
| role | ENUM('admin','user') NOT NULL DEFAULT 'user' | |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | |

### `products`
| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| name | VARCHAR(100) NOT NULL | |
| price | DECIMAL(10,3) NOT NULL | Unit Price ([ADR-0002](adr/0002-exact-three-decimal-prices.md)); CHECK > 0 |
| quantity_available | INT UNSIGNED NOT NULL | CHECK ≤ 1,000,000 |
| archived_at | DATETIME NULL | non-null = Archived ([ADR-0003](adr/0003-archive-products-and-snapshot-price.md)) |
| created_at / updated_at | DATETIME | |

### `transactions` (domain term: **Purchase**)
| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED PK AI | |
| user_id | INT UNSIGNED NOT NULL FK → users.id ON DELETE RESTRICT | |
| product_id | INT UNSIGNED NOT NULL FK → products.id ON DELETE RESTRICT | |
| quantity | INT UNSIGNED NOT NULL | ≥ 1 |
| unit_price | DECIMAL(10,3) NOT NULL | snapshot at time of Purchase |
| total | DECIMAL(13,3) NOT NULL | unit_price × quantity, exact |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | |

Indexes: `transactions(user_id, created_at)`, `transactions(product_id)`.

Relationships: a User has many Purchases; a Product has many Purchases. Products are never hard-deleted, so FKs can be RESTRICT.

### Migrations & seed
- Numbered SQL files in `database/migrations/`, applied by `bin/migrate`, tracked in a `migrations` table; idempotent. Runs on container start.
- Seed (idempotent): Products Coke 3.990, Pepsi 6.885, Water 0.500 (each with a starting quantity, e.g. 10); demo accounts `admin@example.com` (Admin) and `user@example.com` (User) with known passwords documented in the README and on the login page.

## 4. Domain rules

### Product validation (web + API, server-side authoritative)
- **name**: required, trimmed, 1–100 chars → else 422.
- **price**: required, numeric, > 0, ≤ 999,999.999, at most 3 decimal places.
- **quantity_available**: required, integer, 0–1,000,000.

### Archiving
- "Delete" sets `archived_at`. One-way; no restore.
- Archived Products are excluded from every list and return 404 on show/edit/purchase.

### Purchase
- Allowed for any signed-in User or Admin. Guests are redirected to login (web) / 401 (API).
- quantity: integer, 1 ≤ quantity ≤ Quantity Available. Otherwise rejected, nothing changes.
- **Sold Out** (quantity 0): shown in lists, Buy disabled; purchase attempt → 409.
- Algorithm, inside one DB transaction:
  1. `SELECT … FROM products WHERE id = ? AND archived_at IS NULL FOR UPDATE` (404 if none)
  2. If requested quantity > quantity_available → rollback, 409 "Only N available"
  3. `UPDATE products SET quantity_available = quantity_available - ?`
  4. `INSERT INTO transactions (… unit_price = current price, total = bcmul(price, qty, 3))`
  5. Commit.
- Concurrency guarantee: two simultaneous buyers of the last unit → exactly one succeeds.

### Users & auth
- Registration: name (required, ≤100), email (valid, unique), password (≥ 8 chars, confirmed). Always role `user`.
- Admins exist only via seed.
- Web: PHP sessions; `session_regenerate_id(true)` on login; cookie `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS.
- Role is stored in the session; middleware/guards check it before Admin-only routes (403 if wrong role, redirect to login if not signed in).
- CSRF token required on every state-changing web form. All output escaped. All SQL uses prepared statements.

## 5. Web routes (brief tasks 5, 7–11)

HTML forms use a hidden `_method` field for PUT/DELETE.

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/` | all | redirect to `/products` |
| GET | `/products` | all | list (sort + pagination) |
| GET | `/products/create` | Admin | create form |
| POST | `/products` | Admin | store |
| GET | `/products/{id}` | all | details |
| GET | `/products/{id}/edit` | Admin | edit form |
| PUT | `/products/{id}` | Admin | update |
| DELETE | `/products/{id}` | Admin | archive |
| GET | `/products/{id}/purchase` | User/Admin | purchase form (attribute-routed) |
| POST | `/products/{id}/purchase` | User/Admin | perform purchase (attribute-routed) |
| GET/POST | `/login` | Guest | sign in |
| POST | `/logout` | signed-in | sign out |
| GET/POST | `/register` | Guest | sign up |

All routes are declared with `#[Route]` attributes on controller methods and discovered by the router.

### Product list
- Query: `sort` ∈ {name, price, quantity}, `dir` ∈ {asc, desc}, `page` ≥ 1. Unknown values fall back to `name`/`asc`/`1`.
- 10 per page; pagination links preserve sort; column headers toggle direction.
- Each row: name, price (3 dp), quantity or "Sold out", Buy link; Admin also sees Edit/Delete.

### Purchase view
- Shows name, Unit Price, Quantity Available; quantity input (min 1, max available); live total preview in JS; confirmation flash after success with the Purchase Total.

### Validation UX (task 13)
- Client: HTML5 attributes (`required`, `min`, `max`, `step="0.001"`, `maxlength`) + small JS showing inline messages before submit.
- Server: same rules; on failure re-render the form with per-field errors and old input preserved.

## 6. REST API (brief tasks 16–17)

Base `/api`, JSON in/out. Auth: `Authorization: Bearer <JWT>` — HS256, 1-hour expiry, claims `sub` (user id), `role`, `iat`, `exp`. No refresh tokens.

| Method | Path | Access |
|---|---|---|
| POST | `/api/auth/login` | public → `{token, expires_at, user}` |
| GET | `/api/products?sort&dir&page` | public → `{data, meta:{page, per_page, total, last_page}}` |
| GET | `/api/products/{id}` | public |
| POST | `/api/products` | Admin |
| PUT | `/api/products/{id}` | Admin |
| DELETE | `/api/products/{id}` | Admin (archive) → 204 |
| POST | `/api/products/{id}/purchase` | User/Admin, body `{quantity}` → 201 Purchase |

Errors: `{"error": {"code": "...", "message": "...", "fields": {...}}}` with 400 (malformed JSON), 401 (missing/invalid/expired token), 403 (wrong role), 404 (missing or archived), 409 (Sold Out / quantity exceeds stock), 422 (validation). Prices serialised as strings (`"6.885"`) to avoid float loss.

## 7. Code structure

- `ProductsController` (web) handles index, show, create, store, edit, update, destroy, purchaseForm, purchase.
- Its collaborators are injected via constructor: `ProductRepository` (interface), `PurchaseService` (interface), `Validator`, `Auth`/session, view renderer. This lets PHPUnit mock them (task 15).
- `Database` connection class wraps PDO (exceptions mode, emulated prepares off, TLS options) and exposes a `transaction(callable)` helper.
- Repositories provide CRUD for products, users, transactions (task 2).
- API controllers reuse the same repositories, service and validator.

## 8. Testing (brief tasks 14–15)

- **Unit** (`tests/Unit`): `ProductsController` with mocked repository/service/auth. Covers: list sort/page whitelist; store valid; store invalid (each field rule incl. negative quantity, zero/negative price, >3 dp, missing name); update; archive; non-admin → 403; guest → redirect; missing/archived → 404; purchase success; purchase quantity 0 / non-integer / > available; Sold Out.
- Scope is exactly what the brief asks: PHPUnit unit tests for `ProductsController`, isolated from the database via DI + mocks. No integration tests, no test database, no CI.
- Run on the host (PHP 8.5, no Docker or DB needed): `composer test` → `vendor/bin/phpunit`.
- Database-level behaviour (row locking, atomic purchase) is verified manually against the compose MySQL during development, not by automated tests.

## 9. Deployment & submission

- `Dockerfile` (php:8.5-apache + pdo_mysql + bcmath), entrypoint runs `bin/migrate` then Apache.
- `render.yaml` blueprint; env vars set in the Render dashboard; Aiven CA cert supplied via `DB_SSL_CA`.
- README: overview, DB design (task 1 answer + ER diagram), task → code map, local setup, demo credentials, API examples (curl), preview link.

## 10. Brief traceability

| Task | Covered in |
|---|---|
| 1 DB design | §3, README |
| 2 PDO + CRUD | §7 |
| 3 Sessions + hashing + roles | §4 Users & auth |
| 4 RBAC via session role | §4, §5 Access column |
| 5 ProductsController CRUD | §5, §7 |
| 6 Purchase in controller | §4 Purchase, §7 |
| 7 Admin-only CRUD views | §5 |
| 8 List with pagination + sorting | §5 Product list |
| 9 Purchase view | §5 Purchase view |
| 10 Routing | §5 |
| 11 Attribute routing for purchase | §5 |
| 12 Product validation rules | §4 |
| 13 Client + server validation | §5 Validation UX |
| 14 PHPUnit tests | §8 |
| 15 DI + mocking | §7, §8 |
| 16 REST API | §6 |
| 17 JWT | §6 |
| Deploy | §9 |

## 11. Delivery slices (→ GitHub issues)

1. **Skeleton & core** — composer, autoload, `.env` loader + `APP_ENV` behaviour, router with attributes, DI container, `Database`, view renderer, Dockerfile + compose (app :8080, MySQL :3307), migrations + seed. *Done when:* `docker compose up` serves `/products` listing the 3 seeded Products.
2. **Auth & RBAC** — register/login/logout, sessions, CSRF, guards. *Done when:* demo Admin and User can sign in; Admin-only URL returns 403 for User and redirects Guest.
3. **Product catalogue & admin CRUD** — list (sort/paginate), show, create/edit/archive with client+server validation. *Done when:* all §4 validation rules reject bad input with inline errors; archived Products disappear.
4. **Purchasing** — purchase view + locked transactional purchase. *Done when:* buying decrements stock and logs a Purchase with snapshot price; over-quantity and Sold Out are rejected with nothing changed.
5. **REST API + JWT** — all §6 endpoints and error format. *Done when:* curl walkthrough in README works end-to-end.
6. **Unit tests** — `ProductsController` PHPUnit suite per §8, green via `composer test`.
7. **Deploy & README** — live on Render + Aiven; README complete. *Done when:* preview link works with demo credentials.
