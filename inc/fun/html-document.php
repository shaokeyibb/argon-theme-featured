<?php
/**
 * Full HTML articles. Source never enters the parent document's DOM.
 * Media integration and editor controls live in html-document-editor.php.
 */

const ARGON_HTML_MAX_BYTES = 1048576;

function argon_html_enabled(): bool {
	return get_option('argon_html_enabled', 'true') === 'true';
}

function argon_html_error(string $message, array $parts = array()): WP_Error {
	return new WP_Error('argon_html_document', $message, array_intersect_key($parts, array_flip(array('fallback', 'source_id', 'post_id'))));
}

function argon_html_validate_source($html) {
	if (!is_string($html) || strlen($html) > ARGON_HTML_MAX_BYTES || strpos($html, "\0") !== false) {
		return argon_html_error(__('HTML 文档必须为不超过 1 MiB 的 UTF-8 文本。', 'argon'));
	}
	$html = preg_replace('/^\xEF\xBB\xBF/', '', $html);
	if (trim($html) === '' || !mb_check_encoding($html, 'UTF-8')) {
		return argon_html_error(__('HTML 文档为空或不是有效的 UTF-8 文本。', 'argon'));
	}
	return $html;
}

function argon_html_height($height): string {
	if (is_string($height) && in_array($height, array('auto', 'viewport'), true)) {
		return $height;
	}
	return is_scalar($height) && preg_match('/^\d{2,4}$/D', (string) $height)
		? (string) max(200, min(2000, (int) $height)) : 'auto';
}

/** Skip examples in Markdown fences, inline code, HTML code elements and comments. */
function argon_html_shortcode_parts(string $content) {
	$pattern = '~(?P<code>^[ \t]*(`{3,}|\x7e{3,})[^\r\n]*\R[\s\S]*?^[ \t]*\2[ \t]*(?:\R|$)|`+[^`\r\n]*`+|<pre\b[^>]*>[\s\S]*?</pre\s*>|<code\b[^>]*>[\s\S]*?</code\s*>|<!--[\s\S]*?-->|\[\[argon_html\b[\s\S]*?\]\])|(?P<document>\[argon_html\b(?P<attributes>(?:[^\]"\']|"[^"]*"|\'[^\']*\')*)\](?:(?P<body>[\s\S]*?)\[/argon_html\])?)~mi';
	if (!preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL)) {
		return null;
	}
	$documents = array_values(array_filter($matches, static fn($match) => $match['document'][1] >= 0));
	if (!$documents) {
		return null;
	}
	if (count($documents) !== 1) {
		return argon_html_error(__('一篇文章只能使用一个 argon_html 文档，其余内容作为替代正文。', 'argon'));
	}
	$match = $documents[0];
	$attributes = shortcode_parse_atts(rtrim($match['attributes'][0], "/ \t\r\n"));
	$attributes = is_array($attributes) ? $attributes : array();
	$body = $match['body'][0] ?? '';
	if (preg_match('/^\s*(`{3,}|\x7e{3,})html[^\S\r\n]*\R([\s\S]*)\R\1\s*$/i', $body, $fence)) {
		$body = $fence[2];
	}
	if (($attributes['encoding'] ?? '') === 'base64') {
		$body = strlen($body) <= ARGON_HTML_MAX_BYTES * 1.5 ? base64_decode(preg_replace('/\s+/', '', $body), true) : false;
		if ($body === false) {
			return argon_html_error(__('HTML 文档编码无效。', 'argon'));
		}
	}
	return array(
		'file_id' => $attributes['file_id'] ?? '0',
		'html' => $body,
		'height' => argon_html_height($attributes['height'] ?? 'auto'),
		'hide_header' => ($attributes['hide_header'] ?? 'false') === 'true',
		'fallback' => substr_replace($content, '', $match['document'][1], strlen($match['document'][0])),
	);
}

