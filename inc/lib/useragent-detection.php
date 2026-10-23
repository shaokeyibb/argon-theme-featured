<?php
/**
 * Comment UA detection. These are display hints, never authentication signals.
 * Keep the historical platform/browser keys used by filters and icon mappings.
 * Version numbers are open ended; only marketing names require a catalog.
 */
function argon_ua_version($value) {
	if (!is_string($value)) return null;
	$value = str_replace('_', '.', trim($value, " \t\r\n\""));
	return preg_match('/^\d{1,8}(?:\.\d{1,8}){0,5}$/D', $value) ? $value : null;
}

function argon_ua_os_version($platform, $version, $hint = false) {
	$version = argon_ua_version($version);
	if ($version === null) return null;
	if ($platform === 'Windows' && $hint) {
		// UA-CH reports a UniversalApiContract version, not a Windows build.
		$major = (int) explode('.', $version)[0];
		if ($major >= 13) return '11';
		if ($major >= 1) return '10';
		return array('0.1.0' => '7', '0.2.0' => '8', '0.3.0' => '8.1')[$version] ?? null;
	}
	if ($platform === 'Macintosh') {
		// 10.15.7 and 10.16 in legacy UAs are compatibility/frozen values.
		if (!$hint && in_array($version, array('10.15.7', '10.16', '10.16.0'), true)) return null;
		$names = array('10.0' => 'Cheetah', '10.1' => 'Puma', '10.2' => 'Jaguar', '10.3' => 'Panther', '10.4' => 'Tiger', '10.5' => 'Leopard', '10.6' => 'Snow Leopard', '10.7' => 'Lion', '10.8' => 'Mountain Lion', '10.9' => 'Mavericks', '10.10' => 'Yosemite', '10.11' => 'El Capitan', '10.12' => 'Sierra', '10.13' => 'High Sierra', '10.14' => 'Mojave', '10.15' => 'Catalina', '11' => 'Big Sur', '12' => 'Monterey', '13' => 'Ventura', '14' => 'Sonoma', '15' => 'Sequoia', '26' => 'Tahoe', '27' => 'Golden Gate');
		$parts = explode('.', $version);
		$key = $parts[0] === '10' ? implode('.', array_slice($parts, 0, 2)) : $parts[0];
		return 'macOS ' . (isset($names[$key]) ? $names[$key] . ' ' : '') . $version;
	}
	if (in_array($platform, array('iPhone', 'iPad', 'iPod Touch'), true)) {
		return ($platform === 'iPad' && (int) $version >= 13 ? 'iPadOS ' : 'iOS ') . $version;
	}
	// Linux UA-CH often sends an empty/zero version, which is not a distro release.
	return $version === '0.0.0' ? null : $version;
}

