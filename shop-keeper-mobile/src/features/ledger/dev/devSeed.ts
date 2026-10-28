import { createId } from '@/lib/id';
import { createCustomer } from '../services/customerService';
import { recordSale } from '../services/saleService';
import { buildTicketFromDraft } from '../services/ticketService';
import { SalePaymentChoice } from '../types/sale';
import { TicketDraftItem } from '../types/ticket';

export const DEV_SEED_CUSTOMER_COUNT = 300;
export const DEV_SEED_TICKET_COUNT = 2000;

const SAMPLE_PRODUCTS = [
  { name: 'Lait', unitPriceMillimes: 1350 },
  { name: 'Pain', unitPriceMillimes: 250 },
  { name: 'Huile', unitPriceMillimes: 7900 },
  { name: 'Sucre', unitPriceMillimes: 1500 },
] as const;

const DAY_MS = 24 * 60 * 60 * 1000;
const SEED_HISTORY_DAYS = 90;

function draftLine(index: number): TicketDraftItem {
  const product = SAMPLE_PRODUCTS[index % SAMPLE_PRODUCTS.length];
  return {
    id: createId(),
    productId: null,
    productName: product.name,
    barcode: null,
    unitPriceMillimes: product.unitPriceMillimes,
    quantity: (index % 3) + 1,
  };
}

function saleChoice(index: number, totalMillimes: number): SalePaymentChoice {
  if (index % 3 === 0) return { kind: 'PAID' };
  if (index % 3 === 1) return { kind: 'UNPAID' };
  return { kind: 'PARTIAL', amountMillimes: Math.max(1, Math.floor(totalMillimes / 2)) };
}

export async function seedDevelopmentLedger(now: Date = new Date()): Promise<void> {
  const customerIds: string[] = [];
  for (let index = 0; index < DEV_SEED_CUSTOMER_COUNT; index += 1) {
    const customer = await createCustomer({ displayLabel: `Client ${index + 1}` });
    customerIds.push(customer.id);
  }
  for (let index = 0; index < DEV_SEED_TICKET_COUNT; index += 1) {
    const createdAt = new Date(now.getTime() - (index % SEED_HISTORY_DAYS) * DAY_MS).toISOString();
    const lines = [draftLine(index), draftLine(index + 1)];
    const ticket = buildTicketFromDraft(customerIds[index % customerIds.length], lines, createdAt);
    await recordSale(ticket, saleChoice(index, ticket.totalMillimes));
  }
}
