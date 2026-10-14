import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { SupportedLanguage } from '@/i18n/types';

export type LedgerFormSection = 'home' | 'products' | 'tickets' | 'customers' | 'payments';

interface FormDirtyState {
  section: LedgerFormSection | null;
  isDirty: boolean;
}

interface LedgerState {
  refreshVersion: number;
  selectedLanguage: SupportedLanguage;
  formDirty: FormDirtyState;
}

const initialState: LedgerState = {
  refreshVersion: 0,
  selectedLanguage: 'fr',
  formDirty: { section: null, isDirty: false },
};

const ledgerSlice = createSlice({
  name: 'ledger',
  initialState,
  reducers: {
    refreshCompleted(state) {
      state.refreshVersion += 1;
    },
    languageChanged(state, action: PayloadAction<LedgerState['selectedLanguage']>) {
      state.selectedLanguage = action.payload;
    },
    setFormDirty(state, action: PayloadAction<FormDirtyState>) {
      state.formDirty = action.payload;
    },
    clearFormDirty(state) {
      state.formDirty = { section: null, isDirty: false };
    },
  },
});

export const { languageChanged, refreshCompleted, setFormDirty, clearFormDirty } = ledgerSlice.actions;
export const ledgerReducer = ledgerSlice.reducer;
