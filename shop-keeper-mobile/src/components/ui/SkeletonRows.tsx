import { View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { useReduceMotion } from './useReduceMotion';

export type SkeletonVariant = 'avatar' | 'tile' | 'stat';

interface SkeletonRowsProps {
  count?: number;
  variant?: SkeletonVariant;
}

function AvatarRow({ pulse }: { pulse: string }) {
  return (
    <View className={`min-h-row flex-row items-center gap-3 px-4 py-3 ${pulse}`}>
      <View className="h-10 w-10 rounded-full bg-skeleton" />
      <View className="flex-1 gap-2">
        <View className="h-3.5 w-2/3 rounded-full bg-skeleton" />
        <View className="h-3 w-1/3 rounded-full bg-muted" />
      </View>
      <View className="h-4 w-16 rounded-full bg-skeleton" />
    </View>
  );
}

function TileRow({ pulse }: { pulse: string }) {
  return (
    <View className={`min-h-row flex-row items-center gap-3 px-4 py-3 ${pulse}`}>
      <View className="h-10 w-10 rounded-control bg-skeleton" />
      <View className="flex-1 gap-2">
        <View className="h-3.5 w-1/2 rounded-full bg-skeleton" />
        <View className="h-3 w-1/4 rounded-full bg-muted" />
      </View>
    </View>
  );
}

function StatCardPlaceholder({ pulse }: { pulse: string }) {
  return (
    <View className={`ui-card flex-1 ${pulse}`}>
      <View className="h-3 w-2/3 rounded-full bg-muted" />
      <View className="h-7 w-full rounded-full bg-skeleton" />
      <View className="h-3 w-1/2 rounded-full bg-muted" />
    </View>
  );
}

export function SkeletonRows({ count = 3, variant = 'avatar' }: SkeletonRowsProps) {
  const { t } = useLedgerTranslations();
  const reduceMotion = useReduceMotion();
  const pulse = reduceMotion ? '' : 'animate-pulse';
  const indexes = Array.from({ length: count }, (_, index) => index);

  if (variant === 'stat') {
    return (
      <View accessible accessibilityRole="progressbar" accessibilityLabel={t.loading} className="flex-row gap-3">
        {indexes.map((index) => (
          <StatCardPlaceholder key={index} pulse={pulse} />
        ))}
      </View>
    );
  }

  const Row = variant === 'tile' ? TileRow : AvatarRow;
  return (
    <View accessible accessibilityRole="progressbar" accessibilityLabel={t.loading} className="ui-card-flush">
      {indexes.map((index) => (
        <View key={index} className={index > 0 ? 'border-t border-line-soft' : ''}>
          <Row pulse={pulse} />
        </View>
      ))}
    </View>
  );
}