/** Find a document block anywhere in the block tree without treating code blocks as declarations. */
function argon_html_block_parts(array $blocks, array &$documents): array {
	$result = array();
	foreach ($blocks as $block) {
		if ($block['blockName'] === 'argon/html-document') {
			$attributes = $block['attrs'];
			$encoded = $attributes['source'] ?? '';
			$html = is_string($encoded) && strlen($encoded) <= ARGON_HTML_MAX_BYTES * 1.5 ? base64_decode($encoded, true) : false;
			$documents[] = array(
				'file_id' => $attributes['fileId'] ?? 0, 'html' => $html,
				'height' => argon_html_height($attributes['height'] ?? 'auto'),
				'hide_header' => ($attributes['hideHeader'] ?? false) === true,
			);
			continue;
		}
		if ($block['blockName'] === 'core/shortcode' || $block['blockName'] === null) {
			$parts = argon_html_shortcode_parts($block['innerHTML']);
			if (is_wp_error($parts)) {
				$documents[] = $parts;
				continue;
			}
			if ($parts !== null) {
				$documents[] = $parts;
				$block['innerHTML'] = $parts['fallback'];
				$block['innerContent'] = array($parts['fallback']);
			}
		}
		if (!empty($block['innerBlocks'])) {
			$before = $block['innerBlocks'];
			$after = array();
			$inner_content = array();
			$index = 0;
			foreach ($block['innerContent'] as $chunk) {
				if ($chunk !== null) {
					$inner_content[] = $chunk;
					continue;
				}
				$children = argon_html_block_parts(array($before[$index++]), $documents);
				foreach ($children as $child) {
					$after[] = $child;
					$inner_content[] = null;
				}
			}
			$block['innerBlocks'] = $after;
			$block['innerContent'] = $inner_content;
		}
		$result[] = $block;
	}
	return $result;
}

function argon_html_source_content(WP_Post $post): string {
	// Githuber converts at save time, not through the_content. Ignore stale filtered content after switching editors.
	$parent = $post->post_type === 'revision' ? $post->post_parent : $post->ID;
	$is_markdown = get_post_meta($post->ID, '_is_githuber_markdown', true) || get_post_meta($parent, '_is_githuber_markdown', true);
	$enabled = get_post_meta($parent, '_is_githuber_markdown_enabled', true);
	if (!$is_markdown || $enabled === 'no' || $post->post_content_filtered === '') {
		return $post->post_content;
	}
	$content = $post->post_content_filtered;
	// Githuber preserves fenced code before saving this field. Use the active parser's public restore API,
	// exactly as its edit_post_content handler does; a generic entity decode would corrupt literal entities.
	global $wp_filter;
	if (isset($wp_filter['content_save_pre'])) {
		foreach ($wp_filter['content_save_pre']->callbacks as $callbacks) {
			foreach ($callbacks as $callback) {
				$function = $callback['function'];
				if (is_array($function) && is_object($function[0]) && $function[1] === 'preserve_code_blocks'
					&& stripos(get_class($function[0]), 'Githuber') !== false && is_callable(array($function[0], 'restore_code_blocks'))) {
					return $function[0]->restore_code_blocks($content);
				}
			}
		}
	}
	// Githuber loads its controller hooks only in editing contexts. The document endpoint is a separate
	// frontend request, so use the same parser API without calling init() or registering editor hooks.
	if (class_exists('Githuber\\Controller\\Markdown')) {
		static $decoder;
		if (!$decoder) {
			$decoder = new \Githuber\Controller\Markdown();
		}
		return $decoder->restore_code_blocks($content);
	}
	return $content;
}

function argon_html_preview_post(WP_Post $post): WP_Post {
	if (is_preview() && current_user_can('edit_post', $post->ID)) {
		$id = argon_request_string($_GET, 'preview_id');
		$nonce = argon_request_string($_GET, 'preview_nonce');
		if ((int) $id === $post->ID && wp_verify_nonce($nonce, 'post_preview_' . $post->ID)) {
			$autosave = wp_get_post_autosave($post->ID, get_current_user_id());
			if ($autosave) {
				return $autosave;
			}
		}
	}
	return $post;
}

