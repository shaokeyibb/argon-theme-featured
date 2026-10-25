import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { gzipSync } from 'node:zlib';

const root = resolve(import.meta.dirname, '..');
export function assetMetrics() {
  const manifest = JSON.parse(readFileSync(resolve(root, 'assets/dist/manifest.json'), 'utf8'));
  const scripts = new Set(), css = new Set();
  function visit(key) {
    const chunk = manifest[key];
    if (chunk.file.endsWith('.js')) scripts.add(chunk.file);
    else if (chunk.file.endsWith('.css')) css.add(chunk.file);
    for (const file of chunk.css ?? []) css.add(file);
    for (const key of chunk.imports ?? []) visit(key);
  }
  visit('src/modern/index.js');
  visit('src/styles/icons.scss');
  const measure = paths => paths.reduce((sum, path) => {
    const bytes = readFileSync(resolve(root, path));
    return { bytes: sum.bytes + bytes.length, gzip: sum.gzip + gzipSync(bytes).length };
  }, { bytes: 0, gzip: 0 });
  return {
    frontendCSS: measure([...css].map(file => `assets/dist/${file}`)),
    frontendJS: measure([...scripts].map(file => `assets/dist/${file}`)),
    editorCSS: measure(['gutenberg/build/blocks.css']),
    editorTotalCSS: measure(['gutenberg/build/blocks.css', `assets/dist/${manifest['src/styles/icons.scss'].file}`])
  };
}
