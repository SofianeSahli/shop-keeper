import type { Href } from 'expo-router';
import type { IconName } from '@/components/ui/Icon';
import type { getTranslations } from '@/i18n';

export type TranslationKey = keyof ReturnType<typeof getTranslations>;
export type { IconName };

export type NavItem = {
  id: string;
  route: Href;
  labelKey: TranslationKey;
  icon: IconName;
  activeIcon: IconName;
  pathFragments: readonly string[];
};

export type Feature = {
  id: string;
  labelKey: TranslationKey;
  route: Href;
  items: readonly NavItem[];
  /** Pathname fragments of form screens: tab bar hidden, back button shown. */
  formFragments: readonly string[];
};

/** Detail screens that keep the tab bar but show a back button. */
const DETAIL_FRAGMENTS = ['customer-ledger', 'balances', 'history'] as const;

const LEDGER: Feature = {
  id: 'ledger',
  labelKey: 'ledger',
  route: '/',
  formFragments: ['product-form', 'ticket-form', 'customer-form', 'payment-form', 'ticket-detail'],
  items: [
    { id: 'home', route: '/', labelKey: 'home', icon: 'home-outline', activeIcon: 'home', pathFragments: [] },
    { id: 'products', route: '/ledger/products', labelKey: 'products', icon: 'cube-outline', activeIcon: 'cube', pathFragments: ['product'] },
    { id: 'tickets', route: '/ledger/tickets', labelKey: 'tickets', icon: 'receipt-outline', activeIcon: 'receipt', pathFragments: ['ticket'] },
    { id: 'customers', route: '/ledger/customers', labelKey: 'customers', icon: 'people-outline', activeIcon: 'people', pathFragments: ['customer'] },
    { id: 'payments', route: '/ledger/payments', labelKey: 'payments', icon: 'wallet-outline', activeIcon: 'wallet', pathFragments: ['payment', 'balances', 'history'] },
  ],
};

export const FEATURES: readonly Feature[] = [LEDGER];

const HOME_PATHS = new Set(['/', '/ledger']);

const matchesItem = (item: NavItem, pathname: string) =>
  item.pathFragments.some((fragment) => pathname.includes(fragment));

function belongsTo(feature: Feature, pathname: string): boolean {
  return (
    feature.items.some((item) => item.route === pathname || matchesItem(item, pathname)) ||
    feature.formFragments.some((fragment) => pathname.includes(fragment))
  );
}

/** The feature owning a route, or `null` for routes outside every feature (the shell is then hidden). */
export function getActiveFeature(pathname: string): Feature | null {
  if (HOME_PATHS.has(pathname)) return FEATURES[0];
  return FEATURES.find((feature) => belongsTo(feature, pathname)) ?? null;
}

export function getActiveItem(feature: Feature, pathname: string): NavItem {
  return feature.items.find((item) => matchesItem(item, pathname)) ?? feature.items[0];
}

export function isFormRoute(feature: Feature, pathname: string): boolean {
  return feature.formFragments.some((fragment) => pathname.includes(fragment));
}

/** Screens below a tab's root: show a back button. */
export function isNestedRoute(feature: Feature, pathname: string): boolean {
  return isFormRoute(feature, pathname) || DETAIL_FRAGMENTS.some((fragment) => pathname.includes(fragment));
}
