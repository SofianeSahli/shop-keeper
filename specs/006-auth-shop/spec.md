# Feature Specification: Merchant Account and Shop

**Feature Branch**: `006-auth-shop`

**Created**: 2026-11-01

**Status**: Draft

**Input**: User description: "for spec 006 we need to make the auth system with shop creation system start with specify". Scope from the phase 1 roadmap in the root `CLAUDE.md`: merchant account, creation and reading of the merchant's shop, versioned REST API, consistent errors with stable codes, importable API collection, tests including cross-shop isolation. Backend only.

## Context

Features 001 to 005 run on a single phone with no account. From this feature on, shop data lives on the backend, so the backend needs to know who is calling and which shop they own. This feature is the first step: a merchant signs up with their Google account or with an email and password, signs in on their devices, can protect new sign-ins with a PIN, and creates their shop. Every later feature (products, stock, sales, customer credit, dashboard) hangs off that account and that shop.

This feature ships no screen. Its users reach it through the API; the merchant app connects to it in a later spec. The stories below describe what the merchant app will be able to do, and each one can be exercised directly with an API testing tool.

## Clarifications

### Session 2026-11-01

- Q: How long is a PIN? → A: 6 digits.
- Q: How does a merchant recover a forgotten PIN? → A: Google sign-in becomes the login and the PIN becomes an optional second factor. The constitution is amended to 3.0.0 accordingly, replacing the phone number plus PIN rule.
- Q: Is the phone number required? → A: No, it is optional for now.
- Q: Which phone numbers are accepted? → A: Any country. The number carries an international dialing code, chosen by the merchant, not only `+216`.
- Q: What about a shopkeeper with no Google account? → A: Classic email and password sign-up is allowed too. The constitution's ban on it is removed in the same 3.0.0 amendment.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Create an account with Google (Priority: P1)

A shopkeeper signs in with their Google account for the first time and accepts the terms of use, and may give their phone number with its international dialing code. The account is created with the name and email from Google, and they are signed in on that device with no second step.

**Why this priority**: Nothing else exists without an account, and the terms of use must be accepted at sign-up (constitution VIII).

**Independent Test**: Send a valid first sign-in, then call the account endpoint with the session it returned and see the new account.

**Acceptance Scenarios**:

1. **Given** a valid Google identity with no account, **When** the shopkeeper signs in with the terms accepted, with or without a phone number, **Then** the account is created, the accepted terms version and time are recorded, and a session for that device is returned.
2. **Given** a valid Google identity with no account, **When** the terms acceptance is missing or the phone number given is not a valid international number, **Then** the request is refused with a stable code for each invalid item and no account is created.
3. **Given** the same phone number written differently (`+216 20 123 456`, `0021620123456`), **When** it is sent, **Then** it is saved in one standard form.
4. **Given** a Google identity that is expired, forged, issued for another application, or has an unverified email, **When** someone signs in with it, **Then** the sign-in is refused with a stable code and no account is created.

---

### User Story 2 - Sign in and out on a device (Priority: P1)

A merchant signs in with Google on a phone or tablet, stays signed in on that device, and can sign out of it. Each device has its own session, so signing out of one leaves the others working.

**Why this priority**: The merchant must get back into their account on any device, and each device must be controllable on its own.

**Independent Test**: Sign in from two devices, sign out of one, and check that its session stops working while the other still works.

**Acceptance Scenarios**:

1. **Given** an account without a PIN, **When** the merchant signs in with Google from a new device, **Then** a session for that device is returned and the terms are not asked again.
2. **Given** a device that already has a session, **When** the merchant signs in again from it, **Then** the new session replaces the old one, which stops working.
3. **Given** a signed-in device, **When** the merchant signs out, **Then** that device's session stops working and the other devices stay signed in.
4. **Given** a missing, invalid or signed-out session, **When** any protected request is made, **Then** it is refused with the same stable code.

---

### User Story 3 - Protect my account with a PIN (Priority: P2)

A merchant who wants more protection turns on a 6-digit PIN. From then on, a sign-in from a new device needs both the normal sign-in and the PIN, so someone who gets into their Google account or learns their password still cannot open the shop. Devices already signed in keep working.

**Why this priority**: It is optional and the account works without it, but it protects customer credit data for merchants who share a Google account or a phone.

