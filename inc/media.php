<?php
/** Keep theme and Markdown images on WordPress's native media filter pipeline. */

/** Resolve uploads only; never fetch an external image or guess a replacement URL. */
function argon_image_attachment_id(string $url): int {
	$uploads = wp_get_upload_dir();
	$base = wp_parse_url($uploads['baseurl']);
	$parts = wp_parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	if (!$parts || isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) return 0;
	if (isset($parts['host']) && strcasecmp($parts['host'], $base['host'] ?? '') !== 0) return 0;
	if (isset($parts['port']) && $parts['port'] !== ($base['port'] ?? null)) return 0;
	$prefix = trailingslashit(rawurldecode($base['path'] ?? ''));
	$path = rawurldecode($parts['path'] ?? '');
	if (!str_starts_with($path, $prefix)) return 0;
	$file = substr($path, strlen($prefix));
	if ($file === '' || str_contains($file, '..') || !preg_match('/\.(?:jpe?g|png|gif|webp|avif)$/i', $file)) return 0;
	$key = md5($uploads['baseurl'] . '/' . $file);
	$id = wp_cache_get($key, 'argon_image_ids', false, $found);
	if ($found) return (int) $id;
	$id = attachment_url_to_postid(trailingslashit($uploads['baseurl']) . $file);
	if (!$id) {
		// Core's reverse lookup knows the attached file, but not resized or
		// original-before-scaling URLs. Query a bounded set of exact filenames,
		// then verify the requested variant against the attachment metadata.
		$original = preg_replace('/-(jpe?g|png)\.(?:avif|webp)$/i', '.$1', $file);
		$stem = preg_replace('/-\d+x\d+(?=\.[^.]+$)/', '', $original);
		$stems = array($stem);
		if (preg_match('/\.(?:avif|webp)$/i', $stem)) {
			foreach (array('jpg', 'jpeg', 'png') as $ext) $stems[] = preg_replace('/\.[^.]+$/', '.' . $ext, $stem);
		}
		$candidates = array($file);
		foreach ($stems as $candidate) {
			$candidates[] = $candidate;
			$candidates[] = preg_replace('/(?=\.[^.]+$)/', '-scaled', $candidate);
			$candidates[] = preg_replace('/(?=\.[^.]+$)/', '-rotated', $candidate);
		}
		$candidates = array_values(array_unique($candidates));
		global $wpdb;
		$ids = $wpdb->get_col($wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value IN (" . implode(',', array_fill(0, count($candidates), '%s')) . ')',
			$candidates
		));
		foreach ($ids as $candidate_id) {
			$meta = wp_get_attachment_metadata((int) $candidate_id);
			if (!is_array($meta) || empty($meta['file'])) continue;
			$directory = dirname($meta['file']);
			$directory = $directory === '.' ? '' : $directory . '/';
			$files = array($meta['file']);
			if (isset($meta['original_image'])) $files[] = $directory . $meta['original_image'];
			foreach (array_merge(array($meta), array_values($meta['sizes'] ?? array())) as $size) {
				if (isset($size['file'])) $files[] = $directory . wp_basename($size['file']);
				foreach ($size['sources'] ?? array() as $source) if (isset($source['file'])) $files[] = $directory . $source['file'];
			}
			if (in_array($file, $files, true)) { $id = (int) $candidate_id; break; }
		}
	}
	$id = $id && wp_attachment_is_image($id) ? (int) $id : 0;
	wp_cache_set($key, $id, 'argon_image_ids', $id ? HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS);
	return $id;
}

/** Add the attachment identity before core adds dimensions, srcset and plugin markup. */
function argon_prepare_content_images(string $content): string {
	if (!str_contains(strtolower($content), '<img')) return $content;
	$tags = new WP_HTML_Tag_Processor($content);
	$in_picture = false;
	while ($tags->next_tag(array('tag_closers' => 'visit'))) {
		if ($tags->get_tag() === 'PICTURE') { $in_picture = !$tags->is_tag_closer(); continue; }
		if ($tags->get_tag() !== 'IMG' || $in_picture) continue;
		$src = $tags->get_attribute('src');
		if (!is_string($src)) continue;
		if (preg_match('/(?:^|\s)wp-image-\d+(?:\s|$)/', (string) $tags->get_attribute('class'))) {
			// Existing attachment identities still need the layout hint before
			// core supplies intrinsic dimensions and plugins wrap the image.
			if ($tags->get_attribute('width') === null && $tags->get_attribute('height') === null) $tags->add_class('argon-image-inset');
			continue;
		}
		$id = argon_image_attachment_id($src);
		if (!$id) continue;
		$tags->add_class('wp-image-' . $id);
		// Preserve the theme's existing inset after core supplies intrinsic width.
		if ($tags->get_attribute('width') === null && $tags->get_attribute('height') === null) $tags->add_class('argon-image-inset');
	}
	return $tags->get_updated_html();
}
add_filter('the_content', 'argon_prepare_content_images', 11);

/** Render a media-library URL through the same hooks as an explicit attachment. */
function argon_get_image_from_url(string $url, $size = 'full', array $attr = array()): string {
	$id = argon_image_attachment_id($url);
	if ($id) return wp_get_attachment_image($id, $size, false, $attr);
	$src = esc_url_raw($url, array('http', 'https'));
	if ($src === '') return '';
	$attr = array_merge(array('alt' => '', 'decoding' => 'async'), $attr, array('src' => $src));
	$attr = array_merge($attr, wp_get_loading_optimization_attributes('img', $attr, 'argon_image'));
	$tag = new WP_HTML_Tag_Processor('<img>');
	$tag->next_tag();
	foreach ($attr as $key => $value) if ($value !== false && $value !== null) $tag->set_attribute($key, $value);
	return $tag->get_updated_html();
}

/** A decorative banner can still use responsive images and modern-format sources. */
function argon_get_banner_image(): string {
	if (get_option('argon_banner_size', 'full') === 'hide') return '';
	if (get_option('argon_page_background_banner_style', 'false') === 'transparent' && (string) apply_filters('argon_page_background_url', get_option('argon_page_background_url')) !== '') return '';
	$attr = array('class' => 'argon-banner-image', 'alt' => '', 'aria-hidden' => 'true', 'loading' => 'eager', 'sizes' => '100vw');
	if (is_singular(array('post', 'page')) && get_option('argon_show_thumbnail_in_banner_in_content_page') === 'true') {
		$image = argon_get_post_thumbnail_image(get_queried_object_id(), 'full', $attr);
		if ($image !== '') return $image;
	}
	$url = get_banner_background_url();
	return is_string($url) && $url !== '' ? argon_get_image_from_url($url, 'full', $attr) : '';
}
