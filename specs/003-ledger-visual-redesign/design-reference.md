# Design Reference: Ledger Visual Redesign (003)

Source: "Carnet Kridi — Mobile UI" design canvas (8 artboards, 390×844). This file transcribes it so it can be implemented without opening the canvas.

## 1. Tokens

Put these in `tailwind.config.js` (`theme.extend`) so components use classes such as `bg-ground`, `text-ink`, `text-debt`. Never hard-code hex values in `.tsx` files.

### Colors

| Token | Hex | Use |
|---|---|---|
| `ground` | `#F3F5F4` | Screen background |
| `surface` | `#FFFFFF` | Cards, bars, inputs |
| `ink` | `#0F1F1A` | Primary text |
| `ink-2` | `#4A5A54` | Secondary text, inactive tab labels |
| `line` | `#DCE3E0` | Card and bar borders |
| `line-soft` | `#EDF1EF` | Row separators |
| `line-strong` | `#C9D3CF` | Input borders, secondary buttons |
| `muted` | `#EEF1F0` | Avatar background |
| `primary` | `#164E9C` | Primary buttons, active tab, links |
| `primary-soft` | `#DCE8F7` | Active tab pill, selected row background `#EEF4FC` (`primary-faint`) |
| `debt` | `#9A3412` | Amounts owed |
| `debt-strong` | `#7C2D12` | Text on debt background |
| `debt-soft` | `#FDEBDD` | Debt card / chip background, border `#F6CFAE` (`debt-line`) |
| `paid` | `#0F5F57` | Paid amounts, payment button, success |
| `paid-soft` | `#DDF3EE` | Paid chip, "saved on phone" pill |
| `partial` | `#854D0E` | Partial chip text |
| `partial-soft` | `#FEF3C7` | Partial chip, warning banner background, border `#F3D27A` |
| `warn-strong` | `#713F12` | Warning banner text and button |
| `scrim` | `rgba(15,31,26,0.45)` | Bottom-sheet backdrop |

### Type (IBM Plex Sans Arabic)

| Token | Size / weight | Use |
|---|---|---|
| `display` | 28–34 / 700 | Stat and balance amounts (tabular digits) |
| `title` | 19 / 700 | Top-bar title |
| `heading` | 15–16 / 700 | Section titles, primary row text |
| `body` | 15 / 400–600 | Row labels |
| `label` | 13 / 600 | Field labels, card captions (color `ink-2`) |
| `caption` | 12.5 / 400 | Row meta lines |
| `tab` | 12 / 600 (700 active) | Tab labels |

Amounts: dinars in the main size, `,millimes` in a smaller size (about 0.65×) in the same color, then ` DT` (Arabic: `د.ت`). Digits stay LTR inside RTL layouts.

### Shape and spacing

- Radii: cards 18, large action 20, buttons and inputs 14–16, chips and pills full.
- Screen padding 16; gap between blocks 14–16; row min height 60–68; fixed footer padding 12/16/16.
- Minimum touch target 44 (primary buttons 52–56 high).
- Borders 1 px; selected input or card 1.5 px in `primary`.
- Shadows only on the floating button.

## 2. Components (`src/components/ui/` or `src/features/ledger/components/ui/`)

