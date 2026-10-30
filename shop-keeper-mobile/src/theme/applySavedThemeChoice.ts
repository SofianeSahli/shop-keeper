import { colorScheme } from 'nativewind';
import { getSavedThemeChoice } from './themeStorage';

function followThePhone(): null {
  return null;
}

export async function applySavedThemeChoice(): Promise<void> {
  const savedChoice = await getSavedThemeChoice().catch(followThePhone);
  colorScheme.set(savedChoice ?? 'system');
}
