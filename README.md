# Vending Machine

A plain-PHP web app and JSON API where Guests browse Products, Users and Admins buy them, and Admins manage the catalogue. See [docs/spec.md](docs/spec.md) for the full specification and [GLOSSARY.md](GLOSSARY.md) for the vocabulary.

## Local setup

```sh
cp .env.example .env
docker compose up -d --build   # app on http://localhost:8080, MySQL on 127.0.0.1:3307
composer install && composer test
```

Demo accounts (seeded):

| Role  | Email               | Password    |
|-------|---------------------|-------------|
| Admin | `admin@example.com` | `admin1234` |
| User  | `user@example.com`  | `user12345` |

## REST API

Base URL `/api`, JSON in and out. Protected endpoints need `Authorization: Bearer <token>`. Tokens are HS256 JWTs whose key is the SHA-256 digest of `JWT_SECRET` (php-jwt requires a 32-byte HS256 key; this lets any secret work locally). They are valid for one hour, with claims `sub` (user id), `role`, `iat` and `exp`. There are no refresh tokens: sign in again when one expires.

| Method | Path | Access | Success |
|---|---|---|---|
| POST | `/api/auth/login` | public | 200 `{token, expires_at, user}` |
| GET | `/api/products?sort=name\|price\|quantity&dir=asc\|desc&page=N` | public | 200 `{data, meta: {page, per_page, total, last_page}}` |
| GET | `/api/products/{id}` | public | 200 `{data}` |
| POST | `/api/products` | Admin | 201 `{data}` + `Location` |
| PUT | `/api/products/{id}` | Admin | 200 `{data}` |
| DELETE | `/api/products/{id}` | Admin | 204 (archives the Product) |
| POST | `/api/products/{id}/purchase` | User or Admin | 201 `{data}` (the Purchase) |

A Product is `{"id", "name", "price", "quantity_available"}`. Prices are strings with three decimals (`"6.885"`) so they are never rounded by floating point. Product bodies take `name`, `price` and `quantity_available`; a purchase body takes `quantity`.

Every error has the same shape:

```json
{"error": {"code": "validation_failed", "message": "The given data was invalid.", "fields": {"price": "Price must be greater than 0."}}}
```

| Status | `code` | When |
|---|---|---|
| 400 | `malformed_json` | The body isn't a JSON object |
| 401 | `unauthenticated` | Token missing, malformed, tampered with or expired |
| 401 | `invalid_credentials` | Wrong email or password at login |
| 403 | `forbidden` | A User token on an Admin endpoint |
| 404 | `not_found` | No such Product, or it is archived |
| 405 | `method_not_allowed` | Path exists, method doesn't (`Allow` lists the methods) |
| 409 | `sold_out` / `insufficient_stock` | Sold Out, or more requested than the Quantity Available |
| 422 | `validation_failed` | Invalid fields; `fields` has a message for each |

### Postman

Import [docs/postman/vending-machine.postman_collection.json](docs/postman/vending-machine.postman_collection.json) and run it top to bottom (Collection Runner). It signs in as both demo accounts and saves the tokens. Then it creates its own Product, buys from it, triggers every error status and archives that Product, so the seeded data is left alone. Change the `baseUrl` collection variable to point it at another deployment.

### Walkthrough with curl

Sign in as the demo User and keep the token:

```sh
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email": "user@example.com", "password": "user12345"}' \
  | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')
```

```json
{"token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9…", "expires_at": "2026-11-25T16:01:42+00:00",
 "user": {"id": 2, "name": "Demo User", "email": "user@example.com", "role": "user"}}
```

List Products, cheapest first:

```sh
curl -s 'http://localhost:8080/api/products?sort=price&dir=asc'
```

```json
{"data": [{"id": 3, "name": "Water", "price": "0.500", "quantity_available": 10}, …],
 "meta": {"page": 1, "per_page": 10, "total": 3, "last_page": 1}}
```

Buy two Pepsis:

```sh
curl -s -X POST http://localhost:8080/api/products/2/purchase \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"quantity": 2}'
```

```json
{"data": {"id": 8, "product_id": 2, "quantity": 2, "unit_price": "6.885", "total": "13.770", "created_at": "2026-11-25T15:01:42+00:00"}}
```

Asking for more than is in stock changes nothing and returns `409 {"error": {"code": "insufficient_stock", "message": "Only 8 available.", …}}`. With the same token, `POST /api/products` returns `403`. Sign in as `admin@example.com` / `admin1234` to manage Products:

```sh
curl -s -X POST http://localhost:8080/api/products \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name": "Iced Tea", "price": "2.750", "quantity_available": 20}'
```
