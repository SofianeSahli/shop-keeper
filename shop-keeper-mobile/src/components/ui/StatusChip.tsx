import { Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { TicketStatus } from '@/features/ledger/types/ticket';

export type ChipStatus = TicketStatus | 'PAYMENT';

const CHIP: Record<ChipStatus, { container: string; text: string; labelKey: 'chipUnpaid' | 'chipPartial' | 'chipPaid' | 'payment' }> = {
  UNPAID: { container: 'bg-debt-soft', text: 'text-debt', labelKey: 'chipUnpaid' },
  PARTIALLY_PAID: { container: 'bg-partial-soft', text: 'text-partial', labelKey: 'chipPartial' },
  PAID: { container: 'bg-paid-soft', text: 'text-paid', labelKey: 'chipPaid' },
  PAYMENT: { container: 'bg-muted', text: 'text-ink-2', labelKey: 'payment' },
};

/** Status is always shown as text, never by color alone (spec US1-AS3). */
export function StatusChip({ status }: { status: ChipStatus }) {
  const { t } = useLedgerTranslations();
  const chip = CHIP[status];
  return (
    <View className={`self-start rounded-full px-2.5 py-0.5 ${chip.container}`}>
      <Text numberOfLines={1} className={`font-plex-semibold text-caption ${chip.text}`}>
        {t[chip.labelKey]}
      </Text>
    </View>
  );
}
