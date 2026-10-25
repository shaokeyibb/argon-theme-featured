<?php
/** WordPress owns resource ordering, dependency resolution and cache versions. */

function argon_asset_manifest(): array {
	static $manifest;
	if ($manifest === null) {
		$path = get_template_directory() . '/assets/dist/manifest.json';
		$manifest = is_readable($path) ? json_decode(file_get_contents($path), true) : array();
		$manifest = is_array($manifest) ? $manifest : array();
	}
	return $manifest;
}

function argon_asset_url(string $file): string {
	return trailingslashit($GLOBALS['assets_path']) . 'assets/dist/' . ltrim($file, '/');
}

function argon_register_icon_assets(): void {
	$icons = argon_asset_manifest()['src/styles/icons.scss'] ?? null;
	if ($icons && !empty($icons['file'])) {
		wp_register_style('argon-icons', argon_asset_url($icons['file']), array(), null);
	}
}
add_action('init', 'argon_register_icon_assets', 5);

function argon_code_theme_names(): array {
	$root = get_template_directory() . '/assets/dist/highlight/';
	$files = array_merge(glob($root . '*.css'), glob($root . 'base16/*.css'));
	$names = array_map(static fn($file) => substr($file, strlen($root), -4), $files);
	sort($names);
	return $names;
}

function argon_enqueue_assets(): void {
	$entry = argon_asset_manifest()['src/modern/index.js'] ?? null;
	if (!$entry || empty($entry['file'])) {
		return;
	}
	foreach ($entry['css'] ?? array() as $index => $css) {
		wp_enqueue_style('argon-theme-' . $index, argon_asset_url($css), array('argon-icons'), null);
	}
	// pagereveal runs before the first render: its listener must be a classic
	// head script, not a deferred module. Only optional transition rejections
	// are handled; unrelated errors keep their normal reporting.
	wp_register_script('argon-navigation', false, array(), null, false);
	wp_enqueue_script('argon-navigation');
	wp_add_inline_script('argon-navigation', "for (const name of ['pageswap', 'pagereveal']) window.addEventListener(name, event => { event.viewTransition?.ready.catch(() => {}); });");
	wp_enqueue_script_module('argon-theme', argon_asset_url($entry['file']), array(), null);
	if (get_option('argon_disable_googlefont') !== 'true') {
		wp_enqueue_style('argon-google-fonts', 'https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Noto+Serif+SC:300,600&display=swap', array(), null);
	}
	if (get_option('argon_enable_code_highlight') === 'true') {
		$style = (string) get_option('argon_code_theme', 'vs2015');
		if (!preg_match('~\A(?:base16/)?[a-zA-Z0-9_-]+\z~', $style)) $style = 'vs2015';
		if (!is_file(get_template_directory() . '/assets/dist/highlight/' . $style . '.css')) {
			$style = 'vs2015';
		}
		wp_enqueue_style('argon-code-theme', argon_asset_url('highlight/' . $style . '.css'), array(), $GLOBALS['theme_version']);
	}
}
add_action('wp_enqueue_scripts', 'argon_enqueue_assets');

function argon_missing_build_notice(): void {
	if (current_user_can('edit_theme_options') && !argon_asset_manifest()) {
		echo '<div class="notice notice-error"><p>' . esc_html__('Argon 前端资源缺失，请运行 npm ci && npm run build，或安装包含构建资源的发行包。', 'argon') . '</p></div>';
	}
}
add_action('admin_notices', 'argon_missing_build_notice');

function argon_enqueue_admin_assets($hook): void {
	if (!in_array($hook, array('toplevel_page_admin', 'post.php', 'post-new.php'), true)) return;
	$entry = argon_asset_manifest()['src/modern/admin.js'] ?? null;
	if (!$entry) return;
	wp_enqueue_script('argon-admin', argon_asset_url($entry['file']), array('wp-i18n'), null, true);
}
add_action('admin_enqueue_scripts', 'argon_enqueue_admin_assets');
add_filter('script_loader_tag', static function($tag, $handle) {
	if ($handle !== 'argon-admin') return $tag;
	$processor = new WP_HTML_Tag_Processor($tag);
	if ($processor->next_tag('script')) $processor->set_attribute('type', 'module');
	return $processor->get_updated_html();
}, 10, 2);
