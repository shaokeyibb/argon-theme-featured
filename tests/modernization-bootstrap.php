<?php
// Installed as an MU plugin in the disposable test site, before the theme loads.
error_reporting(E_ALL);
$GLOBALS['argon_boot_diagnostics'] = array();
function argon_test_theme_frame(): bool {
	foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
		$file = $frame['file'] ?? '';
		if (str_contains($file, '/themes/argon-theme/') && !str_contains($file, '/tests/')) return true;
	}
	return false;
}
set_error_handler(static function($severity, $message, $file, $line) {
	if ((error_reporting() & $severity) && (str_contains($file, '/themes/argon-theme/') || argon_test_theme_frame())) {
		$GLOBALS['argon_boot_diagnostics'][] = "$message ($file:$line)";
	}
	return false;
});
foreach (array('doing_it_wrong_run', 'deprecated_function_run', 'deprecated_argument_run', 'deprecated_hook_run') as $hook) {
	add_action($hook, static function($name) use ($hook) {
		if (argon_test_theme_frame()) $GLOBALS['argon_boot_diagnostics'][] = "$hook: $name";
	});
}
