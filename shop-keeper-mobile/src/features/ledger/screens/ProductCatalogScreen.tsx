import { useMemo, useState } from 'react';
import { router } from 'expo-router';
import { View } from 'react-native';
import { useSelector } from 'react-redux';
import { RootState } from '@/store';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { getLedgerErrorMessage } from '@/i18n/errorMessages';
import { AmountText, EmptyState, Fab, Icon, InfoBanner, ListRow, ScreenScroll, SearchField, isolateLtr } from '@/components/ui';
import { listProducts } from '../services/productService';
import { Product } from '../types/product';

/** Product catalog (spec US7): search, rows that open the product form, no destructive action in rows. */
export function ProductCatalogScreen() {
  const { t } = useLedgerTranslations();
  const refreshVersion = useSelector((state: RootState) => state.ledger.refreshVersion);
  const [search, setSearch] = useState('');
  const result = useMemo(() => {
    try {
      return { products: listProducts(search), error: '', version: refreshVersion };
    } catch (cause) {
      return { products: [] as Product[], error: getLedgerErrorMessage(cause, t, t.loadFailed), version: refreshVersion };
    }
  }, [refreshVersion, search, t]);

  return (
    <View className="flex-1">
      <ScreenScroll>
        <SearchField value={search} onChangeText={setSearch} placeholder={t.productSearchPlaceholder} />
        {result.error ? <InfoBanner tone="error" title={result.error} /> : null}
        {result.products.length === 0 && !result.error ? (
          <EmptyState
            icon="cube-outline"
            message={search.trim() ? t.emptyProducts : t.noActiveProducts}
            actionLabel={search.trim() ? undefined : t.newProduct}
            onAction={() => router.push('/ledger/product-form')}
          />
        ) : null}
        {result.products.length > 0 ? (
          <View className="ui-card-flush">
            {result.products.map((product, index) => (
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