function argon_ua_platform($ua) {
	$platform = null;
	$version = null;
	$patterns = array(
		'Windows Phone' => '/Windows Phone(?: OS)?[ \/]([\d.]+)/i',
		'OpenHarmony' => '/OpenHarmony(?:[ \/]([\d.]+))?/i',
		'HarmonyOS' => '/HarmonyOS(?:[ \/]([\d.]+))?/i',
		'iPad' => '/iPad.*?OS[ \/]([\d_]+)/i',
		'iPod Touch' => '/iPod.*?OS[ \/]([\d_]+)/i',
		'iPhone' => '/iPhone.*?OS[ \/]([\d_]+)/i',
		'Chrome OS' => '/CrOS\s+\S+\s+([\d.]+)/i',
		'Android' => '/Android(?:[ \/]([\d.]+))?/i',
		'Windows' => '/Windows(?: NT[ \/]([\d.]+))?/i',
		'Macintosh' => '/(?:Macintosh.*?Mac OS X|macOS|Mac OS X)(?:[ \/]([\d._]+))?/i',
		'Tizen' => '/Tizen(?:[ \/]([\d.]+))?/i',
		'KaiOS' => '/KaiOS(?:[ \/]([\d.]+))?/i',
		'webOS' => '/(?:webOS|hpwOS)(?:[ \/]([\d.]+))?/i',
		'Fuchsia' => '/Fuchsia(?:[ \/]([\d.]+))?/i',
		'FreeBSD' => '/FreeBSD(?:[ \/]([\d.]+))?/i',
		'OpenBSD' => '/OpenBSD(?:[ \/]([\d.]+))?/i',
		'NetBSD' => '/NetBSD(?:[ \/]([\d.]+))?/i',
		'DragonFly BSD' => '/DragonFly(?:[ \/]([\d.]+))?/i',
		'Haiku' => '/Haiku(?:[ \/]([\d.]+))?/i',
		'BlackBerry' => '/(?:BlackBerry|BB10)(?:[ \/]([\d.]+))?/i',
		'PlayBook' => '/PlayBook/i',
		'Linux' => '/(?:Linux|X11)/i',
	);
	foreach ($patterns as $name => $pattern) {
		if (preg_match($pattern, $ua, $match)) { $platform = $name; $version = $match[1] ?? null; break; }
	}
	if ($platform === 'Windows') {
		$version = array('10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista', '5.2' => 'XP x64 / Server 2003', '5.1' => 'XP', '5.0' => '2000', '4.0' => 'NT 4.0')[$version ?? ''] ?? $version;
		return array($platform, $version);
	}
	if ($platform === 'Linux') {
		$distros = array('Ubuntu' => 'Ubuntu', 'Debian' => 'Debian', 'Fedora' => 'Fedora', 'CentOS' => 'CentOS', 'Arch Linux' => 'Arch(?: Linux)?', 'openSUSE' => '(?:openSUSE|SUSE)', 'Manjaro' => 'Manjaro', 'Linux Mint' => '(?:Linux Mint|Mint)', 'Red Hat' => '(?:Red Hat|RedHat|RHEL)', 'Gentoo' => 'Gentoo', 'Kali Linux' => 'Kali', 'Alpine Linux' => 'Alpine', 'Rocky Linux' => 'Rocky', 'AlmaLinux' => 'AlmaLinux', 'NixOS' => 'NixOS', 'Pop!_OS' => 'Pop!_OS', 'Deepin' => 'Deepin', 'UOS' => 'UOS');
		foreach ($distros as $name => $token) {
			if (preg_match('~\b' . $token . '(?:[ /]([\d.]+))?\b~i', $ua, $match)) { $platform = $name; $version = $match[1] ?? null; break; }
		}
	}
	if ($platform === 'Android' && $version === '10' && preg_match('/;\s*K(?:\s|;|\)|$)/', $ua)) $version = null;
	if ($platform === 'Chrome OS' && $version === '14541.0.0') $version = null;
	// Safari 26+ freezes iOS/iPadOS at 18.6/18.7; do not invent an OS patch version.
	if (in_array($platform, array('iPhone', 'iPad', 'iPod Touch'), true) && preg_match('/Version\/(\d+)/', $ua, $safari) && (int) $safari[1] >= 26 && preg_match('/^18[_.][67](?:[_.]|$)/', $version ?? '')) $version = null;
	if (preg_match('/\b(Xbox One|Xbox|(?:New )?Nintendo (?:WiiU?|3?DS|Switch))\b/i', $ua, $console)) { $platform = $console[1]; $version = null; }
	return array($platform, argon_ua_os_version($platform, $version));
}

