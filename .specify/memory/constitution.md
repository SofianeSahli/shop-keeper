<!--
Sync Impact Report
- Version change: 1.5.0 -> 2.0.0
- Reason: alignment with the product roadmap of 2026-10-31 (pivot from a local credit ledger to a small-business management app with a Laravel backend).
- Modified principles: I. Monorepo (one independent app per actor); VI. Offline-First (operation-log sync with the backend; the local-only exception ends).
- Added principles: VII. Product Scope: Small-Business Management; VIII. Data Ownership and Privacy.
- Modified constraints: money in minor units of the shop currency; "kridi" renamed "customer credit"; customer credit is distinct from the payment method; phone + PIN required, Google login optional for a later feature; shared product catalog; append-only money and stock records; hosting-agnostic deployment.
- Removed sections: Approved Local-Only Feature Exception (replaced by a closing note in VI).
- Follow-up TODOs: legal review of data collection and anonymization (INPDP) before any third-party sharing; who verifies pending global products; hosting choice.
-->

# Shop Keeper Constitution

## Core Principles

### I. Monorepo Integrity and Boundary Control
The repository MUST maintain a monorepo with separate applications at its root: `shop-keeper-backend/` for the Laravel backend, and one directory per actor app, starting with `shop-keeper-mobile/` for the merchant app (React Native, TypeScript, Expo). Each app MUST have its own dependencies, configuration, tests and releases, and MUST work on its own; another actor's features MUST NOT be added as a "mode" inside an existing app. Apps MUST communicate only through documented REST API endpoints of the backend and MAY share only a common package (UI components, translations, money formatting). The backend is the final authority for reconciliation, validation, permissions and persistent data.

This constitution exists to prevent unclear ownership between client and server responsibilities while allowing essential shop workflows to continue without connectivity. When data is reconciled or shared, backend decisions govern data integrity, access control and business behavior.

### II. Accessible, Simple, and Trustworthy Mobile Experience
The mobile application MUST prioritize intuitive navigation, predictable behavior, low cognitive load, and clear primary actions for shopkeepers and users with limited literacy or digital experience. UI text MUST be short, familiar, and consistent, and screens MUST provide obvious loading, success, empty, error, offline, and destructive-action states. Components MUST be reusable, accessible, and focused on presentation and interaction without duplicating backend validation or business rules.

The product MUST be usable without requiring technical understanding. Large touch targets, simple layouts, readable typography, and clear icon labels are mandatory, and accessibility requirements such as screen-reader labels, logical focus order, contrast, and text scaling MUST be built into the experience from the first release.

UI components MUST be composed from small, reusable units when those units represent repeated or independently understandable behavior. Shared components MUST have clear responsibilities and MUST NOT become generic abstractions without a demonstrated reuse need. TypeScript and TSX files MUST NOT contain CSS rules, `StyleSheet` declarations, or inline style objects; styling definitions MUST live in dedicated style files, while component files may reference approved classes or imported styles. Implementations MUST account for loading, empty, invalid-input, failure, and recovery states rather than validating only the happy path.

### III. Internationalization by Default: Arabic and French
Arabic and French MUST both be supported from initial release. Every user-facing string MUST be stored in JSON translation resources, and user-visible text MUST never be hardcoded in components, hooks, services, validation logic, or API error handling. The application MUST persist the active language selection and restore it on future launches, and switching between Arabic and French MUST work without reinstalling the app.

French MUST use left-to-right layout and Arabic MUST use right-to-left layout. Layouts MUST adapt using direction-aware techniques rather than duplicating screens or hardcoding left/right behavior. Translation resources MUST remain complete, synchronized, and parameterized for dynamic messages. The backend MUST return stable machine error codes and no user-facing text; apps MUST map those codes to localized messages instead of exposing raw server text.

### IV. Secure Backend Architecture and Authorization
The backend MUST use Laravel and PHP with MySQL as the database. It MUST expose a documented, versioned REST API (`/api/v1`) that can be exercised with standard HTTP tools such as Postman, and it MUST validate incoming data using server-side Laravel validation, migrations, and database constraints. Controllers MUST focus on HTTP handling; business logic, permissions, and persistence MUST be separated into appropriate layers. API responses, pagination, status codes, and error handling MUST follow consistent conventions, and contract changes MUST be documented and tested.

