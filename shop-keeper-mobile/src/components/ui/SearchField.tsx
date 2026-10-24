import { Pressable, TextInput, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { Icon } from './Icon';

interface SearchFieldProps {
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  autoFocus?: boolean;
}

export function SearchField({ value, onChangeText, placeholder, autoFocus = false }: SearchFieldProps) {
  const { t } = useLedgerTranslations();
  return (
    <View className="min-h-[50px] flex-row items-center gap-2 rounded-control border border-line-strong bg-surface ps-3">
      <Icon name="search" size={20} className="text-ink-2" />
      <TextInput
        accessibilityLabel={placeholder}
        value={value}
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderClassName="text-ink-2"
        autoFocus={autoFocus}
        autoCorrect={false}
        returnKeyType="search"
        className="min-h-[48px] flex-1 font-plex text-body text-ink"
      />
      {value ? (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={t.clearSearch}
          className="ui-icon-button"
          onPress={() => onChangeText('')}
        >
          <Icon name="close-circle" size={20} className="text-ink-2" />
        </Pressable>
      ) : null}
    </View>
  );
}
