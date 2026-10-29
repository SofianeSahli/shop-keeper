import { describe, expect, it } from 'vitest';
import {
  asyncActionFinished,
  asyncActionStarted,
  feedbackReducer,
  FeedbackState,
  loaderHidden,
  loaderShown,
  loaderTurnedSlow,
  pendingToastResolved,
  Toast,
  toastDismissed,
  toastQueued,
} from './feedbackSlice';

const toast = (overrides: Partial<Toast>): Toast => ({
  id: 'toast-1',
  kind: 'success',
  title: 'Ticket enregistré',
  detail: null,
  actionId: null,
  actionLabel: null,
  dedupeKey: null,
  ...overrides,
});

const empty: FeedbackState = { toasts: [], loader: null, actionRunning: false };

describe('feedback slice', () => {
  it('queues toasts in order with the head visible', () => {
    let state = feedbackReducer(empty, toastQueued(toast({ id: 'a' })));
    state = feedbackReducer(state, toastQueued(toast({ id: 'b', kind: 'error' })));
    expect(state.toasts.map((item) => item.id)).toEqual(['a', 'b']);

    state = feedbackReducer(state, toastDismissed('a'));
    expect(state.toasts.map((item) => item.id)).toEqual(['b']);
  });

  it('replaces a visible success with the same key instead of queueing', () => {
    let state = feedbackReducer(empty, toastQueued(toast({ id: 'a', dedupeKey: 'Produit enregistré' })));
    state = feedbackReducer(state, toastQueued(toast({ id: 'b', dedupeKey: 'Produit enregistré' })));
    expect(state.toasts.map((item) => item.id)).toEqual(['b']);

    state = feedbackReducer(state, toastQueued(toast({ id: 'c', dedupeKey: 'Client enregistré' })));
    expect(state.toasts.map((item) => item.id)).toEqual(['b', 'c']);
  });

  it('never replaces an error toast', () => {
    let state = feedbackReducer(empty, toastQueued(toast({ id: 'a', kind: 'error', dedupeKey: 'x' })));
    state = feedbackReducer(state, toastQueued(toast({ id: 'b', dedupeKey: 'x' })));
    expect(state.toasts.map((item) => item.id)).toEqual(['a', 'b']);
  });

  it('turns a pending toast into its outcome in place, keeping id and position', () => {
    let state = feedbackReducer(empty, toastQueued(toast({ id: 'p', kind: 'pending', title: 'Sauvegarde…' })));
    state = feedbackReducer(state, toastQueued(toast({ id: 'q' })));
    state = feedbackReducer(
      state,
      pendingToastResolved({
        id: 'p',
        outcome: { kind: 'success', title: 'Sauvegarde terminée', detail: null, actionId: null, actionLabel: null },
      }),
    );
    expect(state.toasts[0]).toMatchObject({ id: 'p', kind: 'success', title: 'Sauvegarde terminée' });
    expect(state.toasts).toHaveLength(2);
  });

  it('tracks whether a write is running', () => {
    const running = feedbackReducer(empty, asyncActionStarted());
    expect(running.actionRunning).toBe(true);
    expect(feedbackReducer(running, asyncActionFinished()).actionRunning).toBe(false);
  });

  it('shows, slows and hides the single loader', () => {
    let state = feedbackReducer(empty, loaderShown({ label: 'Enregistrement du ticket…', detail: null, shownAt: 300 }));
    expect(state.loader).toEqual({ label: 'Enregistrement du ticket…', detail: null, shownAt: 300, slow: false });
    state = feedbackReducer(state, loaderTurnedSlow());
    expect(state.loader?.slow).toBe(true);
    state = feedbackReducer(state, loaderHidden());
    expect(state.loader).toBeNull();
  });
});
