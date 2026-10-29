import { useCallback, useEffect, useRef, useState } from "react";
import { router, useLocalSearchParams } from "expo-router";
import { Pressable, ScrollViewInstance, Text, View } from "react-native";
import { useDispatch } from "react-redux";
import { useLedgerTranslations } from "@/i18n/useTranslations";
import { getLedgerErrorMessage } from "@/i18n/errorMessages";
import { formatMessage } from "@/i18n/format";
import { useAsyncAction } from "@/features/feedback/useAsyncAction";
import { useLedgerQuery } from "@/features/feedback/useLedgerQuery";
import {
  AmountText,
  BottomSheet,
  BusyButton,
  FieldErrorText,
  Icon,
  InfoBanner,
  ScreenScroll,
  SegmentedControl,
  SkeletonRows,
  StatusChip,
  isolateLtr,
  useFieldErrors,
} from "@/components/ui";
import type { SegmentOption } from "@/components/ui";
import { formatMillimeAmount } from "@/lib/money";
import { createId } from "@/lib/id";
import { useTopBarOptions } from "@/navigation/TopBarContext";
import {
  clearFormDirty,
  refreshCompleted,
  setFormDirty,
} from "../store/ledgerSlice";
import {
  createProduct,
  findProductByBarcode,
  listProducts,
} from "../services/productService";
import {
  addTicketDraftItem,
  calculateTicketTotal,
  updateTicketDraftItemQuantity,
} from "../services/ticketDraftService";
import {
  buildTicketFromDraft,
  updateTicketFromDraft,
} from "../services/ticketService";
import {
  getSaleSaveLabel,
  getSalePaymentAmount,
  recordSale,
  validateSaleChoice,
} from "../services/saleService";
import { isWalkInCustomer, WALK_IN_CUSTOMER_ID } from "../constants";
import { SalePaymentChoice, SalePaymentKind } from "../types/sale";
import { ticketRepository } from "../services/ticketRepository";
import { Product } from "../types/product";
import { Ticket, TicketDraftItem } from "../types/ticket";
import { BarcodeScanner } from "../components/ticket/BarcodeScanner";
import { ProductSearchSheet } from "../components/ticket/ProductSearchSheet";
import { TicketLineRow } from "../components/ticket/TicketLineRow";
import { CustomerPickerCard } from "../components/customers/CustomerPickerCard";
import { LedgerField } from "../components/forms/LedgerField";
import {
  isValidMillimeText,
  MillimeInput,
  parseMillimes,
} from "../components/forms/MillimeInput";
import { ledgerFieldErrorFor } from "../components/forms/ledgerFieldError";
import { useDirtyFormGuard } from "../hooks/useDirtyFormGuard";

const TICKET_FIELDS = ["customer", "items", "partialAmount"] as const;
const NEW_PRODUCT_FIELDS = ["name", "price"] as const;

interface TicketFormData {
  products: Product[];
  ticket: Ticket | null;
}

async function loadTicketForm(id: string | undefined): Promise<TicketFormData> {
  const products = await listProducts();
  const ticket = id ? await ticketRepository.getTicket(id, false) : null;
  return { products, ticket };
}

