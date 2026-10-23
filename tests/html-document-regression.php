<?php
// Creates users/posts/options. Run only through the disposable Playground blueprint.
if (!defined('ARGON_HTML_TESTING') || !ARGON_HTML_TESTING) {
	exit;
}

class ArgonHtmlTestAjaxEnd extends RuntimeException {}
$results = array();
add_filter('pre_wp_mail', '__return_true');
add_filter('wp_die_ajax_handler', static fn() => static function() { throw new ArgonHtmlTestAjaxEnd(); });
function html_assert($condition, string $message = 'Assertion failed'): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}
function html_test(string $name, callable $test): void {
	wp_set_current_user(1);
	$_POST = $_GET = $_COOKIE = array();
	$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query();
	try {
		$test();
		$GLOBALS['results'][$name] = 'passed';
	} catch (Throwable $error) {
		$GLOBALS['results'][$name] = get_class($error) . ': ' . $error->getMessage() . ' (' . $error->getFile() . ':' . $error->getLine() . ')';
	}
}
function html_post(string $content, array $extra = array()): int {
	return wp_insert_post(wp_slash(array_merge(array('post_title' => 'HTML test', 'post_content' => $content, 'post_status' => 'publish', 'post_author' => 1), $extra)));
}
function html_inline(string $html): string {
	return '[argon_html encoding="base64"]' . base64_encode($html) . '[/argon_html]';
}
function html_response(int $id, array $extra = array()): array {
	return argon_html_document_response(array_merge(array('argon_html_document' => (string) $id, 'instance' => wp_generate_uuid4()), $extra));
}
function html_ajax(array $request): array {
	$_POST = wp_slash($request);
	ob_start();
	try {
		argon_html_editor_preview();
	} catch (ArgonHtmlTestAjaxEnd $error) {
		// Read the real WordPress JSON response without terminating the test request.
	} finally {
		$output = ob_get_clean();
	}
	return json_decode($output, true) ?: array();
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_set_current_user(1);
update_option('argon_html_enabled', 'true');
update_option('argon_html_allow_scripts', 'true');
$source = file_get_contents(__DIR__ . '/fixtures/html-document.html');
$author = wp_insert_user(array('user_login' => 'argon_html_author', 'user_pass' => wp_generate_password(), 'role' => 'author'));
$article = html_post('<p>替代正文唯一关键词：alternativeonly。先写介绍。</p>' . html_inline($source) . '<p>后续文字也属于替代正文。</p>');
$empty_fallback = html_post(html_inline($source));
$draft = html_post(html_inline($source), array('post_status' => 'draft'));
$private = html_post(html_inline($source), array('post_status' => 'private'));
$protected = html_post('保密替代正文' . html_inline($source), array('post_password' => 'secret'));
$normal = html_post('<p>正常文章仍然正常显示。</p>');

set_error_handler(static function($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	throw new ErrorException($message, 0, $severity, $file, $line);
});

html_test('new PHP files parse on the configured PHP version', function() {
	foreach (array('/inc/fun/html-document.php', '/inc/fun/html-document-editor.php') as $path) {
		token_get_all(file_get_contents(get_template_directory() . $path), TOKEN_PARSE);
	}
});
html_test('encoded inline source round trips with exact Unicode and scripts', function() use ($article, $source) {
	$parts = argon_html_document_parts($article);
	html_assert($parts['html'] === $source && argon_html_document_approved($parts));
	html_assert(str_contains($parts['fallback'], '先写介绍') && str_contains($parts['fallback'], '后续文字'));
});
html_test('Markdown fenced inline document remains readable', function() use ($source) {
	$id = html_post("前文\n\n[argon_html]\n```html\n" . $source . "\n```\n[/argon_html]\n\n后文");
	$parts = argon_html_document_parts($id);
	html_assert($parts['html'] === $source && str_contains($parts['fallback'], '前文') && str_contains($parts['fallback'], '后文'));
});
html_test('shortcode examples in Markdown fences do not activate HTML mode', function() {
	$id = html_post("```text\n[argon_html file_id=123]\n```\n说明");
	html_assert(!argon_html_has_document($id));
});
html_test('inline code and escaped shortcode examples remain ordinary text', function() {
	foreach (array('`[argon_html file_id=123]`', '[[argon_html file_id=123]]', '<pre><code>[argon_html file_id=123]</code></pre>') as $content) {
		html_assert(!argon_html_has_document(html_post($content)), $content);
	}
});
html_test('multiple document declarations are rejected', function() use ($source) {
	html_assert(is_wp_error(argon_html_document_parts(html_post(html_inline($source) . html_inline($source)))));
});
html_test('an inline document may contain literal Gutenberg block comments', function() {
	$source = '<!doctype html><html><body><!-- wp:paragraph --><p>文档内部</p><!-- /wp:paragraph --></body></html>';
	$id = html_post("替代正文\n[argon_html]\n```html\n" . $source . "\n```\n[/argon_html]");
	html_assert(argon_html_document_parts($id)['html'] === $source);
});
html_test('mixed block and shortcode declarations are rejected', function() use ($source) {
	$id = html_post(html_inline($source) . '<!-- wp:argon/html-document {"source":"' . base64_encode($source) . '"} /-->');
	html_assert(is_wp_error(argon_html_document_parts($id)));
});
html_test('malformed base64 is rejected rather than interpreted as HTML', function() {
	html_assert(is_wp_error(argon_html_document_parts(html_post('[argon_html encoding="base64"]!!bad!![/argon_html]'))));
});
html_test('file references cannot read paths URLs or ordinary attachments', function() {
	foreach (array('../../wp-config.php', 'https://example.org/secret.html', '999999999') as $id) {
		html_assert(is_wp_error(argon_html_document_parts(html_post('[argon_html file_id="' . $id . '"]'))));
	}
});
html_test('Gutenberg source and surrounding paragraph blocks share the same model', function() use ($source) {
	$block = serialize_block(array('blockName' => 'argon/html-document', 'attrs' => array('source' => base64_encode($source)), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array()));
	$id = html_post('<!-- wp:paragraph --><p>古腾堡前文</p><!-- /wp:paragraph -->' . $block . '<!-- wp:paragraph --><p>古腾堡后文</p><!-- /wp:paragraph -->');
	$parts = argon_html_document_parts($id);
	html_assert($parts['html'] === $source && str_contains(argon_html_document_text($id), '古腾堡前文') && str_contains(argon_html_document_text($id), '古腾堡后文'));
});
html_test('Gutenberg group preserves sibling fallback blocks', function() use ($source) {
	$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>组内正文</p><!-- /wp:paragraph --><!-- wp:argon/html-document {"source":"' . base64_encode($source) . '"} /--></div><!-- /wp:group -->';
	$id = html_post($content);
	html_assert(is_array(argon_html_document_parts($id)) && str_contains(argon_html_document_text($id), '组内正文'));
});
html_test('Githuber original Markdown wins over its converted document code', function() use ($source) {
	$raw = "Githuber 前文\n\n[argon_html]\n```html\n" . $source . "\n```\n[/argon_html]\n\nGithuber 后文";
	$converted = '<p>Githuber 前文</p><p>[argon_html]</p><pre><code>' . esc_html($source) . '</code></pre><p>[/argon_html]</p><p>Githuber 后文</p>';
	$id = html_post($converted, array('post_content_filtered' => $raw, 'meta_input' => array('_is_githuber_markdown' => '1')));
	html_assert(argon_html_document_parts($id)['html'] === $source);
	html_assert(!str_contains(argon_html_document_text($id), 'artifactExecuted'));
});
html_test('ordinary Gutenberg articles ignore stale filtered Markdown', function() use ($source) {
	$id = html_post('<!-- wp:paragraph --><p>古腾堡文章</p><!-- /wp:paragraph -->', array('post_content_filtered' => html_inline($source)));
	html_assert(!argon_html_has_document($id));
});
html_test('surrounding content is preferred over HTML text for AI and reading time', function() use ($article) {
	$text = argon_html_document_text($article);
	html_assert(str_contains($text, 'alternativeonly') && str_contains($text, '后续文字') && !str_contains($text, '响应式布局'));
});
html_test('automatic extraction removes head scripts styles and hidden text', function() {
	$id = html_post(html_inline('<!doctype html><html><head><title>secret title</title><style>badcss</style></head><body><h1>正文</h1><div hidden>secret hidden</div><script>badjs()</script><p>第二段</p></body></html>'));
	$text = argon_html_document_text($id);
	html_assert(str_contains($text, '正文') && str_contains($text, '第二段') && !str_contains($text, 'secret') && !str_contains($text, 'bad'));
});
html_test('post CSS and scripts cannot become fallback text or AI input after KSES', function() use ($source) {
	$id = html_post('<p>正常替代正文</p><script>not-summary-script()</script>' . html_inline($source));
	update_post_meta($id, 'argon_custom_css', 'not-summary-css { color: red; }');
	$text = argon_html_document_text($id);
	html_assert(str_contains($text, '正常替代正文') && !str_contains($text, 'not-summary'));
});
html_test('public rendered content contains fallback text and no frame or source code', function() use ($article) {
	$GLOBALS['post'] = get_post($article);
	$html = apply_filters('the_content', get_post($article)->post_content);
	html_assert(str_contains($html, 'alternativeonly') && !str_contains($html, '<iframe') && !str_contains($html, 'artifactExecuted') && !str_contains($html, 'base64'));
});
html_test('shortcodes outside the article template never execute an HTML document', function() use ($source) {
	$html = do_shortcode(html_inline($source));
	html_assert(!str_contains($html, '<script') && !str_contains($html, '<iframe'));
});
html_test('normal article rendering is unchanged', function() use ($normal) {
	$GLOBALS['post'] = get_post($normal);
	html_assert(str_contains(apply_filters('the_content', get_post($normal)->post_content), '正常文章'));
	ob_start(); $rendered = argon_html_render_article($normal); ob_end_clean();
	html_assert($rendered === false);
});
html_test('article frame and text version are rendered together', function() use ($article) {
	$GLOBALS['post'] = get_post($article);
	ob_start(); argon_html_render_article($article); $html = ob_get_clean();
	html_assert(str_contains($html, '<iframe') && str_contains($html, 'sandbox="allow-scripts"') && str_contains($html, '<details') && str_contains($html, 'alternativeonly'));
	html_assert(!str_contains($html, 'artifactExecuted'));
});
html_test('public document response has HTTP sandbox CSP and noindex', function() use ($article) {
	wp_set_current_user(0);
	$response = html_response($article);
	html_assert($response['status'] === 200 && str_contains($response['headers']['Content-Security-Policy'], "sandbox allow-scripts;"));
	html_assert(str_contains($response['headers']['X-Robots-Tag'], 'noindex') && $response['headers']['Cache-Control'] === 'private, no-store');
	html_assert(str_contains($response['body'], 'artifactExecuted') && str_contains($response['body'], 'argonHtmlBridge'));
});
html_test('draft private and password protected document endpoints are inaccessible to guests', function() use ($draft, $private, $protected) {
	wp_set_current_user(0);
	foreach (array($draft, $private, $protected) as $id) { html_assert(html_response($id)['status'] === 404); }
});
html_test('article password cookie unlocks its document', function() use ($protected) {
	wp_set_current_user(0);
	require_once ABSPATH . WPINC . '/class-phpass.php';
	$hasher = new PasswordHash(8, true);
	$_COOKIE['wp-postpass_' . COOKIEHASH] = $hasher->HashPassword('secret');
	html_assert(html_response($protected)['status'] === 200);
});
html_test('password protection also covers list and feed fallback text', function() use ($protected) {
	wp_set_current_user(0);
	$GLOBALS['post'] = get_post($protected);
	html_assert(!str_contains(argon_html_excerpt($protected), '保密替代正文'));
	html_assert(!str_contains(apply_filters('the_content', get_post($protected)->post_content), '保密替代正文'));
});
html_test('lower privilege source edits invalidate approval including base64 payloads', function() use ($author, $source) {
	wp_set_current_user($author);
	$id = html_post(html_inline($source), array('post_author' => $author));
	$parts = argon_html_document_parts($id);
	html_assert(!argon_html_document_approved($parts) && html_response($id)['status'] === 404);
	wp_set_current_user(1);
	wp_update_post(array('ID' => $id, 'post_title' => 'Approved'));
	html_assert(html_response($id)['status'] === 200);
	wp_set_current_user($author);
	wp_update_post(wp_slash(array('ID' => $id, 'post_content' => html_inline(str_replace('完整 HTML', 'changed HTML', $source)))));
	html_assert(html_response($id)['status'] === 404);
});
html_test('lower privilege changes to fallback and title preserve unchanged approved source', function() use ($source, $author) {
	$id = html_post(html_inline($source), array('post_author' => $author));
	wp_set_current_user($author);
	wp_update_post(wp_slash(array('ID' => $id, 'post_content' => '更新替代正文' . html_inline($source))));
	html_assert(html_response($id)['status'] === 200 && str_contains(argon_html_document_text($id), '更新替代正文'));
});
html_test('removing a document revokes approval before a lower privilege user can reinsert it', function() use ($source, $author) {
	$id = html_post(html_inline($source), array('post_author' => $author));
	wp_update_post(array('ID' => $id, 'post_content' => '普通文章'));
	html_assert(get_post_meta($id, '_argon_html_approval', true) === '');
	wp_set_current_user($author);
	wp_update_post(wp_slash(array('ID' => $id, 'post_content' => html_inline($source))));
	html_assert(html_response($id)['status'] === 404);
});
html_test('untrusted clients cannot write internal approvals through WordPress meta permissions', function() use ($article, $author) {
	wp_set_current_user($author);
	foreach (array('_argon_html_source', '_argon_html_approval', '_argon_html_source_signature', '_argon_html_media') as $key) {
		html_assert(!current_user_can('edit_post_meta', $article, $key), $key);
	}
});
html_test('revision endpoint requires parent permission and a nonce', function() use ($article, $source) {
	$revision = wp_insert_post(wp_slash(array('post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $article, 'post_content' => html_inline($source), 'post_title' => 'Preview revision')));
	$nonce = wp_create_nonce('argon_html_view_' . $article . '_' . $revision);
	$response = html_response($article, array('revision' => (string) $revision, '_wpnonce' => $nonce));
	$parts = argon_html_document_parts($revision, false);
	html_assert($response['status'] === 200, wp_json_encode(array('status' => $response['status'], 'revision' => $revision, 'parent' => get_post($revision)->post_parent, 'approved' => is_array($parts) && argon_html_document_approved($parts), 'nonce' => wp_verify_nonce($nonce, 'argon_html_view_' . $article . '_' . $revision))));
	wp_set_current_user(0);
	html_assert(html_response($article, array('revision' => (string) $revision, '_wpnonce' => $nonce))['status'] === 404);
});
html_test('revision approval and text never overwrite the published parent metadata', function() use ($article, $source) {
	$approval = get_post_meta($article, '_argon_html_approval', true);
	$text = get_post_meta($article, '_argon_html_search_text', true);
	$revision = wp_insert_post(wp_slash(array('post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $article,
		'post_content' => 'private-draft-only' . html_inline(str_replace('完整 HTML', '修订 HTML', $source)), 'post_title' => 'Private draft')));
	html_assert(argon_html_document_approved(argon_html_document_parts($revision, false)));
	html_assert(get_post_meta($article, '_argon_html_approval', true) === $approval && get_post_meta($article, '_argon_html_search_text', true) === $text);
	html_assert(html_response($article)['status'] === 200);
});
html_test('sandbox permissions are configurable but cannot grant escape tokens', function() use ($article) {
	foreach (array('scripts', 'popups', 'downloads', 'modals') as $permission) { update_option('argon_html_allow_' . $permission, 'true'); }
	$tokens = argon_html_sandbox();
	html_assert(str_contains($tokens, 'allow-popups') && str_contains($tokens, 'allow-downloads') && str_contains($tokens, 'allow-modals'));
	html_assert(!str_contains($tokens, 'same-origin') && !str_contains($tokens, 'top-navigation') && !str_contains($tokens, 'escape-sandbox') && !str_contains($tokens, 'allow-forms'));
	foreach (array('popups', 'downloads', 'modals') as $permission) { update_option('argon_html_allow_' . $permission, 'false'); }
});
html_test('disabling scripts also removes bridge and keeps fixed-height rendering', function() use ($article) {
	update_option('argon_html_allow_scripts', 'false');
	$response = html_response($article);
	html_assert(!str_contains(argon_html_sandbox(), 'allow-scripts') && str_contains($response['headers']['Content-Security-Policy'], "script-src 'none'"));
	html_assert(!str_contains($response['body'], 'argonHtmlBridge'));
	ob_start(); argon_html_render_article($article); $html = ob_get_clean();
	html_assert(str_contains($html, 'data-height="viewport"'));
	update_option('argon_html_allow_scripts', 'true');
});
html_test('resource origin parser rejects CSP injection wildcards credentials paths and HTTP', function() {
	$origins = argon_html_resource_origins("https://cdn.example.org\nhttps://cdn.example.org/\nhttps://cdn.example.org:8443\nhttp://insecure.test\nhttps://*.example.org\nhttps://user:pass@example.org\nhttps://example.org/path\nhttps://example.org;script-src\nhttps://example.org?test");
	html_assert($origins === array('https://cdn.example.org', 'https://cdn.example.org:8443'));
});
html_test('document head normalization removes base overrides refresh and duplicate viewport', function() {
	$html = argon_html_prepare_document('<html><head><base href="https://evil.test"><meta http-equiv="refresh" content="0;url=https://evil.test"><meta name="viewport" content="width=2000"></head><body><a target="_blank" href="https://example.org">link</a></body></html>', home_url('/'), wp_generate_uuid4());
	html_assert(!str_contains($html, 'evil.test') && !str_contains($html, 'width=2000') && str_contains($html, 'noopener noreferrer'));
});
html_test('CSP always blocks forms nested frames worker network interfaces and eval', function() use ($article) {
	$policy = html_response($article)['headers']['Content-Security-Policy'];
	foreach (array("connect-src 'none'", "form-action 'none'", "frame-src 'none'", "worker-src 'none'", "object-src 'none'") as $part) { html_assert(str_contains($policy, $part), $part); }
	html_assert(!str_contains($policy, 'unsafe-eval'));
});
html_test('malformed endpoint arrays are rejected without PHP warnings', function() use ($article) {
	html_assert(argon_html_document_response(array('argon_html_document' => array($article), 'instance' => array('invalid')))['status'] === 404);
});
html_test('search uses the text projection while preserving WordPress search conditions', function() use ($article) {
	wp_set_current_user(0);
	$query = new WP_Query(array('s' => 'alternativeonly', 'post_type' => 'post', 'fields' => 'ids'));
	html_assert(in_array($article, $query->posts, true), $query->request);
	html_assert(str_contains($query->request, '_argon_html_search_text') && str_contains($query->request, 'post_password'));
});
html_test('searching for a literal SQL column name does not rewrite the search string', function() {
	global $wpdb;
	$literal = $wpdb->posts . '.post_content';
	$id = html_post('<p>' . $literal . '</p>');
	wp_set_current_user(0);
	$query = new WP_Query(array('s' => $literal, 'post_type' => 'post', 'fields' => 'ids'));
	html_assert($wpdb->last_error === '' && in_array($id, $query->posts, true), $query->request);
});
html_test('manual excerpt and AI summary retain precedence over alternative content', function() use ($article) {
	$post = get_post($article);
	update_option('argon_ai_post_summary', 'true');
	update_option('argon_ai_show_post_summary_in_home', 'true');
	update_post_meta($article, 'argon_ai_summary', 'AI 缓存摘要');
	$post->post_excerpt = '手写摘要';
	html_assert(argon_html_excerpt($post) === '手写摘要');
	$post->post_excerpt = '';
	html_assert(argon_html_excerpt($post) === 'AI 缓存摘要');
	update_option('argon_ai_show_post_summary_in_home', 'false');
	html_assert(str_contains(argon_html_excerpt($post), 'alternativeonly'));
	update_option('argon_ai_post_summary', 'false');
	update_option('argon_ai_show_post_summary_in_home', 'true');
});
html_test('native WordPress sideload stores a real attachment ID without a public source file', function() use ($source, $article) {
	$file = wp_tempnam('argon-native.html');
	file_put_contents($file, $source);
	$id = media_handle_sideload(array('name' => 'argon-native.html', 'tmp_name' => $file, 'error' => 0, 'size' => strlen($source)), $article);
	html_assert(!is_wp_error($id), is_wp_error($id) ? $id->get_error_message() : '');
	html_assert(get_post_type($id) === 'attachment' && get_post_mime_type($id) === 'text/html');
	html_assert(get_post_meta($id, '_argon_html_source', true) === $source && !file_exists(get_attached_file($id)));
	html_assert(!str_contains(wp_get_attachment_url($id), '/uploads/') && str_contains(wp_get_attachment_url($id), 'argon_html_source='));
	$GLOBALS['html_media_fixture'] = $id;
});
html_test('media-backed articles resolve the protected attachment source', function() use ($source) {
	$id = $GLOBALS['html_media_fixture'] ?? 0;
	html_assert($id > 0, 'Media fixture missing');
	$post = html_post('文件模式替代正文[argon_html file_id="' . $id . '"]');
	$parts = argon_html_document_parts($post);
	html_assert($parts['html'] === $source && html_response($post)['status'] === 200);
});
html_test('deleted media invalidates document access', function() {
	$id = $GLOBALS['html_media_fixture'] ?? 0;
	$post = html_post('文件删除后仍然保留的替代正文[argon_html file_id="' . $id . '"]');
	wp_delete_attachment($id, true);
	html_assert(html_response($post)['status'] === 404);
	html_assert(str_contains(argon_html_document_text($post), '文件删除后仍然保留的替代正文'));
});
html_test('native upload prefilter rejects untrusted HTML before moving a file', function() use ($author, $source) {
	wp_set_current_user($author);
	$file = wp_tempnam('forbidden.html'); file_put_contents($file, $source);
	$result = argon_html_media_prefilter(array('name' => 'forbidden.html', 'tmp_name' => $file, 'error' => 0));
	html_assert(!empty($result['error']) && file_get_contents($file) === $source);
	wp_delete_file($file);
});
html_test('upload payload is replaced with an inert placeholder before public storage', function() use ($source) {
	$file = wp_tempnam('protected.html'); file_put_contents($file, $source);
	$result = argon_html_media_prefilter(array('name' => 'protected.html', 'tmp_name' => $file, 'error' => 0));
	html_assert(empty($result['error']) && str_ends_with($result['name'], '.txt') && !str_contains(file_get_contents($file), '<html'));
	wp_delete_file($file);
});
html_test('native media picker offers HTML only to trusted users', function() use ($author) {
	$settings = apply_filters('plupload_default_settings', array('filters' => array('mime_types' => array())));
	html_assert(in_array('html,htm', array_column($settings['filters']['mime_types'], 'extensions'), true));
	wp_set_current_user($author);
	$settings = apply_filters('plupload_default_settings', array('filters' => array('mime_types' => array())));
	html_assert(!in_array('html,htm', array_column($settings['filters']['mime_types'], 'extensions'), true));
});
html_test('oversized and invalid UTF-8 sources are rejected', function() {
	html_assert(is_wp_error(argon_html_validate_source(str_repeat('x', ARGON_HTML_MAX_BYTES + 1))));
	html_assert(is_wp_error(argon_html_validate_source("<html>\xC3\x28</html>")));
});
html_test('editor preview is bound to a user capability nonce and editable article', function() use ($article, $source) {
	$response = html_ajax(array('post_id' => (string) $article, '_wpnonce' => wp_create_nonce('argon_html_editor'), 'html' => $source));
	html_assert($response['success'] === true && str_contains($response['data']['url'], 'argon_html_preview'));
	parse_str(wp_parse_url($response['data']['url'], PHP_URL_QUERY), $request);
	html_assert(argon_html_document_response($request)['status'] === 200);
	wp_set_current_user(0);
	html_assert(argon_html_document_response($request)['status'] === 404);
});
html_test('editor preview rejects missing nonce and lower privilege user', function() use ($article, $source, $author) {
	html_assert(html_ajax(array('post_id' => (string) $article, 'html' => $source))['success'] === false);
	wp_set_current_user($author);
	html_assert(html_ajax(array('post_id' => (string) $article, '_wpnonce' => wp_create_nonce('argon_html_editor'), 'html' => $source))['success'] === false);
});
html_test('global disable returns text and stops document endpoints', function() use ($article) {
	update_option('argon_html_enabled', 'false');
	html_assert(html_response($article)['status'] === 404);
	ob_start(); argon_html_render_article($article); $html = ob_get_clean();
	html_assert(!str_contains($html, '<iframe') && str_contains($html, 'alternativeonly'));
	update_option('argon_html_enabled', 'true');
});

restore_error_handler();
$failed = array_filter($results, static fn($result) => $result !== 'passed');
$report = array('php' => PHP_VERSION, 'wordpress' => $GLOBALS['wp_version'], 'passed' => count($results) - count($failed), 'failed' => count($failed), 'results' => $results);
wp_mkdir_p(__DIR__ . '/../temp');
file_put_contents(__DIR__ . '/../temp/html-document-report-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.json', wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo wp_json_encode(array('passed' => $report['passed'], 'failed' => $report['failed'])) . "\n";
if ($failed) { throw new RuntimeException('HTML document regression failures: ' . wp_json_encode($failed)); }