**Independent Test**: Turn on a PIN, sign in from a new device and see that the normal sign-in alone gives no session, then send the PIN and get one. Then send wrong PINs until the block starts.

**Acceptance Scenarios**:

1. **Given** a signed-in merchant with no PIN, **When** they set a valid PIN, **Then** the second factor is on and their signed-in devices keep working.
2. **Given** an account with a PIN, **When** the merchant signs in with Google or with their password from a new device, **Then** no session is returned yet, the answer says a PIN is needed, and a session is returned only after the right PIN is sent within 5 minutes.
3. **Given** 5 wrong PINs in a row on an account, **When** another PIN is sent within 15 minutes, **Then** it is refused with a stable code and the time left, even if it is right.
4. **Given** a weak PIN (one repeated digit, or a straight run such as `123456`), **When** the merchant sets it, **Then** it is refused with a stable code.
5. **Given** a merchant with a PIN, **When** they change or turn off the PIN with the current PIN, **Then** the change applies; with a wrong current PIN, it is refused and counts as a wrong PIN.
6. **Given** a merchant who forgot their PIN but is signed in on a device, **When** they confirm their identity with a fresh sign-in on that device, **Then** they can set a new PIN, and every other device is signed out.
7. **Given** a merchant who forgot their PIN and has no signed-in device, **When** the operator confirms who they are and resets the PIN, **Then** the second factor is off and all their sessions have ended.

---

### User Story 4 - Create my shop (Priority: P1)

A signed-in merchant with no shop creates it by giving its name and type of business, and optionally its city and address. The merchant is its owner.

**Why this priority**: Every piece of shop data in later features belongs to a shop, so the shop must exist first.

**Independent Test**: Sign in as a merchant with no shop, create the shop, and see it returned with the merchant as owner.

**Acceptance Scenarios**:

1. **Given** a signed-in merchant with no shop, **When** they send a valid shop name and business type, **Then** the shop is created with the merchant as owner and Tunisian dinar as its currency, and is returned.
2. **Given** a merchant who already owns a shop, **When** they try to create another, **Then** the request is refused with a stable code and the existing shop is unchanged.
3. **Given** a missing name, a name that is too long, or an unknown business type, **When** the merchant creates the shop, **Then** it is refused with a stable code for each invalid field and no shop is created.
4. **Given** two identical creation requests sent at the same moment, for example after a network retry, **When** both reach the backend, **Then** exactly one shop exists.

---

### User Story 5 - See my account and my shop, and only mine (Priority: P1)

A signed-in merchant reads their account and their shop. The account answer says whether they already have a shop, so the app can send them to shop creation or to the main screen. No merchant can reach another merchant's shop.

**Why this priority**: Isolation between shops is a core promise of the product (constitution IV and VIII), and all later features copy this pattern.

**Independent Test**: Create two merchants with a shop each, and check that each reads their own shop and is refused the other's.

**Acceptance Scenarios**:

1. **Given** a merchant with a shop, **When** they read their account, **Then** the answer includes their name, email, phone number if given, whether a PIN is on, and their shop, and never the PIN or its hash.
2. **Given** a merchant with no shop, **When** they read their account, **Then** the answer shows clearly that there is no shop yet.
3. **Given** two merchants with one shop each, **When** the first asks for the second's shop, **Then** the request is refused with a stable code and nothing about that shop is revealed.

---

### User Story 6 - Try the API with standard tools (Priority: P2)

A developer or the owner imports the published API collection into Postman and runs the whole journey: sign in, create the shop, read it, turn on a PIN, sign out. Every failure has the same shape and a stable code, which the apps will translate into French, Arabic and English.

**Why this priority**: The merchant app connects in a later spec, so this collection is how the feature is checked until then. It adds no merchant-facing value on its own.

**Independent Test**: Import the collection into an empty Postman workspace, set the server address, and run every request in order against a local backend without editing any of them.

**Acceptance Scenarios**:

1. **Given** the collection and a local backend with the test identity on, **When** the requests are run in order, **Then** each succeeds, and the session is reused by the later requests without being copied by hand.
2. **Given** any failed request in this feature, **When** the response is read, **Then** it has the common error shape with a stable machine code and no user-facing text.
3. **Given** a change to an endpoint, **When** it is merged, **Then** the collection and the contract file change with it.

