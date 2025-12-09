<?php

/**
 * Parses Sec-CH-UA series headers into structured data
 *
 * @param string $sec_ch_ua Sec-CH-UA header value
 * @param string|null $sec_ch_ua_platform Sec-CH-UA-Platform header value
 * @param string|null $sec_ch_ua_platform_version Sec-CH-UA-Platform-Version header value
 * @param string|null $sec_ch_ua_full_version_list Sec-CH-UA-Full-Version-List header value
 * @param string|null $user_agent User-Agent string for fallback device type detection (e.g., iOS device type)
 *
 * @return array|null Parsed data array or null if parsing fails
 */
function argon_parse_sec_ch_ua( $sec_ch_ua, $sec_ch_ua_platform = null, $sec_ch_ua_platform_version = null, $sec_ch_ua_full_version_list = null, $user_agent = null ) {
	$platform = null;
	$platform_version = null;
	$browser = null;
	$version = null;

	// 解析平台信息
	if ( $sec_ch_ua_platform !== null ) {
		// 移除引号
		$platform = trim( $sec_ch_ua_platform, '"' );
		
		// 标准化平台名称
		$platform_mapping = array(
			'Windows' => 'Windows',
			'macOS' => 'Macintosh',
			'Linux' => 'Linux',
			'Android' => 'Android',
			'iOS' => 'iPhone', // 需要根据设备类型进一步判断
			'Chrome OS' => 'Chrome OS',
			'CrOS' => 'Chrome OS',
		);
		
		if ( isset( $platform_mapping[ $platform ] ) ) {
			$platform = $platform_mapping[ $platform ];
		}
		
		// 处理 iOS 平台（需要根据 User-Agent 或其他信息判断设备类型）
		if ( $platform === 'iPhone' ) {
			$ua = $user_agent;
			if ( $ua === null && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
				$ua = $_SERVER['HTTP_USER_AGENT'];
			}
			if ( $ua !== null ) {
				if ( strpos( $ua, 'iPad' ) !== false ) {
					$platform = 'iPad';
				} elseif ( strpos( $ua, 'iPod' ) !== false ) {
					$platform = 'iPod Touch';
				}
			}
		}
	}

	// 解析平台版本
	if ( $sec_ch_ua_platform_version !== null ) {
		$platform_version = trim( $sec_ch_ua_platform_version, '"' );
		
		// 处理 Windows 版本映射
		if ( $platform === 'Windows' ) {
			// 根据 sec_ch_ua_platform_version 映射到 Windows 版本
			// https://github.com/WICG/ua-client-hints/issues/220#issuecomment-870858413
			$windows_versions = array(
				'0' => '7/8/8.1',
				'1' => '10 1507',
				'2' => '10 1511',
				'3' => '10 1607',
				'4' => '10 1703',
				'5' => '10 1709',
				'6' => '10 1803',
				'7' => '10 1809',
				'8' => '10 1903/1909',
				'10' => '10 2004/20H2/21H1/21H2',
			);
			
			// 提取主版本号（整数部分）
			if ( preg_match( '/^(\d+)/', $platform_version, $matches ) ) {
				$major_version = $matches[1];
				
				// 先尝试完整版本号匹配（如 "1.0.0"）
				if ( isset( $windows_versions[ $platform_version ] ) ) {
					$platform_version = $windows_versions[ $platform_version ];
				}
				// 再尝试主版本号匹配（如 "1"）
				elseif ( isset( $windows_versions[ $major_version ] ) ) {
					$platform_version = $windows_versions[ $major_version ];
				}
				// 对于 13 及以上的版本，映射到 Win11
				elseif ( intval( $major_version ) >= 13 ) {
					$platform_version = '11';
				}
			}
		}
		
		// 处理 macOS 版本映射
		if ( $platform === 'Macintosh' || strpos( $platform, 'Mac' ) === 0 ) {
			$macos_version_names = array(
				'10.12' => 'Sierra',
				'10.13' => 'High Sierra',
				'10.14' => 'Mojave',
				'10.15' => 'Catalina',
				'11' => 'Big Sur',
				'12' => 'Monterey',
				'13' => 'Ventura',
				'14' => 'Sonoma',
				'15' => 'Sequoia',
				'26' => 'Tahoe'
			);
			
			// 保存原始版本号
			$original_version = $platform_version;
			
			// 提取主版本号
			if ( preg_match( '/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $platform_version, $matches ) ) {
				$major = $matches[1];
				$minor = isset( $matches[2] ) ? $matches[2] : '0';
				$patch = isset( $matches[3] ) ? $matches[3] : '0';
				
				// macOS 10.16+ 实际上是 macOS 11+
				if ( $major == '10' && intval( $minor ) >= 16 ) {
					$major = ( intval( $minor ) - 6 );
					$minor = '0';
					// 重新构建版本号
					$original_version = $major . '.' . $minor . ( $patch != '0' ? '.' . $patch : '' );
				}
				
				$version_key = $major . ( $minor != '0' ? '.' . $minor : '' );
				$version_name = null;
				
				if ( isset( $macos_version_names[ $version_key ] ) ) {
					$version_name = $macos_version_names[ $version_key ];
				} elseif ( isset( $macos_version_names[ $major ] ) ) {
					$version_name = $macos_version_names[ $major ];
				}
				
				// 格式化：macOS + 版本名称 + 版本号
				if ( $version_name !== null ) {
					$platform_version = 'macOS ' . $version_name . ' ' . $original_version;
				} else {
					$platform_version = 'macOS ' . $original_version;
				}
			}
		}
		
		// 处理 iOS/iPadOS 版本映射
		if ( $platform === 'iPhone' || $platform === 'iPad' || $platform === 'iPod Touch' ) {
			$ios_version_names = array(
				'9' => '9',
				'10' => '10',
				'11' => '11',
				'12' => '12',
				'13' => '13',
				'14' => '14',
				'15' => '15',
				'16' => '16',
				'17' => '17',
				'18' => '18'
			);
			
			// 保存原始版本号
			$original_version = $platform_version;
			
			// 提取主版本号
			if ( preg_match( '/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $platform_version, $matches ) ) {
				$major = $matches[1];
				$minor = isset( $matches[2] ) ? $matches[2] : '0';
				$patch = isset( $matches[3] ) ? $matches[3] : '0';
				
				// 构建完整版本号
				$full_version = $major . '.' . $minor . ( $patch != '0' ? '.' . $patch : '' );
				
				// 判断是 iOS 还是 iPadOS
				// iPadOS 13+ 才有名称，之前统一使用 iOS
				$os_name = 'iOS';
				if ( $platform === 'iPad' && intval( $major ) >= 13 ) {
					$os_name = 'iPadOS';
				}
				
				// 格式化：iOS/iPadOS + 版本号
				$platform_version = $os_name . ' ' . $full_version;
			}
		}
	}

	// 解析浏览器信息
	if ( $sec_ch_ua !== null ) {
		// Sec-CH-UA 格式: "Chromium";v="116", "Google Chrome";v="116", "Not=A?Brand";v="8"
		// 优先使用 Sec-CH-UA-Full-Version-List（如果存在）
		$ua_string = $sec_ch_ua_full_version_list !== null ? $sec_ch_ua_full_version_list : $sec_ch_ua;
		
		// 解析浏览器品牌和版本
		// 匹配格式: "Brand";v="Version"
		if ( preg_match_all( '/"([^"]+)";v="([^"]+)"/', $ua_string, $matches, PREG_SET_ORDER ) ) {
			$chromium_brand = null;
			$chromium_version = null;
			
			// 首先尝试找到非 "Not A Brand" 变体的品牌
			foreach ( $matches as $match ) {
				$brand = $match[1];
				$ver = $match[2];
				
				// 检测是否为 "Not A Brand" 变体
				// 检查 brand 中是否同时包含 "Not"、"A" 和 "Brand"（不区分大小写，不关心顺序和中间内容）
				$is_not_a_brand = (
					stripos( $brand, 'Not' ) !== false &&
					stripos( $brand, 'A' ) !== false &&
					stripos( $brand, 'Brand' ) !== false
				);
				
				// 跳过 "Not A Brand" 变体
				if ( $is_not_a_brand ) {
					continue;
				}
				
				// 如果是 Chromium，先保存起来（作为备选）
				if ( $brand === 'Chromium' ) {
					$chromium_brand = $brand;
					$chromium_version = $ver;
					continue;
				}
				
				// 找到第一个有效品牌（非 Not 且非 Chromium），使用它
				$browser = $brand;
				$version = $ver;
				break;
			}
			
			// 如果没有找到其他品牌，但有 Chromium，则使用 Chromium
			if ( $browser === null && $chromium_brand !== null ) {
				$browser = $chromium_brand;
				$version = $chromium_version;
			}
		}
		
		// 标准化浏览器名称
		if ( $browser !== null ) {
			$browser_mapping = array(
				'Google Chrome' => 'Chrome',
				'Microsoft Edge' => 'Edge',
				'Opera' => 'Opera',
				'Brave' => 'Brave',
				'Vivaldi' => 'Vivaldi',
				'Yandex' => 'Yandex',
			);
			
			if ( isset( $browser_mapping[ $browser ] ) ) {
				$browser = $browser_mapping[ $browser ];
			}
		}
	}

	// 如果成功解析到浏览器信息，返回结果
	if ( $browser !== null ) {
		return array(
			'platform' => $platform ?: null,
			'platform_version' => $platform_version ?: null,
			'browser' => $browser,
			'version' => $version ?: null
		);
	}

	// 解析失败，返回 null
	return null;
}

/**
 * Parses a user agent string into its important parts
 *
 * @param string|null $u_agent User agent string to parse or null. Uses $_SERVER['HTTP_USER_AGENT'] on NULL
 *
 * @return string[] an array with browser, version and platform keys
 * @throws \InvalidArgumentException on not having a proper user agent to parse.
 *
 * @author Jesse G. Donat <donatj@gmail.com>
 *
 * @link https://donatstudios.com/PHP-Parser-HTTP_USER_AGENT
 * @link https://github.com/donatj/PhpUserAgent
 *
 * @license MIT
 */
function argon_parse_user_agent( $u_agent = null, $sec_ch_ua = null, $sec_ch_ua_platform = null, $sec_ch_ua_platform_version = null, $sec_ch_ua_full_version_list = null ) {
	if ( $u_agent === null && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$u_agent = $_SERVER['HTTP_USER_AGENT'];
	}

	if ( $u_agent === null ) {
		throw new \InvalidArgumentException( 'parse_user_agent requires a user agent' );
	}

	$platform = null;
	$platform_version = null;
	$browser  = null;
	$version  = null;

	$empty = array( 'platform' => $platform, 'platform_version' => $platform_version, 'browser' => $browser, 'version' => $version );

	if ( ! $u_agent ) {
		return $empty;
	}

	// 如果传入了 Sec-CH-UA 系列 Header，优先使用它们进行解析
	if ( $sec_ch_ua !== null && $sec_ch_ua !== '' ) {
		$parsed_from_sec_ch = argon_parse_sec_ch_ua( $sec_ch_ua, $sec_ch_ua_platform, $sec_ch_ua_platform_version, $sec_ch_ua_full_version_list, $u_agent );
		if ( $parsed_from_sec_ch !== null ) {
			return $parsed_from_sec_ch;
		}
	}

	// 如果 Sec-CH-UA 解析失败或不存在，回退到使用 User-Agent Header

	if ( preg_match( '/\((.*?)\)/m', $u_agent, $parent_matches ) ) {
		preg_match_all( '/(?P<platform>BB\d+;|Android|CrOS|Tizen|iPhone|iPad|iPod|Linux|(Open|Net|Free)BSD|Macintosh|Windows(\ Phone)?|Silk|linux-gnu|BlackBerry|PlayBook|X11|(New\ )?Nintendo\ (WiiU?|3?DS|Switch)|Xbox(\ One)?)
				(?:\ [^;]*)?
				(?:;|$)/imx', $parent_matches[1], $result );

		$priority = array(
			'Xbox One',
			'Xbox',
			'Windows Phone',
			'Tizen',
			'Android',
			'FreeBSD',
			'NetBSD',
			'OpenBSD',
			'CrOS',
			'X11'
		);

		$result['platform'] = array_unique( $result['platform'] );
		if ( count( $result['platform'] ) > 1 ) {
			if ( $keys = array_intersect( $priority, $result['platform'] ) ) {
				$platform = reset( $keys );
			} else {
				$platform = $result['platform'][0];
			}
		} elseif ( isset( $result['platform'][0] ) ) {
			$platform = $result['platform'][0];
		}
	}

	if ( $platform == 'linux-gnu' || $platform == 'X11' ) {
		$platform = 'Linux';
	} elseif ( $platform == 'CrOS' ) {
		$platform = 'Chrome OS';
	}

	// 检测 Linux 发行版
	if ( $platform == 'Linux' ) {
		// 常见的 Linux 发行版标识（按优先级排序）
		$distros = array(
			'Ubuntu'     => array( '/Ubuntu/i', '/X11; Ubuntu/i' ),
			'Debian'     => array( '/Debian/i', '/X11; Debian/i' ),
			'Fedora'     => array( '/Fedora/i' ),
			'CentOS'     => array( '/CentOS/i' ),
			'Arch Linux' => array( '/Arch Linux/i', '/X11; Arch/i', '/\bArch\b/i' ),
			'openSUSE'   => array( '/openSUSE/i', '/SUSE/i' ),
			'Manjaro'    => array( '/Manjaro/i' ),
			'Linux Mint' => array( '/Linux Mint/i', '/Mint/i' ),
			'Red Hat'    => array( '/Red Hat/i', '/RedHat/i' ),
			'Gentoo'     => array( '/Gentoo/i' ),
		);

		foreach ( $distros as $distro_name => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match( $pattern, $u_agent ) ) {
					$platform = $distro_name;
					break 2; // 跳出两层循环
				}
			}
		}
	}

	// 检测操作系统版本
	if ( $platform ) {
		// Windows 版本检测 (Windows NT 10.0, Windows NT 6.1, etc.)
		if ( strpos( $platform, 'Windows' ) === 0 ) {
			if ( preg_match( '/Windows\s+NT\s+(\d+\.\d+)/i', $u_agent, $win_match ) ) {
				$nt_version = $win_match[1];
				// 将 Windows NT 版本号转换为友好的版本名称
				$windows_versions = array(
					'10.0' => '10/11',
					'6.3'  => '8.1',
					'6.2'  => '8',
					'6.1'  => '7',
					'6.0'  => 'Vista',
					'5.2'  => 'Server 2003',
					'5.1'  => 'XP',
					'5.0'  => '2000'
				);
				$platform_version = isset( $windows_versions[ $nt_version ] ) ? $windows_versions[ $nt_version ] : $nt_version;
			} elseif ( preg_match( '/Windows\s+Phone\s+OS\s+([0-9.]+)/i', $u_agent, $wp_match ) ) {
				$platform_version = $wp_match[1];
			}
		}
		// macOS 版本检测 (Mac OS X 10_15_7, macOS 12.0, etc.)
		elseif ( $platform == 'Macintosh' || strpos( $platform, 'Mac' ) === 0 ) {
			$mac_version = null;
			if ( preg_match( '/Mac\s+OS\s+X\s+(\d+)[._](\d+)(?:[._](\d+))?/i', $u_agent, $mac_match ) ) {
				$major = $mac_match[1];
				$minor = $mac_match[2];
				$patch = isset( $mac_match[3] ) ? $mac_match[3] : '0';
				// macOS 10.16+ 实际上是 macOS 11+
				if ( $major == '10' && intval( $minor ) >= 16 ) {
					$mac_version = ( intval( $minor ) - 6 ) . '.' . $patch;
				} else {
					$mac_version = $major . '.' . $minor . ( $patch != '0' ? '.' . $patch : '' );
				}
			} elseif ( preg_match( '/macOS\s+([0-9.]+)/i', $u_agent, $macos_match ) ) {
				$mac_version = $macos_match[1];
			}
			
			if ( $mac_version ) {
				// 如果版本是 10.15.7，则不显示任何名称和版本号
				if ( $mac_version == '10.15.7' ) {
					$platform_version = null;
				} else {
					// macOS 版本号到名称的映射
					$macos_version_names = array(
						'10.12' => 'Sierra',
						'10.13' => 'High Sierra',
						'10.14' => 'Mojave',
						'10.15' => 'Catalina',
						'11'    => 'Big Sur',
						'12'    => 'Monterey',
						'13'    => 'Ventura',
						'14'    => 'Sonoma',
						'15'    => 'Sequoia',
						'26'    => 'Tahoe'
					);
					
					// 保存原始版本号
					$original_version = $mac_version;
					
					// 先尝试精确匹配（如 10.12, 11, 12 等）
					$version_name = null;
					if ( isset( $macos_version_names[ $mac_version ] ) ) {
						$version_name = $macos_version_names[ $mac_version ];
					} else {
						// 尝试匹配主版本号（如 10.12.6 → 10.12 → Sierra）
						$version_parts = explode( '.', $mac_version );
						if ( count( $version_parts ) >= 2 ) {
							$major_minor = $version_parts[0] . '.' . $version_parts[1];
							if ( isset( $macos_version_names[ $major_minor ] ) ) {
								$version_name = $macos_version_names[ $major_minor ];
							} elseif ( isset( $macos_version_names[ $version_parts[0] ] ) ) {
								// 对于 macOS 11+，只匹配主版本号
								$version_name = $macos_version_names[ $version_parts[0] ];
							}
						} else {
							// 单版本号（如 11, 12）
							if ( isset( $macos_version_names[ $mac_version ] ) ) {
								$version_name = $macos_version_names[ $mac_version ];
							}
						}
					}
					
					// 格式化：macOS + 版本名称 + 版本号
					if ( $version_name !== null ) {
						$platform_version = 'macOS ' . $version_name . ' ' . $original_version;
					} else {
						$platform_version = 'macOS ' . $original_version;
					}
				}
			}
		}
		// iOS/iPadOS 版本检测 (OS 14_0, OS 15_0, etc.)
		elseif ( $platform == 'iPhone' || $platform == 'iPad' || $platform == 'iPod Touch' ) {
			if ( preg_match( '/OS\s+(\d+)[._](\d+)(?:[._](\d+))?/i', $u_agent, $ios_match ) ) {
				$major = $ios_match[1];
				$minor = $ios_match[2];
				$patch = isset( $ios_match[3] ) && $ios_match[3] != '0' ? $ios_match[3] : null;
				
				// 构建完整版本号
				$full_version = $major . '.' . $minor;
				if ( $patch !== null ) {
					$full_version .= '.' . $patch;
				}
				
				// 判断是 iOS 还是 iPadOS
				// iPadOS 13+ 才有名称，之前统一使用 iOS
				$os_name = 'iOS';
				if ( $platform === 'iPad' && intval( $major ) >= 13 ) {
					$os_name = 'iPadOS';
				}
				
				// 格式化：iOS/iPadOS + 版本号
				$platform_version = $os_name . ' ' . $full_version;
			}
		}
		// Android 版本检测
		elseif ( $platform == 'Android' ) {
			if ( preg_match( '/Android\s+([0-9.]+)/i', $u_agent, $android_match ) ) {
				$android_version = $android_match[1];
				// 如果检测到 Android 10; K 这样的 UA，则不显示版本号
				// 当 Model 为 K 时，版本始终为 10，不显示版本号
				if ( $android_version == '10' && preg_match( '/;\s*K(?:\s|;|\)|$)/i', $u_agent ) ) {
					$platform_version = null;
				} else {
					$platform_version = $android_version;
				}
			}
		}
		// Linux 发行版版本检测
		elseif ( $platform == 'Linux' || in_array( $platform, array( 'Ubuntu', 'Debian', 'Fedora', 'CentOS', 'Arch Linux', 'openSUSE', 'Manjaro', 'Linux Mint', 'Red Hat', 'Gentoo' ) ) ) {
			// Ubuntu 版本检测
			if ( $platform == 'Ubuntu' && preg_match( '/Ubuntu[\/\s]+([0-9.]+)/i', $u_agent, $ubuntu_match ) ) {
				$platform_version = $ubuntu_match[1];
			}
			// Debian 版本检测
			elseif ( $platform == 'Debian' && preg_match( '/Debian[\/\s]+([0-9.]+)/i', $u_agent, $debian_match ) ) {
				$platform_version = $debian_match[1];
			}
			// Fedora 版本检测
			elseif ( $platform == 'Fedora' && preg_match( '/Fedora[\/\s]+([0-9]+)/i', $u_agent, $fedora_match ) ) {
				$platform_version = $fedora_match[1];
			}
			// CentOS 版本检测
			elseif ( $platform == 'CentOS' && preg_match( '/CentOS[\/\s]+([0-9.]+)/i', $u_agent, $centos_match ) ) {
				$platform_version = $centos_match[1];
			}
		}
		// Chrome OS 版本检测
		elseif ( $platform == 'Chrome OS' ) {
			if ( preg_match( '/CrOS\s+[^\s]+\s+([0-9.]+)/i', $u_agent, $cros_match ) ) {
				$platform_version = $cros_match[1];
			}
		}
	}

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

		return $empty;
	}

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
		} elseif ( strpos( $platform, 'BB' ) === 0 ) {
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

		// 如果是 Safari，优先使用 Safari 的版本号，否则使用 Version 的版本号
		if ( $browser == 'Safari' && $safari_key !== null && isset( $result['version'][ $safari_key ] ) ) {
			$version = $result['version'][ $safari_key ];
		} else {
			$find( 'Version', $key );
			$version = $result['version'][ $key ];
		}
	} elseif ( $pKey = preg_grep( '/playstation \d/i', $result['browser'] ) ) {
		$pKey = reset( $pKey );

		$platform = 'PlayStation ' . preg_replace( '/\D/', '', $pKey );
		$browser  = 'NetFront';
	}

	return array( 'platform' => $platform ?: null, 'platform_version' => $platform_version ?: null, 'browser' => $browser ?: null, 'version' => $version ?: null );
}

