# API Contract: Merchant Account and Shop

Base path `/api/v1`. JSON only. Protected routes take `Authorization: Bearer <token>`. An importable Postman collection, `shop-keeper-backend/postman/shop-keeper-006.postman_collection.json`, is delivered with the implementation and must match this file. It lives in the backend so that a test running in the `app` container can compare it with the routes.

Successful responses with content wrap it in `data`: `GET /me` answers `{ "data": Account }`, and `POST /shops` and `GET /shops/{shop}` answer `{ "data": Shop }`. The 202 and 204 answers have an empty body.

## Common shapes

**Session** (from any sign-in):

```json
{ "data": { "status": "signed_in", "token": "<token>", "account": { "...": "see Account" } } }
```

**PIN needed** (HTTP 200, when the account has a PIN):

```json
{ "data": { "status": "pin_required", "pending_sign_in": "<token>", "expires_in_seconds": 300 } }
```

**Account**:

```json
{ "id": 1, "name": "Amira", "email": "amira@example.com", "phone": "+21620123456", "pin_enabled": false, "shop": null }
```

`phone` may be null. When sent, it includes its international dialing code (`+21620123456`, `+33 6 12 34 56 78`); it is returned in E.164. `shop` is null or a Shop.

**Shop**:

```json
{ "id": 1, "name": "Epicerie Amira", "business_type": "grocery", "currency": "TND", "city": "Sousse", "address": null, "owner_id": 1 }
```

**Error**:

```json
{ "error": { "code": "validation.failed", "fields": { "email": ["email.invalid"] }, "retry_after_seconds": 120 } }
```

`fields` appears only on validation failures, `retry_after_seconds` only on blocks and throttling.

## Endpoints

### Sign-up and sign-in (public)

| Method and path | Body | Success |
|-----------------|------|---------|
| `POST /auth/google` | `id_token`, `device_id`; on first sign-in `terms_version`; optional `phone` | 200 Session or PIN needed |
| `POST /auth/email/register` | `name`, `email`, `password`, `terms_version`; optional `phone` | 202, code sent |
| `POST /auth/email/confirm` | `email`, `code`, `device_id` | 200 Session |
| `POST /auth/email/resend` | `email` | 202, same answer for any address |
| `POST /auth/email/login` | `email`, `password`, `device_id` | 200 Session or PIN needed |
| `POST /auth/password/forgot` | `email` | 202, same answer for any address |
| `POST /auth/password/reset` | `email`, `code`, `password` | 204 |
| `POST /auth/pin` | `pending_sign_in`, `pin` | 200 Session |

A first Google sign-in without `terms_version` answers `auth.terms_required`; the app resends it with the terms accepted. Password sign-in, code checks and `POST /auth/pin` are limited to 30 requests per minute per network origin; sign-up, resend and forgot-password to 10 per minute per origin and 3 per 15 minutes per address, shared between the three.

### Account (signed in)

| Method and path | Body | Success |
|-----------------|------|---------|
| `GET /me` | | 200 Account |
| `POST /auth/logout` | | 204 |
| `POST /me/pin` | `pin` | 204; `pin.already_enabled` if on |
| `PUT /me/pin` | `current_pin`, `pin` | 204 |
| `DELETE /me/pin` | `current_pin` | 204 |
| `POST /me/pin/recovery` | `pin`, and `id_token` or `password` | 204; ends other devices |

Wrong proof at recovery answers `auth.google_invalid` or `auth.invalid_credentials`. A wrong password there counts toward `auth.password_locked`.

### Shop (signed in)

| Method and path | Body | Success |
|-----------------|------|---------|
| `POST /shops` | `name`, `business_type`; optional `city`, `address` | 201 Shop |
| `GET /shops/{shop}` | | 200 Shop |

## Error codes

| Code | HTTP | Meaning |
|------|------|---------|
| `validation.failed` | 422 | `fields` holds a code per field, such as `email.invalid` |
| `auth.unauthenticated` | 401 | No valid session |
| `auth.google_invalid` | 401 | Google identity refused |
| `auth.google_unavailable` | 503 | Google keys unreachable; retry |
| `auth.google_email_conflict` | 409 | Email matches an account and Google is not authoritative for it, or the account is linked to another Google identity |
| `auth.terms_required` | 422 | First sign-in without accepted terms |
| `auth.invalid_credentials` | 401 | Wrong password or unknown email |
| `auth.password_locked` | 429 | 5 wrong passwords; has `retry_after_seconds` |
| `auth.email_unconfirmed` | 403 | Right password, address not confirmed |
| `auth.pending_invalid` | 401 | Pending sign-in unknown, expired or already used |
| `email.taken` | 409 | Address has a confirmed account |
| `code.invalid` | 422 | Wrong, expired, used or unknown code |
| `pin.invalid` | 401 | Wrong PIN |
| `pin.locked` | 429 | 5 wrong PINs; has `retry_after_seconds` |
| `pin.already_enabled` | 409 | PIN already on |
| `pin.not_enabled` | 409 | No PIN to change or turn off |
| `shop.already_exists` | 409 | Merchant already has a shop |
| `shop.forbidden` | 403 | Shop is another merchant's or does not exist |
| `request.not_found` | 404 | Unknown address |
| `request.method_not_allowed` | 405 | |
| `request.not_json` | 415 | Body is not JSON, or is malformed JSON |
| `request.throttled` | 429 | Network-origin or address limit; has `retry_after_seconds` |
| `server.error` | 500 | No internal detail |

Field codes follow `<field>.<rule>`: `email.required`, `email.invalid`, `password.too_short`, `password.too_long`, `code.format`, `pin.format`, `pin.weak`, `current_pin.format`, `phone.invalid`, `device_id.invalid`, `terms_version.outdated`, `name.required`, `name.too_long`, `city.too_long`, `address.too_long`, `business_type.invalid`, and so on.
