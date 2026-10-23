<?php
/** Githuber preserves shortcode bodies at save time; render their Markdown before wpautop. */
function argon_markdown_shortcodes(string $content): string {
	if (!str_contains($content, '[')) return $content;
	$post = get_post();
	if (!$post || !class_exists('Githuber\\Controller\\Markdown')) return $content;
	$parent = $post->post_type === 'revision' ? $post->post_parent : $post->ID;
	if (get_post_meta($parent, '_is_githuber_markdown_enabled', true) === 'no'
		|| !(get_post_meta($post->ID, '_is_githuber_markdown', true) || get_post_meta($parent, '_is_githuber_markdown', true))) return $content;
	// A separate parser keeps request-local fragment state out of the plugin's shared parser.
	$parser = clone \Githuber\Controller\Markdown::get_parser();
	// Preserve existing HTML; KSES sanitizes the resulting Markdown HTML before shortcodes run.
	$parser->setSafeMode(false);
	return argon_markdown_shortcode_fragments($content, $parser);
}
add_filter('the_content', 'argon_markdown_shortcodes', 9);

function argon_markdown_shortcode_fragments(string $content, $parser): string {
	$blocks = array('collapse', 'fold', 'alert', 'admonition');
	$inline = array('label', 'checkbox', 'progressbar', 'hidden', 'spoiler', 'ref');
	// Examples must not be interpreted as declarations, including closing tags in fenced code.
	$protected = array();
	$prefix = 'ARGONMD' . bin2hex(random_bytes(8));
	$pattern = '~^[ ]{0,3}(`{3,}|\x7e{3,})[^\r\n]*\R[\s\S]*?^[ ]{0,3}\1[ \t]*(?:\R|$)|(`+)[^`]*?\2(?!`)|<(pre|code)\b[^>]*>[\s\S]*?</\3\s*>|<!--[\s\S]*?-->~mi';
	$content = preg_replace_callback($pattern, static function($match) use (&$protected, $prefix) {
		$key = $prefix . count($protected) . 'END';
		$protected[$key] = $match[0];
		return $key;
	}, $content);
	$content = preg_replace_callback('/' . get_shortcode_regex() . '/s', static function($match) use ($parser, $blocks, $inline, $protected) {
		$tag = $match[2];
		if ($match[1] === '[' || $match[6] === ']' || !isset($match[5]) || $match[5] === ''
			|| !in_array($tag, array_merge($blocks, $inline), true)) return $match[0];
		$attributes = shortcode_parse_atts($match[3]);
		$body = strtr($match[5], $protected);
		if (($attributes['nested'] ?? 'true') !== 'false') $body = argon_markdown_shortcode_fragments($body, $parser);
		// Keep nested declarations and their attributes opaque to both Markdown and KSES.
		$nested = array();
		$body = preg_replace_callback('/' . get_shortcode_regex() . '/s', static function($child) use (&$nested) {
			$key = 'ARGONSHORTCODE' . bin2hex(random_bytes(8)) . 'END';
			$nested[$key] = $child[0];
			return $key;
		}, $body);
		$rendered = in_array($tag, $blocks, true) ? $parser->text($body) : $parser->line($body);
		$rendered = strtr(wp_kses_post($rendered), $nested);
		return '[' . $tag . $match[3] . ']' . $rendered . '[/' . $tag . ']';
	}, $content);
	$content = strtr($content, $protected);
	// WordPress executes shortcodes inside code elements too unless their brackets are entities.
	return preg_replace_callback('~<(pre|code)\b[^>]*>[\s\S]*?</\1\s*>~i', static fn($match) => str_replace(array('[', ']'), array('&#91;', '&#93;'), $match[0]), $content);
}
