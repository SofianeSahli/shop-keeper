import { beforeEach, describe, expect, it, vi } from 'vitest';
import { getTextDirection, getTranslations } from '@/i18n';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { calculateLineTotal, calculateTicketTotal } from './ticketDraftService';
import { deriveTicketStatus, validateTicketPayment } from './paymentService';
import { buildTicketFromDraft, getTicketStatus, updateTicketFromDraft } from './ticketService';
import { Ticket } from '../types/ticket';

vi.mock('@/lib/id', () => ({ createId: vi.fn(() => 'generated-id') }));

const ticket: Ticket = {
  id: 'ticket-1',
  customerId: 'customer-1',
  createdAt: '2025-01-01T00:00:00.000Z',
  updatedAt: '2025-01-01T00:00:00.000Z',
  totalMillimes: 5000,
  status: 'UNPAID',
  voidedAt: null,
  lines: [],
};

describe('ledger domain calculations', () => {
  beforeEach(() => vi.clearAllMocks());

  it('calculates ticket line and aggregate totals using integer millimes', () => {
    expect(calculateLineTotal(1250, 2)).toBe(2500);
    expect(calculateTicketTotal([
      { id: 'line-1', productId: 'product-1', productName: 'Tea', barcode: null, unitPriceMillimes: 1250, quantity: 2 },
      { id: 'line-2', productId: 'product-2', productName: 'Sugar', barcode: null, unitPriceMillimes: 2500, quantity: 1 },
    ])).toBe(5000);
    expect(() => calculateLineTotal(1250, 0)).toThrow(/quantity/);
    expect(() => calculateLineTotal(Number.MAX_SAFE_INTEGER, 2)).toThrow(/integer range/);
  });

  it('derives unpaid, partial and paid states and refuses invalid payment amounts', () => {
    expect(getTicketStatus(0, 5000)).toBe('UNPAID');
    expect(getTicketStatus(2500, 5000)).toBe('PARTIALLY_PAID');
    expect(deriveTicketStatus(ticket, 5000)).toBe('PAID');
    expect(validateTicketPayment(ticket, 2500, 2000)).toBeNull();
    expect(validateTicketPayment(ticket, 3001, 2000)).toMatch(/exceeds/);
    expect(validateTicketPayment(ticket, 0)).toMatch(/positive integer/);
    expect(() => getTicketStatus(-1, 5000)).toThrow(/non-negative/);
  });

  it('creates and corrects ticket snapshots while retaining the original ticket identity', () => {
    const draft = [{
      id: 'draft-line',
      productId: 'product-1',
      productName: 'Tea',
      barcode: '123',
      unitPriceMillimes: 1250,
      quantity: 2,
    }];
    const created = buildTicketFromDraft('customer-1', draft, '2025-01-01T00:00:00.000Z');
    const updated = updateTicketFromDraft(created, 'customer-2', [{
      ...draft[0],
      productName: 'Green Tea',
      unitPriceMillimes: 1500,
    }]);

    expect(created.totalMillimes).toBe(2500);
    expect(created.lines[0].productNameSnapshot).toBe('Tea');
    expect(updated.id).toBe(created.id);
    expect(updated.createdAt).toBe(created.createdAt);
    expect(updated.customerId).toBe('customer-2');
    expect(updated.totalMillimes).toBe(3000);
    expect(updated.lines[0].productNameSnapshot).toBe('Green Tea');
  });
});

describe('localization helpers', () => {
  it('selects layout direction and maps known service errors to translations', () => {
    expect(getTextDirection('ar')).toBe('rtl');
    expect(getTextDirection('fr')).toBe('ltr');
    expect(getLedgerErrorMessage(
      new Error('Payment exceeds the outstanding ticket balance.'),
      getTranslations('fr'),
    )).toBe(getTranslations('fr').paymentExceedsBalance);
    expect(getLedgerErrorMessage(
      new Error('SQLite driver detail'),
      getTranslations('ar'),
    )).toBe(getTranslations('ar').operationFailed);
  });
});
