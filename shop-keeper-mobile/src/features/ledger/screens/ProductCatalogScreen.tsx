import { useMemo, useState } from 'react';
import { Link } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useDispatch, useSelector } from 'react-redux';
import { RootState } from '@/store';
import { refreshCompleted } from '../store/ledgerSlice';
import { ConfirmAction } from '../components/feedback/ConfirmAction';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { LedgerPage } from '../components/LedgerPage';
import { LedgerField } from '../components/forms/LedgerField';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { Product } from '../types/product';
import { listProducts, removeProduct } from '../services/productService';
import { formatMillimeAmount } from '@/lib/money';

export function ProductCatalogScreen() {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const [search, setSearch] = useState('');
  const [actionError, setActionError] = useState('');
  const result = useMemo(() => {
    try {
      return { products: listProducts(search), error: '', version: refreshVersion };
    } catch (cause) {
      return {
        products: [] as Product[],
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
        version: refreshVersion,
      };
    }
  }, [refreshVersion, search, t]);
  const error = actionError || result.error;

  return (
    <LedgerPage key={result.version} title={t.products}>
      <Link href="/ledger/product-form" asChild>
        <Pressable accessibilityRole="button" className="ledger-primary-button">
          <Text className="font-semibold text-white">{t.newProduct}</Text>
        </Pressable>
      </Link>
      <LedgerField label={t.search} value={search} onChangeText={setSearch} />
      {error ? <FeedbackState kind="error" message={error} /> : null}
      {result.products.length === 0 && !error ? <FeedbackState kind="empty" message={t.emptyProducts} /> : null}
      {result.products.map((product) => (
        <View key={product.id} className="ledger-card">
          <View className="flex-row items-start justify-between gap-3">
            <View className="flex-1 gap-1">
              <Text className="text-lg font-semibold text-slate-900">{product.name}</Text>
              {product.barcode ? <Text className="text-sm text-slate-600">{product.barcode}</Text> : null}
              <Text className="font-semibold text-blue-800">{formatMillimeAmount(product.sellingPriceMillimes, language)}</Text>
            </View>
            <Link href={{ pathname: '/ledger/product-form', params: { id: product.id } }} asChild>
              <Pressable accessibilityRole="button" className="ledger-touch-target rounded-lg bg-slate-100 px-3 py-2">
                <Text className="font-medium text-slate-800">{t.editProduct}</Text>
              </Pressable>
            </Link>
          </View>
          <ConfirmAction
            buttonLabel={t.remove}
            title={t.confirmRemoval}
            message={t.removeProductMessage}
            confirmLabel={t.confirm}
            cancelLabel={t.cancel}
            onConfirm={() => {
              try {
                removeProduct(product.id);
                dispatch(refreshCompleted());
                setActionError('');
              } catch (cause) {
                setActionError(getLedgerErrorMessage(cause, t, t.saveFailed));
              }
            }}
          />
        </View>
      ))}
    </LedgerPage>
  );
}
