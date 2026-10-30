import { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, ScrollViewInstance, Text, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useAsyncAction } from '@/features/feedback/useAsyncAction';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import { formatMillimeAmount, formatMillimeInput } from '@/lib/money';
import { BusyButton, Icon, InfoBanner, ScreenScroll, SkeletonRows, useFieldErrors } from '@/components/ui';
import { useTopBarOptions } from '@/navigation/TopBarContext';
import { createProduct, getProduct, listProducts, removeProduct, updateProduct } from '../services/productService';
import { LedgerField } from '../components/forms/LedgerField';
import { isValidMillimeText, MillimeInput, parseMillimes } from '../components/forms/MillimeInput';
import { ledgerFieldErrorFor } from '../components/forms/ledgerFieldError';
import { ConfirmAction } from '../components/feedback/ConfirmAction';
import { BarcodeScanner } from '../components/ticket/BarcodeScanner';
import { clearFormDirty, refreshCompleted, setFormDirty } from '../store/ledgerSlice';
import { useDirtyFormGuard } from '../hooks/useDirtyFormGuard';
import { Product } from '../types/product';

async function loadProductForm(id: string | undefined) {
  const product = id ? await getProduct(id) : null;
  const catalog = await listProducts();
  return { product, catalog };
}

const PRODUCT_FIELDS = ['barcode', 'name', 'price'] as const;

function findDuplicate(catalog: readonly Product[], code: string, ownId: string | undefined): Product | null {
  const trimmed = code.trim();
  if (!trimmed) return null;
  return catalog.find((product) => product.id !== ownId && product.isActive && product.barcode?.trim() === trimmed) ?? null;
}

export function ProductFormScreen() {
  const { id, barcode } = useLocalSearchParams<{ id?: string; barcode?: string }>();
  const { t } = useLedgerTranslations();
  useDirtyFormGuard();
  useTopBarOptions({ title: id ? t.editProduct : t.newProduct });
  const loadForm = useCallback(() => loadProductForm(id), [id]);
  const query = useLedgerQuery(loadForm, [id]);

  if (query.status === 'loading') {
    return (
      <ScreenScroll>
        <SkeletonRows variant="tile" count={3} />
      </ScreenScroll>
    );
  }
  if (!query.data || (id && !query.data.product)) {
    return (
      <ScreenScroll>
        <InfoBanner
          tone="error"
          title={query.status === 'error' ? query.error : t.recordUnavailable}
          actionLabel={query.status === 'error' ? t.retry : undefined}
          onAction={query.retry}
        />
      </ScreenScroll>
    );
  }
  return (
    <ProductForm
      key={id ?? `new-${barcode ?? ''}`}
      id={id}
      initialBarcode={barcode}
      product={query.data.product}
      catalog={query.data.catalog}
    />
  );
}

interface ProductFormProps {
  id?: string;
  initialBarcode?: string;
  product: Product | null;
  catalog: readonly Product[];
}