function argon_html_document_parts($post = null, bool $preview = true) {
	$post = get_post($post);
	if (!$post || !in_array($post->post_type, array('post', 'page', 'revision'), true)) {
		return null;
	}
	$post = $preview ? argon_html_preview_post($post) : $post;
	$content = argon_html_source_content($post);
	$parts = argon_html_shortcode_parts($content);
	if (is_wp_error($parts)) {
		return $parts;
	}
	// Extract an enclosing shortcode before parsing blocks: an HTML source can itself contain block comments.
	$remaining = is_array($parts) ? $parts['fallback'] : $content;
	if (has_blocks($remaining)) {
		$documents = $parts !== null ? array($parts) : array();
		$fallback = argon_html_block_parts(parse_blocks($remaining), $documents);
		if (!$documents) {
			return null;
		}
		if (count($documents) !== 1) {
			return argon_html_error(__('一篇文章只能使用一个 HTML 文档。', 'argon'));
		}
		$parts = $documents[0];
		if (is_wp_error($parts)) {
			return $parts;
		}
		$parts['fallback'] = serialize_blocks($fallback);
	}
	if ($parts === null || is_wp_error($parts)) {
		return $parts;
	}
	$parts['source_id'] = $post->ID;
	$parts['post_id'] = $post->post_parent && $post->post_type === 'revision' ? $post->post_parent : $post->ID;
	$parts['file_id'] = filter_var($parts['file_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
	if ($parts['file_id'] === false) {
		return argon_html_error(__('file_id 必须是媒体库附件 ID。', 'argon'), $parts);
	}
	if ($parts['file_id']) {
		$attachment = get_post($parts['file_id']);
		if (!$attachment || $attachment->post_type !== 'attachment' || $attachment->post_status === 'trash' || !get_post_meta($attachment->ID, '_argon_html_media', true)) {
			return argon_html_error(__('请选择通过 WordPress 媒体库上传的受保护 HTML 文档。', 'argon'), $parts);
		}
		$parts['html'] = get_post_meta($attachment->ID, '_argon_html_source', true);
		$signature = get_post_meta($attachment->ID, '_argon_html_source_signature', true);
		if (!is_string($signature) || !hash_equals(argon_html_signature($parts['html']), $signature)) {
			return argon_html_error(__('HTML 附件源码校验失败，请重新上传。', 'argon'), $parts);
		}
	}
	$html = argon_html_validate_source($parts['html']);
	if (is_wp_error($html)) {
		return argon_html_error($html->get_error_message(), $parts);
	}
	$parts['html'] = $html;
	return $parts;
}

function argon_html_signature(string $source): string {
	return hash_hmac('sha256', $source, wp_salt('auth'));
}

function argon_html_document_signature(array $parts): string {
	return argon_html_signature($parts['post_id'] . ':' . $parts['file_id'] . ':' . $parts['html']);
}

function argon_html_document_approved(array $parts): bool {
	$signature = get_post_meta($parts['source_id'], '_argon_html_approval', true);
	return is_string($signature) && hash_equals(argon_html_document_signature($parts), $signature);
}

function argon_html_can_author(array $parts): bool {
	return current_user_can('unfiltered_html') && current_user_can('edit_post', $parts['post_id'])
		&& (!$parts['file_id'] || current_user_can('edit_post', $parts['file_id']));
}

/** Sign exact source, not the author's role. Lower-privilege edits cannot activate new HTML. */
function argon_html_on_save(int $post_id, WP_Post $post): void {
	$parts = argon_html_document_parts($post, false);
	if ($parts === null) {
		delete_metadata('post', $post_id, '_argon_html_approval');
		delete_metadata('post', $post_id, '_argon_html_search_text');
		return;
	}
	if (is_wp_error($parts)) {
		delete_metadata('post', $post_id, '_argon_html_approval');
		delete_metadata('post', $post_id, '_argon_html_search_text');
		return;
	}
	$parent_approval = $post->post_type === 'revision' ? get_post_meta($parts['post_id'], '_argon_html_approval', true) : '';
	if (argon_html_can_author($parts) || (is_string($parent_approval) && hash_equals(argon_html_document_signature($parts), $parent_approval))) {
		// update_post_meta redirects revision writes to the parent; that would leak draft text into its search index.
		update_metadata('post', $post_id, '_argon_html_approval', argon_html_document_signature($parts));
	}
	if (argon_html_document_approved($parts)) {
		update_metadata('post', $post_id, '_argon_html_search_text', wp_slash(mb_substr(argon_html_document_text($post), 0, 100000, 'UTF-8')));
	} else {
		delete_metadata('post', $post_id, '_argon_html_search_text');
	}
}
add_action('wp_after_insert_post', 'argon_html_on_save', 30, 2);
// Core creates revisions with fire_after_hooks=false; their approval must be stored too.
add_action('save_post', 'argon_html_on_save', 99, 2);
add_action('_wp_put_post_revision', static function($id) {
	$post = get_post($id);
	if ($post) {
		argon_html_on_save((int) $id, $post);
	}
});

function argon_html_dom(string $html): DOMDocument {
	$previous = libxml_use_internal_errors(true);
	$dom = new DOMDocument('1.0', 'UTF-8');
	// Encoding the entire source as entities corrupts non-ASCII script/style text.
	// The processing instruction tells libxml the encoding without rewriting source.
	$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
	foreach (iterator_to_array($dom->childNodes) as $node) {
		if ($node->nodeType === XML_PI_NODE) $dom->removeChild($node);
	}
	libxml_clear_errors();
	libxml_use_internal_errors($previous);
	return $dom;
}

function argon_html_plain_text(string $html): string {
	if (trim($html) === '') {
		return '';
	}
	$dom = argon_html_dom($html);
	$xpath = new DOMXPath($dom);
	foreach ($xpath->query('//head|//script|//style|//template|//noscript|//*[@hidden or @aria-hidden="true"]') as $node) {
		if ($node->parentNode) {
			$node->parentNode->removeChild($node);
		}
	}
	foreach ($xpath->query('//p|//div|//section|//article|//li|//tr|//h1|//h2|//h3|//h4|//h5|//h6|//br') as $node) {
		if ($node->parentNode) {
			$node->parentNode->insertBefore($dom->createTextNode("\n"), $node->nextSibling);
		}
	}
	return trim(preg_replace('/\n[\s\n]*\n/u', "\n\n", preg_replace('/[^\S\n]+/u', ' ', $dom->textContent)));
}

/** Use the already converted fallback for Githuber; never render the raw HTML declaration. */
function argon_html_fallback_markup(WP_Post $post, array $parts): string {
	$raw = argon_html_source_content($post);
	$fallback = $parts['fallback'];
	if ($raw !== $post->post_content) {
		$converted = argon_html_shortcode_parts($post->post_content);
		if (is_array($converted)) {
			$fallback = $converted['fallback'];
		}
	}
	$GLOBALS['argon_html_rendering_fallback'] = ($GLOBALS['argon_html_rendering_fallback'] ?? 0) + 1;
	$previous_post = $GLOBALS['post'] ?? null;
	$GLOBALS['post'] = $post;
	try {
		$html = apply_filters('the_content', $fallback);
		if (trim($html) === '') {
			return '';
		}
		// KSES removes tags but leaves their text; remove script/style contents first so they cannot
		// become visible fallback text or AI input (including Argon's per-post CSS filter).
		$dom = argon_html_dom($html);
		$xpath = new DOMXPath($dom);
		foreach ($xpath->query('//head|//script|//style|//template|//noscript') as $node) {
			if ($node->parentNode) {
				$node->parentNode->removeChild($node);
			}
		}
		$body = $dom->getElementsByTagName('body')->item(0);
		$html = '';
		if ($body) {
			foreach ($body->childNodes as $node) {
				$html .= $dom->saveHTML($node);
			}
		}
		return wp_kses_post($html);
	} finally {
		$GLOBALS['post'] = $previous_post;
		$GLOBALS['argon_html_rendering_fallback']--;
	}
}

function argon_html_document_text($post = null): string {
	$post = get_post($post);
	$parts = argon_html_document_parts($post);
	if ($post && is_wp_error($parts)) {
		$fallback = $parts->get_error_data();
		return is_array($fallback) && isset($fallback['source_id'], $fallback['fallback'])
			? argon_html_plain_text(argon_html_fallback_markup(get_post($fallback['source_id']), $fallback)) : '';
	}
	if (!$post || !is_array($parts)) {
		return '';
	}
	$post = get_post($parts['source_id']);
	$text = argon_html_plain_text(argon_html_fallback_markup($post, $parts));
	if ($text !== '') {
		return $text;
	}
	return argon_html_document_approved($parts) || argon_html_can_author($parts) ? argon_html_plain_text($parts['html']) : '';
}

function argon_html_has_document($post = null): bool {
	return argon_html_document_parts($post) !== null;
}

function argon_html_hide_header($post = null): bool {
	$parts = argon_html_document_parts($post);
	return argon_html_enabled() && is_array($parts) && $parts['hide_header'] && !post_password_required($parts['post_id']);
}

function argon_html_excerpt($post = null, int $length = 175): string {
	$post = get_post($post);
	if (!$post || post_password_required($post)) {
		return __('这篇文章受密码保护，输入密码才能阅读', 'argon');
	}
	$text = $post->post_excerpt;
	if ($text === '' && get_option('argon_ai_post_summary', 'false') === 'true'
		&& get_post_meta($post->ID, 'argon_ai_post_summary', true) !== 'false'
		&& get_option('argon_ai_show_post_summary_in_home', 'true') !== 'false') {
		$text = get_post_meta($post->ID, 'argon_ai_summary', true);
	}
	if ($text === '') {
		$text = argon_html_document_text($post);
	}
	return wp_trim_words(wp_strip_all_tags($text), $length, '…');
}

function argon_html_filter_content(string $content): string {
	if (!empty($GLOBALS['argon_html_rendering_fallback'])) {
		return $content;
	}
	$post = get_post();
	if (!$post || !argon_html_has_document($post)) {
		return $content;
	}
	if (post_password_required($post)) {
		return esc_html__('这篇文章受密码保护，输入密码才能阅读', 'argon');
	}
	return nl2br(esc_html(argon_html_document_text($post)));
}
add_filter('the_content', 'argon_html_filter_content', -9999);
add_filter('get_the_excerpt', static function($excerpt, $post) {
	return argon_html_has_document($post) ? argon_html_excerpt($post, (int) get_option('argon_trim_words_count', 175)) : $excerpt;
}, 20, 2);

add_shortcode('argon_html', static function() {
	// Only the article template creates frames. Widgets, feeds and nested shortcodes remain inert.
	return '';
});

function argon_html_search_text(string $search, WP_Query $query): string {
	if (is_admin() || !$query->is_search() || $search === '') {
		return $search;
	}
	global $wpdb;
	$column = $wpdb->posts . '.post_content';
	$projection = "COALESCE((SELECT argon_html_index.meta_value FROM {$wpdb->postmeta} argon_html_index WHERE argon_html_index.post_id = {$wpdb->posts}.ID AND argon_html_index.meta_key = '_argon_html_search_text' LIMIT 1), {$column})";
	// Never replace a column-like search term inside a SQL string literal.
	$quoted = <<<'REGEX'
'(?:''|\\.|[^'\\])*'|"(?:""|\\.|[^"\\])*"
REGEX;
	$pattern = '~' . $quoted . '|\b' . preg_quote($column, '~') . '\b~s';
	return preg_replace_callback($pattern, static fn($match) => $match[0] === $column ? $projection : $match[0], $search) ?? $search;
}
add_filter('posts_search', 'argon_html_search_text', 20, 2);

