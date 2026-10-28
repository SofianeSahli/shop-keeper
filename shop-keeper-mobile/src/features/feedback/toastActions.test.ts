import { describe, expect, it, vi } from 'vitest';
import { registerToastAction, releaseToastAction, runToastAction } from './toastActions';

describe('toast action registry', () => {
  it('runs a registered callback by id', () => {
    const retry = vi.fn();
    const actionId = registerToastAction(retry);
    runToastAction(actionId);
    expect(retry).toHaveBeenCalledOnce();
  });

  it('forgets a callback once released', () => {
    const retry = vi.fn();
    const actionId = registerToastAction(retry);
    releaseToastAction(actionId);
    runToastAction(actionId);
    expect(retry).not.toHaveBeenCalled();
  });

  it('ignores unknown ids', () => {
    expect(() => runToastAction('missing')).not.toThrow();
    expect(() => releaseToastAction(null)).not.toThrow();
  });
});
