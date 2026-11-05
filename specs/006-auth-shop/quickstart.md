# Quickstart: Merchant Account and Shop

How to prove the feature works. Endpoints and codes are in [contracts/api.md](./contracts/api.md).

## Prerequisites

- Docker with Docker Compose.
- From `shop-keeper-backend/`: copy `.env.example` to `.env`, fill the empty secrets, then run `docker compose up -d --build`.
- For the local journey, set `AUTH_TEST_MODE=true` in `.env`, then run `docker compose up -d --force-recreate app queue`.

## Automated checks

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

All tests pass, covering success, validation, unauthenticated and cross-shop cases for each endpoint (FR-031).

## Postman journey

1. Import `shop-keeper-backend/postman/shop-keeper-006.postman_collection.json`.
2. Set `base_url` to `http://localhost:8000`.
3. Run the collection in order. Expected: every request succeeds without edits, and the session carries over by itself (SC-007).

The collection uses `test:<email>` as the Google identity and `000000` as the emailed code.

## Scenarios

| # | Scenario | Expected |
|---|----------|----------|
| 1 | First Google sign-in with terms, no phone | Session; `GET /me` shows `shop: null` |
| 2 | First Google sign-in without terms | `auth.terms_required` |
| 3 | Email sign-up, confirm with `000000`, create shop, read it | 4 requests, shop returned |
| 4 | Password sign-in before confirming | `auth.email_unconfirmed` |
| 5 | 5 wrong passwords, then the right one | `auth.password_locked` with time left |
| 6 | Forgot password for an unknown and a known address | Identical answers |
| 7 | Sign in on two `device_id`s, sign out of one | Only that session stops |
| 8 | Sign in twice on one `device_id` | First session stops |
| 9 | Turn on two-step sign-in, sign in on a new device | `code_required`, a code in Mailpit, no session until the code |
| 10 | 5 wrong codes, then the right one | `auth.pending_invalid`; a new sign-in sends a new code |
| 11 | Ask for a new code while a sign-in waits | The first code gives `code.invalid`, the new one gives a session |
| 12 | Turn off two-step sign-in with the emailed code | 204; the next sign-in gives a session directly |
| 13 | Second shop for one merchant | `shop.already_exists` |
| 14 | Merchant A reads merchant B's shop | `shop.forbidden`, no data |
| 15 | Unknown address, or a non-JSON body | Common error shape |
| 16 | `APP_ENV=production` with `AUTH_TEST_MODE=true`, then `test:<email>` | `auth.google_invalid` |
| 17 | Search `storage/logs` after the journey | No emails, codes or tokens |

## Real Google check

With `GOOGLE_CLIENT_IDS` set and test mode off, sign in with a real ID token from Google's OAuth Playground configured with your client ID, and check `GET /me`. Then try a token whose `aud` is another application: `auth.google_invalid`.
