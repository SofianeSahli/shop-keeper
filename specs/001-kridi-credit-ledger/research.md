# Research: Kridi Credit Ledger Refactor

## Decision: Keep the refactor inside the Expo mobile app

- Decision: Implement the ledger in `shop-keeper-mobile` and keep its persistence local-first. No backend API or upload worker is introduced.
- Rationale: The approved feature scope is a single-shop, single-device offline ledger and explicitly excludes backend synchronization and upload.
- Alternatives considered:
  - Add backend endpoints or background synchronization: rejected as out of scope.
  - Replace local persistence with remote storage: rejected because ledger actions must work offline.

## Decision: Use the existing Expo SQLite dependency as the durable source of ledger data

- Decision: Treat SQLite records as the source of truth for products, customers, tickets, ticket lines, payments, and pending changes. Add or repair repository methods and transactions around it.
- Rationale: `expo-sqlite` is installed in the mobile app and supports the required relational constraints and restart persistence. Existing service functions are incomplete and do not yet constitute end-to-end CRUD.
- Alternatives considered:
  - Add a second storage mechanism: rejected because it would create competing sources of truth.
  - Store monetary state only in Redux or component state: rejected because it would not survive restart and could drift.

## Decision: Use NativeWind and keep style definitions out of TypeScript/TSX

- Decision: Configure the constitution-required NativeWind styling system. Keep CSS rules and style definitions in dedicated styling resources; components may reference reusable class names but MUST NOT contain CSS declarations, `StyleSheet.create`, or inline style objects.
- Rationale: This follows project governance and the user's requirement for component files without embedded styles. The current mobile manifest has no NativeWind dependency/configuration, so setup work is needed.
- Alternatives considered:
  - Keep component-local `StyleSheet.create` or inline styles: rejected because this conflicts with the newly amended constitution.
  - Introduce a second styling framework: rejected as duplicate infrastructure.

## Decision: Use Redux Toolkit only for shared transient client state

- Decision: Use Redux Toolkit for cross-screen UI state where needed, with SQLite remaining the persisted ledger source of truth. Do not introduce RTK Query because this feature has no remote API requests.
- Rationale: The constitution requires Redux Toolkit for shared client state while the feature excludes network APIs. Keeping durable entity writes in repositories avoids duplicate financial state.
- Alternatives considered:
  - Keep ledger entities exclusively in component state: rejected because multiple screens need consistent shared state.
  - Mirror the full database into a second mutable source of truth: rejected because it invites drift.
  - Add RTK Query for nonexistent endpoints: rejected as unnecessary.

## Decision: Preserve financial history with soft removal and append-only change events

- Decision: Product/customer removal marks a record inactive. Ticket/payment corrections update the active view while appending a before/after audit event; removal marks the financial record voided rather than deleting it. Derived balances use non-voided tickets/payments only.
- Rationale: This gives users full CRUD semantics without erasing financial activity needed to explain balances. It also provides stable history after catalog edits.
- Alternatives considered:
  - Hard-delete financial records: rejected because it destroys the audit trail and can make balances inexplicable.
  - Reuse only the future-upload queue as audit history: rejected because queue operations may eventually be reconciled or cleared and have a different purpose.

## Decision: Use typed feature modules, reusable UI components, and localized user-facing messages

- Decision: Keep validation, financial calculations, persistence, and UI presentation in separate feature modules. Compose flows from reusable components, centralize translation strings in Arabic and French JSON resources, and provide explicit loading, empty, validation, failure, confirmation, and success states.
- Rationale: This follows the architecture and accessibility principles in the constitution and supports independent verification of business rules.
- Alternatives considered:
  - Put persistence and business rules in screen components: rejected because it couples UI behavior to data integrity.
  - Hardcode strings or create duplicate screens for RTL: rejected by localization and direction-aware layout requirements.

## Decision: Remove only verified dead feature code

- Decision: The refactor will remove unused ledger-specific imports, exports, helpers, components, files, and configuration only after checking references, route usage, package scripts/configuration, and tests. Generic Expo starter files are not deleted merely because they are not part of a ledger flow.
- Rationale: A reference-checked cleanup avoids breaking unrelated app navigation or build targets while addressing the request to remove unused code.
- Alternatives considered:
  - Delete all starter files or all currently unreferenced files repository-wide: rejected because some may be loaded through Expo routing or platform-specific resolution.

## Styling and tooling findings

- `expo-sqlite` is already in `shop-keeper-mobile/package.json`; Expo Router and React Native are also installed.
- NativeWind and Redux Toolkit are not present in the mobile dependency manifest at planning time.
- No ledger screen/routes or ledger tests are present in `shop-keeper-mobile/src/features/ledger`; current files are domain/service groundwork.
- The active app has an Expo route and template components; route/config references must be checked before cleanup.
- The mobile app currently has `lint` but no dedicated type-check or test script. The implementation plan includes focused verification scripts/tooling as needed, without broad dependency upgrades.

## Scope conflict and governance resolution

- Constitution principle VI describes synchronization when connectivity is available; the approved 001 specification explicitly excludes upload and backend synchronization.
- For this feature release, retain local pending-change records but do not transmit them. This is a deliberate, specification-bounded scope exception; enabling synchronization requires a separate approved specification and security/reconciliation design.
- Constitution additional constraints require NativeWind and Redux Toolkit; their absence from the current manifest is addressed as setup, not treated as permission to bypass those requirements.
