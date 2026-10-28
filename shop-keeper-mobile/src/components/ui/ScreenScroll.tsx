import { PropsWithChildren, ReactNode, RefObject } from 'react';
import { KeyboardAvoidingView, RefreshControl, ScrollView, ScrollViewInstance } from 'react-native';
import { StickyFooter } from './StickyFooter';

interface ScreenScrollProps extends PropsWithChildren {
  footer?: ReactNode;
  footerWithSafeArea?: boolean;
  refreshing?: boolean;
  onRefresh?: () => void;
  scrollRef?: RefObject<ScrollViewInstance | null>;
}

export function ScreenScroll({
  children,
  footer,
  footerWithSafeArea = false,
  refreshing = false,
  onRefresh,
  scrollRef,
}: ScreenScrollProps) {
  const scroll = (
    <ScrollView
      ref={scrollRef}
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
