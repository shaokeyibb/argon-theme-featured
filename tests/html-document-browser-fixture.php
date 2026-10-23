<?php
if (!defined('ARGON_HTML_TESTING') || !ARGON_HTML_TESTING) { exit; }
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_set_current_user(1);
wp_set_password('argon-html-local-test', 1);
update_option('argon_html_enabled', 'true');
update_option('argon_html_allow_scripts', 'true');
update_option('argon_html_allow_popups', 'false');
update_option('argon_html_allow_downloads', 'false');
update_option('argon_html_allow_modals', 'false');
update_option('argon_ai_post_summary', 'false');
update_option('argon_show_sharebtn', 'false');
update_option('argon_show_readingtime', 'true');
update_option('argon_show_headindex_number', 'true');
update_option('githuber_markdown', array('enable_markdown_for_post_types' => array('post', 'page'), 'markdown_editor_switcher' => 'yes', 'editor_live_preview' => 'yes', 'editor_html_decode' => 'yes', 'editor_editor_theme' => 'default', 'editor_toolbar_theme' => 'default'));
$source = file_get_contents(__DIR__ . '/fixtures/html-document.html');
$block = '<!-- wp:argon/html-document {"source":"' . base64_encode($source) . '"} /-->';
$posts = array();
$posts['normal'] = wp_insert_post(wp_slash(array('post_title' => '普通文章', 'post_content' => '<!-- wp:paragraph --><p>这是普通文章，用于验证 PJAX 导航。</p><!-- /wp:paragraph -->', 'post_status' => 'publish', 'meta_input' => array('_is_githuber_markdown_enabled' => 'no'))));
$posts['gutenberg'] = wp_insert_post(wp_slash(array('post_title' => 'HTML 文档：古腾堡', 'post_content' => '<!-- wp:paragraph --><p>这段普通区块是文档的替代正文，用于摘要和文本阅读。</p><!-- /wp:paragraph -->' . $block, 'post_status' => 'publish', 'meta_input' => array('_is_githuber_markdown_enabled' => 'no'))));
$declaration = '[argon_html encoding="base64"]' . base64_encode($source) . '[/argon_html]';
$raw = "## Markdown 替代正文\n\nGithuber MD 的文字内容保留在文档声明之外。\n\n" . $declaration . "\n\n正文后半部分也用于摘要。";
$posts['markdown'] = wp_insert_post(wp_slash(array('post_title' => 'HTML 文档：Githuber MD', 'post_content' => '<h2>Markdown 替代正文</h2><p>Githuber MD 的文字内容保留在文档声明之外。</p>' . $declaration . '<p>正文后半部分也用于摘要。</p>', 'post_content_filtered' => $raw, 'post_status' => 'publish', 'meta_input' => array('_is_githuber_markdown' => '1', '_is_githuber_markdown_enabled' => 'yes'))));
$temp = wp_tempnam('native-media.html');
file_put_contents($temp, $source);
$posts['attachment'] = media_handle_sideload(array('name' => 'native-media.html', 'tmp_name' => $temp, 'size' => strlen($source), 'error' => 0), $posts['gutenberg']);
$posts['file'] = wp_insert_post(wp_slash(array('post_title' => 'HTML 文档：媒体库', 'post_content' => '<p>媒体库文件引用的替代正文。</p>[argon_html file_id="' . $posts['attachment'] . '"]', 'post_status' => 'publish', 'meta_input' => array('_is_githuber_markdown_enabled' => 'no'))));
foreach (array('gutenberg', 'markdown', 'file') as $key) {
	update_post_meta($posts[$key], 'argon_after_post', '<span id="argon-html-escape-target" hidden>protected</span>');
}
$posts['draft'] = wp_insert_post(wp_slash(array('post_title' => 'HTML 文档：草稿', 'post_content' => $block, 'post_status' => 'draft', 'meta_input' => array('_is_githuber_markdown_enabled' => 'no'))));
$posts['site'] = home_url('/');
$posts['githuber_version'] = defined('GITHUBER_PLUGIN_VERSION') ? GITHUBER_PLUGIN_VERSION : '';
wp_mkdir_p(__DIR__ . '/../temp');
file_put_contents(__DIR__ . '/../temp/html-document-browser-fixture.json', wp_json_encode($posts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo wp_json_encode($posts);
