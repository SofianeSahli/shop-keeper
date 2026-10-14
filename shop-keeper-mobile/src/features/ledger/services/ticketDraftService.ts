import { sumMillimeAmounts } from '@/lib/money';
import { TicketDraftItem } from '../types/ticket';

export function calculateLineTotal(unitPriceMillimes: number, quantity: number): number {
  if (!Number.isInteger(quantity) || quantity <= 0) {
    throw new Error('Ticket quantity must be a positive integer.');
  }

  if (!Number.isInteger(unitPriceMillimes) || unitPriceMillimes <= 0) {
    throw new Error('Ticket item price must be a positive integer in millimes.');
  }

  const lineTotal = unitPriceMillimes * quantity;
  if (!Number.isSafeInteger(lineTotal)) {
    throw new Error('Ticket line total exceeds the supported integer range.');
  }
  return lineTotal;
}

export function addTicketDraftItem(
  items: TicketDraftItem[],
  item: TicketDraftItem,
): TicketDraftItem[] {
  calculateLineTotal(item.unitPriceMillimes, item.quantity);

  return [...items, { ...item }];
}

export function updateTicketDraftItemQuantity(
  items: TicketDraftItem[],
  itemId: string,
  quantity: number,
): TicketDraftItem[] {
  if (!items.some((item) => item.id === itemId)) {
    throw new Error('Ticket item not found.');
  }
  if (!Number.isInteger(quantity) || quantity <= 0) {
    throw new Error('Ticket quantity must be a positive integer.');
  }

  return items.map((item) => (item.id === itemId ? { ...item, quantity } : item));
}

export function calculateTicketTotal(items: TicketDraftItem[]): number {
  return sumMillimeAmounts(
    items.map((item) => calculateLineTotal(item.unitPriceMillimes, item.quantity)),
  );
}
