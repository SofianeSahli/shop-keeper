import { ReactNode } from 'react';
import { Pressable, Text, View } from 'react-native';
import { Icon } from './Icon';

interface ListRowProps {
  leading?: ReactNode;
  title: string;
  meta?: string;
  /** Extra content under the title, e.g. a status chip. */
  detail?: ReactNode;
  trailing?: ReactNode;
  trailingCaption?: string;
  chevron?: boolean;
  selected?: boolean;
  /** Draws the separator above the row; the first row of a card has none. */
  separated?: boolean;
  onPress?: () => void;
  accessibilityLabel?: string;
  accessibilityRole?: 'button' | 'radio';
}

export function ListRow({
  leading,
  title,
  meta,
  detail,
  trailing,
  trailingCaption,
  chevron = false,
  selected = false,
  separated = false,
  onPress,
  accessibilityLabel,
  accessibilityRole = 'button',
}: ListRowProps) {
  const className = `min-h-row flex-row items-center gap-3 px-4 py-3 ${separated ? 'border-t border-line-soft' : ''} ${
    selected ? 'bg-primary-faint' : 'bg-surface'
  }`;
  const content = (
    <>
      {leading}
      <View className="flex-1 gap-0.5">
        <Text numberOfLines={1} className="type-body-strong">
          {title}
        </Text>
        {meta ? (
          <Text numberOfLines={1} className="type-caption">
            {meta}
          </Text>
        ) : null}
        {detail}
      </View>
      {trailing || trailingCaption ? (
        <View className="items-end gap-0.5">
          {trailing}
          {trailingCaption ? (
            <Text numberOfLines={1} className="type-caption">
              {trailingCaption}
            </Text>
          ) : null}
        </View>
      ) : null}
      {chevron ? <Icon name="chevron-forward" directional size={18} className="text-ink-2" /> : null}
    </>
  );

  if (!onPress) {
    return (
      <View accessible accessibilityLabel={accessibilityLabel} className={className}>
        {content}
      </View>
    );
  }
  return (
    <Pressable
      accessibilityRole={accessibilityRole}
      accessibilityLabel={accessibilityLabel}
      accessibilityState={accessibilityRole === 'radio' ? { selected, checked: selected } : { selected }}
      className={`${className} active:bg-muted`}
      onPress={onPress}
    >
      {content}
    </Pressable>
  );
}