| Component | Description |
|---|---|
| `ScreenScroll` | Replaces `LedgerPage`: `ground` background, padding 16, gap 14, optional `footer` slot rendered as `StickyFooter` |
| `StatCard` | Caption, amount (`AmountText` display), meta line; `tone: neutral | debt`; optional `onPress` |
| `ActionTile` | `variant: primary` (full width, 84 high, icon tile 52, title + subtitle + chevron) or `secondary` (64 high, icon tile 40 tinted, label) |
| `ListRow` | Avatar or icon tile, title, meta, trailing amount + caption, chevron optional; whole row pressable |
| `Avatar` | 40–44 circle, initials (first letters of the label, max 2) |
| `AmountText` | Formats millimes via `formatMillimeAmount`; `size`, `tone: ink | debt | paid`, signed option for payments (−20,000) |
| `StatusChip` | Unpaid (`debt-soft`/`debt`), Partial (`partial-soft`/`partial`), Paid (`paid-soft`/`paid`), always with text |
| `FilterChips` | Single-select pill group, selected = `ink` background / white text, `accessibilityState.selected` |
| `SearchField` | 50 high, search icon, placeholder |
| `QuantityStepper` | − value + ; 44×44 buttons; min 1 |
| `StickyFooter` | Surface bar with top border, stays above the keyboard |
| `BottomSheet` | Scrim, handle, title, helper line, content, confirm button; closes on backdrop and Android back |
| `Fab` | Pill button with + icon and label, bottom-end above the tab bar (logical `end` so it mirrors in RTL) |
| `InfoBanner` | `tone: warning | success | info`, icon, title, text, optional action button |
| `EmptyState` | Icon, one sentence, optional action |
| `DayHeader` | Uppercase caption: "Aujourd'hui", "Hier", or weekday + date |

## 3. Screens

### Shell
- Top bar 60 high, `surface`, bottom border. Root screens: app mark (34 square, `primary`, letter K / ك) + title. Form/detail screens: 48 back button (arrow mirrors in RTL) + title. Right side: language pill (globe + FR / عربي + chevron), 44 high.
- Tab bar 78 high incl. bottom inset; 5 tabs; active tab = icon inside a 56×30 `primary-soft` pill, label `primary` 700; inactive `ink-2`.

### Home (`/`)
1. Row: weekday + date (`label` `ink-2`) — "saved on this phone" pill (`paid-soft`, phone-check icon).
2. Two `StatCard`s side by side: "Ventes du jour" (amount, "DT · n tickets") and "Kridi à encaisser" (debt tone, amount, "DT · n tickets ouverts", opens Customers filtered on Owes).
3. `ActionTile` primary: "Nouveau ticket" / "Scanner ou choisir les articles".
4. Two `ActionTile` secondary: "Encaisser" (paid tint, banknote icon), "Produit" (primary tint, cube icon).
5. Card "Ils doivent le plus" + "Tout voir" link; 3 `ListRow`s: avatar, label, "n tickets · depuis le d MMM", amount in `debt`.

### Ticket form
- Customer: label "Client"; pinned card (1.5 `primary` border) with avatar, label, "Doit déjà X DT" in `debt`, chevron; beside it a 56-wide dashed button "Nouveau" (person-add icon).
- Two equal buttons 64 high: "Scanner" (primary filled, scan icon) and "Chercher" (outlined, search icon).
- Card "Articles" with count; each line: name, "unit DT × qty = total", `QuantityStepper`.
- Last row in the card: "+ Produit absent ? Le créer ici" (opens the existing inline product flow).
- Helper line: draft is preserved when creating a customer or product.
- Customer card when none is chosen: dashed card "Client de passage" with "Choisir un client" — allowed only while "Payé" is selected.
- `StickyFooter` (top to bottom):
  1. "Total du ticket" + display amount.
  2. Segmented control "Paiement" with 3 equal options (44 high): "Payé" (selected = `paid` background, white text), "Partiel" (selected = `partial-soft` / `partial`), "Kridi" (selected = `debt-soft` / `debt`). Default "Payé".
  3. When "Partiel": an amount field "Montant reçu" with "DT" suffix and the line "Reste au kridi : X DT" in `debt`.
  4. Primary button, label and color by choice: Payé → `paid` "Encaisser X DT"; Partiel → `paid` "Encaisser Y DT · reste Z au kridi"; Kridi → `primary` "Mettre X DT au kridi".
- Error states: no customer with Partiel/Kridi → customer card border `debt` + message "Le kridi a besoin d'un client"; invalid partial amount → message under the field.

