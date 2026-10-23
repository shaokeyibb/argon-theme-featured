import { readFileSync, writeFileSync, copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join, dirname, resolve } from 'node:path';
const require = createRequire(import.meta.url), root = resolve(import.meta.dirname,'..');
mkdirSync(join(root,'licenses'),{recursive:true});
const packages = ['bootstrap','@popperjs/core','@fortawesome/fontawesome-free','photoswipe','highlight.js','katex','pangu','qrcode','is-whitespace-character'];
const notices = ['# Third-party notices', '', 'Runtime assets are bundled locally. Source/build tools and their dependency graph are recorded in package-lock.json.', '', '| Package | Bundled version | License |', '| --- | --- | --- |'];
for (const name of packages) {
  let dir;
  try { dir=dirname(require.resolve(`${name}/package.json`)); }
  catch { dir=dirname(require.resolve(name)); while(!existsSync(join(dir,'package.json'))) dir=dirname(dir); }
  const pkg=JSON.parse(readFileSync(join(dir,'package.json'),'utf8'));
  const license=['LICENSE','LICENSE.txt','LICENSE.md','LICENSE-MIT','license','license.md'].find(file=>existsSync(join(dir,file)));
  if (!license) throw new Error(`Missing license: ${name}`);
  const file=`${name.replaceAll('/','-').replace('@','')}.txt`;
  copyFileSync(join(dir,license),join(root,'licenses',file));
  notices.push(`| ${name} | ${pkg.version} | [${pkg.license}](licenses/${file}) |`);
}
notices.push('| Parsedown | 1.8.0 | [MIT](inc/lib/Parsedown-LICENSE.txt) |', '| Argon Design System compatibility CSS | 1.1.0 | [MIT](licenses/argon-design-system.txt) |', '', 'Parsedown source: https://github.com/erusev/parsedown/tree/1.8.0. Only its class name is changed to `_Parsedown` to avoid plugin collisions.', '', 'The Argon Design System CSS preserves the appearance and class names of existing templates and saved content. Its obsolete JavaScript and unused assets are removed. The project and inherited Argon theme source remain under the root GPL license.', '');
writeFileSync(join(root,'THIRD_PARTY_NOTICES.md'),notices.join('\n'));
