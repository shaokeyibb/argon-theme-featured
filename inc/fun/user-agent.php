<?php
$argon_comment_ua = get_option("argon_comment_ua");
$argon_comment_show_ua = Array();
if (strpos($argon_comment_ua, 'platform') !== false){
	$argon_comment_show_ua['platform'] = true;
}
if (strpos($argon_comment_ua, 'browser') !== false){
	$argon_comment_show_ua['browser'] = true;
}
if (strpos($argon_comment_ua, 'version') !== false){
	$argon_comment_show_ua['version'] = true;
}
function parse_ua_and_icon($userAgent, $comment_id = null){
	global $argon_comment_ua;
	global $argon_comment_show_ua;
	if ($argon_comment_ua == "" || $argon_comment_ua == "hidden"){
		return "";
	}
	
	// 尝试从 comment meta 读取 Sec-CH-UA 系列 Header
	$sec_ch_ua = null;
	$sec_ch_ua_platform = null;
	$sec_ch_ua_platform_version = null;
	$sec_ch_ua_full_version_list = null;
	
	if ($comment_id !== null){
		$sec_ch_ua = get_comment_meta($comment_id, "sec_ch_ua", true);
		$sec_ch_ua_platform = get_comment_meta($comment_id, "sec_ch_ua_platform", true);
		$sec_ch_ua_platform_version = get_comment_meta($comment_id, "sec_ch_ua_platform_version", true);
		$sec_ch_ua_full_version_list = get_comment_meta($comment_id, "sec_ch_ua_full_version_list", true);
	}
	
	$parsed = argon_parse_user_agent($userAgent, $sec_ch_ua, $sec_ch_ua_platform, $sec_ch_ua_platform_version, $sec_ch_ua_full_version_list);
	// 如果是管理员，添加 title 属性显示完整 UA 字符串
	$title_attr = "";
	if (current_user_can("manage_options")){
		$title_attr = " title='" . ($sec_ch_ua == null ? esc_attr($userAgent) : esc_attr($sec_ch_ua)) . "'";
	}
	$out = "<div class='comment-useragent'" . $title_attr . ">";
	if (isset($argon_comment_show_ua['platform']) && $argon_comment_show_ua['platform'] == true){
		if (isset($GLOBALS['UA_ICON'][$parsed['platform']])){
			$out .= $GLOBALS['UA_ICON'][$parsed['platform']] . " ";
		}else{
			$out .= $GLOBALS['UA_ICON']['Unknown'] . " ";
		}
		$out .= $parsed['platform'];
		// 如果存在操作系统版本，则显示
		if (isset($parsed['platform_version']) && $parsed['platform_version'] !== null){
			$out .= " " . $parsed['platform_version'];
		}
	}
	if (isset($argon_comment_show_ua['browser']) && $argon_comment_show_ua['browser'] == true){
		if (isset($GLOBALS['UA_ICON'][$parsed['browser']])){
			$out .= " " . $GLOBALS['UA_ICON'][$parsed['browser']];
		}else{
			$out .= " " . $GLOBALS['UA_ICON']['Unknown'];
		}
		$out .= " " . $parsed['browser'];
		if (isset($argon_comment_show_ua['version']) && $argon_comment_show_ua['version'] == true){
			$out .= " " . $parsed['version'];
		}
	}
	$out .= "</div>";
	return apply_filters("argon_comment_ua_icon", $out);
}
