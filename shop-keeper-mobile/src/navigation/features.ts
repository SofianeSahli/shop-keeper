import { ComponentProps } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { Href } from 'expo-router';
import { useLedgerTranslations } from '@/i18n/useTranslations';

export type TranslationKey = keyof ReturnType<typeof useLedgerTranslations>['t'];
export type IconName = ComponentProps<typeof Ionicons>['name'];

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

const LEDGER: Feature = {
  id: 'ledger',
  labelKey: 'ledger',
  route: '/',
  formFragments: ['product-form', 'ticket-form', 'customer-form', 'payment-form', 'ticket-detail'],
  items: [
    { id: 'home', route: '/', labelKey: 'home', icon: 'home-outline', activeIcon: 'home', pathFragments: [] },
    { id: 'products', route: 'ledger/products', labelKey: 'products', icon: 'cube-outline', activeIcon: 'cube', pathFragments: ['product'] },
    { id: 'tickets', route: 'ledger/tickets', labelKey: 'tickets', icon: 'receipt-outline', activeIcon: 'receipt', pathFragments: ['ticket'] },
    { id: 'customers', route: 'ledger/customers', labelKey: 'customers', icon: 'people-outline', activeIcon: 'people', pathFragments: ['customer'] },
    { id: 'payments', route: 'ledger/payments', labelKey: 'payments', icon: 'wallet-outline', activeIcon: 'wallet', pathFragments: ['payment', 'balances', 'history'] },
  ],
};

export const FEATURES: readonly Feature[] = [LEDGER];

const matchesItem = (item: NavItem, pathname: string) =>
  item.pathFragments.some((f) => pathname.includes(f));

export function getActiveFeature(pathname: string): Feature {
  return FEATURES.find((feature) => feature.items.some((i) => matchesItem(i, pathname))) ?? FEATURES[0];
}

export function getActiveItem(feature: Feature, pathname: string): NavItem {
  return feature.items.find((i) => matchesItem(i, pathname)) ?? feature.items[0];
}

export function isFormRoute(feature: Feature, pathname: string): boolean {
  return feature.formFragments.some((f) => pathname.includes(f));
}