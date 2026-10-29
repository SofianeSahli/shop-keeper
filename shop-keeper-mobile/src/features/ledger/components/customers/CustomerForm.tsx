import { useEffect, useRef, useState } from 'react';
import { ScrollViewInstance, View } from 'react-native';
import { useDispatch } from 'react-redux';
import { router } from 'expo-router';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { useAsyncAction } from '@/features/feedback/useAsyncAction';
import { BusyButton, InfoBanner, ScreenScroll, useFieldErrors } from '@/components/ui';
import { clearFormDirty, refreshCompleted, setFormDirty } from '../../store/ledgerSlice';
import { createCustomer, removeCustomer, updateCustomer } from '../../services/customerService';
import { Customer } from '../../types/customer';
import { LedgerField } from '../forms/LedgerField';
import { ledgerFieldErrorFor } from '../forms/ledgerFieldError';
import { ConfirmAction } from '../feedback/ConfirmAction';

type CustomerFormProps = {
  customer?: Customer | null;
  embedded?: boolean;
  onSaved?: (customer: Customer) => void;
};

export function CustomerForm({ customer = null, embedded = false, onSaved }: CustomerFormProps) {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const [displayLabel, setDisplayLabel] = useState(customer?.displayLabel ?? '');
  const [realName, setRealName] = useState(customer?.realName ?? '');
  const [phoneNumber, setPhoneNumber] = useState(customer?.phoneNumber ?? '');
  const [sheetError, setSheetError] = useState('');
  const textAlign = language === 'ar' ? 'right' : 'left';
  const scrollRef = useRef<ScrollViewInstance>(null);
  const fieldErrors = useFieldErrors({
    fieldOrder: ['displayLabel'] as const,
    validate: () => (displayLabel.trim() ? {} : { displayLabel: t.fieldRequired }),
    fieldErrorFor: ledgerFieldErrorFor({ requiredField: 'displayLabel' }, t),
    scrollRef,
  });

  useEffect(() => {
    if (embedded) return;
    const hasChanges =
      displayLabel !== (customer?.displayLabel ?? '') ||
      realName !== (customer?.realName ?? '') ||
      phoneNumber !== (customer?.phoneNumber ?? '');
    dispatch(setFormDirty({ section: 'customers', isDirty: hasChanges }));
  }, [customer, dispatch, displayLabel, embedded, phoneNumber, realName]);

  useEffect(() => {
    if (embedded) return;
    return () => {
      dispatch(clearFormDirty());
    };
  }, [dispatch, embedded]);

  function leaveForm(): void {
    dispatch(refreshCompleted());
    if (embedded) return;
    dispatch(clearFormDirty());
    router.replace('/ledger/customers');
  }

  const saveAction = useAsyncAction(() => ({
    checkFields: fieldErrors.checkFields,
    showFieldError: fieldErrors.showReportedError,
    work: () => {
      const input = { displayLabel, realName: realName || null, phoneNumber: phoneNumber || null };
      return customer ? updateCustomer(customer.id, input) : createCustomer(input);
    },
    loaderLabel: t.savingCustomer,
    loaderDetail: displayLabel.trim() || null,
    successToast: { title: t.customerSavedToast, detail: displayLabel.trim() },
    onSuccess: (saved: Customer) => {
      setSheetError('');
      onSaved?.(saved);
      leaveForm();
    },
    onFailure: (cause: unknown) => {
      if (embedded) setSheetError(getLedgerErrorMessage(cause, t, t.saveFailed));
    },
    failureThing: t.thingCustomer,
    keepValuesMessage: t.valuesKept,
  }));

  const removeAction = useAsyncAction(() => ({
    work: () => removeCustomer(customer?.id ?? ''),
    loaderLabel: t.removingCustomer,
    loaderDetail: displayLabel.trim() || null,
    successToast: { title: t.customerRemovedToast, detail: displayLabel.trim() },
    onSuccess: leaveForm,
    failureThing: t.thingCustomer,
    keepValuesMessage: t.nothingChanged,
  }));

  const fields = (
    <>
      <LedgerField
        ref={fieldErrors.register('displayLabel')}
        label={t.displayLabel}
        value={displayLabel}
        error={fieldErrors.errorOf('displayLabel')}
        onChangeText={(value) => {
          setDisplayLabel(value);
          fieldErrors.fieldChanged('displayLabel');
        }}
        autoCapitalize="words"
        textAlign={textAlign}
      />
      <LedgerField
        label={t.realName}
        value={realName}
        onChangeText={setRealName}
        autoCapitalize="words"
        textAlign={textAlign}
      />
      <LedgerField
        label={t.phoneNumber}
        value={phoneNumber}
        onChangeText={setPhoneNumber}
        keyboardType="phone-pad"
        textAlign="left"
      />
    </>
  );

  const saveButton = (
    <BusyButton
      label={t.saveCustomer}
      busy={saveAction.busy}
      disabled={removeAction.busy}
      onPress={() => void saveAction.run()}
    />
  );

  if (embedded) {
    return (
      <View className="gap-3">
        {sheetError ? <InfoBanner tone="error" title={sheetError} /> : null}
        {fields}
        {saveButton}
      </View>
    );
  }

  return (
    <ScreenScroll footer={saveButton} footerWithSafeArea scrollRef={scrollRef}>
      {fields}
      {customer?.isActive ? (
        <ConfirmAction
          buttonLabel={t.removeCustomer}
          title={t.confirmRemoval}
          message={t.removeCustomerMessage}
          confirmLabel={t.remove}
          cancelLabel={t.cancel}
          busy={removeAction.busy}
          busyLabel={t.removingCustomer}
          onConfirm={() => void removeAction.run()}
        />
      ) : null}
    </ScreenScroll>
  );
}
