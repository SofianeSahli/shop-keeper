import { useCallback } from 'react';
import { useColorScheme } from 'nativewind';
import { saveThemeChoice, type ThemeChoice } from './themeStorage';

function oppositeOf(scheme: ThemeChoice): ThemeChoice {
  return scheme === 'dark' ? 'light' : 'dark';
}

function keepSwitchWhenSaveFails(): void {}

export function useThemeChoice(): { scheme: ThemeChoice; toggle: () => void } {
  const { colorScheme, setColorScheme } = useColorScheme();
  const scheme: ThemeChoice = colorScheme === 'dark' ? 'dark' : 'light';

  const toggle = useCallback(() => {
    const next = oppositeOf(scheme);
    setColorScheme(next);
    saveThemeChoice(next).catch(keepSwitchWhenSaveFails);
  }, [scheme, setColorScheme]);

  return { scheme, toggle };
}
