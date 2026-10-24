const LETTER = /\p{L}/u;

/** Up to two initials from a customer label: first letter of the first two words. */
export function getInitials(label: string): string {
  const letters = label
    .trim()
    .split(/\s+/)
    .map((word) => [...word].find((char) => LETTER.test(char)))
    .filter((letter): letter is string => Boolean(letter));
  if (letters.length === 0) return '?';
  return letters.slice(0, 2).join('').toLocaleUpperCase();
}