function argon_html_sandbox(): string {
	$permissions = array('scripts' => true, 'popups' => false, 'downloads' => false, 'modals' => false);
	$tokens = array();
	foreach ($permissions as $permission => $default) {
		if (get_option('argon_html_allow_' . $permission, $default ? 'true' : 'false') === 'true') {
			$tokens[] = 'allow-' . $permission;
		}
	}
	return implode(' ', $tokens);
}

/** Exact HTTPS origins only: no wildcards, credentials, paths, CSP separators or network schemes. */
function argon_html_resource_origins($value): array {
	if (!is_string($value)) {
		return array();
	}
	$origins = array();
	foreach (preg_split('/\s+/', trim($value)) as $origin) {
		$parts = wp_parse_url($origin);
		if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
			|| isset($parts['query']) || isset($parts['fragment']) || !in_array($parts['path'] ?? '', array('', '/'), true)
			|| !preg_match('/^[a-z0-9.-]+$/iD', $parts['host']) || strpos($parts['host'], '..') !== false) {
			continue;
		}
		$origins[] = 'https://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
	}
	return array_values(array_unique($origins));
}

function argon_html_csp(string $base): string {
	$origin = wp_parse_url(home_url('/'));
	$site = $origin['scheme'] . '://' . $origin['host'] . (isset($origin['port']) ? ':' . $origin['port'] : '');
	$resources = implode(' ', array_unique(array_merge(array($site), argon_html_resource_origins(get_option('argon_html_resource_origins', '')))));
	$scripts = strpos(argon_html_sandbox(), 'allow-scripts') !== false ? "'unsafe-inline' {$resources}" : "'none'";
	return "sandbox " . argon_html_sandbox() . "; default-src 'none'; script-src {$scripts}; style-src 'unsafe-inline' {$resources}; img-src data: blob: {$resources}; font-src data: {$resources}; media-src data: blob: {$resources}; connect-src 'none'; object-src 'none'; frame-src 'none'; worker-src 'none'; form-action 'none'; base-uri {$resources}; frame-ancestors {$site}";
}