Authentication and authorization MUST remain distinct responsibilities. Laravel Sanctum MUST provide API authentication, while RBAC MUST be enforced through Laravel policies, gates, or equivalent permission checks. All protected endpoints MUST verify identity and authorization on the server, and every shop-owned record MUST be isolated by shop: a user MUST never reach another shop's data. Secrets, credentials, and environment-specific settings MUST never be committed to source control. The backend MUST run on a PHP release that includes the latest security fixes of a supported branch. Detailed backend rules live in `shop-keeper-backend/CLAUDE.md`.

### V. Testability, Quality Gates, and Definition of Done
Every feature MUST be independently testable, with the scope and depth of automated testing proportionate to the feature's risk and responsibilities. Mobile tests SHOULD cover relevant components, hooks, utilities, Redux logic, API integrations, translations, language switching, RTL/LTR behavior, accessibility labels, and permission-dependent user flows. Backend tests MUST cover each endpoint's success, validation, unauthenticated and cross-shop access cases, and SHOULD cover relevant business logic and database operations. Important mobile-to-backend workflows SHOULD be validated end-to-end where practical; testing scope MUST support reliable delivery without making exhaustive testing a prerequisite for an MVP.

Features are complete when they satisfy the approved specification, architecture, and applicable quality gates. Relevant automated tests, linting, static analysis, and TypeScript checks MUST pass before integration where practical and proportionate to risk. A feature MUST NOT be considered done merely because it compiles or the happy path works; material failure states, empty states, edge cases, and security checks MUST be considered.

### VI. Offline-First
Core shopkeeper actions (recording a sale, receiving stock, recording customer credit and its payments, viewing stock and balances) MUST work without a network connection. The mobile application MUST write these actions to a local SQLite database first, recording each one in an operation log with a client-generated UUID, and synchronize the log with the backend when connectivity is available: the app pushes its operations, then pulls the changes made on the server.

The backend MUST apply each pushed operation through the same business logic, validation and permissions as the matching REST endpoint. Pushing the same operation twice MUST apply it once. Each operation MUST receive its own result (applied, duplicate, or rejected with a stable error code), a rejected operation MUST NOT block the others, and the app MUST show rejected operations to the merchant rather than drop them silently.

Features 001 to 005 were delivered as a single-device, local-only scope. That scope ended on 2026-10-31: from feature 006 onward, shop data is stored and synchronized with the backend.

### VII. Product Scope: Small-Business Management
The merchant app is a management app for small independent shops (grocery, hardware, bakery, clothing and similar): shop, products with purchase and sale prices, stock, sales, customer credit and a dashboard, followed by employees and cash-register shifts. It MUST NOT require buying hardware: the phone or tablet the merchant already has is enough, the camera scans barcodes, and receipts are digital. Optional accessories (Bluetooth scanner, thermal printer) MAY be supported later.

Customer credit is a feature of its own, distinct from the payment method: a sale is either settled at once or put on credit, and every settlement, immediate or later, records its own payment method (cash, cheque, card, transfer). Features MUST be delivered in the roadmap order recorded in the owner's "Feuille de route — Écosystème Shop Keeper", one small specification at a time.

### VIII. Data Ownership and Privacy
All data entered in the apps belongs to the platform operator, as stated in the terms of use accepted at sign-up, and is collected by the backend. Inside the apps, a shop MUST see only its own data, and only the owner and the employees the owner authorizes MAY see its customers and their credit. Personal data (customer names, phone numbers, credit) MUST be anonymized or aggregated before any sharing with third parties, at least to the level required by Tunisian law (INPDP), and the collection and anonymization rules MUST be reviewed by a legal advisor before any third-party sharing. Logs MUST NOT contain personal data, PINs or tokens.

## Additional Constraints

