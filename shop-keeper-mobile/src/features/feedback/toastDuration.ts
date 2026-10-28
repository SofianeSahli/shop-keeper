import { ToastKind } from './store/feedbackSlice';

const SUCCESS_TOAST_MS = 3000;
const INFO_TOAST_MS = 4000;

export function toastDurationMs(kind: ToastKind): number | null {
  if (kind === 'success') return SUCCESS_TOAST_MS;
  if (kind === 'info') return INFO_TOAST_MS;
  return null;
}
