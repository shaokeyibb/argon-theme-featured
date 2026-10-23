<?php
//文章特色图片
function argon_get_first_image_of_article($post_id = 0){
	$post = get_post($post_id ?: null);
	if (!$post || post_password_required($post)){
		return false;
	}
	$document = argon_html_document_parts($post);
	if (is_array($document)) {
		if (!argon_html_document_approved($document)) {
			return false;
		}
		$dom = argon_html_dom($document['html']);
		foreach ($dom->getElementsByTagName('img') as $image) {
			$url = $image->getAttribute('src');
			if ($url !== '' && !preg_match('/^(data|blob|javascript):/i', $url)) {
				$base = argon_html_document_base($document);
				return esc_url_raw(WP_Http::make_absolute_url($url, $base), array('http', 'https'));
			}
		}
		return false;
	}
	
	// 方法1：尝试从文章内容中查找附件ID（最佳实践）
	// 这样可以使用 WordPress 标准函数获取优化后的图片
	$content = $post -> post_content;
	
	// 查找 WordPress 图片块或附件 ID
	if (preg_match('/<!-- wp:image {"id":(\d+)}/i', $content, $block_match)) {
		// Gutenberg 图片块
		$attachment_id = intval($block_match[1]);
		if ($attachment_id) {
			$image_data = wp_get_attachment_image_src($attachment_id, 'full');
			if ($image_data) {
				return $image_data[0];
			}
		}
	}
	
	// 查找经典编辑器中的 wp-image-{id} class
	if (preg_match('/<img[^>]+class=["\'][^"\']*wp-image-(\d+)[^"\']*["\'][^>]*>/i', $content, $class_match)) {
		$attachment_id = intval($class_match[1]);
		if ($attachment_id) {
			$image_data = wp_get_attachment_image_src($attachment_id, 'full');
			if ($image_data) {
				return $image_data[0];
			}
		}
	}
	
	// Inspect stored markup without rendering every shortcode/block again for each thumbnail.
	$post_content_full = apply_filters('argon_first_image_source', $content, $post);
	
	// 优先查找 data-original 属性（lazyload 场景）
	if (preg_match('/<img[^>]+data-original=["\']([^"\']+)["\'][^>]*>/i', $post_content_full, $match)) {
		return $match[1];
	}
	
	// 然后查找普通 src 属性，但排除 data: 和占位符
	if (preg_match('/<img[^>]+src=["\'](?!data:)([^"\']+)["\'][^>]*>/i', $post_content_full, $match)) {
		return $match[1];
	}
	
	return false;
}
function argon_has_post_thumbnail($postID = 0){
	if ($postID == 0){
		global $post;
		$postID = $post -> ID;
	}
	if (has_post_thumbnail($postID)){
		return true;
	}
	$argon_first_image_as_thumbnail = get_post_meta($postID, 'argon_first_image_as_thumbnail', true);
	if ($argon_first_image_as_thumbnail == ""){
		$argon_first_image_as_thumbnail = "default";
	}
	if ($argon_first_image_as_thumbnail == "true" || ($argon_first_image_as_thumbnail == "default" && get_option("argon_first_image_as_thumbnail_by_default", "false") == "true")){
		if (argon_get_first_image_of_article($postID) != false){
			return true;
		}
	}
	return false;
}
function argon_get_post_thumbnail($postID = 0){
	if ($postID == 0){
		global $post;
		$postID = $post -> ID;
	}
	if (has_post_thumbnail($postID)){
		// 获取附件 ID
		$attachment_id = get_post_thumbnail_id($postID);
		// 使用 wp_get_attachment_image_src 获取图片信息，这会触发相关的过滤器
		$image_data = wp_get_attachment_image_src($attachment_id, "full");
		// 返回 URL（已经过 wp_get_attachment_image_src 过滤器处理）
		$url = $image_data ? $image_data[0] : '';
		return apply_filters("argon_post_thumbnail", $url, $postID, $attachment_id);
	}
	return apply_filters("argon_post_thumbnail", argon_get_first_image_of_article($postID), $postID, 0);
}
// 获取文章缩略图的完整 <img> 标签（推荐使用，支持 WebP 等现代图片格式插件）
function argon_get_post_thumbnail_image($postID = 0, $size = 'full', $attr = array()){
	if ($postID == 0){
		global $post;
		$postID = $post -> ID;
	}
	
	// 设置默认属性
	$default_attr = array(
		'class' => 'post-thumbnail',
		'alt' => get_the_title($postID)
	);
	$attr = array_merge($default_attr, $attr);
	
	if (has_post_thumbnail($postID)){
		// 使用 WordPress 标准函数，支持所有图片过滤器和插件
		$thumbnail = wp_get_attachment_image(get_post_thumbnail_id($postID), $size, false, $attr);
		return apply_filters("argon_post_thumbnail_image", $thumbnail, $postID, $size, $attr);
	}
	
	// 如果没有特色图片，尝试获取文章中的第一张图片
	$first_image_url = argon_get_first_image_of_article($postID);
	if ($first_image_url){
		$class = isset($attr['class']) ? esc_attr($attr['class']) : 'post-thumbnail';
		$alt = isset($attr['alt']) ? esc_attr($attr['alt']) : get_the_title($postID);
		$img_html = '<img src="' . esc_url($first_image_url) . '" class="' . $class . '" alt="' . $alt . '">';
		return apply_filters("argon_post_thumbnail_image", $img_html, $postID, $size, $attr);
	}
	
	return '';
}
//文末附加内容
function get_additional_content_after_post(){
	global $post;
	$postID = $post -> ID;
	$res = get_post_meta($post -> ID, 'argon_after_post', true);
	if ($res == "--none--"){
		return "";
	}
	if ($res == ""){
		$res = get_option("argon_additional_content_after_post");
	}
	$res = str_replace("\n", "</br>", $res);
	$res = str_replace("%url%", get_permalink($postID), $res);
	$res = str_replace("%link%", '<a href="' . get_permalink($postID) . '" target="_blank">' . get_permalink($postID) . '</a>', $res);
	$res = str_replace("%title%", get_the_title(), $res);
	$res = str_replace("%author%", get_the_author(), $res);
	return $res;
}
//页面分享预览图
function get_og_image(){
	global $post;
	$postID = $post -> ID;
	$argon_first_image_as_thumbnail = get_post_meta($postID, 'argon_first_image_as_thumbnail', 'true');
	if (has_post_thumbnail() || $argon_first_image_as_thumbnail == 'true'){
		return argon_get_post_thumbnail($postID);
	}
	return '';
}
//页面浏览量
function get_post_views($post_id){
	// Historical counts remain readable; counting belongs to a cache-aware plugin.
	return number_format_i18n((int) get_post_meta($post_id, 'views', true));
}
//字数和预计阅读时间
function get_article_words($str){
	preg_match_all('/<pre(.*?)>[\S\s]*?<code(.*?)>([\S\s]*?)<\/code>[\S\s]*?<\/pre>/im', $str, $codeSegments, PREG_PATTERN_ORDER);
	$codeSegments = $codeSegments[3];
	$codeTotal = 0;
	foreach ($codeSegments as $codeSegment){
		$codeLines = preg_split('/\r\n|\n|\r/', $codeSegment);
		foreach ($codeLines as $line){
			if (strlen(trim($str)) > 0){
				$codeTotal++;
			}
		}
	}

	$str = preg_replace(
		'/<code(.*?)>[\S\s]*?<\/code>/im',
		'',
		$str
	);
	$str = preg_replace(
		'/<pre(.*?)>[\S\s]*?<\/pre>/im',
		'',
		$str
	);
	$str = preg_replace(
		'/<style(.*?)>[\S\s]*?<\/style>/im',
		'',
		$str
	);
	$str = preg_replace(
		'/<script(.*?)>[\S\s]*?<\/script>/im',
		'',
		$str
	);
	$str =  preg_replace('/<[^>]+?>/', ' ', $str);
	$str = html_entity_decode(strip_tags($str));
	preg_match_all('/[\x{4e00}-\x{9fa5}]/u' , $str , $cnRes);
	$cnTotal = count($cnRes[0]);
	$enRes = preg_replace('/[\x{4e00}-\x{9fa5}]/u', '', $str);
	preg_match_all('/[a-zA-Z0-9_\x{0392}-\x{03c9}\x{0400}-\x{04FF}]+|[\x{4E00}-\x{9FFF}\x{3400}-\x{4dbf}\x{f900}-\x{faff}\x{3040}-\x{309f}\x{ac00}-\x{d7af}\x{0400}-\x{04FF}]+|[\x{00E4}\x{00C4}\x{00E5}\x{00C5}\x{00F6}\x{00D6}]+|\w+/u' , $str , $enRes);
	$enTotal = count($enRes[0]);
	return array(
		'cn' => $cnTotal,
		'en' => $enTotal,
		'code' => $codeTotal,
	);
}
function get_article_words_total($str){
	$res = get_article_words($str);
	return $res['cn'] + $res['en'] + $res['code'];
}
function get_reading_time($len){
	$speedcn = get_option('argon_reading_speed', 300);
	$speeden = get_option('argon_reading_speed_en', 160);
	$speedcode = get_option('argon_reading_speed_code', 20);
	$reading_time = $len['cn'] / $speedcn + $len['en'] / $speeden + $len['code'] / $speedcode;
	if ($reading_time < 0.3){
		return __("几秒读完", 'argon');
	}
	if ($reading_time < 1){
		return __("1 分钟内", 'argon');
	}
	if ($reading_time < 60){
		return ceil($reading_time) . " " . __("分钟", 'argon');
	}
	return round($reading_time / 60 , 1) . " " . __("小时", 'argon');
}
//当前文章是否可以生成目录
function have_catalog(){
	if (!is_single() && !is_page()){
		return false;
	}
	if (post_password_required()){
		return false;
	}
	if (argon_html_has_document()) {
		return argon_html_enabled() && strpos(argon_html_sandbox(), 'allow-scripts') !== false;
	}
	if (is_page() && is_page_template('timeline.php')){
		return true;
	}
	$content = get_post(get_the_ID()) -> post_content;
	if (preg_match('/<h[1-6](.*?)>/',$content)){
		return true;
	}else{
		return false;
	}
}
//获取文章 Meta
function get_article_meta($type){
	if ($type == 'sticky'){
		return '<div class="post-meta-detail post-meta-detail-stickey">
					<i class="fa fa-thumb-tack" aria-hidden="true"></i>
					' . _x('置顶', 'pinned', 'argon') . '
				</div>';
	}
	if ($type == 'needpassword'){
		return '<div class="post-meta-detail post-meta-detail-needpassword">
					<i class="fa fa-lock" aria-hidden="true"></i>
					' . __('需要密码', 'argon') . '
				</div>';
	}
	if ($type == 'time'){
		return '<div class="post-meta-detail post-meta-detail-time">
					<i class="fa fa-clock fa-clock-o" aria-hidden="true"></i>
					<time title="' . __('发布于', 'argon') . ' ' . get_the_time('Y-n-d G:i:s') . ' | ' . __('编辑于', 'argon') . ' ' . get_the_modified_time('Y-n-d G:i:s') . '">' .
						get_the_time('Y-n-d G:i') . '
					</time>
				</div>';
	}
	if ($type == 'edittime'){
		return '<div class="post-meta-detail post-meta-detail-edittime">
					<i class="fa fa-pencil fa-pen" aria-hidden="true"></i>
					<time title="' . __('发布于', 'argon') . ' ' . get_the_time('Y-n-d G:i:s') . ' | ' . __('编辑于', 'argon') . ' ' . get_the_modified_time('Y-n-d G:i:s') . '">' .
						get_the_modified_time('Y-n-d G:i') . '
					</time>
				</div>';
	}
	if ($type == 'views'){
		if ( get_option( "argon_view_counter" ) == 'wp-statistics' && function_exists( 'wp_statistics_pages' ) ) {
			$views = wp_statistics_pages( 'total', "", get_the_ID() ) . " " . __( "人看过", 'argon' );
		} else if ( get_option( "argon_view_counter" ) == 'post-views-counter' && function_exists( 'pvc_get_post_views' ) ) {
			$views = pvc_get_post_views( get_the_ID() ) . "" . __( "人看过", 'argon' );
		} else {
			$views = get_post_views( get_the_ID() );
		}
		return '<div class="post-meta-detail post-meta-detail-views">
					<i class="fa fa-eye" aria-hidden="true"></i> ' .
					$views .
				'</div>';
	}
	if ($type == 'comments'){
		return '<div class="post-meta-detail post-meta-detail-comments">
					<i class="fa fa-comments fa-comments-o" aria-hidden="true"></i> ' .
					get_post(get_the_ID()) -> comment_count .
				'</div>';
	}
	if ($type == 'categories'){
		$res = '<div class="post-meta-detail post-meta-detail-categories">
				<i class="fa fa-bookmark fa-bookmark-o" aria-hidden="true"></i> ';
		$categories = get_the_category();
		foreach ($categories as $index => $category){
			$res .= '<a href="' . get_category_link($category -> term_id) . '" target="_blank" class="post-meta-detail-catagory-link">' . $category -> cat_name . '</a>';
			if ($index != count($categories) - 1){
				$res .= '<span class="post-meta-detail-catagory-space">,</span>';
			}
		}
		$res .= '</div>';
		return $res;
	}
	if ($type == 'author'){
		$res = '<div class="post-meta-detail post-meta-detail-author">
					<i class="fa fa-circle-user fa-user-circle-o" aria-hidden="true"></i> ';
					global $authordata;
		$res .= '<a href="' . get_author_posts_url($authordata -> ID, $authordata -> user_nicename) . '" target="_blank">' . get_the_author() . '</a>
				</div>';
		return $res;
	}
}
//获取文章字数统计和预计阅读时间
function get_article_reading_time_meta($post_content_full){
	if (argon_html_has_document()) {
		$post_content_full = argon_html_document_text();
	}
	$post_content_full = apply_filters("argon_html_before_wordcount", $post_content_full);
	$words = get_article_words($post_content_full);
	$res = '</br><div class="post-meta-detail post-meta-detail-words">
		<i class="fa fa-file-word fa-file-word-o" aria-hidden="true"></i>';
	if ($words['code'] > 0){
		$res .= '<span title="' . sprintf(__( '包含 %d 行代码', 'argon'), $words['code']) . '">';
	}else{
		$res .= '<span>';
	}
	$res .= ' ' . get_article_words_total($post_content_full) . " " . __("字", 'argon');
	$res .= '</span>
		</div>
		<div class="post-meta-devide">|</div>
		<div class="post-meta-detail post-meta-detail-words">
			<i class="fa fa-hourglass-end" aria-hidden="true"></i>
			' . get_reading_time(get_article_words($post_content_full)) . '
		</div>
	';
	return $res;
}
//当前文章是否隐藏 阅读时间 Meta
function is_readingtime_meta_hidden(){
	if (strpos(get_the_content() , "[hide_reading_time][/hide_reading_time]") !== False){
		return true;
	}
	global $post;
	if (get_post_meta($post -> ID, 'argon_hide_readingtime', true) == 'true'){
		return true;
	}
	return false;
}
//当前文章是否隐藏 发布时间和分类 (简洁 Meta)
function is_meta_simple(){
	global $post;
	if (get_post_meta($post -> ID, 'argon_meta_simple', true) == 'true'){
		return true;
	}
	return false;
}
//根据文章 id 获取标题
function get_post_title_by_id($id){
	$post = get_post($id);
	return $post ? $post->post_title : '';
}
//获取顶部 Banner 背景图（用户指定或必应日图）
function get_banner_background_url(){
	$url = get_option("argon_banner_background_url");
	if ($url == "--bing--"){
		$lastUpdated = get_option("argon_bing_banner_background_last_updated_time");
		if ($lastUpdated == ""){
			$lastUpdated = 0;
		}
		$now = time();
		if ($now - $lastUpdated < 3600){
			return get_option("argon_bing_banner_background_last_updated_url");
		}else{
			$response = wp_safe_remote_get('https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1', array('timeout' => 5, 'limit_response_size' => 65536));
			$data = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);
			if (!isset($data['images'][0]['url']) || !is_string($data['images'][0]['url'])){
				return get_option('argon_bing_banner_background_last_updated_url', '');
			}
			$url = esc_url_raw('https://www.bing.com/' . ltrim($data['images'][0]['url'], '/'));
			update_option("argon_bing_banner_background_last_updated_time" , $now);
			update_option("argon_bing_banner_background_last_updated_url" , $url);
			return $url;
		}
	}else{
		return $url;
	}
}
//Lazyload 对 <img> 标签预处理以加载 Lazyload

