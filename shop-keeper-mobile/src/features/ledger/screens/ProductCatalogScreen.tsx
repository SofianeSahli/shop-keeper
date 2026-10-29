import { useCallback, useState } from 'react';
import { router } from 'expo-router';
import { View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useLedgerQuery } from '@/features/feedback/useLedgerQuery';
import {
  AmountText,
  EmptyState,
  Fab,
  Icon,
  InfoBanner,
  ListRow,
  RefreshingPill,
  ScreenScroll,
  SearchField,
  SkeletonRows,
  isolateLtr,
} from '@/components/ui';
import { listProducts } from '../services/productService';

export function ProductCatalogScreen() {
  const { t } = useLedgerTranslations();
  const [search, setSearch] = useState('');
  const loadProducts = useCallback(() => listProducts(search), [search]);
  const query = useLedgerQuery(loadProducts, [search]);
  const products = query.data ?? [];

  return (
    <View className="flex-1">
      <ScreenScroll>
        <SearchField value={search} onChangeText={setSearch} placeholder={t.productSearchPlaceholder} />
        <View className="items-end">
          <RefreshingPill visible={query.status === 'refreshing'} />
        </View>
        {query.status === 'error' ? (
          <InfoBanner tone="error" title={query.error} actionLabel={t.retry} onAction={query.retry} />
        ) : null}
        {query.status === 'loading' ? <SkeletonRows variant="tile" count={5} /> : null}
        {products.length === 0 && query.status === 'ready' ? (
          <EmptyState
            icon="cube-outline"
            message={search.trim() ? t.emptyProducts : t.noActiveProducts}
            actionLabel={search.trim() ? undefined : t.newProduct}
            onAction={() => router.push('/ledger/product-form')}
          />
        ) : null}
        {products.length > 0 ? (
          <View className="ui-card-flush">
            {products.map((product, index) => (
              <ListRow
                key={product.id}
                separated={index > 0}
                leading={
                  <View className="h-10 w-10 items-center justify-center rounded-control bg-primary-soft">
                    <Icon name="cube-outline" size={20} className="text-primary" />
                  </View>
                }
                title={product.name}
                meta={product.barcode ? isolateLtr(product.barcode) : t.noCode}
                trailing={<AmountText millimes={product.sellingPriceMillimes} />}
                chevron
                onPress={() => router.push({ pathname: '/ledger/product-form', params: { id: product.id } })}
              />
            ))}
          </View>
        ) : null}
      </ScreenScroll>
      <Fab label={t.newProduct} onPress={() => router.push('/ledger/product-form')} />
    </View>
  );
}
