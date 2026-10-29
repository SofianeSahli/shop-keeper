import type { ReportedFieldError } from '@/components/ui';
import { getTranslations } from '@/i18n';
import { getFieldValidationKey, LedgerErrorKey } from '@/i18n/errorMessages';

type Translations = ReturnType<typeof getTranslations>;

export function ledgerFieldErrorFor<Field extends string>(
  fieldForKey: Partial<Record<LedgerErrorKey, Field>>,
  t: Translations,
) {
  return (cause: unknown): ReportedFieldError<Field> | null => {
    const key = getFieldValidationKey(cause);
    const field = key ? fieldForKey[key] : undefined;
    if (!key || !field) return null;
    return { field, message: key === 'requiredField' ? t.fieldRequired : t[key] };
  };
}
