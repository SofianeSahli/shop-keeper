# Data Model: Light and Dark Theme (005)

No schema change: the existing `app_settings` table gets one more key. No ledger record, audit event or `pending_changes` entry is created by this feature (FR-013).

## Theme choice (`app_settings` row)

| Field | Type | Values | Notes |
|---|---|---|---|
| `theme` | string or absent | `"light"`, `"dark"`, or absent | Absent means "never switched: follow the phone". |

- **Where**: SQLite table `app_settings`, row `key = 'theme'`, next to the `language` row (research R1). Upserted with `INSERT … ON CONFLICT(key) DO UPDATE`.
- **Written**: only on a manual tap of the theme button, with the scheme the app switches to.
- **Read**: once at app start, right after the database opens and with the language, while the launch screen is still up (R2).
- **Later**: 006 moves display preferences to SharedPreferences / UserDefaults (spec Assumptions).
- **Validation**: any other stored value is treated as absent (follow the phone). A read error is treated as absent, silently (FR-008).
- **Lifetime**: kept until the app's data is cleared or the app is uninstalled. Not backed up or synchronized by this feature.

### States

```text
            first launch
                 │
                 ▼
     ┌───────────────────────┐   phone setting changes
     │ follows the phone     │◄──────────────┐
     │ (no stored value)     │───────────────┘ (app follows it, FR-007)
     └──────────┬────────────┘
                │ tap theme button
                ▼
     ┌───────────────────────┐   tap theme button
     │ fixed: light or dark  │◄──────────────┐
     │ (stored value)        │───────────────┘ (switches and saves the other one)
     └───────────────────────┘
       phone setting changes: ignored
```

There is no transition back to "follows the phone" (spec Assumptions).

## Active scheme (runtime only)

`"light"` or `"dark"`: the scheme the screens are drawn with. Derived as: stored choice if present, otherwise the phone's setting. Held by React Native `Appearance` and NativeWind's color-scheme observable; not copied into Redux (R5).

## Color role (style configuration)

A named color with one value per scheme, defined once in `src/theme/tokens.css` and exposed as a Tailwind color. Components refer to roles only. The full list with values is in [contracts/color-roles.md](./contracts/color-roles.md).

**Rules**:
- Every role has a light and a dark value (checked by test, R8).
- Light values equal the values shipped before this feature (FR-001, R6).
- No color value appears in `.ts` / `.tsx` files (SC-005).