/** Known brands are ranked independently of randomized UA-CH list order. */
function argon_ua_brands($header) {
	$brands = array();
	if (!is_string($header)) return $brands;
	$aliases = array('google chrome' => 'Chrome', 'microsoft edge' => 'Edge', 'microsoft edge webview2' => 'Edge WebView2', 'opera gx' => 'Opera GX', 'opera' => 'Opera', 'brave' => 'Brave', 'vivaldi' => 'Vivaldi', 'yandex' => 'Yandex', 'yandex browser' => 'Yandex', 'samsung internet' => 'Samsung Internet', 'huawei browser' => 'Huawei Browser', 'chromium' => 'Chromium', 'chrome' => 'Chrome');
	preg_match_all('/"([^"\\\\]{1,64})"\s*;\s*v\s*=\s*"(\d+(?:\.\d+){0,5})"/', substr($header, 0, 4096), $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		$key = strtolower($match[1]);
		if (str_contains($key, 'not') && str_contains($key, 'brand')) continue;
		if (!preg_match('/^[a-z][a-z0-9 ._+-]*$/iD', $match[1])) continue;
		$brand = $aliases[$key] ?? $match[1];
		$brands[$brand] = $match[2];
	}
	return $brands;
}

function argon_parse_sec_ch_ua($sec_ch_ua, $sec_ch_ua_platform = null, $sec_ch_ua_platform_version = null, $sec_ch_ua_full_version_list = null, $user_agent = null) {
	$platform = null;
	$platforms = array('windows' => 'Windows', 'macos' => 'Macintosh', 'linux' => 'Linux', 'android' => 'Android', 'ios' => 'iPhone', 'ipados' => 'iPad', 'chrome os' => 'Chrome OS', 'chromium os' => 'Chrome OS', 'cros' => 'Chrome OS', 'harmonyos' => 'HarmonyOS', 'openharmony' => 'OpenHarmony', 'fuchsia' => 'Fuchsia');
	if (is_string($sec_ch_ua_platform)) $platform = $platforms[strtolower(trim($sec_ch_ua_platform, " \t\r\n\""))] ?? null;
	if ($platform === 'iPhone' && is_string($user_agent)) {
		if (stripos($user_agent, 'iPad') !== false) $platform = 'iPad';
		elseif (stripos($user_agent, 'iPod') !== false) $platform = 'iPod Touch';
	}
	$brands = argon_ua_brands($sec_ch_ua);
	$full = argon_ua_brands($sec_ch_ua_full_version_list);
	foreach ($full as $name => $value) $brands[$name] = $value;
	$browser = null;
	$rank = -1;
	foreach ($brands as $name => $value) {
		$priority = $name === 'Chromium' ? 0 : ($name === 'Chrome' ? 1 : 2);
		if ($priority > $rank) { $browser = $name; $rank = $priority; }
	}
	if ($platform === null && $browser === null) return null;
	return array('platform' => $platform, 'platform_version' => $platform ? argon_ua_os_version($platform, $sec_ch_ua_platform_version, true) : null, 'browser' => $browser, 'version' => $browser !== null ? $brands[$browser] : null);
}

function argon_parse_user_agent($u_agent = null, $sec_ch_ua = null, $sec_ch_ua_platform = null, $sec_ch_ua_platform_version = null, $sec_ch_ua_full_version_list = null) {
	$u_agent ??= $_SERVER['HTTP_USER_AGENT'] ?? '';
	$u_agent = is_string($u_agent) ? substr($u_agent, 0, 8192) : '';
	$parsed = argon_parse_legacy_ua($u_agent);
	$hint = argon_parse_sec_ch_ua($sec_ch_ua, $sec_ch_ua_platform, $sec_ch_ua_platform_version, $sec_ch_ua_full_version_list, $u_agent);
	if ($hint === null) return $parsed;
	if ($hint['platform'] !== null) {
		// Generic Linux/Android hints must not erase a more specific UA platform.
		$generic = ($hint['platform'] === 'Linux' && $parsed['platform'] !== null && preg_match('/Linux|X11/i', $u_agent)) || ($hint['platform'] === 'Android' && in_array($parsed['platform'], array('HarmonyOS', 'OpenHarmony', 'Kindle Fire'), true));
		if (!$generic) {
			if ($parsed['platform'] !== $hint['platform']) $parsed['platform_version'] = null;
			$parsed['platform'] = $hint['platform'];
			if ($hint['platform_version'] !== null) $parsed['platform_version'] = $hint['platform_version'];
		}
	}
	if ($hint['browser'] !== null) {
		$generic = in_array($hint['browser'], array('Chrome', 'Chromium'), true);
		if (!$generic || in_array($parsed['browser'], array(null, 'Chrome', 'Chromium', 'Safari', 'AppleWebKit'), true)) {
			$version = $hint['version'];
			// A major-only hint must not replace a more precise matching UA version.
			if ($parsed['browser'] === $hint['browser'] && $parsed['version'] !== null && strpos($version, '.') === false && str_starts_with($parsed['version'], $version . '.')) $version = $parsed['version'];
			$parsed['browser'] = $hint['browser'];
			$parsed['version'] = $version;
		}
	}
	return $parsed;
}