function argon_is_external_url($url){
	if (empty($url) || $url[0] === '#'){
		return false;
	}
	return true;
}
function argon_format_click_count($count){
	if ($count >= 1000){
		$formatted = round($count / 1000, 1);
		return (floor($formatted) == $formatted ? number_format_i18n($formatted, 0) : number_format_i18n($formatted, 1)) . 'k';
	}
	return number_format_i18n($count);
}
function argon_external_link_clicks_get_client_key(){
	if (is_user_logged_in()){
		return 'user:' . get_current_user_id();
	}
	$client_id = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
	if (!empty($client_id)){
		return 'client:' . substr($client_id, 0, 128);
	}
	$ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
	$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
	return 'guest:' . $ip . '|' . $user_agent;
}
function argon_external_link_clicks_get_page_key(){
	$page_id = isset($_POST['page_id']) ? sanitize_text_field(wp_unslash($_POST['page_id'])) : '';
	if (!empty($page_id)){
		return substr($page_id, 0, 128);
	}
	return 'legacy';
}
function argon_external_link_clicks_cleanup_locks(){
	if (mt_rand(1, 100) !== 1){
		return;
	}
	global $wpdb;
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) <= %d",
			$wpdb->esc_like('argon_ext_click_lock_') . '%',
			time()
		)
	);
}
function argon_external_link_clicks_acquire_lock($post_id, $url){
	$ttl = 3600;
	$option_name = 'argon_ext_click_lock_' . md5($post_id . '|' . $url . '|' . argon_external_link_clicks_get_client_key() . '|' . argon_external_link_clicks_get_page_key());
	$now = time();
	$expires_at = intval(get_option($option_name, 0));
	if ($expires_at > $now){
		return false;
	}
	if ($expires_at > 0){
		delete_option($option_name);
	}
	argon_external_link_clicks_cleanup_locks();
	return add_option($option_name, $now + $ttl, '', 'no');
}
function argon_external_link_clicks_is_enabled($post_id = null){
	if (get_option('argon_enable_external_link_clicks', 'true') == 'false'){
		return false;
	}
	if ($post_id === null){
		global $post;
		if (!isset($post)){
			return false;
		}
		$post_id = $post->ID;
	}
	$per_post = get_post_meta($post_id, 'argon_enable_external_link_clicks', true);
	if ($per_post == 'false'){
		return false;
	}
	return true;
}
function argon_external_link_clicks_filter($content){
	if (!is_single() && !is_page()){
		return $content;
	}
	if (!in_the_loop() || !is_main_query()){
		return $content;
	}
	if (!argon_external_link_clicks_is_enabled()){
		return $content;
	}
	if (empty(trim($content)) || stripos($content, '<a') === false){
		return $content;
	}
	global $post;
	$post_id = $post->ID;
	$clicks_json = get_post_meta($post_id, 'argon_external_link_clicks', true);
	$clicks = !empty($clicks_json) ? json_decode($clicks_json, true) : array();
	if (!is_array($clicks)){
		$clicks = array();
	}
	// Edit only anchor attributes; retain core block markup, scripts and image hints byte-for-byte.
	$processor = new WP_HTML_Tag_Processor($content);
	while ($processor->next_tag('a')) {
		$href = $processor->get_attribute('href');
		if (!is_string($href) || !argon_is_external_url($href)) continue;
		$url_key = esc_url_raw($href);
		$count = isset($clicks[$url_key]) ? max(0, (int) $clicks[$url_key]) : 0;
		$processor->add_class('argon-external-link');
		$processor->set_attribute('data-argon-click', 'true');
		$processor->set_attribute('data-url', $url_key);
		$processor->set_attribute('data-click-nonce', wp_create_nonce('argon_external_link_' . $post_id . '_' . md5($url_key)));
		if ($count > 0) $processor->set_attribute('data-argon-click-count', argon_format_click_count($count));
	}
	return $processor->get_updated_html();
}
function argon_click_external_link(){
	if (!wp_verify_nonce(argon_request_string($_POST, '_wpnonce'), 'argon_external_link_clicks')){
		wp_send_json(array('status' => 'failed', 'msg' => 'nonce verification failed'), 403);
		return;
	}
	$post_id = argon_ajax_id('post_id');
	$url = esc_url_raw(argon_request_string($_POST, 'url'));
	if ($post_id <= 0 || empty($url)){
		wp_send_json(array('status' => 'failed', 'msg' => 'invalid parameters'));
		return;
	}
	if (!argon_can_view_post($post_id)){
		wp_send_json(array('status' => 'failed', 'msg' => 'post not found'));
		return;
	}
	if (!argon_external_link_clicks_is_enabled($post_id)){
		wp_send_json(array('status' => 'disabled'));
		return;
	}
	if (strlen($url) > 2048 || !wp_verify_nonce(argon_request_string($_POST, 'link_nonce'), 'argon_external_link_' . $post_id . '_' . md5($url))){
		wp_send_json(array('status' => 'failed', 'msg' => 'invalid link'), 403);
	}
	$clicks_json = get_post_meta($post_id, 'argon_external_link_clicks', true);
	$clicks = !empty($clicks_json) ? json_decode($clicks_json, true) : array();
	if (!is_array($clicks)){
		$clicks = array();
	}
	if (!isset($clicks[$url])){
		$clicks[$url] = 0;
	}
	if (!argon_external_link_clicks_acquire_lock($post_id, $url)){
		wp_send_json(array('status' => 'duplicate', 'count' => $clicks[$url]));
		return;
	}
	$clicks[$url]++;
	update_post_meta($post_id, 'argon_external_link_clicks', wp_json_encode($clicks));
	wp_send_json(array('status' => 'success', 'count' => $clicks[$url]));
}
add_action('wp_ajax_argon_click_external_link', 'argon_click_external_link');
add_action('wp_ajax_nopriv_argon_click_external_link', 'argon_click_external_link');

