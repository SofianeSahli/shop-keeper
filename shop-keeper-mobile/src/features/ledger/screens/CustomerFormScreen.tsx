import { useState } from 'react';
import { Pressable, Text } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { createCustomer, getCustomer, updateCustomer } from '../services/customerService';
import { LedgerPage } from '../components/LedgerPage';
import { LedgerField } from '../components/forms/LedgerField';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { refreshCompleted } from '../store/ledgerSlice';

export function CustomerFormScreen() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  return <CustomerForm key={id ?? 'new'} id={id} />;
}

function CustomerForm({ id }: { id?: string }) {
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

  function save(): void {
    try {
      const input = { displayLabel, realName: realName || null, phoneNumber: phoneNumber || null };
      if (id) updateCustomer(id, input);
      else createCustomer(input);
      dispatch(refreshCompleted());
      router.replace('/ledger/customers');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  return (
    <LedgerPage title={id ? t.editCustomer : t.newCustomer}>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      <LedgerField
        label={t.displayLabel}
        value={displayLabel}
        onChangeText={setDisplayLabel}
        autoCapitalize="words"
        textAlign={language === 'ar' ? 'right' : 'left'}
      />
      <LedgerField
        label={t.realName}
        value={realName}
        onChangeText={setRealName}
        autoCapitalize="words"
        textAlign={language === 'ar' ? 'right' : 'left'}
      />
      <LedgerField
        label={t.phoneNumber}
        value={phoneNumber}
        onChangeText={setPhoneNumber}
        keyboardType="phone-pad"
        textAlign="left"
      />
      <Pressable accessibilityRole="button" className="ledger-primary-button" onPress={save}>
        <Text className="font-semibold text-white">{t.save}</Text>
      </Pressable>
    </LedgerPage>
  );
}