//图标
$GLOBALS['UA_ICON']['Chrome']           = $GLOBALS['UA_ICON']['Chrome OS'] = '<svg height="2373" viewBox="-13.73499479 -4.38055278 539.01318831 520.27162413" width="2500" xmlns="http://www.w3.org/2000/svg"><path d="m256 140h228a256 256 0 0 1 -240 371.7" fill="#fc4"/><path d="m357 314-113 197.7a256 256 0 0 1 -204-393.7" fill="#0f9d58"/><path d="m256 140h228a256 256 1 0 0 -444-22l115 196" fill="#db4437"/><circle cx="256" cy="256" fill="#4285f4" r="105" stroke="#f1f1f1" stroke-width="24"/></svg>';
$GLOBALS['UA_ICON']['Firefox']          = '<svg viewBox="-3.3172958645805295 -4.032998864762789 88.94295956280828 87.39846056256113" xmlns="http://www.w3.org/2000/svg" width="2500" height="2478"><radialGradient id="a" cx="71.531" cy="16.385" gradientUnits="userSpaceOnUse" r="90.78"><stop offset="0" stop-color="#fff36e"/><stop offset=".5" stop-color="#fc4055"/><stop offset="1" stop-color="#e31587"/></radialGradient><radialGradient id="b" cx="6.629" cy="20.14" gradientUnits="userSpaceOnUse" r="53.726"><stop offset=".001" stop-color="#c60084"/><stop offset="1" stop-color="#fc4055" stop-opacity="0"/></radialGradient><radialGradient id="c" cx="79.291" cy="11.219" gradientUnits="userSpaceOnUse" r="106.599"><stop offset="0" stop-color="#ffde67" stop-opacity=".6"/><stop offset=".093" stop-color="#ffd966" stop-opacity=".581"/><stop offset=".203" stop-color="#ffca65" stop-opacity=".525"/><stop offset=".321" stop-color="#feb262" stop-opacity=".432"/><stop offset=".446" stop-color="#fe8f5e" stop-opacity=".302"/><stop offset=".573" stop-color="#fd6459" stop-opacity=".137"/><stop offset=".664" stop-color="#fc4055" stop-opacity="0"/></radialGradient><radialGradient id="d" cx="42.285" cy="44.404" gradientUnits="userSpaceOnUse" r="137.521"><stop offset=".153" stop-color="#810220"/><stop offset=".167" stop-color="#920b27" stop-opacity=".861"/><stop offset=".216" stop-color="#cb2740" stop-opacity=".398"/><stop offset=".253" stop-color="#ef394f" stop-opacity=".11"/><stop offset=".272" stop-color="#fc4055" stop-opacity="0"/></radialGradient><radialGradient id="e" cx="31.878" cy="42.675" gradientUnits="userSpaceOnUse" r="137.521"><stop offset=".113" stop-color="#810220"/><stop offset=".133" stop-color="#920b27" stop-opacity=".861"/><stop offset=".204" stop-color="#cb2740" stop-opacity=".398"/><stop offset=".257" stop-color="#ef394f" stop-opacity=".11"/><stop offset=".284" stop-color="#fc4055" stop-opacity="0"/></radialGradient><linearGradient id="f" gradientUnits="userSpaceOnUse" x1="45.831" x2="69.389" y1="7.787" y2="48.591"><stop offset="0" stop-color="#ffbd4f"/><stop offset=".508" stop-color="#ff9640" stop-opacity="0"/></linearGradient><radialGradient id="g" cx="-1255.933" cy="-77.395" gradientTransform="matrix(.959 0 0 .961 1273.896 86.468)" gradientUnits="userSpaceOnUse" r="88.863"><stop offset="0" stop-color="#ff9640"/><stop offset=".8" stop-color="#fc4055"/></radialGradient><radialGradient id="h" cx="-1255.933" cy="-77.395" gradientTransform="matrix(.959 0 0 .961 1273.896 86.468)" gradientUnits="userSpaceOnUse" r="88.863"><stop offset=".084" stop-color="#ffde67"/><stop offset=".147" stop-color="#ffdc66" stop-opacity=".968"/><stop offset=".246" stop-color="#ffd562" stop-opacity=".879"/><stop offset=".369" stop-color="#ffcb5d" stop-opacity=".734"/><stop offset=".511" stop-color="#ffbc55" stop-opacity=".533"/><stop offset=".667" stop-color="#ffaa4b" stop-opacity=".28"/><stop offset=".822" stop-color="#ff9640" stop-opacity="0"/></radialGradient><radialGradient id="i" cx="49.941" cy="38.654" gradientTransform="matrix(.247 .971 -1.011 .259 76.681 -19.851)" gradientUnits="userSpaceOnUse" r="41.79"><stop offset=".363" stop-color="#fc4055"/><stop offset=".443" stop-color="#fd604d" stop-opacity=".633"/><stop offset=".545" stop-color="#fe8644" stop-opacity=".181"/><stop offset=".59" stop-color="#ff9640" stop-opacity="0"/></radialGradient><radialGradient id="j" cx="42.737" cy="42.098" gradientUnits="userSpaceOnUse" r="41.79"><stop offset=".216" stop-color="#fc4055" stop-opacity=".8"/><stop offset=".267" stop-color="#fd5251" stop-opacity=".633"/><stop offset=".41" stop-color="#fe8345" stop-opacity=".181"/><stop offset=".474" stop-color="#ff9640" stop-opacity="0"/></radialGradient><radialGradient id="k" cx="-1238.198" cy="-87.433" gradientTransform="matrix(.959 0 0 .961 1273.896 86.468)" gradientUnits="userSpaceOnUse" r="150.195"><stop offset=".054" stop-color="#fff36e"/><stop offset=".457" stop-color="#ff9640"/><stop offset=".639" stop-color="#ff9640"/></radialGradient><linearGradient id="l" gradientUnits="userSpaceOnUse" x1="59.052" x2="18.155" y1="7.083" y2="77.92"><stop offset="0" stop-color="#fff36e" stop-opacity=".8"/><stop offset=".094" stop-color="#fff36e" stop-opacity=".699"/><stop offset=".752" stop-color="#fff36e" stop-opacity="0"/></linearGradient><linearGradient id="m" gradientUnits="userSpaceOnUse" x1="40.585" x2="62.3" y1="-.67" y2="62.203"><stop offset="0" stop-color="#b833e1"/><stop offset=".371" stop-color="#9059ff"/><stop offset=".614" stop-color="#5b6df8"/><stop offset="1" stop-color="#0090ed"/></linearGradient><linearGradient id="n" gradientUnits="userSpaceOnUse" x1="27.71" x2="68.071" y1=".324" y2="40.685"><stop offset=".805" stop-color="#722291" stop-opacity="0"/><stop offset="1" stop-color="#592acb" stop-opacity=".5"/></linearGradient><path d="M71.944 15.7A39.47 39.47 0 0 0 41.588.009C32.3-.177 25.884 2.614 22.254 4.858 27.111 2.041 34.14.443 40.294.522c15.83.2 32.832 10.981 35.357 30.413 2.9 22.306-12.637 40.923-34.493 40.98-24.045.061-38.67-21.229-34.847-40.352a19.735 19.735 0 0 1 .413-2.787 37.815 37.815 0 0 1 4.193-14.018c-2.769 1.433-6.295 5.965-8.035 10.163A41.355 41.355 0 0 0 .284 45.1c.06.518.114 1.035.182 1.549A40.062 40.062 0 1 0 71.944 15.7z" fill="url(#a)"/><path d="M71.944 15.7A39.47 39.47 0 0 0 41.588.009C32.3-.177 25.884 2.614 22.254 4.858 27.111 2.041 34.14.443 40.294.522c15.83.2 32.832 10.981 35.357 30.413 2.9 22.306-12.637 40.923-34.493 40.98-24.045.061-38.67-21.229-34.847-40.352a19.735 19.735 0 0 1 .413-2.787 37.815 37.815 0 0 1 4.193-14.018c-2.769 1.433-6.295 5.965-8.035 10.163A41.355 41.355 0 0 0 .284 45.1c.06.518.114 1.035.182 1.549A40.062 40.062 0 1 0 71.944 15.7z" fill="url(#b)" opacity=".67"/><path d="M71.944 15.7A39.47 39.47 0 0 0 41.588.009C32.3-.177 25.884 2.614 22.254 4.858 27.111 2.041 34.14.443 40.294.522c15.83.2 32.832 10.981 35.357 30.413 2.9 22.306-12.637 40.923-34.493 40.98-24.045.061-38.67-21.229-34.847-40.352a19.735 19.735 0 0 1 .413-2.787 37.815 37.815 0 0 1 4.193-14.018c-2.769 1.433-6.295 5.965-8.035 10.163A41.355 41.355 0 0 0 .284 45.1c.06.518.114 1.035.182 1.549A40.062 40.062 0 1 0 71.944 15.7z" fill="url(#c)"/><path d="M71.944 15.7A39.47 39.47 0 0 0 41.588.009C32.3-.177 25.884 2.614 22.254 4.858 27.111 2.041 34.14.443 40.294.522c15.83.2 32.832 10.981 35.357 30.413 2.9 22.306-12.637 40.923-34.493 40.98-24.045.061-38.67-21.229-34.847-40.352a19.735 19.735 0 0 1 .413-2.787 37.815 37.815 0 0 1 4.193-14.018c-2.769 1.433-6.295 5.965-8.035 10.163A41.355 41.355 0 0 0 .284 45.1c.06.518.114 1.035.182 1.549A40.062 40.062 0 1 0 71.944 15.7z" fill="url(#d)"/><path d="M71.944 15.7A39.47 39.47 0 0 0 41.588.009C32.3-.177 25.884 2.614 22.254 4.858 27.111 2.041 34.14.443 40.294.522c15.83.2 32.832 10.981 35.357 30.413 2.9 22.306-12.637 40.923-34.493 40.98-24.045.061-38.67-21.229-34.847-40.352a19.735 19.735 0 0 1 .413-2.787 37.815 37.815 0 0 1 4.193-14.018c-2.769 1.433-6.295 5.965-8.035 10.163A41.355 41.355 0 0 0 .284 45.1c.06.518.114 1.035.182 1.549A40.062 40.062 0 1 0 71.944 15.7z" fill="url(#e)"/><path d="M75.651 30.935a41.01 41.01 0 0 1 .3 7.247q1.99-.3 3.987-.53A40.01 40.01 0 0 0 71.944 15.7 39.47 39.47 0 0 0 41.588.009C32.3-.177 25.884 2.614 22.254 4.858 27.111 2.041 34.14.443 40.294.522 56.124.724 73.126 11.5 75.651 30.935z" fill="url(#f)"/><path d="M76.625 29.826C74.374 9.518 56.263.39 40.294.522c-6.155.05-13.183 1.519-18.04 4.336a19.7 19.7 0 0 0-3.56 2.7c.129-.107.514-.424 1.152-.862l.063-.043.056-.038a26.655 26.655 0 0 1 7.692-3.572A43.5 43.5 0 0 1 40.84 1.5a33.254 33.254 0 0 1 31.25 31.993C72.457 46.7 61.648 57.23 49.188 57.84c-9.062.444-17.6-3.941-21.77-12.713a21.68 21.68 0 0 1-1.964-6.333c-1.976-13.35 6.989-24.735 15.21-27.554-4.435-3.874-15.548-3.611-23.819 2.474-5.956 4.382-9.82 11.049-11.1 19a32.945 32.945 0 0 0 2.34 18 35.3 35.3 0 0 0 30.089 21.443q1.489.114 2.984.113c26.462 0 37.942-20.087 35.467-42.444z" fill="url(#g)"/><path d="M76.625 29.826C74.374 9.518 56.263.39 40.294.522c-6.155.05-13.183 1.519-18.04 4.336a19.7 19.7 0 0 0-3.56 2.7c.129-.107.514-.424 1.152-.862l.063-.043.056-.038a26.655 26.655 0 0 1 7.692-3.572A43.5 43.5 0 0 1 40.84 1.5a33.254 33.254 0 0 1 31.25 31.993C72.457 46.7 61.648 57.23 49.188 57.84c-9.062.444-17.6-3.941-21.77-12.713a21.68 21.68 0 0 1-1.964-6.333c-1.976-13.35 6.989-24.735 15.21-27.554-4.435-3.874-15.548-3.611-23.819 2.474-5.956 4.382-9.82 11.049-11.1 19a32.945 32.945 0 0 0 2.34 18 35.3 35.3 0 0 0 30.089 21.443q1.489.114 2.984.113c26.462 0 37.942-20.087 35.467-42.444z" fill="url(#h)"/><path d="M76.625 29.826C74.374 9.518 56.263.39 40.294.522c-6.155.05-13.183 1.519-18.04 4.336a19.7 19.7 0 0 0-3.56 2.7c.129-.107.514-.424 1.152-.862l.063-.043.056-.038a26.655 26.655 0 0 1 7.692-3.572A43.5 43.5 0 0 1 40.84 1.5a33.254 33.254 0 0 1 31.25 31.993C72.457 46.7 61.648 57.23 49.188 57.84c-9.062.444-17.6-3.941-21.77-12.713a21.68 21.68 0 0 1-1.964-6.333c-1.976-13.35 6.989-24.735 15.21-27.554-4.435-3.874-15.548-3.611-23.819 2.474-5.956 4.382-9.82 11.049-11.1 19a32.945 32.945 0 0 0 2.34 18 35.3 35.3 0 0 0 30.089 21.443q1.489.114 2.984.113c26.462 0 37.942-20.087 35.467-42.444z" fill="url(#i)" opacity=".53"/><path d="M76.625 29.826C74.374 9.518 56.263.39 40.294.522c-6.155.05-13.183 1.519-18.04 4.336a19.7 19.7 0 0 0-3.56 2.7c.129-.107.514-.424 1.152-.862l.063-.043.056-.038a26.655 26.655 0 0 1 7.692-3.572A43.5 43.5 0 0 1 40.84 1.5a33.254 33.254 0 0 1 31.25 31.993C72.457 46.7 61.648 57.23 49.188 57.84c-9.062.444-17.6-3.941-21.77-12.713a21.68 21.68 0 0 1-1.964-6.333c-1.976-13.35 6.989-24.735 15.21-27.554-4.435-3.874-15.548-3.611-23.819 2.474-5.956 4.382-9.82 11.049-11.1 19a32.945 32.945 0 0 0 2.34 18 35.3 35.3 0 0 0 30.089 21.443q1.489.114 2.984.113c26.462 0 37.942-20.087 35.467-42.444z" fill="url(#j)" opacity=".53"/><path d="M49.188 57.84c17.1-1.04 24.42-15.2 24.879-25.245C74.783 16.9 65.472-.02 40.84 1.5a43.5 43.5 0 0 0-13.183 1.546 28.855 28.855 0 0 0-7.692 3.572l-.056.038-.063.043q-.574.4-1.123.842A33.482 33.482 0 0 1 39.7 3.605c14.142 1.856 27.072 12.857 27.072 27.373 0 11.169-8.631 19.7-18.738 19.087-15.015-.9-18.8-16.3-10.989-22.954-2.106-.453-6.064.435-8.82 4.555-2.473 3.7-2.333 9.41-.807 13.461a22.118 22.118 0 0 0 21.77 12.713z" fill="url(#k)"/><path d="M71.944 15.7a39.958 39.958 0 0 0-3.482-3.982 31.342 31.342 0 0 0-3.177-2.926 24.393 24.393 0 0 1 1.849 1.79 22.466 22.466 0 0 1 4.882 8.144c2.089 6.329 1.953 14.25-2.036 20.471a23.539 23.539 0 0 1-20.855 10.895c-.361 0-.725 0-1.091-.027-15.015-.9-18.8-16.3-10.988-22.954-2.107-.453-6.065.435-8.821 4.555-2.473 3.7-2.333 9.41-.807 13.461a21.679 21.679 0 0 1-1.963-6.333c-1.977-13.35 6.988-24.735 15.209-27.554-4.435-3.874-15.548-3.611-23.819 2.474a27.845 27.845 0 0 0-10.087 14.6 38.5 38.5 0 0 1 4.159-13.553c-2.769 1.433-6.295 5.965-8.035 10.163A41.355 41.355 0 0 0 .284 45.1c.06.518.114 1.035.182 1.549A40.062 40.062 0 1 0 71.944 15.7z" fill="url(#l)"/><path d="M72.016 18.726a22.458 22.458 0 0 0-4.882-8.144 30.224 30.224 0 0 0-9.094-6.493A40.518 40.518 0 0 0 49.1.92a39.834 39.834 0 0 0-16.565-.1c-5.683 1.2-10.68 3.659-13.841 6.733a32.1 32.1 0 0 1 8.031-3.2 33.565 33.565 0 0 1 31.173 8.1 27.01 27.01 0 0 1 4.329 5.3c4.895 7.959 4.432 17.965.615 23.866-2.835 4.384-8.907 8.5-14.572 8.452A23.629 23.629 0 0 0 69.98 39.2c3.989-6.224 4.125-14.145 2.036-20.474z" fill="url(#m)"/><path d="M72.016 18.726a22.458 22.458 0 0 0-4.882-8.144 30.224 30.224 0 0 0-9.094-6.493A40.518 40.518 0 0 0 49.1.92a39.834 39.834 0 0 0-16.565-.1c-5.683 1.2-10.68 3.659-13.841 6.733a32.1 32.1 0 0 1 8.031-3.2 33.565 33.565 0 0 1 31.173 8.1 27.01 27.01 0 0 1 4.329 5.3c4.895 7.959 4.432 17.965.615 23.866-2.835 4.384-8.907 8.5-14.572 8.452A23.629 23.629 0 0 0 69.98 39.2c3.989-6.224 4.125-14.145 2.036-20.474z" fill="url(#n)"/></svg>';
$GLOBALS['UA_ICON']['Safari']           = '<svg id="a4e92f88-6871-4900-94c1-275c64397f6e" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 177.68 178.48" style="transform: scale(1.05) translateY(-1px);"><defs><filter id="a05a6cdb-870f-41fc-9968-9bfc71b00e41" x="-4.98" y="-0.16" width="1.1" height="1.1" name="filter2248"><feGaussianBlur result="feGaussianBlur2250" stdDeviation="3.56"/></filter><linearGradient id="b391e05f-04e8-477d-831d-5f38dc037c5d" x1="416.01" y1="-51.17" x2="416.01" y2="127.04" gradientTransform="matrix(1, 0, 0, -1, -320.78, 126.76)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#bdbdbd"/><stop offset="1" stop-color="#fff"/></linearGradient><radialGradient id="a8d29e90-c8e1-4024-809c-5fad76cc7f62" cx="348.08" cy="84.69" r="82.13" gradientTransform="matrix(1.08, 0, 0, -1.08, -282.2, 168.59)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#06c2e7"/><stop offset="0.25" stop-color="#0db8ec"/><stop offset="0.5" stop-color="#12aef1"/><stop offset="0.75" stop-color="#1f86f9"/><stop offset="1" stop-color="#107ddd"/></radialGradient><filter id="a02ea592-7884-4e73-abdb-077b4ef35cba" x="-4.96" y="-0.14" width="1.04" height="1.04" name="filter2222"><feGaussianBlur result="feGaussianBlur2224" stdDeviation="0.96"/></filter></defs><g id="f3b58452-405d-4528-a24d-ad5f359b3429" data-name="layer1"><g id="a5f87dbf-8edf-4e55-9cf3-0e39f3989dbe" data-name="g2858"><g id="a4ea5995-d48b-4a95-915b-bc306a00709f" data-name="path2226" style="opacity:0.5299999713897705;isolation:isolate;filter:url(#a05a6cdb-870f-41fc-9968-9bfc71b00e41)"><ellipse cx="88.84" cy="96.11" rx="85.54" ry="82.37"/></g><path id="abfd8a47-9913-472a-b88b-500895a25895" data-name="path826" d="M182.57,89a88.79,88.79,0,0,1-88.79,88.79h0A88.8,88.8,0,1,1,93.78.16h0A88.8,88.8,0,0,1,182.57,89Z" transform="translate(-4.94 -0.11)" style="stroke:#cdcdcd;stroke-linecap:round;stroke-linejoin:round;stroke-width:0.09301234781742096px;fill:url(#b391e05f-04e8-477d-831d-5f38dc037c5d)"/><path id="a2cf3d28-e9a8-4054-9ece-194c3b43b069" data-name="circle828" d="M175.62,89a81.84,81.84,0,0,1-81.84,81.83h0A81.84,81.84,0,0,1,11.94,89h0A81.84,81.84,0,0,1,93.78,7.12h0A81.84,81.84,0,0,1,175.62,89Z" transform="translate(-4.94 -0.11)" style="fill:url(#a8d29e90-c8e1-4024-809c-5fad76cc7f62)"/><path id="be410b77-c0c6-410a-ae96-6efc91e42d07" data-name="rect830" d="M93.78,11.39a1.19,1.19,0,0,0-1.19,1.19V26.34a1.19,1.19,0,1,0,2.38,0V12.58A1.19,1.19,0,0,0,93.78,11.39ZM86,11.88a1,1,0,0,0-.24,0,1.18,1.18,0,0,0-1.06,1.31l.6,5.76a1.19,1.19,0,1,0,2.37-.25L87,12.94A1.18,1.18,0,0,0,86,11.88Zm15.67,0A1.2,1.2,0,0,0,100.57,13L100,18.71a1.2,1.2,0,0,0,2.38.25l.6-5.76a1.18,1.18,0,0,0-1.06-1.31h-.24ZM78,13l-.24,0a1.2,1.2,0,0,0-.92,1.41L79.64,28a1.2,1.2,0,1,0,2.34-.5L79.13,14A1.2,1.2,0,0,0,78,13Zm31.71,0a1.2,1.2,0,0,0-1.18.95l-2.86,13.46A1.2,1.2,0,1,0,108,28l2.86-13.47a1.2,1.2,0,0,0-.92-1.41l-.24,0ZM70.3,15.2a1.28,1.28,0,0,0-.47.05,1.2,1.2,0,0,0-.77,1.51l1.79,5.5a1.2,1.2,0,0,0,1.51.77,1.18,1.18,0,0,0,.76-1.5L71.33,16a1.18,1.18,0,0,0-1-.82Zm47,0a1.2,1.2,0,0,0-1,.82l-1.79,5.51a1.19,1.19,0,0,0,.77,1.5,1.18,1.18,0,0,0,1.5-.76l1.79-5.51a1.18,1.18,0,0,0-.76-1.5,1.29,1.29,0,0,0-.47-.06ZM62.73,18a1.27,1.27,0,0,0-.46.1,1.19,1.19,0,0,0-.6,1.58l5.59,12.57a1.18,1.18,0,0,0,1.57.61,1.2,1.2,0,0,0,.61-1.58L63.85,18.68A1.2,1.2,0,0,0,62.73,18Zm62.19,0a1.19,1.19,0,0,0-1.11.71L118.2,31.29a1.19,1.19,0,0,0,2.18,1L126,19.69a1.19,1.19,0,0,0-.6-1.57A1.11,1.11,0,0,0,124.92,18ZM55.71,21.69a1.19,1.19,0,0,0-1.12,1.78l2.9,5a1.2,1.2,0,0,0,2.07-1.2l-2.9-5a1.17,1.17,0,0,0-.95-.59Zm76.14,0a1.14,1.14,0,0,0-.95.59l-2.9,5a1.2,1.2,0,0,0,2.07,1.2l2.89-5a1.18,1.18,0,0,0-1.11-1.78Zm-83,4.25a1.17,1.17,0,0,0-.66.23A1.19,1.19,0,0,0,48,27.83L56.05,39A1.19,1.19,0,1,0,58,37.57L49.9,26.43a1.23,1.23,0,0,0-1-.49Zm89.86.06a1.2,1.2,0,0,0-1,.49l-8.1,11.13a1.19,1.19,0,1,0,1.93,1.4l8.09-11.12a1.19,1.19,0,0,0-.26-1.67,1.17,1.17,0,0,0-.66-.23Zm-96,5.05a1.24,1.24,0,0,0-.86.31A1.19,1.19,0,0,0,41.84,33l3.88,4.31a1.19,1.19,0,0,0,1.77-1.6l-3.87-4.3a1.19,1.19,0,0,0-.83-.4Zm102,0a1.19,1.19,0,0,0-.82.4l-3.88,4.3a1.19,1.19,0,0,0,1.78,1.59l3.87-4.3a1.19,1.19,0,0,0-.09-1.68,1.16,1.16,0,0,0-.86-.31ZM37,36.66a1.19,1.19,0,0,0-.82.4,1.17,1.17,0,0,0,.09,1.68L46.44,48a1.2,1.2,0,0,0,1.69-.09A1.19,1.19,0,0,0,48,46.18L37.81,37a1.17,1.17,0,0,0-.86-.3Zm113.69.05a1.18,1.18,0,0,0-.86.3l-10.23,9.2A1.19,1.19,0,0,0,141.14,48l10.24-9.2a1.2,1.2,0,0,0,.09-1.69A1.14,1.14,0,0,0,150.64,36.71ZM32.09,42.91a1.21,1.21,0,0,0-1,.5,1.19,1.19,0,0,0,.27,1.66L36,48.47a1.2,1.2,0,0,0,1.41-1.93l-4.69-3.4a1.25,1.25,0,0,0-.66-.23ZM155.5,43a1.16,1.16,0,0,0-.66.22l-4.69,3.4a1.2,1.2,0,0,0,1.4,1.94l4.69-3.4a1.2,1.2,0,0,0,.26-1.67,1.15,1.15,0,0,0-1-.49ZM27.55,49.58a1.17,1.17,0,0,0-.95.59A1.19,1.19,0,0,0,27,51.8L39,58.68a1.19,1.19,0,1,0,1.19-2.06L28.23,49.74a1.11,1.11,0,0,0-.68-.16Zm132.46,0a1.11,1.11,0,0,0-.68.16l-11.92,6.88a1.19,1.19,0,1,0,1.19,2.06l11.92-6.88a1.19,1.19,0,0,0,.44-1.63,1.17,1.17,0,0,0-1-.59ZM24.12,56.68a1.21,1.21,0,0,0-1.12.71A1.19,1.19,0,0,0,23.61,59l5.29,2.36a1.19,1.19,0,0,0,1-2.18l-5.29-2.35A1.11,1.11,0,0,0,24.12,56.68Zm139.34,0a1.27,1.27,0,0,0-.46.1l-5.29,2.36a1.19,1.19,0,0,0,1,2.18L164,59a1.19,1.19,0,0,0-.51-2.28ZM21.06,64.11a1.16,1.16,0,0,0-1,.82,1.18,1.18,0,0,0,.76,1.5l13.09,4.26a1.19,1.19,0,1,0,.73-2.26L21.53,64.16a1.1,1.1,0,0,0-.47,0Zm145.46,0a1.07,1.07,0,0,0-.47,0L153,68.47a1.19,1.19,0,0,0-.77,1.5,1.2,1.2,0,0,0,1.51.77l13.08-4.26a1.19,1.19,0,0,0-.26-2.32ZM19.15,71.9a1.19,1.19,0,0,0-.25,2.36l5.66,1.2A1.18,1.18,0,0,0,26,74.55a1.19,1.19,0,0,0-.92-1.42l-5.66-1.2a1,1,0,0,0-.24,0Zm149.26,0-.24,0-5.67,1.2a1.19,1.19,0,0,0,.5,2.33l5.66-1.2a1.19,1.19,0,0,0-.25-2.36ZM17.71,79.74a1.19,1.19,0,0,0,0,2.37l13.69,1.45a1.19,1.19,0,0,0,.25-2.37L18,79.74Zm152.15.1a1,1,0,0,0-.24,0l-13.69,1.43a1.2,1.2,0,0,0,.25,2.38l13.69-1.43a1.2,1.2,0,0,0,0-2.38ZM17.48,87.76a1.2,1.2,0,1,0,0,2.39h5.79a1.2,1.2,0,0,0,0-2.39Zm146.81,0a1.2,1.2,0,0,0,0,2.39h5.79a1.2,1.2,0,0,0,0-2.39ZM31.62,94.27h-.24L17.69,95.7a1.19,1.19,0,0,0,.25,2.37l13.69-1.43a1.19,1.19,0,0,0,0-2.37Zm124.31.08a1.2,1.2,0,0,0-1.07,1.07,1.19,1.19,0,0,0,1.06,1.31l13.69,1.45a1.2,1.2,0,0,0,.25-2.38l-13.69-1.45ZM24.8,102.41l-.24,0-5.67,1.2a1.2,1.2,0,0,0,.5,2.34l5.66-1.21a1.18,1.18,0,0,0,.92-1.41,1.19,1.19,0,0,0-1.17-1Zm138,0a1.19,1.19,0,0,0-1.18.94,1.21,1.21,0,0,0,.92,1.42l5.67,1.2a1.19,1.19,0,1,0,.49-2.33L163,102.45a1,1,0,0,0-.24,0Zm-128.43,4.7a1.1,1.1,0,0,0-.47.05l-13.09,4.25a1.2,1.2,0,0,0-.76,1.51,1.18,1.18,0,0,0,1.5.76l13.09-4.25a1.19,1.19,0,0,0,.77-1.5,1.17,1.17,0,0,0-1-.82Zm118.88,0a1.19,1.19,0,0,0-1,.82,1.18,1.18,0,0,0,.76,1.5L166,113.76a1.19,1.19,0,0,0,1.5-.77,1.18,1.18,0,0,0-.76-1.5l-13.09-4.27a1.08,1.08,0,0,0-.47,0ZM29.34,116.44a1.24,1.24,0,0,0-.46.11l-5.29,2.35a1.19,1.19,0,1,0,1,2.18l5.29-2.35a1.2,1.2,0,0,0-.51-2.29Zm128.86,0a1.17,1.17,0,0,0-1.11.71,1.19,1.19,0,0,0,.6,1.57l5.29,2.36a1.19,1.19,0,0,0,1-2.18l-5.29-2.36a1.27,1.27,0,0,0-.46-.1ZM39.64,119.07a1.26,1.26,0,0,0-.68.16L27,126.11a1.19,1.19,0,0,0,1.19,2.07l11.92-6.88a1.19,1.19,0,0,0,.44-1.63,1.21,1.21,0,0,0-1-.6Zm108.28,0a1.21,1.21,0,0,0-.95.6,1.19,1.19,0,0,0,.44,1.63l11.92,6.88a1.19,1.19,0,0,0,1.19-2.07l-11.92-6.88a1.26,1.26,0,0,0-.68-.16ZM36.66,129.17a1.25,1.25,0,0,0-.66.23l-4.68,3.4a1.19,1.19,0,1,0,1.4,1.93l4.69-3.4a1.2,1.2,0,0,0-.75-2.16Zm114.2,0a1.2,1.2,0,0,0-.74,2.16l4.69,3.41a1.2,1.2,0,0,0,1.4-1.94l-4.68-3.4a1.32,1.32,0,0,0-.67-.23Zm-103.58.42a1.19,1.19,0,0,0-.87.3l-10.23,9.2a1.2,1.2,0,0,0,1.6,1.78L48,131.71A1.2,1.2,0,0,0,48.1,130,1.14,1.14,0,0,0,47.28,129.63Zm93,0a1.22,1.22,0,0,0-.82.39,1.2,1.2,0,0,0,.09,1.69L149.75,141a1.19,1.19,0,0,0,1.68-.09,1.2,1.2,0,0,0-.09-1.69L141.11,130a1.15,1.15,0,0,0-.86-.3ZM57,138.4a1.18,1.18,0,0,0-1,.49L47.88,150a1.19,1.19,0,1,0,1.93,1.4l8.1-11.13a1.19,1.19,0,0,0-.26-1.66A1.17,1.17,0,0,0,57,138.4Zm73.51,0a1.23,1.23,0,0,0-.66.23,1.18,1.18,0,0,0-.26,1.66l8.08,11.14a1.19,1.19,0,1,0,1.93-1.4l-8.08-11.14a1.2,1.2,0,0,0-1-.49Zm-84,1.72a1.18,1.18,0,0,0-.82.39l-3.87,4.3a1.19,1.19,0,1,0,1.77,1.6l3.87-4.3a1.19,1.19,0,0,0-.95-2Zm94.49,0a1.24,1.24,0,0,0-.87.31,1.18,1.18,0,0,0-.08,1.68l3.87,4.31a1.19,1.19,0,1,0,1.77-1.6l-3.87-4.3a1.19,1.19,0,0,0-.82-.4ZM68.29,145a1.17,1.17,0,0,0-1.11.7l-5.61,12.57a1.19,1.19,0,1,0,2.18,1l5.61-12.56a1.19,1.19,0,0,0-.6-1.58,1.14,1.14,0,0,0-.47-.1Zm50.9,0a1.23,1.23,0,0,0-.46.1,1.19,1.19,0,0,0-.61,1.58l5.59,12.58a1.19,1.19,0,0,0,2.18-1l-5.59-12.58a1.2,1.2,0,0,0-1.11-.71Zm-60.75,3.85a1.21,1.21,0,0,0-.95.6l-2.9,5a1.19,1.19,0,0,0,2.07,1.19l2.9-5a1.19,1.19,0,0,0-.44-1.63,1.29,1.29,0,0,0-.68-.16Zm70.68,0a1.18,1.18,0,0,0-.68.16,1.19,1.19,0,0,0-.44,1.63l2.9,5a1.19,1.19,0,1,0,2.06-1.19l-2.89-5a1.21,1.21,0,0,0-.95-.6ZM80.77,149a1.19,1.19,0,0,0-1.17,1l-2.86,13.46a1.19,1.19,0,0,0,2.33.5l2.86-13.47A1.19,1.19,0,0,0,81,149l-.25,0Zm26,0a1,1,0,0,0-.24,0,1.18,1.18,0,0,0-.92,1.41l2.85,13.46a1.19,1.19,0,1,0,2.33-.49L107.91,150a1.19,1.19,0,0,0-1.17-.95Zm-13,1.36a1.19,1.19,0,0,0-1.19,1.19v13.76a1.19,1.19,0,1,0,2.38,0V151.57A1.19,1.19,0,0,0,93.78,150.38Zm-21.9,4.45a1.17,1.17,0,0,0-1,.82l-1.79,5.5a1.2,1.2,0,0,0,.77,1.51,1.19,1.19,0,0,0,1.5-.77l1.79-5.51a1.18,1.18,0,0,0-.76-1.5,1.1,1.1,0,0,0-.47,0Zm43.79,0a1.1,1.1,0,0,0-.47,0,1.2,1.2,0,0,0-.77,1.51l1.79,5.5a1.2,1.2,0,0,0,1.51.77,1.18,1.18,0,0,0,.76-1.5l-1.79-5.51a1.19,1.19,0,0,0-1-.82Zm-29.38,3.06A1.2,1.2,0,0,0,85.22,159l-.61,5.76A1.2,1.2,0,0,0,87,165l.6-5.75a1.18,1.18,0,0,0-1.06-1.31Zm14.93,0a1,1,0,0,0-.24,0,1.2,1.2,0,0,0-1.07,1.31l.61,5.76a1.18,1.18,0,0,0,1.31,1.06,1.19,1.19,0,0,0,1.06-1.31l-.6-5.76a1.19,1.19,0,0,0-1.07-1.06Z" transform="translate(-4.94 -0.11)" style="fill:#f4f2f3"/><g id="ad563743-e86a-43b9-9ea7-5679774c8f16" data-name="path2150" style="opacity:0.409000039100647;isolation:isolate;filter:url(#a02ea592-7884-4e73-abdb-077b4ef35cba)"><polygon points="144.77 41.12 79.49 79.05 38.21 144.02 98.59 99.3 144.77 41.12"/></g><g id="b77d43d9-3937-423a-a4c2-972024c9b1af" data-name="g2847"><path id="ac11e92d-0054-4b30-8b68-7fa54c1665bc" data-name="path2096" d="M103.13,98.75,84.42,79.16,150.8,34.51Z" transform="translate(-4.94 -0.11)" style="fill:#ff5150"/><path id="e822e44b-264f-4bbd-b17e-8675a476ae13" data-name="path2099" d="M103.13,98.75,84.42,79.16,36.76,143.4Z" transform="translate(-4.94 -0.11)" style="fill:#f1f1f1"/><path id="aa0c4885-4aa6-4620-bba4-b8e96c704d07" data-name="path2112" d="M36.76,143.4l66.37-44.65L150.8,34.51Z" transform="translate(-4.94 -0.11)" style="opacity:0.24300000071525574;isolation:isolate"/></g></g></g></svg>';
$GLOBALS['UA_ICON']['Edge']             = '<svg id="aa18aec7-1a6d-485f-bae9-692f083c3c03" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 256.02 256.05"><defs><linearGradient id="bdabf876-4b2a-401b-ae2e-987cb08dd27d" x1="63.33" y1="995.95" x2="241.62" y2="995.95" gradientTransform="translate(-4.63 -818.92)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#0c59a4"/><stop offset="1" stop-color="#114a8b"/></linearGradient><radialGradient id="fe174677-dad1-4826-a441-99ddd979d00c" cx="161.83" cy="1032.72" r="95.38" gradientTransform="translate(-4.63 -802.63) scale(1 0.95)" gradientUnits="userSpaceOnUse"><stop offset="0.72" stop-opacity="0"/><stop offset="0.95" stop-opacity="0.53"/><stop offset="1"/></radialGradient><linearGradient id="b101dfb4-29c0-4fe4-8cae-e04038fc9a8c" x1="157.41" y1="918.66" x2="46.02" y2="1039.99" gradientTransform="translate(-4.63 -818.92)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#1b9de2"/><stop offset="0.16" stop-color="#1595df"/><stop offset="0.67" stop-color="#0680d7"/><stop offset="1" stop-color="#0078d4"/></linearGradient><radialGradient id="fe97b05f-3188-4793-8e1b-46ec3f337d64" cx="-1454.1" cy="1697.79" r="143.24" gradientTransform="matrix(0.15, -0.99, 0.8, 0.12, -1069.54, -1444.29)" gradientUnits="userSpaceOnUse"><stop offset="0.76" stop-opacity="0"/><stop offset="0.95" stop-opacity="0.5"/><stop offset="1"/></radialGradient><radialGradient id="b5860bc6-66c5-4872-a544-cd5083b094a5" cx="-338.41" cy="-298.47" r="202.43" gradientTransform="matrix(-0.04, 1, -2.13, -0.08, -623.44, 361.91)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#35c1f1"/><stop offset="0.11" stop-color="#34c1ed"/><stop offset="0.23" stop-color="#2fc2df"/><stop offset="0.31" stop-color="#2bc3d2"/><stop offset="0.67" stop-color="#36c752"/></radialGradient><radialGradient id="a5f0bf22-9f30-4c72-a92f-cd6ab2e6001d" cx="174.77" cy="-738.47" r="97.34" gradientTransform="matrix(0.28, 0.96, -0.78, 0.23, -384.89, 79.47)" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#66eb6e"/><stop offset="1" stop-color="#66eb6e" stop-opacity="0"/></radialGradient></defs><path d="M231.05,190.54a92,92,0,0,1-10.54,4.71,101.85,101.85,0,0,1-35.9,6.46c-47.32,0-88.54-32.55-88.54-74.32a31.47,31.47,0,0,1,16.43-27.31c-42.8,1.8-53.8,46.4-53.8,72.53,0,73.88,68.09,81.37,82.76,81.37,7.91,0,19.84-2.3,27-4.56l1.31-.44a128.39,128.39,0,0,0,66.6-52.8,4,4,0,0,0-5.32-5.64Z" transform="translate(0.02 0)" style="fill:url(#bdabf876-4b2a-401b-ae2e-987cb08dd27d)"/><path d="M231.05,190.54a92,92,0,0,1-10.54,4.71,101.85,101.85,0,0,1-35.9,6.46c-47.32,0-88.54-32.55-88.54-74.32a31.47,31.47,0,0,1,16.43-27.31c-42.8,1.8-53.8,46.4-53.8,72.53,0,73.88,68.09,81.37,82.76,81.37,7.91,0,19.84-2.3,27-4.56l1.31-.44a128.39,128.39,0,0,0,66.6-52.8,4,4,0,0,0-5.32-5.64Z" transform="translate(0.02 0)" style="opacity:0.3499999940395355;isolation:isolate;fill:url(#fe174677-dad1-4826-a441-99ddd979d00c)"/><path d="M105.71,241.42A79.24,79.24,0,0,1,83,220.08a80.72,80.72,0,0,1,17.61-112.79,81.62,81.62,0,0,1,11.92-7.21c3.12-1.47,8.45-4.13,15.54-4a32.32,32.32,0,0,1,25.69,13,31.89,31.89,0,0,1,6.36,18.66c0-.21,24.46-79.6-80-79.6-43.9,0-80,41.66-80,78.21a130.17,130.17,0,0,0,12.11,56,128,128,0,0,0,156.38,67.11,75.53,75.53,0,0,1-62.78-8Z" transform="translate(0.02 0)" style="fill:url(#b101dfb4-29c0-4fe4-8cae-e04038fc9a8c)"/><path d="M105.71,241.42A79.24,79.24,0,0,1,83,220.08a80.72,80.72,0,0,1,17.61-112.79,81.62,81.62,0,0,1,11.92-7.21c3.12-1.47,8.45-4.13,15.54-4a32.32,32.32,0,0,1,25.69,13,31.89,31.89,0,0,1,6.36,18.66c0-.21,24.46-79.6-80-79.6-43.9,0-80,41.66-80,78.21a130.17,130.17,0,0,0,12.11,56,128,128,0,0,0,156.38,67.11,75.53,75.53,0,0,1-62.78-8Z" transform="translate(0.02 0)" style="opacity:0.4099999964237213;isolation:isolate;fill:url(#fe97b05f-3188-4793-8e1b-46ec3f337d64)"/><path d="M152.31,148.86c-.81,1-3.3,2.5-3.3,5.66,0,2.61,1.7,5.12,4.72,7.23,14.38,10,41.49,8.68,41.56,8.68a59.6,59.6,0,0,0,30.27-8.35A61.36,61.36,0,0,0,256,109.2c.26-22.41-8-37.31-11.34-43.91C223.46,23.84,177.72,0,128,0A128,128,0,0,0,0,126.2c.48-36.54,36.8-66.05,80-66.05,3.5,0,23.46.34,42,10.07,16.34,8.58,24.9,18.94,30.85,29.21,6.18,10.67,7.28,24.15,7.28,29.52S157.37,142.28,152.31,148.86Z" transform="translate(0.02 0)" style="fill:url(#b5860bc6-66c5-4872-a544-cd5083b094a5)"/><path d="M152.31,148.86c-.81,1-3.3,2.5-3.3,5.66,0,2.61,1.7,5.12,4.72,7.23,14.38,10,41.49,8.68,41.56,8.68a59.6,59.6,0,0,0,30.27-8.35A61.36,61.36,0,0,0,256,109.2c.26-22.41-8-37.31-11.34-43.91C223.46,23.84,177.72,0,128,0A128,128,0,0,0,0,126.2c.48-36.54,36.8-66.05,80-66.05,3.5,0,23.46.34,42,10.07,16.34,8.58,24.9,18.94,30.85,29.21,6.18,10.67,7.28,24.15,7.28,29.52S157.37,142.28,152.31,148.86Z" transform="translate(0.02 0)" style="fill:url(#a5f0bf22-9f30-4c72-a92f-cd6ab2e6001d)"/></svg>';
$GLOBALS['UA_ICON']['Windows']          = $GLOBALS['UA_ICON']['Windows Phone OS'] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4875 4875"><path fill="#0078d4" d="M0 0h2311v2310H0zm2564 0h2311v2310H2564zM0 2564h2311v2311H0zm2564 0h2311v2311H2564"/></svg>';
$GLOBALS['UA_ICON']['Linux']            = $GLOBALS['UA_ICON']['Ubuntu'] = $GLOBALS['UA_ICON']['Debian'] = $GLOBALS['UA_ICON']['Fedora'] = $GLOBALS['UA_ICON']['CentOS'] = $GLOBALS['UA_ICON']['Arch Linux'] = $GLOBALS['UA_ICON']['openSUSE'] = $GLOBALS['UA_ICON']['Manjaro'] = $GLOBALS['UA_ICON']['Linux Mint'] = $GLOBALS['UA_ICON']['Red Hat'] = $GLOBALS['UA_ICON']['Gentoo'] = '<svg id="b1f0cd52-3ebf-40f7-bd2c-de8826bece89" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 406.6 484.61" style="transform: translateY(-1px);"><path d="M272.26,72.52a26.67,26.67,0,0,0-10.2,12.64,31.08,31.08,0,0,0,.81,20.39,33.83,33.83,0,0,0,12.05,17.19,22.07,22.07,0,0,0,9.84,3.81,18,18,0,0,0,10.35-1.8,20.06,20.06,0,0,0,8.58-9.37,34.64,34.64,0,0,0,2.82-12.6,42.54,42.54,0,0,0-2-16.31A27.36,27.36,0,0,0,293.9,72.41a20.52,20.52,0,0,0-8.34-3.12,17.76,17.76,0,0,0-8.84,1,22,22,0,0,0-4.49,2.45" transform="translate(-62.18 -0.02)" style="fill:#fff"/><path d="M426.55,293.46a229.34,229.34,0,0,0-12.86-44.72,128.23,128.23,0,0,0-16-30.22C391,209.59,382.53,202.06,376,193c-3.47-4.69-6.41-9.94-10-14.58q-2.2-4.52-4.3-9.09c-4.34-9.45-8.28-19.14-13.43-28.12-.81-1.42-1.64-2.81-2.52-4.17-.65-8.66-1.56-17.3-2-26a243.89,243.89,0,0,0-4.36-51.87A84.57,84.57,0,0,0,329,36.05a79.49,79.49,0,0,0-20.16-21.72A77.86,77.86,0,0,0,264.21,0a69.74,69.74,0,0,0-33.82,7.81A60.85,60.85,0,0,0,205,34.32a87.61,87.61,0,0,0-8.1,36c-.4,12.18.72,24.37,1.18,36.57.47,12.67.21,25.39,1.26,38,.34,4.08.81,8.14.8,12.24,0,2-.13,4.09-.16,6.12l-.16.44a259.16,259.16,0,0,1-18.28,27q-6.94,8.84-14.06,17.55A106,106,0,0,0,152,231a164.19,164.19,0,0,0-6.72,22l-.17.62a170.47,170.47,0,0,1-9.73,25c-.36.75-.74,1.56-1.1,2.25q-3.52,7.25-7.31,14.36l-2.92,5.48a87.43,87.43,0,0,0-4.89,10.19,33.17,33.17,0,0,0-1.81,6.48,34.21,34.21,0,0,0,.87,14.06c.3,1.15.64,2.29,1,3.41a74.11,74.11,0,0,0,4.25,9.81c.75,1.45,1.57,2.89,2.33,4.31l.7,1c.78,1.36,1.58,2.69,2.41,4l.09.16c.95,1.49,1.93,3,2.94,4.42l.14.2q1.55,2.15,3.13,4.27a113.08,113.08,0,0,0,21.46,42.76c-1.56,2.73-3,5.5-4.56,8.2a123.91,123.91,0,0,0-13.56,25.65,30.87,30.87,0,0,0-1,14.38,20.67,20.67,0,0,0,7.09,12.36,21.52,21.52,0,0,0,8.58,4,38.13,38.13,0,0,0,9.47.83,153.75,153.75,0,0,0,35.41-7q10.38-2.73,20.89-4.88a125.06,125.06,0,0,1,22.2-3c1.85,0,3.69,0,5.52-.18a104.1,104.1,0,0,0,15.29.56l1.88-.11c1.32.16,2.65.24,4,.31,9,.52,17.94,1.39,26.81,2.74q11.73,1.77,23.27,4.59a178.32,178.32,0,0,0,36.4,7,40.11,40.11,0,0,0,9.72-.77,21.6,21.6,0,0,0,8.81-4A20.67,20.67,0,0,0,380,454.05a30.83,30.83,0,0,0-1.06-14.4,119.37,119.37,0,0,0-13.81-25.57c-2-3.32-3.8-6.71-5.75-10a177.17,177.17,0,0,0,22-30.58,30,30,0,0,0,11.13-1.4A46.68,46.68,0,0,0,416,354.52a26.82,26.82,0,0,0,3.92-8,53,53,0,0,0,7.5-19.09,95.65,95.65,0,0,0-.83-34Z" transform="translate(-62.18 -0.02)" style="fill:#020204"/><path d="M218.36,140.14a19.28,19.28,0,0,0-3.48,7.36,38.32,38.32,0,0,0-1,8.12,71.75,71.75,0,0,1-1.36,16.28,50.2,50.2,0,0,1-8.42,15.3,92.49,92.49,0,0,0-14.73,26.43,47,47,0,0,0-1.71,18.22,193,193,0,0,0-17,30.67,167.55,167.55,0,0,0-13.8,51.12,130.43,130.43,0,0,0,9.19,63.78,104.49,104.49,0,0,0,27.21,37.92,93.44,93.44,0,0,0,19.89,13.2,89.18,89.18,0,0,0,79.89-.78A155.48,155.48,0,0,0,327,401a118.75,118.75,0,0,0,17.19-19.53,109.56,109.56,0,0,0,14.35-47.69,155.39,155.39,0,0,0-9.17-86.19,95.19,95.19,0,0,0-17.18-24.7,135.46,135.46,0,0,0-10.94-36.78c-3.88-8.4-8.6-16.42-12.19-25-1.47-3.5-2.75-7.09-4.39-10.51a31.56,31.56,0,0,0-6.4-9.38,26.45,26.45,0,0,0-10-5.81,43.37,43.37,0,0,0-11.47-2c-7.81-.39-15.62.62-23.34.31-6.25-.25-12.36-1.32-18.54-1a28.47,28.47,0,0,0-9.07,1.9,18.27,18.27,0,0,0-7.45,5.39m2.52-67.52A12.66,12.66,0,0,0,213.05,76a17.76,17.76,0,0,0-4.55,7.35,44.5,44.5,0,0,0-1,17.31,51.08,51.08,0,0,0,2.73,15.5,20.44,20.44,0,0,0,4.21,6.63,14.42,14.42,0,0,0,6.71,4,13.69,13.69,0,0,0,7.32-.26,15.94,15.94,0,0,0,6.25-3.81A21.1,21.1,0,0,0,240,113.4a36.85,36.85,0,0,0,1.23-10.71,44.56,44.56,0,0,0-2.06-13.31,30.05,30.05,0,0,0-6.81-11.56,19.4,19.4,0,0,0-5.22-3.91,13,13,0,0,0-6.33-1.39m51.43,0a26.67,26.67,0,0,0-10.2,12.64,31.08,31.08,0,0,0,.81,20.39,33.83,33.83,0,0,0,12.05,17.19,22.07,22.07,0,0,0,9.84,3.81,18,18,0,0,0,10.35-1.8,20.06,20.06,0,0,0,8.58-9.37,34.64,34.64,0,0,0,2.82-12.6,42.54,42.54,0,0,0-2-16.31A27.36,27.36,0,0,0,293.9,72.41a20.52,20.52,0,0,0-8.34-3.12,17.76,17.76,0,0,0-8.84,1,22,22,0,0,0-4.49,2.45" transform="translate(-62.18 -0.02)" style="fill:#fff"/><path d="M282.73,86.21a10.44,10.44,0,0,0-4.81,1.56,12.8,12.8,0,0,0-3.66,3.56,18.4,18.4,0,0,0-2.92,9.69,20.73,20.73,0,0,0,1,7.58,14.42,14.42,0,0,0,4.27,6.25,12.39,12.39,0,0,0,7.22,2.81,12.08,12.08,0,0,0,7.43-2.13,13.29,13.29,0,0,0,4.1-4.68,17.74,17.74,0,0,0,1.86-6A18.41,18.41,0,0,0,295.5,94.1a15.11,15.11,0,0,0-8-7.27,11.38,11.38,0,0,0-4.69-.73" transform="translate(-62.18 -0.02)" style="fill:#020204"/><path d="M220.86,72.52A12.66,12.66,0,0,0,213.05,76a17.76,17.76,0,0,0-4.55,7.35,44.5,44.5,0,0,0-1,17.31,51.08,51.08,0,0,0,2.73,15.5,20.44,20.44,0,0,0,4.21,6.63,14.42,14.42,0,0,0,6.71,4,13.69,13.69,0,0,0,7.32-.26,15.94,15.94,0,0,0,6.25-3.81A21.1,21.1,0,0,0,240,113.4a36.85,36.85,0,0,0,1.23-10.71,44.56,44.56,0,0,0-2.06-13.31,30.05,30.05,0,0,0-6.81-11.56,19.4,19.4,0,0,0-5.22-3.91,13,13,0,0,0-6.33-1.39" transform="translate(-62.18 -0.02)" style="fill:#fff"/><path d="M213.85,96.58a22,22,0,0,0,.62,10.94,18.44,18.44,0,0,0,3.88,6.25,12.73,12.73,0,0,0,3.78,2.93,7,7,0,0,0,4.69.6,6.91,6.91,0,0,0,3.62-2.44,12,12,0,0,0,2-4A22.05,22.05,0,0,0,232.22,98a16.79,16.79,0,0,0-4.94-8,9.82,9.82,0,0,0-4-2.1,7,7,0,0,0-4.53.36,7.7,7.7,0,0,0-3.5,3.54,16.28,16.28,0,0,0-1.45,4.82" transform="translate(-62.18 -0.02)" style="fill:#020204"/><path d="M207,142.12a4.54,4.54,0,0,0,.53,1.38,6.17,6.17,0,0,0,1.83,1.84c.68.5,1.42.9,2.14,1.36a38.79,38.79,0,0,1,9.67,9.37,60.42,60.42,0,0,0,11.9,13.52A25.13,25.13,0,0,0,245,173.85a38.7,38.7,0,0,0,14.81-1.72,52.28,52.28,0,0,0,12.7-5.57,133,133,0,0,1,22.05-14.46c1.81-.65,3.68-1.14,5.42-1.95a8.54,8.54,0,0,0,4.22-3.78,18.52,18.52,0,0,0,1-5.42c.28-1.95.9-3.86,1.25-5.8a9.74,9.74,0,0,0-.45-5.82,7.55,7.55,0,0,0-3.43-3.41,11.71,11.71,0,0,0-4.68-1.17,69.53,69.53,0,0,0-9.77,1c-4.32.39-8.67-.16-13,0-5.39.16-10.72,1.37-16.11,1.56-6.15.3-12.3-.66-18.45-.91a38.14,38.14,0,0,0-8,.38,18.38,18.38,0,0,0-7.4,2.86,71.37,71.37,0,0,0-5.81,5.18,14.67,14.67,0,0,1-3.21,2.21,8.29,8.29,0,0,1-3.76.89,6.1,6.1,0,0,0-2,0,2.81,2.81,0,0,0-1,.65,6.3,6.3,0,0,0-.78,1,17.54,17.54,0,0,0-1.42,2.54" transform="translate(-62.18 -0.02)" style="fill:#d99a03"/><path d="M220.58,128.5c-2.17,1.31-4.3,2.71-6.36,4.19a7.76,7.76,0,0,0-2.73,2.76,6.51,6.51,0,0,0-.5,2.72,25.78,25.78,0,0,1,0,2.78c-.08.62-.25,1.25-.29,1.89a3.34,3.34,0,0,0,.1.94,1.94,1.94,0,0,0,.45.82,2.28,2.28,0,0,0,1.06.61,11.83,11.83,0,0,0,1.21.28,12,12,0,0,1,5,2.94c1.47,1.31,2.79,2.8,4.32,4a24.21,24.21,0,0,0,15,4.84,67,67,0,0,0,15.81-2.2,96,96,0,0,0,12-3.33A53.51,53.51,0,0,0,282.12,142a56.23,56.23,0,0,1,6.74-5.58c2.18-1.38,4.68-2.28,6.86-3.6a3.6,3.6,0,0,0,.57-.39,1.62,1.62,0,0,0,.44-.53,1.58,1.58,0,0,0,0-1.17,3.19,3.19,0,0,0-.48-1,7.44,7.44,0,0,0-.92-.94,14.79,14.79,0,0,0-8.53-3c-3.13-.23-6.15,0-9.16-.57a59.84,59.84,0,0,1-8.28-2.41,60.68,60.68,0,0,0-8.8-2.14,58.18,58.18,0,0,0-21.17.52,62,62,0,0,0-18.75,7.31" transform="translate(-62.18 -0.02)" style="fill:#604405"/><path d="M219.89,121.11a38.73,38.73,0,0,0-8.37,7.64,18,18,0,0,0-3.31,5.56,43.25,43.25,0,0,0-1.08,5,9.28,9.28,0,0,0-.28,1.89,3.34,3.34,0,0,0,.14.94,2.23,2.23,0,0,0,.48.83,2.7,2.7,0,0,0,1.41.7c.52.11,1.05.12,1.56.19a14.63,14.63,0,0,1,6.53,2.78c2,1.36,3.78,2.92,5.8,4.2a30.32,30.32,0,0,0,15,4.28,67.43,67.43,0,0,0,15.7-1.56,71.66,71.66,0,0,0,12.11-3.41,72.3,72.3,0,0,0,16.5-9.83,68.81,68.81,0,0,0,6.73-5.57c.72-.69,1.41-1.41,2.17-2a7.9,7.9,0,0,1,2.56-1.48,9.6,9.6,0,0,1,4.49-.08,15.91,15.91,0,0,0,3.37.39,5,5,0,0,0,1.67-.26,3.42,3.42,0,0,0,1.38-1,3.32,3.32,0,0,0,.72-2.07,4.21,4.21,0,0,0-.61-2.13,7.23,7.23,0,0,0-3.44-2.76,33.45,33.45,0,0,0-5.87-1.72,84.91,84.91,0,0,1-17.68-6.46c-2.79-1.39-5.51-2.92-8.28-4.4a48.66,48.66,0,0,0-8.79-3.91,34.58,34.58,0,0,0-21.17,1,45.34,45.34,0,0,0-19.52,13.36" transform="translate(-62.18 -0.02)" style="fill:#f5bd0c"/><path d="M255.14,112.53c.37,1.22,2.33,1,3.45,1.56s1.79,1.57,2.89,1.66,2.72-.37,2.86-1.42c.19-1.39-1.84-2.28-3.12-2.78a6.69,6.69,0,0,0-5.42-.11C255.42,111.66,255,112.13,255.14,112.53Zm-18.64-.69c-1.45-.47-3.84,2.08-3.12,3.39.22.36.87.82,1.31.58s1.22-1.69,1.94-2.19c.54-.35.43-1.6-.13-1.78Z" transform="translate(-62.18 -0.02)" style="fill:#cd8907"/><path d="M468.45,414.31a27.74,27.74,0,0,1-4.59,7.73,50.07,50.07,0,0,1-16.14,11.45,318.89,318.89,0,0,0-30.05,15.86A123.91,123.91,0,0,0,400,463.61a140.43,140.43,0,0,1-14.4,13.08,41.43,41.43,0,0,1-17.94,7.59,43,43,0,0,1-23.32-3.53,28,28,0,0,1-13-10.15,30.62,30.62,0,0,1-3.64-16.19,169,169,0,0,1,3.55-29.48c1.44-8.11,2.81-16.24,3.69-24.44a252,252,0,0,0,.52-44.88,33.77,33.77,0,0,1,0-7.52,9.55,9.55,0,0,1,9.71-8.91,35.83,35.83,0,0,1,6.93.58,145.23,145.23,0,0,1,16.14,2.8c3.32.87,6.57,2,9.9,2.95a45.4,45.4,0,0,0,17.08,1.56,133.08,133.08,0,0,1,18.31-2.87,26.83,26.83,0,0,1,7.48,1.31,15.93,15.93,0,0,1,6.72,3.75,14.82,14.82,0,0,1,3.13,5,30.26,30.26,0,0,1,1.9,8.56,73,73,0,0,0,.68,7.81,25.48,25.48,0,0,0,5.74,11.31,73.35,73.35,0,0,0,9.28,8.69,113.53,113.53,0,0,0,10.07,7.81c1.65,1.13,3.36,2.19,4.92,3.42a15.64,15.64,0,0,1,4,4.44,11,11,0,0,1,1.11,7.81" transform="translate(-62.18 -0.02)" style="fill:#f5bd0c"/><path d="M468.45,414.31a27.74,27.74,0,0,1-4.59,7.73,50.07,50.07,0,0,1-16.14,11.45,318.89,318.89,0,0,0-30.05,15.86A123.91,123.91,0,0,0,400,463.61a140.43,140.43,0,0,1-14.4,13.08,41.43,41.43,0,0,1-17.94,7.59,43,43,0,0,1-23.32-3.53,28,28,0,0,1-13-10.15,30.62,30.62,0,0,1-3.64-16.19,169,169,0,0,1,3.55-29.48c1.44-8.11,2.81-16.24,3.69-24.44a252,252,0,0,0,.52-44.88,33.77,33.77,0,0,1,0-7.52,9.55,9.55,0,0,1,9.71-8.91,35.83,35.83,0,0,1,6.93.58,145.23,145.23,0,0,1,16.14,2.8c3.32.87,6.57,2,9.9,2.95a45.4,45.4,0,0,0,17.08,1.56,133.08,133.08,0,0,1,18.31-2.87,26.83,26.83,0,0,1,7.48,1.31,15.93,15.93,0,0,1,6.72,3.75,14.82,14.82,0,0,1,3.13,5,30.26,30.26,0,0,1,1.9,8.56,73,73,0,0,0,.68,7.81,25.48,25.48,0,0,0,5.74,11.31,73.35,73.35,0,0,0,9.28,8.69,113.53,113.53,0,0,0,10.07,7.81c1.65,1.13,3.36,2.19,4.92,3.42a15.64,15.64,0,0,1,4,4.44,11,11,0,0,1,1.11,7.81M128.43,331.26a14.91,14.91,0,0,1,8.33-.76,20.46,20.46,0,0,1,7.81,3.29A49,49,0,0,1,156,346.15c7.63,10.5,15,21.22,21.61,32.34,5.39,9,10.34,18.36,16.58,26.84,4.06,5.54,8.65,10.68,12.75,16.19a55.86,55.86,0,0,1,9.53,18.14,36.31,36.31,0,0,1-2.66,26,34.32,34.32,0,0,1-12.68,13.61,32.71,32.71,0,0,1-18,4.68,88.07,88.07,0,0,1-28.48-9c-18.89-7.53-39.42-9.89-58.89-15.75-6-1.79-11.87-3.94-17.89-5.59A53.65,53.65,0,0,1,70,451.13a13.75,13.75,0,0,1-6.25-5.25,11.79,11.79,0,0,1-1.56-6.25,19.38,19.38,0,0,1,1.26-6.25c1.47-4,3.83-7.69,5.43-11.67A49.17,49.17,0,0,0,71.59,401c-.34-7-1.56-13.94-2-20.94a36.31,36.31,0,0,1,.3-9.37,14.06,14.06,0,0,1,11.83-12,38.31,38.31,0,0,1,8.62-.55,83.38,83.38,0,0,0,8.66,0,19.89,19.89,0,0,0,8.26-2.31,19.51,19.51,0,0,0,5.94-5.61,67.67,67.67,0,0,0,4.25-7,43.91,43.91,0,0,1,4.47-6.89,17.1,17.1,0,0,1,6.43-5" transform="translate(-62.18 -0.02)" style="fill:#f5bd0c"/><path d="M128.43,331.26a14.91,14.91,0,0,1,8.33-.76,20.46,20.46,0,0,1,7.81,3.29A49,49,0,0,1,156,346.15c7.63,10.5,15,21.22,21.61,32.34,5.39,9,10.34,18.36,16.58,26.84,4.06,5.54,8.65,10.68,12.75,16.19a55.86,55.86,0,0,1,9.53,18.14,36.31,36.31,0,0,1-2.66,26,34.32,34.32,0,0,1-12.68,13.61,32.71,32.71,0,0,1-18,4.68,88.07,88.07,0,0,1-28.48-9c-18.89-7.53-39.42-9.89-58.89-15.75-6-1.8-11.87-3.94-17.89-5.59A53.65,53.65,0,0,1,70,451.13a13.75,13.75,0,0,1-6.25-5.25,11.82,11.82,0,0,1-1.56-6.25,19.38,19.38,0,0,1,1.26-6.25c1.47-4,3.83-7.69,5.43-11.67A49.17,49.17,0,0,0,71.59,401c-.34-7-1.56-13.94-2-20.94a36.31,36.31,0,0,1,.3-9.37,14.06,14.06,0,0,1,11.83-12,38.31,38.31,0,0,1,8.62-.55,83.38,83.38,0,0,0,8.66,0,19.89,19.89,0,0,0,8.26-2.31,19.51,19.51,0,0,0,5.94-5.61,68.73,68.73,0,0,0,4.25-7,43.91,43.91,0,0,1,4.47-6.89,17.1,17.1,0,0,1,6.43-5" transform="translate(-62.18 -0.02)" style="fill:#f5bd0c"/><path d="M132.56,335.84a12.78,12.78,0,0,1,7.54-.5,17.79,17.79,0,0,1,6.83,3.34,43.44,43.44,0,0,1,9.55,11.86c6.48,10.5,12.81,21.1,18.75,31.92a210.75,210.75,0,0,0,14.42,24c3.68,5,7.9,9.51,11.67,14.42a48.73,48.73,0,0,1,8.79,16.2,31.91,31.91,0,0,1-2.43,23.21A30.9,30.9,0,0,1,196,472.5a30.1,30.1,0,0,1-16.45,4.06,92.06,92.06,0,0,1-26.08-8.07c-16.48-6-34.37-6.78-51.24-11.46-6.07-1.63-12-3.84-18.07-5.37a55.79,55.79,0,0,1-8-2.31,13,13,0,0,1-6.36-5.16,11.36,11.36,0,0,1-1.37-6,18.44,18.44,0,0,1,1.33-6c1.47-3.86,3.78-7.35,5.26-11.21a43.16,43.16,0,0,0,2.11-18.49c-.42-6.25-1.56-12.42-1.87-18.65a32,32,0,0,1,.37-8.35,14.36,14.36,0,0,1,3.75-7.35,14.56,14.56,0,0,1,8.13-3.77,38.14,38.14,0,0,1,9.06,0,59.84,59.84,0,0,0,9.08.42,17,17,0,0,0,8.59-2.62,17.3,17.3,0,0,0,5.3-6.25,66.57,66.57,0,0,0,3.22-7.53,35.68,35.68,0,0,1,3.62-7.36,14.21,14.21,0,0,1,6.25-5.28" transform="translate(-62.18 -0.02)" style="fill:#f5bd0c"/></svg>';
$GLOBALS['UA_ICON']['Android']          = $GLOBALS['UA_ICON']['Android Browser'] = '<svg xmlns="http://www.w3.org/2000/svg" width="152" height="89" fill="none" viewBox="0 0 152 89"><g clip-path="url(#a)"><path fill="#34A853" d="M151.025 85.224q-.071-.464-.147-.92a75.665 75.665 0 0 0-7.546-22.597 76.5 76.5 0 0 0-5.511-8.995 76 76 0 0 0-8.322-9.808 76.034 76.034 0 0 0-13.398-10.626q.042-.074.085-.148 2.286-3.948 4.572-7.897l4.47-7.712a3946 3946 0 0 0 3.208-5.54q.38-.658.604-1.355a6.97 6.97 0 0 0-.652-5.702 6.9 6.9 0 0 0-2.406-2.398 7 7 0 0 0-2.954-.95 7 7 0 0 0-2.376.206 6.93 6.93 0 0 0-4.22 3.227q-1.606 2.77-3.208 5.54l-4.47 7.712c-1.523 2.634-3.05 5.263-4.573 7.897q-.25.43-.5.865c-.232-.092-.46-.184-.692-.272-8.398-3.205-17.511-4.958-27.036-4.958q-.39-.001-.78.004A75.7 75.7 0 0 0 50.977 25q-1.317.46-2.608.968-.234-.404-.467-.806-2.286-3.95-4.573-7.897l-4.47-7.713a4385 4385 0 0 1-3.208-5.54A6.93 6.93 0 0 0 29.055.58a6.9 6.9 0 0 0-2.954.95 6.92 6.92 0 0 0-3.157 4.185 6.96 6.96 0 0 0 .703 5.27l3.208 5.54 4.47 7.713c1.523 2.634 3.05 5.263 4.573 7.897.01.022.025.044.036.066a76.3 76.3 0 0 0-13.527 10.711 76.5 76.5 0 0 0-8.322 9.808 75.4 75.4 0 0 0-5.51 8.995 75.7 75.7 0 0 0-7.546 22.597 76.038 76.038 0 0 0-.581 4.247h151a77 77 0 0 0-.434-3.327z"/><path fill="#202124" d="M115.225 67.663c3.022-2.012 3.461-6.668.981-10.4-2.48-3.73-6.939-5.123-9.96-3.11-3.021 2.012-3.46 6.668-.98 10.4 2.479 3.73 6.938 5.123 9.959 3.11M46.762 64.564c2.48-3.73 2.04-8.387-.98-10.4-3.022-2.012-7.481-.619-9.96 3.112s-2.041 8.387.98 10.4 7.48.62 9.96-3.112"/></g><defs><clipPath id="a"><path fill="#fff" d="M.459.555h151v88h-151z"/></clipPath></defs></svg>';
$GLOBALS['UA_ICON']['Macintosh']        = $GLOBALS['UA_ICON']['iPhone'] = $GLOBALS['UA_ICON']['iPad'] = $GLOBALS['UA_ICON']['iPod Touch'] = '<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" width="814" height="1000" viewBox="0 0 814 1000"><path d="M788.1 340.9c-5.8 4.5-108.2 62.2-108.2 190.5 0 148.4 130.3 200.9 134.2 202.2-.6 3.2-20.7 71.9-68.7 141.9-42.8 61.6-87.5 123.1-155.5 123.1s-85.5-39.5-164-39.5c-76.5 0-103.7 40.8-165.9 40.8s-105.6-57-155.5-127C46.7 790.7 0 663 0 541.8c0-194.4 126.4-297.5 250.8-297.5 66.1 0 121.2 43.4 162.7 43.4 39.5 0 101.1-46 176.3-46 28.5 0 130.9 2.6 198.3 99.2zm-234-181.5c31.1-36.9 53.1-88.1 53.1-139.3 0-7.1-.6-14.3-1.9-20.1-50.6 1.9-110.8 33.7-147.1 75.8-28.5 32.4-55.1 83.6-55.1 135.5 0 7.8 1.3 15.6 1.9 18.1 3.2.6 8.4 1.3 13.6 1.3 45.4 0 102.5-30.4 135.5-71.3z"/></svg>';
$GLOBALS['UA_ICON']['WeChat']           = '<svg width="800px" height="800px" viewBox="0 0 300 300" xmlns="http://www.w3.org/2000/svg"> <path fill="#2DC100" d="M300 255c0 24.854-20.147 45-45 45H45c-24.854 0-45-20.146-45-45V45C0 20.147 20.147 0 45 0h210c24.853 0 45 20.147 45 45v210z"/> <g fill="#FFF"> <path d="M200.803 111.88c-24.213 1.265-45.268 8.605-62.362 25.188-17.271 16.754-25.155 37.284-23 62.734-9.464-1.172-18.084-2.462-26.753-3.192-2.994-.252-6.547.106-9.083 1.537-8.418 4.75-16.488 10.113-26.053 16.092 1.755-7.938 2.891-14.889 4.902-21.575 1.479-4.914.794-7.649-3.733-10.849-29.066-20.521-41.318-51.232-32.149-82.85 8.483-29.25 29.315-46.989 57.621-56.236 38.635-12.62 82.054.253 105.547 30.927 8.485 11.08 13.688 23.516 15.063 38.224zm-111.437-9.852c.223-5.783-4.788-10.993-10.74-11.167-6.094-.179-11.106 4.478-11.284 10.483-.18 6.086 4.475 10.963 10.613 11.119 6.085.154 11.186-4.509 11.411-10.435zm58.141-11.171c-5.974.11-11.022 5.198-10.916 11.004.109 6.018 5.061 10.726 11.204 10.652 6.159-.074 10.83-4.832 10.772-10.977-.051-6.032-4.981-10.79-11.06-10.679z"/> <path d="M255.201 262.83c-7.667-3.414-14.7-8.536-22.188-9.318-7.459-.779-15.3 3.524-23.104 4.322-23.771 2.432-45.067-4.193-62.627-20.432-33.397-30.89-28.625-78.254 10.014-103.568 34.341-22.498 84.704-14.998 108.916 16.219 21.129 27.24 18.646 63.4-7.148 86.284-7.464 6.623-10.15 12.073-5.361 20.804.884 1.612.985 3.653 1.498 5.689zm-87.274-84.499c4.881.005 8.9-3.815 9.085-8.636.195-5.104-3.91-9.385-9.021-9.406-5.06-.023-9.299 4.318-9.123 9.346.166 4.804 4.213 8.69 9.059 8.696zm56.261-18.022c-4.736-.033-8.76 3.844-8.953 8.629-.205 5.117 3.772 9.319 8.836 9.332 4.898.016 8.768-3.688 8.946-8.562.19-5.129-3.789-9.364-8.829-9.399z"/> </g> </svg>';
$GLOBALS['UA_ICON']['QQ']               = '<svg xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 48 48" width="48px" height="48px"><path fill="#FFC107" d="M17.5,44c-3.6,0-6.5-1.6-6.5-3.5s2.9-3.5,6.5-3.5s6.5,1.6,6.5,3.5S21.1,44,17.5,44z M37,40.5c0-1.9-2.9-3.5-6.5-3.5S24,38.6,24,40.5s2.9,3.5,6.5,3.5S37,42.4,37,40.5z"/><path fill="#37474F" d="M37.2,22.2c-0.1-0.3-0.2-0.6-0.3-1c0.1-0.5,0.1-1,0.1-1.5c0-1.4-0.1-2.6-0.1-3.6C36.9,9.4,31.1,4,24,4S11,9.4,11,16.1c0,0.9,0,2.2,0,3.6c0,0.5,0,1,0.1,1.5c-0.1,0.3-0.2,0.6-0.3,1c-1.9,2.7-3.8,6-3.8,8.5C7,35.5,8.4,35,8.4,35c0.6,0,1.6-1,2.5-2.1C13,38.8,18,43,24,43s11-4.2,13.1-10.1C38,34,39,35,39.6,35c0,0,1.4,0.5,1.4-4.3C41,28.2,39.1,24.8,37.2,22.2z"/><path fill="#ECEFF1" d="M14.7,23c-0.5,1.5-0.7,3.1-0.7,4.8C14,35.1,18.5,41,24,41s10-5.9,10-13.2c0-1.7-0.3-3.3-0.7-4.8H14.7z"/><path fill="#FFF" d="M23,13.5c0,1.9-1.1,3.5-2.5,3.5S18,15.4,18,13.5s1.1-3.5,2.5-3.5S23,11.6,23,13.5z M27.5,10c-1.4,0-2.5,1.6-2.5,3.5s1.1,3.5,2.5,3.5s2.5-1.6,2.5-3.5S28.9,10,27.5,10z"/><path fill="#37474F" d="M22,13.5c0,0.8-0.4,1.5-1,1.5s-1-0.7-1-1.5s0.4-1.5,1-1.5S22,12.7,22,13.5z M27,12c-0.6,0-1,0.7-1,1.5s0.4-0.5,1-0.5s1,1.3,1,0.5S27.6,12,27,12z"/><path fill="#FFC107" d="M32,19.5c0,0.8-3.6,2.5-8,2.5s-8-1.7-8-2.5s3.6-1.5,8-1.5S32,18.7,32,19.5z"/><path fill="#FF3D00" d="M38.7,21.2c-0.4-1.5-1-2.2-2.1-1.3c0,0-5.9,3.1-12.5,3.1v0.1l0-0.1c-6.6,0-12.5-3.1-12.5-3.1c-1.1-0.8-1.7-0.2-2.1,1.3c-0.4,1.5-0.7,2,0.7,2.8c0.1,0.1,1.4,0.8,3.4,1.7c-0.6,3.5-0.5,6.8-0.5,7c0.1,1.5,1.3,1.3,2.9,1.3c1.6-0.1,2.9,0,2.9-1.6c0-0.9,0-2.9,0.3-5c1.6,0.3,3.2,0.6,5,0.6l0,0v0c7.3,0,13.7-3.9,13.9-4C39.3,23.3,39,22.8,38.7,21.2z"/><path fill="#DD2C00" d="M13.2,27.7c1.6,0.6,3.5,1.3,5.6,1.7c0-0.6,0.1-1.3,0.2-2c-2.1-0.5-4-1.1-5.5-1.7C13.4,26.4,13.3,27.1,13.2,27.7z"/></svg>';
$GLOBALS['UA_ICON']['Zhihu']            = '<svg width="88px" height="88px" viewBox="0 0 24.00 24.00" xmlns="http://www.w3.org/2000/svg" fill="#0075D2"><g id="SVGRepo_bgCarrier" stroke-width="0"/><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"/><g id="SVGRepo_iconCarrier"> <g> <path fill="none" d="M0 0h24v24H0z"/> <path d="M12.344 17.963l-1.688 1.074-2.131-3.35c-.44 1.402-1.172 2.665-2.139 3.825-.402.483-.82.918-1.301 1.375-.155.147-.775.717-.878.82l-1.414-1.414c.139-.139.787-.735.915-.856.43-.408.795-.79 1.142-1.206 1.266-1.518 2.03-3.21 2.137-5.231H3v-2h4V7h-.868c-.689 1.266-1.558 2.222-2.618 2.857L2.486 8.143c1.395-.838 2.425-2.604 3.038-5.36l1.952.434c-.14.633-.303 1.227-.489 1.783H11.5v2H9v4h2.5v2H9.185l3.159 4.963zm3.838-.07L17.298 17H19V7h-4v10h.736l.446.893zM13 5h8v14h-3l-2.5 2-1-2H13V5z"/></g></g></svg>';
$GLOBALS['UA_ICON']['Quark']            = '<svg height="2487" viewBox="40.722 40.484 943.271 938.508" width="2500" xmlns="http://www.w3.org/2000/svg"><path d="m469.135 976.134c-110.259-9.764-215.516-59.535-290.768-137.168-75.49-78.11-113.831-154.553-132.168-263.859-4.763-28.339-5.477-95.97-1.19-123.833 10.24-68.822 33.1-134.072 64.297-184.32 88.35-142.407 236.71-226.47 399.598-226.47 69.537 0 132.168 12.621 192.417 39.055 52.629 23.1 110.497 64.297 149.313 106.448 60.726 65.965 91.922 122.166 114.784 206.943 18.098 66.917 18.575 160.983 1.429 227.423-19.29 73.586-45.485 126.214-92.637 184.559-40.96 50.961-84.063 86.92-140.74 117.402-59.535 32.15-114.545 48.105-184.082 53.82-34.054 2.858-47.152 2.858-80.253 0zm84.063-238.616c11.669-5 20.718-19.051 20.718-32.625 0-23.338 4.525-49.771 10.478-61.44 12.146-23.814 28.339-32.149 77.396-39.77 19.05-2.857 38.578-6.905 43.341-8.81 13.574-5.954 24.767-17.385 32.149-33.34 6.668-14.526 6.906-15.955 6.906-53.105-.238-41.198-1.667-50.248-15.955-87.16-21.195-55.486-76.92-110.734-132.168-130.738-11.43-4.048-33.577-9.525-49.295-12.383-26.195-4.287-31.196-4.525-53.82-1.905-44.77 5.239-72.394 14.05-103.352 32.625-19.527 11.907-20.956 13.098-44.532 36.435-34.53 34.054-52.39 67.156-63.345 116.689-19.051 86.92 15.48 178.604 87.874 233.853 30.243 23.1 74.537 41.674 106.686 44.77 27.624 2.858 66.68 1.19 76.92-3.096z" fill="#3a25dd"/></svg>';
$GLOBALS['UA_ICON']['Lark']             = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="32" height="32"><path fill="#00d6b9" d="M273.46,264.31l1.01-1.01c.65-.65,1.36-1.36,2.06-2.01l1.41-1.36,4.17-4.12,5.73-5.58,4.88-4.83,4.57-4.52,4.78-4.73,4.37-4.32,6.13-6.03c1.16-1.16,2.36-2.26,3.57-3.37,2.21-2.01,4.52-3.97,6.84-5.88,2.16-1.71,4.37-3.37,6.64-4.98,3.17-2.26,6.43-4.32,9.75-6.33,3.27-1.91,6.64-3.72,10.05-5.43,3.22-1.56,6.54-3.02,9.9-4.32,1.86-.75,3.77-1.41,5.68-2.06,.96-.3,1.91-.65,2.92-.96h0c-8.5-33.43-24.03-64.6-45.6-91.5-4.17-5.18-10.51-8.19-17.14-8.19H128.97c-1.81,0-3.32,1.46-3.32,3.32,0,1.06,.5,2.01,1.36,2.66,60.13,44.09,110,100.75,146.04,166l.4-.45Z"/><path fill="#133c9a" d="M203.43,419.4c90.99,0,170.27-50.22,211.6-124.43,1.46-2.61,2.87-5.23,4.22-7.89h0c-2.06,3.97-4.37,7.79-6.94,11.41-.9,1.26-1.81,2.51-2.77,3.77-1.21,1.56-2.41,3.02-3.67,4.47-1.01,1.16-2.01,2.26-3.07,3.37-2.11,2.21-4.32,4.32-6.64,6.28-1.31,1.11-2.56,2.16-3.92,3.17-1.56,1.21-3.17,2.36-4.78,3.42-1.01,.7-2.06,1.36-3.12,2.01-1.06,.65-2.16,1.31-3.32,1.96-2.26,1.26-4.63,2.46-6.99,3.52-2.06,.9-4.17,1.76-6.28,2.56-2.31,.85-4.63,1.61-7.04,2.26-3.57,1.01-7.14,1.76-10.81,2.31-2.61,.4-5.33,.7-7.99,.9-2.82,.2-5.68,.25-8.55,.25-3.17-.05-6.33-.25-9.55-.6-2.36-.25-4.73-.6-7.09-1.01-2.06-.35-4.12-.8-6.18-1.31-1.11-.25-2.16-.55-3.27-.85-3.02-.8-6.03-1.66-9.05-2.51-1.51-.45-3.02-.85-4.47-1.31-2.26-.65-4.47-1.36-6.69-2.06-1.81-.55-3.62-1.16-5.43-1.76-1.71-.55-3.47-1.11-5.18-1.71l-3.52-1.21c-1.41-.5-2.87-1.01-4.27-1.51l-3.02-1.11c-2.01-.7-4.02-1.46-5.98-2.21-1.16-.45-2.31-.85-3.47-1.31-1.56-.6-3.07-1.21-4.63-1.81-1.61-.65-3.27-1.31-4.88-1.96l-3.17-1.31-3.92-1.61-3.02-1.26-3.12-1.36-2.71-1.21-2.46-1.11-2.51-1.16-2.56-1.21-3.27-1.51-3.42-1.61c-1.21-.6-2.41-1.16-3.62-1.76l-3.07-1.51c-54.09-27-102.91-63.39-144.23-107.53-1.26-1.31-3.32-1.41-4.68-.15-.65,.6-1.06,1.51-1.06,2.41l.1,155.49v12.62c0,7.34,3.62,14.18,9.7,18.25,39.56,26.44,86.12,40.47,133.73,40.37"/><path fill="#3370ff" d="M470.83,200.21c-30.72-15.03-65.86-18.25-98.79-9-1.41,.4-2.77,.8-4.12,1.21-.96,.3-1.91,.6-2.92,.96-1.91,.65-3.82,1.36-5.68,2.06-3.37,1.31-6.64,2.77-9.9,4.32-3.42,1.66-6.79,3.47-10.05,5.38-3.37,1.96-6.59,4.07-9.75,6.33-2.26,1.61-4.47,3.27-6.64,4.98-2.36,1.91-4.63,3.82-6.84,5.88-1.21,1.11-2.36,2.21-3.57,3.37l-6.13,6.03-4.37,4.32-4.78,4.73-4.57,4.52-4.88,4.83-5.68,5.63-4.17,4.12-1.41,1.36c-.65,.65-1.36,1.36-2.06,2.01l-1.01,1.01-1.56,1.46c-.6,.55-1.16,1.06-1.76,1.61-15.13,13.93-32.02,25.84-50.17,35.54l3.27,1.51,2.56,1.21,2.51,1.16,2.46,1.11,2.71,1.21,3.12,1.36,3.02,1.26,3.92,1.61,3.17,1.31c1.61,.65,3.27,1.31,4.88,1.96,1.51,.6,3.07,1.21,4.63,1.81,1.16,.45,2.31,.85,3.47,1.31,2.01,.75,4.02,1.46,5.98,2.21l3.02,1.11c1.41,.5,2.82,1.01,4.27,1.51l3.52,1.21c1.71,.55,3.42,1.16,5.18,1.71,1.81,.6,3.62,1.16,5.43,1.76,2.21,.7,4.47,1.36,6.69,2.06,1.51,.45,3.02,.9,4.47,1.31,3.02,.85,6.03,1.71,9.05,2.51,1.11,.3,2.16,.55,3.27,.85,2.06,.5,4.12,.9,6.18,1.31,2.36,.4,4.73,.75,7.09,1.01,3.22,.35,6.38,.55,9.55,.6,2.87,.05,5.73-.05,8.55-.25,2.71-.2,5.38-.5,7.99-.9,3.62-.55,7.24-1.36,10.81-2.31,2.36-.65,4.73-1.41,7.04-2.26,2.11-.75,4.22-1.61,6.28-2.56,2.36-1.06,4.73-2.26,6.99-3.52,1.11-.6,2.21-1.26,3.32-1.96,1.11-.65,2.11-1.36,3.12-2.01,1.61-1.11,3.22-2.21,4.78-3.42,1.36-1.01,2.66-2.06,3.92-3.17,2.26-1.96,4.47-4.07,6.59-6.28,1.06-1.11,2.06-2.21,3.07-3.37,1.26-1.46,2.51-2.97,3.67-4.47,.96-1.21,1.86-2.46,2.77-3.77,2.51-3.62,4.83-7.39,6.89-11.31l2.36-4.68,21.01-41.88,.25-.5c6.94-14.98,16.39-28.45,28-39.97Z"/></svg>';
$GLOBALS['UA_ICON']['Samsung Internet'] = '<svg xmlns="http://www.w3.org/2000/svg" id="Layer_1" version="1.1" viewBox="0 0 55.8 55.8"> <defs id="defs22"> <style id="style2"> .st0 { mask: url(#mask); } .st1 { fill: url(#_무제_그라디언트_4); } .st1, .st2, .st3, .st4 { fill-rule: evenodd; } .st2 { fill: url(#_무제_그라디언트_3); } .st3 { fill: url(#_무제_그라디언트_2); } .st4, .st5 { fill: #fff; } </style> <linearGradient id="_무제_그라디언트_4" data-name="무제 그라디언트 4" x1="-783.3" y1="565.5" x2="-783.3" y2="565.1" gradientTransform="matrix(144,0,0,-144,112827,81425)" gradientUnits="userSpaceOnUse"> <stop offset="0" stop-color="#7043ef" id="stop4" /> <stop offset="1" stop-color="#3e14d8" id="stop6" /> </linearGradient> <mask id="mask" x="0" y=".2" width="55.8" height="55.8" maskUnits="userSpaceOnUse"> <g id="mask-3"> <path id="path-21" data-name="path-2" class="st4" d="M 49.2,6.4 C 44.1,1.2 36.4,0.2 27.8,0.2 19.2,0.2 11.6,1.2 6.4,6.4 2.4,10.5 0,17.2 0,28.1 0,39 2.5,45.7 6.5,49.8 11.6,55 19.3,56 27.9,56 36.5,56 44.1,55 49.3,49.8 53.4,45.7 55.8,39 55.8,28.1 55.8,17.2 53.3,10.5 49.3,6.4 Z" /> </g> </mask> <linearGradient id="_무제_그라디언트_2" data-name="무제 그라디언트 2" x1="-789.8" y1="589.7" x2="-790.2" y2="589.7" gradientTransform="matrix(-90.014693,90.014693,28.567114,28.567114,-87924.9,54283.1)" gradientUnits="userSpaceOnUse"> <stop offset="0" stop-color="#8e99ff" id="stop12" /> <stop offset="1" stop-color="#72e3e3" id="stop14" /> </linearGradient> <linearGradient id="_무제_그라디언트_3" data-name="무제 그라디언트 3" x1="-789.8" y1="589.7" x2="-790.2" y2="589.7" gradientTransform="matrix(-90.014693,90.014693,28.567114,28.567114,-87924.9,54283.1)" gradientUnits="userSpaceOnUse"> <stop offset="0" stop-color="#8e99ff" id="stop17" /> <stop offset="1" stop-color="#40efef" id="stop19" /> </linearGradient> </defs> <g id="g54" /> <g id="Page-1" transform="translate(0,-0.2)"> <g id="OneUI7.X"> <g id="Group"> <g id="container"> <path id="path-2" class="st1" d="M 49.2,6.4 C 44.1,1.2 36.4,0.2 27.8,0.2 19.2,0.2 11.6,1.2 6.4,6.4 2.4,10.5 0,17.2 0,28.1 0,39 2.5,45.7 6.5,49.8 11.6,55 19.3,56 27.9,56 36.5,56 44.1,55 49.3,49.8 53.4,45.7 55.8,39 55.8,28.1 55.8,17.2 53.3,10.5 49.3,6.4 Z" /> </g> <g class="st0" mask="url(#mask)" id="g66"> <g id="Group-7"> <g id="g63"> <circle id="Oval" class="st5" cx="27.8" cy="28.1" r="16.4" /> <g id="Combined-Shape"> <path id="path-6" class="st3" d="m 33.3,33.6 c -9.6,9.6 -19.9,15 -23,11.9 -3.1,-3.1 2.3,-13.3 11.9,-23 9.6,-9.7 19.9,-15 23,-11.9 3.1,3.1 -2.3,13.3 -11.9,23 z m -1,-1 c 7.2,-7.2 11.6,-14.4 10,-16.1 -1.6,-1.7 -8.9,2.8 -16.1,10 -7.2,7.2 -11.6,14.4 -10,16.1 1.6,1.7 8.9,-2.8 16.1,-10 z" /> <path id="path-61" data-name="path-6" class="st2" d="m 33.3,33.6 c -9.6,9.6 -19.9,15 -23,11.9 -3.1,-3.1 2.3,-13.3 11.9,-23 9.6,-9.7 19.9,-15 23,-11.9 3.1,3.1 -2.3,13.3 -11.9,23 z m -1,-1 c 7.2,-7.2 11.6,-14.4 10,-16.1 -1.6,-1.7 -8.9,2.8 -16.1,10 -7.2,7.2 -11.6,14.4 -10,16.1 1.6,1.7 8.9,-2.8 16.1,-10 z" /> </g> <path id="Oval1" data-name="Oval" class="st4" d="m 16.2,39.7 c 6.4,6.4 16.8,6.4 23.3,0 6.4,-6.4 6.4,-16.8 0,-23.3 -5.5,1.4 -21.1,17 -23.3,23.3 z" /> </g> </g> </g> </g> </g> </g></svg>';
$GLOBALS['UA_ICON']['Chromium'] = '<svg version="1.1" id="svg44" width="511.98489" height="511.98489" viewBox="0 0 511.98489 511.98489" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://www.w3.org/2000/svg" xmlns:svg="http://www.w3.org/2000/svg"> <defs id="defs18"> <linearGradient xlink:href="#linearGradient4975" id="linearGradient4633" gradientUnits="userSpaceOnUse" gradientTransform="matrix(231.62575,0,0,231.62472,111.11013,159.99363)" x2="0.5565635" x1="0.46521288" y1="-0.67390651" y2="0.81129867" /> <linearGradient id="linearGradient4975"> <stop style="stop-color:#1972e7" offset="0" id="stop4971" /> <stop style="stop-color:#1969d5" offset="1" id="stop4973" /> </linearGradient> <linearGradient xlink:href="#3" id="linearGradient1331" x1="101.74381" y1="33.726189" x2="101.59915" y2="135.466" gradientUnits="userSpaceOnUse" gradientTransform="matrix(3.7794235,0,0,3.7794067,0.00151555,0.00377865)" /> <linearGradient id="3" x2="1" gradientTransform="matrix(61.286,0,0,61.286,29.399,42.333)" gradientUnits="userSpaceOnUse"> <stop offset="0" id="stop1397" style="stop-color:#afccfb" /> <stop offset="1" id="stop1399" style="stop-color:#8bb5f8" /> </linearGradient> <linearGradient xlink:href="#1" id="linearGradient2962" gradientUnits="userSpaceOnUse" gradientTransform="matrix(94.931559,164.42687,-164.4276,94.931137,97.555991,173.61083)" x2="1.7695541" x1="0.018202547" y1="-0.51170158" y2="0.4994337" /> <linearGradient id="1" x2="1" gradientTransform="matrix(25.118,43.506,-43.506,25.118,25.812,45.935)" gradientUnits="userSpaceOnUse"> <stop offset="0" id="stop3122" style="stop-color:#659cf6" /> <stop offset="1" id="stop3124" style="stop-color:#4285f4" /> </linearGradient> <linearGradient xlink:href="#2" id="linearGradient2688" x1="67.452377" y1="40.320694" x2="67.733002" y2="95.25" gradientUnits="userSpaceOnUse" gradientTransform="matrix(3.7794235,0,0,3.7794067,0.00150043,0.00377865)" /> <linearGradient id="2"> <stop style="stop-color:#3680f0" offset="0" id="stop2682" /> <stop style="stop-color:#2678ec" offset="1" id="stop2684" /> </linearGradient> </defs> <path d="m 255.99319,255.99433 110.85049,63.99671 -110.85049,191.99385 c 141.38068,0 255.9917,-114.61051 255.9917,-255.99056 0,-46.64165 -12.53559,-90.3316 -34.33115,-127.99716 h -221.6632 z" id="path34-4" style="fill:url(#linearGradient1331)" /> <path d="M 255.99054,0 C 161.2404,0 78.576848,51.513314 34.31224,128.0274 l 110.82781,191.96363 110.85049,-63.9967 V 127.99717 h 221.6632 C 433.38157,51.501975 350.72936,0 255.99054,0 Z" id="path36-1" style="fill:url(#linearGradient4633)" /> <path d="m 0.00151177,255.99433 c 0,141.38005 114.60723823,255.99056 255.99168823,255.99056 L 366.84368,319.99103 255.9932,255.99433 145.14271,319.99103 34.314897,128.0274 C 12.531434,165.68239 0,209.35646 0,255.99056" id="path38-7" style="fill:url(#linearGradient2962)" /> <path d="m 383.99094,255.99433 c 0,70.69003 -57.30741,127.99717 -127.99775,127.99717 -70.69034,0 -127.99773,-57.30714 -127.99773,-127.99717 0,-70.69002 57.30739,-127.99716 127.99773,-127.99716 70.69034,0 127.99775,57.30714 127.99775,127.99716" fill="#ffffff" id="path40" /> <path d="m 359.99158,255.99433 c 0,57.43565 -46.56249,103.99794 -103.99839,103.99794 -57.4359,0 -103.9984,-46.56229 -103.9984,-103.99794 0,-57.43564 46.5625,-103.99793 103.9984,-103.99793 57.4359,0 103.99839,46.56229 103.99839,103.99793" id="path42-5" style="fill:url(#linearGradient2688)" /></svg>';
$GLOBALS['UA_ICON']['Unknown']          = '<svg viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="5005" style="transform: scale(1.05) translateY(-1px);"><path d="M512.49152 511.50848m-504.29952 0a504.29952 504.29952 0 1 0 1008.59904 0 504.29952 504.29952 0 1 0-1008.59904 0Z" fill="#B9C0CB" p-id="5006"></path><path d="M506.92096 682.47552c-21.9136 0-39.64928 18.18624-39.64928 40.59136s17.73568 40.59136 39.64928 40.59136 39.69024-18.18624 39.69024-40.59136-17.77664-40.59136-39.69024-40.59136z m0-423.15776c-85.52448 9.70752-132.99712 47.63648-142.49984 113.74592-1.88416 21.38112 8.56064 33.05472 31.37536 35.0208 11.38688 1.96608 20.8896-6.79936 28.50816-26.25536 11.38688-40.83712 38.912-61.2352 82.65728-61.2352 53.16608 3.8912 81.67424 31.08864 85.52448 81.67424 0 46.65344-30.96576 54.272-47.75936 68.36224-21.2992 17.8176-35.2256 35.92192-52.30592 66.60096-14.336 25.76384-16.7936 80.896-16.7936 80.896 0 23.3472 10.40384 35.0208 31.37536 35.0208 18.96448 0 29.45024-11.6736 31.37536-35.0208 0 0 2.49856-61.8496 26.91072-89.21088 27.648-31.00672 93.75744-52.71552 95.6416-132.46464-7.70048-83.64032-58.9824-129.35168-154.0096-137.13408z" fill="#FFFFFF" p-id="5007"></path></svg>';