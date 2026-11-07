# API Contract: Merchant Account and Shop

Base path `/api/v1`. JSON only. Protected routes take `Authorization: Bearer <token>`. An importable Postman collection, `shop-keeper-backend/postman/shop-keeper.postman_collection.json` (named `shop-keeper-006…` until spec 007 made it one collection for the whole API), is delivered with the implementation and must match this file. It lives in the backend so that a test running in the `app` container can compare it with the routes.

Successful responses with content wrap it in `data`: `GET /me` answers `{ "data": Account }`, and `POST /shops` and `GET /shops/{shop}` answer `{ "data": Shop }`. The 202 and 204 answers have an empty body.

## Common shapes

**Session** (from any sign-in):

```json
{ "data": { "status": "signed_in", "token": "<token>", "account": { "...": "see Account" } } }
```

**Code needed** (HTTP 200, when two-step sign-in is on; a new code has been emailed to the account's address):

```json
{ "data": { "status": "code_required", "pending_sign_in": "<token>", "expires_in_seconds": 600 } }
```

**Account**:

```json
{ "id": 1, "name": "Amira", "email": "amira@example.com", "phone": "+21620123456", "two_factor_enabled": false, "shop": null }
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
| `POST /auth/google` | `id_token`, `device_id`; on first sign-in `terms_version`; optional `phone` | 200 Session or Code needed |
| `POST /auth/email/register` | `name`, `email`, `password`, `terms_version`; optional `phone` | 202, code sent |
| `POST /auth/email/confirm` | `email`, `code`, `device_id` | 200 Session |
| `POST /auth/email/resend` | `email` | 202, same answer for any address |
| `POST /auth/email/login` | `email`, `password`, `device_id` | 200 Session or Code needed |
| `POST /auth/password/forgot` | `email` | 202, same answer for any address |
| `POST /auth/password/reset` | `email`, `code`, `password` | 204 |
| `POST /auth/two-factor` | `pending_sign_in`, `code` | 200 Session |
| `POST /auth/two-factor/resend` | `pending_sign_in` | 202, new code sent, previous code dead |

A first Google sign-in without `terms_version` answers `auth.terms_required`; the app resends it with the terms accepted. Password sign-in and code checks, `POST /auth/two-factor` included, are limited to 30 requests per minute per network origin; sign-up, resend and forgot-password to 10 per minute per origin and 3 per 15 minutes per address, shared between the three. `POST /auth/two-factor/resend` and `POST /me/two-factor/disable-code` use the same sending limit, counted per origin and per account.

### Account (signed in)

| Method and path | Body | Success |
|-----------------|------|---------|
| `GET /me` | | 200 Account |
| `POST /auth/logout` | | 204 |
| `POST /me/two-factor` | | 204; `two_factor.already_enabled` if on |
| `POST /me/two-factor/disable-code` | | 202, code sent to the account's address; `two_factor.not_enabled` if off |
| `DELETE /me/two-factor` | `code` | 204; `two_factor.not_enabled` if off |

Turning two-step sign-in on or off ends no session. A wrong, expired or used code answers `code.invalid`; a code dies after 5 wrong tries.

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
| `auth.pending_invalid` | 401 | Pending sign-in unknown, expired, already used, or ended by 5 wrong codes |
| `email.taken` | 409 | Address has a confirmed account |
| `code.invalid` | 422 | Wrong, expired, used or unknown code |
| `two_factor.already_enabled` | 409 | Two-step sign-in already on |
| `two_factor.not_enabled` | 409 | Two-step sign-in is off |
| `shop.already_exists` | 409 | Merchant already has a shop |
| `shop.forbidden` | 403 | Shop is another merchant's or does not exist |
| `request.not_found` | 404 | Unknown address |
| `request.method_not_allowed` | 405 | |
| `request.not_json` | 415 | Body is not JSON, or is malformed JSON |
| `request.throttled` | 429 | Network-origin or address limit; has `retry_after_seconds` |
| `server.error` | 500 | No internal detail |

Field codes follow `<field>.<rule>`: `email.required`, `email.invalid`, `password.too_short`, `password.too_long`, `code.format`, `pending_sign_in.required`, `phone.invalid`, `device_id.invalid`, `terms_version.outdated`, `name.required`, `name.too_long`, `city.too_long`, `address.too_long`, `business_type.invalid`, and so on.
