import { readdirSync, lstatSync, readFileSync, existsSync } from 'node:fs';
import { join, resolve, relative } from 'node:path';
export const root = resolve(import.meta.dirname, '..');
export function releaseFiles() {
  const files = [];
  function walk(path) {
    const stat = lstatSync(path);
    if (stat.isSymbolicLink()) throw new Error(`Release must not contain symlinks: ${path}`);
    if (stat.isDirectory()) for (const name of readdirSync(path).sort()) walk(join(path,name));
    else files.push(relative(root,path).replaceAll('\\','/'));
  }
  for (const name of readdirSync(root).sort()) {
    if (/\.(php|css)$/.test(name) || ['theme.json','screenshot.png','LICENSE','README.md','THIRD_PARTY_NOTICES.md'].includes(name)) walk(join(root,name));
  }
  for (const name of ['inc','template-parts','languages','stickers','assets/dist','assets/html-document','assets/tinymce_assets','gutenberg/build','gutenberg/metadata','licenses','docs']) {
    if (existsSync(join(root,name))) walk(join(root,name));
  }
  files.push('assets/editor.css');
  return [...new Set(files)].sort();
}
export function verifyRelease(files = releaseFiles()) {
  const manifest = JSON.parse(readFileSync(join(root,'assets/dist/manifest.json'),'utf8'));
  for (const source of ['src/modern/index.js','src/modern/admin.js']) if (!manifest[source]) throw new Error(`Missing entry ${source}; run npm run build`);
  for (const entry of Object.values(manifest)) {
    for (const name of [entry.file, ...(entry.css || []), ...(entry.assets || [])]) {
      if (!files.includes(`assets/dist/${name}`)) throw new Error(`Unpackaged build asset: ${name}`);
    }
  }
  for (const file of ['style.css','index.php','functions.php','theme.json','gutenberg/build/blocks.js','gutenberg/build/blocks.asset.php','gutenberg/build/blocks.css']) {
    if (!files.includes(file) || !existsSync(join(root,file))) throw new Error(`Missing runtime file: ${file}`);
  }
  const version = JSON.parse(readFileSync(join(root,'package.json'),'utf8')).version;
  if (!new RegExp(`Version:\\s*${version.replaceAll('.', '\\.')}\\s`).test(readFileSync(join(root,'style.css'),'utf8'))) throw new Error('Theme/package versions differ');
  return {version, files};
}
