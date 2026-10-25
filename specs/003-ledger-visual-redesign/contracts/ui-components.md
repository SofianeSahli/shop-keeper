# UI Components Contract (003)

Location: `shop-keeper-mobile/src/components/ui/`. Visual values come from [design-reference.md](../design-reference.md) §1–2 through token classes only.

Rules shared by all components:
- No `style` prop, no hex colors, no `StyleSheet` (ESLint-enforced).
- Labels arrive translated. Only `StatusChip` and `DayHeader` read translations themselves.
- Every pressable has `accessibilityRole`, `accessibilityLabel` (when the visible text is not enough) and `accessibilityState` (`selected` / `disabled`), with a hit area of at least `min-h-touch min-w-touch` (44 dp).
- Layout uses logical `start`/`end`, `ps`/`pe`, `ms`/`me`. Directional icons use `Icon directional`.
- Text that can grow uses `numberOfLines` only in rows. Detail screens show full text. Nothing has a fixed height that could clip at 130% font scale (use `min-h-*`).

| Component | Props (TypeScript) | Behaviour / states |
|---|---|---|
| `Icon` | `name: IoniconName; className?: string; size?: number; directional?: boolean` | `cssInterop` color from `text-*` class. `directional` swaps back/forward and chevron names in RTL. `accessibilityElementsHidden` by default (decorative). |
| `ScreenScroll` | `children; footer?: ReactNode; refreshing?: boolean; onRefresh?: () => void` | `bg-ground`, padding 16, gap 14. With `footer`: `KeyboardAvoidingView` + `StickyFooter`, and the scroll content gets bottom padding so the last item isn't hidden. |
| `StickyFooter` | `children; withSafeArea?: boolean` | `bg-surface`, top border `line`, padding 12/16/16. Adds `pb-safe` when `withSafeArea` (screens without a tab bar). |
| `StatCard` | `caption; amountMillimes; meta; tone?: 'neutral' \| 'debt'; onPress?; accessibilityHint?` | `AmountText size="display"` that shrinks to fit on one line. Pressable only when `onPress`. Its a11y label combines caption + formatted amount + meta. |
| `ActionTile` | `variant: 'primary' \| 'secondary'; icon; title; subtitle?; tint?: 'primary' \| 'paid'; onPress` | Primary: full width, `min-h-[84px]`, 52 icon tile, chevron (directional). Secondary: `min-h-[64px]`, 40 tinted tile. |
| `ListRow` | `leading: ReactNode; title; meta?; trailing?: ReactNode; trailingCaption?; chevron?: boolean; onPress?; selected?: boolean; accessibilityLabel?` | Whole row pressable, `min-h-row`. Title and meta are one line with ellipsis. Selected → `bg-primary-faint`. Separator `line-soft` comes from the parent card. |
| `Avatar` | `label: string; size?: 'md' \| 'lg'` | `bg-muted` circle showing `getInitials(label)` (max 2 letters, Arabic-safe). Hidden from a11y. |
| `AmountText` | `millimes: number; size?: 'body' \| 'heading' \| 'display' \| 'display-lg'; tone?: 'ink' \| 'debt' \| 'paid'; signed?: boolean; showCurrency?: boolean` | Uses `splitMillimeAmount`; millimes at about 0.65× size; `tabular-nums`; LTR digits inside RTL. `signed` prefixes `−` (payments). |
| `StatusChip` | `status: TicketStatus \| 'PAYMENT'` | Unpaid → debt-soft/debt; Partial → partial-soft/partial; Paid → paid-soft/paid; Payment → muted/ink-2. Always shows text (`unpaid`, `partiallyPaid`, `paid`, `payment`). |
| `FilterChips<T>` | `options: { value: T; label: string }[]; value: T; onChange(v: T)` | Single-select, horizontally scrollable. Selected → `bg-ink text-surface`, `accessibilityRole="radio"` + `selected`. The container has `accessibilityRole="radiogroup"`. |
| `SegmentedControl<T>` | `options: { value: T; label: string; tone: 'paid' \| 'partial' \| 'debt' }[]; value; onChange; accessibilityLabel` | Three equal 44-high segments. The selected tone follows the reference (Payé = `bg-paid text-surface`, Partiel = `partial-soft/partial`, Kridi = `debt-soft/debt`). |
| `SearchField` | `value; onChangeText; placeholder; accessibilityLabel` | 50 high, leading search icon, clear button (44) when not empty, `placeholder:text-ink-2`. |
| `QuantityStepper` | `value: number; onChange(n: number); min?: number (1); decrementLabel; incrementLabel` | 44×44 buttons. `−` is disabled at `min` (`accessibilityState.disabled`). Uses `clampQuantity`. `accessibilityRole="adjustable"` on the value, with increment/decrement actions. |
| `BottomSheet` | `visible; title; helper?; onClose(); children; confirmLabel?; onConfirm?; confirmDisabled?` | `Modal` slide up with a `bg-scrim` backdrop (press closes); `onRequestClose` handles Android back; handle bar; content scrolls; the confirm button sits in a `StickyFooter`. Focus starts on the title (`accessibilityRole="header"`). |
| `Fab` | `label; icon?: 'add'; onPress` | Pill, `absolute bottom-4 end-4` above the tab bar, shadow token only here. `accessibilityLabel = label`. |
| `InfoBanner` | `tone: 'warning' \| 'success' \| 'info'; title; text?; actionLabel?; onAction?` | Warning → partial-soft + partial-line border + warn-strong text; success → paid-soft/paid; info → primary-faint/primary. Warning uses `accessibilityRole="alert"`. |
| `EmptyState` | `icon; message; actionLabel?; onAction?` | Centred, a single action at most. |
| `DayHeader` | `dateKey: string; now?: Date` | Uppercase caption: `today` / `yesterday` / weekday + date (locale). `accessibilityRole="header"`. |

## Pure helpers (`src/components/ui/logic/`, each with a `*.test.ts`)

| Helper | Signature | Must cover |
|---|---|---|
| `getInitials` | `(label: string) => string` | Single word, two words, extra spaces, Arabic, emoji/symbols ignored, empty → `?`. |
| `splitMillimeAmount` | `(value: number, language) => { sign: '' \| '−'; dinars: string; millimes: string; currency: string }` | 0, 999, 1 000, 1 284 500 000, negative, every language; dinars + millimes equal `formatMillimeAmount` digits. |
| `groupByLocalDay` | `<T>(items: T[], getDate: (t: T) => string, now: Date) => DayGroup<T>[]` | Today/yesterday/older; local midnight boundary; newest first; stable order within a day. |
| `getQuickAmountOptions` | `(remaining: number) => QuickAmountOption[]` | remaining < 10 000 → only `whole` enabled; clamping; remaining 0 → all disabled. |
| `clampQuantity` | `(next: number, min = 1) => number` | Below min, non-integer, overflow. |
