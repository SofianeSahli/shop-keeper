import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

type Channels = [number, number, number];
type SchemeTokens = Map<string, Channels>;

const TOKENS_CSS = readFileSync(new URL('./tokens.css', import.meta.url), 'utf8');
const DARK_SCHEME_MARKER = '@media (prefers-color-scheme: dark)';

const LIGHT_VALUES_BEFORE_DARK_MODE: Record<string, string> = {
  ground: '#F3F5F4',
  surface: '#FFFFFF',
  ink: '#0F1F1A',
  'ink-2': '#4A5A54',
  line: '#DCE3E0',
  'line-soft': '#EDF1EF',
  'line-strong': '#C9D3CF',
  muted: '#EEF1F0',
  primary: '#164E9C',
  'primary-soft': '#DCE8F7',
  'primary-faint': '#EEF4FC',
  'primary-line': '#B7CDEB',
  debt: '#9A3412',
  'debt-strong': '#7C2D12',
  'debt-soft': '#FDEBDD',
  'debt-line': '#F6CFAE',
  paid: '#0F5F57',
  'paid-soft': '#DDF3EE',
  'paid-line': '#A9DCD2',
  partial: '#854D0E',
  'partial-soft': '#FEF3C7',
  'partial-line': '#F3D27A',
  'warn-strong': '#713F12',
  error: '#A3221A',
  'error-soft': '#FDE8E6',
  'error-line': '#F4BDB7',
  'error-strong': '#6B1F18',
  skeleton: '#E6EBE9',
  scrim: '#0F1F1A',
  viewfinder: '#0B1411',
  'scan-line': '#F59E0B',
};

const SPLIT_ROLES_SAME_AS_LIGHT_SOURCE: Record<string, string> = {
  'surface-2': 'surface',
  'line-dashed': 'line-strong',
  'avatar-ink': 'ink-2',
  elevation: 'ink',
  'on-fill': 'surface',
  'primary-fill': 'primary',
  'paid-fill': 'paid',
  'debt-fill': 'debt',
  'error-fill': 'error',
  'warn-fill': 'warn-strong',
  'chip-selected': 'ink',
  'on-chip-selected': 'surface',
  'theme-icon': 'ink',
  'viewfinder-mark': 'surface',
};

function parseScheme(cssBlock: string): SchemeTokens {
  const tokens: SchemeTokens = new Map();
  for (const [, role, red, green, blue] of cssBlock.matchAll(/--color-([a-z0-9-]+):\s*(\d+)\s+(\d+)\s+(\d+);/g)) {
    tokens.set(role, [Number(red), Number(green), Number(blue)]);
  }
  return tokens;
}

function hexToChannels(hex: string): Channels {
  const digits = hex.slice(1);
  return [0, 2, 4].map((start) => parseInt(digits.slice(start, start + 2), 16)) as Channels;
}

function relativeLuminance(channels: Channels): number {
  const [red, green, blue] = channels.map((channel) => {
    const ratio = channel / 255;
    return ratio <= 0.03928 ? ratio / 12.92 : ((ratio + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function contrastRatio(foreground: Channels, background: Channels): number {
  const [lighter, darker] = [relativeLuminance(foreground), relativeLuminance(background)].sort((a, b) => b - a);
  return (lighter + 0.05) / (darker + 0.05);
}

const [lightBlock, darkBlock] = TOKENS_CSS.split(DARK_SCHEME_MARKER);
const light = parseScheme(lightBlock);
const dark = parseScheme(darkBlock);

function darkRole(role: string): Channels {
  const channels = dark.get(role);
  if (!channels) throw new Error(`No dark value for ${role}`);
  return channels;
}

describe('tokens.css', () => {
  it('gives every role a light and a dark value', () => {
    expect(light.size).toBeGreaterThan(0);
    expect([...dark.keys()].sort()).toEqual([...light.keys()].sort());
  });

  it('keeps the light theme exactly as before dark mode', () => {
    for (const [role, hex] of Object.entries(LIGHT_VALUES_BEFORE_DARK_MODE)) {
      expect(light.get(role), role).toEqual(hexToChannels(hex));
    }
    for (const [role, sourceRole] of Object.entries(SPLIT_ROLES_SAME_AS_LIGHT_SOURCE)) {
      expect(light.get(role), role).toEqual(light.get(sourceRole));
    }
  });

  it('keeps the scrim opacity of the light theme', () => {
    expect(lightBlock).toContain('--color-scrim-alpha: 0.45;');
  });

  it.each([
    ['ink', 'surface', 12],
    ['ink-2', 'surface', 7],
    ['on-fill', 'primary-fill', 4.5],
    ['on-fill', 'paid-fill', 4.5],
    ['on-fill', 'warn-fill', 4.5],
    ['on-fill', 'debt-fill', 4.5],
    ['on-fill', 'error-fill', 3],
    ['on-chip-selected', 'chip-selected', 4.5],
    ['debt', 'surface', 4.5],
    ['paid', 'surface', 4.5],
    ['partial', 'surface', 4.5],
    ['error', 'surface', 4.5],
    ['primary', 'surface', 4.5],
  ])('dark %s on %s reaches %d:1', (foreground, background, minimumRatio) => {
    expect(contrastRatio(darkRole(foreground), darkRole(background))).toBeGreaterThanOrEqual(minimumRatio);
  });
});
