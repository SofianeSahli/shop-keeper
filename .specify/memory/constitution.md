<!--
Sync Impact Report
- Version change: 1.1.0 -> 1.2.0
- Modified principles: None
- Added sections: None
- Modified constraints: Authentication options; phone OTP deferred unless cost-effective
- Removed sections: None
- Follow-up TODOs: None
-->

# Shop Keeper Constitution

## Core Principles

### I. Monorepo Integrity and Boundary Control
The repository MUST maintain a monorepo with separate applications at its root: `shop-keeper-backend/` for the Laravel backend and `shop-keeper-mobile/` for the React Native, TypeScript, and Expo application. Mobile and backend dependencies, configs, tests, and responsibilities MUST remain separate, and server operations MUST be mediated through documented REST API endpoints. The mobile application MAY operate against local data while offline; the backend is the final authority for reconciliation, validation, permissions, and persistent shared data.

This constitution exists to prevent unclear ownership between client and server responsibilities while allowing essential shop workflows to continue without connectivity. When data is reconciled or shared, backend decisions govern data integrity, access control, and business behavior.

### II. Accessible, Simple, and Trustworthy Mobile Experience
The mobile application MUST prioritize intuitive navigation, predictable behavior, low cognitive load, and clear primary actions for shopkeepers and users with limited literacy or digital experience. UI text MUST be short, familiar, and consistent, and screens MUST provide obvious loading, success, empty, error, offline, and destructive-action states. Components MUST be reusable, accessible, and focused on presentation and interaction without duplicating backend validation or business rules.

The product MUST be usable without requiring technical understanding. Large touch targets, simple layouts, readable typography, and clear icon labels are mandatory, and accessibility requirements such as screen-reader labels, logical focus order, contrast, and text scaling MUST be built into the experience from the first release.

### III. Internationalization by Default: Arabic and French
Arabic and French MUST both be supported from initial release. Every user-facing string MUST be stored in JSON translation resources, and user-visible text MUST never be hardcoded in components, hooks, services, validation logic, or API error handling. The application MUST persist the active language selection and restore it on future launches, and switching between Arabic and French MUST work without reinstalling the app.

French MUST use left-to-right layout and Arabic MUST use right-to-left layout. Layouts MUST adapt using direction-aware techniques rather than duplicating screens or hardcoding left/right behavior. Translation resources MUST remain complete, synchronized, and parameterized for dynamic messages, and backend validation errors and API codes MUST be mapped to localized mobile messages instead of exposing raw server text directly to users.

### IV. Secure Backend Architecture and Authorization
The backend MUST use Laravel and PHP with MySQL as the database. It MUST expose a documented REST API consumed by the mobile application, and it MUST validate incoming data using server-side Laravel validation, migrations, and database constraints. Controllers MUST focus on HTTP handling; business logic, permissions, and persistence MUST be separated into appropriate layers. API responses, pagination, status codes, and error handling MUST follow consistent conventions, and contract changes MUST be documented and tested.

Authentication and authorization MUST remain distinct responsibilities. Laravel Sanctum MUST provide API authentication, while RBAC MUST be enforced through Laravel policies, gates, or equivalent permission checks. All protected endpoints MUST verify identity and authorization on the server, and resource ownership, role restrictions, and cross-user isolation MUST be enforced before data is exposed. Secrets, credentials, and environment-specific settings MUST never be committed to source control.

### V. Testability, Quality Gates, and Definition of Done
Every feature MUST be independently testable, with the scope and depth of automated testing proportionate to the feature's risk and responsibilities. Mobile tests SHOULD cover relevant components, hooks, utilities, Redux logic, API integrations, translations, language switching, RTL/LTR behavior, accessibility labels, and permission-dependent user flows. Backend tests SHOULD cover relevant validation, business logic, database operations, API endpoints, authentication, authorization, and security boundaries. Important mobile-to-backend workflows SHOULD be validated end-to-end where practical; testing scope MUST support reliable delivery without making exhaustive testing a prerequisite for an MVP.

Features are complete when they satisfy the approved specification, architecture, and applicable quality gates. Relevant automated tests, linting, static analysis, and TypeScript checks MUST pass before integration where practical and proportionate to risk. A feature MUST NOT be considered done merely because it compiles or the happy path works; material failure states, empty states, edge cases, and security checks MUST be considered.

