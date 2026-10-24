import { existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { FEATURES, getActiveFeature, getActiveItem, isFormRoute, isNestedRoute } from './features';

const appDir = fileURLToPath(new URL('../app', import.meta.url));

function routeFile(route: string): string {
  return route === '/' ? `${appDir}/index.tsx` : `${appDir}${route}.tsx`;
}

describe('feature registry', () => {
  it('points every tab at an existing route file', () => {
    for (const feature of FEATURES) {
      for (const item of feature.items) {
        expect(typeof item.route).toBe('string');
        expect(String(item.route).startsWith('/')).toBe(true);
        expect(existsSync(routeFile(String(item.route))), String(item.route)).toBe(true);
      }
    }
  });

  it('returns no feature for routes outside the registry', () => {
    expect(getActiveFeature('/+not-found')).toBeNull();
    expect(getActiveFeature('/unknown')).toBeNull();
  });

  it('matches home, tabs, forms and details', () => {
    const ledger = getActiveFeature('/');
    expect(ledger?.id).toBe('ledger');
    if (!ledger) return;
    expect(getActiveItem(ledger, '/').id).toBe('home');
    expect(getActiveItem(ledger, '/ledger/ticket-form').id).toBe('tickets');
    expect(getActiveItem(ledger, '/ledger/customer-ledger').id).toBe('customers');
    expect(getActiveItem(ledger, '/ledger/balances').id).toBe('payments');
    expect(isFormRoute(ledger, '/ledger/ticket-form')).toBe(true);
    expect(isFormRoute(ledger, '/ledger/customer-ledger')).toBe(false);
    expect(isNestedRoute(ledger, '/ledger/customer-ledger')).toBe(true);
    expect(isNestedRoute(ledger, '/ledger/customers')).toBe(false);
  });
});
