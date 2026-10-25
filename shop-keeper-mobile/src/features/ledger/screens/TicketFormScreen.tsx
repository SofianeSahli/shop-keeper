import { useEffect, useState } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { AmountText, BottomSheet, Icon, InfoBanner, ScreenScroll, SegmentedControl, StatusChip, isolateLtr } from '@/components/ui';
import type { SegmentOption } from '@/components/ui';
import { formatMillimeAmount } from '@/lib/money';
import { createId } from '@/lib/id';
import { useTopBarOptions } from '@/navigation/TopBarContext';
import { clearFormDirty, refreshCompleted, setFormDirty } from '../store/ledgerSlice';
import { createProduct, findProductByBarcode, listProducts } from '../services/productService';
import {
  addTicketDraftItem,
  calculateTicketTotal,
  updateTicketDraftItemQuantity,
} from '../services/ticketDraftService';
import { buildTicketFromDraft, updateTicketFromDraft } from '../services/ticketService';
import { getSaleSaveLabel, getSalePaymentAmount, recordSale, validateSaleChoice } from '../services/saleService';
import { isWalkInCustomer, WALK_IN_CUSTOMER_ID } from '../constants';
import { SalePaymentChoice, SalePaymentKind } from '../types/sale';
import { ticketRepository } from '../services/ticketRepository';
import { Product } from '../types/product';
import { Ticket, TicketDraftItem } from '../types/ticket';
import { BarcodeScanner } from '../components/ticket/BarcodeScanner';
import { ProductSearchSheet } from '../components/ticket/ProductSearchSheet';
import { TicketLineRow } from '../components/ticket/TicketLineRow';
import { CustomerPickerCard } from '../components/customers/CustomerPickerCard';
import { LedgerField } from '../components/forms/LedgerField';
import { MillimeInput, parseMillimes } from '../components/forms/MillimeInput';
import { useDirtyFormGuard } from '../hooks/useDirtyFormGuard';

export function TicketFormScreen() {
  const { id, customerId } = useLocalSearchParams<{ id?: string; customerId?: string }>();
  return <TicketForm key={id ?? customerId ?? 'new'} id={id} initialCustomerId={customerId} />;
}

function toDraftItems(ticket: Ticket | null): TicketDraftItem[] {
  return (
    ticket?.lines.map((line) => ({
      id: line.id,
      productId: line.productId,
      productName: line.productNameSnapshot,
      barcode: line.barcodeSnapshot,
      unitPriceMillimes: line.unitPriceMillimes,
      quantity: line.quantity,
    })) ?? []
  );
}

function parseAmountOrNull(value: string): number | null {
  try {
    return parseMillimes(value);
  } catch {
    return null;
  }
}

