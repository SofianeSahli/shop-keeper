# Feature Specification: Light and Dark Theme

**Feature Branch**: `005-dark-mode`

**Created**: 2026-10-29

**Status**: Closed on 2026-10-31 (all tasks closed before the phase 1 backend)

**Input**: User description: "Let's start on spec 5, which includes light and dark mode." Design source: [dark-mode.md](../../shop-keeper-mobile/docs/design/dark-mode.md), the approved "Mode sombre" row of the "Carnet Kridi — Mobile UI" design canvas (`DarkPalette`, `MainDark`, `TicketFormDark`, `PaymentFormDark`, `CustomersDark`, `CustomerLedgerDark`, `ProductFormDark`).

## Context

The app has a single light look. Many shops are dim in the evening, and some owners keep their phone in dark mode to save battery and rest their eyes; a bright white app is then tiring and stands out against everything else on the phone. This feature adds a dark theme with the same layout, wording and meaning colors, and a one-tap switch between light and dark.

Nothing else changes: the same screens, the same data, the same rules. Only the colors change, and every color meaning (debt, paid, partial, error) stays recognisable and comes with its text label, as today.

## Clarifications

### Session 2026-10-29

- Q: Where is the theme choice saved? → A: In the app's local settings, where the language is already saved (the on-phone database's settings table), so the feature needs no extra native development. Moving the display preferences to the phone's native preference store (SharedPreferences on Android, UserDefaults on iOS) is deferred to feature 006.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Use the whole app in a dark theme (Priority: P1)

The owner turns the app dark and every screen, list, form, sheet, toast and loader is dark and readable: a green-black background, slightly lighter cards, light text, and lightened debt, paid, partial and error colors that still mean the same thing. Nothing stays white, nothing becomes unreadable.

**Why this priority**: The dark theme itself is the value of the feature; the switch (Story 2) is useless if any screen is unreadable or half light.

