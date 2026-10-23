<?php
if (!defined('ARGON_UA_TESTING')) exit;
$results = array();
set_error_handler(static function($severity, $message, $file, $line) {
	if (error_reporting() & $severity) throw new ErrorException($message, 0, $severity, $file, $line);
	return false;
});
function ua_test($name, $callback) {
	try { $callback(); $GLOBALS['results'][$name] = 'passed'; }
	catch (Throwable $error) { $GLOBALS['results'][$name] = get_class($error) . ': ' . $error->getMessage(); }
}
function ua_assert($condition, $message) { if (!$condition) throw new RuntimeException($message); }
foreach (json_decode(file_get_contents(__DIR__ . '/user-agent-cases.json'), true, 512, JSON_THROW_ON_ERROR) as $case) {
	ua_test($case['name'], static function() use ($case) {
		$actual = argon_parse_user_agent(...$case['input']);
		foreach ($case['expected'] as $key => $expected) ua_assert($actual[$key] === $expected, $key . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual[$key]));
	});
}
ua_test('comment metadata round trip and public rendering use high entropy hints', function() {
	add_filter('pre_wp_mail', '__return_true');
	$headers = array('sec_ch_ua' => '"Not A Brand";v="99", "Chromium";v="140", "Microsoft Edge";v="140"', 'sec_ch_ua_platform' => '"Windows"', 'sec_ch_ua_platform_version' => '"19.0.0"', 'sec_ch_ua_full_version_list' => '"Microsoft Edge";v="140.0.3485.81", "Chromium";v="140.0.7339.81"');
	foreach ($headers as $key => $value) $_SERVER['HTTP_' . strtoupper($key)] = wp_slash($value);
	$_COOKIE['argon_user_token'] = str_repeat('a', 32);
	$post = wp_insert_post(array('post_title' => 'UA fixture', 'post_status' => 'publish'));
	$id = wp_insert_comment(array('comment_post_ID' => $post, 'comment_author' => 'UA test', 'comment_content' => 'UA test', 'comment_approved' => 1));
	post_comment_updatemetas($id);
	foreach ($headers as $key => $value) ua_assert(get_comment_meta($id, $key, true) === $value, $key . ' did not round trip');
	$GLOBALS['argon_comment_ua'] = 'platformbrowserVersion';
	$GLOBALS['argon_comment_show_ua'] = array('platform' => true, 'browser' => true, 'version' => true);
	wp_set_current_user(0);
	$html = parse_ua_and_icon('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', $id);
	ua_assert(str_contains($html, 'Windows 11') && str_contains($html, 'Edge 140.0.3485.81'), $html);
	ua_assert(!str_contains($html, ' title='), 'Raw header exposed to guest');
	foreach ($headers as $key => $value) unset($_SERVER['HTTP_' . strtoupper($key)]);
	wp_delete_comment($id, true); wp_delete_post($post, true);
});
ua_test('historical comment with missing full-version metadata retains browser and OS', function() {
	$id = wp_insert_comment(array('comment_content' => 'Old UA'));
	update_comment_meta($id, 'sec_ch_ua', '"Google Chrome";v="140"');
	$html = parse_ua_and_icon('Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 Chrome/140.0.1.2 Safari/537.36', $id);
	ua_assert(str_contains($html, 'Windows 7') && str_contains($html, 'Chrome 140.0.1.2'), $html);
	wp_delete_comment($id, true);
});
ua_test('malformed header types are not stored or executed', function() {
	$_SERVER['HTTP_SEC_CH_UA'] = array('bad');
	$id = wp_insert_comment(array('comment_content' => 'Malformed UA'));
	post_comment_updatemetas($id);
	ua_assert(!metadata_exists('comment', $id, 'sec_ch_ua'), 'Array header saved');
	unset($_SERVER['HTTP_SEC_CH_UA']);
	wp_set_current_user(1);
	$html = parse_ua_and_icon("<img src=x onerror='alert(1)'>");
	ua_assert(!str_contains($html, '<img') && !str_contains($html, "title='<img"), 'Raw UA injected markup');
	wp_delete_comment($id, true);
});
ua_test('hidden UA option still returns no markup', function() {
	$GLOBALS['argon_comment_ua'] = 'hidden';
	ua_assert(parse_ua_and_icon('Chrome/140.0') === '', 'Hidden setting ignored');
});
restore_error_handler();
$failed = array_filter($results, static fn($result) => $result !== 'passed');
$report = array('php' => PHP_VERSION, 'wordpress' => $GLOBALS['wp_version'], 'passed' => count($results) - count($failed), 'failed' => count($failed), 'results' => $results);
$report_dir = dirname(__DIR__) . '/temp';
if (!wp_mkdir_p($report_dir) || file_put_contents($report_dir . '/user-agent-report-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.json', wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
	throw new RuntimeException('Could not write user-agent report');
}
echo wp_json_encode($report);
if ($failed) throw new RuntimeException('UA regressions: ' . wp_json_encode($failed));