---

### User Story 7 - Sign up and sign in with email and password (Priority: P1)

A shopkeeper with no Google account, or who prefers not to use it, signs up with their name, email address and a password, accepts the terms of use, and confirms the address with a code sent to it. They can then sign in with email and password, and reset the password by email if they forget it.

**Why this priority**: Without it, a shopkeeper with no Google account cannot use the product.

**Independent Test**: Sign up with an email, confirm the code, sign in with the password, then reset the password and sign in with the new one.

**Acceptance Scenarios**:

1. **Given** an email with no account, **When** the shopkeeper signs up with a name, that email, a valid password and the terms accepted, **Then** a 6-digit code is sent to the address and no session is returned yet.
2. **Given** a pending sign-up, **When** the right code is sent within 15 minutes, **Then** the email is confirmed and a session for that device is returned.
3. **Given** a sign-up with a wrong or expired code, **When** it is sent, **Then** it is refused with a stable code; a new code can be requested, and sign-in stays closed until the email is confirmed.
4. **Given** an email that already has a confirmed account, **When** someone signs up with it, **Then** the sign-up is refused with a stable code and the account is unchanged.
5. **Given** a wrong password or an unknown email, **When** someone signs in, **Then** the answer is the same in both cases.
6. **Given** 5 wrong passwords in a row on an account, **When** another attempt is made within 15 minutes, **Then** it is refused with a stable code and the time left, even if the password is right.
7. **Given** a forgotten password, **When** the merchant asks for a reset, **Then** the answer is the same whether or not the email has an account, and an account's owner gets a 6-digit code by email.
8. **Given** a valid reset code within 15 minutes, **When** the merchant sets a new password, **Then** the new password works and all their sessions have ended. A PIN, if on, stays on.

### Edge Cases

- **Same person, both methods**: a Google sign-in whose email matches a confirmed account opens that account and links Google to it, but only if Google is authoritative for that address (a Gmail address, or a verified address on a Google hosted domain). Otherwise it is refused with a stable code and the merchant signs in with their password, so a Google account holding someone else's address cannot take over their account.
- **Someone starts a sign-up with another person's email**: the sign-up stays unconfirmed and gives no access. If the real owner later signs in with Google, and Google is authoritative for the address, the unconfirmed sign-up and its password are discarded.
- **Google email or name changes**: the account is tied to Google's permanent account identifier, so it still opens; the saved name is refreshed at sign-in and the saved email stays as it is.
- **Google account lost or closed**: the merchant can reset a password through their email address and sign in with it, if they still have that mailbox.
- **Email cannot be sent**: sending happens in the background and is retried, so the answer to the request stays the same. The merchant can ask for a new code.
- **Google identity check unavailable**: if Google's keys cannot be obtained, the sign-in is refused with a retryable stable code, and existing sessions keep working.
- **Replayed Google identity**: an identity already used can only be replayed until its short expiry; a new session replaces the same device's session and creates nothing else.
- **PIN guessing on many accounts**: repeated failed attempts from one network origin are limited as well as those on one account.
- **Someone blocks a merchant on purpose**: only a person who already passed Google sign-in can send PINs, and the block lasts 15 minutes at most. Sessions already open keep working.
- **Two first sign-ins at the same moment**: exactly one account exists, and both requests end with a session for the same account.
- **Merchant offline for weeks**: sessions do not expire by time, so a shopkeeper who stays offline is not signed out when they come back. A first sign-in on a device always needs a network.
- **Shop name in Arabic or French**: names keep their exact text, including Arabic script and accents.
- **Leading and trailing spaces in names**: they are removed before saving; a name that is only spaces counts as missing.
- **Malformed request or unknown address**: answered with the common error shape, not an HTML page.
- **Server failure**: answered with the common error shape and a stable code, with no internal detail.

## Requirements *(mandatory)*

### Functional Requirements

**Accounts**