### VI. Offline-First
Core shopkeeper actions—adding a customer, recording a debt, recording a payment, and viewing a balance—MUST work without a network connection. The mobile application MUST write these actions to a local SQLite database first, recording changes in an operation log with client-generated UUIDs, and synchronize the logged operations when connectivity is available. During reconciliation, the backend MUST validate operations and enforce permissions as the authority for shared data; it MUST reject or explicitly flag invalid operations rather than silently accept them.

Offline-first behavior preserves continuity of essential shop work when connectivity is unavailable, while server-side reconciliation protects shared data integrity and access control.

## Additional Constraints

- The mobile application MUST use React Native and Expo as the standard development and build platform.
- TypeScript MUST be used with strict type checking where practical.
- NativeWind MUST be used for styling; browser CSS and DOM-specific patterns are not permitted in the mobile app.
- Redux Toolkit MUST be the standard for shared client-side state, and RTK Query SHOULD be used for API requests and server-state lifecycle management when appropriate.
- API calls MUST be centralized in a dedicated API layer, and UI components MUST NOT implement backend business rules or duplicate authoritative validation.
- Mobile and backend architecture MUST maintain clean boundaries between presentation, state management, API communication, business logic, and data persistence.
- Shared API contracts and integration conventions MUST remain consistent across both applications.
- The application MUST support Arabic and French without requiring a reinstall, and all relevant layouts MUST adapt to LTR and RTL information flow.
- Dates, times, numbers, and currencies MUST be formatted according to the selected locale and business requirements.
- Monetary amounts MUST be stored and transferred as integer millimes; TND has three decimal places. Floating-point types MUST NOT be used for money.
- Kridi customer data, including names, phone numbers, and debts, is private to the shop and MUST NEVER be included in aggregated, shared, or analytics datasets. Any future data sharing MUST be anonymized, aggregated, and based on explicit opt-in consent.
- Authentication MUST support phone number plus PIN and Google login through Laravel Sanctum. Phone-number OTP MAY be added when its operating cost is viable; it is not required for the initial release. Email/password authentication MUST NOT be used.
- Common shopkeeper actions MUST take three taps or fewer.
- Sensitive configuration MUST be excluded from version control and secure handling MUST be used for tokens, credentials, and environment settings.
- Documentation and architectural decisions MUST be updated when a feature introduces material changes in contracts, permissions, or system boundaries.

## Development Workflow

- Spec Kit MUST be used for specification-driven development. Specifications MUST define user goals, requirements, acceptance criteria, and relevant edge cases.
- Plans MUST identify responsibilities across mobile, backend, database, and integration boundaries.
- Tasks MUST be granular enough for implementation and verification.
- Relevant specifications MUST explicitly cover translations, RTL/LTR behavior, accessibility, security, authorization, and testing.
- Implementations MUST follow the approved specification and this constitution. Conflicts MUST be resolved explicitly rather than silently bypassed.
- Changes MUST remain focused on the approved scope and avoid introducing new frameworks, state-management systems, styling systems, or architectural patterns that conflict with this constitution without explicit justification.
- The project MUST retain clear separation of concerns and avoid dead code, duplicated business rules, and unexplained magic values.
- Bug fixes MUST include regression coverage where practical.

## Governance

This constitution is the governing standard for the Shop Keeper monorepo. It supersedes conflicting local conventions, informal patterns, and undocumented assumptions in all feature work, reviews, and implementation decisions. Any requirement materially conflicting with this document MUST be resolved explicitly before implementation proceeds.

Amendments MUST be documented in the constitution, versioned using semantic versioning, and reviewed before being treated as project policy. A major version bump is required for backward-incompatible governance or principle changes; a minor version bump is required for new principles or materially expanded guidance; a patch version bump is reserved for clarifications, wording fixes, and non-semantic refinements. The constitution MUST be reviewed when cross-project architecture, security, accessibility, or API contracts materially change.

All work in the repository MUST be checked against this constitution during planning, implementation, and review. If a feature violates a mandatory requirement, the work MUST be corrected before it is considered complete. Maintainers MUST preserve a durable architectural record of significant decisions and deviations that are intentionally allowed.

**Version**: 1.2.0 | **Ratified**: 2026-10-10 | **Last Amended**: 2026-10-10
