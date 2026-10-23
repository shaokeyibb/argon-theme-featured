import { spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync, copyFileSync, statSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const php = process.env.ARGON_TEST_PHP || '8.3';
const wp = process.env.ARGON_TEST_WP || '7.0';
mkdirSync(resolve(root, 'temp'), { recursive: true });
const suites = process.argv.slice(2);
for (const suite of suites.length ? suites : ['security', 'html-document', 'modernization', 'user-agent']) {
  const blueprint = JSON.parse(readFileSync(resolve(root, `tests/${suite}-blueprint.json`), 'utf8'));
  blueprint.preferredVersions = { php, wp };
  const path = resolve(root, `temp/${suite}-${php}-${wp}-blueprint.json`);
  writeFileSync(path, JSON.stringify(blueprint, null, 2));
  console.log(`Testing ${suite}: WordPress ${wp}, PHP ${php}`);
  const started = Date.now();
  const child = spawnSync(process.execPath, ['--experimental-wasm-jspi', 'node_modules/@wp-playground/cli/wp-playground.js',
    'run-blueprint', '--mount-dir', root, '/wordpress/wp-content/themes/argon-theme', '--blueprint', path], { cwd: root, stdio: 'inherit' });
  if (child.status !== 0) process.exit(child.status || 1);
  const reportPath = resolve(root, `temp/${suite}-report-${php}.json`);
  if (statSync(reportPath).mtimeMs < started) throw new Error(`Missing fresh ${suite} report`);
  const report = JSON.parse(readFileSync(reportPath, 'utf8'));
  if (report.failed) throw new Error(JSON.stringify(report.results));
  copyFileSync(reportPath, resolve(root, `temp/${suite}-report-${php}-wp-${wp}.json`));
  console.log(`${report.passed} checks passed (WordPress ${report.wordpress}, PHP ${report.php})`);
}
