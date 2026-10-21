<?php
// Request values can be arrays; reject them before passing them to PHP string APIs.
function argon_request_string(array $input, string $key, string $default = ''): string {
	return isset($input[$key]) && is_string($input[$key]) ? wp_unslash($input[$key]) : $default;
}

function argon_ajax_nonce(): void {
	if (!wp_verify_nonce(argon_request_string($_POST, '_wpnonce'), 'argon_public_ajax')){
		wp_send_json(array('status' => 'failed', 'msg' => __('请求已过期，请刷新页面后重试', 'argon')), 403);
	}
}

function argon_ajax_id(string $field, bool $allow_zero = false): int {
	$id = filter_var(argon_request_string($_POST, $field), FILTER_VALIDATE_INT, array('options' => array('min_range' => $allow_zero ? 0 : 1)));
	if ($id === false){
		wp_send_json(array('status' => 'failed', 'msg' => __('参数错误', 'argon')), 400);
	}
	return $id;
}

function argon_user_token(): string {
	$token = argon_request_string($_COOKIE, 'argon_user_token');
	return preg_match('/^[a-f0-9]{32}$/D', $token) === 1 ? $token : '';
}

function argon_token_matches($stored_token): bool {
	$token = argon_user_token();
	return $token !== '' && is_string($stored_token) && strlen($stored_token) === 32 && hash_equals($stored_token, $token);
}

function argon_can_view_post($post_id): bool {
	$post = get_post($post_id);
	return $post && !post_password_required($post) && (is_post_publicly_viewable($post) || current_user_can('read_post', $post->ID));
}

// Use this for values embedded in inline scripts, including closing script tags.
function argon_json($value): string {
	return (string) wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function argon_card_radius($value): float {
	return is_scalar($value) && is_numeric($value) ? max(0, min(30, (float) $value)) : 4;
}