- **FR-001**: The system MUST let a person sign in with a Google identity, verified by the system itself, and MUST refuse one that is expired, forged, issued for another application, or has an unverified email.
- **FR-002**: The system MUST create an account at the first sign-in of a Google identity, using Google's name and email, and MUST require acceptance of the terms of use. A phone number MUST be optional and, when given, MUST be an international number with its dialing code, from any country.
- **FR-003**: The system MUST record the accepted terms version and the acceptance time.
- **FR-004**: The system MUST allow one account per Google account, tied to Google's permanent identifier, and one account per email address, even under concurrent requests.
- **FR-005**: The system MUST accept common spellings of an international number (spaces, dashes, parentheses, and `+` or `00` before the dialing code), MUST refuse a number with no dialing code, and MUST save one standard form.
- **FR-006**: When Google is authoritative for its email (a Gmail address, or a verified address on a Google hosted domain), a Google sign-in that matches a confirmed account MUST open that account and link Google to it, and one that matches an unconfirmed sign-up MUST discard that sign-up. When Google is not authoritative, a match on either MUST be refused with a stable code.

**Sessions**

- **FR-007**: The system MUST return a session for the device named in the sign-in, using a device identifier sent by the client.
- **FR-008**: The system MUST keep at most one session per device for an account, and a new sign-in from a device MUST replace that device's previous session.
- **FR-009**: Sessions MUST stay valid until the merchant signs out, the session is replaced, or a PIN recovery ends it; they MUST NOT expire by time.
- **FR-010**: The system MUST let a merchant sign out of the current device without affecting their other devices.
- **FR-011**: The system MUST limit sign-in attempts per network origin.
- **FR-012**: The system MUST refuse every protected request that has no valid session, with one stable code.

**PIN second factor**

- **FR-013**: The PIN MUST be optional. A signed-in merchant MUST be able to turn it on, change it and turn it off; changing or turning it off MUST require the current PIN.
- **FR-014**: A PIN MUST be exactly 6 digits, and the system MUST refuse one made of one repeated digit or of a straight ascending or descending run.
- **FR-015**: When a PIN is on, a Google or password sign-in MUST NOT return a session until the right PIN is sent. The pending sign-in MUST expire after 5 minutes.
- **FR-016**: The system MUST block PIN attempts on an account for 15 minutes after 5 consecutive wrong PINs, and MUST return the time left. A right PIN MUST reset the count.
- **FR-017**: The system MUST store only a one-way hash of the PIN, and MUST NOT return it in any response.
- **FR-018**: A merchant signed in on a device MUST be able to set a new PIN after a fresh Google or password sign-in on that device, without the current PIN. This MUST end the sessions of their other devices.
- **FR-019**: An operator MUST be able to reset a merchant's PIN through a server-side command after checking their identity. This MUST turn off the PIN and end all their sessions. It MUST NOT be reachable through the API.

**Shop**

- **FR-020**: The system MUST let a signed-in merchant with no shop create one, with a required name (up to 120 characters), a required business type, and optional city and address.
- **FR-021**: Business type MUST be one of: grocery, hardware, bakery, clothing, other.
- **FR-022**: The system MUST set the shop's currency to Tunisian dinar, and MUST make the creating merchant its owner.
- **FR-023**: A merchant MUST own at most one shop, even under concurrent requests.
- **FR-024**: The system MUST let a signed-in merchant read their account, including their shop or a clear statement that they have none, and read their own shop.
- **FR-025**: The system MUST refuse any access to a shop by a merchant who does not own it, and MUST reveal nothing about that shop.

**API conventions**

- **FR-026**: Every endpoint MUST sit under a versioned API path and MUST accept and return JSON only.
- **FR-027**: Every failure, including validation, unknown addresses and server errors, MUST use one error shape with a stable machine code, plus a code per field for validation failures, and MUST contain no user-facing text.
- **FR-028**: The feature MUST ship an API collection, importable into Postman, that covers every endpoint and runs the full journey in order, and it MUST be kept in sync with the endpoints.
- **FR-029**: Local and test environments MUST offer a test identity that stands in for Google, and a way to read emailed codes without a mailbox, so the collection and automated tests can run. It MUST be off unless switched on by configuration, and it MUST refuse to work in production.
- **FR-030**: Logs MUST NOT contain names, emails, phone numbers, passwords, PINs, emailed codes, session tokens or Google identities.
- **FR-031**: Every endpoint MUST have automated tests for success, validation errors and unauthenticated access, and every endpoint that touches shop data MUST also be tested for access to another merchant's shop.

**Email sign-up**