function argon_html_frame_url(array $parts, string $token): string {
	$args = array('argon_html_document' => $parts['post_id'], 'instance' => $token, 'v' => hash('sha256', $parts['html']));
	if ($parts['source_id'] !== $parts['post_id']) {
		$args['revision'] = $parts['source_id'];
		$args['_wpnonce'] = wp_create_nonce('argon_html_view_' . $parts['post_id'] . '_' . $parts['source_id']);
	}
	return add_query_arg($args, home_url('/'));
}

function argon_html_document_base(array $parts): string {
	if (!$parts['file_id']) {
		return get_permalink($parts['post_id']);
	}
	$uploads = wp_upload_dir();
	$relative = get_post_meta($parts['file_id'], '_wp_attached_file', true);
	$directory = is_string($relative) ? wp_normalize_path(dirname($relative)) : '.';
	if ($directory === '.' || str_contains($directory, '..') || !preg_match('~^[a-zA-Z0-9_/-]+$~D', $directory)) {
		$directory = '';
	}
	return trailingslashit($uploads['baseurl']) . ($directory !== '' ? trailingslashit($directory) : '');
}

function argon_html_render_article($post = null): bool {
	$post = get_post($post);
	$parts = argon_html_document_parts($post);
	if ($parts === null) {
		return false;
	}
	if (post_password_required($post)) {
		return false;
	}
	if (is_wp_error($parts) || !argon_html_enabled() || !argon_html_document_approved($parts)) {
		$message = is_wp_error($parts) ? $parts->get_error_message() : __('HTML 文档未启用或源码已更改，请由有 unfiltered_html 权限的用户保存文章。', 'argon');
		if (current_user_can('edit_post', $post->ID)) {
			echo '<p class="argon-html-notice" role="status">' . esc_html($message) . '</p>';
		}
		$text = argon_html_document_text($post);
		if ($text !== '') {
			echo nl2br(esc_html($text));
		} elseif (!current_user_can('edit_post', $post->ID)) {
			echo '<p class="argon-html-notice">' . esc_html__('HTML 文档暂不可用。', 'argon') . '</p>';
		}
		return true;
	}
	$token = wp_generate_uuid4();
	$scripts = strpos(argon_html_sandbox(), 'allow-scripts') !== false;
	$height = $scripts ? $parts['height'] : ($parts['height'] === 'auto' ? 'viewport' : $parts['height']);
	echo '<div class="argon-html-document"><iframe class="argon-html-frame" title="' . esc_attr(get_the_title($post)) . '" src="' . esc_url(argon_html_frame_url($parts, $token)) . '" sandbox="' . esc_attr(argon_html_sandbox()) . '" referrerpolicy="no-referrer" allow="camera \'none\'; microphone \'none\'; geolocation \'none\'; payment \'none\'; fullscreen \'none\'; clipboard-read \'none\'; clipboard-write \'none\'" data-argon-html-instance="' . esc_attr($token) . '" data-height="' . esc_attr($height) . '" height="640"></iframe>';
	$text = argon_html_document_text($post);
	if ($text !== '') {
		echo '<details class="argon-html-text"><summary>' . esc_html__('文本阅读', 'argon') . '</summary><div>';
		$fallback = argon_html_fallback_markup(get_post($parts['source_id']), $parts);
		echo argon_html_plain_text($fallback) !== '' ? $fallback : nl2br(esc_html($text));
		echo '</div></details>';
	}
	echo '</div>';
	return true;
}

