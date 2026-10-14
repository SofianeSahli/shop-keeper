import { useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMillimeInput } from '@/lib/money';
import { createProduct, getProduct, listProducts, updateProduct } from '../services/productService';
import { LedgerPage } from '../components/LedgerPage';
import { LedgerField } from '../components/forms/LedgerField';
import { MillimeInput, parseMillimes } from '../components/forms/MillimeInput';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { BarcodeScanner } from '../components/ticket/BarcodeScanner';
import { clearFormDirty, refreshCompleted, setFormDirty } from '../store/ledgerSlice';

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
  const [duplicateProductId, setDuplicateProductId] = useState<string | null>(null);
  const [price, setPrice] = useState(
    initial.product ? formatMillimeInput(initial.product.sellingPriceMillimes) : '',
  );
  const [error, setError] = useState(initial.error);

  useEffect(() => {
    const hasChanges =
      name !== (initial.product?.name ?? '') ||
      barcode !== (initial.product?.barcode ?? initialBarcode ?? '') ||
      price !== (initial.product ? formatMillimeInput(initial.product.sellingPriceMillimes) : '');
    dispatch(setFormDirty({ section: 'products', isDirty: hasChanges && !error }));
  }, [barcode, dispatch, error, initial.product, initialBarcode, name, price]);

  useEffect(() => {
    return () => {
      dispatch(clearFormDirty());
    };
  }, [dispatch]);

  function updateBarcodeValue(nextValue: string): void {
    const trimmed = nextValue.trim();
    setBarcode(nextValue);
    if (!trimmed) {
      setDuplicateProductId(null);
      return;
    }

    const match = listProducts().find(
      (product) => product.id !== id && product.isActive && product.barcode?.trim() === trimmed,
    );
    setDuplicateProductId(match?.id ?? null);
    if (match) {
      setError(t.duplicateBarcode);
    } else if (error) {
      setError('');
    }
  }

  function save(): void {
    try {
      const input = { name, barcode: barcode.trim() || null, sellingPriceMillimes: parseMillimes(price) };
      if (id) updateProduct(id, input);
      else createProduct(input);
      dispatch(clearFormDirty());
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
      <BarcodeScanner
        scanLabel={t.scanBarcode}
        permissionLabel={t.cameraAccess}
        closeLabel={t.close}
        onScanned={(value) => {
          updateBarcodeValue(value);
        }}
      />
      <LedgerField
        label={t.barcode}
        value={barcode}
        onChangeText={updateBarcodeValue}
        autoCapitalize="none"
        textAlign="left"
      />
      {duplicateProductId ? (
        <View className="gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3">
          <Text className="text-sm font-semibold text-amber-900">{t.duplicateBarcode}</Text>
          <Pressable
            accessibilityRole="button"
            className="ledger-touch-target rounded-lg bg-amber-700 px-3 py-2"
            onPress={() => router.push({ pathname: '/ledger/product-form', params: { id: duplicateProductId } })}
          >
            <Text className="text-center font-semibold text-white">{t.openProduct}</Text>
          </Pressable>
        </View>
      ) : null}
      <MillimeInput label={t.price} value={price} onChangeText={setPrice} />
      <Pressable accessibilityRole="button" className="ledger-primary-button" onPress={save}>
        <Text className="font-semibold text-white">{t.save}</Text>
      </Pressable>
    </LedgerPage>
  );
}