- **FR-032**: The system MUST let a person sign up with a name, an email address, a password and acceptance of the terms of use, with an optional phone number under the same rule as FR-002. Email addresses MUST be compared without regard to case.
- **FR-033**: A password MUST have at least 8 characters, with no rule on character types.
- **FR-034**: The system MUST send a 6-digit code to confirm the address, valid for 15 minutes, and MUST NOT give a session or allow password sign-in until the address is confirmed.
- **FR-035**: The system MUST let a merchant sign in with email, password and a device identifier, and MUST answer a wrong password and an unknown email identically.
- **FR-036**: The system MUST block password sign-in on an account for 15 minutes after 5 consecutive wrong passwords, and MUST return the time left. A right password MUST reset the count.
- **FR-037**: The system MUST let a merchant reset a forgotten password with a 6-digit emailed code valid for 15 minutes. The request MUST be answered identically whether or not the email has an account. A reset MUST end all the account's sessions and MUST NOT change the PIN.
- **FR-038**: The system MUST store only one-way hashes of passwords and emailed codes, and MUST NOT return either in any response. A code MUST work once and MUST stop working after 5 wrong tries.
- **FR-039**: The system MUST limit sign-up, code and reset requests per email address and per network origin.

### Key Entities

- **Merchant account**: a person who uses the app. Name, email (unique) and whether it is confirmed, optional Google permanent identifier (unique), optional password hash, optional standard phone number, accepted terms version and acceptance time, optional PIN hash.
- **Device session**: a merchant's signed-in state on one device. Belongs to one account, identified by the device, replaced at the next sign-in from that device, ended by sign-out or PIN recovery.
- **Pending sign-in**: a Google or password sign-in waiting for its PIN. Belongs to one account and one device, and expires after 5 minutes.
- **Email code**: a one-time 6-digit code for confirming an address or resetting a password. Belongs to one address and one purpose, and expires after 15 minutes.
- **Shop**: a merchant's business. Name, business type, currency, optional city and address, and its owner. Later features attach their data to it.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A merchant can go from no account to a created shop in 3 requests or fewer with Google (first sign-in, create the shop, read it), and in 4 with email (sign up, confirm the code, create the shop, read it).
- **SC-002**: In testing, 100% of attempts by one merchant to reach another merchant's shop are refused and reveal no shop data.
- **SC-003**: With a PIN on, 100% of sign-in attempts from a new device without the right PIN end with no session.
- **SC-004**: At most 5 wrong PINs and 5 wrong passwords per account are evaluated in any 15-minute window.
- **SC-005**: A password, PIN or emailed code cannot be read from any stored record, response or log; checks of all three find none.
- **SC-006**: 100% of failure responses in the feature's test suite use the common error shape with a stable code and no user-facing text.
- **SC-007**: A person who has never seen the project can import the collection and run the full journey in under 10 minutes without editing a request.
- **SC-008**: The test identity is refused in 100% of attempts in a production configuration.
- **SC-009**: Every endpoint is covered by the test cases listed in FR-031, and all pass.

## Assumptions

- **Email delivery**: the backend sends codes through an outgoing mail service set in environment configuration, with no provider-specific code. Choosing that service is a deployment task.
- **Any Google account**: Gmail addresses and other Google accounts are both accepted.
- **Phone number is an optional contact detail**: it is not checked by SMS and is not a login, so it is not unique; two accounts can hold the same number.
- **International numbers**: any dialing code is accepted, with no default country. The dialing-code selector is a screen, so it comes with the mobile spec.
- **One owner, one shop**: employees and several shops per merchant are later features. The only role in this feature is owner.
- **Terms of use**: the current version label comes from configuration. The final text and legal review are outside this feature.
- **Shops cannot be edited or deleted yet**, and accounts cannot be deleted by the merchant.
- **Device identifier**: the client generates and sends it; no hardware identifier is collected.
- **Existing local data**: moving a phone's 001 to 005 data to the backend belongs to the later sync spec.
- **Out of scope**, as set by the roadmap: products and the shared catalog, stock, sales, customer credit, dashboard, sync endpoints, employees, cash shifts, and any mobile screen.
- **Translations**: the backend returns codes only. Translating them into French, Arabic and English, and RTL and accessibility behavior, belong to the mobile spec that connects these endpoints.
