# Quickstart: Validate the Light and Dark Theme (005)

## Prerequisites

- From `shop-keeper-mobile/`: `npm install`.
- `npx expo start` and open the app in Expo Go (no native code in this feature).
- A phone where you can switch the system between light and dark.

## 1. Automated gate

```bash
npm run validate
```

Expected: lint, typecheck and tests all green. New tests cover:
- `tokens.css`: every role has a light and a dark value; the contrast targets of [research R8](./research.md) pass in dark.
- `themeStorage` on the real in-memory SQLite: a saved choice reads back; no row and unknown values read as "follow the phone"; saving twice keeps one row; the language row is untouched.
- No color value in `.ts` / `.tsx` files (the existing grep check, now also covering `rgba(`).

## 2. Scenarios

| # | Spec | Steps | Expected |
|---|---|---|---|
| 1 | US3-AS1 | Clear the app's data. Phone in dark. Open the app. | The launch screen, then a dark Home; no light frame in between. |
| 2 | US3-AS1 | Clear the app's data. Phone in light. Open the app. | Light, identical to before this feature. |
| 3 | US3-AS2 | Without tapping the theme button, switch the phone between light and dark with the app open. | The app follows each time. |
| 4 | US2-AS1, AS4 | Look at the top bar on Home, a list and a form; turn on TalkBack / VoiceOver. | Round theme button right after the language button; moon in light, sun in dark; read as "Passer en mode sombre" / "Passer en mode clair". |
| 5 | US2-AS2, AS3 | On "Nouveau ticket" with 2 articles, "Partiel" and an amount typed, tap the theme button. Repeat with the payment ticket sheet open. | Whole screen, top bar, tab bar, sheet and status bar icons switch at once; every value, the scroll position and the sheet stay. |
| 6 | US1 | In dark, open each screen of the design canvas: Accueil, Nouveau ticket (Partiel), Encaisser with the ticket sheet, Clients, a customer's ledger, Ajouter un produit with a duplicate barcode. | Matches `MainDark`, `TicketFormDark`, `PaymentFormDark`, `CustomersDark`, `CustomerLedgerDark`, `ProductFormDark`; no white surface. |
| 7 | US1-AS3, 004 | In dark: save a ticket (success toast), force an error toast, slow mode for the loader, open a confirmation, leave a required field empty and tap Save. | Dark toasts and loader from the note, darker scrim, red field outline readable. |
| 8 | US1-AS5 | Open the scanner in light, then in dark. | Viewfinder looks the same. |
| 9 | US3-AS3 | Switch to light while the phone is dark. Close the app fully and reopen it 10 times; then switch the phone to light and back. | Always light, never a dark flash; the phone setting no longer changes it. |
| 10 | US2-AS5 | Repeat 5 and 6 in Arabic. | Same colors, mirrored layout; theme button next to the language button on the mirrored side. |
| 11 | Edge | Text size 130%; a toast visible while switching; the keyboard open while switching. | Top bar fits both buttons; toast changes color in place and keeps its timer; keyboard stays open. |
| 12 | FR-001 | Compare light screens with the 003 screenshots in the mobile README. | No visible difference. |
