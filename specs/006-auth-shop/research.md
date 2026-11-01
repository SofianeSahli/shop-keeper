# Research: Merchant Account and Shop

Decisions for `specs/006-auth-shop/plan.md`. Sources: Laravel 13 Sanctum docs, Google's "Verify the Google ID token" guide, Packagist (checked 2026-11-01).

## R1. Session tokens

- **Decision**: Laravel Sanctum 4.x personal access tokens, installed with `php artisan install:api`. One token per device: the token `name` holds the client's `device_id`. Issuing a token locks the user row, deletes the tokens with that name, and creates the new one, in one transaction. `sanctum.expiration` stays `null`.
- **Rationale**: Required by the constitution. Sanctum 4.3 supports Laravel 13, its tokens never expire by default (FR-009), and it stores them as SHA-256 hashes.
- **Alternatives**: A unique index on Sanctum's table was rejected: the row lock gives the same guarantee without changing a vendor table.

## R2. Google identity check

- **Decision**: The app sends a Google ID token. The backend verifies it with `firebase/php-jwt` 7.x against the keys at `https://www.googleapis.com/oauth2/v3/certs`, cached for the `max-age` of Google's `Cache-Control` header. It checks the RS256 signature, `iss` (`accounts.google.com` or `https://accounts.google.com`), `aud` (one of `GOOGLE_CLIENT_IDS`), `exp`, and `email_verified`. The account key is `sub`, never the email.
- **Rationale**: Google recommends a general-purpose JWT library or its client library. `firebase/php-jwt` is small; several client IDs are needed (Android, iOS, web), and `aud` is checked by hand.
- **Alternatives**: `google/apiclient` brings the whole Google API surface for one check. Laravel Socialite targets browser redirects, not ID tokens from a mobile app. The `tokeninfo` endpoint adds a network call per sign-in.
- **Key fetch failure**: answered as `auth.google_unavailable` (503). Sessions already issued need no Google call.

## R3. When Google can link to an existing email

- **Decision**: Google counts as authoritative for an address only if it ends in `@gmail.com`, or `email_verified` is true and the `hd` claim is present. Linking to a confirmed account, or discarding an unconfirmed sign-up, requires that. Otherwise the sign-in is refused with `auth.google_email_conflict`. An address with no match needs no such check.
- **Rationale**: Google's guide says that outside those two cases it is not authoritative for the address, and recommends another challenge. Linking on email alone would allow account takeover.

## R4. Password and PIN attempt limits

- **Decision**: Counters and lock times live in columns on `users`, updated under a row lock. 5 consecutive failures lock for 15 minutes; a success resets the count. Network-origin limits use named Laravel rate limiters on the routes.
- **Rationale**: SC-004 allows at most 5 wrong attempts in any 15-minute window. Laravel's `RateLimiter` uses fixed windows, so 5 at the end of one and 5 at the start of the next would pass.
- **Unknown email**: the sign-in still checks a fixed dummy hash, so its timing matches a wrong password (FR-035).

## R5. Hashing

- **Decision**: Passwords, PINs and emailed codes use `Hash::make` (bcrypt, Laravel's default). The pending sign-in token is 40 random bytes stored as SHA-256.
- **Rationale**: PINs and 6-digit codes are small, so a slow hash is needed. A long random token does not need one.

## R6. Sending email

- **Decision**: Codes go out through queued Laravel mail on the Redis queue, with retries. `MAIL_*` settings pick the service; locally that is Mailpit.
- **Rationale**: Forgot-password must look the same whether or not the account exists (FR-037), in content and timing, so sending cannot happen in the request. No provider-specific code (constitution).
- **Consequence**: deployments need a running queue worker. The Compose `queue` service is that worker locally.

## R7. Test mode

- **Decision**: `AUTH_TEST_MODE` defaults to `false`. A single check returns true only if it is set and the environment is not `production`. When on, an `id_token` of the form `test:<email>` stands in for Google, and every emailed code is `000000`.
- **Rationale**: FR-029 and SC-007 need a run without a Google login or a mailbox. Writing codes to the mail log would break FR-030. Keeping the guard in one place makes SC-008 testable.

## R8. Error format

- **Decision**: One `ApiException` carries the code, HTTP status and optional details. A renderer in `app/Shared/` turns it, and framework exceptions, into `{ "error": { "code", "fields"?, "retry_after_seconds"? } }`. Form Requests set each rule's message to its stable code, so the validator's output is already codes.
- **Rationale**: Covers FR-027 in one place, including 404, 405, throttling and 500.
- **Logs**: `QueryException` text can include bound values such as an email, so it is reported with its SQL state only (FR-030).

## R9. Shop keys

- **Decision**: `shops.id` is an auto-increment big integer.
- **Rationale**: The backend rule requires client UUIDs only for records created offline. A shop is created online, since an account needs the network. Refusing a foreign or missing shop with the same `shop.forbidden` keeps ids from revealing anything.

## R10. Phone numbers

- **Decision**: The API takes one `phone` field with its international dialing code. Spaces, dashes and parentheses are removed, a leading `00` becomes `+`, and the result must match E.164: `+`, then a first digit from 1 to 9, then 6 to 14 more digits. A number without a dialing code is refused, with no default country. The app's dialing-code selector joins the code and the number before sending; it comes with the mobile spec.
- **Rationale**: The rule stays loose because the number is an unverified contact detail, and refusing a real number costs more than accepting an odd one. The same reason keeps out a phone-validation library, whose per-country rules would add a dependency for a check nothing relies on.

## R11. Unconfirmed sign-ups

- **Decision**: An unconfirmed row is a `users` row with `email_verified_at` null. Signing up again with that email replaces its name, password and code. Removing old unconfirmed rows is left out of 006.
- **Rationale**: Replacement means a stranger's unconfirmed sign-up cannot block the real owner.

## R12. Environment

- **Docker**: the app, MySQL, Redis, the queue worker and Mailpit run through `shop-keeper-backend/compose.yaml`, which is a prerequisite of this feature. The host PHP version no longer matters; the image pins PHP 8.4.27.
- **Tests**: they run on MySQL in `shop_keeper_test`, so behavior matches the real database. SQLite is no longer used by the backend.
- **Redis**: Laravel's cache store, so it holds the Google keys (R2) and the network-origin rate limits (R4), and it carries the mail queue (R6).
- **Laravel Boost**: `shop-keeper-backend/CLAUDE.md` asks for it before application changes, so it is the first setup task, run inside the `app` container.
