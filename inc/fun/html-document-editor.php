<?php
/** WordPress-native media uploads, editor controls and theme settings for HTML articles. */

function argon_html_register_meta(): void {
	foreach (array('_argon_html_source', '_argon_html_source_signature', '_argon_html_media', '_argon_html_filename', '_argon_html_approval', '_argon_html_search_text') as $key) {
		// Internal state is written only by these handlers, never by custom-field, REST or XML-RPC clients.
		register_meta('post', $key, array('type' => 'string', 'single' => true, 'show_in_rest' => false, 'auth_callback' => '__return_false'));
	}
	$url = get_template_directory_uri() . '/assets/html-document/';
	wp_register_style('argon-html-document-editor', $url . 'style.css', array(), (string) filemtime(get_template_directory() . '/assets/html-document/style.css'));
	wp_register_script('argon-html-editor', $url . 'editor.js', array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-data', 'wp-i18n'),
		(string) filemtime(get_template_directory() . '/assets/html-document/editor.js'), true);
	wp_localize_script('argon-html-editor', 'argonHtmlEditor', array(
		'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('argon_html_editor'),
		'canAuthor' => current_user_can('unfiltered_html'), 'maxBytes' => ARGON_HTML_MAX_BYTES,
		'postId' => get_the_ID() ?: 0,
	));
	register_block_type('argon/html-document', array(
		'api_version' => 3, 'editor_script' => 'argon-html-editor', 'editor_style' => 'argon-html-document-editor',
		'attributes' => array('source' => array('type' => 'string', 'default' => ''), 'fileId' => array('type' => 'number', 'default' => 0),
			'height' => array('type' => 'string', 'default' => 'auto'), 'hideHeader' => array('type' => 'boolean', 'default' => false)),
		'render_callback' => static fn() => '',
	));
}
add_action('init', 'argon_html_register_meta');

/**
 * Let the normal media picker select .html, without adding executable HTML to upload_mimes.
 * Native wp_handle_upload/wp_handle_sideload prefilters replace the temporary payload BEFORE it can enter uploads.
 */
add_filter('plupload_default_settings', static function($settings) {
	if (argon_html_enabled() && current_user_can('unfiltered_html') && current_user_can('upload_files')) {
		$settings['filters']['mime_types'][] = array('title' => __('HTML 文档', 'argon'), 'extensions' => 'html,htm');
	}
	return $settings;
});

function argon_html_media_prefilter(array $file): array {
	unset($GLOBALS['argon_html_upload_pending']);
	if (!is_string($file['name'] ?? null)) {
		$file['error'] = __('文件名无效。', 'argon');
		return $file;
	}
	if (!preg_match('/\.html?$/iD', $file['name'])) {
		return $file;
	}
	if (!argon_html_enabled() || !current_user_can('upload_files') || !current_user_can('unfiltered_html')) {
		$file['error'] = __('上传 HTML 文档需要 upload_files 和 unfiltered_html 权限。', 'argon');
		return $file;
	}
	if (!empty($file['error']) || !is_string($file['tmp_name'] ?? null) || !is_readable($file['tmp_name']) || filesize($file['tmp_name']) > ARGON_HTML_MAX_BYTES) {
		$file['error'] = __('HTML 文档不能超过 1 MiB。', 'argon');
		return $file;
	}
	$html = argon_html_validate_source(file_get_contents($file['tmp_name']));
	if (is_wp_error($html)) {
		$file['error'] = $html->get_error_message();
		return $file;
	}
	$name = sanitize_file_name($file['name']);
	// The original payload exists only in the PHP upload temp directory and this request's memory.
	// Even a failed attachment insert can leave only this harmless placeholder in a public directory.
	$placeholder = 'Protected Argon HTML document: ' . wp_generate_uuid4();
	if (file_put_contents($file['tmp_name'], $placeholder) !== strlen($placeholder)) {
		$file['error'] = __('无法保护 HTML 上传文件。', 'argon');
		return $file;
	}
	$GLOBALS['argon_html_upload_pending'] = array('html' => $html, 'filename' => $name, 'placeholder' => $placeholder);
	$file['name'] = $name . '.argon-html.txt';
	$file['type'] = 'text/plain';
	$file['size'] = strlen($placeholder);
	return $file;
}
add_filter('wp_handle_upload_prefilter', 'argon_html_media_prefilter', PHP_INT_MAX);
add_filter('wp_handle_sideload_prefilter', 'argon_html_media_prefilter', PHP_INT_MAX);

