<?php
/** Parent-theme identity and explicit local/custom asset origin. */
$GLOBALS['theme_version'] = wp_get_theme(get_template())->get('Version');
$GLOBALS['assets_path'] = get_template_directory_uri();
if (get_option('argon_assets_path') === 'custom') {
    $argon_custom_assets = str_replace('%theme_version%', $GLOBALS['theme_version'], (string) get_option('argon_custom_assets_path'));
    if (wp_http_validate_url($argon_custom_assets)) {
        $GLOBALS['assets_path'] = untrailingslashit(esc_url_raw($argon_custom_assets));
    }
}
$GLOBALS['openai_baseurl'] = get_option('argon_openai_baseurl') === 'custom'
    ? untrailingslashit((string) get_option('argon_custom_openai_baseurl'))
    : 'https://api.openai.com';
require_once __DIR__ . '/setup.php';
require_once __DIR__ . '/design.php';
require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/updates.php';