### Payment form
- Customer card: avatar, label, "Doit en tout X DT · n tickets" in `debt`, "Changer".
- "Ticket à payer": card with receipt icon, "Ticket du d MMM", "Reste X DT sur Y DT", "Changer" → `BottomSheet` "Choisir un ticket impayé" / "Le plus ancien est choisi par défaut." with radio rows (label, status · total, remaining in `debt`) and "Valider".
- "Montant reçu": 72-high field, amount 34/700, "DT" suffix; `FilterChips`-like quick amounts: "Tout le ticket", "10 DT", "20 DT", "50 DT".
- Remaining line: `debt-soft` "Reste après paiement X DT", or `paid-soft` "Ticket entièrement payé · Payé".
- `StickyFooter`: `paid` button "Enregistrer X DT".
- Ticket history rows (customer ledger, ticket detail): an at-sale payment reads "Payé à la vente".

### Customers
- Search field "Nom ou surnom…"; `FilterChips` "Doivent (n)", "À jour (n)", "Tous"; caption "Triés par dette, la plus élevée en premier".
- One card containing `ListRow`s: avatar, label, "n tickets ouverts · n j" (or "Tout payé le d MMM"), amount (`debt` + "DT dus", or `paid` "À jour").
- `Fab` "+ Client".

### Customer ledger
- Top bar: back, customer label, edit (pencil) button.
- Balance card: "Reste à payer", display amount in `debt`, "n tickets ouverts · plus ancien : d MMM"; call button (48 circle, `paid-soft`) only if a phone exists; two buttons "Ticket" (outlined) and "Encaisser" (`paid`).
- "Historique": `DayHeader` groups; ticket rows (icon tile "T", "Ticket · n articles", `StatusChip`, total + "reste X"); payment rows (paid tile "−", "Payé en espèces", chip "Paiement", "−X" in `paid`, "sur ticket du d MMM").
- Tab bar visible, Customers active.

### Product form
- Viewfinder 200 high, radius 20, dark, four white corners, amber scan line, caption "Visez le code-barres ou le QR code". Permission denied → same block with message + "Autoriser la caméra".
- "Code-barres (facultatif)" input + 52 square re-scan button. States: duplicate → `InfoBanner` warning "Ce code existe déjà" + "Name · price" + "Ouvrir ce produit"; new → success line "Nouveau code, prêt à enregistrer". Hint: "Pas de caméra ? Tapez le code à la main ou laissez vide."
- "Nom du produit"; "Prix de vente" with "DT" suffix and hint "3 chiffres après la virgule : 1,250 = 1 dinar 250 millimes".
- `StickyFooter`: "Enregistrer le produit" (disabled style while duplicate).

### Ticket list (not drawn in the canvas — follow the same patterns)
- `FilterChips`: Tous, Impayés, Partiels, Payés. `DayHeader` groups, newest first. Rows: avatar, customer label, "n articles · HH:mm", `StatusChip`, amount (remaining for open tickets, total for paid). `Fab` "+ Ticket". Row opens ticket detail; edit and void live there.

### Product catalog (not drawn — same patterns)
- `SearchField` "Nom ou code-barres…"; rows: cube tile, name, code (or "Sans code"), price. `Fab` "+ Produit". Row opens the product form; remove lives there.

## 4. New translation keys (FR / AR)

