import { useEffect, useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMillimeAmount, formatMillimeInput } from '@/lib/money';
import { Icon, InfoBanner, ScreenScroll } from '@/components/ui';
import { createProduct, getProduct, listProducts, removeProduct, updateProduct } from '../services/productService';
import { LedgerField } from '../components/forms/LedgerField';
import { MillimeInput, parseMillimes } from '../components/forms/MillimeInput';
import { ConfirmAction } from '../components/feedback/ConfirmAction';
import { BarcodeScanner } from '../components/ticket/BarcodeScanner';
import { clearFormDirty, refreshCompleted, setFormDirty } from '../store/ledgerSlice';
import { useDirtyFormGuard } from '../hooks/useDirtyFormGuard';
import { Product } from '../types/product';

export function ProductFormScreen() {
  const { id, barcode } = useLocalSearchParams<{ id?: string; barcode?: string }>();
  return <ProductForm key={id ?? `new-${barcode ?? ''}`} id={id} initialBarcode={barcode} />;
}

function findDuplicate(code: string, ownId: string | undefined): Product | null {
  const trimmed = code.trim();
  if (!trimmed) return null;
  return listProducts().find((product) => product.id !== ownId && product.isActive && product.barcode?.trim() === trimmed) ?? null;
}

function ProductForm({ id, initialBarcode }: { id?: string; initialBarcode?: string }) {
  const { t, language } = useLedgerTranslations();
  useDirtyFormGuard();
  const dispatch = useDispatch();
  const [initial] = useState(() => {
    try {
      const product = id ? getProduct(id) : null;
      return { product, error: id && !product ? t.loadFailed : '' };
    } catch (cause) {
      return { product: null, error: getLedgerErrorMessage(cause, t, t.loadFailed) };
    }
  });
  const startBarcode = initial.product?.barcode ?? initialBarcode ?? '';
  const startPrice = initial.product ? formatMillimeInput(initial.product.sellingPriceMillimes) : '';
  const [name, setName] = useState(initial.product?.name ?? '');
  const [barcode, setBarcode] = useState(startBarcode);
  const [duplicate, setDuplicate] = useState<Product | null>(() => findDuplicate(startBarcode, id));
  const [price, setPrice] = useState(startPrice);
  const [scannerArmed, setScannerArmed] = useState(!startBarcode);
  const [error, setError] = useState(initial.error);

  useEffect(() => {
    const hasChanges = name !== (initial.product?.name ?? '') || barcode !== startBarcode || price !== startPrice;
    dispatch(setFormDirty({ section: 'products', isDirty: hasChanges && !initial.error }));
  }, [barcode, dispatch, initial.error, initial.product, name, price, startBarcode, startPrice]);

  useEffect(() => {
    return () => {
      dispatch(clearFormDirty());
    };
  }, [dispatch]);

  function updateBarcodeValue(nextValue: string): void {
    setBarcode(nextValue);
    try {
      setDuplicate(findDuplicate(nextValue, id));
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.loadFailed));
    }
  }

  function finish(): void {
    dispatch(clearFormDirty());
    dispatch(refreshCompleted());
    router.replace('/ledger/products');
  }

  function save(): void {
    if (duplicate) return;
    try {
      const input = { name, barcode: barcode.trim() || null, sellingPriceMillimes: parseMillimes(price) };
      if (id) updateProduct(id, input);
      else createProduct(input);
      finish();
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  function remove(): void {
    if (!id) return;
    try {
      removeProduct(id);
      finish();
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  const isNewCode = !duplicate && barcode.trim() !== '' && barcode.trim() !== (initial.product?.barcode ?? '');
  const footer = (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: Boolean(duplicate) }}
      disabled={Boolean(duplicate)}
      className={`ui-button-primary min-h-button-lg ${duplicate ? 'ui-button-disabled' : ''}`}
      onPress={save}
    >
      <Text className="type-button text-surface">{t.saveProduct}</Text>
    </Pressable>
  );

  return (
    <ScreenScroll footer={footer} footerWithSafeArea>
      {error ? <InfoBanner tone="error" title={error} /> : null}

      <BarcodeScanner
        mode="inline"
        armed={scannerArmed}
        scanLabel={t.scanBarcode}
        permissionLabel={t.cameraAccess}
        closeLabel={t.close}
        onScanned={(value) => {
          setScannerArmed(false);
          updateBarcodeValue(value);
        }}
      />

      <LedgerField
        label={t.barcodeOptional}
        value={barcode}
        onChangeText={updateBarcodeValue}
        autoCapitalize="none"
        textAlign="left"
        hint={duplicate || isNewCode ? undefined : t.noCameraHint}
        accessory={
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={t.rescan}
            className="h-[52px] w-[52px] items-center justify-center rounded-control border border-line-strong bg-surface active:bg-muted"
            onPress={() => setScannerArmed(true)}
          >
            <Icon name="scan-outline" className="text-primary" />
          </Pressable>
        }
      />
      {duplicate ? (
        <InfoBanner
          tone="warning"
          title={t.codeExists}
          text={`${duplicate.name} · ${formatMillimeAmount(duplicate.sellingPriceMillimes, language)}`}
          actionLabel={t.openThisProduct}
          onAction={() => router.push({ pathname: '/ledger/product-form', params: { id: duplicate.id } })}
        />
      ) : null}
      {isNewCode ? (
        <View accessibilityLiveRegion="polite" className="flex-row items-center gap-2">
          <Icon name="checkmark-circle" size={18} className="text-paid" />
          <Text className="font-plex-semibold text-label text-paid">{t.newCodeReady}</Text>
        </View>
      ) : null}

      <LedgerField
        label={t.productName}
        value={name}
        onChangeText={setName}
        autoCapitalize="sentences"
        textAlign={language === 'ar' ? 'right' : 'left'}
      />
      <MillimeInput label={t.salePrice} value={price} onChangeText={setPrice} suffix={t.currencySuffix} hint={t.millimesHint} />

      {id && initial.product?.isActive ? (
        <ConfirmAction
          buttonLabel={t.removeProduct}
          title={t.confirmRemoval}
          message={t.removeProductMessage}
          confirmLabel={t.remove}
          cancelLabel={t.cancel}
          onConfirm={remove}
        />
      ) : null}
    </ScreenScroll>
  );
}
