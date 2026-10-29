import { PropsWithChildren } from 'react';
import { KeyboardAvoidingView, Modal, Pressable, ScrollView, Text, View } from 'react-native';
import { getTextDirection } from '@/i18n';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { BusyButton } from './BusyButton';
import { Icon } from './Icon';

interface BottomSheetProps extends PropsWithChildren {
  visible: boolean;
  title: string;
  helper?: string;
  onClose: () => void;
  confirmLabel?: string;
  onConfirm?: () => void;
  confirmDisabled?: boolean;
  confirmBusy?: boolean;
}

export function BottomSheet({
  visible,
  title,
  helper,
  onClose,
  confirmLabel,
  onConfirm,
  confirmDisabled = false,
  confirmBusy = false,
  children,
}: BottomSheetProps) {
  const { t, language } = useLedgerTranslations();
  const direction = getTextDirection(language) === 'rtl' ? 'ledger-rtl' : 'ledger-ltr';

  return (
    <Modal
      transparent
      visible={visible}
      animationType="slide"
      statusBarTranslucent
      navigationBarTranslucent
      onRequestClose={onClose}
    >
      <KeyboardAvoidingView behavior="padding" className={`flex-1 justify-end ${direction}`}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={t.closeSheet}
          className="absolute inset-0 bg-scrim"
          onPress={onClose}
        />
        <View className="max-h-[85%] rounded-t-tile bg-surface pb-safe-offset-4">
          <View className="items-center pt-2.5">
            <View className="h-1 w-10 rounded-full bg-line-strong" />
          </View>
          <View className="flex-row items-start gap-2 px-4 pt-2">
            <View className="flex-1 gap-0.5 pt-2">
              <Text accessibilityRole="header" className="type-title">
                {title}
              </Text>
              {helper ? <Text className="type-caption">{helper}</Text> : null}
            </View>
            <Pressable accessibilityRole="button" accessibilityLabel={t.closeSheet} className="ui-icon-button" onPress={onClose}>
              <Icon name="close" className="text-ink-2" />
            </Pressable>
          </View>
          <ScrollView keyboardShouldPersistTaps="handled" contentContainerClassName="gap-3 px-4 py-3">
            {children}
          </ScrollView>
          {confirmLabel && onConfirm ? (
            <View className="px-4 pt-1">
              <BusyButton label={confirmLabel} busy={confirmBusy} disabled={confirmDisabled} onPress={onConfirm} />
            </View>
          ) : null}
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}
