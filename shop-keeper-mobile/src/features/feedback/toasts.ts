import type { Dispatch, UnknownAction } from '@reduxjs/toolkit';
import { pendingToastResolved, Toast, toastDismissed, toastQueued, ToastKind } from './store/feedbackSlice';
import { registerToastAction, releaseToastAction } from './toastActions';

export interface ToastActionRequest {
  label: string;
  onPress: () => void;
}

export interface ToastRequest {
  kind: Exclude<ToastKind, 'pending'>;
  title: string;
  detail?: string | null;
  action?: ToastActionRequest;
  dedupeKey?: string;
}

export type FeedbackDispatch = Dispatch<UnknownAction>;

let lastToastNumber = 0;

function nextToastId(): string {
  lastToastNumber += 1;
  return `toast-${lastToastNumber}`;
}

function registerAction(action: ToastActionRequest | undefined) {
  return {
    actionId: action ? registerToastAction(action.onPress) : null,
    actionLabel: action?.label ?? null,
  };
}

export function showToast(dispatch: FeedbackDispatch, request: ToastRequest): string {
  const toast: Toast = {
    id: nextToastId(),
    kind: request.kind,
    title: request.title,
    detail: request.detail ?? null,
    dedupeKey: request.dedupeKey ?? null,
    ...registerAction(request.action),
  };
  dispatch(toastQueued(toast));
  return toast.id;
}

export function showPendingToast(dispatch: FeedbackDispatch, request: { title: string; detail?: string }): string {
  const toast: Toast = {
    id: nextToastId(),
    kind: 'pending',
    title: request.title,
    detail: request.detail ?? null,
    actionId: null,
    actionLabel: null,
    dedupeKey: null,
  };
  dispatch(toastQueued(toast));
  return toast.id;
}

export function resolvePendingToast(dispatch: FeedbackDispatch, id: string, outcome: ToastRequest): void {
  dispatch(
    pendingToastResolved({
      id,
      outcome: { kind: outcome.kind, title: outcome.title, detail: outcome.detail ?? null, ...registerAction(outcome.action) },
    }),
  );
}

export function dismissToast(dispatch: FeedbackDispatch, toast: Pick<Toast, 'id' | 'actionId'>): void {
  releaseToastAction(toast.actionId);
  dispatch(toastDismissed(toast.id));
}