| Key | fr | ar |
|---|---|---|
| `savedOnPhone` | Enregistré sur le téléphone | محفوظ على الهاتف |
| `kridiToCollect` | Kridi à encaisser | كريدي للتحصيل |
| `ticketsCount` | {count} tickets | {count} فاتورة |
| `openTicketsCount` | {count} tickets ouverts | {count} فاتورة مفتوحة |
| `newTicketHint` | Scanner ou choisir les articles | امسح الرمز أو اختر السلع |
| `collect` | Encaisser | تحصيل دفعة |
| `topDebtors` | Ils doivent le plus | الأكثر ديناً |
| `seeAll` | Tout voir | عرض الكل |
| `sinceDate` | depuis le {date} | منذ {date} |
| `alreadyOwes` | Doit déjà {amount} | عليه {amount} |
| `scan` | Scanner | مسح |
| `searchAction` | Chercher | بحث |
| `missingProduct` | Produit absent ? Le créer ici | منتج غير موجود؟ أنشئه هنا |
| `draftKept` | Le ticket reste en brouillon : créer un client ou un produit ne fait rien perdre. | تبقى الفاتورة مسودة: إضافة عميل أو منتج لا تحذف شيئاً. |
| `ticketTotal` | Total du ticket | مجموع الفاتورة |
| `ticketToPay` | Ticket à payer | الفاتورة المراد دفعها |
| `change` | Changer | تغيير |
| `amountReceived` | Montant reçu | المبلغ المستلم |
| `wholeTicket` | Tout le ticket | كامل الفاتورة |
| `remainingAfter` | Reste après paiement | المتبقي بعد الدفع |
| `ticketFullyPaid` | Ticket entièrement payé | الفاتورة مدفوعة بالكامل |
| `oldestSelected` | Le plus ancien est choisi par défaut. | الأقدم مختار تلقائياً. |
| `validate` | Valider | تأكيد |
| `customerSearchPlaceholder` | Nom ou surnom… | الاسم أو اللقب… |
| `filterOwes` | Doivent ({count}) | عليهم دين ({count}) |
| `filterUpToDate` | À jour ({count}) | خالصون ({count}) |
| `filterAll` | Tous | الكل |
| `sortedByDebt` | Triés par dette, la plus élevée en premier | مرتبون حسب الدين، الأكبر أولاً |
| `upToDate` | À jour | خالص |
| `owedSuffix` | DT dus | د.ت مستحقة |
| `call` | Appeler | اتصال |
| `oldestOpen` | plus ancien : {date} | الأقدم: {date} |
| `today` | Aujourd'hui | اليوم |
| `yesterday` | Hier | أمس |
| `paidCash` | Payé en espèces | دفع نقداً |
| `onTicketOf` | sur ticket du {date} | على فاتورة {date} |
| `aimAtCode` | Visez le code-barres ou le QR code | وجّه الكاميرا نحو الرمز |
| `codeExists` | Ce code existe déjà | هذا الرمز موجود |
| `openThisProduct` | Ouvrir ce produit | فتح هذا المنتج |
| `newCodeReady` | Nouveau code, prêt à enregistrer | رمز جديد، جاهز للحفظ |
| `noCameraHint` | Pas de caméra ? Tapez le code à la main ou laissez vide. | لا توجد كاميرا؟ اكتب الرمز يدوياً أو اتركه فارغاً. |
| `millimesHint` | 3 chiffres après la virgule : 1,250 = 1 dinar 250 millimes | 3 أرقام بعد الفاصلة: 1,250 = دينار و250 مليم |
| `allowCamera` | Autoriser la caméra | السماح بالكاميرا |
| `itemsCount` | {count} articles | {count} سلع |
| `filterUnpaid` / `filterPartial` / `filterPaid` | Impayés / Partiels / Payés | غير مدفوعة / جزئية / مدفوعة |
| `productSearchPlaceholder` | Nom ou code-barres… | الاسم أو الرمز… |
| `noCode` | Sans code | بدون رمز |
| `walkInCustomer` | Client de passage | حريف عابر |
| `paymentStatus` | Paiement | الدفع |
| `statusPaidShort` / `statusPartialShort` / `statusKridiShort` | Payé / Partiel / Kridi | مدفوع / جزئي / كريدي |
| `collectAmount` | Encaisser {amount} | استلام {amount} |
| `collectWithRemainder` | Encaisser {paid} · reste {rest} au kridi | استلام {paid} · الباقي {rest} في الكريدي |
| `putOnKridi` | Mettre {amount} au kridi | سجّل {amount} في الكريدي |
| `remainingToKridi` | Reste au kridi : {amount} | الباقي في الكريدي: {amount} |
| `kridiNeedsCustomer` | Le kridi a besoin d'un client | الكريدي يحتاج إلى عميل |
| `partialAmountInvalid` | Saisissez un montant supérieur à 0 et inférieur au total. | أدخل مبلغاً أكبر من 0 وأقل من المجموع. |
| `paidAtSale` | Payé à la vente | مدفوع عند البيع |

Reuse existing keys where they already exist (`newTicket`, `recordPayment`, `customers`, `remaining`, `paid`, `partiallyPaid`, `unpaid`, …). Arabic wording follows the current `ar.json` convention ("فاتورة" for ticket); validate with Tunisian shop owners.
