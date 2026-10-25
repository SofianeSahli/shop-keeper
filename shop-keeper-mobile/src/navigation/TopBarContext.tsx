import { createContext, PropsWithChildren, useContext, useEffect, useMemo, useState } from 'react';
import type { IconName } from '@/components/ui/Icon';

export interface TopBarAction {
  icon: IconName;
  label: string;
  onPress: () => void;
}

export interface TopBarOptions {
  title?: string;
  action?: TopBarAction;
}

interface TopBarContextValue {
  options: TopBarOptions;
  setOptions: (options: TopBarOptions) => void;
}

const TopBarContext = createContext<TopBarContextValue>({ options: {}, setOptions: () => undefined });

export function TopBarProvider({ children }: PropsWithChildren) {
  const [options, setOptions] = useState<TopBarOptions>({});
  const value = useMemo(() => ({ options, setOptions }), [options]);
  return <TopBarContext.Provider value={value}>{children}</TopBarContext.Provider>;
}

export function useTopBarState(): TopBarOptions {
  return useContext(TopBarContext).options;
}

/** Lets a screen show its own title and one trailing action; cleared when the screen unmounts. */
export function useTopBarOptions({ title, action }: TopBarOptions): void {
  const { setOptions } = useContext(TopBarContext);
  const actionIcon = action?.icon;
  const actionLabel = action?.label;
  const onActionPress = action?.onPress;

  useEffect(() => {
    setOptions({
      title,
      action: actionIcon && actionLabel && onActionPress
        ? { icon: actionIcon, label: actionLabel, onPress: onActionPress }
        : undefined,
    });
  }, [actionIcon, actionLabel, onActionPress, setOptions, title]);

  useEffect(() => () => setOptions({}), [setOptions]);
}
