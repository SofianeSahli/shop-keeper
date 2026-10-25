import { useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { useDispatch } from 'react-redux';
import { router } from 'expo-router';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { InfoBanner, ScreenScroll } from '@/components/ui';
import { clearFormDirty, refreshCompleted, setFormDirty } from '../../store/ledgerSlice';
import { createCustomer, getCustomer, removeCustomer, updateCustomer } from '../../services/customerService';
import { Customer } from '../../types/customer';
import { LedgerField } from '../forms/LedgerField';
import { ConfirmAction } from '../feedback/ConfirmAction';

type CustomerFormProps = {
  id?: string;
  /** Rendered inside another form (ticket form sheet): no screen chrome, no dirty tracking. */
  embedded?: boolean;
  onSaved?: (customer: Customer) => void;
};

export function CustomerForm({ id, embedded = false, onSaved }: CustomerFormProps) {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const [initial] = useState(() => {
    try {
      const customer = id ? getCustomer(id) : null;
      return { customer, error: id && !customer ? t.loadFailed : '' };
    } catch (cause) {
      return { customer: null, error: getLedgerErrorMessage(cause, t, t.loadFailed) };
    }
  });
  const [displayLabel, setDisplayLabel] = useState(initial.customer?.displayLabel ?? '');
  const [realName, setRealName] = useState(initial.customer?.realName ?? '');
  const [phoneNumber, setPhoneNumber] = useState(initial.customer?.phoneNumber ?? '');
  const [error, setError] = useState(initial.error);
  const textAlign = language === 'ar' ? 'right' : 'left';

  useEffect(() => {
    if (embedded) return;
    const hasChanges =
      displayLabel !== (initial.customer?.displayLabel ?? '') ||
      realName !== (initial.customer?.realName ?? '') ||
      phoneNumber !== (initial.customer?.phoneNumber ?? '');
    dispatch(setFormDirty({ section: 'customers', isDirty: hasChanges && !error }));
  }, [dispatch, displayLabel, embedded, error, initial.customer, phoneNumber, realName]);

  useEffect(() => {
    if (embedded) return;
    return () => {
      dispatch(clearFormDirty());
    };
  }, [dispatch, embedded]);

  function save(): void {
    try {
      const input = { displayLabel, realName: realName || null, phoneNumber: phoneNumber || null };
      if (id) {
        updateCustomer(id, input);
      } else {
        // Create first: `onSaved?.(createCustomer(…))` would skip the save when no callback is given.
        const created = createCustomer(input);
        onSaved?.(created);
      }
      dispatch(refreshCompleted());
      if (!embedded) {
        dispatch(clearFormDirty());
        router.replace('/ledger/customers');
      }
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  function remove(): void {
    if (!id) return;
    try {
      removeCustomer(id);
      dispatch(clearFormDirty());
      dispatch(refreshCompleted());
      router.replace('/ledger/customers');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  const fields = (
    <>
      {error ? <InfoBanner tone="error" title={error} /> : null}
      <LedgerField
        label={t.displayLabel}
        value={displayLabel}
        onChangeText={setDisplayLabel}
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
    <Pressable accessibilityRole="button" className="ui-button-primary" onPress={save}>
      <Text className="type-button text-surface">{t.saveCustomer}</Text>
    </Pressable>
  );

  if (embedded) {
    return (
      <View className="gap-3">
        {fields}
        {saveButton}
      </View>
    );
  }

  return (
    <ScreenScroll footer={saveButton} footerWithSafeArea>
      {fields}
      {id && initial.customer?.isActive ? (
        <ConfirmAction
          buttonLabel={t.removeCustomer}
          title={t.confirmRemoval}
          message={t.removeCustomerMessage}
          confirmLabel={t.remove}
          cancelLabel={t.cancel}
          onConfirm={remove}
        />
      ) : null}
    </ScreenScroll>
  );
}