function the_content_filter($content){
	global $post;
	$custom_css = $post instanceof WP_Post ? get_post_meta($post->ID, 'argon_custom_css', true) : '';
	if (!empty($custom_css)){
		$content .= "<style>" . $custom_css . "</style>";
	}

	$content = argon_external_link_clicks_filter($content);

	return $content;
}
add_filter('the_content' , 'the_content_filter',20);
//文章过时信息显示
function argon_get_post_outdated_info(){
	global $post;
	$post_show_outdated_info_status = strval(get_post_meta($post -> ID, 'argon_show_post_outdated_info', true));
	if (get_option("argon_outdated_info_tip_type") == "toast"){
		$before = "<div id='post_outdate_toast' style='display:none;' data-text='";
		$after = "'></div>";
	}else{
		$before = "<div class='post-outdated-info'><i class='fa fa-info-circle' aria-hidden='true'></i>";
		$after = "</div>";
	}
	$content = get_option('argon_outdated_info_tip_content') == '' ? '本文最后更新于 %date_delta% 天前，其中的信息可能已经有所发展或是发生改变。' : get_option('argon_outdated_info_tip_content');
	$delta = get_option('argon_outdated_info_days') == '' ? (-1) : get_option('argon_outdated_info_days');
	if ($delta == -1){
		$delta = 2147483647;
	}
	$post_date_delta = floor((current_time('timestamp') - get_the_time("U")) / (60 * 60 * 24));
	$modify_date_delta = floor((current_time('timestamp') - get_the_modified_time("U")) / (60 * 60 * 24));
	if (get_option("argon_outdated_info_time_type") == "createdtime"){
		$date_delta = $post_date_delta;
	}else{
		$date_delta = $modify_date_delta;
	}
	if (($date_delta <= $delta && $post_show_outdated_info_status != 'always') || $post_show_outdated_info_status == 'never'){
		return "";
	}
	$content = str_replace("%date_delta%", $date_delta, $content);
	$content = str_replace("%modify_date_delta%", $modify_date_delta, $content);
	$content = str_replace("%post_date_delta%", $post_date_delta, $content);
	return $before . $content . $after;
}

function argon_get_ai_post_summary(): string {
	global $post;

	$title   = __( "由 AI 生成的文章摘要", 'agron' );
	$content = esc_html(get_post_meta( $post->ID, "argon_ai_summary", true ));


	return trim( strtr( '<div class="post-ai-summary">
			<div class="post-ai-summary_title">
				<i class="fa fa-android" aria-hidden="true"></i>
				<span>$title</span>
			</div>
			<div class="post-ai-summary_content">
				<span>$content</span>
			</div>
		</div>', array(
		'$title'  => $title,
		'$content' => $content,
	) ) );
}
