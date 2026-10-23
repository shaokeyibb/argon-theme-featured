<?php
if (!defined('ARGON_MODERN_TESTING')) exit;
add_filter('pre_wp_mail', '__return_true');
$results = array();
function modern_assert($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function modern_test(string $name, callable $callback): void {
	try { $callback(); $GLOBALS['results'][$name] = 'passed'; }
	catch (Throwable $error) { $GLOBALS['results'][$name] = get_class($error) . ': ' . $error->getMessage(); }
}
set_error_handler(static function($severity, $message, $file, $line) {
	if (str_contains($file, '/themes/argon-theme/') && (error_reporting() & $severity)) throw new ErrorException($message, 0, $severity, $file, $line);
	return false;
});
wp_set_current_user(1);
$post_id = wp_insert_post(array('post_title' => 'Modern theme fixture', 'post_status' => 'publish', 'post_content' => '<h2>Heading</h2><p>Readable article.</p>'));

modern_test('all first-party PHP parses on the target runtime', function() {
	$root = get_template_directory();
	$paths = glob($root . '/*.php');
	foreach (array('inc', 'template-parts', 'tests') as $directory) {
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory)) as $file) {
			if ($file->isFile() && $file->getExtension() === 'php') $paths[] = $file->getPathname();
		}
	}
	foreach ($paths as $path) token_get_all(file_get_contents($path), TOKEN_PARSE);
});
modern_test('native theme features are registered', function() {
	foreach (array('title-tag', 'post-thumbnails', 'automatic-feed-links', 'responsive-embeds', 'align-wide', 'editor-styles', 'wp-block-styles', 'custom-logo') as $feature) modern_assert(current_theme_supports($feature), $feature);
	modern_assert(wp_theme_has_theme_json(), 'theme.json missing');
});
modern_test('all nine Argon blocks use API 3', function() {
	foreach (array('alert', 'admonition', 'collapse', 'github', 'timeline', 'progressbar', 'todolist', 'tabpanel', 'html-document') as $name) {
		$block = WP_Block_Type_Registry::get_instance()->get_registered('argon/' . $name);
		modern_assert($block && $block->api_version === 3, $name . ' missing or outdated');
	}
});
modern_test('build manifests and extracted dependencies resolve', function() {
	$manifest = argon_asset_manifest();
	foreach ($manifest as $entry) {
		modern_assert(is_readable(get_template_directory() . '/assets/dist/' . $entry['file']), 'Missing chunk');
		foreach ($entry['css'] ?? array() as $css) modern_assert(is_readable(get_template_directory() . '/assets/dist/' . $css), 'Missing CSS');
	}
	modern_assert(isset($manifest['src/modern/index.js'], $manifest['src/modern/admin.js']), 'Entrypoints missing');
	modern_assert(in_array('wp-block-editor', wp_scripts()->registered['argon-blocks']->deps, true), 'WordPress dependency extraction missing');
});
modern_test('historical view reads do not write database', function() use ($post_id) {
	delete_post_meta($post_id, 'views');
	get_post_views($post_id);
	modern_assert(!metadata_exists('post', $post_id, 'views'), 'Read created a counter');
	modern_assert(!has_action('get_header', 'set_post_views'), 'Legacy write hook present');
	delete_post_meta($post_id, 'upvotes'); get_shuoshuo_upvotes($post_id);
	modern_assert(!metadata_exists('post', $post_id, 'upvotes'), 'Read created a like counter');
	modern_assert(!has_action('init', 'set_user_token_cookie'), 'Anonymous page reads set cookies');
});
modern_test('shuoshuo supports REST, blocks and revisions', function() {
	modern_assert(get_post_type_object('shuoshuo')->show_in_rest && post_type_supports('shuoshuo', 'revisions'), 'Post type remains a legacy editor island');
});
modern_test('HTML document parsing preserves Unicode scripts and CSS', function() {
	$dom = argon_html_dom('<!doctype html><html><head><style>.a::before { content: "中文🌏"; }</style></head><body><script>window.message = "中文🌏 &amp;";</script><p>中文</p></body></html>');
	modern_assert($dom->getElementsByTagName('script')->item(0)->textContent === 'window.message = "中文🌏 &amp;";', 'Script source was rewritten');
	modern_assert(str_contains($dom->getElementsByTagName('style')->item(0)->textContent, '中文🌏'), 'Stylesheet source was rewritten');
});
modern_test('core responsive image attributes survive content filtering', function() use ($post_id) {
	$GLOBALS['post'] = get_post($post_id);
	$content = '<figure class="wp-block-image"><img src="https://example.com/a.jpg" srcset="https://example.com/a.jpg 800w" sizes="100vw" width="800" height="600" fetchpriority="high" alt="Example"></figure>';
	$result = the_content_filter($content);
	modern_assert($result === $content, 'Image markup was rewritten');
});
modern_test('external link badges preserve Unicode scripts and interactive markup', function() use ($post_id) {
	$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(array('p' => $post_id)); $GLOBALS['wp_query']->the_post();
	$body = '<p><a href="https://example.org/">Link</a></p><script>window.message="中文🌏";</script><img src="x.webp" loading="lazy" data-wp-interactive="core/image" width="800" height="600">';
	$html = argon_external_link_clicks_filter($body);
	modern_assert(str_contains($html, 'data-click-nonce='), 'Link proof missing');
	modern_assert(str_contains($html, substr($body, strpos($body, '<script>'))), 'Unrelated markup was reserialized');
	wp_reset_query();
});
modern_test('thumbnail discovery does not render article filters again', function() use ($post_id) {
	wp_update_post(array('ID' => $post_id, 'post_content' => '<img src="https://example.org/thumb.webp" alt="thumb"><p>Readable article for the summary fixture.</p>'));
	$calls = 0; $filter = static function($html) use (&$calls) { $calls++; return $html; };
	add_filter('the_content', $filter);
	modern_assert(argon_get_first_image_of_article($post_id) === 'https://example.org/thumb.webp' && $calls === 0, 'Thumbnail rendered full content');
	remove_filter('the_content', $filter);
});
modern_test('fork updates are disabled by default and never use upstream', function() {
	modern_assert(get_option('argon_update_source') === 'stop', 'Upgrade did not disable upstream updates');
	modern_assert(!function_exists('post_analytics_info'), 'Installation telemetry remains');
	modern_assert(!class_exists('Puc_v4_Factory'), 'Legacy updater loaded');
	modern_assert(str_contains(wp_get_theme()->get('UpdateURI'), 'shaokeyibb/argon-theme-featured'), 'Fork update identity missing');
});
modern_test('schema migration preserves explicit settings after first upgrade', function() {
	update_option('argon_update_source', 'github'); argon_upgrade_theme();
	modern_assert(get_option('argon_update_source') === 'github', 'Opt-in reset on every page');
	update_option('argon_update_source', 'stop');
});
modern_test('REST metadata participates in revisions', function() {
	$meta = get_registered_meta_keys('post', 'post');
	foreach (argon_post_meta_allowed_values() as $name => $values) {
		modern_assert(!empty($meta[$name]['show_in_rest']) && !empty($meta[$name]['revisions_enabled']), $name);
	}
});
modern_test('REST saves valid settings and rejects invalid enum', function() use ($post_id) {
	$request = new WP_REST_Request('POST', '/wp/v2/posts/' . $post_id);
	$request->set_param('meta', array('argon_show_post_outdated_info' => 'never'));
	$response = rest_do_request($request);
	modern_assert($response->get_status() === 200, wp_json_encode($response->get_data()));
	modern_assert(get_post_meta($post_id, 'argon_show_post_outdated_info', true) === 'never', 'Setting not saved');
	$request->set_param('meta', array('argon_show_post_outdated_info' => 'invalid'));
	modern_assert(rest_do_request($request)->get_status() === 400, 'Invalid enum accepted');
});
modern_test('REST cannot let authors write raw HTML or CSS', function() use ($post_id) {
	$author = wp_insert_user(array('user_login' => 'modern-author', 'user_pass' => wp_generate_password(), 'role' => 'author'));
	wp_update_post(array('ID' => $post_id, 'post_author' => $author)); wp_set_current_user($author);
	foreach (array('argon_after_post', 'argon_custom_css') as $key) {
		$request = new WP_REST_Request('POST', '/wp/v2/posts/' . $post_id); $request->set_param('meta', array($key => 'untrusted'));
		modern_assert(rest_do_request($request)->get_status() === 403, $key . ' accepted');
	}
	wp_set_current_user(1);
});

