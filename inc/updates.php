<?php
/** Opt-in, cached fork updates using WordPress's native theme update API. */
function argon_check_fork_update($update, $theme_data, $stylesheet) {
	if ($stylesheet !== get_template() || get_option('argon_update_source', 'stop') !== 'github') {
		return $update;
	}
	$key = 'argon_featured_release_v3';
	$release = get_transient($key);
	if ($release === false) {
		$response = wp_safe_remote_get('https://api.github.com/repos/shaokeyibb/argon-theme-featured/releases/latest', array(
			'timeout' => 5, 'limit_response_size' => 65536,
			'headers' => array('Accept' => 'application/vnd.github+json')
		));
		$release = array();
		if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
			$decoded = json_decode(wp_remote_retrieve_body($response), true);
			if (is_array($decoded)) $release = $decoded;
		}
		set_transient($key, $release, empty($release) ? 15 * MINUTE_IN_SECONDS : 6 * HOUR_IN_SECONDS);
	}
	$version = ltrim((string) ($release['tag_name'] ?? ''), 'v');
	if (!empty($release['prerelease']) || !empty($release['draft']) || !preg_match('/^\d+\.\d+\.\d+$/D', $version) || !version_compare($version, $theme_data['Version'], '>')) {
		return $update;
	}
	foreach ($release['assets'] ?? array() as $asset) {
		$url = $asset['browser_download_url'] ?? '';
		if (($asset['name'] ?? '') === 'argon-theme-featured.zip' && str_starts_with($url, 'https://github.com/shaokeyibb/argon-theme-featured/releases/download/')) {
			return array('id' => 'https://github.com/shaokeyibb/argon-theme-featured', 'slug' => $stylesheet, 'version' => $version,
				'url' => 'https://github.com/shaokeyibb/argon-theme-featured', 'package' => esc_url_raw($url));
		}
	}
	return $update;
}
add_filter('update_themes_github.com', 'argon_check_fork_update', 10, 3);
