export function normalizeLabel(value: string | null | undefined): string {
  return (value ?? '').trim();
}

export function isNonEmptyText(value: string | null | undefined): boolean {
  return normalizeLabel(value).length > 0;
}

export function isPositiveInteger(value: number | null | undefined): boolean {
  return typeof value === 'number' && Number.isInteger(value) && value > 0;
}

export function validateRequiredText(value: string | null | undefined, fieldName: string): string {
  const normalized = normalizeLabel(value);

  if (!normalized) {
    throw new Error(`${fieldName} is required.`);
  }

  return normalized;
}
