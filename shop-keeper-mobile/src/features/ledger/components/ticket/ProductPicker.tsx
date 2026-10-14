import { Pressable, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { Product } from '../../types/product';
import { formatMillimeAmount } from '@/lib/money';

interface ProductPickerProps {
  products: Product[];
  onSelect: (product: Product) => void;
}

export function ProductPicker({ products, onSelect }: ProductPickerProps) {
  const { language } = useLedgerTranslations();
  return (
    <View className="flex-row flex-wrap gap-2">
      {products.map((product) => (
        <Pressable
          key={product.id}
          accessibilityRole="button"
          className="ledger-touch-target rounded-lg bg-white px-3 py-2"
          onPress={() => onSelect(product)}
        >
          <Text className="font-medium text-slate-800">{product.name}</Text>
          <Text className="text-xs text-slate-600">{formatMillimeAmount(product.sellingPriceMillimes, language)}</Text>
        </Pressable>
      ))}
    </View>
  );
}
