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
modern_test('existing color and appearance options reach WordPress global styles without visitor leakage', function() {
	$keys = array('argon_theme_color', 'argon_font', 'argon_card_radius', 'argon_card_shadow');
	$previous = array();
	foreach ($keys as $key) $previous[$key] = get_option($key, null);
	$cookie = $_COOKIE['argon_custom_theme_color'] ?? null;
	try {
		$expected = array('#5e72e4', '#fa7298', '#009688', '#607d8b', '#2196f3', '#3f51b5', '#ff9700', '#109d58', '#dc4437', '#673bb7', '#212121', '#795547');
		modern_assert(array_column(argon_theme_color_presets(), 'color') === $expected, 'Existing color swatches changed');
		$_COOKIE['argon_custom_theme_color'] = '#ff0000';
		update_option('argon_font', 'serif'); update_option('argon_card_radius', 9.5); update_option('argon_card_shadow', 'big');
		foreach ($expected as $color) {
			update_option('argon_theme_color', $color);
			$source = wp_json_file_decode(get_template_directory() . '/theme.json', array('associative' => true));
			$source['settings']['color']['palette'][] = array('slug' => 'child-color', 'name' => 'Child color', 'color' => '#123456');
			$data = argon_design_theme_json(new WP_Theme_JSON_Data($source, 'theme'))->get_theme_json()->get_data();
			$palette = array_column($data['settings']['color']['palette'], 'color', 'slug');
			modern_assert($palette['primary'] === $color && $palette['child-color'] === '#123456', 'Site/child palette lost or reader cookie leaked');
			modern_assert($palette['argon-teal'] === '#009688', 'Existing preset missing from editor');
			$design = $data['settings']['custom']['argon'];
			modern_assert($design['content']['font-family'] === 'var(--wp--preset--font-family--serif)', 'Site font ignored');
			modern_assert($design['surface']['radius'] === '9.5px' && str_contains($design['surface']['shadow'], '35px'), 'Existing card settings ignored');
		}
		modern_assert(in_array('argon-icons', wp_styles()->registered['argon-blocks-editor']->deps, true), 'Editor does not share the official icon stylesheet');
	} finally {
		foreach ($previous as $key => $value) { if ($value === null) delete_option($key); else update_option($key, $value); }
		if ($cookie === null) unset($_COOKIE['argon_custom_theme_color']); else $_COOKIE['argon_custom_theme_color'] = $cookie;
	}
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
modern_test('REST publish honors the opt-out saved in the same request', function() use (&$ai_request) {
	update_option('argon_ai_async_generate', 'false');
	$id = wp_insert_post(array('post_title' => 'REST opt-out', 'post_content' => 'Do not send this content.', 'post_status' => 'draft'));
	$ai_request = null;
	$request = new WP_REST_Request('POST', '/wp/v2/posts/' . $id);
	$request->set_param('status', 'publish');
	$request->set_param('meta', array('argon_ai_post_summary' => 'false'));
	$response = rest_do_request($request);
	modern_assert($response->get_status() === 200, wp_json_encode($response->get_data()));
	modern_assert($ai_request === null && get_post_meta($id, 'argon_ai_summary', true) === '', 'AI read the previous opt-in before REST saved meta');
});
modern_test('REST page publish uses the newly saved prompt', function() use (&$ai_request) {
	$id = wp_insert_post(array('post_type' => 'page', 'post_title' => 'REST page summary', 'post_content' => 'Summarize this page.', 'post_status' => 'draft'));
	$ai_request = null;
	$request = new WP_REST_Request('POST', '/wp/v2/pages/' . $id);
	$request->set_param('status', 'publish');
	$request->set_param('meta', array('argon_ai_post_summary' => 'true', 'argon_ai_extra_prompt_mode' => 'replace', 'argon_ai_extra_prompt' => 'Use the new page prompt.'));
	$response = rest_do_request($request);
	modern_assert($response->get_status() === 200, wp_json_encode($response->get_data()));
	modern_assert($ai_request !== null, 'Pages did not trigger summary generation');
	$payload = json_decode($ai_request[0]['body'], true);
	modern_assert(end($payload['messages'])['content'] === 'Use the new page prompt.', 'Prompt was read before REST persisted it');
	update_option('argon_ai_async_generate', 'true');
});
modern_test('search filters leave secondary queries unchanged', function() {
	$query = new WP_Query(array('s' => 'fixture', 'post_type' => 'page'));
	modern_assert($query->get('post_type') === 'page', 'Theme replaced another component\'s post type filter');
});
modern_test('queued AI jobs stop when a publication is withdrawn', function() use (&$ai_request) {
	$id = wp_insert_post(array('post_title' => 'Withdrawn summary', 'post_content' => 'Do not send unpublished content.', 'post_status' => 'draft'));
	$ai_request = null;
	do_action('argon_update_ai_post_meta', $id);
	modern_assert($ai_request === null, 'Queued job sent a withdrawn article');
	modern_assert(argon_update_ai_post_meta($id), 'Manual draft generation should remain available');
});
modern_test('dynamic first images retain shortcode support without recursive rendering', function() {
	$runs = 0;
	add_shortcode('argon_fixture_image', static function() use (&$runs) {
		$runs++;
		argon_get_first_image_of_article(get_the_ID()); // A plugin can ask for its own thumbnail.
		return '<img src="https://example.com/dynamic-first.png">';
	});
	$id = wp_insert_post(array('post_title' => 'Dynamic image', 'post_content' => '[argon_fixture_image]', 'post_status' => 'draft'));
	$previous = $GLOBALS['post'] ?? null;
	modern_assert(argon_get_first_image_of_article($id) === 'https://example.com/dynamic-first.png', 'Shortcode image missing');
	modern_assert(argon_get_first_image_of_article($id) === 'https://example.com/dynamic-first.png' && $runs === 1, 'Fallback repeated rendering');
	modern_assert(($GLOBALS['post'] ?? null) === $previous, 'Thumbnail changed the current post');
	remove_shortcode('argon_fixture_image');
});
modern_test('settings page renders without jQuery or retired controls', function() {
	wp_set_current_user(1); ob_start(); themeoptions_page(); $html = ob_get_clean();
	modern_assert(!str_contains($html, '$(') && !str_contains($html, 'vendor/jquery'), 'jQuery still required');
	modern_assert(!str_contains($html, 'name="argon_enable_zoomify"'), 'Retired setting remains');
	modern_assert(str_contains($html, 'name="argon_enable_lightbox"'), 'New lightbox setting missing');
});
modern_test('native custom logo renders without a saved title and hidden banners keep a solid toolbar', function() {
	$previous_title = get_option('argon_toolbar_title', null);
	$previous_banner = get_option('argon_banner_size', null);
	$previous_logo = get_theme_mod('custom_logo');
	$logo = wp_insert_attachment(array('post_title' => 'Logo fixture', 'post_mime_type' => 'image/png', 'guid' => 'https://example.com/logo.png'));
	update_post_meta($logo, '_wp_attached_file', 'logo.png');
	wp_update_attachment_metadata($logo, array('width' => 100, 'height' => 100, 'file' => 'logo.png', 'sizes' => array()));
	delete_option('argon_toolbar_title'); set_theme_mod('custom_logo', $logo);
	update_option('argon_banner_size', 'hide');
	ob_start();
	try {
		get_header();
		$html = ob_get_contents();
		modern_assert(str_contains($html, 'class="custom-logo"'), 'Registered native logo was ignored without a legacy title option');
		$processor = new WP_HTML_Tag_Processor($html);
		while ($processor->next_tag('nav') && $processor->get_attribute('id') !== 'navbar-main') {}
		modern_assert($processor->get_attribute('id') === 'navbar-main', 'Missing toolbar');
		modern_assert(!$processor->has_class('navbar-ontop'), 'Hidden banner starts with a transparent toolbar');
		modern_assert($processor->has_class('navbar-no-blur'), 'Toolbar starts blurred');
	} finally {
		ob_end_clean();
		if ($previous_title !== null) update_option('argon_toolbar_title', $previous_title);
		if ($previous_banner === null) delete_option('argon_banner_size'); else update_option('argon_banner_size', $previous_banner);
		if ($previous_logo) set_theme_mod('custom_logo', $previous_logo); else remove_theme_mod('custom_logo');
		wp_delete_attachment($logo, true);
	}
});
modern_test('sharing preserves Unicode URLs and titles, valid links and existing platform filters', function() use ($post_id) {
	$previous_post = $GLOBALS['post'] ?? null;
	$previous_mode = get_option('argon_show_sharebtn', null);
	$GLOBALS['post'] = get_post($post_id);
	$title = '中文 & "quoted" + #/? 🌌';
	$url = 'https://example.com/中文/?a=1&b=%2B#标题';
	$title_filter = static fn() => $title;
	$url_filter = static fn() => $url;
	add_filter('the_title', $title_filter); add_filter('post_link', $url_filter);
	try {
		foreach (array('true' => 7, 'domestic' => 1, 'abroad' => 6) as $mode => $count) {
			update_option('argon_show_sharebtn', $mode);
			ob_start(); get_template_part('template-parts/share'); $html = ob_get_clean();
			$dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
			$xpath = new DOMXPath($dom); $links = $xpath->query('//a[@data-share-platform]');
			modern_assert($links->length === $count, 'Wrong platform filter: ' . $mode);
			modern_assert($xpath->query('//a/button')->length === 0, 'Nested interactive elements');
			foreach ($links as $link) {
				parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $params);
				$name = $link->getAttribute('data-share-platform');
				if (in_array($name, array('whatsapp', 'bluesky'), true)) modern_assert($params['text'] === $title . ' ' . $url, $name . ' text corrupted');
				else modern_assert(($params['url'] ?? $params['u'] ?? '') === $url, $name . ' URL corrupted');
				modern_assert($link->getAttribute('rel') === 'noopener noreferrer', 'Unsafe target');
			}
			modern_assert(!str_contains($html, 'shareqq') && !str_contains($html, 'qzone') && !str_contains($html, 'douban'), 'Retired destination');
		}
	} finally {
		remove_filter('the_title', $title_filter); remove_filter('post_link', $url_filter);
		$GLOBALS['post'] = $previous_post;
		if ($previous_mode === null) delete_option('argon_show_sharebtn'); else update_option('argon_show_sharebtn', $previous_mode);
	}
});
modern_test('theme bootstrap and WordPress hooks emit no PHP warnings or deprecated API diagnostics', function() {
	modern_assert(isset($GLOBALS['argon_boot_diagnostics']), 'Boot diagnostics MU plugin was not loaded');
	modern_assert(!$GLOBALS['argon_boot_diagnostics'], implode("\n", $GLOBALS['argon_boot_diagnostics']));
});
restore_error_handler();
$failed = array_filter($results, static fn($result) => $result !== 'passed');
$report = array('php' => PHP_VERSION, 'wordpress' => $GLOBALS['wp_version'], 'passed' => count($results) - count($failed), 'failed' => count($failed), 'results' => $results);
$report_dir = dirname(__DIR__) . '/temp';
if (!wp_mkdir_p($report_dir) || file_put_contents($report_dir . '/modernization-report-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.json', wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
	throw new RuntimeException('Could not write modernization report');
}
echo wp_json_encode($report);
if ($failed) throw new RuntimeException('Modernization regressions: ' . wp_json_encode($failed));
