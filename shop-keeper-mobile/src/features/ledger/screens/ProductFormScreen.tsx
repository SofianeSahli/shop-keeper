import { useState } from 'react';
import { Pressable, Text } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMillimeInput } from '@/lib/money';
import { createProduct, getProduct, updateProduct } from '../services/productService';
import { LedgerPage } from '../components/LedgerPage';
import { LedgerField } from '../components/forms/LedgerField';
import { MillimeInput, parseMillimes } from '../components/forms/MillimeInput';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { refreshCompleted } from '../store/ledgerSlice';

export function ProductFormScreen() {
  const { id, barcode } = useLocalSearchParams<{ id?: string; barcode?: string }>();
  return <ProductForm key={id ?? `new-${barcode ?? ''}`} id={id} initialBarcode={barcode} />;
}

function ProductForm({ id, initialBarcode }: { id?: string; initialBarcode?: string }) {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const [initial] = useState(() => {
    try {
      const product = id ? getProduct(id) : null;
      return { product, error: id && !product ? t.loadFailed : '' };
    } catch (cause) {
      return { product: null, error: getLedgerErrorMessage(cause, t, t.loadFailed) };
    }
  });
  const [name, setName] = useState(initial.product?.name ?? '');
  const [barcode, setBarcode] = useState(initial.product?.barcode ?? initialBarcode ?? '');
  const [price, setPrice] = useState(
    initial.product ? formatMillimeInput(initial.product.sellingPriceMillimes) : '',
  );
  const [error, setError] = useState(initial.error);

  function save(): void {
    try {
      const input = { name, barcode: barcode.trim() || null, sellingPriceMillimes: parseMillimes(price) };
      if (id) updateProduct(id, input);
      else createProduct(input);
      dispatch(refreshCompleted());
      router.replace('/ledger/products');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  return (
    <LedgerPage title={id ? t.editProduct : t.newProduct}>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      <LedgerField
        label={t.productName}
        value={name}
        onChangeText={setName}
        autoCapitalize="sentences"
        textAlign={language === 'ar' ? 'right' : 'left'}
      />
      <LedgerField
        label={t.barcode}
        value={barcode}
        onChangeText={setBarcode}
        autoCapitalize="none"
        textAlign="left"
      />
      <MillimeInput label={t.price} value={price} onChangeText={setPrice} />
      <Pressable accessibilityRole="button" className="ledger-primary-button" onPress={save}>
        <Text className="font-semibold text-white">{t.save}</Text>
      </Pressable>
    </LedgerPage>
  );
}
