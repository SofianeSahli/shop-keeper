import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { SupportedLanguage } from '@/i18n/types';

interface LedgerState {
  refreshVersion: number;
  selectedLanguage: SupportedLanguage;
}

const initialState: LedgerState = {
  refreshVersion: 0,
  selectedLanguage: 'fr',
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
  },
});

export const { languageChanged, refreshCompleted } = ledgerSlice.actions;
export const ledgerReducer = ledgerSlice.reducer;
