# Research: Ledger Usability Dashboard

## 1. Persistent Navigation and Unsaved Forms

- **Decision**: Render one shared, direction-aware toolbar from the existing `src/app/ledger/_layout.tsx` above the ledger route stack. Map each child route to its main area so ticket details and forms continue to highlight Tickets, for example. Use `/ledger` as the dashboard/Home route and preserve `/` as the existing language-selection and entry screen linking to the ledger. For dirty forms, intercept toolbar route changes and display an in-app localized confirmation; dispatch the attempted navigation only after confirmation.
- **Rationale**: The root layout already owns provider and database-startup behavior; the ledger layout is the direct parent of all ledger screens and allows the toolbar to remain out of the existing language-selection entry flow. A persistent ledger parent avoids duplicating navigation across screens. The clarified requirement explicitly requires confirmation before discarding form state. React Navigation's `usePreventRemove` pattern intercepts stack removals and lets the app resume the original action after confirmation; it does not intercept tab-style screen unfocusing, so toolbar navigation should use a route-removal action or explicitly consult the active form guard rather than silently switching sections.
- **Alternatives considered**:
  - Duplicating toolbar controls on each page: rejected because active-state logic and accessibility would drift.
  - Using a tab/drawer navigator: rejected because the request calls for a top toolbar and tab-style route changes may not trigger the required removal guard.
  - Automatically persisting all form drafts: rejected because the chosen behavior is to ask and discard only after explicit confirmation.
- **References**: Existing root and ledger layouts in `shop-keeper-mobile/src/app/`; [Expo Router layouts](https://docs.expo.dev/router/layouts/) and [React Navigation `usePreventRemove`](https://reactnavigation.org/docs/use-prevent-remove/).

## 2. Dashboard Totals and Refresh

- **Decision**: Add a ledger-domain summary operation that returns today's sales and outstanding unpaid balance in integer millimes. Use active, non-voided tickets and their saved line totals for sales; classify "today" by the device's local calendar date. For outstanding debt, sum each active ticket's positive remainder after non-voided payments. Recalculate from persisted repository data when the dashboard is focused and when the existing Redux `refreshVersion` changes.
- **Rationale**: Existing `TicketRepository` hydrates ticket line snapshots and derives paid status from active payments. Existing repository queries exclude voided records by default, and `refreshCompleted()` is dispatched after writes. Existing balance logic provides a precedent for subtracting payments. Keeping calculation in a service protects the millime and voiding rules from UI duplication. Focus refresh complements the store signal when navigation returns to the dashboard.
- **Alternatives considered**:
  - Treating sales as payments collected: rejected because the specification defines sales as ticket-line sales unaffected by payment timing.
  - Summing customer totals: rejected because dashboard debt is the aggregate remaining ticket balance, independent of customer identity/display filtering.
  - Persisting cached dashboard totals: rejected because totals are derived and a cache could become stale after corrections, voids, or app restarts.
- **References**: Existing `ticketRepository`, `paymentRepository`, `summariseCustomerBalances`, and `refreshCompleted` in `shop-keeper-mobile/src/features/ledger/`.

## 3. Barcode Scanning and Duplicate Products

- **Decision**: Reuse `BarcodeScanner`/`CameraView` and the current exact, trimmed active-barcode matching semantics. Add the scanner to the new-product form, retain its editable manual code field, and route a scan or typed code through the same lookup. On a match, offer the existing product and open its edit/detail route; do not create a second active product.
- **Rationale**: `expo-camera` is already installed and configured, and ticket entry already scans QR and common retail barcode formats. The product service already rejects active duplicate barcodes. A single lookup contract means camera and keyboard input cannot diverge on duplicates.
- **Alternatives considered**:
  - A separate product-only camera component or new scanning package: rejected because the existing scanner provides the required capability.
  - Automatic creation or silent overwrite on duplicate: rejected because it risks losing existing catalog data and conflicts with the user-visible duplicate warning.
  - Making camera permission mandatory: rejected because the spec requires manual entry after denial or scan failure.
- **References**: [Expo Camera SDK 57 documentation](https://docs.expo.dev/versions/v57.0.0/sdk/camera/) and existing `BarcodeScanner` and `productService` in the ledger feature.

## 4. Inline Ticket Creation

- **Decision**: Preserve the ticket form's local draft state while inline customer/product forms are shown in an in-place modal or dialog. Persist via the existing customer/product services, then select the returned customer or append the returned product as a quantity-one ticket draft line. Update the product/customer lists only after successful persistence.
- **Rationale**: Ticket inputs are currently component-local state, and ticket product creation already has an inline flow that creates and appends an item. Keeping the parent screen mounted preserves all current values, avoids introducing draft persistence, and naturally supports cancellation without changing the ticket.
- **Alternatives considered**:
  - Navigating to a standalone customer/product form: rejected because route removal can lose the ticket draft and would require extra draft storage/restoration machinery.
  - Updating the draft before persistence: rejected because a failed create would leave a line or selection pointing to a nonexistent record.
  - Adding inline records directly to ticket persistence: rejected because products and customers are independent catalog entities with existing service and audit/pending-change behavior.

## 5. Scope, Storage, and Quality Gates

- **Decision**: Keep feature 002 within the feature 001 offline local ledger. The constitution was amended from 1.4.0 to 1.5.0 after the user's explicit approval so the existing local-only exception covers this usability extension. No remote interface or new stored entity is introduced.
- **Rationale**: All requested behavior is a presentation and local aggregate enhancement to existing records and CRUD flows. A backend/authentication/synchronization redesign is outside the explicitly approved scope.
- **Alternatives considered**:
  - Adding backend, accounts, or synchronization: rejected in the user's explicit approval of the local-only extension.
  - Adding a persisted dashboard table/cache: rejected because its values are completely derived from existing ledger entities.
- **Web constraint**: Expo SQLite web support requires WebAssembly asset bundling and cross-origin isolation headers for `SharedArrayBuffer`; the application already configures these requirements for Expo Router hosting. Other deployment hosts must supply the documented headers. Web support remains experimental and must be runtime-tested, not inferred from a successful static bundle.
- **Quality gates**: `npm run validate`, `npx expo export --platform web`, browser runtime checks on an isolated origin, and native camera/permission and navigation checks on supported devices.
- **References**: [Expo SQLite SDK 57 web setup](https://docs.expo.dev/versions/v57.0.0/sdk/sqlite/) and existing `shop-keeper-mobile/metro.config.js` and `app.json`.