$ai_request = null;
$ai_mode = 'success';
add_filter('pre_http_request', static function($pre, $args, $url) use (&$ai_request, &$ai_mode) {
	if (!str_contains($url, 'api.openai.com')) return $pre;
	$ai_request = array($args, $url);
	if ($ai_mode === 'error') return new WP_Error('http_request_failed', 'Synthetic transport failure');
	if ($ai_mode === 'empty') return array('response' => array('code' => 200), 'headers' => array(), 'body' => '{}');
	return array('response' => array('code' => 200), 'headers' => array(), 'body' => wp_json_encode(array('choices' => array(array('message' => array('content' => '<b>A safe summary</b>'))))));
}, 10, 3);
update_option('argon_ai_post_summary', 'true'); update_option('argon_openai_api_key', 'local-test-key');
$GLOBALS['openai_baseurl'] = 'https://api.openai.com';
modern_test('AI uses the WP HTTP API and stores plain summaries', function() use ($post_id, &$ai_request) {
	modern_assert(argon_update_ai_post_meta($post_id), 'Summary failed');
	modern_assert(get_post_meta($post_id, 'argon_ai_summary', true) === 'A safe summary', 'Unexpected summary');
	modern_assert($ai_request[1] === 'https://api.openai.com/v1/chat/completions', 'Wrong endpoint');
	modern_assert($ai_request[0]['redirection'] === 0, 'Credential redirect allowed');
	modern_assert(!class_exists('HaoZiTeam\\ChatGPT\\V2'), 'Legacy SDK loaded');
});
modern_test('AI failures preserve previous summary and release locks', function() use ($post_id, &$ai_mode) {
	foreach (array('error', 'empty') as $mode) {
		$ai_mode = $mode; modern_assert(!argon_update_ai_post_meta($post_id), 'Failure reported as success');
		modern_assert(get_post_meta($post_id, 'argon_ai_summary', true) === 'A safe summary', 'Previous summary lost');
		modern_assert(!get_option('argon_ai_lock_' . $post_id), 'Lock leaked');
	}
});
modern_test('AI respects per-post opt-out and concurrent locks', function() use ($post_id, &$ai_mode) {
	$ai_mode = 'success'; update_post_meta($post_id, 'argon_ai_post_summary', 'false');
	modern_assert(!argon_update_ai_post_meta($post_id), 'Opt-out ignored'); delete_post_meta($post_id, 'argon_ai_post_summary');
	add_option('argon_ai_lock_' . $post_id, time(), '', false);
	modern_assert(!argon_update_ai_post_meta($post_id), 'Concurrent request ran'); delete_option('argon_ai_lock_' . $post_id);
});
modern_test('settings page renders without jQuery or retired controls', function() {
	wp_set_current_user(1); ob_start(); themeoptions_page(); $html = ob_get_clean();
	modern_assert(!str_contains($html, '$(') && !str_contains($html, 'vendor/jquery'), 'jQuery still required');
	modern_assert(!str_contains($html, 'name="argon_enable_zoomify"'), 'Retired setting remains');
	modern_assert(str_contains($html, 'name="argon_enable_lightbox"'), 'New lightbox setting missing');
});
restore_error_handler();
$failed = array_filter($results, static fn($result) => $result !== 'passed');
$report = array('php' => PHP_VERSION, 'wordpress' => $GLOBALS['wp_version'], 'passed' => count($results) - count($failed), 'failed' => count($failed), 'results' => $results);
wp_mkdir_p(__DIR__ . '/../temp');
file_put_contents(__DIR__ . '/../temp/modernization-report-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.json', wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo wp_json_encode($report);
if ($failed) throw new RuntimeException('Modernization regressions: ' . wp_json_encode($failed));
