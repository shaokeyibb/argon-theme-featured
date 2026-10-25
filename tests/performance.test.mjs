import test from 'node:test';
import assert from 'node:assert/strict';
import { assetMetrics } from '../scripts/asset-metrics.mjs';

test('initial theme assets stay within the measured CSS and JavaScript budgets', () => {
  const metrics = assetMetrics();
  // Count all initial styles (including the separate icon sheet) and shared JS.
  // Lazy features such as math/highlighting keep their independent bundles.
  for (const [key, limit] of Object.entries({
    frontendCSS: { bytes: 440000, gzip: 82000 },
    frontendJS: { bytes: 130000, gzip: 42000 },
    editorCSS: { bytes: 18000, gzip: 4000 },
    editorTotalCSS: { bytes: 125000, gzip: 31000 }
  })) {
    for (const type of ['bytes', 'gzip']) assert(metrics[key][type] <= limit[type], `${key} ${type}: ${metrics[key][type]} > ${limit[type]}`);
  }
  console.log(JSON.stringify(metrics));
});
