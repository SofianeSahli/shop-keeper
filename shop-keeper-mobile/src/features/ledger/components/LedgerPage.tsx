import { PropsWithChildren } from 'react';
import { ScrollView, Text, View } from 'react-native';

interface LedgerPageProps extends PropsWithChildren {
  title: string;
  subtitle?: string;
}

export function LedgerPage({ title, subtitle, children }: LedgerPageProps) {
  return (
    <ScrollView className="flex-1 bg-slate-100" contentContainerClassName="gap-5 p-5">
      <View className="gap-1">
        <Text className="text-2xl font-bold text-slate-950">{title}</Text>
        {subtitle ? <Text className="text-slate-600">{subtitle}</Text> : null}
      </View>
      {children}
    </ScrollView>
  );
}
