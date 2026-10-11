# Implementation Plan: Kridi Credit Ledger Refactor

**Branch**: `001-kridi-credit-ledger` | **Date**: `2026-10-11` | **Spec**: `/specs/001-kridi-credit-ledger/spec.md`

**Input**: User request: refactor current 001 functionality, implement the full CRUD requirements in the feature specification, and remove verified unused code.

## Summary

Refactor the incomplete Kridi ledger groundwork into a complete, reusable offline mobile workflow for catalog products, pseudonymous customers, tickets, payments, and balance review. SQLite remains the durable source of truth; Redux Toolkit is used only for shared transient client state. NativeWind is configured as required by project governance, with CSS/style definitions separate from TypeScript/TSX component logic. CRUD corrections preserve financial traceability. Dead-code cleanup is limited to items proven unused within the ledger feature or app configuration.

## Technical Context

**Language/Version**: TypeScript 6 with strict checking, React Native 0.86, Expo SDK 57 in `shop-keeper-mobile`.

**Primary Dependencies**: Expo Router, React Native, `expo-sqlite` (already installed), NativeWind and Redux Toolkit (required by constitution but missing from the current manifest).

**Storage**: On-device SQLite for products, customers, tickets, ticket lines, payments, audit events, and pending changes. The pending-change log is retained locally; no upload/synchronization is implemented.

**Testing**: Add focused domain, repository/persistence, and user-flow coverage using Expo-compatible tooling selected during implementation. Existing app has lint but no dedicated type-check/test scripts.

**Target Platform**: Android and iOS via Expo; preserve existing Expo web support where current routes/platform components require it.

**Project Type**: Mobile application in a monorepo; backend changes and remote API contracts are outside scope.

**Performance Goals**: Common local CRUD actions, total recalculation, status derivation, and balance sorting complete without perceptible delay for the specified single-shop scale.

**Constraints**: Offline operation; integer millime money; Arabic RTL and French LTR; private customer data; common tasks within three taps; reusable components; styling definitions outside `.ts`/`.tsx`; no backend sync, login, stock, purchase prices, reminders, or dashboards.

**Scale/Scope**: One shop owner and one device with a small product catalog and ledger. The refactor includes all create, view, update, and removal flows in the 001 spec.

## Repository Assessment

- `expo-sqlite`, Expo Router, Expo SDK 57, and React Native are present.
- The ledger currently contains type/service/database groundwork, but no ledger screens/routes or tests were found in `src/features/ledger`.
- Current product/customer repository methods are incomplete, and payment validation does not calculate existing payment totals; the refactor must establish complete transaction-safe CRUD and derived values.
- NativeWind and Redux Toolkit are required by project constitution but are not currently in the mobile dependency manifest.
- The mobile app has an Expo route and template components. Refactor route wiring intentionally; remove template/dead code only after checking Expo route discovery, platform resolution, imports, package scripts, and config references.
- A previously observed modified/deleted app file may be user work; preserve unrelated work and do not restore or delete it as cleanup.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- PASS: Scope remains in `shop-keeper-mobile`; no backend responsibilities or remote contract are added.
- PASS: NativeWind, strict TypeScript, Expo, and Redux Toolkit are part of the plan. SQLite remains the persisted authority; shared Redux state is transient and derived.
- PASS: UI is assembled from reusable components, localized in JSON, accessible, and designed for both Arabic RTL and French LTR.
- PASS: No CSS declarations, `StyleSheet.create`, or inline style objects in TS/TSX component files; keep style definitions in dedicated resources.
- PASS: Money uses integer millimes; financial validation and balance/status calculations are kept in domain services, not duplicated in UI.
- PASS: Product/customer removal and ticket/payment correction/removal preserve traceable history and recalculate balances.
- PASS WITH EXPLICIT SCOPE EXCEPTION: Principle VI describes synchronization when connected, but the approved 001 feature spec excludes upload/backend synchronization. Keep pending operations locally without transmitting them. Synchronization requires a separate approved spec and security/reconciliation plan.
- PASS: Unused-code cleanup is reference-checked and limited to code proven dead in the ledger scope; avoid deleting unrelated or dynamically discovered Expo files.

## Design Decisions

- SQLite records provide persisted entity truth; database transactions keep each entity mutation, related audit event, and pending-change entry atomic.
- Product/customer removal is a soft removal from active selection so historical ticket references remain understandable.
- Ticket/payment corrections append before/after audit events. Financial record removals mark the record voided and append an audit event instead of hard-deleting it. Balances use non-voided tickets and payments.
- Ticket status is derived from active payment totals, not maintained as an independently mutable business value.
- NativeWind is the styling system. Components may reference utility classes; CSS rules and other style definitions remain in separate style resources, never embedded in TS/TSX.
- Reusable field, amount, confirmation, empty/error/loading, product selection, ticket line, customer row, and status/balance components should be created only where their usage justifies reuse.
- Delete or consolidate a helper/file only after verifying no route, platform variant, import, build script, test, or configuration depends on it.

## Project Structure

### Documentation (this feature)

```text
specs/001-kridi-credit-ledger/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── local-operations-contract.md
└── tasks.md
```

### Source Code (mobile application)

```text
shop-keeper-mobile/
├── src/
│   ├── app/                         # Expo Router routes and layouts
│   ├── components/                  # App-wide reusable components
│   ├── features/ledger/
│   │   ├── components/              # Reusable ledger UI pieces
│   │   ├── screens/                 # Product, customer, ticket, payment, balance flows
│   │   ├── services/                # Validation, calculations, orchestration
│   │   ├── store/                   # Redux Toolkit transient shared state
│   │   └── types/
│   ├── db/                          # SQLite schema, migrations, connection
│   ├── i18n/                        # Arabic and French resources
│   └── styles/                      # NativeWind/CSS tokens and shared style definitions
├── app.json
├── package.json
└── tsconfig.json
```

**Structure Decision**: Keep domain rules and persistence in the ledger feature/repository layer. Expo routes connect screens and reusable components. A feature may have dedicated CSS/style resources; TS/TSX files contain component logic and styling references only.

## Complexity Tracking

| Deviation | Why needed | Resolution |
|-----------|------------|------------|
| No background synchronization in this release despite constitution principle VI describing sync | The approved 001 spec explicitly excludes upload and backend synchronization | Keep pending change records locally; require a separate approved spec for transmission and reconciliation |
