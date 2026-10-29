export type FieldErrors<Field extends string> = Partial<Record<Field, string>>;

export interface ReportedFieldError<Field extends string> {
  field: Field;
  message: string;
}

export function firstFieldInError<Field extends string>(
  fieldOrder: readonly Field[],
  errors: FieldErrors<Field>,
): Field | null {
  return fieldOrder.find((field) => errors[field]) ?? null;
}

export function withoutField<Field extends string>(errors: FieldErrors<Field>, field: Field): FieldErrors<Field> {
  const remaining = { ...errors };
  delete remaining[field];
  return remaining;
}

export function displayedFieldError<Field extends string>(
  field: Field,
  reported: FieldErrors<Field>,
  checkedFields: ReadonlySet<Field>,
  liveErrors: FieldErrors<Field>,
): string | undefined {
  return reported[field] ?? (checkedFields.has(field) ? liveErrors[field] : undefined);
}
