import { useMemo, useState } from 'react';
import { FlatList, Modal, Pressable, ScrollView, Text, View } from 'react-native';
import { useLedgerTranslations } from '@/i18n/useTranslations';
import { Customer } from '../../types/customer';
import { LedgerField } from '../forms/LedgerField';
import { FeedbackState } from '../feedback/FeedbackState';
import { CustomerForm } from './CustomerForm';
import { useCustomers } from '../../hooks/UseCustomers';
import { DialogShell } from '../utils/DialogShell';

type CustomerSelectProps = {
    customers: Customer[];
    value: string;
    onChange: (customerId: string) => void;
};

export function CustomerSelect({ value, onChange }: CustomerSelectProps) {
    const { t } = useLedgerTranslations();
    const { customers, reload } = useCustomers();   // <- owned here now

    const [pickerOpen, setPickerOpen] = useState(false);
    const [createOpen, setCreateOpen] = useState(false);
    const [query, setQuery] = useState('');
    const selected = customers.find((c) => c.id === value);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return customers;
        return customers.filter((c) =>
            [c.displayLabel, c.realName, c.phoneNumber]
                .filter(Boolean)
                .some((field) => field!.toLowerCase().includes(q)),
        );
    }, [customers, query]);

    function closePicker() {
        setPickerOpen(false);
        setQuery('');
    }

    function select(id: string) {
        onChange(id);
        closePicker();
    }

    function openCreate() {
        closePicker();          // close the picker first: nested modals are flaky on iOS
        setCreateOpen(true);
    }

    function handleCreated(customer: Customer) {
        reload();
        onChange(customer.id);
        setCreateOpen(false);
    }

    return (
        <View className="gap-2">
            <Text className="font-semibold text-slate-800">{t.chooseCustomer}</Text>

            {/* The "select box" */}
            <Pressable
                accessibilityRole="button"
                className="ledger-touch-target rounded-lg border border-slate-300 bg-white p-3"
                onPress={() => setPickerOpen(true)}
            >
                <Text className={selected ? 'text-slate-900' : 'text-slate-400'}>
                    {selected?.displayLabel ?? t.chooseCustomer}
                </Text>
            </Pressable>

            {/* Picker dialog */}
            <DialogShell visible={pickerOpen} title={t.chooseCustomer} onClose={closePicker}>
                <View className="gap-3">
                    <LedgerField
                        label={t.search}
                        value={query}
                        onChangeText={setQuery}
                        autoCapitalize="none"
                    />

                    <FlatList
                        style={{ maxHeight: 280 }}
                        data={filtered}
                        keyExtractor={(c) => c.id}
                        keyboardShouldPersistTaps="handled"
                        ItemSeparatorComponent={() => <View className="h-2" />}
                        ListEmptyComponent={<FeedbackState kind="empty" message={t.emptyCustomers} />}
                        renderItem={({ item }) => (
                            <Pressable
                                accessibilityRole="radio"
                                accessibilityState={{ selected: item.id === value }}
                                className={`ledger-touch-target rounded-lg p-3 ${item.id === value ? 'bg-blue-100' : 'bg-slate-50'}`}
                                onPress={() => select(item.id)}
                            >
                                <Text className="text-slate-900">{item.displayLabel}</Text>
                            </Pressable>
                        )}
                    />

                    <Pressable
                        accessibilityRole="button"
                        className="ledger-touch-target rounded-xl border border-blue-300 px-4 py-3"
                        onPress={openCreate}
                    >
                        <Text className="text-center font-semibold text-blue-800">{t.newCustomer}</Text>
                    </Pressable>
                    <Pressable
                        accessibilityRole="button"
                        className="ledger-touch-target rounded-xl border border-slate-300 px-4 py-3"
                        onPress={closePicker}
                    >
                        <Text className="text-center font-semibold text-slate-700">{t.close}</Text>
                    </Pressable>
                </View>
            </DialogShell>

            {/* Create-customer dialog */}
            <DialogShell visible={createOpen} title={t.newCustomer} onClose={() => setCreateOpen(false)}>
                <ScrollView keyboardShouldPersistTaps="handled">
                    <CustomerForm embedded onSaved={handleCreated} />
                </ScrollView>
                <Pressable
                    accessibilityRole="button"
                    className="ledger-touch-target mt-3 rounded-xl border border-slate-300 px-4 py-3"
                    onPress={() => setCreateOpen(false)}
                >
                    <Text className="text-center font-semibold text-slate-700">{t.cancel}</Text>
                </Pressable>
            </DialogShell>
        </View>
    );
}