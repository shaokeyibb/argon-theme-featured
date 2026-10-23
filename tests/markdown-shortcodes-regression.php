<?php
if (!defined('ARGON_MARKDOWN_TESTING') || !ARGON_MARKDOWN_TESTING) exit;
wp_set_current_user(1);
update_option('argon_ai_post_summary', 'false');
$results = array();
function markdown_assert($condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}
function markdown_test(string $name, callable $test): void {
	try { $test(); $GLOBALS['results'][$name] = 'passed'; }
	catch (Throwable $error) { $GLOBALS['results'][$name] = $error->getMessage(); }
}
function markdown_render(string $source, bool $enabled = true): string {
	$controller = new \Githuber\Controller\Markdown();
	$converted = $controller->transform($source, array('unslash' => false));
	$post_id = wp_insert_post(wp_slash(array('post_title' => 'Markdown shortcode regression', 'post_content' => $converted,
		'post_content_filtered' => $source, 'post_status' => 'publish', 'meta_input' => array('_is_githuber_markdown' => '1', '_is_githuber_markdown_enabled' => $enabled ? 'yes' : 'no'))));
	$GLOBALS['post'] = get_post($post_id);
	return apply_filters('the_content', $converted);
}
foreach (array('collapse', 'fold', 'alert', 'admonition') as $tag) {
	markdown_test($tag . ' renders block Markdown through the real plugin and WordPress filters', function() use ($tag) {
		$html = markdown_render('[' . $tag . ' title="Example"]' . "\n## Heading\n\n**bold** and [link](https://example.test/)\n\n- first\n- second\n\n```js\nconst value = 1 < 2;\n```\n" . '[/' . $tag . ']');
		foreach (array('<h2>Heading</h2>', '<strong>bold</strong>', '<ul>', '<li>second</li>', 'href="https://example.test/"', 'language-js', 'const value = 1 &lt; 2;') as $expected) markdown_assert(str_contains($html, $expected), 'Missing ' . $expected . ': ' . $html);
	});
}
foreach (array('label', 'checkbox', 'progressbar', 'hidden', 'spoiler', 'ref') as $tag) {
	markdown_test($tag . ' renders inline Markdown without paragraph wrappers', function() use ($tag) {
		$html = markdown_render('[' . $tag . ']**bold** and *emphasis*[/' . $tag . ']');
		if ($tag === 'ref') $html = end($GLOBALS['post_references'])['content'];
		markdown_assert(str_contains($html, '<strong>bold</strong>') && str_contains($html, '<em>emphasis</em>'), $html);
		markdown_assert(!str_contains($html, '<span><p>') && !str_contains($html, "text'><p>"), 'Invalid inline paragraphs: ' . $html);
	});
}
markdown_test('nested containers render once and nested false retains literal declarations', function() {
	$html = markdown_render("[collapse]\n**outer**\n\n[admonition title=\"**literal title**\"]\n**inner**\n[/admonition]\n[/collapse]");
	markdown_assert(str_contains($html, '<strong>outer</strong>') && str_contains($html, '<strong>inner</strong>') && str_contains($html, '**literal title**'), $html);
	markdown_assert(substr_count($html, 'admonition-body') === 1, 'Nested container duplicated');
	$html = markdown_render('[collapse nested="false"]**outer** [label]**literal child**[/label][/collapse]');
	markdown_assert(str_contains($html, '<strong>outer</strong>') && str_contains($html, '[label]**literal child**[/label]'), $html);
});
markdown_test('code examples and escaped declarations never execute', function() {
	$html = markdown_render("[collapse]\n```text\n[label]example[/label]\n```\n\n`[alert]inline example[/alert]`\n\n[[label]escaped[/label]]\n[/collapse]");
	markdown_assert(substr_count($html, 'collapse-block-body') === 1 && !str_contains($html, 'alert-inner--text') && !str_contains($html, 'badge-primary'), $html);
	markdown_assert(str_contains(html_entity_decode($html), '[label]example[/label]') && str_contains(html_entity_decode($html), '[alert]inline example[/alert]'), $html);
});
markdown_test('existing HTML entities and backslashes remain intact', function() {
	$html = markdown_render('[collapse]<div><strong>Existing HTML</strong> &amp; **literal**</div>' . "\n\n```text\nC:\\notes\\new &amp; <tag>\n```\n[/collapse]");
	markdown_assert(str_contains($html, '<strong>Existing HTML</strong> &amp; **literal**'), $html);
	markdown_assert(str_contains($html, 'C:\\notes\\new &amp;amp; &lt;tag&gt;'), $html);
});
markdown_test('unsafe Markdown URLs and raw executable HTML are sanitized', function() {
	$html = markdown_render('[collapse][unsafe](javascript:alert%281%29) <img src=x onerror="alert(1)"><script>alert(2)</script>[/collapse]');
	markdown_assert(!str_contains($html, 'javascript:') && !str_contains($html, 'onerror=') && !str_contains($html, '<script>'), $html);
});
markdown_test('structured records and noshortcode keep their existing grammar', function() {
	$html = markdown_render("[timeline]\n2026|**Title**|**Body**\n[/timeline]\n\n[noshortcode][label]**literal**[/label][/noshortcode]");
	markdown_assert(str_contains($html, '**Title**') && str_contains($html, '**Body**') && str_contains($html, '[label]**literal**[/label]'), $html);
});
markdown_test('switching to the normal editor disables Markdown adaptation', function() {
	$html = markdown_render('[collapse]**literal**[/collapse]', false);
	markdown_assert(str_contains($html, '**literal**') && !str_contains($html, '<strong>literal</strong>'), $html);
});
markdown_test('rendering fragments does not weaken the plugin parser for subsequent content', function() {
	markdown_render('[collapse]**first**[/collapse]');
	$parser = \Githuber\Controller\Markdown::get_parser();
	$html = $parser->transform('<script>alert(1)</script>');
	markdown_assert(!str_contains($html, '<script>') && str_contains($html, '&lt;script&gt;'), $html);
});
$failed = array_filter($results, static fn($result) => $result !== 'passed');
$report = array('php' => PHP_VERSION, 'wordpress' => $GLOBALS['wp_version'], 'githuber' => GITHUBER_PLUGIN_VERSION, 'passed' => count($results) - count($failed), 'failed' => count($failed), 'results' => $results);
$report_dir = dirname(__DIR__) . '/temp';
if (!wp_mkdir_p($report_dir) || file_put_contents($report_dir . '/markdown-shortcodes-report-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.json', wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) throw new RuntimeException('Could not write Markdown shortcode report');
echo wp_json_encode($report);
if ($failed) throw new RuntimeException('Markdown shortcode regressions: ' . wp_json_encode($failed));
