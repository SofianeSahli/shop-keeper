import { asyncActionFinished, asyncActionStarted, loaderHidden, loaderShown, loaderTurnedSlow } from './store/feedbackSlice';
import { outcomeDelayMs, SHOW_AFTER_MS, SLOW_AFTER_MS } from './loaderTimeline';
import { FeedbackDispatch, showToast } from './toasts';

export interface SuccessToastSpec {
  title: string;
  detail?: string | null;
  view?: () => void;
}

export interface AsyncActionSpec<T> {
  work: () => Promise<T>;
  loaderLabel: string;
  loaderDetail?: string | null;
  successToast?: SuccessToastSpec;
  onSuccess?: (result: T) => void;
  onFailure?: (cause: unknown) => void;
  checkFields?: () => boolean;
  showFieldError?: (cause: unknown) => boolean;
  failureThing: string;
  keepValuesMessage: string;
}

export interface OutcomeWording {
  notSavedTitle: (thing: string) => string;
  explainFailure: (cause: unknown) => string | null;
  retryLabel: string;
  viewLabel: string;
}

export interface RunOptions {
  wording: OutcomeWording;
  retry: () => void;
  onErrorToast?: (toastId: string) => void;
  onStarted?: () => void;
}

function wait(milliseconds: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

type WorkOutcome<T> = { succeeded: true; value: T } | { succeeded: false; cause: unknown };

async function settle<T>(work: () => Promise<T>): Promise<WorkOutcome<T>> {
  try {
    return { succeeded: true, value: await work() };
  } catch (cause) {
    return { succeeded: false, cause };
  }
}

export function createAsyncActionRunner(dispatch: FeedbackDispatch) {
  let running = false;

  function startLoaderTimeline(spec: AsyncActionSpec<unknown>) {
    let shownAt: number | null = null;
    let slowTimer: ReturnType<typeof setTimeout> | undefined;
    const showTimer = setTimeout(() => {
      shownAt = Date.now();
      dispatch(loaderShown({ label: spec.loaderLabel, detail: spec.loaderDetail ?? null, shownAt }));
      slowTimer = setTimeout(() => dispatch(loaderTurnedSlow()), SLOW_AFTER_MS);
    }, SHOW_AFTER_MS);

    return async function finish() {
      clearTimeout(showTimer);
      const delay = outcomeDelayMs(Date.now(), shownAt);
      if (delay > 0) await wait(delay);
      clearTimeout(slowTimer);
      if (shownAt !== null) dispatch(loaderHidden());
    };
  }

  function showSuccess(spec: AsyncActionSpec<unknown>, wording: OutcomeWording) {
    const toast = spec.successToast;
    if (!toast) return;
    showToast(dispatch, {
      kind: 'success',
      title: toast.title,
      detail: toast.detail,
      dedupeKey: toast.title,
      action: toast.view ? { label: wording.viewLabel, onPress: toast.view } : undefined,
    });
  }

  function showFailure(spec: AsyncActionSpec<unknown>, cause: unknown, options: RunOptions) {
    const { wording } = options;
    const explanation = wording.explainFailure(cause);
    const toastId = showToast(dispatch, {
      kind: 'error',
      title: wording.notSavedTitle(spec.failureThing),
      detail: explanation ? `${explanation} ${spec.keepValuesMessage}` : spec.keepValuesMessage,
      action: { label: wording.retryLabel, onPress: options.retry },
    });
    options.onErrorToast?.(toastId);
  }

  return {
    isRunning: () => running,

    async run<T>(spec: AsyncActionSpec<T>, options: RunOptions): Promise<T | undefined> {
      if (running) return undefined;
      if (spec.checkFields && !spec.checkFields()) return undefined;
      running = true;
      options.onStarted?.();
      dispatch(asyncActionStarted());
      const finishLoader = startLoaderTimeline(spec as AsyncActionSpec<unknown>);
      const outcome = await settle(spec.work);
      await finishLoader();
      running = false;
      dispatch(asyncActionFinished());

      if (!outcome.succeeded) {
        if (spec.showFieldError?.(outcome.cause)) return undefined;
        spec.onFailure?.(outcome.cause);
        showFailure(spec as AsyncActionSpec<unknown>, outcome.cause, options);
        return undefined;
      }
      spec.onSuccess?.(outcome.value);
      showSuccess(spec as AsyncActionSpec<unknown>, options.wording);
      return outcome.value;
    },
  };
}

export type AsyncActionRunner = ReturnType<typeof createAsyncActionRunner>;
