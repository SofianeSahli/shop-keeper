# Contract: theme preference and theme switch (005)

## Storage: `src/theme/themeStorage.ts`

Same pattern as `src/i18n/languageStorage.ts`, through `accessLedger` (so it goes through `runQueued`).

```ts
type ThemeChoice = 'light' | 'dark';

getSavedThemeChoice(): Promise<ThemeChoice | null>   // null = follow the phone; any other stored value reads as null
saveThemeChoice(choice: ThemeChoice): Promise<void>  // upsert of app_settings('theme'); rejects an unsupported value
```

## Startup: `src/app/_layout.tsx`

- At module load: `SplashScreen.preventAutoHideAsync()` (`expo-splash-screen`).
- Startup chain: `openLedgerDatabase()` → `getSavedLanguage()` and `applySavedThemeChoice()` (`src/theme/applySavedThemeChoice.ts`: `getSavedThemeChoice()` then `colorScheme.set(theme ?? 'system')`) → `languageChanged(...)` → ready → `SplashScreen.hideAsync()` once fonts and the database have settled.
- `<StatusBar style="auto" />` (`expo-status-bar`) is rendered once, so the status bar icons follow the app scheme.
- A failed read of the theme alone counts as `null` (follow the phone), never as a startup failure.
- Database failure: `SplashScreen.hideAsync()` too; the startup error screen shows in the phone's theme.

## Hook: `useThemeChoice()` (`src/theme/useThemeChoice.ts`)

```ts
useThemeChoice(): {
  scheme: 'light' | 'dark';   // the scheme screens are drawn with now
  toggle(): void;             // switch to the other scheme, then saveThemeChoice(it)
}
```

- Reads the scheme with NativeWind's `useColorScheme()`.
- `toggle()` applies the change first, then calls `saveThemeChoice(next)` without awaiting it in the UI; a failed save is ignored (the switch still applies for the session).

## Component: `ThemeToggle` (`src/navigation/ThemeToggle.tsx`)

| Scheme | Icon | Accessibility label | Classes |
|---|---|---|---|
| light | `moon-outline` | `t.switchToDark` | `ui-icon-button rounded-full border border-line-strong bg-surface-2` (like the language button), icon `text-theme-icon` |
| dark | `sunny` | `t.switchToLight` | same |

- `accessibilityRole="button"`, at least 44 dp, placed right after `LanguageMenu` in `TopBar` (FR-004).
- One tap calls `toggle()`. No animation (R9).

## Strings

| Key | fr | ar | en |
|---|---|---|---|
| `switchToDark` | Passer en mode sombre | التبديل إلى الوضع الداكن | Switch to dark mode |
| `switchToLight` | Passer en mode clair | التبديل إلى الوضع الفاتح | Switch to light mode |

## Configuration

- `app.json`: `"userInterfaceStyle": "automatic"` (was `"light"`).
- `tailwind.config.js`: colors become `rgb(var(--color-<role>) / <alpha-value>)`; `darkMode: 'class'` kept.
- `src/global.css`: imports `./theme/tokens.css` before the component styles.
