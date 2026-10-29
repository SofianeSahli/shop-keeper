type ToastActionCallback = () => void;

const callbacks = new Map<string, ToastActionCallback>();
let lastActionNumber = 0;

export function registerToastAction(callback: ToastActionCallback): string {
  lastActionNumber += 1;
  const actionId = `toast-action-${lastActionNumber}`;
  callbacks.set(actionId, callback);
  return actionId;
}

export function runToastAction(actionId: string): void {
  callbacks.get(actionId)?.();
}

export function releaseToastAction(actionId: string | null): void {
  if (actionId) callbacks.delete(actionId);
}