**Independent Test**: With the app in dark, go through every tab and every form (home, tickets, new ticket with "Partiel", payment with the ticket sheet open, customers, a customer's ledger, add a product with a duplicate barcode), open a toast, the blocking loader and a confirmation sheet: no light surface remains and every text is readable.

**Acceptance Scenarios**:

1. **Given** the dark theme, **When** the owner opens any screen, **Then** the background is green-black (never pure black), cards, top bar, tab bar, sheets and the sticky footer are one step lighter, and inputs and small buttons one step lighter again.
2. **Given** the dark theme, **When** amounts owed, paid amounts, partial tickets and errors are shown, **Then** they use the lightened debt, paid, partial and error colors of the design note and keep their text labels ("Kridi", "Payé", "Partiel", error messages).
3. **Given** the dark theme, **When** a success, error, info or pending toast, the blocking loader, a bottom sheet or a confirmation is shown, **Then** it uses its dark version from the design note, with the darker scrim behind sheets and the loader.
4. **Given** the dark theme, **When** a filled button is shown (save, collect, FAB), **Then** it is one step lighter than in light mode with white text, and its text contrast is at least 4.5:1.
5. **Given** the dark theme, **When** the camera scanner is open, **Then** its viewfinder looks the same as in light mode (it is dark in both themes).
6. **Given** a form field in error (004, FR-015), **When** it is outlined, **Then** the outline and message use the dark error color and stay readable.

---

### User Story 2 - Switch between light and dark in one tap (Priority: P1)

Next to the language button in the top bar, a round button shows a moon in light mode and a sun in dark mode. One tap switches the whole app to the other theme at once, on the screen the owner is on, without losing anything typed.

**Why this priority**: The owner must be able to choose; without the switch the dark theme can only follow the phone.

**Independent Test**: On a half-filled ticket form, tap the theme button: the app turns dark (or light) immediately, the form keeps every value, and the button now shows the other icon.

**Acceptance Scenarios**:

1. **Given** any screen that shows the language button, **When** it is displayed, **Then** the theme button is right next to it, round, at least 44 dp, showing a moon (light mode) or a sun (dark mode).
2. **Given** the light theme, **When** the owner taps the moon, **Then** the whole app switches to dark at once, including the top bar, tab bar, system status bar icons and any open sheet, and the button shows a sun.
3. **Given** a form with entered values, **When** the theme is switched, **Then** every value, the scroll position and any open sheet are kept (same rule as switching language).
4. **Given** a screen reader, **When** it reaches the theme button, **Then** it reads "Passer en mode sombre" in light mode or "Passer en mode clair" in dark mode (in the current language).
5. **Given** Arabic, **When** the theme is switched, **Then** colors change the same way and the layout stays mirrored.

---

### User Story 3 - The app remembers the choice and starts like the phone (Priority: P2)

On first launch the app uses the phone's light or dark setting. Once the owner has switched manually, the app keeps that choice every time it opens, whatever the phone setting.

**Why this priority**: Restoring the choice is expected (the language works the same way), but the feature already delivers value without it.

**Independent Test**: Fresh install with the phone in dark: the app opens dark. Switch to light, close and reopen: the app opens light, even though the phone is still dark.

**Acceptance Scenarios**:

1. **Given** a first launch and a phone in dark mode, **When** the app opens, **Then** it is dark from the first screen (no light flash); with a phone in light mode it is light.
2. **Given** the owner has never switched manually, **When** the phone setting changes while the app is open, **Then** the app follows it.
3. **Given** the owner switched manually, **When** the app is closed and reopened, **Then** it opens in the chosen theme, and later changes of the phone setting no longer change it.
4. **Given** the saved theme cannot be read, **When** the app opens, **Then** it falls back to the phone setting without any error message.

---

### Edge Cases

- Theme switched while the blocking loader is visible: the loader cannot be dismissed, but the top bar is behind it, so the switch is not reachable; if the phone setting changes during a save (Story 3, AS2), the loader changes color in place and the save continues.
- Theme switched while a toast is visible: the toast changes color in place and keeps its timer.
- Theme switched with the keyboard open: the keyboard stays open; the text being typed is kept.
- Startup error screen and loading before the database is ready: they also follow the theme (no white screen in dark mode).
- Phone in dark mode with "reduce motion": the switch is instant, without animation.
- Large text (130%): the theme button keeps its 44 dp size and the top bar still fits the language and theme buttons with the title.
- Skeleton rows and the "Mise à jour…" pill: dark versions, still visibly different from loaded content.
- Avatars (initials), status chips, the "saved on phone" pill and the duplicate barcode warning: dark versions from the token table, text still readable.
- Images or icons drawn in a single color (logo, empty-state icons): they use theme colors and never disappear on the dark background.
- Splash screen and app icon: unchanged in this feature (see Assumptions).

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The app MUST offer two themes, light and dark. The light theme MUST look exactly as today.
- **FR-002**: The dark theme MUST use the dark values of the design note's token table for every color role (ground, surface, surface-2, ink, ink-2, the line colors, muted, primary, primary text, primary soft and faint, debt, paid, partial and error families, selected chip, scrim, theme icon).
- **FR-003**: Screens and components MUST NOT contain theme-specific colors: every color MUST come from a shared color role whose value depends on the active theme, so that a component written once works in both themes. Adding the dark theme MUST NOT duplicate any screen.
- **FR-004**: A theme button MUST appear next to the language button on every screen that shows the language button: round, at least 44 dp, a moon in light mode and a sun in dark mode, with the accessibility label "Passer en mode sombre" / "Passer en mode clair" in the current language, and the button role.
- **FR-005**: One tap on the theme button MUST switch the entire app to the other theme immediately, including the top bar, tab bar, open sheets, toasts, the loader and the system status bar icons, without restarting the app.
- **FR-006**: Switching theme MUST keep all entered form values, the current screen, the scroll position and any open sheet.
- **FR-007**: On first launch, and as long as the owner has never switched manually, the theme MUST follow the phone's light or dark setting, including changes made while the app is open.
- **FR-008**: After a manual switch, the choice MUST be saved on the phone where the language is saved, and restored at every launch before the app's first screen is shown: the launch screen stays visible until the saved theme is applied, so the app never flashes the other theme. If the saved choice cannot be read, the app MUST fall back to the phone setting.
- **FR-009**: Dark colors MUST meet these contrasts: primary text at least 12:1 and secondary text at least 7:1 on cards; text on filled buttons at least 4.5:1. Debt, paid, partial and error colors MUST always come with a text label (as in the light theme).
- **FR-010**: In the dark theme, depth MUST come from lighter surfaces (ground → card → input), not shadows; only floating elements (FAB, toast) keep a shadow. The background MUST never be pure black.
- **FR-011**: The camera viewfinder MUST look the same in both themes.
- **FR-012**: The new texts (`switchToDark`, `switchToLight`) MUST be added in French, Arabic and English with the wording of the design note; in Arabic the theme button sits next to the language button in the mirrored layout.
- **FR-013**: Choosing a theme MUST NOT change any data, ledger record or operation-log entry: it is a display preference stored on the phone only.

### Key Entities

- **Theme preference**: the owner's choice, one of "follow the phone" (default, never switched) or a fixed "light" or "dark" after a manual switch. Stored with the language in the app's local settings; never synchronized, never part of the ledger records or the operation log.
- **Color role**: a named color (ground, surface, ink, debt, …) with one value per theme, as listed in the design note. Components refer to roles, never to values.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: In the dark theme, 100% of the screens listed in User Story 1, their sheets, toasts and the loader show no light surface, checked against the design canvas screens.
- **SC-002**: Measured on the dark token values, primary text on cards reaches at least 12:1, secondary text at least 7:1 and filled-button text at least 4.5:1.
- **SC-003**: Switching theme takes one tap and the whole screen has changed within 300 ms, with 100% of entered form values kept.
- **SC-004**: After a manual choice, the app opens in that theme on 10 out of 10 relaunches, with no visible flash of the other theme.
- **SC-005**: A search of the screen and component files finds zero color values: every color goes through a color role.
- **SC-006**: In a check with 3 shop owners using the app in a dim shop, each finds the theme button and switches theme without help.

## Assumptions

- Feature numbering: the owner chose 005 for the light and dark theme (2026-10-29). Account, encrypted backup and server sync, previously announced as 005 in `CLAUDE.md`, move to 006.
- The switch has two states only (light, dark), as in the approved design; there is no separate "follow the phone" option in the interface. Following the phone is only the behaviour before the first manual switch. Going back to "follow the phone" after a manual switch is out of scope.
- The splash screen, the app icon and the Android navigation bar keep their current look in this feature; only in-app screens and the status bar icons follow the theme.
- English uses "Switch to dark mode" / "Switch to light mode"; the French and Arabic wording comes from the design note.
- Contrast targets apply to the dark theme; the light theme is unchanged and already approved (003).
- No change to ledger data, the database structure or the operation log; the theme preference is a phone-only display setting stored like the language.
- The app keeps running in Expo Go: this feature adds no native code.
- Feature 006 will move the display preferences (theme, and possibly the language) to the phone's native preference store (SharedPreferences on Android, UserDefaults on iOS).
