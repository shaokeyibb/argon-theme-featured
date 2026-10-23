<?php
// Run only in a disposable WordPress installation; see tests/README.md.
if (!defined('ARGON_SECURITY_TESTING') || !ARGON_SECURITY_TESTING){
	exit;
}

class ArgonTestAjaxEnd extends RuntimeException {}

$argon_test_results = array();
$argon_test_status = 200;
add_filter('wp_die_ajax_handler', static fn() => static function(){ throw new ArgonTestAjaxEnd(); });
add_filter('status_header', static function($header, $status){
	$GLOBALS['argon_test_status'] = $status;
	return $header;
}, 10, 2);
// Never send notifications from this disposable test installation.
add_filter('pre_wp_mail', '__return_true');

function argon_test_assert($condition, string $message): void {
	if (!$condition){
		throw new RuntimeException($message);
	}
}
function argon_test(string $name, callable $test): void {
	$_POST = $_GET = array();
	$_COOKIE = array('argon_user_token' => str_repeat('a', 32));
	try {
		$test();
		$GLOBALS['argon_test_results'][$name] = 'passed';
	} catch (Throwable $e) {
		$GLOBALS['argon_test_results'][$name] = get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')';
	}
}
function argon_test_ajax(string $handler, array $data): array {
	$_POST = wp_slash($data);
	$GLOBALS['argon_test_status'] = 200;
	ob_start();
	try {
		$handler();
	} catch (ArgonTestAjaxEnd $e) {
		// WordPress still executes wp_send_json(), but its terminating wp_die is intercepted.
	} catch (Throwable $e) {
		ob_end_clean();
		throw $e;
	}
	$output = ob_get_clean();
	$response = json_decode($output, true);
	argon_test_assert(is_array($response), 'Expected JSON response, got: ' . $output);
	return array($GLOBALS['argon_test_status'], $response);
}
function argon_test_meta_request(int $id, string $key = 'argon_meta_simple', string $value = 'true'): array {
	return array('argon_meta_box_nonce' => wp_create_nonce('argon_meta_box_nonce_action'), 'post_id' => (string) $id, 'meta_key' => $key, 'meta_value' => $value);
}
function argon_test_public_request(array $data = array()): array {
	return array_merge(array('_wpnonce' => wp_create_nonce('argon_public_ajax')), $data);
}
function argon_test_safe_html(string $html): void {
	$old = libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$dom->loadHTML('<div>' . $html . '</div>', LIBXML_NONET);
	libxml_clear_errors();
	libxml_use_internal_errors($old);
	argon_test_assert($dom->getElementsByTagName('script')->length === 0, 'Executable script element');
	foreach ($dom->getElementsByTagName('*') as $element){
		foreach ($element->attributes as $attr){
			argon_test_assert(!str_starts_with(strtolower($attr->name), 'on'), 'Event handler: ' . $attr->name);
			if (in_array($attr->name, array('src', 'href'), true)){
				argon_test_assert(!preg_match('/^\s*(javascript|vbscript|data):/i', $attr->value), 'Executable URL');
			}
		}
	}
}

