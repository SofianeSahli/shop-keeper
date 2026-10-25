export const LEFT_TO_RIGHT_ISOLATE = '⁦';
export const POP_DIRECTIONAL_ISOLATE = '⁩';

/**
 * Wraps text in a Unicode left-to-right isolate so digit groups, amounts and barcodes keep
 * their order inside a right-to-left paragraph ("1 284 500" must not read "500 284 1").
 */
export function isolateLtr(text: string): string {
  return `${LEFT_TO_RIGHT_ISOLATE}${text}${POP_DIRECTIONAL_ISOLATE}`;
}

/**
 * Text runs for an amount drawn in two sizes (large dinars, small millimes). One isolate spans the
 * whole number across both runs: isolating each run separately lets a right-to-left paragraph swap
 * them ("8,600" read as ",6008"). The currency stays outside, so Arabic shows "8,600 د.ت".
 */
export function splitAmountRuns(
  parts: { sign: string; dinars: string; millimes: string },
  currencySuffix: string,
): { major: string; minor: string } {
  return {
    major: `${LEFT_TO_RIGHT_ISOLATE}${parts.sign}${parts.dinars}`,
    minor: `${parts.millimes}${POP_DIRECTIONAL_ISOLATE}${currencySuffix}`,
  };
}
