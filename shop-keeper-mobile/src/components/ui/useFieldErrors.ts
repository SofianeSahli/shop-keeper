import { RefObject, useRef, useState } from 'react';
import { AccessibilityInfo, HostInstance, ScrollViewInstance } from 'react-native';
import { displayedFieldError, FieldErrors, firstFieldInError, ReportedFieldError, withoutField } from './logic/fieldErrors';

const SCROLL_MARGIN_DP = 24;

interface FieldErrorsOptions<Field extends string> {
  fieldOrder: readonly Field[];
  validate: () => FieldErrors<Field>;
  fieldErrorFor?: (cause: unknown) => ReportedFieldError<Field> | null;
  scrollRef?: RefObject<ScrollViewInstance | null>;
}

export function useFieldErrors<Field extends string>({
  fieldOrder,
  validate,
  fieldErrorFor,
  scrollRef,
}: FieldErrorsOptions<Field>) {
  const [checkedFields, setCheckedFields] = useState<ReadonlySet<Field>>(() => new Set());
  const [reportedErrors, setReportedErrors] = useState<FieldErrors<Field>>({});
  const elements = useRef(new Map<Field, HostInstance>());
  const liveErrors = validate();

  function errorOf(field: Field): string | undefined {
    return displayedFieldError(field, reportedErrors, checkedFields, liveErrors);
  }

  function scrollTo(element: HostInstance): void {
    const scroll = scrollRef?.current;
    const content = scroll?.getInnerViewRef();
    if (!scroll || !content) return;
    element.measureLayout(
      content,
      (_x, y) => scroll.scrollTo({ y: Math.max(0, y - SCROLL_MARGIN_DP), animated: true }),
      () => undefined,
    );
  }

  function bringIntoView(field: Field, message: string): void {
    const element = elements.current.get(field);
    if (element) {
      scrollTo(element);
      element.focus();
      AccessibilityInfo.sendAccessibilityEvent(element, 'focus');
    }
    AccessibilityInfo.announceForAccessibility(message);
  }

  function checkFields(): boolean {
    const errors = validate();
    setCheckedFields(new Set(fieldOrder.filter((field) => errors[field])));
    const first = firstFieldInError(fieldOrder, errors);
    if (!first) return true;
    bringIntoView(first, errors[first] ?? '');
    return false;
  }

  function showReportedError(cause: unknown): boolean {
    const reported = fieldErrorFor?.(cause);
    if (!reported) return false;
    setReportedErrors((current) => ({ ...current, [reported.field]: reported.message }));
    bringIntoView(reported.field, reported.message);
    return true;
  }

  function fieldChanged(field: Field): void {
    if (reportedErrors[field]) setReportedErrors((current) => withoutField(current, field));
  }

  function reset(): void {
    setCheckedFields(new Set());
    setReportedErrors({});
  }

  function register(field: Field) {
    return (element: HostInstance | null) => {
      if (element) elements.current.set(field, element);
      else elements.current.delete(field);
    };
  }

  return { errorOf, checkFields, showReportedError, fieldChanged, reset, register };
}