function ProductForm({ id, initialBarcode, product, catalog }: ProductFormProps) {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const startBarcode = product?.barcode ?? initialBarcode ?? '';
  const startPrice = product ? formatMillimeInput(product.sellingPriceMillimes) : '';
  const [name, setName] = useState(product?.name ?? '');
  const [barcode, setBarcode] = useState(startBarcode);
  const [price, setPrice] = useState(startPrice);
  const [scannerArmed, setScannerArmed] = useState(!startBarcode);
  const duplicate = findDuplicate(catalog, barcode, id);
  const scrollRef = useRef<ScrollViewInstance>(null);
  const fieldErrors = useFieldErrors({
    fieldOrder: PRODUCT_FIELDS,
    validate: () => ({
      name: name.trim() ? undefined : t.fieldRequired,
      price: !price.trim() ? t.fieldRequired : isValidMillimeText(price) ? undefined : t.invalidAmount,
    }),
    fieldErrorFor: ledgerFieldErrorFor(
      { duplicateBarcode: 'barcode', invalidBarcode: 'barcode', requiredField: 'name', invalidAmount: 'price' },
      t,
    ),
    scrollRef,
  });

  useEffect(() => {
    const hasChanges = name !== (product?.name ?? '') || barcode !== startBarcode || price !== startPrice;
    dispatch(setFormDirty({ section: 'products', isDirty: hasChanges }));
  }, [barcode, dispatch, name, price, product, startBarcode, startPrice]);

  useEffect(() => {
    return () => {
      dispatch(clearFormDirty());
    };
  }, [dispatch]);

  function finish(): void {
    dispatch(clearFormDirty());
    dispatch(refreshCompleted());
    router.replace('/ledger/products');
  }

  const saveAction = useAsyncAction(() => ({
    checkFields: fieldErrors.checkFields,
    showFieldError: fieldErrors.showReportedError,
    work: () => {
      const input = { name, barcode: barcode.trim() || null, sellingPriceMillimes: parseMillimes(price) };
      return id ? updateProduct(id, input) : createProduct(input);
    },
    loaderLabel: t.savingProduct,
    loaderDetail: name.trim() || null,
    successToast: { title: t.productSavedToast, detail: name.trim() },
    onSuccess: finish,
    failureThing: t.thingProduct,
    keepValuesMessage: t.valuesKept,
  }));

  const removeAction = useAsyncAction(() => ({
    work: () => removeProduct(id ?? ''),
    loaderLabel: t.removingProduct,
    loaderDetail: name.trim() || null,
    successToast: { title: t.productRemovedToast, detail: name.trim() },
    onSuccess: finish,
    failureThing: t.thingProduct,
    keepValuesMessage: t.nothingChanged,
  }));

  const isNewCode = !duplicate && barcode.trim() !== '' && barcode.trim() !== (product?.barcode ?? '');
  const footer = (
    <BusyButton
      label={t.saveProduct}
      busy={saveAction.busy}
      disabled={Boolean(duplicate) || removeAction.busy}
      className="min-h-button-lg"
      onPress={() => void saveAction.run()}
    />
  );

  return (
    <ScreenScroll footer={footer} footerWithSafeArea scrollRef={scrollRef}>
      <BarcodeScanner
        mode="inline"
        armed={scannerArmed}
        scanLabel={t.scanBarcode}
        permissionLabel={t.cameraAccess}
        closeLabel={t.close}
        onScanned={(value) => {
          setScannerArmed(false);
          setBarcode(value);
          fieldErrors.fieldChanged('barcode');
        }}
      />

      <LedgerField
        ref={fieldErrors.register('barcode')}
        label={t.barcodeOptional}
        value={barcode}
        error={fieldErrors.errorOf('barcode')}
        onChangeText={(value) => {
          setBarcode(value);
          fieldErrors.fieldChanged('barcode');
        }}
        autoCapitalize="none"
        textAlign="left"
        hint={duplicate || isNewCode ? undefined : t.noCameraHint}
        accessory={
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={t.rescan}
            className="h-[52px] w-[52px] items-center justify-center rounded-control border border-line-strong bg-surface-2 active:bg-muted"
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
        ref={fieldErrors.register('name')}
        label={t.productName}
        value={name}
        error={fieldErrors.errorOf('name')}
        onChangeText={(value) => {
          setName(value);
          fieldErrors.fieldChanged('name');
        }}
        autoCapitalize="sentences"
        textAlign={language === 'ar' ? 'right' : 'left'}
      />
      <MillimeInput
        ref={fieldErrors.register('price')}
        label={t.salePrice}
        value={price}
        error={fieldErrors.errorOf('price')}
        onChangeText={(value) => {
          setPrice(value);
          fieldErrors.fieldChanged('price');
        }}
        suffix={t.currencySuffix}
        hint={t.millimesHint}
      />

      {id && product?.isActive ? (
        <ConfirmAction
          buttonLabel={t.removeProduct}
          title={t.confirmRemoval}
          message={t.removeProductMessage}
          confirmLabel={t.remove}
          cancelLabel={t.cancel}
          busy={removeAction.busy}
          busyLabel={t.removingProduct}
          onConfirm={() => void removeAction.run()}
        />
      ) : null}
    </ScreenScroll>
  );
}