export function TicketFormScreen() {
  const { id, customerId } = useLocalSearchParams<{
    id?: string;
    customerId?: string;
  }>();
  const { t } = useLedgerTranslations();
  useDirtyFormGuard();
  useTopBarOptions({ title: id ? t.editTicket : t.newTicket });
  const loadForm = useCallback(() => loadTicketForm(id), [id]);
  const query = useLedgerQuery(loadForm, [id]);

  if (query.status === "loading") {
    return (
      <ScreenScroll>
        <SkeletonRows variant="tile" count={3} />
      </ScreenScroll>
    );
  }
  if (!query.data || (id && !query.data.ticket)) {
    return (
      <ScreenScroll>
        <InfoBanner
          tone="error"
          title={query.status === "error" ? query.error : t.recordUnavailable}
          actionLabel={query.status === "error" ? t.retry : undefined}
          onAction={query.retry}
        />
      </ScreenScroll>
    );
  }
  return (
    <TicketForm
      key={id ?? customerId ?? "new"}
      id={id}
      initialCustomerId={customerId}
      initial={query.data}
    />
  );
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

interface TicketFormProps {
  id?: string;
  initialCustomerId?: string;
  initial: TicketFormData;
}

function TicketForm({ id, initialCustomerId, initial }: TicketFormProps) {
  const { t, language } = useLedgerTranslations();
  const dispatch = useDispatch();
  const initialCustomer = initial.ticket?.customerId ?? initialCustomerId ?? "";

  const [products, setProducts] = useState<Product[]>(initial.products);
  const [customerId, setCustomerId] = useState(initialCustomer);
  const [items, setItems] = useState<TicketDraftItem[]>(() =>
    toDraftItems(initial.ticket),
  );
  const [searchOpen, setSearchOpen] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [newProductName, setNewProductName] = useState("");
  const [newProductPrice, setNewProductPrice] = useState("");
  const [pendingBarcode, setPendingBarcode] = useState("");
  const [draftError, setDraftError] = useState("");
  const [newProductError, setNewProductError] = useState("");
  const isNew = !id;
  const [saleKind, setSaleKind] = useState<SalePaymentKind>("PAID");
  const [partialAmount, setPartialAmount] = useState("");
  const scrollRef = useRef<ScrollViewInstance>(null);
  const newProductFields = useFieldErrors({
    fieldOrder: NEW_PRODUCT_FIELDS,
    validate: () => ({
      name: newProductName.trim() ? undefined : t.fieldRequired,
      price: !newProductPrice.trim()
        ? t.fieldRequired
        : isValidMillimeText(newProductPrice)
          ? undefined
          : t.invalidAmount,
    }),
    fieldErrorFor: ledgerFieldErrorFor(
      { requiredField: "name", invalidAmount: "price" },
      t,
    ),
  });

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
    dispatch(setFormDirty({ section: "tickets", isDirty: hasChanges }));
  }, [customerId, dispatch, initial.ticket, initialCustomer, items]);

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
      setDraftError("");
    } catch (cause) {
      setDraftError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  function matchBarcode(value: string): void {
    const product = findProductByBarcode(products, value);
    if (product) {
      addProduct(product);
      setPendingBarcode("");
    } else {
      setPendingBarcode(value.trim());
      setCreateOpen(true);
    }
  }

  function openCreateMissing(): void {
    setSearchOpen(false);
    setPendingBarcode("");
    setCreateOpen(true);
  }

  function closeCreate(): void {
    setCreateOpen(false);
    setPendingBarcode("");
    setNewProductName("");
    setNewProductPrice("");
    setNewProductError("");
    newProductFields.reset();
  }

  const createProductAction = useAsyncAction(() => ({
    checkFields: newProductFields.checkFields,
    showFieldError: newProductFields.showReportedError,
    work: () =>
      createProduct({
        name: newProductName.trim(),
        barcode: pendingBarcode || null,
        sellingPriceMillimes: parseMillimes(newProductPrice),
      }),
    loaderLabel: t.savingProduct,
    loaderDetail: newProductName.trim() || null,
    successToast: { title: t.productSavedToast, detail: newProductName.trim() },
    onSuccess: (product: Product) => {
      setProducts((current) => [...current, product]);
      addProduct(product);
      dispatch(refreshCompleted());
      closeCreate();
    },
    onFailure: (cause: unknown) =>
      setNewProductError(getLedgerErrorMessage(cause, t, t.saveFailed)),
    failureThing: t.thingProduct,
    keepValuesMessage: t.valuesKept,
  }));

  function changeQuantity(itemId: string, quantity: number): void {
    try {
      setItems((current) =>
        updateTicketDraftItemQuantity(current, itemId, quantity),
      );
      setDraftError("");
    } catch (cause) {
      setDraftError(getLedgerErrorMessage(cause, t, t.saveFailed));
    }
  }

  const total = calculateTicketTotal(items);
  const choice: SalePaymentChoice =
    saleKind === "PARTIAL"
      ? { kind: "PARTIAL", amountMillimes: parseAmountOrNull(partialAmount) }
      : { kind: saleKind };
  const choiceError =
    isNew && items.length > 0
      ? validateSaleChoice(choice, total, customerId || null)
      : null;
  const customerError =
    choiceError === "kridiNeedsCustomer" ? t.kridiNeedsCustomer : undefined;
  const partialAmountRuleError =
    choiceError === "partialAmountInvalid"
      ? t.partialAmountInvalid
      : choiceError === "partialEqualsTotal"
        ? t.partialEqualsTotal
        : undefined;
  const ticketFields = useFieldErrors({
    fieldOrder: TICKET_FIELDS,
    validate: () => ({
      customer: customerError,
      items: items.length === 0 ? t.selectProductsFirst : undefined,
      partialAmount: partialAmountRuleError,
    }),
    fieldErrorFor: ledgerFieldErrorFor(
      {
        kridiNeedsCustomer: "customer",
        partialAmountInvalid: "partialAmount",
        partialEqualsTotal: "partialAmount",
        invalidQuantity: "items",
      },
      t,
    ),
    scrollRef,
  });
  const amountError =
    partialAmount !== ""
      ? partialAmountRuleError
      : ticketFields.errorOf("partialAmount");
  const itemsError = ticketFields.errorOf("items");
  const ticketIsWalkIn = isWalkInCustomer(initialCustomer);
  const saleOptions: SegmentOption<SalePaymentKind>[] = [
    { value: "PAID", label: t.statusPaidShort, tone: "paid" },
    { value: "PARTIAL", label: t.statusPartialShort, tone: "partial" },
    { value: "UNPAID", label: t.statusKridiShort, tone: "debt" },
  ];
  const saveLabel = (() => {
    if (!isNew) return t.save;
    const label = getSaleSaveLabel(choice, total);
    const money = (value: number) => formatMillimeAmount(value, language);
    if (label.key === "collectWithRemainder") {
      return formatMessage(t.collectWithRemainder, {
        paid: money(label.params.paid),
        rest: money(label.params.rest),
      });
    }
    return formatMessage(t[label.key], { amount: money(label.params.amount) });
  })();
  const collectedMillimes = isNew ? getSalePaymentAmount(choice, total) : 0;

  async function saveTicket() {
    const editedTicket = initial.ticket;
    if (editedTicket) {
      return ticketRepository.updateTicket(
        editedTicket.id,
        updateTicketFromDraft(
          editedTicket,
          customerId || editedTicket.customerId,
          items,
        ),
      );
    }
    const sale = await recordSale(
      buildTicketFromDraft(customerId || WALK_IN_CUSTOMER_ID, items),
      choice,
    );
    return sale.ticket;
  }

  const saveAction = useAsyncAction(() => {
    const saved = { ticketId: "" };
    return {
      checkFields: ticketFields.checkFields,
      showFieldError: ticketFields.showReportedError,
      work: saveTicket,
      loaderLabel: t.savingTicket,
      loaderDetail: formatMessage(t.itemsAndTotal, {
        count: items.length,
        amount: formatMillimeAmount(total, language),
      }),
      successToast: {
        title: t.ticketSavedToast,
        detail:
          collectedMillimes > 0
            ? formatMessage(t.amountCollected, {
                amount: formatMillimeAmount(collectedMillimes, language),
              })
            : formatMessage(t.itemsCount, { count: items.length }),
        view: () =>
          router.push({
            pathname: "/ledger/ticket-detail",
            params: { id: saved.ticketId },
          }),
      },
      onSuccess: (ticket: Ticket) => {
        saved.ticketId = ticket.id;
        dispatch(clearFormDirty());
        dispatch(refreshCompleted());
        router.replace("/ledger/tickets");
      },
      failureThing: t.thingTicket,
      keepValuesMessage: t.valuesKept,
    };
  });

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
          {saleKind === "PARTIAL" ? (
            <View className="gap-1.5">
              <MillimeInput
                ref={ticketFields.register("partialAmount")}
                label={t.amountReceived}
                value={partialAmount}
                onChangeText={setPartialAmount}
                suffix={t.currencySuffix}
                error={amountError}
              />
              {!amountError &&
              choice.kind === "PARTIAL" &&
              choice.amountMillimes ? (
                <Text className="font-plex-semibold text-label text-debt">
                  {formatMessage(t.remainingToKridi, {
                    amount: formatMillimeAmount(
                      total - getSalePaymentAmount(choice, total),
                      language,
                    ),
                  })}
                </Text>
              ) : null}
            </View>
          ) : null}
        </>
      ) : null}
      <BusyButton
        variant={isNew && saleKind !== "UNPAID" ? "paid" : "primary"}
        label={saveLabel}
        accessibilityLabel={saveLabel}
        busy={saveAction.busy}
        busyLabel={t.saving}
        disabled={createProductAction.busy}
        className="min-h-button-lg"
        onPress={() => void saveAction.run()}
      />
    </>
  );

  return (
    <ScreenScroll footer={footer} footerWithSafeArea scrollRef={scrollRef}>
      {draftError ? <InfoBanner tone="error" title={draftError} /> : null}

      <CustomerPickerCard
        ref={ticketFields.register("customer")}
        value={isWalkInCustomer(customerId) ? "" : customerId}
        onChange={setCustomerId}
        error={customerError}
        walkInAllowed={(isNew && saleKind === "PAID") || ticketIsWalkIn}
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
          className="min-h-[64px] flex-1 flex-row items-center justify-center gap-2 rounded-control-lg border border-line-strong bg-surface-2 px-3 active:bg-muted"
          onPress={() => setSearchOpen(true)}
        >
          <Icon name="search" size={22} className="text-primary" />
          <Text className="type-button text-primary">{t.searchAction}</Text>
        </Pressable>
      </View>

      <View
        ref={ticketFields.register("items")}
        accessible={Boolean(itemsError)}
        accessibilityLabel={itemsError}
        className={`ui-card-flush ${itemsError ? "ui-input-error" : ""}`}
      >
        <Text
          accessibilityRole="header"
          className="type-heading px-4 pt-3.5 pb-1"
        >
          {formatMessage(t.articlesCount, { count: items.length })}
        </Text>
        {itemsError ? (
          <View className="px-4 pb-1">
            <FieldErrorText message={itemsError} />
          </View>
        ) : null}
        {items.length === 0 ? (
          <Text className="type-caption px-4 pb-2">{t.noItemsYet}</Text>
        ) : null}
        {items.map((item, index) => (
          <TicketLineRow
            key={item.id}
            item={item}
            separated={index > 0}
            onQuantityChange={(quantity) => changeQuantity(item.id, quantity)}
            onRemove={() =>
              setItems((current) =>
                current.filter((line) => line.id !== item.id),
              )
            }
          />
        ))}
        <Pressable
          accessibilityRole="button"
          className="min-h-touch flex-row items-center gap-2 border-t border-line-soft px-4 py-3 active:bg-muted"
          onPress={openCreateMissing}
        >
          <Icon name="add-circle-outline" size={20} className="text-primary" />
          <Text className="font-plex-semibold text-body text-primary">
            {t.missingProduct}
          </Text>
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
        helper={
          pendingBarcode
            ? isolateLtr(
                formatMessage(t.unknownBarcodeTitle, { code: pendingBarcode }),
              )
            : t.draftKept
        }
        onClose={closeCreate}
        confirmLabel={t.save}
        confirmBusy={createProductAction.busy}
        onConfirm={() => void createProductAction.run()}
      >
        {newProductError ? (
          <InfoBanner tone="error" title={newProductError} />
        ) : null}
        {pendingBarcode ? (
          <InfoBanner tone="warning" title={t.unknownBarcode} />
        ) : null}
        <LedgerField
          ref={newProductFields.register("name")}
          label={t.productName}
          value={newProductName}
          error={newProductFields.errorOf("name")}
          onChangeText={(value) => {
            setNewProductName(value);
            newProductFields.fieldChanged("name");
          }}
        />
        <MillimeInput
          ref={newProductFields.register("price")}
          label={t.price}
          value={newProductPrice}
          error={newProductFields.errorOf("price")}
          onChangeText={(value) => {
            setNewProductPrice(value);
            newProductFields.fieldChanged("price");
          }}
          suffix={t.currencySuffix}
        />
      </BottomSheet>
    </ScreenScroll>
  );
}
