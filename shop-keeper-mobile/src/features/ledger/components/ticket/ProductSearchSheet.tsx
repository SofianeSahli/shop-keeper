import { useMemo, useState } from 'react';
import { View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { AmountText, BottomSheet, EmptyState, Icon, ListRow, SearchField, isolateLtr } from '@/components/ui';
import { Product } from '../../types/product';

interface ProductSearchSheetProps {
  visible: boolean;
  products: readonly Product[];
  onSelect: (product: Product) => void;
  onClose: () => void;
  /** Opens the inline product creation when nothing matches. */
  onCreateMissing: () => void;
}

export function ProductSearchSheet({ visible, products, onSelect, onClose, onCreateMissing }: ProductSearchSheetProps) {
  const { t } = useLedgerTranslations();
  const [query, setQuery] = useState('');

  const filtered = useMemo(() => {
    const search = query.trim().toLocaleLowerCase();
    if (!search) return products;
    return products.filter(
      (product) => product.name.toLocaleLowerCase().includes(search) || (product.barcode ?? '').includes(search),
    );
  }, [products, query]);

  function close(): void {
    setQuery('');
    onClose();
  }

  return (
    <BottomSheet visible={visible} title={t.searchAction} onClose={close}>
      <SearchField value={query} onChangeText={setQuery} placeholder={t.productSearchPlaceholder} />
      {filtered.length === 0 ? (
        <EmptyState
          icon="cube-outline"
          message={products.length === 0 ? t.noActiveProducts : t.emptyProducts}
          actionLabel={t.missingProduct}
          onAction={() => {
            setQuery('');
            onCreateMissing();
          }}
        />
      ) : (
        <View className="ui-card-flush">
          {filtered.map((product, index) => (
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
              onPress={() => {
                setQuery('');
                onSelect(product);
              }}
            />
          ))}
        </View>
      )}
    </BottomSheet>
  );
}