function argon_html_media_uploaded(array $upload): array {
	if (!empty($GLOBALS['argon_html_upload_pending']) && isset($upload['file']) && str_ends_with($upload['file'], '.txt') && empty($upload['error'])
		&& is_readable($upload['file']) && file_get_contents($upload['file']) === $GLOBALS['argon_html_upload_pending']['placeholder']) {
		$GLOBALS['argon_html_uploaded_files'][$upload['file']] = $GLOBALS['argon_html_upload_pending'];
		unset($GLOBALS['argon_html_upload_pending']);
		$upload['type'] = 'text/html';
	}
	return $upload;
}
add_filter('wp_handle_upload', 'argon_html_media_uploaded');
add_filter('wp_handle_sideload', 'argon_html_media_uploaded');

function argon_html_media_attached(int $id): void {
	$file = get_attached_file($id);
	$pending = $GLOBALS['argon_html_uploaded_files'][$file] ?? null;
	if (!is_array($pending)) {
		return;
	}
	unset($GLOBALS['argon_html_uploaded_files'][$file]);
	update_post_meta($id, '_argon_html_source', wp_slash($pending['html']));
	update_post_meta($id, '_argon_html_source_signature', argon_html_signature($pending['html']));
	update_post_meta($id, '_argon_html_filename', $pending['filename']);
	update_post_meta($id, '_argon_html_media', 'true');
	// Core retains an attachment ID and handles ownership, trash, deletion and exports.
	// Remove the harmless placeholder too: there is no publicly readable source file.
	wp_delete_file($file);
}
add_action('add_attachment', 'argon_html_media_attached');

function argon_html_media_source_url(int $id): string {
	$args = array('argon_html_source' => $id);
	if (current_user_can('unfiltered_html') && current_user_can('edit_post', $id)) {
		$args['_wpnonce'] = wp_create_nonce('argon_html_source_' . $id);
	}
	return add_query_arg($args, home_url('/'));
}
add_filter('wp_get_attachment_url', static function($url, $id) {
	return get_post_meta($id, '_argon_html_media', true) ? argon_html_media_source_url($id) : $url;
}, 10, 2);
add_filter('wp_prepare_attachment_for_js', static function($response, $attachment) {
	if (get_post_meta($attachment->ID, '_argon_html_media', true)) {
		$response['argonHtmlDocument'] = true;
		$response['filename'] = get_post_meta($attachment->ID, '_argon_html_filename', true);
		$response['filesizeInBytes'] = strlen(get_post_meta($attachment->ID, '_argon_html_source', true));
		$response['filesizeHumanReadable'] = size_format($response['filesizeInBytes']);
		$response['url'] = argon_html_media_source_url($attachment->ID);
		$response['link'] = $response['url'];
	}
	return $response;
}, 10, 2);

function argon_html_dispatch_source(): void {
	if (is_attachment() && get_post_meta(get_queried_object_id(), '_argon_html_media', true)) {
		status_header(404);
		header('X-Robots-Tag: noindex, nofollow, noarchive');
		nocache_headers();
		exit;
	}
	if (!isset($_GET['argon_html_source'])) {
		return;
	}
	$id = (int) argon_request_string($_GET, 'argon_html_source');
	$source = get_post_meta($id, '_argon_html_source', true);
	$allowed = get_post_meta($id, '_argon_html_media', true) && get_post_status($id) !== 'trash'
		&& current_user_can('unfiltered_html') && current_user_can('edit_post', $id)
		&& wp_verify_nonce(argon_request_string($_GET, '_wpnonce'), 'argon_html_source_' . $id);
	status_header($allowed ? 200 : 404);
	nocache_headers();
	header('X-Robots-Tag: noindex, nofollow, noarchive');
	header('X-Content-Type-Options: nosniff');
	header('Content-Type: text/plain; charset=UTF-8');
	header("Content-Security-Policy: sandbox; default-src 'none'");
	if ($allowed) {
		header('Content-Disposition: attachment; filename="document-' . $id . '.html"');
		echo $source;
	}
	exit;
}
add_action('template_redirect', 'argon_html_dispatch_source', -99);