function argon_html_prepare_document(string $html, string $base, string $token, string $document_url = ''): string {
	$dom = argon_html_dom($html);
	$head = $dom->getElementsByTagName('head')->item(0);
	if (!$head) {
		$head = $dom->createElement('head');
		$dom->documentElement->insertBefore($head, $dom->documentElement->firstChild);
	}
	foreach (iterator_to_array($dom->getElementsByTagName('base')) as $node) {
		$node->parentNode->removeChild($node);
	}
	foreach (iterator_to_array($dom->getElementsByTagName('meta')) as $node) {
		if ($node->hasAttribute('http-equiv') || strtolower($node->getAttribute('name')) === 'viewport' || $node->hasAttribute('charset')) {
			$node->parentNode->removeChild($node);
		}
	}
	$charset = $dom->createElement('meta');
	$charset->setAttribute('charset', 'UTF-8');
	$head->insertBefore($charset, $head->firstChild);
	$viewport = $dom->createElement('meta');
	$viewport->setAttribute('name', 'viewport');
	$viewport->setAttribute('content', 'width=device-width, initial-scale=1');
	$head->appendChild($viewport);
	$base_element = $dom->createElement('base');
	$base_element->setAttribute('href', $base);
	$head->appendChild($base_element);
	// Keep intrinsic code/media widths from expanding nested grid and flex containers.
	// Inline defaults also apply when document scripts are disabled by the sandbox.
	$style = $dom->createElement('style');
	$style->setAttribute('data-argon-html-layout', '');
	$style->appendChild($dom->createTextNode(file_get_contents(get_template_directory() . '/assets/html-document/document.css')));
	$head->appendChild($style);
	foreach ($dom->getElementsByTagName('a') as $link) {
		if ($document_url !== '' && str_starts_with($link->getAttribute('href'), '#')) {
			$link->setAttribute('href', $document_url . $link->getAttribute('href'));
		}
		if ($link->getAttribute('target') === '_blank') {
			$link->setAttribute('rel', 'noopener noreferrer');
		}
	}
	if (strpos(argon_html_sandbox(), 'allow-scripts') !== false) {
		$bridge = $dom->createElement('script');
		$settings = 'window.argonHtmlBridge=' . argon_json(array('instance' => $token, 'parentOrigin' => wp_parse_url(home_url(), PHP_URL_SCHEME) . '://' . wp_parse_url(home_url(), PHP_URL_HOST) . (wp_parse_url(home_url(), PHP_URL_PORT) ? ':' . wp_parse_url(home_url(), PHP_URL_PORT) : ''))) . ';';
		$bridge->appendChild($dom->createTextNode($settings . file_get_contents(get_template_directory() . '/assets/html-document/document-bridge.js')));
		$head->insertBefore($bridge, $head->firstChild);
	}
	return '<!doctype html>' . $dom->saveHTML($dom->documentElement);
}

