---

description: "Task list for the merchant account and shop backend"
---

# Tasks: Merchant Account and Shop

**Input**: Design documents from `specs/006-auth-shop/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.md, quickstart.md

**Tests**: Required by FR-031 and `shop-keeper-backend/CLAUDE.md`. Write each story's tests first and see them fail before implementing.

**Organization**: Tasks are grouped by user story, in priority order: the P1 stories (US1, US2, US7, US4, US5), then the P2 stories (US3, US6).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on an unfinished task)
- **[Story]**: The user story the task belongs to

## Path Conventions

- All source paths are relative to `shop-keeper-backend/`.
- Run PHP, Composer and Artisan in the containers: `docker compose exec app <command>`. The stack from `compose.yaml` must be up.
- Tests use Pest and run on MySQL (`shop_keeper_test`).
- In each module, controllers go in `Controllers/`, form requests in `Controllers/Requests/`, resources in `Controllers/Resources/`.
- No comments in code. Form Request rule messages are stable codes, such as `email.invalid`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Packages, configuration and test wiring

- [X] T001 Run `composer require laravel/boost --dev` and `php artisan boost:install`, then reread the generated guidelines in `CLAUDE.md` and `AGENTS.md`, keeping the Shop Keeper rules below the Boost block; updates `composer.json` and `composer.lock`
- [X] T002 Run `php artisan install:api --without-migration-prompt` to add `laravel/sanctum` ^4.3, keep its `personal_access_tokens` migration, delete the generated `routes/api.php` and its entry in `bootstrap/app.php` because modules own their routes, and keep `'expiration' => null` in `config/sanctum.php`
- [X] T003 Run `composer require firebase/php-jwt:^7.2`; updates `composer.json` and `composer.lock`
- [X] T004 [P] Add `AUTH_TEST_MODE=false`, `GOOGLE_CLIENT_IDS=` and `TERMS_VERSION=2026-11` to `.env.example` and the local `.env`, same keys in the same order
- [X] T005 [P] Create `config/shop_keeper.php` with `terms_version`, `google_client_ids` (comma-separated `GOOGLE_CLIENT_IDS` split into a list) and `auth_test_mode`
- [X] T006 Add a `Modules` test suite for `app/Modules/*/Tests` in `phpunit.xml`, and create `tests/Pest.php` binding `Tests\TestCase` and `RefreshDatabase` to `tests/Feature` and the module test folders; confirm with one throwaway module test that the binding works, then delete it

**Checkpoint**: `docker compose exec app php artisan test` passes and the new packages are installed.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Error format, account table, module wiring and the single path to a session

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T007 [P] Write error-format tests in `tests/Feature/Shared/ErrorFormatTest.php`: `request.not_found` (404), `request.method_not_allowed` (405), `request.not_json` (415), `request.throttled` (429 with `retry_after_seconds`), `server.error` (500 with no internal detail), `validation.failed` (422 with `fields` holding codes), and that no response contains user-facing text
- [X] T008 [P] Create `App\Shared\Errors\ApiException` carrying the code, HTTP status and optional `fields` and `retry_after_seconds` in `app/Shared/Errors/ApiException.php`
- [X] T009 Create `App\Shared\Errors\ApiErrorRenderer` in `app/Shared/Errors/ApiErrorRenderer.php` and register it in `bootstrap/app.php`, rendering `{ "error": { "code", "fields"?, "retry_after_seconds"? } }` for `ApiException`, validation, authentication (`auth.unauthenticated`), 404, 405, throttling and any other exception; report `QueryException` with its SQL state only (research R8)
- [X] T010 [P] Create `App\Shared\Http\RequireJson` in `app/Shared/Http/RequireJson.php`, answering a non-JSON body with `request.not_json`, and apply it to the `api` middleware group in `bootstrap/app.php`
- [X] T011 Create `app/Modules/Identity/IdentityServiceProvider.php`, which loads `app/Modules/Identity/database/migrations` and `app/Modules/Identity/routes.php` under the `api/v1` prefix and `api` middleware, and register it in `bootstrap/providers.php`
- [X] T012 Move `app/Models/User.php` to `app/Modules/Identity/Models/User.php` with `HasApiTokens`, hidden `password`, `pin_hash` and `remember_token`, and the new casts; move `database/factories/UserFactory.php` to `app/Modules/Identity/database/factories/UserFactory.php` with its `newFactory()` wiring, and update `config/auth.php` *(PIN parts superseded on 2026-11-02, see T058)*
- [X] T013 Add migration `app/Modules/Identity/database/migrations/2026_11_01_000001_add_identity_columns_to_users_table.php`: `name` string(120); `password` made nullable; `google_id` string, null, unique; `phone` string(16), null, not unique; `terms_version` string(20); `terms_accepted_at` timestamp; `pin_hash` string, null; `pin_failed_attempts` tiny unsigned, default 0; `pin_locked_until` timestamp, null; `password_failed_attempts` tiny unsigned, default 0; `password_locked_until` timestamp, null. Keep `email` unique and stored lowercase *(PIN parts superseded on 2026-11-02, see T057)*
- [X] T014 [P] Create `App\Modules\Identity\AuthTestMode` in `app/Modules/Identity/AuthTestMode.php` with the single check, true only when `auth_test_mode` is set and the environment is not `production` (research R7), with tests in `app/Modules/Identity/Tests/AuthTestModeTest.php` covering SC-008
- [X] T015 Define the named rate limiters in `app/Modules/Identity/IdentityServiceProvider.php`: `identity-sign-in`, 30 per minute per origin, for sign-in and code checks; `identity-email-send`, 10 per minute per origin and 3 per 15 minutes per lowercase address, for sign-up, resend and forgot-password; both end in `request.throttled`
- [X] T016 [P] Write tests in `app/Modules/Identity/Tests/InternationalPhoneTest.php`: `+216 20 123 456` and `0021620123456` both give `+21620123456`; `+33 6 12 34 56 78` and `(+1) 202-555-0143` are accepted; `20123456`, `+0123456`, `+12345` and 16-digit numbers give `phone.invalid`
- [X] T017 [P] Create `App\Modules\Identity\Rules\InternationalPhone` in `app/Modules/Identity/Rules/InternationalPhone.php`, with a normalizer that removes spaces, dashes and parentheses, turns a leading `00` into `+`, and requires `+`, then a first digit from 1 to 9, then 6 to 14 more digits (research R10)
- [X] T018 Write tests in `app/Modules/Identity/Tests/IssueDeviceSessionTest.php`: one token per account and `device_id`, a repeat replaces the earlier token, other devices keep theirs, `expires_at` stays null
- [X] T019 Create `App\Modules\Identity\Actions\IssueDeviceSession` in `app/Modules/Identity/Actions/IssueDeviceSession.php`, which in one transaction locks the user row, deletes tokens named with the `device_id`, and creates the new token with `name` = `device_id` (research R1)
- [X] T020 Create `App\Modules\Identity\Actions\FinishSignIn` in `app/Modules/Identity/Actions/FinishSignIn.php` as the only route to a session, calling `IssueDeviceSession`, and `SessionResource` and `AccountResource` in `app/Modules/Identity/Controllers/Resources/` matching the Session and Account shapes in `contracts/api.md`, with `shop` null for now

**Checkpoint**: Foundation ready; `php artisan migrate:fresh` and the tests above pass.

---

## Phase 3: User Story 1 - Create an account with Google (Priority: P1) 🎯 MVP

**Goal**: A first Google sign-in creates the account, records the terms and returns a session.

**Independent Test**: Send a valid first sign-in with `test:<email>`, then call `GET /api/v1/me` with the returned token and see the new account.

### Tests for User Story 1

- [X] T021 [P] [US1] Write feature tests in `app/Modules/Identity/Tests/GoogleSignInTest.php`: account created with terms version and time, with and without `phone`; `auth.terms_required`; `validation.failed` with `phone.invalid` and `device_id.invalid` and no account; `auth.google_invalid`; `auth.google_unavailable` (503); `auth.google_email_conflict` for a confirmed or unconfirmed match when Google is not authoritative, and for an account linked to another Google identity; linking on a Gmail or `hd` address; an unconfirmed sign-up discarded on an authoritative match; saved name refreshed and email kept when Google's values change; two concurrent first sign-ins, simulated with the conflicting row created first, end on one account
- [X] T022 [P] [US1] Write unit tests in `app/Modules/Identity/Tests/GoogleIdentityVerifierTest.php` with a locally generated RSA key and a faked keys response: bad signature, wrong `iss`, wrong `aud`, expired `exp` and false `email_verified` are refused; keys are cached for the `max-age` of `Cache-Control`; a failed key fetch gives `auth.google_unavailable`
- [X] T023 [P] [US1] Write feature tests in `app/Modules/Identity/Tests/AccountTest.php` for `GET /api/v1/me`: Account shape with `shop: null`, `auth.unauthenticated`, no hash in the response

### Implementation for User Story 1

- [X] T024 [P] [US1] Create the `GoogleIdentity` value object in `app/Modules/Identity/Google/GoogleIdentity.php`, with `sub`, `email`, `name` and an `isAuthoritativeForEmail()` that is true for `@gmail.com`, or for `email_verified` with `hd` (research R3)
- [X] T025 [US1] Create `GoogleIdentityVerifier` in `app/Modules/Identity/Google/GoogleIdentityVerifier.php` using `firebase/php-jwt` against `https://www.googleapis.com/oauth2/v3/certs`, checking RS256, `iss` (`accounts.google.com` or `https://accounts.google.com`), `aud` in `google_client_ids`, `exp` and `email_verified` (research R2)
- [X] T026 [US1] Create `TestModeVerifier` in `app/Modules/Identity/Google/TestModeVerifier.php` wrapping the verifier and answering `test:<email>` only when `AuthTestMode` is on, and bind it in `app/Modules/Identity/IdentityServiceProvider.php`
- [X] T027 [US1] Create `App\Modules\Identity\Actions\SignInWithGoogle` in `app/Modules/Identity/Actions/SignInWithGoogle.php`: find by `google_id`; otherwise apply FR-006 to a matching email; otherwise create with `terms_version` equal to the configured version; catch `UniqueConstraintViolationException` and answer as if the other request came first; end in `FinishSignIn`
- [X] T028 [US1] Create `GoogleSignInRequest` in `app/Modules/Identity/Controllers/Requests/GoogleSignInRequest.php` (`id_token` required; `device_id` required UUID; `terms_version` must equal the configured current version; `phone` optional, `InternationalPhone`) and `GoogleSignInController` in `app/Modules/Identity/Controllers/GoogleSignInController.php`
- [X] T029 [US1] Create `AccountController` in `app/Modules/Identity/Controllers/AccountController.php` and add `POST /auth/google` (limiter `identity-sign-in`) and `GET /me` (`auth:sanctum`) to `app/Modules/Identity/routes.php`

**Checkpoint**: US1 works alone through the test identity.

---

## Phase 4: User Story 2 - Sign in and out on a device (Priority: P1)

**Goal**: A session per device, with sign-out for the current device only.

**Independent Test**: Sign in on two `device_id`s, sign out of one, and check that only that session stops.

### Tests for User Story 2

- [X] T030 [P] [US2] Write feature tests in `app/Modules/Identity/Tests/DeviceSessionTest.php`: a returning sign-in on a new device does not ask for terms; a repeat on one device stops the first session; `POST /auth/logout` gives 204 and stops only the current session; a missing, invalid or signed-out session gives `auth.unauthenticated`; a limited origin gets `request.throttled`

### Implementation for User Story 2

- [X] T031 [US2] Create `App\Modules\Identity\Actions\SignOutDevice` in `app/Modules/Identity/Actions/SignOutDevice.php`, deleting the current access token, and `LogoutController` in `app/Modules/Identity/Controllers/LogoutController.php`
- [X] T032 [US2] Add `POST /auth/logout` (`auth:sanctum`) to `app/Modules/Identity/routes.php`

**Checkpoint**: US1 and US2 work together.

---

## Phase 5: User Story 7 - Sign up and sign in with email and password (Priority: P1)

**Goal**: A shopkeeper with no Google account signs up, confirms the address, signs in, and resets a forgotten password.

**Independent Test**: Sign up, confirm with `000000` in test mode, sign in with the password, reset it, and sign in with the new one.

### Tests for User Story 7

- [X] T033 [P] [US7] Write feature tests in `app/Modules/Identity/Tests/EmailSignUpTest.php`: register gives 202 with a queued code and no session; signing up again replaces the name, password and code of an unconfirmed row; `email.taken` on a confirmed address; confirm gives a session; `code.invalid` for wrong, expired (15 minutes), used and unknown codes; a code dies after 5 wrong tries; resend gives the same 202 for any address; email compared lowercase; `password.too_short` under 8 characters and refused over 72 bytes; `validation.failed` per field; the per-address limit
- [X] T034 [P] [US7] Write feature tests in `app/Modules/Identity/Tests/PasswordSignInTest.php`: session on the right password; `auth.email_unconfirmed` for an unconfirmed address with the right password; identical `auth.invalid_credentials` for a wrong password and an unknown email, with the dummy hash checked for the unknown email; `auth.password_locked` with `retry_after_seconds` after 5 failures even with the right password; a success resets the count; the lock ends after 15 minutes
- [X] T035 [P] [US7] Write feature tests in `app/Modules/Identity/Tests/PasswordResetTest.php`: forgot gives an identical 202 for a known and an unknown address, with mail queued only for the known one; reset gives 204, the new password works, all sessions end and the PIN stays on; `code.invalid` cases *(PIN parts superseded on 2026-11-02, see T058)*

### Implementation for User Story 7

- [X] T036 [P] [US7] Add migration `app/Modules/Identity/database/migrations/2026_11_01_000002_create_email_codes_table.php`: `id`; `user_id` FK users, cascade delete; `purpose` string(20), `email_confirmation` or `password_reset`; `code_hash` string; `failed_attempts` tiny unsigned, default 0, code dies at 5; `expires_at` timestamp, 15 minutes after creation; timestamps; unique on (`user_id`, `purpose`)
- [X] T037 [P] [US7] Create the `EmailCode` model in `app/Modules/Identity/Models/EmailCode.php`
- [X] T038 [P] [US7] Create queued `EmailCodeMail` in `app/Modules/Identity/Mail/EmailCodeMail.php`, on the Redis queue with retries and containing only the code and its purpose
- [X] T039 [US7] Create `App\Modules\Identity\Actions\SendEmailCode` in `app/Modules/Identity/Actions/SendEmailCode.php`: replace any code for that user and purpose, store the hash, queue the mail, and use `000000` when `AuthTestMode` is on
- [X] T040 [US7] Create `App\Modules\Identity\Actions\RegisterWithEmail` in `app/Modules/Identity/Actions/RegisterWithEmail.php`, including the unconfirmed-row replacement (research R11), and `ResendEmailCode` in `app/Modules/Identity/Actions/ResendEmailCode.php`
- [X] T041 [US7] Create `App\Modules\Identity\Actions\ConfirmEmail` in `app/Modules/Identity/Actions/ConfirmEmail.php`, counting wrong tries under `lockForUpdate`, deleting a used code, and ending in `FinishSignIn`
- [X] T042 [US7] Create `App\Modules\Identity\Actions\SignInWithPassword` in `app/Modules/Identity/Actions/SignInWithPassword.php`, with the dummy hash, counters and `password_locked_until` under `lockForUpdate` (research R4), ending in `FinishSignIn`
- [X] T043 [US7] Create `RequestPasswordReset` and `ResetPassword` in `app/Modules/Identity/Actions/`; `ResetPassword` deletes all the account's tokens and leaves `pin_hash` unchanged *(PIN parts superseded on 2026-11-02, see T058)*
- [X] T044 [US7] Create form requests in `app/Modules/Identity/Controllers/Requests/`: `name` required, at most 120 characters after trimming; `email` valid address, compared lowercase; `password` at least 8 characters, at most 72 bytes (the bcrypt input limit); `code` 6 digits; `device_id` required UUID, client-generated; `terms_version` must equal the configured current version; `phone` optional, `InternationalPhone`
- [X] T045 [US7] Create `EmailSignUpController`, `PasswordSignInController` and `PasswordResetController` in `app/Modules/Identity/Controllers/`, and add `POST /auth/email/register|confirm|resend|login` and `POST /auth/password/forgot|reset` with their limiters to `app/Modules/Identity/routes.php`

**Checkpoint**: Either sign-in method gives a session.

---

## Phase 6: User Story 4 - Create my shop (Priority: P1)

**Goal**: A signed-in merchant with no shop creates one and owns it.

**Independent Test**: Sign in as a merchant with no shop, `POST /api/v1/shops`, and see the shop returned with the merchant as owner.

### Tests for User Story 4

- [X] T046 [P] [US4] Write feature tests in `app/Modules/Shops/Tests/CreateShopTest.php`: 201 with `currency` `TND` and the caller as `owner_id`; `shop.already_exists` leaves the first shop unchanged; `name.required` (including only spaces), `name.too_long`, `business_type.invalid`; `auth.unauthenticated`; Arabic and accented names kept exactly; a concurrent duplicate, simulated with the row created first, leaves one shop

### Implementation for User Story 4

- [X] T047 [US4] Create `app/Modules/Shops/ShopsServiceProvider.php`, loading its migrations and `app/Modules/Shops/routes.php` under `api/v1`, and register it in `bootstrap/providers.php`
- [X] T048 [US4] Add migration `app/Modules/Shops/database/migrations/2026_11_01_000003_create_shops_table.php`: `id`, big integer; `owner_id` FK users, restrict delete, unique: one shop per merchant; `name` string(120), required, trimmed, any script; `business_type` string(20), `grocery`, `hardware`, `bakery`, `clothing`, `other`; `currency` char(3), set by the server to `TND`; `city` string(120), null, trimmed; `address` string(255), null, trimmed; timestamps
- [X] T049 [P] [US4] Create the `Shop` model in `app/Modules/Shops/Models/Shop.php` and `ShopResource` in `app/Modules/Shops/Controllers/Resources/ShopResource.php`, matching the Shop shape in `contracts/api.md`
- [X] T050 [US4] Create `App\Modules\Shops\Actions\CreateShop` in `app/Modules/Shops/Actions/CreateShop.php`, turning `UniqueConstraintViolationException` into `shop.already_exists`
- [X] T051 [US4] Create `CreateShopRequest` in `app/Modules/Shops/Controllers/Requests/CreateShopRequest.php` and `ShopController::store` in `app/Modules/Shops/Controllers/ShopController.php`, and add `POST /shops` (`auth:sanctum`) to `app/Modules/Shops/routes.php`

**Checkpoint**: The merchant journey from sign-in to a created shop works.

---

## Phase 7: User Story 5 - See my account and my shop, and only mine (Priority: P1)

**Goal**: `/me` shows the merchant's shop, and no merchant can reach another's.

**Independent Test**: Create two merchants with a shop each, and check that each reads their own and is refused the other's.

### Tests for User Story 5

- [X] T052 [P] [US5] Write feature tests in `app/Modules/Shops/Tests/ShowShopTest.php`: the owner reads their shop; another merchant's shop and an unknown id both give the same `shop.forbidden` with no shop data; `auth.unauthenticated`
- [X] T053 [P] [US5] Extend `app/Modules/Identity/Tests/AccountTest.php`: `/me` includes the merchant's shop, and shows `shop: null` when there is none

### Implementation for User Story 5

- [X] T054 [US5] Create `App\Modules\Shops\Actions\FindShopOfOwner` in `app/Modules/Shops/Actions/FindShopOfOwner.php`, and have `AccountResource` read the shop through it, so Identity never touches the `shops` table
- [X] T055 [US5] Create `ShopPolicy` in `app/Modules/Shops/Policies/ShopPolicy.php` and add `ShopController::show`, answering a foreign or missing shop with `shop.forbidden`; add `GET /shops/{shop}` to `app/Modules/Shops/routes.php`

**Checkpoint**: All P1 stories work; isolation is tested. This is the first complete delivery.

---

## Phase 8: User Story 3 - Protect my account with a code sent by email (Priority: P2)

**Goal**: Optional two-step sign-in: each Google or password sign-in emails a new 6-digit code, and the session comes only once that code is sent back.

**Independent Test**: Turn on two-step sign-in, sign in on a new device, see `code_required`, no session and a code in Mailpit, send the code and get a session. Then send 5 wrong codes and see `auth.pending_invalid`.

**Rework (2026-11-02)**: The owner replaced the PIN second factor with an emailed code (spec clarifications of 2026-11-02, research R13, constitution 3.1.0). The PIN tasks delivered on 2026-11-01 (former T056 to T067) are superseded; their code is still in `app/Modules/Identity` and is removed here. Do not extend it.

### Removal of the PIN

- [X] T056 [US3] Delete the PIN code: `CheckPin`, `ConfirmPin`, `EnablePin`, `ChangePin`, `DisablePin`, `RecoverPin` and `ResetPinByOperator` in `app/Modules/Identity/Actions/`; `ResetPinCommand` in `app/Modules/Identity/Console/` and its registration in `IdentityServiceProvider`; `NotWeakPin` in `app/Modules/Identity/Rules/`; the PIN form requests; `PinController` and `PinSignInController`; the `POST /auth/pin` and `/me/pin*` routes; and `PinManagementTest.php`, `PinSignInTest.php`, `PinRecoveryTest.php`
- [X] T057 [US3] Edit the 006 migrations, which are not released: in `2026_11_01_000001_add_identity_columns_to_users_table.php`, replace `pin_hash`, `pin_failed_attempts` and `pin_locked_until` with `two_factor_enabled_at` timestamp, null; in `2026_11_01_000004_create_pending_sign_ins_table.php`, add `code_hash` string and `failed_attempts` tiny unsigned, default 0. Then run `docker compose exec app php artisan migrate:fresh`
- [X] T058 [US3] Update `User` (hidden fields, casts, `hasTwoFactor()` instead of `hasPin()`), `AccountResource` (`two_factor_enabled` instead of `pin_enabled`), `ResetPassword` (leaves `two_factor_enabled_at` as it is) and the user factory; keep `RunCountedAttempt` for passwords only

### Tests for User Story 3

- [X] T059 [P] [US3] Write feature tests in `app/Modules/Identity/Tests/TwoFactorManagementTest.php`: turning on gives 204 and devices stay signed in; `two_factor.already_enabled`; `POST /me/two-factor/disable-code` gives 202 and queues a mail; `DELETE /me/two-factor` with the right code gives 204; a wrong, expired or used code gives `code.invalid` and the factor stays on; `two_factor.not_enabled`; no session ends; `auth.unauthenticated`
- [X] T060 [P] [US3] Write feature tests in `app/Modules/Identity/Tests/TwoFactorSignInTest.php`: Google and password sign-in both give `code_required` with no session and queue a mail; the right code gives a session; `code.invalid` for a wrong code; after 5 wrong codes `auth.pending_invalid`, even with the right code; `auth.pending_invalid` after 10 minutes, for a used or unknown pending sign-in; a code does not work on another pending sign-in; a resend kills the previous code and restarts the 10 minutes; resends are throttled; with the factor off, sign-in gives a session directly

### Implementation for User Story 3

- [X] T061 [US3] Extract code generation from `SendEmailCode` into one shared class used by every emailed code (6 digits, `000000` in test mode); add `EmailCodePurpose::TwoFactorDisable` (`two_factor_disable`) and a sign-in purpose for `EmailCodeMail`, with the subject in French and Arabic like the others
- [X] T062 [US3] Extend `FinishSignIn` in `app/Modules/Identity/Actions/FinishSignIn.php`: with two-step sign-in on, create the pending sign-in with a new code, store its hash, queue `EmailCodeMail`, and return `code_required`, `pending_sign_in` and `expires_in_seconds` (600); update `SignInResult` and `SessionResource`
- [X] T063 [US3] Create `ConfirmSignInCode` (locks the pending row, refuses an expired one, counts wrong codes and deletes the row at 5, deletes it once used, ends in `IssueDeviceSession`) and `ResendSignInCode` (new code, `failed_attempts` back to 0, new 10 minutes) in `app/Modules/Identity/Actions/`
- [X] T064 [US3] Create `EnableTwoFactor`, `SendTwoFactorDisableCode` (through `SendEmailCode`) and `DisableTwoFactor` (consumes the code through `ConsumeEmailCode`) in `app/Modules/Identity/Actions/`
- [X] T065 [US3] Create the form requests, `TwoFactorController` and `TwoFactorSignInController` in `app/Modules/Identity/Controllers/`, and add to `app/Modules/Identity/routes.php`: `POST /auth/two-factor` (limiter `identity-sign-in`), `POST /auth/two-factor/resend` (limiter `identity-email-send`), and `POST /me/two-factor`, `POST /me/two-factor/disable-code` (also `identity-email-send`) and `DELETE /me/two-factor` (`auth:sanctum`). Extend `identity-email-send` so it counts per account when the request has no `email`
- [X] T066 [US3] Update `postman/shop-keeper-006.postman_collection.json`: replace the "4. PIN" folder with turn on, sign in on a new device (`code_required`), send `000000`, ask for a disable code, turn off; remove the `pin`, `new_pin` and `recovered_pin` variables; check `two_factor_enabled` in the last `GET /me`. The owner reruns the collection by hand
- [X] T067 [US3] Update the journey in `tests/Feature/Shared/NoSecretsInLogsTest.php` to use two-step sign-in instead of the PIN; compare `contracts/api.md` with the routes; run `docker compose exec app php artisan test` (including `ApiCollectionTest`) and `docker compose exec app vendor/bin/pint`

**Checkpoint**: All 16 endpoints from `contracts/api.md` exist, and no PIN code is left.

---

## Phase 9: User Story 6 - Try the API with standard tools (Priority: P2)

**Goal**: An importable collection runs the whole journey with no edits.

**Independent Test**: Import the collection into an empty Postman workspace, set `base_url`, and run it in order against the local stack with `AUTH_TEST_MODE=true`.

### Tests for User Story 6

- [X] T068 [US6] Write a test in `tests/Feature/Shared/ApiCollectionTest.php` that compares every route under `api/v1` with the requests in the collection, so an endpoint cannot be added without its request (FR-028)

### Implementation for User Story 6

- [X] T069 [US6] Create `postman/shop-keeper-006.postman_collection.json` covering all 16 endpoints in journey order, with `base_url`, generated `device_id`, `test:<email>` as the Google identity, `000000` as the emailed code, and test scripts that store `token` and `pending_sign_in` for later requests
- [X] T070 [US6] Run the collection with `newman` from a temporary container against the local stack, with a fresh database, and fix any request until it passes without edits (SC-007)

**Checkpoint**: The collection, contract and endpoints agree.

---

## Phase 10: Polish & Cross-Cutting Concerns

**Purpose**: Checks that span the stories

- [X] T071 [P] Write `tests/Feature/Shared/NoSecretsInLogsTest.php`, running a sign-up, sign-in, PIN and reset journey and asserting that the log holds no name, email, phone, password, PIN, code, token or Google identity (FR-030, SC-005) *(PIN parts superseded on 2026-11-02, see T067)*
- [X] T072 [P] Update the module list and Postman section of `README.md` if the delivered result differs, and record the two new packages and why they are needed for the PR description
- [X] T073 Compare `contracts/api.md` with the delivered routes, responses and codes, and fix any drift in the same change
- [X] T074 Run `docker compose exec app php artisan test` and `docker compose exec app vendor/bin/pint --test`
- [X] T075 Run every scenario in `quickstart.md` on the Docker stack, including the production refusal (scenario 16) and the log search (scenario 17). The real Google check needs OAuth client IDs from the owner; if they are not available, report it as not done

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Needs the running Docker stack.
- **Foundational (Phase 2)**: Depends on Setup. Blocks every story.
- **US1, US2, US7, US4, US5**: Depend on Foundational.
- **Polish (Phase 10)**: Depends on every story.

### User Story Dependencies

- **US1 (P1)**: No dependency on other stories.
- **US2 (P1)**: Needs a sign-in, so it comes after US1.
- **US7 (P1)**: Only Foundational. It can run in parallel with US1.
- **US4 (P1)**: Needs a signed-in merchant, which tests get from a factory, so it only needs Foundational to be tested. Its journey needs US1 or US7.
- **US5 (P1)**: Depends on US4 for the shop.
- **US3 (P2)**: Two-step sign-in needs both US1 and US7.
- **US6 (P2)**: Depends on every other story, because it covers all endpoints.

### Within Each User Story

- Tests are written first and must fail before implementation.
- Migrations and models, then actions, then requests and controllers, then routes.

### Parallel Opportunities

- T004 and T005 in Setup.
- T007, T008, T010, T014 and T016 with T017 in Foundational. T009 waits for T008.
- Once Foundational is done, US1 and US7 can run in parallel. Both add to `app/Modules/Identity/routes.php`, so merge that file with care.
- Within a story, all test tasks marked [P].

---

## Parallel Example: User Story 7

```bash
Task: "Write feature tests in app/Modules/Identity/Tests/EmailSignUpTest.php"
Task: "Write feature tests in app/Modules/Identity/Tests/PasswordSignInTest.php"
Task: "Write feature tests in app/Modules/Identity/Tests/PasswordResetTest.php"

Task: "Add migration for email_codes"
Task: "Create the EmailCode model"
Task: "Create queued EmailCodeMail"
```

---

## Implementation Strategy

### MVP First

1. Complete Setup and Foundational.
2. Complete US1, then US2.
3. Complete US4 and US5.
4. **Stop and validate**: Google sign-in to a created shop, with isolation tested. Run quickstart scenarios 1, 2, 7, 8, 13 and 14.

### Incremental Delivery

1. MVP: a merchant signs in with Google and creates a shop.
2. Add US7: shopkeepers with no Google account can join. All P1 stories are done.
3. Add US3: optional two-step sign-in with an emailed code.
4. Add US6, then Polish: the collection and final checks.

All of this ships in one pull request with one commit, as the repository rules require.

---

## Notes

- [P] tasks touch different files and depend on no unfinished task.
- See each test fail before implementing it.
- Any endpoint change updates `contracts/api.md` and the tests in the same change.
- Log no personal data, and put no secret in code or git.
