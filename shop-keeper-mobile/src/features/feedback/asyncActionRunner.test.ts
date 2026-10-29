import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { UnknownAction } from '@reduxjs/toolkit';
import { AsyncActionSpec, createAsyncActionRunner, OutcomeWording, RunOptions } from './asyncActionRunner';
import { asyncActionFinished, asyncActionStarted, loaderHidden, loaderShown, loaderTurnedSlow, Toast, toastQueued } from './store/feedbackSlice';
import { runToastAction } from './toastActions';

const wording: OutcomeWording = {
  notSavedTitle: (thing) => `${thing} non enregistré`,
  explainFailure: (cause) => (cause instanceof Error && cause.message === 'duplicate' ? 'Ce code existe déjà.' : null),
  retryLabel: 'Réessayer',
  viewLabel: 'Voir',
};

function after(milliseconds: number, value = 'saved'): () => Promise<string> {
  return () => new Promise((resolve) => setTimeout(() => resolve(value), milliseconds));
}

function failingAfter(milliseconds: number, message = 'disk full'): () => Promise<string> {
  return () => new Promise((_, reject) => setTimeout(() => reject(new Error(message)), milliseconds));
}

describe('async action runner', () => {
  let actions: UnknownAction[];
  let runner: ReturnType<typeof createAsyncActionRunner>;
  const retry = vi.fn();
  const options: RunOptions = { wording, retry };

  const spec = (work: () => Promise<string>, extra: Partial<AsyncActionSpec<string>> = {}): AsyncActionSpec<string> => ({
    work,
    loaderLabel: 'Enregistrement du ticket…',
    loaderDetail: '4 articles · 13,150 DT',
    successToast: { title: 'Ticket enregistré', detail: '13,150 DT encaissés' },
    failureThing: 'Ticket',
    keepValuesMessage: 'Vos saisies sont gardées.',
    ...extra,
  });
  const types = () => actions.map((action) => action.type);
  const toasts = () => actions.filter(toastQueued.match).map((action) => action.payload as Toast);

  beforeEach(() => {
    vi.useFakeTimers();
    actions = [];
    retry.mockReset();
    runner = createAsyncActionRunner((action) => {
      actions.push(action);
      return action;
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('never shows the loader for work under 300 ms and returns its result', async () => {
    const result = runner.run(spec(after(200)), options);
    await vi.advanceTimersByTimeAsync(200);
    await expect(result).resolves.toBe('saved');
    expect(types()).not.toContain(loaderShown.type);
    expect(toasts()).toEqual([expect.objectContaining({ kind: 'success', title: 'Ticket enregistré' })]);
  });

  it('shows the loader at 300 ms and keeps it at least 500 ms', async () => {
    const result = runner.run(spec(after(400)), options);
    await vi.advanceTimersByTimeAsync(300);
    expect(actions[1]).toEqual(loaderShown({ label: 'Enregistrement du ticket…', detail: '4 articles · 13,150 DT', shownAt: Date.now() }));

    await vi.advanceTimersByTimeAsync(100);
    expect(types()).not.toContain(loaderHidden.type);
    await vi.advanceTimersByTimeAsync(399);
    expect(types()).not.toContain(loaderHidden.type);
    await vi.advanceTimersByTimeAsync(1);
    await result;
    expect(types()).toEqual([
      asyncActionStarted.type,
      loaderShown.type,
      loaderHidden.type,
      asyncActionFinished.type,
      toastQueued.type,
    ]);
  });

  it('turns the loader slow 2 s after it shows', async () => {
    const result = runner.run(spec(after(5000)), options);
    await vi.advanceTimersByTimeAsync(2299);
    expect(types()).not.toContain(loaderTurnedSlow.type);
    await vi.advanceTimersByTimeAsync(1);
    expect(types()).toContain(loaderTurnedSlow.type);
    await vi.advanceTimersByTimeAsync(2700);
    await result;
  });

  it('refuses a second run while one is running and runs the work once', async () => {
    const work = vi.fn(after(100));
    const first = runner.run(spec(work), options);
    expect(runner.isRunning()).toBe(true);
    await expect(runner.run(spec(work), options)).resolves.toBeUndefined();
    await vi.advanceTimersByTimeAsync(100);
    await first;
    expect(work).toHaveBeenCalledOnce();
    expect(runner.isRunning()).toBe(false);
  });

  it('calls onSuccess before the success toast and offers "Voir" when given', async () => {
    const onSuccess = vi.fn(() => expect(toasts()).toEqual([]));
    const view = vi.fn();
    const result = runner.run(spec(after(10), { onSuccess, successToast: { title: 'Ticket enregistré', view } }), options);
    await vi.advanceTimersByTimeAsync(10);
    await result;
    expect(onSuccess).toHaveBeenCalledWith('saved');

    const [toast] = toasts();
    expect(toast).toMatchObject({ actionLabel: 'Voir', dedupeKey: 'Ticket enregistré' });
    runToastAction(toast.actionId!);
    expect(view).toHaveBeenCalledOnce();
  });

  it('closes the loader, then shows an error toast with "Réessayer" that calls the retry handler', async () => {
    const onErrorToast = vi.fn();
    const onSuccess = vi.fn();
    const result = runner.run(spec(failingAfter(600), { onSuccess }), { ...options, onErrorToast });
    await vi.advanceTimersByTimeAsync(800);
    await expect(result).resolves.toBeUndefined();

    expect(types()).toEqual([
      asyncActionStarted.type,
      loaderShown.type,
      loaderHidden.type,
      asyncActionFinished.type,
      toastQueued.type,
    ]);
    const [toast] = toasts();
    expect(toast).toMatchObject({
      kind: 'error',
      title: 'Ticket non enregistré',
      detail: 'Vos saisies sont gardées.',
      actionLabel: 'Réessayer',
    });
    expect(onSuccess).not.toHaveBeenCalled();
    expect(onErrorToast).toHaveBeenCalledWith(toast.id);

    runToastAction(toast.actionId!);
    expect(retry).toHaveBeenCalledOnce();
  });

  it('tells the caller about the failure so a form inside a sheet can show it', async () => {
    const onFailure = vi.fn();
    const result = runner.run(spec(failingAfter(10, 'duplicate'), { onFailure }), options);
    await vi.advanceTimersByTimeAsync(10);
    await result;
    expect(onFailure).toHaveBeenCalledWith(new Error('duplicate'));
  });

  it('puts a known cause before the keep-values message', async () => {
    const result = runner.run(spec(failingAfter(10, 'duplicate')), options);
    await vi.advanceTimersByTimeAsync(10);
    await result;
    expect(toasts()[0].detail).toBe('Ce code existe déjà. Vos saisies sont gardées.');
  });

  it('accepts a new run after a failure', async () => {
    const failed = runner.run(spec(failingAfter(10)), options);
    await vi.advanceTimersByTimeAsync(10);
    await failed;
    const second = runner.run(spec(after(10, 'second')), options);
    await vi.advanceTimersByTimeAsync(10);
    await expect(second).resolves.toBe('second');
  });

  describe('form validation stays in the form', () => {
    it('does not start, show the loader or toast when the form fields are invalid', async () => {
      const work = vi.fn(after(10));
      const onStarted = vi.fn();
      const result = runner.run(spec(work, { checkFields: () => false }), { ...options, onStarted });
      await vi.advanceTimersByTimeAsync(1000);
      await expect(result).resolves.toBeUndefined();
      expect(work).not.toHaveBeenCalled();
      expect(onStarted).not.toHaveBeenCalled();
      expect(actions).toEqual([]);
      expect(runner.isRunning()).toBe(false);
    });

    it('starts normally when the form fields are valid', async () => {
      const onStarted = vi.fn();
      const result = runner.run(spec(after(10), { checkFields: () => true }), { ...options, onStarted });
      await vi.advanceTimersByTimeAsync(10);
      await expect(result).resolves.toBe('saved');
      expect(onStarted).toHaveBeenCalledOnce();
    });

    it('hands a field validation failure to the form instead of a toast', async () => {
      const onFailure = vi.fn();
      const showFieldError = vi.fn(() => true);
      const result = runner.run(spec(failingAfter(10, 'Product name is required.'), { showFieldError, onFailure }), options);
      await vi.advanceTimersByTimeAsync(10);
      await result;
      expect(showFieldError).toHaveBeenCalledWith(new Error('Product name is required.'));
      expect(toasts()).toEqual([]);
      expect(onFailure).not.toHaveBeenCalled();
    });

    it('still shows the error toast for failures no field explains', async () => {
      const showFieldError = vi.fn(() => false);
      const result = runner.run(spec(failingAfter(10), { showFieldError }), options);
      await vi.advanceTimersByTimeAsync(10);
      await result;
      expect(toasts()).toEqual([expect.objectContaining({ kind: 'error', title: 'Ticket non enregistré' })]);
    });
  });
});
