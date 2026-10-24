import { PropsWithChildren } from 'react';
import { View } from 'react-native';

interface StickyFooterProps extends PropsWithChildren {
  /** Adds the bottom safe-area inset; use on screens without the tab bar. */
  withSafeArea?: boolean;
}

export function StickyFooter({ withSafeArea = false, children }: StickyFooterProps) {
  return (
    <View
      className={`gap-3 border-t border-line bg-surface px-4 pt-3 ${withSafeArea ? 'pb-safe-offset-4' : 'pb-4'}`}
    >
      {children}
    </View>
  );
}