/** Specific products precede the compatibility engine tokens they include. */
function argon_ua_specific_browser($ua) {
	$tokens = array('MicroMessenger' => 'WeChat', 'QQEX|QQ(?=/)' => 'QQ', 'DingTalk' => 'DingTalk', 'AlipayClient' => 'Alipay', 'Lark' => 'Lark', 'MaiMai' => 'MaiMai', 'Weibo|__weibo__' => 'Weibo', 'MQQBrowser|QQBrowser' => 'QQ Browser', 'Quark' => 'Quark', 'UCBrowser|UCWEB' => 'UC Browser', 'HeyTapBrowser' => 'HeyTap Browser', 'VivoBrowser' => 'vivo Browser', 'HONORBrowser' => 'HONOR Browser', 'HuaweiBrowser' => 'Huawei Browser', 'XiaoMi/MiuiBrowser|MiuiBrowser' => 'Mi Browser', 'SLBrowser' => 'Lenovo Browser', 'SamsungBrowser' => 'Samsung Internet', 'EdgiOS|EdgA|Edg|Edge' => 'Edge', 'FxiOS' => 'Firefox', 'CriOS' => 'Chrome', 'OPiOS|OPR' => 'Opera', 'Opera Mini' => 'Opera Mini', 'Opera Mobi' => 'Opera Mobile', 'DuckDuckGo|Ddg' => 'DuckDuckGo', 'Brave' => 'Brave', 'Vivaldi' => 'Vivaldi', 'YaBrowser' => 'Yandex', 'Waterfox' => 'Waterfox', 'LibreWolf' => 'LibreWolf', 'Floorp' => 'Floorp', 'PaleMoon' => 'Pale Moon', 'SeaMonkey' => 'SeaMonkey', 'Whale' => 'Whale', 'CocCocBrowser' => 'Coc Coc', 'HeadlessChrome' => 'HeadlessChrome', 'Chromium' => 'Chromium', 'Electron' => 'Electron', 'Epiphany' => 'GNOME Web', 'Falkon' => 'Falkon', 'QtWebEngine' => 'QtWebEngine');
	foreach ($tokens as $token => $name) {
		if (preg_match('~(?:^|[ ;()/])(?:' . $token . ')[ /]([0-9]+(?:\.[0-9]+)*)~i', $ua, $match)) return array($name, $match[1]);
	}
	if (preg_match('/;\s*wv\).*?Chrome\/([\d.]+)/i', $ua, $match)) return array('Android WebView', $match[1]);
	return null;
}

/**
 * Legacy token fallbacks derived from Jesse G. Donat's MIT-licensed PhpUserAgent.
 * https://github.com/donatj/PhpUserAgent
 */