// Use real WordPress roles, nonces, metadata, KSES, and SQLite database operations.
$admin = 1;
$author = wp_insert_user(array('user_login' => 'argon_test_author', 'user_pass' => wp_generate_password(), 'role' => 'author'));
$other_author = wp_insert_user(array('user_login' => 'argon_test_other', 'user_pass' => wp_generate_password(), 'role' => 'author'));
argon_test_assert(is_int($author) && is_int($other_author), 'Fixture users must be created');
wp_set_current_user($admin);
$own = wp_insert_post(array('post_title' => 'Argon test', 'post_status' => 'publish', 'post_author' => $author, 'post_content' => '<p>中文 test</p><a href="https://example.org/">Example</a>'));
$other = wp_insert_post(array('post_title' => 'Other author', 'post_status' => 'publish', 'post_author' => $other_author));
$page = wp_insert_post(array('post_title' => 'Page', 'post_status' => 'publish', 'post_type' => 'page', 'post_author' => $admin));
$draft = wp_insert_post(array('post_title' => 'Draft', 'post_status' => 'draft', 'post_author' => $other_author));
$protected = wp_insert_post(array('post_title' => 'Protected', 'post_status' => 'publish', 'post_password' => 'secret', 'post_author' => $other_author));
$revision = wp_insert_post(array('post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $other));
$comment = wp_insert_comment(array('comment_post_ID' => $own, 'comment_content' => 'Original', 'comment_approved' => '1', 'user_id' => $author));
$private = wp_insert_comment(array('comment_post_ID' => $own, 'comment_content' => 'Private', 'comment_approved' => '1', 'user_id' => $other_author));
$pending = wp_insert_comment(array('comment_post_ID' => $own, 'comment_content' => 'Pending', 'comment_approved' => '0', 'user_id' => $other_author));
update_comment_meta($private, 'private_mode', str_repeat('b', 32));
update_comment_meta($comment, 'user_token', str_repeat('a', 32));
update_option('argon_comment_allow_editing', 'true');
update_option('argon_enable_comment_pinning', 'true');
update_option('argon_who_can_visit_comment_edit_history', 'everyone');

set_error_handler(static function($severity, $message, $file, $line){
	if ((error_reporting() & $severity) && str_starts_with($file, dirname(__DIR__)) && !str_contains($file, '/vendor/')){
		throw new ErrorException($message, 0, $severity, $file, $line);
	}
	return false;
});
error_reporting(E_ALL);

argon_test('privileged AJAX actions have no anonymous hook', function(){
	foreach (array('update_post_meta_ajax', 'regenerate_ai_post_summary', 'pin_comment') as $action){
		argon_test_assert(!has_action('wp_ajax_nopriv_' . $action), $action . ' is exposed to guests');
		argon_test_assert((bool) has_action('wp_ajax_' . $action), $action . ' authenticated hook missing');
	}
});

foreach (array('update_post_meta_ajax', 'regenerate_ai_post_summary') as $handler){
	argon_test($handler . ': guest with nonce rejected', function() use ($handler, $own){
		wp_set_current_user(0);
		[$status] = argon_test_ajax($handler, argon_test_meta_request($own));
		argon_test_assert($status === 403, 'Guest allowed');
	});
	argon_test($handler . ': cross-author write rejected before same-value check', function() use ($handler, $author, $other){
		wp_set_current_user($author);
		update_post_meta($other, 'argon_meta_simple', 'true');
		$before = get_post_meta($other);
		[$status] = argon_test_ajax($handler, argon_test_meta_request($other));
		argon_test_assert($status === 403 && get_post_meta($other) === $before, 'Cross-post mutation permitted');
	});
	foreach (array(null, '', 'invalid', array('bad')) as $nonce){
		argon_test($handler . ': malformed nonce ' . json_encode($nonce), function() use ($handler, $author, $own, $nonce){
			wp_set_current_user($author);
			$data = argon_test_meta_request($own);
			$data['argon_meta_box_nonce'] = $nonce;
			[$status] = argon_test_ajax($handler, $data);
			argon_test_assert($status === 403, 'Invalid nonce accepted');
		});
	}
	foreach (array(null, '', '0', '-1', '1abc', '1.5', '1e2', str_repeat('9', 30), array('1'), '9999999', (string) $revision) as $invalid_id){
		argon_test($handler . ': invalid post ID ' . json_encode($invalid_id), function() use ($handler, $author, $own, $invalid_id){
			wp_set_current_user($author);
			$data = argon_test_meta_request($own);
			$data['post_id'] = $invalid_id;
			[$status] = argon_test_ajax($handler, $data);
			argon_test_assert($status === 400, 'Invalid ID accepted');
		});
	}
}

foreach (array('_wp_page_template', '_thumbnail_id', 'argon_ai_summary', 'unknown', 'ARGON_META_SIMPLE') as $key){
	argon_test('metadata allowlist rejects ' . $key, function() use ($admin, $own, $key){
		wp_set_current_user($admin);
		$before = get_post_meta($own);
		[$status] = argon_test_ajax('update_post_meta_ajax', argon_test_meta_request($own, $key));
		argon_test_assert($status === 403 && get_post_meta($own) === $before, 'Unexpected metadata write');
	});
}
foreach (array('meta_key', 'meta_value') as $field){
	foreach (array(null, array('unexpected')) as $value){
		argon_test('metadata non-string ' . $field . json_encode($value), function() use ($author, $own, $field, $value){
			wp_set_current_user($author);
			$data = argon_test_meta_request($own);
			$data[$field] = $value;
			[$status] = argon_test_ajax('update_post_meta_ajax', $data);
			argon_test_assert($status === 400, 'Malformed field accepted');
		});
	}
}

$valid_settings = array(
	'argon_hide_readingtime' => 'false', 'argon_meta_simple' => 'true',
	'argon_first_image_as_thumbnail' => 'default', 'argon_show_post_outdated_info' => 'always',
	'argon_enable_external_link_clicks' => 'false', 'argon_ai_post_summary' => 'true',
	'argon_ai_no_update_post_summary' => 'default', 'argon_ai_extra_prompt_mode' => 'append',
	'argon_ai_extra_prompt' => "中文 prompt\nC:\\notes\\test \"quoted\" </textarea><script>alert(1)</script>",
	'argon_enable_discourse_comments' => 'true', 'argon_discourse_comments_topic_id' => '123'
);
foreach ($valid_settings as $key => $value){
	argon_test('author can save valid setting ' . $key, function() use ($author, $own, $key, $value){
		wp_set_current_user($author);
		[$status, $response] = argon_test_ajax('update_post_meta_ajax', argon_test_meta_request($own, $key, $value));
		argon_test_assert($status === 200 && $response['status'] === 'success' && get_post_meta($own, $key, true) === $value, 'Valid setting failed or lost slashes');
		[$status, $response] = argon_test_ajax('update_post_meta_ajax', argon_test_meta_request($own, $key, $value));
		argon_test_assert($status === 200 && $response['status'] === 'success', 'Unchanged setting failed');
	});
}
foreach (array('argon_after_post', 'argon_custom_css') as $key){
	argon_test('raw field is restricted across AJAX and WordPress: ' . $key, function() use ($author, $own, $key){
		wp_set_current_user($author);
		argon_test_assert(!current_user_can('edit_post_meta', $own, $key), 'Core metadata authorization bypass');
		[$status] = argon_test_ajax('update_post_meta_ajax', argon_test_meta_request($own, $key, '</style><script>alert(1)</script>'));
		argon_test_assert($status === 403, 'Author can save executable content');
	});
	argon_test('administrator retains raw-field editing: ' . $key, function() use ($admin, $own, $key){
		wp_set_current_user($admin);
		$value = $key === 'argon_after_post' ? '<b>Additional content</b>' : 'p::after { content: "\\2713"; }';
		[$status] = argon_test_ajax('update_post_meta_ajax', argon_test_meta_request($own, $key, $value));
		argon_test_assert($status === 200 && get_post_meta($own, $key, true) === $value, 'Admin setting broken');
	});
}
argon_test('invalid select and Discourse values are rejected', function() use ($author, $own){
	wp_set_current_user($author);
	foreach (array('argon_ai_extra_prompt_mode', 'argon_meta_simple', 'argon_discourse_comments_topic_id') as $key){
		[$status] = argon_test_ajax('update_post_meta_ajax', argon_test_meta_request($own, $key, "';alert(1);//"));
		argon_test_assert($status === 400, 'Invalid value accepted');
	}
});
argon_test('page edit uses object capability', function() use ($author, $admin, $page){
	wp_set_current_user($author);
	[$status] = argon_test_ajax('update_post_meta_ajax', argon_test_meta_request($page));
	argon_test_assert($status === 403, 'Author can modify page');
	wp_set_current_user($admin);
	[$status] = argon_test_ajax('update_post_meta_ajax', argon_test_meta_request($page));
	argon_test_assert($status === 200, 'Admin cannot modify page');
});
argon_test('normal save cannot bypass raw fields or wipe omitted settings', function() use ($author, $own){
	wp_set_current_user($author);
	$before = get_post_meta($own, 'argon_after_post', true);
	$_POST = wp_slash(array('argon_meta_box_nonce' => wp_create_nonce('argon_meta_box_nonce_action'), 'post_type' => 'bogus', 'argon_after_post' => '<script>alert(1)</script>', 'argon_meta_simple' => 'false'));
	argon_save_meta_data($own);
	argon_test_assert(get_post_meta($own, 'argon_after_post', true) === $before, 'Raw field bypass');
	argon_test_assert(get_post_meta($own, 'argon_meta_simple', true) === 'false', 'Normal setting not saved');
	argon_test_assert(get_post_meta($own, 'argon_discourse_comments_topic_id', true) === '123', 'Missing setting erased');
});
argon_test('normal save cannot spoof post_type to bypass authorization', function() use ($author, $other){
	wp_set_current_user($author);
	$before = get_post_meta($other);
	$_POST = array('argon_meta_box_nonce' => wp_create_nonce('argon_meta_box_nonce_action'), 'post_type' => 'bogus', 'argon_meta_simple' => 'false');
	argon_save_meta_data($other);
	argon_test_assert(get_post_meta($other) === $before, 'Unauthorized normal save');
});
argon_test('AI missing configuration returns failure without PHP fatal error', function() use ($author, $own){
	wp_set_current_user($author);
	update_option('argon_ai_post_summary', 'true');
	delete_option('argon_openai_api_key');
	[$status, $response] = argon_test_ajax('regenerate_ai_post_summary', argon_test_meta_request($own));
	argon_test_assert($status === 500 && $response['status'] === 'failed', 'AI failure was reported as success');
	update_option('argon_ai_post_summary', 'false');
});

foreach (array('pin_comment', 'user_edit_comment', 'upvote_comment', 'upvote_shuoshuo', 'get_comment_edit_history', 'ajax_post_comment', 'ajax_get_captcha') as $handler){
	argon_test('CSRF rejected by ' . $handler, function() use ($handler, $admin){
		wp_set_current_user($admin);
		[$status] = argon_test_ajax($handler, array('_wpnonce' => array('invalid')));
		argon_test_assert($status === 403, 'Missing or malformed nonce accepted');
	});
}
foreach (array(0, $admin) as $nonce_user) {
	argon_test('Uncached public nonce follows visitor ' . $nonce_user, function() use ($nonce_user) {
		wp_set_current_user($nonce_user);
		$server = $_SERVER;
		try {
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_SERVER['HTTP_X_ARGON_REQUEST'] = 'public-nonce';
			$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
			$_SERVER['HTTP_ORIGIN'] = rtrim(home_url(), '/');
			[$status, $response] = argon_test_ajax('argon_refresh_public_nonce', array());
			argon_test_assert($status === 200 && wp_verify_nonce($response['ajax_nonce'], 'argon_public_ajax'), 'Fresh visitor nonce rejected');
		} finally { $_SERVER = $server; }
	});
}
foreach (array('foreign-origin', 'cross-site', 'missing-header', 'get-method') as $nonce_case) {
	argon_test('Public nonce rejects ' . $nonce_case, function() use ($nonce_case) {
		$server = $_SERVER;
		try {
			$_SERVER['REQUEST_METHOD'] = $nonce_case === 'get-method' ? 'GET' : 'POST';
			$_SERVER['HTTP_X_ARGON_REQUEST'] = $nonce_case === 'missing-header' ? '' : 'public-nonce';
			$_SERVER['HTTP_SEC_FETCH_SITE'] = $nonce_case === 'cross-site' ? 'cross-site' : 'same-origin';
			$_SERVER['HTTP_ORIGIN'] = $nonce_case === 'foreign-origin' ? 'https://attacker.invalid' : rtrim(home_url(), '/');
			[$status, $response] = argon_test_ajax('argon_refresh_public_nonce', array());
			argon_test_assert($status === 403 && !isset($response['ajax_nonce']), 'Unsafe nonce request accepted');
		} finally { $_SERVER = $server; }
	});
}
argon_test('private and pending comment histories stay private', function() use ($private, $pending){
	wp_set_current_user(0);
	foreach (array($private, $pending) as $id){
		[$status, $response] = argon_test_ajax('get_comment_edit_history', argon_test_public_request(array('id' => (string) $id)));
		argon_test_assert($status === 403 && $response['history'] === '', 'Comment content exposed');
	}
});
argon_test('comment history is escaped and empty history is valid', function() use ($comment){
	wp_set_current_user(0);
	update_comment_meta($comment, 'comment_edit_history', wp_slash(wp_json_encode(array(array('content' => '<img src=x onerror=alert(1)><script>alert(1)</script>', 'time' => time(), 'isfirst' => true)))));
	[$status, $response] = argon_test_ajax('get_comment_edit_history', argon_test_public_request(array('id' => (string) $comment)));
	argon_test_assert($status === 200 && str_contains($response['history'], '&lt;script&gt;'), 'Source not escaped');
	argon_test_safe_html($response['history']);
	delete_comment_meta($comment, 'comment_edit_history');
	[$status, $response] = argon_test_ajax('get_comment_edit_history', argon_test_public_request(array('id' => (string) $comment)));
	argon_test_assert($status === 200 && $response['history'] === '', 'Missing history crashes');
});
argon_test('guest owner can edit safely and other visitor cannot', function() use ($comment){
	wp_set_current_user(0);
	[$status, $response] = argon_test_ajax('user_edit_comment', argon_test_public_request(array('id' => (string) $comment, 'comment' => '<b>OK</b><img src=x onerror=alert(1)><script>alert(1)</script>')));
	argon_test_assert($status === 200, 'Guest owner editing broken');
	argon_test_safe_html(get_comment($comment)->comment_content);
	argon_test_safe_html($response['new_comment']);
	$_COOKIE['argon_user_token'] = str_repeat('c', 32);
	[$status] = argon_test_ajax('user_edit_comment', argon_test_public_request(array('id' => (string) $comment, 'comment' => 'Unauthorized')));
	argon_test_assert($status === 403, 'Other visitor edited comment');
});
argon_test('moderation requires both nonce and capabilities', function() use ($author, $admin, $comment){
	wp_set_current_user($author);
	[$status] = argon_test_ajax('pin_comment', argon_test_public_request(array('id' => (string) $comment, 'pinned' => 'true')));
	argon_test_assert($status === 403, 'Author can pin');
	wp_set_current_user($admin);
	[$status] = argon_test_ajax('pin_comment', argon_test_public_request(array('id' => (string) $comment, 'pinned' => 'true')));
	argon_test_assert($status === 200 && get_comment_meta($comment, 'pinned', true) === 'true', 'Moderator cannot pin');
});
argon_test('Markdown comments reject executable HTML and URLs', function(){
	foreach (array('<img src=x onerror=alert(1)>', '[link](javascript:alert(1))', '<svg onload=alert(1)>', '**bold** [safe](https://example.org/)') as $content){
		argon_test_safe_html(comment_markdown_parse($content));
	}
});
argon_test('cookie arrays and token type juggling fail closed', function() use ($comment){
	$_COOKIE['argon_user_token'] = array('bad');
	argon_test_assert(!check_comment_token($comment), 'Array cookie accepted');
	$_COOKIE['argon_user_token'] = '0e' . str_repeat('1', 30);
	argon_test_assert(!argon_token_matches('0e' . str_repeat('2', 30)), 'Magic hash comparison bypass');
});
argon_test('CAPTCHA requires a server-signed seed and exact answer', function() use ($comment){
	wp_set_current_user(0);
	update_option('argon_comment_need_captcha', 'true');
	$seed = get_comment_captcha_seed(true);
	$_POST = array('comment_captcha_seed' => (string) $seed, 'comment_captcha' => (string) get_comment_captcha_answer($seed), 'comment_captcha_nonce' => wp_create_nonce('argon_captcha_' . $seed));
	argon_test_assert(check_comment_captcha(array('comment_content' => 'OK'))['comment_content'] === 'OK', 'Valid answer rejected');
	[$status] = argon_test_ajax('check_comment_captcha_for_test', array_merge($_POST, array('comment_captcha_nonce' => 'forged')));
	argon_test_assert($status === 400, 'Forged seed accepted');
	foreach (range(1, 50) as $seed){
		$captcha = new captcha_calculation($seed);
		argon_test_assert($captcha->check((string) $captcha->getAnswer()) && !$captcha->check('wrong') && !$captcha->check(array()), 'CAPTCHA comparison broken');
	}
	update_option('argon_comment_need_captcha', 'false');
});
function check_comment_captcha_for_test(){ check_comment_captcha(array()); }

argon_test('guest submission preserves source and refreshes the signed CAPTCHA', function() use ($own){
	wp_set_current_user(0);
	update_option('argon_comment_need_captcha', 'true');
	update_option('argon_comment_allow_markdown', 'true');
	$seed = get_comment_captcha_seed(true);
	$source = "Guest's **中文** \\ path <img src=x onerror=alert(1)>";
	// Fixture comments share the test IP and timestamp; exclude core flood throttling here.
	add_filter('comment_flood_filter', '__return_false');
	try {
		[$status, $response] = argon_test_ajax('ajax_post_comment', argon_test_public_request(array(
			'comment_post_ID' => (string) $own, 'comment_parent' => '0',
			'author' => 'Guest', 'email' => 'guest@example.org', 'url' => '',
			'comment' => $source, 'use_markdown' => 'true',
			'comment_captcha_seed' => (string) $seed,
			'comment_captcha_nonce' => wp_create_nonce('argon_captcha_' . $seed),
			'comment_captcha' => (string) get_comment_captcha_answer($seed)
		)));
		argon_test_assert($status === 200 && $response['status'] === 'success', 'Guest submission failed: ' . wp_json_encode($response));
		argon_test_assert(get_comment_meta($response['id'], 'comment_content_source', true) === $source, 'Comment source lost slashes');
		argon_test_safe_html(get_comment($response['id'])->comment_content);
		argon_test_assert(user_can_view_comment($response['id']), 'Author cannot view the new comment');
		argon_test_assert(wp_verify_nonce($response['newCaptchaNonce'], 'argon_captcha_' . $response['newCaptchaSeed']) !== false, 'Refreshed CAPTCHA nonce invalid');
	} finally {
		remove_filter('comment_flood_filter', '__return_false');
		update_option('argon_comment_need_captcha', 'false');
	}
});
argon_test('guest cannot reply to another visitor private comment', function() use ($own, $private){
	wp_set_current_user(0);
	[$status] = argon_test_ajax('ajax_post_comment', argon_test_public_request(array(
		'comment_post_ID' => (string) $own, 'comment_parent' => (string) $private,
		'comment' => 'Unauthorized reply', 'author' => 'Guest', 'email' => 'guest@example.org'
	)));
	argon_test_assert($status === 403, 'Private reply accepted');
});

argon_test('settings require edit_theme_options even with a valid nonce', function() use ($author){
	wp_set_current_user($author);
	$before = get_option('argon_toolbar_title');
	$_POST = array('update_themeoptions' => 'true', 'argon_update_themeoptions_nonce' => wp_create_nonce('argon_update_themeoptions'), 'argon_toolbar_title' => 'unauthorized');
	argon_update_themeoptions();
	argon_test_assert(get_option('argon_toolbar_title') === $before, 'Theme setting authorization bypass');
});
argon_test('partial settings updates preserve absent and malformed values', function() use ($admin){
	wp_set_current_user($admin);
	update_option('argon_toolbar_title', 'keep');
	$_POST = array('update_themeoptions' => 'true', 'argon_update_themeoptions_nonce' => wp_create_nonce('argon_update_themeoptions'), 'argon_toolbar_title' => array('invalid'));
	argon_update_themeoptions();
	argon_test_assert(get_option('argon_toolbar_title') === 'keep', 'Malformed option was saved');
});

$shortcodes = array(
	'progressbar' => array('progress' => "1' onmouseover='alert(1)"),
	'alert' => array('icon' => "x' onclick='alert(1)", 'title' => '<script>alert(1)</script>'),
	'admonition' => array('icon' => "x' onclick='alert(1)", 'title' => '<img src=x onerror=alert(1)>'),
	'collapse' => array('icon' => "x' onclick='alert(1)", 'title' => '<script>alert(1)</script>'),
	'hidden' => array('tip' => "x' onmouseover='alert(1)"),
	'video' => array('url' => 'javascript:alert(1)', 'width' => "1' onerror='alert(1)"),
	'github' => array('author' => "x' onclick='alert(1)", 'project' => 'test')
);
foreach ($shortcodes as $shortcode => $attributes){
	argon_test('shortcode output escapes ' . $shortcode, function() use ($shortcode, $attributes){
		global $shortcode_tags;
		argon_test_safe_html($shortcode_tags[$shortcode]($attributes, 'Body'));
	});
}
argon_test('simple friend links reject executable URLs and missing fields', function(){
	argon_test_safe_html(shortcode_friend_link_simple(array(), "category|Friends\nlink|javascript:alert(1)|Test|Description|x' onerror='alert(1)\nlink|https://example.org/|Safe"));
	argon_test_safe_html(shortcode_timeline(array(), '2026/10/21'));
	argon_test_assert(str_contains(shortcode_checkbox(array('inline' => 'true'), 'OK'), 'inline'), 'Checkbox inline option broken');
});
argon_test('script data and theme cookies cannot inject markup', function(){
	$json = argon_json(array('payload' => "</script><script>alert(1)</script>'\""));
	argon_test_assert(!str_contains($json, '</script>') && json_decode($json, true)['payload'] === "</script><script>alert(1)</script>'\"", 'Script serialization unsafe');
	argon_test_assert(argon_card_radius('</style><script>alert(1)</script>') === 4.0 && argon_card_radius(array()) === 4.0, 'Untrusted radius accepted');
	argon_test_assert(!checkHEX('#xxxxx!') && !checkHEX(array()) && checkHEX('#Aabb09'), 'Hex validation broken');
});
argon_test('meta editor escapes stored values', function() use ($admin, $own){
	wp_set_current_user($admin);
	$GLOBALS['post'] = get_post($own);
	update_option('argon_ai_post_summary', 'true');
	$payload = '</textarea><script>alert(1)</script>';
	update_post_meta($own, 'argon_after_post', $payload);
	ob_start();
	argon_meta_box_1();
	$html = ob_get_clean();
	argon_test_assert(!str_contains($html, $payload) && str_contains($html, '&lt;/textarea&gt;'), 'Meta editor allows textarea breakout');
	update_post_meta($own, 'argon_after_post', '');
	update_option('argon_ai_post_summary', 'false');
});
argon_test('public click endpoint rejects arbitrary links and hidden posts', function() use ($own, $draft, $protected){
	wp_set_current_user(0);
	update_post_meta($own, 'argon_enable_external_link_clicks', 'true');
	foreach (array($own, $draft, $protected) as $id){
		$before = get_post_meta($id, 'argon_external_link_clicks', true);
		[$status, $response] = argon_test_ajax('argon_click_external_link', array('_wpnonce' => wp_create_nonce('argon_external_link_clicks'), 'post_id' => (string) $id, 'url' => 'https://example.org/unlisted'));
		argon_test_assert($response['status'] === 'failed' && get_post_meta($id, 'argon_external_link_clicks', true) === $before, 'Public click wrote arbitrary data');
	}
});
argon_test('rendered links carry a working nonce and count legitimate clicks', function() use ($own){
	wp_set_current_user(0);
	$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(array('p' => $own));
	$GLOBALS['wp_query']->the_post();
	$html = argon_external_link_clicks_filter(get_post($own)->post_content);
	argon_test_assert(preg_match('/data-click-nonce="([^"]+)"/', $html, $matches) === 1, 'Rendered link has no proof');
	[$status, $response] = argon_test_ajax('argon_click_external_link', array('_wpnonce' => wp_create_nonce('argon_external_link_clicks'), 'post_id' => (string) $own, 'url' => 'https://example.org/', 'link_nonce' => $matches[1]));
	argon_test_assert($status === 200 && $response['status'] === 'success' && $response['count'] === 1, 'Legitimate click broken');
});
argon_test('default public page renders without PHP warnings', function() use ($own){
	wp_set_current_user(0);
	$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(array('p' => $own));
	$GLOBALS['wp_query']->the_post();
	$_SERVER['REQUEST_URI'] = '/?p=' . $own;
	$_SERVER['HTTP_X_PROVIDED_BY'] = '<img src=x onerror=alert(1)>';
	$_COOKIE['argon_card_radius'] = '</style><script>alert(1)</script>';
	ob_start();
	try {
		get_header();
		comments_template();
		get_footer();
		$html = ob_get_contents();
	} finally {
		ob_end_clean();
	}
	argon_test_assert(!str_contains($html, '</style><script>alert(1)</script>') && !str_contains($html, $_SERVER['HTTP_X_PROVIDED_BY']), 'Request data injected into page');
});
argon_test('integer comparator remains antisymmetric', function() use ($comment, $private){
	$a = get_comment($comment);
	$b = get_comment($private);
	argon_test_assert(is_int(argon_comment_cmp($a, $b)) && argon_comment_cmp($a, $b) === -argon_comment_cmp($b, $a), 'Sort callback violates comparator contract');
});

restore_error_handler();
$failed = array_filter($argon_test_results, static fn($result) => $result !== 'passed');
$report = array('php' => PHP_VERSION, 'wordpress' => $GLOBALS['wp_version'], 'passed' => count($argon_test_results) - count($failed), 'failed' => count($failed), 'results' => $argon_test_results);
$report_dir = __DIR__ . '/../temp';
wp_mkdir_p($report_dir);
file_put_contents($report_dir . '/security-report-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.json', wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo wp_json_encode(array('passed' => $report['passed'], 'failed' => $report['failed'])) . "\n";
if ($failed){
	throw new RuntimeException('Security regression failures: ' . wp_json_encode($failed));
}
