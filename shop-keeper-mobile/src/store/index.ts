import { configureStore } from '@reduxjs/toolkit';
import { ledgerReducer } from '@/features/ledger/store/ledgerSlice';

export const store = configureStore({
  reducer: {
    ledger: ledgerReducer,
  },
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