function argon_parse_legacy_ua($u_agent) {
	[$platform, $platform_version] = argon_ua_platform($u_agent);
	$specific = argon_ua_specific_browser($u_agent);
	if ($specific !== null) return array('platform' => $platform, 'platform_version' => $platform_version, 'browser' => $specific[0], 'version' => $specific[1]);
	preg_match_all( '%(?P<browser>Camino|Kindle(\ Fire)?|Firefox|Iceweasel|IceCat|Safari|MSIE|Trident|AppleWebKit|
				TizenBrowser|(?:Headless)?Chrome|YaBrowser|Vivaldi|IEMobile|Opera|OPR|Silk|Midori|Edge|Edg|EdgA|CriOS|UCBrowser|Puffin|OculusBrowser|SamsungBrowser|
				MicroMessenger|QQEX|QQ(?=/|\s)|ZhihuHybrid|Quark|XiaoMi/MiuiBrowser|HuaweiBrowser|Lark|MaiMai|QQBrowser|SLBrowser|
				Baiduspider|Googlebot|YandexBot|bingbot|Lynx|Version|Wget|curl|
				Valve\ Steam\ Tenfoot|
				NintendoBrowser|PLAYSTATION\ (\d|Vita)+)
				(?:\)?;?)
				(?:(?:[:/ ])(?P<version>[0-9A-Z.]+)|/(?:[A-Z]*))%ix',
		$u_agent, $result );

	// If nothing matched, return null (to avoid undefined index errors)
	if ( ! isset( $result['browser'][0] ) || ! isset( $result['version'][0] ) ) {
		if ( preg_match( '%^(?!Mozilla)(?P<browser>[A-Z0-9\-]+)(/(?P<version>[0-9A-Z.]+))?%ix', $u_agent, $result ) ) {
			return array(
				'platform' => $platform ?: null,
				'platform_version' => $platform_version ?: null,
				'browser'  => $result['browser'],
				'version'  => isset( $result['version'] ) ? $result['version'] ?: null : null
			);
		}

		return array('platform' => $platform, 'platform_version' => $platform_version, 'browser' => null, 'version' => null);
	}

	$rv_result = null;
	if ( preg_match( '/rv:(?P<version>[0-9A-Z.]+)/i', $u_agent, $rv_result ) ) {
		$rv_result = $rv_result['version'];
	}

	$browser = $result['browser'][0];
	$version = $result['version'][0];

	$lowerBrowser = array_map( 'strtolower', $result['browser'] );

	$find = function ( $search, &$key = null, &$value = null ) use ( $lowerBrowser ) {
		$search = (array) $search;

		foreach ( $search as $val ) {
			$xkey = array_search( strtolower( $val ), $lowerBrowser );
			if ( $xkey !== false ) {
				$value = $val;
				$key   = $xkey;

				return true;
			}
		}

		return false;
	};

	$findT = function ( array $search, &$key = null, &$value = null ) use ( $find ) {
		$value2 = null;
		if ( $find( array_keys( $search ), $key, $value2 ) ) {
			$value = $search[ $value2 ];

			return true;
		}

		return false;
	};

	$key = 0;
	$val = '';
	if ( $find( 'QQEX', $key, $browser ) || $find( 'QQ', $key, $browser ) ) {
		$browser = 'QQ';
		$version = $result['version'][ $key ];
	} elseif ( $find( 'ZhihuHybrid', $key, $browser ) ) {
		$browser = 'Zhihu';
		// 尝试从 UA 中提取版本号
		// Android: com.zhihu.android/Futureve/10.41.0
		// iOS: osee2unifiedReleaseVersion/10.78.0
		if ( preg_match( '/(?:com\.zhihu\.android\/[^\/]+\/(\d+\.\d+\.\d+)|osee2unifiedReleaseVersion\/(\d+\.\d+\.\d+))/i', $u_agent, $zhihu_version ) ) {
			$version = $zhihu_version[1] ?: $zhihu_version[2];
		} else {
			$version = $result['version'][ $key ] ?? null;
		}
	} elseif ( $findT( array(
		'OPR'                => 'Opera',
		'UCBrowser'          => 'UC Browser',
		'YaBrowser'          => 'Yandex',
		'Iceweasel'          => 'Firefox',
		'Icecat'             => 'Firefox',
		'CriOS'              => 'Chrome',
		'Edg'                => 'Edge',
		'EdgA'               => 'Edge',
		'MicroMessenger'     => 'WeChat',
		'Quark'              => 'Quark',
		'XiaoMi/MiuiBrowser' => 'Mi Browser',
		'HuaweiBrowser'      => 'Huawei Browser',
		'Lark'               => 'Lark',
		'MaiMai'             => 'MaiMai',
		'QQBrowser'          => 'QQ Browser',
		'SLBrowser'          => 'Lenovo Browser',
		'SamsungBrowser'     => 'Samsung Internet'
	), $key, $browser ) ) {
		$version = $result['version'][ $key ];
	} elseif ( $find( 'Playstation Vita', $key, $platform ) ) {
		$platform = 'PlayStation Vita';
		$browser  = 'Browser';
	} elseif ( $find( array( 'Kindle Fire', 'Silk' ), $key, $val ) ) {
		$browser  = $val == 'Silk' ? 'Silk' : 'Kindle';
		$platform = 'Kindle Fire';
		if ( ! ( $version = $result['version'][ $key ] ) || ! is_numeric( $version[0] ) ) {
			$version = $result['version'][ array_search( 'Version', $result['browser'] ) ];
		}
	} elseif ( $find( 'NintendoBrowser', $key ) || $platform == 'Nintendo 3DS' ) {
		$browser = 'NintendoBrowser';
		$version = $result['version'][ $key ];
	} elseif ( $find( 'Kindle', $key, $platform ) ) {
		$browser = $result['browser'][ $key ];
		$version = $result['version'][ $key ];
	} elseif ( $find( 'Opera', $key, $browser ) ) {
		$find( 'Version', $key );
		$version = $result['version'][ $key ];
	} elseif ( $find( 'Puffin', $key, $browser ) ) {
		$version = $result['version'][ $key ];
		if ( strlen( $version ) > 3 ) {
			$part = substr( $version, - 2 );
			if ( ctype_upper( $part ) ) {
				$version = substr( $version, 0, - 2 );

				$flags = array(
					'IP' => 'iPhone',
					'IT' => 'iPad',
					'AP' => 'Android',
					'AT' => 'Android',
					'WP' => 'Windows Phone',
					'WT' => 'Windows'
				);
				if ( isset( $flags[ $part ] ) ) {
					$platform = $flags[ $part ];
				}
			}
		}
	} elseif ( $find( array(
		'IEMobile',
		'Edge',
		'Midori',
		'Vivaldi',
		'OculusBrowser',
		'Valve Steam Tenfoot',
		'Firefox',
		'Chrome',
		'HeadlessChrome'
	), $key, $browser ) ) {
		$version = $result['version'][ $key ];
	} elseif ( $rv_result && $find( 'Trident' ) ) {
		$browser = 'MSIE';
		$version = $rv_result;
	} elseif ( $browser == 'AppleWebKit' ) {
		$safari_key = null;
		if ( $platform == 'Android' ) {
			$browser = 'Android Browser';
		} elseif ( strpos( (string) $platform, 'BB' ) === 0 ) {
			$browser  = 'BlackBerry Browser';
			$platform = 'BlackBerry';
		} elseif ( $platform == 'BlackBerry' || $platform == 'PlayBook' ) {
			$browser = 'BlackBerry Browser';
		} else {
			$safari_found = $find( 'Safari', $safari_key, $browser );
			if ( ! $safari_found ) {
				$find( 'TizenBrowser', $key, $browser );
			}
		}

		// Safari/<build> identifies WebKit; Version/<release> identifies Safari.
		$version = $find('Version', $key) ? $result['version'][$key] : null;
	} elseif ( $pKey = preg_grep( '/playstation \d/i', $result['browser'] ) ) {
		$pKey = reset( $pKey );

		$platform = 'PlayStation ' . preg_replace( '/\D/', '', $pKey );
		$browser  = 'NetFront';
	}

	return array( 'platform' => $platform ?: null, 'platform_version' => $platform_version ?: null, 'browser' => $browser ?: null, 'version' => $version ?: null );
}

