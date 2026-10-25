export type MessageParams = Record<string, string | number>;

/** Replaces `{name}` placeholders; unknown placeholders stay visible so a missing param is noticed. */
export function formatMessage(template: string, params: MessageParams = {}): string {
  return template.replace(/\{(\w+)\}/g, (placeholder, name: string) =>
    Object.prototype.hasOwnProperty.call(params, name) ? String(params[name]) : placeholder,
  );
}

export function getPlaceholders(template: string): string[] {
  return [...new Set([...template.matchAll(/\{(\w+)\}/g)].map((match) => match[1]))].sort();
}
