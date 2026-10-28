import { configureStore } from '@reduxjs/toolkit';
import { feedbackReducer } from '@/features/feedback/store/feedbackSlice';
import { ledgerReducer } from '@/features/ledger/store/ledgerSlice';

export const store = configureStore({
  reducer: {
    ledger: ledgerReducer,
    feedback: feedbackReducer,
  },
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
