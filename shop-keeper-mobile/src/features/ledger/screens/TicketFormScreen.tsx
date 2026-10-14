import { useState } from 'react';
import { router, useLocalSearchParams } from 'expo-router';
import { Pressable, Text, View } from 'react-native';
import { useDispatch } from 'react-redux';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { refreshCompleted } from '../store/ledgerSlice';
import { createProduct, findProductByBarcode, listProducts } from '../services/productService';
import { listCustomers } from '../services/customerService';
import {
  addTicketDraftItem,
  calculateTicketTotal,
  updateTicketDraftItemQuantity,
} from '../services/ticketDraftService';
import { buildTicketFromDraft, updateTicketFromDraft } from '../services/ticketService';
import { ticketRepository } from '../services/ticketRepository';
import { Customer } from '../types/customer';
import { Product, ProductInput } from '../types/product';
import { TicketDraftItem } from '../types/ticket';
import { BarcodeScanner } from '../components/ticket/BarcodeScanner';
import { TicketLineEditor } from '../components/ticket/TicketLineEditor';
import { ProductPicker } from '../components/ticket/ProductPicker';
import { TicketTotal } from '../components/ticket/TicketTotal';
import { FeedbackState } from '../components/feedback/FeedbackState';
import { LedgerPage } from '../components/LedgerPage';
import { LedgerField } from '../components/forms/LedgerField';
import { MillimeInput, parseMillimes } from '../components/forms/MillimeInput';
import { createId } from '@/lib/id';

export function TicketFormScreen() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  return <TicketForm key={id ?? 'new'} id={id} />;
}

function TicketForm({ id }: { id?: string }) {
  const { t } = useLedgerTranslations();
  const dispatch = useDispatch();
  const [initial] = useState(() => {
    try {
      const customers = listCustomers();
      const products = listProducts();
      const ticket = id ? ticketRepository.getTicket(id, false) : null;
      return { customers, products, ticket, error: id && !ticket ? t.loadFailed : '' };
    } catch (cause) {
      return {
        customers: [] as Customer[],
        products: [] as Product[],
        ticket: null,
        error: getLedgerErrorMessage(cause, t, t.loadFailed),
      };
    }
  });
  const [customers] = useState<Customer[]>(initial.customers);
  const [products, setProducts] = useState<Product[]>(initial.products);
  const [customerId, setCustomerId] = useState(initial.ticket?.customerId ?? initial.customers[0]?.id ?? '');
  const [items, setItems] = useState<TicketDraftItem[]>(
    initial.ticket?.lines.map((line) => ({
      id: line.id,
      productId: line.productId,
      productName: line.productNameSnapshot,
      barcode: line.barcodeSnapshot,
      unitPriceMillimes: line.unitPriceMillimes,
      quantity: line.quantity,
    })) ?? [],
  );
  const [barcode, setBarcode] = useState('');
  const [pendingBarcode, setPendingBarcode] = useState('');
  const [quickProductName, setQuickProductName] = useState('');
  const [quickProductPrice, setQuickProductPrice] = useState('');
  const [error, setError] = useState(initial.error);

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
    setBarcode(value);
    const product = findProductByBarcode(products, value);
    if (product) {
      addProduct(product);
      setBarcode('');
    } else {
      setPendingBarcode(value);
      setError(t.unknownBarcode);
    }
  }

  function saveScannedProduct(): void {
    try {
      const input: ProductInput = {
        barcode: pendingBarcode,
        name: quickProductName,
        sellingPriceMillimes: parseMillimes(quickProductPrice),
      };
      const product = createProduct(input);
      setProducts((current) => [...current, product]);
      addProduct(product);
      dispatch(refreshCompleted());
      setBarcode('');
      setPendingBarcode('');
      setQuickProductName('');
      setQuickProductPrice('');
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
      dispatch(refreshCompleted());
      router.replace('/ledger/tickets');
    } catch (cause) {
      setError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  const total = calculateTicketTotal(items);

  return (
    <LedgerPage title={id ? t.editTicket : t.newTicket}>
      {error ? <FeedbackState kind="error" message={error} /> : null}
      <View className="gap-2">
        <Text className="font-semibold text-slate-800">{t.chooseCustomer}</Text>
        {customers.map((customer) => (
          <Pressable
            key={customer.id}
            accessibilityRole="radio"
            accessibilityState={{ selected: customer.id === customerId }}
            className={`ledger-touch-target rounded-lg p-3 ${customer.id === customerId ? 'bg-blue-100' : 'bg-white'}`}
            onPress={() => setCustomerId(customer.id)}
          >
            <Text className="text-slate-900">{customer.displayLabel}</Text>
          </Pressable>
        ))}
        {customers.length === 0 ? <FeedbackState kind="empty" message={t.emptyCustomers} /> : null}
      </View>
      <BarcodeScanner
        scanLabel={t.scanBarcode}
        permissionLabel={t.cameraAccess}
        closeLabel={t.close}
        onScanned={matchBarcode}
      />
      <View className="gap-2">
        <LedgerField
          label={t.barcode}
          value={barcode}
          onChangeText={setBarcode}
          autoCapitalize="none"
          textAlign="left"
        />
        <Pressable
          accessibilityRole="button"
          className="ledger-touch-target rounded-xl border border-blue-300 px-4 py-3"
          onPress={() => matchBarcode(barcode)}
        >
          <Text className="text-center font-semibold text-blue-800">{t.search}</Text>
        </Pressable>
        {pendingBarcode ? (
          <View className="gap-3 rounded-xl bg-blue-50 p-4">
            <Text className="font-semibold text-blue-900">{t.addProduct}: {pendingBarcode}</Text>
            <LedgerField
              label={t.productName}
              value={quickProductName}
              onChangeText={setQuickProductName}
            />
            <MillimeInput
              label={t.price}
              value={quickProductPrice}
              onChangeText={setQuickProductPrice}
            />
            <Pressable accessibilityRole="button" className="ledger-primary-button" onPress={saveScannedProduct}>
              <Text className="font-semibold text-white">{t.save}</Text>
            </Pressable>
          </View>
        ) : null}
      </View>
      {products.length === 0 ? <FeedbackState kind="empty" message={t.noActiveProducts} /> : null}
      <ProductPicker products={products} onSelect={addProduct} />
      <View className="gap-3">
        {items.map((item) => (
          <TicketLineEditor
            key={item.id}
            item={item}
            quantityLabel={t.quantity}
            removeLabel={t.removeLine}
            onQuantityChange={(quantity) => {
              try {
                setItems((current) => updateTicketDraftItemQuantity(current, item.id, quantity));
                setError('');
              } catch (cause) {
                setError(getLedgerErrorMessage(cause, t, t.saveFailed));
              }
            }}
            onRemove={() => setItems((current) => current.filter((line) => line.id !== item.id))}
          />
        ))}
      </View>
      <TicketTotal amountMillimes={total} />
      <Pressable
        accessibilityRole="button"
        className="ledger-touch-target rounded-xl border border-slate-300 px-4 py-3"
        onPress={() => router.replace('/ledger/tickets')}
      >
        <Text className="text-center font-semibold text-slate-700">{t.cancel}</Text>
      </Pressable>
      <Pressable accessibilityRole="button" className="ledger-primary-button" onPress={save}>
        <Text className="font-semibold text-white">{t.save}</Text>
      </Pressable>
    </LedgerPage>
  );
}
