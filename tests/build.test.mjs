import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { root, verifyRelease, releaseFiles } from '../scripts/release-files.mjs';
test('release contains every manifest resource and excludes development dependencies', () => {
  const {files} = verifyRelease();
  assert(files.length > 50);
  assert(!files.some(file => /(^|\/)(node_modules|vendor|tests|temp|src|theme-update-checker)\//.test(file)));
  assert(files.includes('inc/lib/parsedown.php'));
});
test('every local CSS URL in the deployable assets resolves', () => {
  for (const file of releaseFiles().filter(name => /^(assets\/dist|gutenberg\/build)\/.+\.css$/.test(name))) {
    const css = readFileSync(join(root,file),'utf8');
    for (const match of css.matchAll(/url\(\s*['"]?([^'"\s)]+)['"]?\s*\)/g)) {
      if (/^(data:|https?:|#)/.test(match[1])) continue;
      assert(existsSync(resolve(dirname(join(root,file)),match[1].split(/[?#]/)[0])), `${file}: ${match[1]}`);
    }
  }
});
test('block metadata uses API 3 and actual WordPress JSON types', () => {
  const directories = readdirSync(join(root,'gutenberg/metadata'));
  assert.equal(directories.length,8);
  for (const directory of directories) {
    const block = JSON.parse(readFileSync(join(root,'gutenberg/metadata',directory,'block.json'),'utf8'));
    assert.equal(block.apiVersion,3);
    for (const schema of Object.values(block.attributes)) assert(['string','boolean','number','integer','object','array','null'].includes(schema.type), `${block.name}: ${schema.type}`);
  }
});
test('production bundle cannot accidentally restore retired runtimes', () => {
  const manifest=JSON.parse(readFileSync(join(root,'assets/dist/manifest.json'),'utf8'));
  const entry=readFileSync(join(root,'assets/dist',manifest['src/modern/index.js'].file),'utf8');
  for (const pattern of [/jQuery\.fn/, /pjax:start/, /Zoomify\.prototype/, /MathJax\.Hub/, /window\._?\$\s*=/]) assert(!pattern.test(entry), String(pattern));
  assert(!existsSync(join(root,'assets/vendor/jquery')));
  assert(!existsSync(join(root,'assets/src/styles/argon-compat.css')));
  const css = manifest['src/modern/index.js'].css.map(file => readFileSync(join(root, 'assets/dist', file), 'utf8')).join('\n');
  for (const obsolete of ['.noUi-target', '.datepicker-dropdown', 'Bootstrap v4', '.hljs-ln-numbers']) assert(!css.includes(obsolete), obsolete);
});
test('bundled fonts are referenced by a deployable stylesheet', () => {
  const files = releaseFiles(), referenced = new Set();
  for (const file of files.filter(name => name.endsWith('.css'))) {
    const css = readFileSync(join(root, file), 'utf8');
    for (const match of css.matchAll(/url\(\s*['"]?([^'"\s)]+)['"]?\s*\)/g)) {
      if (!/^(data:|https?:|#)/.test(match[1])) referenced.add(resolve(root, dirname(file), match[1].split(/[?#]/)[0]));
    }
  }
  for (const font of files.filter(name => /\.(?:woff2?|ttf|eot)$/.test(name))) assert(referenced.has(resolve(root, font)), `Orphan font: ${font}`);
});

test('lazy chunks never import a page entry that cache plugins can version independently', () => {
  const manifest = JSON.parse(readFileSync(join(root, 'assets/dist/manifest.json'), 'utf8'));
  const entries = Object.entries(manifest).filter(([, chunk]) => chunk.isEntry).map(([key]) => key);
  for (const [name, chunk] of Object.entries(manifest)) {
    for (const dependency of chunk.imports ?? []) assert(!entries.includes(dependency), `${name} imports entry ${dependency}`);
  }
});
