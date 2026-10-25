const LEFT_TO_RIGHT_ISOLATE = '⁦';
const POP_DIRECTIONAL_ISOLATE = '⁩';

/**
 * Wraps text in a Unicode left-to-right isolate so digit groups, amounts and barcodes keep
 * their order inside a right-to-left paragraph ("1 284 500" must not read "500 284 1").
 */
export function isolateLtr(text: string): string {
  return `${LEFT_TO_RIGHT_ISOLATE}${text}${POP_DIRECTIONAL_ISOLATE}`;
}
