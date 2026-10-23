import { spawnSync } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { root } from './release-files.mjs';

const temp = join(root, 'temp');
mkdirSync(temp, {recursive:true});
const blueprint = {
  preferredVersions:{php:'8.3',wp:'7.0'},
  steps:[
    {step:'installTheme', themeData:{resource:'vfs',path:'/argon-release/argon-theme-featured.zip'}, options:{activate:true,onError:'throw'}},
    {step:'runPHP',code:`<?php
require '/wordpress/wp-load.php';
set_error_handler(static function($severity,$message,$file,$line) {
  if (str_contains($file, '/themes/argon-theme-featured/') && (error_reporting() & $severity)) throw new ErrorException($message,0,$severity,$file,$line);
  return false;
});
function package_check($condition,$message) { if (!$condition) throw new RuntimeException($message); }
package_check(get_template() === 'argon-theme-featured', 'Installed ZIP was not activated');
$theme = wp_get_theme(); package_check(!$theme->errors(), 'Theme validation failed');
package_check($theme->get('Version') === '3.0.0', 'Incorrect version');
$root = get_template_directory();
foreach (array('node_modules','vendor','tests','temp','assets/src') as $path) package_check(!file_exists($root.'/'.$path), 'Development files leaked: '.$path);
foreach (argon_asset_manifest() as $entry) {
  foreach (array_merge(array($entry['file']),$entry['css'] ?? array(),$entry['assets'] ?? array()) as $file) package_check(is_file($root.'/assets/dist/'.$file), 'Missing asset: '.$file);
}
foreach (array('alert','admonition','collapse','github','timeline','progressbar','todolist','tabpanel','html-document') as $name) {
  $block = WP_Block_Type_Registry::get_instance()->get_registered('argon/'.$name);
  package_check($block && $block->api_version === 3, 'Missing block: '.$name);
}
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(array('posts_per_page'=>2));
ob_start(); include $root.'/index.php'; $html = ob_get_clean();
package_check(str_contains($html,'id="main"') && str_contains($html,'/assets/dist/theme-'), 'Installed frontend did not render');
file_put_contents('/argon-results/package-report.json', wp_json_encode(array('passed'=>true,'wordpress'=>get_bloginfo('version'),'php'=>PHP_VERSION,'version'=>$theme->get('Version'),'template'=>get_template(),'html_bytes'=>strlen($html))));
`}
  ]
};
const path = join(temp,'package-blueprint.json');
writeFileSync(path,JSON.stringify(blueprint,null,2));
const started = Date.now();
const child = spawnSync(process.execPath,['--experimental-wasm-jspi','node_modules/@wp-playground/cli/wp-playground.js','run-blueprint',
  '--mount-dir',join(root,'release'),'/argon-release','--mount-dir',temp,'/argon-results','--blueprint',path],{cwd:root,stdio:'inherit'});
if(child.status !== 0) process.exit(child.status || 1);
const reportPath = join(temp,'package-report.json');
if(statSync(reportPath).mtimeMs < started) throw new Error('Installation did not produce a fresh report');
const report = JSON.parse(readFileSync(reportPath,'utf8'));
if(!report.passed) throw new Error('ZIP installation check failed');
console.log(`ZIP installed and rendered: Argon ${report.version}, WordPress ${report.wordpress}, PHP ${report.php}`);
