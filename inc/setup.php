<?php
/** Hybrid classic theme: keep PHP templates and expose modern block features. */

function argon_setup(): void {
	load_theme_textdomain('argon', get_template_directory() . '/languages');
	add_theme_support('title-tag');
	add_theme_support('post-thumbnails');
	add_theme_support('automatic-feed-links');
	add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'navigation-widgets'));
	add_theme_support('responsive-embeds');
	add_theme_support('align-wide');
	add_theme_support('wp-block-styles');
	add_theme_support('editor-styles');
	add_theme_support('custom-logo', array('height' => 100, 'width' => 300, 'flex-width' => true, 'flex-height' => true));
	add_theme_support('customize-selective-refresh-widgets');
	add_editor_style('assets/editor.css');
	$GLOBALS['content_width'] = 900;
}
add_action('after_setup_theme', 'argon_setup');

// Core can load block styles only when the corresponding blocks are rendered.
add_filter('should_load_separate_core_block_assets', '__return_true');

function argon_upgrade_theme(): void {
	if (get_option('argon_modern_schema') === '3') {
		return;
	}
	// Keep old values as data, but never send a fork to the upstream updater/CDN.
	if (get_option('argon_assets_path') !== 'custom') {
		update_option('argon_assets_path', 'local');
	}
	update_option('argon_update_source', 'stop');
	if (get_option('argon_enable_lightbox', null) === null) {
		update_option('argon_enable_lightbox', get_option('argon_enable_fancybox') === 'false' && get_option('argon_enable_zoomify') !== 'true' ? 'false' : 'true');
	}
	$math = get_option('argon_math_render');
	if (in_array($math, array('mathjax2', 'mathjax3'), true)) {
		update_option('argon_math_render', 'katex');
	}
	// Obsolete scheduled upstream update checks are no longer needed.
	wp_clear_scheduled_hook('check_theme_updates-argon');
	update_option('argon_modern_schema', '3', false);
}
add_action('after_setup_theme', 'argon_upgrade_theme', 20);