function argon_html_editor_ajax_post(): int {
	$id = filter_var(argon_request_string($_POST, 'post_id'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
	if (!wp_verify_nonce(argon_request_string($_POST, '_wpnonce'), 'argon_html_editor') || !current_user_can('unfiltered_html')
		|| !$id || !current_user_can('edit_post', $id) || !in_array(get_post_type($id), array('post', 'page'), true)) {
		wp_send_json_error(array('message' => __('无权编辑 HTML 文档或请求已过期。', 'argon')), 403);
	}
	return $id;
}

function argon_html_editor_preview(): void {
	$id = argon_html_editor_ajax_post();
	if (!argon_html_enabled()) {
		wp_send_json_error(array('message' => __('请先在 Argon 主题选项中启用 HTML 文档文章。', 'argon')), 400);
	}
	$file_id = (int) argon_request_string($_POST, 'file_id');
	$html = argon_request_string($_POST, 'html');
	if ($file_id) {
		if (!current_user_can('edit_post', $file_id) || !get_post_meta($file_id, '_argon_html_media', true)) {
			wp_send_json_error(array('message' => __('无权读取该 HTML 附件。', 'argon')), 403);
		}
		$html = get_post_meta($file_id, '_argon_html_source', true);
	}
	$html = argon_html_validate_source($html);
	if (is_wp_error($html)) {
		wp_send_json_error(array('message' => $html->get_error_message()), 400);
	}
	$key = bin2hex(random_bytes(16));
	$base = $file_id ? argon_html_document_base(array('file_id' => $file_id, 'post_id' => $id)) : get_permalink($id);
	set_transient('argon_html_preview_' . get_current_user_id() . '_' . $key, array('html' => $html, 'post_id' => $id, 'base' => $base), 10 * MINUTE_IN_SECONDS);
	$token = wp_generate_uuid4();
	$url = add_query_arg(array('argon_html_preview' => $key, 'instance' => $token, '_wpnonce' => wp_create_nonce('argon_html_preview_' . $key)), home_url('/'));
	wp_send_json_success(array('url' => $url, 'sandbox' => argon_html_sandbox(), 'instance' => $token));
}
add_action('wp_ajax_argon_html_editor_preview', 'argon_html_editor_preview');

function argon_html_editor_assets(string $hook): void {
	if (!in_array($hook, array('post.php', 'post-new.php'), true) || !in_array(get_post_type(), array('post', 'page'), true)) {
		return;
	}
	wp_enqueue_media();
	if (function_exists('use_block_editor_for_post') && use_block_editor_for_post(get_post())) {
		wp_enqueue_script('argon-html-editor');
	} else {
		// Do not pull Gutenberg into Githuber MD / Classic Editor.
		wp_enqueue_script('argon-html-classic-editor', get_template_directory_uri() . '/assets/html-document/editor.js', array(),
			(string) filemtime(get_template_directory() . '/assets/html-document/editor.js'), true);
		wp_localize_script('argon-html-classic-editor', 'argonHtmlEditor', array('ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('argon_html_editor'), 'canAuthor' => current_user_can('unfiltered_html'),
			'maxBytes' => ARGON_HTML_MAX_BYTES, 'postId' => get_the_ID() ?: 0));
	}
	wp_enqueue_style('argon-html-document', get_template_directory_uri() . '/assets/html-document/style.css', array(),
		(string) filemtime(get_template_directory() . '/assets/html-document/style.css'));
	wp_enqueue_script('argon-html-document', get_template_directory_uri() . '/assets/html-document/host.js', array(),
		(string) filemtime(get_template_directory() . '/assets/html-document/host.js'), true);
}
add_action('admin_enqueue_scripts', 'argon_html_editor_assets');

add_action('add_meta_boxes', static function() {
	if (get_post() && use_block_editor_for_post(get_post())) return;
	if (!current_user_can('unfiltered_html')) {
		return;
	}
	add_meta_box('argon_html_document_editor', __('HTML 文档文章', 'argon'), static function($post) {
		$parts = argon_html_document_parts($post, false);
		$html = is_array($parts) && !$parts['file_id'] ? $parts['html'] : '';
		?>
		<p><?php esc_html_e('使用一个 argon_html 短代码或 HTML 文档区块替换文章正文。声明以外的文章内容作为替代正文，用于摘要、搜索、RSS 和文本阅读。', 'argon'); ?></p>
		<div id="argon-html-classic-tools" data-post-id="<?php echo esc_attr($post->ID); ?>">
			<p><button type="button" class="button" data-argon-html-select><?php esc_html_e('从媒体库选择 HTML', 'argon'); ?></button> <span data-argon-html-file><?php if (is_array($parts) && $parts['file_id']) { echo esc_html((string) $parts['file_id']); } ?></span></p>
			<label for="argon-html-source"><?php esc_html_e('完整 HTML 源码（在此粘贴，插入时会安全编码保存）', 'argon'); ?></label>
			<textarea id="argon-html-source" class="widefat code" rows="10" spellcheck="false"><?php echo esc_textarea($html); ?></textarea>
			<p><label><?php esc_html_e('文档高度', 'argon'); ?> <select data-argon-html-height><?php foreach (array('auto', 'viewport', '800') as $height) { echo '<option value="' . esc_attr($height) . '"' . selected(is_array($parts) ? $parts['height'] : 'auto', $height, false) . '>' . esc_html($height) . '</option>'; } ?></select></label> <label><input type="checkbox" data-argon-html-hide-header <?php checked(is_array($parts) && $parts['hide_header']); ?>> <?php esc_html_e('隐藏主题文章标题和元信息', 'argon'); ?></label></p>
			<p><button type="button" class="button button-primary" data-argon-html-insert><?php esc_html_e('插入或更新文档声明', 'argon'); ?></button> <button type="button" class="button" data-argon-html-preview><?php esc_html_e('安全预览', 'argon'); ?></button></p>
			<p class="description"><?php esc_html_e('HTML 文件通过 WordPress 原生媒体库上传（最大 1 MiB，UTF-8）。内联源码不会进入编辑器原生 HTML 预览。外部资源需在主题选项中加入白名单。', 'argon'); ?></p>
			<p role="status" data-argon-html-status></p><div data-argon-html-preview-container></div>
		</div>
		<?php
	}, array('post', 'page'), 'normal', 'default');
});

function argon_html_settings_fields(): void {
	$settings = array(
		'enabled' => array(__('启用 HTML 文档文章', 'argon'), true),
		'allow_scripts' => array(__('允许文档脚本（scripts）', 'argon'), true),
		'allow_popups' => array(__('允许弹窗（popups，继续受沙箱约束）', 'argon'), false),
		'allow_downloads' => array(__('允许下载（downloads）', 'argon'), false),
		'allow_modals' => array(__('允许对话框（modals）', 'argon'), false),
	);
	echo '<tr><th class="subtitle"><h2>' . esc_html__('HTML 文档文章', 'argon') . '</h2></th></tr>';
	foreach ($settings as $key => [$label, $default]) {
		$name = 'argon_html_' . $key;
		echo '<tr><th><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td><select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '">';
		foreach (array('true' => __('启用', 'argon'), 'false' => __('关闭', 'argon')) as $value => $text) {
			echo '<option value="' . esc_attr($value) . '"' . selected(get_option($name, $default ? 'true' : 'false'), $value, false) . '>' . esc_html($text) . '</option>';
		}
		echo '</select></td></tr>';
	}
	echo '<tr><th><label for="argon_html_resource_origins">' . esc_html__('外部资源白名单', 'argon') . '</label></th><td><textarea id="argon_html_resource_origins" name="argon_html_resource_origins" rows="5" class="large-text code">' . esc_textarea(get_option('argon_html_resource_origins', '')) . '</textarea><p class="description">' . esc_html__('每行一个完整 HTTPS 来源，例如 https://cdn.jsdelivr.net。本站资源与内联样式默认允许；脚本接口请求、表单提交、嵌套 iframe、同源权限、顶层跳转和弹窗逃离沙箱始终禁用。关闭 scripts 后使用固定高度及内部滚动，自动高度、主题同步和文档目录不可用。修改权限需要 unfiltered_html。', 'argon') . '</p></td></tr>';
}

function argon_html_save_options(): void {
	if (!current_user_can('edit_theme_options') || !current_user_can('unfiltered_html')
		|| !wp_verify_nonce(argon_request_string($_POST, 'argon_update_themeoptions_nonce'), 'argon_update_themeoptions')) {
		return;
	}
	foreach (array('enabled', 'allow_scripts', 'allow_popups', 'allow_downloads', 'allow_modals') as $key) {
		$name = 'argon_html_' . $key;
		$value = argon_request_string($_POST, $name);
		if (in_array($value, array('true', 'false'), true)) {
			update_option($name, $value);
		}
	}
	if (isset($_POST['argon_html_resource_origins'])) {
		update_option('argon_html_resource_origins', implode("\n", argon_html_resource_origins(argon_request_string($_POST, 'argon_html_resource_origins'))));
	}
}
