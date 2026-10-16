import { useEffect, useState } from "react";
import { clearFormDirty, refreshCompleted, setFormDirty } from "../../store/ledgerSlice";
import { useLedgerTranslations } from "@/i18n/useTranslations";
import { useDispatch } from "react-redux";
import { createCustomer, getCustomer, updateCustomer } from "../../services/customerService";
import { getLedgerErrorMessage } from "@/i18n/errorMessages";
import { router } from "expo-router";
import { LedgerPage } from "../LedgerPage";
import { LedgerField } from "../forms/LedgerField";
import { Pressable } from "react-native";
import { FeedbackState } from "../feedback/FeedbackState";
import { Customer } from "../../types/customer";
import { View,Text } from "react-native"; 

type CustomerFormProps = {
    id?: string;
    embedded?: boolean;                    
    onSaved?: (customer: Customer) => void;
};

export function CustomerForm({ id, embedded = false, onSaved }: CustomerFormProps) {
    const { t, language } = useLedgerTranslations();
    const dispatch = useDispatch();
    const [initial] = useState(() => {
        try {
            const customer = id ? getCustomer(id) : null;
            return { customer, error: id && !customer ? t.loadFailed : '' };
        } catch (cause) {
            return { customer: null, error: getLedgerErrorMessage(cause, t, t.loadFailed) };
        }
    });
    const [displayLabel, setDisplayLabel] = useState(initial.customer?.displayLabel ?? '');
    const [realName, setRealName] = useState(initial.customer?.realName ?? '');
    const [phoneNumber, setPhoneNumber] = useState(initial.customer?.phoneNumber ?? '');
    const [error, setError] = useState(initial.error);

    useEffect(() => {
        if (embedded)
            return;
        const hasChanges =
            displayLabel !== (initial.customer?.displayLabel ?? '') ||
            realName !== (initial.customer?.realName ?? '') ||
            phoneNumber !== (initial.customer?.phoneNumber ?? '');
        dispatch(setFormDirty({ section: 'customers', isDirty: hasChanges && !error }));
    }, [dispatch, displayLabel, error, initial.customer?.displayLabel, initial.customer?.phoneNumber, initial.customer?.realName, phoneNumber, realName]);

    useEffect(() => {
        if (embedded)
            return;
        return () => {
            dispatch(clearFormDirty());
        };
    }, [dispatch]);

    function save(): void {
        try {
            const input = { displayLabel, realName: realName || null, phoneNumber: phoneNumber || null };
            if (id) updateCustomer(id, input);
            else {
                const newRow = createCustomer(input);
                onSaved?.(newRow);
            }
            dispatch(refreshCompleted());
            if (!embedded) {
                dispatch(clearFormDirty());
                router.replace('/ledger/customers');
            }
        } catch (cause) {
            setError(getLedgerErrorMessage(cause, t, t.saveFailed));
        }
    }

    const body = (
        <>
            {error ? <FeedbackState kind="error" message={error} /> : null}
            <LedgerField
                label={t.displayLabel}
                value={displayLabel}
                onChangeText={setDisplayLabel}
                autoCapitalize="words"
                textAlign={language === 'ar' ? 'right' : 'left'}
            />
            <LedgerField
                label={t.realName}
                value={realName}
                onChangeText={setRealName}
                autoCapitalize="words"
                textAlign={language === 'ar' ? 'right' : 'left'}
            />
            <LedgerField
                label={t.phoneNumber}
                value={phoneNumber}
                onChangeText={setPhoneNumber}
                keyboardType="phone-pad"
                textAlign="left"
            />
            <Pressable accessibilityRole="button" className="ledger-primary-button" onPress={save}>
                <Text className="font-semibold text-white">{t.save}</Text>
            </Pressable>
        </>
    );

    return embedded ? <View className="gap-3">{body}</View> : <LedgerPage title={id ? t.editCustomer : t.newCustomer}>
        {body}
    </LedgerPage>
}
