import { PropsWithChildren, ReactNode } from 'react';
import { KeyboardAvoidingView, RefreshControl, ScrollView } from 'react-native';
import { StickyFooter } from './StickyFooter';

interface ScreenScrollProps extends PropsWithChildren {
  /** Rendered in a fixed footer that stays above the keyboard. */
  footer?: ReactNode;
  footerWithSafeArea?: boolean;
  refreshing?: boolean;
  onRefresh?: () => void;
}

export function ScreenScroll({
  children,
  footer,
  footerWithSafeArea = false,
  refreshing = false,
  onRefresh,
}: ScreenScrollProps) {
  const scroll = (
    <ScrollView
      className="flex-1 bg-ground"
      contentContainerClassName="gap-3.5 p-4 pb-24"
      keyboardShouldPersistTaps="handled"
      refreshControl={onRefresh ? <RefreshControl refreshing={refreshing} onRefresh={onRefresh} /> : undefined}
    >
      {children}
    </ScrollView>
  );

  if (!footer) return scroll;

  return (
    <KeyboardAvoidingView behavior="padding" className="flex-1 bg-ground">
      {scroll}
      <StickyFooter withSafeArea={footerWithSafeArea}>{footer}</StickyFooter>
    </KeyboardAvoidingView>
  );
}
