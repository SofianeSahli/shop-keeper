import { useEffect, useState } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { formatMessage } from '@/i18n/format';
import { AmountText, BottomSheet, Icon, InfoBanner, ScreenScroll, StatusChip, isolateLtr } from '@/components/ui';
import { createId } from '@/lib/id';
import { clearFormDirty, refreshCompleted, setFormDirty } from '../store/ledgerSlice';
import { createProduct, findProductByBarcode, listProducts } from '../services/productService';
import {
  addTicketDraftItem,
  calculateTicketTotal,
  updateTicketDraftItemQuantity,
} from '../services/ticketDraftService';
import { buildTicketFromDraft, updateTicketFromDraft } from '../services/ticketService';
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

function TicketForm({ id, initialCustomerId }: { id?: string; initialCustomerId?: string }) {
  const { t } = useLedgerTranslations();
  const dispatch = useDispatch();
  useDirtyFormGuard();
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
    try {
      if (!customerId) throw new Error(t.chooseCustomer);
      if (items.length === 0) throw new Error(t.selectProductsFirst);
      if (id) {
        const existing = ticketRepository.getTicket(id, false);
        if (!existing) throw new Error(t.loadFailed);
        ticketRepository.updateTicket(id, updateTicketFromDraft(existing, customerId, items));
      } else {
        ticketRepository.createTicket(buildTicketFromDraft(customerId, items));
      }
      dispatch(clearFormDirty());
      dispatch(refreshCompleted());
      router.replace('/ledger/tickets');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  const total = calculateTicketTotal(items);

  const footer = (
    <>
      <View className="flex-row items-center justify-between gap-3">
        <Text className="type-label">{t.ticketTotal}</Text>
        <AmountText millimes={total} size="display" fitToWidth />
      </View>
      {initial.ticket ? <StatusChip status={initial.ticket.status} /> : null}
      <Pressable
        accessibilityRole="button"
        accessibilityState={{ disabled: items.length === 0 }}
        className={`ui-button-primary min-h-button-lg ${items.length === 0 ? 'ui-button-disabled' : ''}`}
        onPress={save}
      >
        <Text className="type-button text-surface">{t.save}</Text>
      </Pressable>
    </>
  );

  return (
    <ScreenScroll footer={footer} footerWithSafeArea>
      {error ? <InfoBanner tone="error" title={error} /> : null}

      <CustomerPickerCard value={customerId} onChange={setCustomerId} />

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