- The mobile application MUST use React Native and Expo as the standard development and build platform.
- TypeScript MUST be used with strict type checking where practical.
- NativeWind MUST be used for styling; browser CSS and DOM-specific patterns are not permitted in the mobile app.
- Redux Toolkit MUST be the standard for shared client-side state, and RTK Query SHOULD be used for API requests and server-state lifecycle management when appropriate.
- API calls MUST be centralized in a dedicated API layer, and UI components MUST NOT implement backend business rules or duplicate authoritative validation.
- Mobile and backend architecture MUST maintain clean boundaries between presentation, state management, API communication, business logic, and data persistence.
- Shared API contracts and integration conventions MUST remain consistent across all apps.
- The application MUST support Arabic and French without requiring a reinstall, and all relevant layouts MUST adapt to LTR and RTL information flow.
- Dates, times, numbers, and currencies MUST be formatted according to the selected locale and business requirements.
- Monetary amounts MUST be stored and transferred as integers in the minor unit of the shop's currency (TND: millimes, three decimal places). Floating-point types MUST NOT be used for money.
- Records that carry money or stock (sales, payments, customer credit entries, stock movements) MUST NOT be updated or deleted; a correction is a new record.
- Products MUST be split between a shared catalog of products (`global_products`: barcode, name, brand, format, no price) and each shop's own products (`shop_products`: prices, stock, optional link to the shared catalog).
- The feature formerly called "kridi" is named "customer credit" in code, API and documentation; the local word MAY be used in Arabic translations and dialect marketing.
- Authentication MUST use phone number plus PIN through Laravel Sanctum, with rate limiting, and PINs MUST be stored only as hashes. Google login MAY be added in a later feature. Phone-number OTP MAY be added when its operating cost is viable. Email/password authentication MUST NOT be used.
- Common shopkeeper actions MUST take three taps or fewer.
- CSS and style declarations MUST be kept in dedicated styling files, separate from TypeScript and TSX component logic. UI work MUST favor clean, reusable components and MUST cover relevant non-happy-path states.
- Sensitive configuration MUST be excluded from version control and secure handling MUST be used for tokens, credentials, and environment settings. Every app MUST stay deployable on any host: configuration only through environment files, no provider-specific code, and no hard-coded app name, API URL or colors outside configuration.
- Documentation and architectural decisions MUST be updated when a feature introduces material changes in contracts, permissions, or system boundaries.

## Development Workflow

- Spec Kit MUST be used for specification-driven development. Specifications MUST define user goals, requirements, acceptance criteria, and relevant edge cases.
- Plans MUST identify responsibilities across mobile, backend, database, and integration boundaries.
- Tasks MUST be granular enough for implementation and verification.
- Relevant specifications MUST explicitly cover translations, RTL/LTR behavior, accessibility, security, authorization, and testing.
- Backend work MUST follow `shop-keeper-backend/CLAUDE.md`; mobile work MUST follow `shop-keeper-mobile/AGENTS.md` and the root `CLAUDE.md`.
- Implementations MUST follow the approved specification and this constitution. Conflicts MUST be resolved explicitly rather than silently bypassed.
- Changes MUST remain focused on the approved scope and avoid introducing new frameworks, state-management systems, styling systems, or architectural patterns that conflict with this constitution without explicit justification.
- The project MUST retain clear separation of concerns and avoid dead code, duplicated business rules, and unexplained magic values.
- Bug fixes MUST include regression coverage where practical.

## Governance

This constitution is the governing standard for the Shop Keeper monorepo. It supersedes conflicting local conventions, informal patterns, and undocumented assumptions in all feature work, reviews, and implementation decisions. Any requirement materially conflicting with this document MUST be resolved explicitly before implementation proceeds.

Amendments MUST be documented in the constitution, versioned using semantic versioning, and reviewed before being treated as project policy. A major version bump is required for backward-incompatible governance or principle changes; a minor version bump is required for new principles or materially expanded guidance; a patch version bump is reserved for clarifications, wording fixes, and non-semantic refinements. The constitution MUST be reviewed when cross-project architecture, security, accessibility, or API contracts materially change.

All work in the repository MUST be checked against this constitution during planning, implementation, and review. If a feature violates a mandatory requirement, the work MUST be corrected before it is considered complete. Maintainers MUST preserve a durable architectural record of significant decisions and deviations that are intentionally allowed.

**Version**: 2.0.0 | **Ratified**: 2026-10-10 | **Last Amended**: 2026-10-31
