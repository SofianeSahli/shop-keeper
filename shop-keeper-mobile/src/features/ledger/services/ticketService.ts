import { sumMillimeAmounts } from '@/lib/money';
import { Ticket, TicketDraftItem, TicketLine, TicketStatus } from '../types/ticket';
import { calculateLineTotal } from './ticketDraftService';
import { createId } from '@/lib/id';
import { validateRequiredText } from '@/utils/validation';

export function getTicketStatus(totalPaidMillimes: number, totalMillimes: number): TicketStatus {
  if (!Number.isSafeInteger(totalPaidMillimes) || totalPaidMillimes < 0) {
    throw new Error('Paid amount must be a non-negative integer in millimes.');
  }
  if (!Number.isSafeInteger(totalMillimes) || totalMillimes <= 0) {
    throw new Error('Ticket total must be a positive integer in millimes.');
  }
  if (totalPaidMillimes === 0) {
    return 'UNPAID';
  }

  if (totalPaidMillimes >= totalMillimes) {
    return 'PAID';
  }

  return 'PARTIALLY_PAID';
}

export function buildTicketFromDraft(
  customerId: string,
  draftItems: TicketDraftItem[],
  createdAt = new Date().toISOString(),
): Ticket {
  if (draftItems.length === 0) {
    throw new Error('A ticket must contain at least one line.');
  }

  const lines: TicketLine[] = draftItems.map((item, index) => ({
    id: createId(),
    ticketId: 'pending-ticket',
    productId: item.productId,
    productNameSnapshot: item.productName,
    barcodeSnapshot: item.barcode,
    unitPriceMillimes: item.unitPriceMillimes,
    quantity: item.quantity,
    lineTotalMillimes: calculateLineTotal(item.unitPriceMillimes, item.quantity),
  }));

  const totalMillimes = sumMillimeAmounts(lines.map((line) => line.lineTotalMillimes));
  if (!Number.isSafeInteger(totalMillimes) || totalMillimes <= 0) {
    throw new Error('Ticket total must be a positive safe integer in millimes.');
  }

  return {
    id: createId(),
    customerId: validateRequiredText(customerId, 'Ticket customer'),
    createdAt,
    updatedAt: createdAt,
    totalMillimes,
    status: 'UNPAID',
    voidedAt: null,
    lines,
  };
}

export function updateTicketFromDraft(
  existing: Ticket,
  customerId: string,
  draftItems: TicketDraftItem[],
): Ticket {
  const updated = buildTicketFromDraft(customerId, draftItems);
  return {
    ...updated,
    id: existing.id,
    createdAt: existing.createdAt,
    updatedAt: new Date().toISOString(),
    status: existing.status,
    voidedAt: existing.voidedAt,
  };
}

export type TicketFilter = 'ALL' | TicketStatus;

/** Ticket list filter (spec US7): by derived status; voided tickets never appear. */
export function filterTicketsByStatus(tickets: readonly Ticket[], filter: TicketFilter): Ticket[] {
  return tickets.filter((ticket) => !ticket.voidedAt && (filter === 'ALL' || ticket.status === filter));
}