function TicketForm({ id, initialCustomerId }: { id?: string; initialCustomerId?: string }) {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  useDirtyFormGuard();
  useTopBarOptions({ title: id ? t.editTicket : t.newTicket });
  const [initial] = useState(() => {
    try {
      const products = listProducts();
      const ticket = id ? ticketRepository.getTicket(id, false) : null;
      return { products, ticket, error: id && !ticket ? t.loadFailed : '' };
    } catch (cause) {
      return {
        products: [] as Product[],
        ticket: null,
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
      };
    }
  });
  const initialCustomer = initial.ticket?.customerId ?? initialCustomerId ?? '';

  const [products, setProducts] = useState<Product[]>(initial.products);
  const [customerId, setCustomerId] = useState(initialCustomer);
  const [items, setItems] = useState<TicketDraftItem[]>(() => toDraftItems(initial.ticket));
  const [searchOpen, setSearchOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [newProductName, setNewProductName] = useState('');
  const [newProductPrice, setNewProductPrice] = useState('');
  const [pendingBarcode, setPendingBarcode] = useState('');
  const [error, setError] = useState(initial.error);
  // Payment status chosen at sale (spec 003, US8): new tickets only, Paid by default.
  const isNew = !id;
  const [saleKind, setSaleKind] = useState<SalePaymentKind>('PAID');
  const [partialAmount, setPartialAmount] = useState('');
  const [submitted, setSubmitted] = useState(false);

  useEffect(() => {
    const original = initial.ticket?.lines ?? [];
    const hasChanges =
      customerId !== initialCustomer ||
      items.length !== original.length ||
      items.some((item, index) => {
        const line = original[index];
        return (
          !line ||
          item.productId !== line.productId ||
          item.quantity !== line.quantity ||
          item.unitPriceMillimes !== line.unitPriceMillimes ||
          item.productName !== line.productNameSnapshot
        );
      });
    dispatch(setFormDirty({ section: 'tickets', isDirty: hasChanges && !initial.error }));
  }, [customerId, dispatch, initial.error, initial.ticket, initialCustomer, items]);

  useEffect(() => {
    return () => {
      dispatch(clearFormDirty());
    };
  }, [dispatch]);

  function addProduct(product: Product): void {
    try {
      const item: TicketDraftItem = {
        id: createId(),
        productId: product.id,
        productName: product.name,
        barcode: product.barcode,
        unitPriceMillimes: product.sellingPriceMillimes,
        quantity: 1,
      };
      setItems((current) => addTicketDraftItem(current, item));
      setError('');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  function matchBarcode(value: string): void {
    const product = findProductByBarcode(products, value);
    if (product) {
      addProduct(product);
      setPendingBarcode('');
    } else {
      // Unknown code: offer to create the product with this code, keeping the draft.
      setPendingBarcode(value.trim());
      setCreateOpen(true);
    }
  }

  function openCreateMissing(): void {
    setSearchOpen(false);
    setPendingBarcode('');
    setCreateOpen(true);
  }

  function closeCreate(): void {
    setCreateOpen(false);
    setPendingBarcode('');
    setNewProductName('');
    setNewProductPrice('');
  }

  function saveNewProduct(): void {
    try {
      const product = createProduct({
        name: newProductName.trim(),
        barcode: pendingBarcode || null,
        sellingPriceMillimes: parseMillimes(newProductPrice),
      });
      setProducts((current) => [...current, product]);
      addProduct(product);
      dispatch(refreshCompleted());
      closeCreate();
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  function changeQuantity(itemId: string, quantity: number): void {
    try {
      setItems((current) => updateTicketDraftItemQuantity(current, itemId, quantity));
      setError('');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  function save(): void {
    setSubmitted(true);
    try {
      if (items.length === 0) throw new Error(t.selectProductsFirst);
      if (id) {
        const existing = ticketRepository.getTicket(id, false);
        if (!existing) throw new Error(t.loadFailed);
        ticketRepository.updateTicket(id, updateTicketFromDraft(existing, customerId || existing.customerId, items));
      } else {
        if (choiceError) return; // shown under the customer card or the amount field
        recordSale(buildTicketFromDraft(customerId || WALK_IN_CUSTOMER_ID, items), choice);
      }
      dispatch(clearFormDirty());
      dispatch(refreshCompleted());
      router.replace('/ledger/tickets');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  const total = calculateTicketTotal(items);
  const choice: SalePaymentChoice =
    saleKind === 'PARTIAL' ? { kind: 'PARTIAL', amountMillimes: parseAmountOrNull(partialAmount) } : { kind: saleKind };
  const choiceError = isNew && items.length > 0 ? validateSaleChoice(choice, total, customerId || null) : null;
  const customerError = choiceError === 'kridiNeedsCustomer' ? t.kridiNeedsCustomer : undefined;
  const amountError =
    choiceError === 'partialAmountInvalid' && (submitted || partialAmount !== '')
      ? t.partialAmountInvalid
      : choiceError === 'partialEqualsTotal'
        ? t.partialEqualsTotal
        : undefined;
  const ticketIsWalkIn = isWalkInCustomer(initialCustomer);
  const saleOptions: SegmentOption<SalePaymentKind>[] = [
    { value: 'PAID', label: t.statusPaidShort, tone: 'paid' },
    { value: 'PARTIAL', label: t.statusPartialShort, tone: 'partial' },
    { value: 'UNPAID', label: t.statusKridiShort, tone: 'debt' },
  ];
  const saveLabel = (() => {
    if (!isNew) return t.save;
    const label = getSaleSaveLabel(choice, total);
    const money = (value: number) => formatMillimeAmount(value, language);
    if (label.key === 'collectWithRemainder') {
      return formatMessage(t.collectWithRemainder, { paid: money(label.params.paid), rest: money(label.params.rest) });
    }
    return formatMessage(t[label.key], { amount: money(label.params.amount) });
  })();
  const saveButtonClass = isNew && saleKind !== 'UNPAID' ? 'ui-button-paid' : 'ui-button-primary';

  const footer = (
    <>
      <View className="flex-row items-center justify-between gap-3">
        <Text className="type-label">{t.ticketTotal}</Text>
        <AmountText millimes={total} size="display" fitToWidth />
      </View>
      {initial.ticket ? <StatusChip status={initial.ticket.status} /> : null}
      {isNew ? (
        <>
          <View className="flex-row items-baseline justify-between gap-2">
            <Text accessibilityRole="header" className="type-label">
              {t.paymentStatus}
            </Text>
            <Text className="type-caption">{t.howPaidHint}</Text>
          </View>
          <SegmentedControl
            options={saleOptions}
            value={saleKind}
            onChange={setSaleKind}
            accessibilityLabel={t.paymentStatus}
          />
          {saleKind === 'PARTIAL' ? (
            <View className="gap-1.5">
              <MillimeInput
                label={t.amountReceived}
                value={partialAmount}
                onChangeText={setPartialAmount}
                suffix={t.currencySuffix}
                error={amountError}
              />
              {!amountError && choice.kind === 'PARTIAL' && choice.amountMillimes ? (
                <Text className="font-plex-semibold text-label text-debt">
                  {formatMessage(t.remainingToKridi, {
                    amount: formatMillimeAmount(total - getSalePaymentAmount(choice, total), language),
                  })}
                </Text>
              ) : null}
            </View>
          ) : null}
        </>
      ) : null}
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={saveLabel}
        accessibilityState={{ disabled: items.length === 0 }}
        className={`${saveButtonClass} min-h-button-lg ${items.length === 0 ? 'ui-button-disabled' : ''}`}
        onPress={save}
      >
        <Text numberOfLines={2} className="type-button text-center text-surface">
          {saveLabel}
        </Text>
      </Pressable>
    </>
  );

  return (
    <ScreenScroll footer={footer} footerWithSafeArea>
      {error ? <InfoBanner tone="error" title={error} /> : null}

      <CustomerPickerCard
        value={isWalkInCustomer(customerId) ? '' : customerId}
        onChange={setCustomerId}
        error={customerError}
        walkInAllowed={(isNew && saleKind === 'PAID') || ticketIsWalkIn}
      />

      <View className="flex-row gap-3">
        <BarcodeScanner
          scanLabel={t.scan}
          permissionLabel={t.cameraAccess}
          closeLabel={t.close}
          onScanned={matchBarcode}
        />
        <Pressable
          accessibilityRole="button"
          className="min-h-[64px] flex-1 flex-row items-center justify-center gap-2 rounded-control-lg border border-line-strong bg-surface px-3 active:bg-muted"
          onPress={() => setSearchOpen(true)}
        >
          <Icon name="search" size={22} className="text-primary" />
          <Text className="type-button text-primary">{t.searchAction}</Text>
        </Pressable>
      </View>

      <View className="ui-card-flush">
        <Text accessibilityRole="header" className="type-heading px-4 pt-3.5 pb-1">
          {formatMessage(t.articlesCount, { count: items.length })}
        </Text>
        {items.length === 0 ? <Text className="type-caption px-4 pb-2">{t.noItemsYet}</Text> : null}
        {items.map((item, index) => (
          <TicketLineRow
            key={item.id}
            item={item}
            separated={index > 0}
            onQuantityChange={(quantity) => changeQuantity(item.id, quantity)}
            onRemove={() => setItems((current) => current.filter((line) => line.id !== item.id))}
          />
        ))}
        <Pressable
          accessibilityRole="button"
          className="min-h-touch flex-row items-center gap-2 border-t border-line-soft px-4 py-3 active:bg-muted"
          onPress={openCreateMissing}
        >
          <Icon name="add-circle-outline" size={20} className="text-primary" />
          <Text className="font-plex-semibold text-body text-primary">{t.missingProduct}</Text>
        </Pressable>
      </View>

      <Text className="type-caption">{t.draftKept}</Text>

      <ProductSearchSheet
        visible={searchOpen}
        products={products}
        onSelect={(product) => {
          addProduct(product);
          setSearchOpen(false);
        }}
        onClose={() => setSearchOpen(false)}
        onCreateMissing={openCreateMissing}
      />

      <BottomSheet
        visible={createOpen}
        title={t.addProduct}
        helper={pendingBarcode ? isolateLtr(formatMessage(t.unknownBarcodeTitle, { code: pendingBarcode })) : t.draftKept}
        onClose={closeCreate}
        confirmLabel={t.save}
        onConfirm={saveNewProduct}
      >
        {pendingBarcode ? <InfoBanner tone="warning" title={t.unknownBarcode} /> : null}
        <LedgerField label={t.productName} value={newProductName} onChangeText={setNewProductName} />
        <MillimeInput label={t.price} value={newProductPrice} onChangeText={setNewProductPrice} suffix={t.currencySuffix} />
      </BottomSheet>
    </ScreenScroll>
  );
}
