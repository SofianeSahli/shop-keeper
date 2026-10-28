import { createSlice, PayloadAction } from '@reduxjs/toolkit';

export type ToastKind = 'success' | 'info' | 'error' | 'pending';

export interface Toast {
  id: string;
  kind: ToastKind;
  title: string;
  detail: string | null;
  actionId: string | null;
  actionLabel: string | null;
  dedupeKey: string | null;
}

export interface LoaderState {
  label: string;
  detail: string | null;
  shownAt: number;
  slow: boolean;
}

export interface FeedbackState {
  toasts: Toast[];
  loader: LoaderState | null;
  actionRunning: boolean;
}

export type PendingOutcome = Pick<Toast, 'title' | 'detail' | 'actionId' | 'actionLabel'> & {
  kind: Exclude<ToastKind, 'pending'>;
};

const initialState: FeedbackState = { toasts: [], loader: null, actionRunning: false };

function replacesVisibleToast(visible: Toast | undefined, incoming: Toast): boolean {
  return (
    visible !== undefined &&
    visible.kind === 'success' &&
    incoming.kind === 'success' &&
    incoming.dedupeKey !== null &&
    visible.dedupeKey === incoming.dedupeKey
  );
}

const feedbackSlice = createSlice({
  name: 'feedback',
  initialState,
  reducers: {
    toastQueued(state, action: PayloadAction<Toast>) {
      if (replacesVisibleToast(state.toasts[0], action.payload)) {
        state.toasts[0] = action.payload;
        return;
      }
      state.toasts.push(action.payload);
    },
    toastDismissed(state, action: PayloadAction<string>) {
      state.toasts = state.toasts.filter((toast) => toast.id !== action.payload);
    },
    pendingToastResolved(state, action: PayloadAction<{ id: string; outcome: PendingOutcome }>) {
      const toast = state.toasts.find((candidate) => candidate.id === action.payload.id);
      if (toast?.kind !== 'pending') return;
      Object.assign(toast, action.payload.outcome);
    },
    asyncActionStarted(state) {
      state.actionRunning = true;
    },
    asyncActionFinished(state) {
      state.actionRunning = false;
    },
    loaderShown(state, action: PayloadAction<{ label: string; detail: string | null; shownAt: number }>) {
      state.loader = { ...action.payload, slow: false };
    },
    loaderTurnedSlow(state) {
      if (state.loader) state.loader.slow = true;
    },
    loaderHidden(state) {
      state.loader = null;
    },
  },
});

export const {
  toastQueued,
  toastDismissed,
  pendingToastResolved,
  asyncActionStarted,
  asyncActionFinished,
  loaderShown,
  loaderTurnedSlow,
  loaderHidden,
} = feedbackSlice.actions;
export const feedbackReducer = feedbackSlice.reducer;
