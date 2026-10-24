# Quickstart: Validate Ledger Visual Redesign (003)

## Prerequisites

- Branch `003-ledger-visual-redesign`, run from `shop-keeper-mobile/`.
- `npm ci`, then `npx expo install @expo-google-fonts/ibm-plex-sans-arabic` (done in setup).
- A phone or emulator (Android with edge-to-edge, plus iOS if available). Camera scenarios need a real device or an emulator with a virtual camera.
- For seeded scenarios: a fresh install, then create customers **Ali** (phone set), **Sonia** (no phone), **Hédi** (no tickets), and products **Lait 1,250**, **Pain 0,250**, **Huile 7,900** (Huile with a barcode).

## 1. Automated gate

```bash
npm run validate     # lint (incl. no-hex / no-style rules) + typecheck (typed routes) + vitest
```
Expected: all green. The 001/002 service tests are unchanged. New tests cover the `components/ui/logic/*`, `i18n/format`, translation parity, `saleService`, debtor summaries, dashboard counts, the walk-in migration and guards, and the void cascade.

Lint check: temporarily add `className={'#fff'}` or `style={{}}` in any `.tsx`. `npm run lint` must fail with the constitution message. Revert afterwards.

## 2. Scenarios by user story

| # | Story | Steps | Expected |
|---|---|---|---|
| 1 | US1 | Open every tab and form in FR, then switch to AR via the language pill. | Same top bar/tab bar style; Plex font; arrows and chevrons mirrored; tab order reversed; amounts keep LTR digits. |
| 2 | US1 | Airplane mode on; repeat 1. | Identical (no network use). |
| 3 | US8 | Home → New ticket → add Lait ×2, Pain ×1 → keep **Payé**, no customer → button reads "Encaisser 2,750 DT" → save. | Ticket list shows "Client de passage", chip Payé; Home sales +2,750, kridi unchanged. |
| 4 | US8 | New ticket for Ali, Huile ×1 → **Partiel**, amount 3,000. | Line "Reste au kridi : 4,900 DT"; button "Encaisser 3,000 DT · reste 4,900 DT au kridi"; after save, chip Partiel; Ali owes 4,900; kridi +4,900. |
| 5 | US8 | Partiel with 0, empty, 7,900, 8,000. | Saving blocked with `partialAmountInvalid`; at 7,900 the message suggests Payé. |
| 6 | US8 | Kridi or Partiel with no customer → save. | Blocked; customer card border in debt color plus "Le kridi a besoin d'un client"; draft kept. |
| 7 | US8 | Kridi for Sonia, Pain ×4 → "Mettre 1,000 DT au kridi". | No payment created; chip Impayé. |
| 8 | US8 | Void the walk-in ticket from scenario 3 (ticket detail → Annuler, confirm). | Ticket and its at-sale payment voided; Home sales −2,750. Payment history shows the payment as cancelled. |
| 9 | US8 | Try to edit the walk-in customer, pick it in the payment form, or edit the walk-in ticket total. | Not offered, or rejected with `walkInLocked`. |
| 10 | US2 | Home. | Date, "Enregistré sur le téléphone" pill; stat cards match the totals/counts above; top-3 = Ali, Sonia (desc); tapping a row opens their ledger; tapping the kridi card opens Customers on "Doivent". |
| 11 | US2 | Pay off all debts → Home. | Positive empty state instead of the top-3 list. |
| 12 | US3 | New ticket for Ali (owes) → steppers + / − on a line. | Card shows "Doit déjà …"; qty never < 1; line and total update; total + segmented control + button stay visible with the keyboard open and after scrolling. |
| 13 | US3 | With 2 lines, tap Nouveau (customer) and "Produit absent ? Le créer ici", create both. | Lines kept; new customer selected; new product added with qty 1 (002 rules). |
| 14 | US3 | Draft with lines → Android system back, iOS swipe, top-bar back, tab tap. | Exactly one confirmation each time; Cancel keeps the draft. |
| 15 | US4 | Home → Encaisser → pick Ali → Enregistrer. | 3 taps (SC-003); oldest unpaid ticket preselected; "Tout le ticket" prefilled; success. |
| 16 | US4 | Ticket with 15,000 remaining: tap 10, 20, 50. | 10 → 10,000 (remaining-after 5,000); 20/50 clamp to 15,000 → "Ticket entièrement payé". |
| 17 | US4 | Ticket with 8,000 remaining. | Only "Tout le ticket" enabled. |
| 18 | US4 | "Changer" → sheet → choose another ticket → Valider; Android back closes the sheet. | Current choice marked; form shows the new ticket; back closes without changing. |
| 19 | US5 | Customers. | "Doivent (2)" selected; order by balance; "À jour (n)" shows Hédi; search "so" finds Sonia; no walk-in anywhere. |
| 20 | US5 | Ledgers of Ali and Sonia. | Call button only for Ali (opens the dialer); history grouped Aujourd'hui / Hier / date; payments as "−X" in paid color; at-sale payment reads "Payé à la vente". |
| 21 | US6 | Product form: scan Huile's code; scan a new code; deny the camera. | Duplicate banner + Save disabled + "Ouvrir ce produit"; "Nouveau code, prêt à enregistrer"; permission block with "Autoriser la caméra" while the code field stays editable. |
| 22 | US7 | Tickets: each filter; open a ticket; edit/void from the detail. Products: search "lai"; remove from the form. | No destructive buttons in list rows; filters by derived status; voided tickets hidden. |
| 23 | SC-007 | Home → New ticket → Scan → Scan (2 known codes) → Encaisser. | Recorded in 4 taps with no customer step. |

## 3. Cross-cutting checks

- **Font scale 130%** (Android Settings → Display size and text) in FR and AR on Home, Ticket form, Payment form, Customers: nothing clipped, footer reachable (SC-004).
- **Long values**: customer label of 40 characters (one line in rows, full on the ledger); amount 1 284 500,000 DT in a stat card shrinks to fit.
- **Font failure**: in dev, temporarily rename one font key in `useFonts`; the app starts with the system font.
- **Screen reader** (TalkBack / VoiceOver): tabs, chips and segments announce selected; the stepper announces its value; disabled Save is announced.
- **Startup states**: the database failure screen and retry use tokens and translated text.
