/** How a new ticket was paid, chosen on the ticket form (spec 003, US8). Form state, never stored. */
export type SalePaymentChoice =
  | { kind: 'PAID' }
  | { kind: 'PARTIAL'; amountMillimes: number | null }
  | { kind: 'UNPAID' };

export type SalePaymentKind = SalePaymentChoice['kind'];
