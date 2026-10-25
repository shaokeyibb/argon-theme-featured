<?php
/** Normalize existing Argon options for WordPress global styles and the editor. */

function argon_theme_color_presets(): array {
	return array(
		array('slug' => 'argon-default', 'name' => 'Argon (' . __('默认', 'argon') . ')', 'color' => '#5e72e4'),
		array('slug' => 'argon-pink', 'name' => __('粉', 'argon'), 'color' => '#fa7298'),
		array('slug' => 'argon-teal', 'name' => __('水鸭青', 'argon'), 'color' => '#009688'),
		array('slug' => 'argon-blue-grey', 'name' => __('蓝灰', 'argon'), 'color' => '#607d8b'),
		array('slug' => 'argon-blue', 'name' => __('天蓝', 'argon'), 'color' => '#2196f3'),
		array('slug' => 'argon-indigo', 'name' => __('靛蓝', 'argon'), 'color' => '#3f51b5'),
		array('slug' => 'argon-orange', 'name' => __('橙', 'argon'), 'color' => '#ff9700'),
		array('slug' => 'argon-green', 'name' => __('绿', 'argon'), 'color' => '#109d58'),
		array('slug' => 'argon-red', 'name' => __('红', 'argon'), 'color' => '#dc4437'),
		array('slug' => 'argon-purple', 'name' => __('紫', 'argon'), 'color' => '#673bb7'),
		array('slug' => 'argon-black', 'name' => __('黑', 'argon'), 'color' => '#212121'),
		array('slug' => 'argon-brown', 'name' => __('棕', 'argon'), 'color' => '#795547'),
	);
}

function argon_design_theme_json($theme_json) {
	// Use site options only. Reader cookies must never change saved editor colors.
	$color = get_option('argon_theme_color', '#5e72e4');
	$color = is_string($color) && preg_match('/^#[a-fA-F0-9]{6}$/', $color) ? $color : '#5e72e4';
	$data = $theme_json->get_theme_json()->get_data();
	$palette = $data['settings']['color']['palette'] ?? array();
	foreach ($palette as &$entry) {
		if (($entry['slug'] ?? '') === 'primary') $entry['color'] = $color;
	}
	unset($entry);
	// Preserve child-theme palette entries while adding the existing Argon swatches.
	$slugs = array_column($palette, 'slug');
	foreach (argon_theme_color_presets() as $preset) {
		if (!in_array($preset['slug'], $slugs, true)) $palette[] = $preset;
	}
	$family = get_option('argon_font') === 'serif' ? 'serif' : 'sans';
	$radius = argon_card_radius(get_option('argon_card_radius', 4)) . 'px';
	$shadow = get_option('argon_card_shadow') === 'big'
		? '0 15px 35px #32325d1a, 0 5px 15px #00000012'
		: '0 0.125rem 0.25rem #00000013';
	return $theme_json->update_with(array(
		'version' => 3,
		'settings' => array(
			'color' => array('palette' => $palette),
			'custom' => array('argon' => array(
				'content' => array('font-family' => 'var(--wp--preset--font-family--' . $family . ')'),
				'surface' => array('radius' => $radius, 'shadow' => $shadow),
			)),
		),
	));
}
add_filter('wp_theme_json_data_theme', 'argon_design_theme_json');
