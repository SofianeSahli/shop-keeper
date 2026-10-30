# Design note: dark mode (light / dark theme)

**Status**: approved design, specified in [005-dark-mode](../../../specs/005-dark-mode/spec.md).

**Source**: "Carnet Kridi — Mobile UI" design canvas, row "Mode sombre":
- `DarkPalette` — every light token with its dark value, the rules, dark toasts and loader.
- Dark screens: `MainDark` (Accueil), `TicketFormDark` (Nouveau ticket, "Partiel" selected), `PaymentFormDark` (Encaisser, ticket sheet open), `CustomersDark` (Clients), `CustomerLedgerDark` (Carnet d'un client), `ProductFormDark` (Ajouter un produit, duplicate warning).

## Behaviour

- **Theme toggle** in the top bar, right next to the language button, on every screen that shows the language button: a round 44 dp button. Dark mode shows a sun (switch to light), light mode shows a moon (switch to dark). One tap switches Light ↔ Dark.
- **First launch** follows the phone's system setting. After the first manual switch, the choice is saved (same storage as the language) and restored on every launch.
- Switching theme never loses form values (same rule as switching language).
- Arabic (RTL): same colors, mirrored layout.
- Screens and components do not change: only token values change. No screen may hard-code a light or dark color; everything goes through the design tokens (Tailwind config / CSS variables), so a component written once works in both themes.

## Rules

- Background is green-black `#0E1513`, never pure black.
- Depth comes from lighter surfaces (ground → card → input), not shadows. Only floating elements (FAB, toast) keep a shadow.
- Meaning colors are lightened for dark backgrounds (debt light orange, paid sea green, error light red) and always come with a text label.
- Filled buttons are one step lighter than in light mode, with white text (contrast ≥ 4.5:1).
- Primary text ≥ 12:1 and secondary text ≥ 7:1 on cards.
- The camera viewfinder stays dark in both themes.

## Tokens (light → dark)

| Token | Light | Dark | Use |
|---|---|---|---|
| `ground` | `#F3F5F4` | `#0E1513` | Screen background |
| `surface` | `#FFFFFF` | `#17211E` | Cards, top bar, tab bar, sheets, sticky footer |
| `surface-2` | `#F3F5F4` | `#1E2A26` | Inputs, −/+ buttons, language and theme buttons, loader card |
| `ink` | `#0F1F1A` | `#E8EEEC` | Primary text |
| `ink-2` | `#4A5A54` | `#A3B2AC` | Secondary text, inactive tabs |
| `line` | `#DCE3E0` | `#2A3833` | Card and bar borders |
| `line-soft` | `#EDF1EF` | `#22302B` | Row separators |
| `line-strong` | `#C9D3CF` | `#3A4A44` | Input borders, outlined buttons |
| `line-dashed` | `#9AAAA4` | `#4E5F59` | Dashed "new" / walk-in cards, unselected radio |
| `muted` | `#EEF1F0` | `#24312C` | Avatar background (avatar text `#2C3D37` → `#C9D6D1`) |
| `primary` (fill) | `#164E9C` | `#2F6FC4` | Primary buttons, logo, FAB |
| `primary-text` | `#164E9C` | `#8DB8F2` | Links, active tab, selected border |
| `primary-soft` | `#DCE8F7` | `#1C2E47` | Active tab pill, product icon tile |
| `primary-faint` | `#EEF4FC` | `#1A2A40` | Selected row background |
| `debt` | `#9A3412` | `#F5A06A` | Amounts owed |
| `debt-strong` | `#7C2D12` | `#FBC9A3` | Text on debt background |
| `debt-soft` / `debt-line` | `#FDEBDD` / `#F6CFAE` | `#3A2416` / `#5A3520` | Kridi card, Unpaid chip |
| `paid` (fill) | `#0F5F57` | `#1F7A6E` | Encaisser buttons, "Payé" selected |
| `paid-text` | `#0F5F57` | `#5FD0BE` | Paid amounts, success actions |
| `paid-soft` / `paid-line` | `#DDF3EE` / `#A9DCD2` | `#12302B` / `#22564D` | Success toast, "saved on phone" pill, Paid chip (chip text `#7FDCCB`) |
| `partial` | `#854D0E` | `#F2C46B` | Partial chip text |
| `partial-soft` / `partial-line` | `#FEF3C7` / `#F3D27A` | `#3A2F12` / `#6B5418` | Partial chip, duplicate warning (text `#F5D58A`, button `#8A5A16`) |
| `error` | `#A3221A` | `#FF8A80` | Error text and action (icon circle `#D9443B`) |
| `error-soft` / `error-line` | `#FDE8E6` / `#F4BDB7` | `#3B1816` / `#6B2A25` | Error toast (detail text `#F5B8B2`) |
| `selected-chip` | bg `#0F1F1A` / text `#FFFFFF` | bg `#E8EEEC` / text `#0E1513` | Selected filter chips |
| `scrim` | `rgba(15,31,26,0.45)` | `rgba(0,0,0,0.6)` | Behind sheets and the blocking loader |
| theme icon | moon, `ink` | sun, `#F2C46B` | Theme toggle |

## Strings (FR / AR)

| Key | fr | ar |
|---|---|---|
| `switchToDark` | Passer en mode sombre | التبديل إلى الوضع الداكن |
| `switchToLight` | Passer en mode clair | التبديل إلى الوضع الفاتح |
