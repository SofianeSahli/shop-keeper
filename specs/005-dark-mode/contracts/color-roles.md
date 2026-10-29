# Contract: color roles (005)

Source of every value: [dark-mode.md](../../../shop-keeper-mobile/docs/design/dark-mode.md). Light values are today's (FR-001, research R6); new and split roles come from research R7. Each role is a CSS variable `--color-<role>` in `src/theme/tokens.css` (RGB channels) and a Tailwind color `<role>`.

## Surfaces and text

| Role | Light | Dark | Used for |
|---|---|---|---|
| `ground` | `#F3F5F4` | `#0E1513` | Screen background |
| `surface` | `#FFFFFF` | `#17211E` | Cards, top bar, tab bar, sheets, sticky footer |
| `surface-2` | `#FFFFFF` | `#1E2A26` | Inputs, amount box, −/+ stepper, language and theme buttons, loader card (all `bg-surface` today) |
| `ink` | `#0F1F1A` | `#E8EEEC` | Primary text |
| `ink-2` | `#4A5A54` | `#A3B2AC` | Secondary text, inactive tabs |
| `line` | `#DCE3E0` | `#2A3833` | Card and bar borders |
| `line-soft` | `#EDF1EF` | `#22302B` | Row separators |
| `line-strong` | `#C9D3CF` | `#3A4A44` | Input borders, outlined buttons |
| `line-dashed` | `#C9D3CF` | `#4E5F59` | Dashed "new" and walk-in cards |
| `muted` | `#EEF1F0` | `#24312C` | Avatar background, pressed rows |
| `avatar-ink` | `#4A5A54` | `#C9D6D1` | Avatar initials |
| `skeleton` | `#E6EBE9` | `#24312C` | Skeleton bars |
| `scrim` | `rgba(15,31,26,0.45)` | `rgba(0,0,0,0.6)` | Behind sheets and the loader |
| `elevation` | `#0F1F1A` | `#000000` | Shadow color of FAB and toast |

## Fills and text on fills

| Role | Light | Dark | Used for |
|---|---|---|---|
| `on-fill` | `#FFFFFF` | `#FFFFFF` | Text and icons on any `*-fill` |
| `primary-fill` | `#164E9C` | `#2F6FC4` | Primary buttons, logo, FAB, info icon circle |
| `paid-fill` | `#0F5F57` | `#1F7A6E` | Encaisser buttons, "Payé" selected, success icon circle |
| `debt-fill` | `#9A3412` | `#B4501E` | Confirm button of destructive actions (was `bg-debt`) |
| `error-fill` | `#A3221A` | `#D9443B` | Error toast icon circle |
| `warn-fill` | `#713F12` | `#8A5A16` | Warning banner button |
| `chip-selected` | `#0F1F1A` | `#E8EEEC` | Selected filter chip background and border |
| `on-chip-selected` | `#FFFFFF` | `#0E1513` | Selected filter chip text |

## Meaning colors (text, borders, soft backgrounds)

| Role | Light | Dark |
|---|---|---|
| `primary` | `#164E9C` | `#8DB8F2` |
| `primary-soft` | `#DCE8F7` | `#1C2E47` |
| `primary-faint` | `#EEF4FC` | `#1A2A40` |
| `primary-line` | `#B7CDEB` | `#2B4A73` |
| `debt` | `#9A3412` | `#F5A06A` |
| `debt-strong` | `#7C2D12` | `#FBC9A3` |
| `debt-soft` | `#FDEBDD` | `#3A2416` |
| `debt-line` | `#F6CFAE` | `#5A3520` |
| `paid` | `#0F5F57` | `#5FD0BE` |
| `paid-soft` | `#DDF3EE` | `#12302B` |
| `paid-line` | `#A9DCD2` | `#22564D` |
| `partial` | `#854D0E` | `#F2C46B` |
| `partial-soft` | `#FEF3C7` | `#3A2F12` |
| `partial-line` | `#F3D27A` | `#6B5418` |
| `warn-strong` | `#713F12` | `#F5D58A` |
| `error` | `#A3221A` | `#FF8A80` |
| `error-soft` | `#FDE8E6` | `#3B1816` |
| `error-line` | `#F4BDB7` | `#6B2A25` |
| `error-strong` | `#6B1F18` | `#F5B8B2` |
| `theme-icon` | `#0F1F1A` | `#F2C46B` |

## Same in both themes (FR-011)

| Role | Value | Used for |
|---|---|---|
| `viewfinder` | `#0B1411` | Camera viewfinder background |
| `viewfinder-mark` | `#FFFFFF` | Viewfinder corners (was `border-surface`) |
| `scan-line` | `#F59E0B` | Scan line |

## Class renames in components

| Today | After | Why |
|---|---|---|
| `bg-primary` | `bg-primary-fill` | Fill and text differ in dark |
| `bg-paid` | `bg-paid-fill` | Same |
| `bg-error` (icon circle, banner button) | `bg-error-fill` | Same |
| `bg-debt` (confirm button) | `bg-debt-fill` | Same; white text needs a dark-enough fill (5.1:1 in dark) |
| `bg-warn-strong` | `bg-warn-fill` | Same |
| `text-surface` on fills | `text-on-fill` | `surface` is dark in dark mode |
| `bg-surface/15` on the primary action tile | `bg-on-fill/15` | Translucent white on a fill |
| `bg-ink border-ink` + `text-surface` (selected chip) | `bg-chip-selected border-chip-selected` + `text-on-chip-selected` | Inverted chip |
| `text-ink-2` in `Avatar` | `text-avatar-ink` | Own dark value |
| `bg-surface` on inputs, the stepper, the language button, the loader card, outlined and small buttons, unselected chips and segments, and the customer picker card | `bg-surface-2` | One step lighter than cards in dark; white in light as today |
| `border-line-strong` on dashed cards | `border-line-dashed` | Own dark value |
| `shadow-ink/…` | `shadow-elevation/…` | Shadows stay dark |
| `border-surface` (viewfinder corners) | `border-viewfinder-mark` | Stays white |
| `text-surface` on the viewfinder, the camera permission button (`bg-surface` + `text-ink`) | `text-viewfinder-mark`, `bg-viewfinder-mark` + `text-viewfinder` | Same in both themes (FR-011) |

All other color classes keep their names; only their values follow the theme.
