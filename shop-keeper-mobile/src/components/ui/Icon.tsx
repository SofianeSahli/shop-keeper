import { ComponentProps } from 'react';
import { Ionicons } from '@expo/vector-icons';
import { getTextDirection } from '@/i18n';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import './interop';

export type IconName = ComponentProps<typeof Ionicons>['name'];

type InteropIoniconsProps = ComponentProps<typeof Ionicons> & { className?: string };
const InteropIonicons = Ionicons as unknown as (props: InteropIoniconsProps) => React.JSX.Element;

interface IconProps {
  name: IconName;
  /** Token color class, e.g. `text-primary`. */
  className?: string;
  size?: number;
  /** Mirrors back/forward arrows and chevrons in right-to-left languages. */
  directional?: boolean;
}

function mirrorName(name: IconName): IconName {
  if (name.includes('back')) return name.replace('back', 'forward') as IconName;
  if (name.includes('forward')) return name.replace('forward', 'back') as IconName;
  return name;
}

export function Icon({ name, className = 'text-ink', size = 22, directional = false }: IconProps) {
  const { language } = useLedgerTranslations();
  const isRtl = getTextDirection(language) === 'rtl';
  return (
    <InteropIonicons
      name={directional && isRtl ? mirrorName(name) : name}
      size={size}
      className={className}
      accessibilityElementsHidden
      importantForAccessibility="no"
    />
  );
}