/** Return a response value for testing; only the dispatcher sends headers and exits. */
function argon_html_document_response(array $request): array {
	$denied = array('status' => 404, 'body' => '', 'headers' => array('X-Robots-Tag' => 'noindex, nofollow, noarchive', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'));
	if (!argon_html_enabled()) {
		return $denied;
	}
	$token = argon_request_string($request, 'instance');
	if (!preg_match('/^[a-f0-9-]{36}$/D', $token)) {
		return $denied;
	}
	$base = home_url('/');
	if (isset($request['argon_html_preview'])) {
		$key = argon_request_string($request, 'argon_html_preview');
		if (!preg_match('/^[a-f0-9]{32}$/D', $key) || !current_user_can('unfiltered_html') || !wp_verify_nonce(argon_request_string($request, '_wpnonce'), 'argon_html_preview_' . $key)) {
			return $denied;
		}
		$preview = get_transient('argon_html_preview_' . get_current_user_id() . '_' . $key);
		if (!is_array($preview) || !current_user_can('edit_post', $preview['post_id'])) {
			return $denied;
		}
		$html = $preview['html'];
		$base = $preview['base'] ?? get_permalink($preview['post_id']);
	} else {
		$id = filter_var(argon_request_string($request, 'argon_html_document'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
		$post = $id ? get_post($id) : null;
		if (!$post || !in_array($post->post_type, array('post', 'page'), true) || !argon_can_view_post($post->ID)) {
			return $denied;
		}
		$base = get_permalink($post);
		if (isset($request['revision'])) {
			$revision = get_post((int) argon_request_string($request, 'revision'));
			if (!$revision || $revision->post_type !== 'revision' || $revision->post_parent !== $post->ID || !current_user_can('edit_post', $post->ID)
				|| !wp_verify_nonce(argon_request_string($request, '_wpnonce'), 'argon_html_view_' . $post->ID . '_' . $revision->ID)) {
				return $denied;
			}
			$post = $revision;
		}
		$parts = argon_html_document_parts($post, false);
		if (!is_array($parts) || !argon_html_document_approved($parts)) {
			return $denied;
		}
		$html = $parts['html'];
		$base = argon_html_document_base($parts);
	}
	$document_url = add_query_arg($request, home_url('/'));
	return array('status' => 200, 'body' => argon_html_prepare_document($html, $base, $token, $document_url), 'headers' => array_merge($denied['headers'], array(
		'Content-Type' => 'text/html; charset=UTF-8', 'Content-Security-Policy' => argon_html_csp($base),
		'Referrer-Policy' => 'no-referrer', 'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), fullscreen=(), clipboard-read=(), clipboard-write=()',
	)));
}

function argon_html_dispatch_document(): void {
	if (!isset($_GET['argon_html_document']) && !isset($_GET['argon_html_preview'])) {
		return;
	}
	$response = argon_html_document_response($_GET);
	status_header($response['status']);
	foreach ($response['headers'] as $name => $value) {
		header($name . ': ' . $value);
	}
	echo $response['body'];
	exit;
}
add_action('template_redirect', 'argon_html_dispatch_document', -100);

function argon_html_enqueue_assets(): void {
	$directory = get_template_directory();
	$url = get_template_directory_uri() . '/assets/html-document/';
	if (!is_singular() || !argon_html_has_document(get_queried_object())) { return; }
	wp_enqueue_style('argon-html-document', $url . 'style.css', array(), (string) filemtime($directory . '/assets/html-document/style.css'));
	wp_enqueue_script('argon-html-document', $url . 'host.js', array(), (string) filemtime($directory . '/assets/html-document/host.js'), array('in_footer' => true, 'strategy' => 'defer'));
}
add_action('wp_enqueue_scripts', 'argon_html_enqueue_assets');

require_once __DIR__ . '/html-document-editor.php';
