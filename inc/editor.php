<?php
/** REST-backed settings participate in native editor saves, autosaves and revisions. */
function argon_register_editor_meta(): void {
	foreach (array('post', 'page') as $type) {
		foreach (argon_post_meta_allowed_values() as $key => $values) {
			$schema = array('type' => 'string', 'default' => '');
			if (is_array($values)) $schema['enum'] = array_merge(array(''), $values);
			if ($key === 'argon_discourse_comments_topic_id') $schema['pattern'] = '^(|[1-9][0-9]*)$';
			register_post_meta($type, $key, array(
				'type' => 'string', 'single' => true, 'default' => '', 'revisions_enabled' => true,
				'show_in_rest' => array('schema' => $schema),
				'auth_callback' => static function($allowed, $meta_key, $post_id, $user_id) {
					return user_can($user_id, 'edit_post', $post_id) && (!in_array($meta_key, array('argon_after_post', 'argon_custom_css'), true) || user_can($user_id, 'unfiltered_html'));
				}
			));
		}
	}
}
add_action('init', 'argon_register_editor_meta', 20);

function argon_enqueue_editor_settings(): void {
	$screen = get_current_screen();
	if (!$screen || !in_array($screen->post_type, array('post', 'page'), true)) return;
	wp_enqueue_script('argon-blocks');
	wp_add_inline_script('argon-blocks', 'window.argonEditorSettings = ' . wp_json_encode(array(
		'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('argon_meta_box_nonce_action'),
		'canUnfilteredHtml' => current_user_can('unfiltered_html'),
		'aiEnabled' => get_option('argon_ai_post_summary') === 'true',
		'discourseEnabled' => get_option('argon_enable_discourse_comments') === 'true'
	)) . ';', 'before');
}
add_action('enqueue_block_editor_assets', 'argon_enqueue_editor_settings');
