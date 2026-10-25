import test from 'node:test';
import assert from 'node:assert/strict';
import { rgb2hsl, hsl2rgb, hex2rgb } from '../assets/src/modern/color.js';

test('the real color picker converts existing presets without leaking globals in ES modules', () => {
  for (const color of ['#5e72e4', '#fa7298', '#009688', '#607d8b', '#2196f3', '#3f51b5', '#ff9700', '#109d58', '#dc4437', '#673bb7', '#212121', '#795547']) {
    const original = hex2rgb(color);
    const hsl = rgb2hsl(original.R, original.G, original.B);
    const rgb = hsl2rgb(hsl.h, hsl.s, hsl.l);
    for (const key of ['R', 'G', 'B']) assert.equal(rgb[key], original[key], `${color}: ${key}`);
  }
  assert.equal(globalThis.del_R, undefined);
});
