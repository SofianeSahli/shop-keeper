import { describe, expect, it, vi } from 'vitest';
import { runQueued } from './queue';

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}

describe('runQueued', () => {
  it('runs tasks one after another in call order', async () => {
    const order: string[] = [];
    const first = deferred<void>();
    const firstDone = runQueued(async () => {
      order.push('first:start');
      await first.promise;
      order.push('first:end');
    });
    const secondDone = runQueued(async () => {
      order.push('second');
    });

    await Promise.resolve();
    await Promise.resolve();
    expect(order).toEqual(['first:start']);

    first.resolve();
    await Promise.all([firstDone, secondDone]);
    expect(order).toEqual(['first:start', 'first:end', 'second']);
  });

  it('passes a rejection to its caller and keeps running the next tasks', async () => {
    const failing = runQueued(async () => {
      throw new Error('boom');
    });
    const next = runQueued(async () => 'next');

    await expect(failing).rejects.toThrow('boom');
    await expect(next).resolves.toBe('next');
  });

  it('waits the configured delay before a task', async () => {
    vi.useFakeTimers();
    try {
      const task = vi.fn(async () => 'done');
      const result = runQueued(task, 1200);

      await vi.advanceTimersByTimeAsync(1199);
      expect(task).not.toHaveBeenCalled();
      await vi.advanceTimersByTimeAsync(1);
      await expect(result).resolves.toBe('done');
    } finally {
      vi.useRealTimers();
    }
  });

  it('adds no delay by default outside development builds', async () => {
    vi.stubEnv('EXPO_PUBLIC_LEDGER_DELAY_MS', '5000');
    try {
      await expect(runQueued(async () => 'fast')).resolves.toBe('fast');
    } finally {
      vi.unstubAllEnvs();
    }
  });
});
