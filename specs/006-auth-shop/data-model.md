# Data Model: Merchant Account and Shop

Target database: MySQL 8, tests included. The skeleton's `users` migration is already merged, so `users` changes through a new migration.

## users (module Identity)

The merchant account. Altered from the skeleton table.

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `name` | string(120) | required, trimmed |
| `email` | string | required, unique, stored lowercase |
| `email_verified_at` | timestamp, null | null means an unconfirmed sign-up |
| `password` | string, null (changed) | hash; null for Google-only accounts |
| `google_id` | string, null | unique; Google's `sub` |
| `phone` | string(16), null | E.164, such as `+21620123456`; not unique |
| `terms_version` | string(20) | |
| `terms_accepted_at` | timestamp | |
| `two_factor_enabled_at` | timestamp, null | null means two-step sign-in is off |
| `password_failed_attempts` | tiny unsigned, default 0 | consecutive |
| `password_locked_until` | timestamp, null | |
| `remember_token`, timestamps | | from the skeleton |

**States**

```text
(none) --email sign-up--> unconfirmed --right code--> confirmed
(none) --first Google sign-in--> confirmed
unconfirmed --sign-up again--> unconfirmed (name, password and code replaced)
unconfirmed --Google, authoritative--> row removed, new confirmed account
confirmed --Google, authoritative, email match--> confirmed + google_id set
```

Only confirmed accounts can get a session. Two-step sign-in is independent: off → on (turned on), on → off (turned off with an emailed code). A password reset leaves it as it is.

## personal_access_tokens (Sanctum)

The device session. Vendor table, unchanged.

- `name` holds the `device_id`. One row per account and `device_id`, enforced by the action that issues tokens (research R1).
- `expires_at` stays null. Deleted at sign-out, at replacement, and by a password reset.

## email_codes (module Identity)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `user_id` | FK users, cascade delete | |
| `purpose` | string(20) | `email_confirmation`, `password_reset` or `two_factor_disable` |
| `code_hash` | string | hash |
| `failed_attempts` | tiny unsigned, default 0 | code dies at 5 |
| `expires_at` | timestamp | 15 minutes after creation |
| timestamps | | |

Unique on (`user_id`, `purpose`): a new code replaces the old one. A used code is deleted.

## pending_sign_ins (module Identity)

A sign-in that passed Google or the password and waits for its emailed code. Each row carries its own code, so sign-in codes are not stored in `email_codes`.

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `user_id` | FK users, cascade delete | |
| `device_id` | uuid string | |
| `token_hash` | char(64), unique | SHA-256 of a random token |
| `code_hash` | string | hash of the emailed 6-digit code |
| `failed_attempts` | tiny unsigned, default 0 | the row is deleted at 5 |
| `expires_at` | timestamp | 10 minutes after the latest code was sent |
| timestamps | | |

Deleted once used or after 5 wrong codes. A resend replaces `code_hash`, resets `failed_attempts` and restarts `expires_at`. An expired row is refused and removed when it is next presented.

## shops (module Shops)

| Column | Type | Rules |
|--------|------|-------|
| `id` | bigint, auto | |
| `owner_id` | FK users, restrict delete | unique: one shop per merchant |
| `name` | string(120) | required, trimmed; any script |
| `business_type` | string(20) | `grocery`, `hardware`, `bakery`, `clothing`, `other` |
| `currency` | char(3) | set by the server to `TND` |
| `city` | string(120), null | trimmed |
| `address` | string(255), null | trimmed |
| timestamps | | |

The MySQL default charset `utf8mb4` keeps Arabic text exact.

## Validation

| Field | Rule |
|-------|------|
| `name` | required, at most 120 characters after trimming |
| `email` | valid address, compared lowercase |
| `password` | at least 8 characters, at most 72 bytes (the bcrypt input limit) |
| `phone` | optional; see research R10 |
| `device_id` | required UUID, client-generated |
| `terms_version` | must equal the configured current version |
| `code` | 6 digits |
| `pending_sign_in` | required string |
