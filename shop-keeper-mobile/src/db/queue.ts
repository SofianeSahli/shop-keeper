let queueTail: Promise<unknown> = Promise.resolve();

function readDevDelayMs(): number {
  const isDevBuild = typeof __DEV__ !== 'undefined' && __DEV__ === true;
  if (!isDevBuild) return 0;
  const configured = Number(process.env.EXPO_PUBLIC_LEDGER_DELAY_MS);
  return Number.isSafeInteger(configured) && configured > 0 ? configured : 0;
}

function wait(milliseconds: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

export function runQueued<T>(task: () => Promise<T>, delayMs: number = readDevDelayMs()): Promise<T> {
  const run = async () => {
    if (delayMs > 0) await wait(delayMs);
    return task();
  };
  const result = queueTail.then(run, run);
  queueTail = result.catch(() => undefined);
  return result;
}
