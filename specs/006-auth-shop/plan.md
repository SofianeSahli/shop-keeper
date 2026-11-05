# Implementation Plan: Merchant Account and Shop

**Branch**: `006-auth-shop` | **Date**: 2026-11-01 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/006-auth-shop/spec.md`

## Summary

Turn the Laravel skeleton into the first real API: accounts by Google sign-in or confirmed email and password, one Sanctum token per device, optional two-step sign-in with a new 6-digit code emailed at each sign-in, one shop per merchant with strict isolation, one error format with stable codes, and a Postman collection. Backend only; `shop-keeper-mobile/` is not touched.

## Technical Context

**Language/Version**: PHP 8.4.27 (pinned in `docker/php/Dockerfile`), Laravel 13

**Primary Dependencies**: `laravel/sanctum` ^4.3 (required by the constitution), `firebase/php-jwt` ^7.2 (to verify Google ID tokens), `laravel/boost` dev (required by the backend guide)

**Storage**: MySQL 8 everywhere; tests use the `shop_keeper_test` database. Redis for cache, rate limits and the queue

**Testing**: Pest feature and unit tests through `docker compose exec app php artisan test`; Pint

**Target Platform**: Docker Compose (see `shop-keeper-backend/compose.yaml`), which must be running before this feature starts

**Project Type**: REST API

**Performance Goals**: Ordinary API latency. Password and code checks are deliberately slow hashes.

**Constraints**: Configuration only through `.env`; no provider-specific code; no personal data in logs; stable codes and no user-facing text

**Scale/Scope**: 16 endpoints, 2 modules, 4 tables touched, pilot-size traffic

## Constitution Check

*Version 3.1.0. Checked before research and again after design, and again after the 2026-11-02 change of second factor.*

| Principle | Status | How |
|-----------|--------|-----|
| I. Monorepo boundaries | Pass | Only `shop-keeper-backend/`. |
| II. Mobile experience | N/A | No screen in this feature. |
| III. Internationalization | Pass | Backend returns stable codes only. Translations come with the mobile spec. |
| IV. Secure backend | Pass | Versioned REST, server validation, Sanctum, `ShopPolicy`, thin controllers, shop isolation, Postman. |
| V. Testability | Pass | Success, validation, unauthenticated and cross-shop tests per endpoint. |
| VI. Offline-first | Pass | Sessions never expire, so an offline merchant stays signed in. No shop data is created in this feature. |
| VII. Product scope | Pass | Roadmap order respected. |
| VIII. Data and privacy | Pass | Terms acceptance recorded; each merchant sees only their shop; logs carry no personal data. |
| Authentication constraint | Pass | Google or verified email with password, optional emailed one-time code as second factor, rate limited, hashes only. |

No violations, so Complexity Tracking is empty.

## Project Structure

### Documentation (this feature)

```text
specs/006-auth-shop/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── api.md
└── tasks.md                                      # /speckit-tasks
```

### Source Code

```text
shop-keeper-backend/
├── app/
│   ├── Shared/
│   │   ├── Errors/          # ApiException, renderer, field-code handling
│   │   └── Http/            # RequireJson middleware
│   ├── Providers/
│   └── Modules/
│       ├── Identity/
│       │   ├── Actions/     # SignInWithGoogle, RegisterWithEmail, ConfirmEmail,
│       │   │                # SendEmailCode, ResendEmailCode, SignInWithPassword,
│       │   │                # FinishSignIn, ConfirmSignInCode, ResendSignInCode,
│       │   │                # IssueDeviceSession, SignOutDevice,
│       │   │                # RequestPasswordReset, ResetPassword,
│       │   │                # EnableTwoFactor, SendTwoFactorDisableCode,
│       │   │                # DisableTwoFactor
│       │   ├── Google/      # GoogleIdentityVerifier, GoogleIdentity, TestModeVerifier
│       │   ├── Controllers/ # controllers, form requests, resources
│       │   ├── Mail/        # EmailCodeMail, queued
│       │   ├── Models/      # User, EmailCode, PendingSignIn
│       │   ├── Rules/       # InternationalPhone
│       │   ├── database/migrations/
│       │   ├── routes.php
│       │   ├── IdentityServiceProvider.php
│       │   └── Tests/
│       └── Shops/
│           ├── Actions/     # CreateShop, FindShopOfOwner
│           ├── Controllers/
│           ├── Models/      # Shop
│           ├── Policies/    # ShopPolicy
│           ├── database/migrations/
│           ├── routes.php
│           ├── ShopsServiceProvider.php
│           └── Tests/
├── bootstrap/app.php, bootstrap/providers.php
├── config/shop_keeper.php   # terms version, Google client ids, test mode
├── phpunit.xml              # adds app/Modules/*/Tests
├── postman/                 # shop-keeper-006.postman_collection.json
└── .env.example             # the new keys
```

**Structure Decision**: The module layout from `shop-keeper-backend/CLAUDE.md`. `App\Models\User` moves to `App\Modules\Identity\Models\User`, and `config/auth.php` follows. Each module has a service provider that loads its migrations and routes under `api/v1`. `/me` reads the shop by calling the Shops action `FindShopOfOwner`, so Identity never touches the `shops` table.

## Design Notes

- **One path to a session**: every sign-in ends in `FinishSignIn`, which calls `IssueDeviceSession`, or, when two-step sign-in is on, creates a pending sign-in and emails its code, so the second factor cannot be skipped on any route.
- **Google verification**: `GoogleIdentityVerifier` is bound in the container. `TestModeVerifier` wraps it, and answers `test:<email>` only when the one test-mode check passes (research R7).
- **Concurrency**: unique indexes on `users.email`, `users.google_id`, `shops.owner_id` and `email_codes (user_id, purpose)` are the final guard. Actions catch `UniqueConstraintViolationException` and answer as if the other request came first. Attempt counters and token replacement run under `lockForUpdate`.
- **Tests on MySQL**: tests run on the same database engine as the app, so unique indexes, foreign keys and row locks behave as in real use.
- **Rate limits**: named limiters on routes: sign-in and code checks 30 per minute per origin; sign-up, resend and forgot-password 10 per minute per origin and 3 per 15 minutes per address. Sign-in code resends and the disable code use the same sending limiter, keyed by the account when the request has no email.
- **Dependencies to justify in the PR**: `firebase/php-jwt` (research R2) and `laravel/sanctum` (constitution).

## Spec Changes Made During Planning

- FR-006 and its edge cases now require Google to be authoritative for an address before linking (research R3).
- The "email cannot be sent" edge case now retries in the background (research R6), to keep FR-037's identical answers.
- 2026-11-02: the PIN second factor is replaced by a new one-time code emailed at each sign-in (owner decision, constitution 3.1.0, research R13). FR-013 to FR-019, User Story 3, the contract and the data model changed with it. The PIN code already written is removed in tasks Phase 8.

## Complexity Tracking

No constitution violations.
