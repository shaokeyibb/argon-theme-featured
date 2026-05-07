<?php
//文章特色图片
function argon_get_first_image_of_article(){
	global $post;
	if (post_password_required()){
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
	
	// 方法2：如果无法获取附件ID，使用处理后的内容提取URL
	// 注意：这里使用 'the_content' 过滤器，会触发图片优化插件
	$post_content_full = apply_filters('the_content', preg_replace( '<!--more(.*?)-->', '', $content));
	
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
	if (has_post_thumbnail()){
		return true;
	}
	$argon_first_image_as_thumbnail = get_post_meta($postID, 'argon_first_image_as_thumbnail', true);
	if ($argon_first_image_as_thumbnail == ""){
		$argon_first_image_as_thumbnail = "default";
	}
	if ($argon_first_image_as_thumbnail == "true" || ($argon_first_image_as_thumbnail == "default" && get_option("argon_first_image_as_thumbnail_by_default", "false") == "true")){
		if (argon_get_first_image_of_article() != false){
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
	if (has_post_thumbnail()){
		// 获取附件 ID
		$attachment_id = get_post_thumbnail_id($postID);
		// 使用 wp_get_attachment_image_src 获取图片信息，这会触发相关的过滤器
		$image_data = wp_get_attachment_image_src($attachment_id, "full");
		// 返回 URL（已经过 wp_get_attachment_image_src 过滤器处理）
		$url = $image_data ? $image_data[0] : '';
		return apply_filters("argon_post_thumbnail", $url, $postID, $attachment_id);
	}
	return apply_filters("argon_post_thumbnail", argon_get_first_image_of_article());
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
	$first_image_url = argon_get_first_image_of_article();
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
	$count_key = 'views';
	$count = get_post_meta($post_id, $count_key, true);
	if ($count==''){
		delete_post_meta($post_id, $count_key);
		add_post_meta($post_id, $count_key, '0');
		$count = '0';
	}
	return number_format_i18n($count);
}
function set_post_views(){
	if (!is_single() && !is_page()) {
		return;
	}
	if (!isset($post_id)){
		global $post;
		$post_id = $post -> ID;
	}
	if (post_password_required($post_id)){
		return;
	}
	if (isset($_GET['preview'])){
		if ($_GET['preview'] == 'true'){
			if (current_user_can('publish_posts')){
				return;
			}
		}
	}
	$noPostView = 'false';
	if (isset($_POST['no_post_view'])){
		$noPostView = $_POST['no_post_view'];
	}
	if ($noPostView == 'true'){
		return;
	}
	global $post;
	if (!isset($post -> ID)){
		return;
	}
	$post_id = $post -> ID;
	$count_key = 'views';
	$count = get_post_meta($post_id, $count_key, true);
	if (is_single() || is_page()) {
		if ($count==''){
			delete_post_meta($post_id, $count_key);
			add_post_meta($post_id, $count_key, '0');
		} else {
			update_post_meta($post_id, $count_key, $count + 1);
		}
	}
}
add_action('get_header', 'set_post_views');
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
	return get_post($id) -> post_title;
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
			$data = json_decode(@file_get_contents('https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1') , true);
			$url = "//bing.com" . $data['images'][0]['url'];
			update_option("argon_bing_banner_background_last_updated_time" , $now);
			update_option("argon_bing_banner_background_last_updated_url" , $url);
			return $url;
		}
	}else{
		return $url;
	}
}
//Lazyload 对 <img> 标签预处理以加载 Lazyload
function argon_lazyload($content){
	// 如果启用了 fancybox，lazyload 的处理会在 argon_fancybox 中一起完成
	// 这样可以避免 DOM 操作冲突
	if (get_option('argon_enable_fancybox') != 'false' && get_option('argon_enable_zoomify') == 'false'){
		return $content;
	}
	
	$lazyload_loading_style = get_option('argon_lazyload_loading_style');
	if ($lazyload_loading_style == ''){
		$lazyload_loading_style = 'none';
	}
	$lazyload_loading_style = "lazyload-style-" . $lazyload_loading_style;

	if(!is_feed() && !is_robots() && !is_home()){
		$content = preg_replace('/<img(.*?)src=[\'"](.*?)[\'"](.*?)((\/>)|(<\/img>))/i',"<img class=\"lazyload " . $lazyload_loading_style . "\" src=\"data:image/svg+xml;base64,PCEtLUFyZ29uTG9hZGluZy0tPgo8c3ZnIHdpZHRoPSIxIiBoZWlnaHQ9IjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgc3Ryb2tlPSIjZmZmZmZmMDAiPjxnPjwvZz4KPC9zdmc+\" \$1data-original=\"\$2\" src=\"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsQAAA7EAZUrDhsAAAANSURBVBhXYzh8+PB/AAffA0nNPuCLAAAAAElFTkSuQmCC\"\$3$4" , $content);
		$content = preg_replace('/<img(.*?)data-full-url=[\'"]([^\'"]+)[\'"](.*)>/i',"<img$1data-full-url=\"$2\" data-original=\"$2\"$3>" , $content);
		$content = preg_replace('/<img(.*?)srcset=[\'"](.*?)[\'"](.*?)>/i',"<img$1$3>" , $content);
	}
	return $content;
}
function argon_fancybox($content){
	// 如果内容为空，直接返回，避免 DOMDocument::loadHTML() 报错
	if (empty(trim($content))) {
		return $content;
	}
	
	if(!is_feed() && !is_robots() && !is_home()){
		// 使用 DOMDocument 来更精确地处理 HTML 结构
		libxml_use_internal_errors(true); // 忽略HTML5标签的警告
		$dom = new DOMDocument();
		
		// 将内容包装在一个临时容器中，避免 DOMDocument 将第一个元素作为根节点
		// 这样可以保持原有的 HTML 结构层次
		$wrapped_content = '<div class="argon-temp-wrapper">' . $content . '</div>';
		
		$dom->loadHTML(mb_convert_encoding($wrapped_content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		
		$images = $dom->getElementsByTagName('img');
		$is_lazyload = (get_option('argon_enable_lazyload') != 'false');
		
		// 获取 lazyload 加载样式
		$lazyload_loading_style = '';
		if ($is_lazyload) {
			$lazyload_loading_style = get_option('argon_lazyload_loading_style');
			if ($lazyload_loading_style == ''){
				$lazyload_loading_style = 'none';
			}
			$lazyload_loading_style = "lazyload-style-" . $lazyload_loading_style;
		}
		
		// 需要从后向前处理，避免修改DOM时索引变化
		$images_array = array();
		foreach ($images as $img) {
			$images_array[] = $img;
		}
		
		foreach ($images_array as $img) {
			// 检查图片是否已经被 fancybox-wrapper 包裹
			if ($img->parentNode && $img->parentNode->getAttribute('class') && 
			    strpos($img->parentNode->getAttribute('class'), 'fancybox-wrapper') !== false) {
				continue;
			}

			$should_skip_fancybox = false;
			$skip_keywords = array('friend-link-avatar', 'no-fancybox');
			$parent_skip_keywords = array('friend-links', 'friend-links-simple', 'friend-link-container', 'friend-link-avatar', 'friend-link-content');

			if ($img->hasAttribute('data-no-fancybox') && $img->getAttribute('data-no-fancybox') !== 'false') {
				$should_skip_fancybox = true;
			}

			if (!$should_skip_fancybox) {
				$img_class_attr = $img->getAttribute('class');
				if (!empty($img_class_attr)) {
					foreach ($skip_keywords as $keyword) {
						if (strpos(' ' . $img_class_attr . ' ', ' ' . $keyword . ' ') !== false) {
							$should_skip_fancybox = true;
							break;
						}
					}
				}
			}

			if (!$should_skip_fancybox) {
				$ancestor = $img->parentNode;
				while ($ancestor && $ancestor->nodeType === XML_ELEMENT_NODE) {
					if ($ancestor->hasAttribute('data-no-fancybox') && $ancestor->getAttribute('data-no-fancybox') !== 'false') {
						$should_skip_fancybox = true;
						break;
					}
					$ancestor_class_attr = $ancestor->getAttribute('class');
					if (!empty($ancestor_class_attr)) {
						foreach ($parent_skip_keywords as $keyword) {
							if (strpos(' ' . $ancestor_class_attr . ' ', ' ' . $keyword . ' ') !== false) {
								$should_skip_fancybox = true;
								break 2;
							}
						}
					}
					$ancestor = $ancestor->parentNode;
				}
			}

			if ($should_skip_fancybox) {
				continue;
			}
			
			// 获取图片URL
			$img_url = $img->getAttribute('src');
			
			// 处理 data-full-url 属性（高清图）
			if ($img->hasAttribute('data-full-url')) {
				$data_full_url = $img->getAttribute('data-full-url');
				if (!empty($data_full_url)) {
					if ($is_lazyload && !$img->hasAttribute('data-original')) {
						$img->setAttribute('data-original', $data_full_url);
					}
				}
			}
			
			if (!empty($img_url) && strpos($img_url, 'data:image') === false) {
				// 如果启用了 lazyload，处理图片属性
				if ($is_lazyload) {
					// 获取现有的 class 属性
					$existing_classes = $img->getAttribute('class');
					$classes_array = array_filter(explode(' ', $existing_classes));
					
					// 添加 lazyload 相关的类（如果还没有）
					if (!in_array('lazyload', $classes_array)) {
						$classes_array[] = 'lazyload';
					}
					if (!in_array($lazyload_loading_style, $classes_array)) {
						$classes_array[] = $lazyload_loading_style;
					}
					
					// 设置新的 class 属性
					$img->setAttribute('class', implode(' ', $classes_array));
					
					// 设置 data-original 属性
					if (!$img->hasAttribute('data-original')) {
						$img->setAttribute('data-original', $img_url);
					}
					
					// 替换 src 为占位图（使用 SVG 占位图以触发 CSS 动画）
					$img->setAttribute('src', 'data:image/svg+xml;base64,PCEtLUFyZ29uTG9hZGluZy0tPgo8c3ZnIHdpZHRoPSIxIiBoZWlnaHQ9IjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgc3Ryb2tlPSIjZmZmZmZmMDAiPjxnPjwvZz4KPC9zdmc+');
					
					// 移除 srcset 属性（如果有）
					if ($img->hasAttribute('srcset')) {
						$img->removeAttribute('srcset');
					}
				}
				
				// 获取图片URL（用于 fancybox）
				$fancybox_url = $is_lazyload ? $img->getAttribute('data-original') : $img_url;
				
				// 创建 fancybox wrapper div
				$wrapper = $dom->createElement('div');
				$wrapper_class = $is_lazyload ? 'fancybox-wrapper lazyload-container-unload' : 'fancybox-wrapper';
				$wrapper->setAttribute('class', $wrapper_class);
				$wrapper->setAttribute('data-fancybox', 'post-images');
				$wrapper->setAttribute('href', $fancybox_url);
				
				// 将图片插入到wrapper中
				$img->parentNode->insertBefore($wrapper, $img);
				$wrapper->appendChild($img);
			}
		}
		
		// 提取包装容器内的内容，移除临时包装 div
		// 使用 XPath 查找包装容器，更精确
		$xpath = new DOMXPath($dom);
		$wrapper = $xpath->query("//div[@class='argon-temp-wrapper']")->item(0);
		
		if ($wrapper) {
			$content = '';
			foreach ($wrapper->childNodes as $child) {
				$content .= $dom->saveHTML($child);
			}
		} else {
			// 如果找不到包装容器，尝试从文档根元素提取（向后兼容）
			$body = $dom->documentElement;
			if ($body) {
				$content = '';
				foreach ($body->childNodes as $child) {
					$content .= $dom->saveHTML($child);
				}
			} else {
				// 最后的回退方案
				$content = $dom->saveHTML();
			}
		}
		
		libxml_use_internal_errors(false);
	}
	return $content;
}
//外链点击量统计
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
	if (empty(trim($content))){
		return $content;
	}
	global $post;
	$post_id = $post->ID;
	$clicks_json = get_post_meta($post_id, 'argon_external_link_clicks', true);
	$clicks = !empty($clicks_json) ? json_decode($clicks_json, true) : array();
	if (!is_array($clicks)){
		$clicks = array();
	}
	libxml_use_internal_errors(true);
	$dom = new DOMDocument();
	$wrapped_content = '<div class="argon-temp-wrapper">' . $content . '</div>';
	$dom->loadHTML(mb_convert_encoding($wrapped_content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	libxml_clear_errors();
	$anchors = $dom->getElementsByTagName('a');
	$anchors_array = array();
	foreach ($anchors as $anchor){
		$anchors_array[] = $anchor;
	}
	$modified = false;
	foreach ($anchors_array as $anchor){
		$href = $anchor->getAttribute('href');
		if (empty($href) || !argon_is_external_url($href)){
			continue;
		}
		$url_key = esc_url_raw($href);
		$count = isset($clicks[$url_key]) ? intval($clicks[$url_key]) : 0;
		$wrapper = $dom->createElement('span');
		$wrapper->setAttribute('class', 'external-link-click-wrapper');
		$anchor->parentNode->replaceChild($wrapper, $anchor);
		$wrapper->appendChild($anchor);
		$anchor->setAttribute('data-url', $url_key);
		$anchor->setAttribute('no-pjax', '');
		if ($count > 0){
			$badge = $dom->createElement('span', argon_format_click_count($count));
			$badge->setAttribute('class', 'external-link-click-badge');
			$badge->setAttribute('data-count', $count);
			$wrapper->appendChild($badge);
		}
		$modified = true;
	}
	if (!$modified){
		libxml_use_internal_errors(false);
		return $content;
	}
	$xpath = new DOMXPath($dom);
	$wrapper_node = $xpath->query("//div[@class='argon-temp-wrapper']")->item(0);
	if ($wrapper_node){
		$result = '';
		foreach ($wrapper_node->childNodes as $child){
			$result .= $dom->saveHTML($child);
		}
	}else{
		$result = $content;
	}
	libxml_use_internal_errors(false);
	return $result;
}
function argon_click_external_link(){
	if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'argon_external_link_clicks')){
		wp_send_json(array('status' => 'failed', 'msg' => 'nonce verification failed'), 403);
		return;
	}
	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
	$url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
	if ($post_id <= 0 || empty($url)){
		wp_send_json(array('status' => 'failed', 'msg' => 'invalid parameters'));
		return;
	}
	if (!get_post($post_id)){
		wp_send_json(array('status' => 'failed', 'msg' => 'post not found'));
		return;
	}
	if (!argon_external_link_clicks_is_enabled($post_id)){
		wp_send_json(array('status' => 'disabled'));
		return;
	}
	$clicks_json = get_post_meta($post_id, 'argon_external_link_clicks', true);
	$clicks = !empty($clicks_json) ? json_decode($clicks_json, true) : array();
	if (!is_array($clicks)){
		$clicks = array();
	}
	if (!isset($clicks[$url])){
		$clicks[$url] = 0;
	}
	$clicks[$url]++;
	update_post_meta($post_id, 'argon_external_link_clicks', wp_json_encode($clicks));
	wp_send_json(array('status' => 'success', 'count' => $clicks[$url]));
}
add_action('wp_ajax_argon_click_external_link', 'argon_click_external_link');
add_action('wp_ajax_nopriv_argon_click_external_link', 'argon_click_external_link');

function the_content_filter($content){
	if (get_option('argon_enable_lazyload') != 'false'){
		$content = argon_lazyload($content);
	}
	if (get_option('argon_enable_fancybox') != 'false' && get_option('argon_enable_zoomify') == 'false'){
		$content = argon_fancybox($content);
	}
	global $post;
	$custom_css = get_post_meta($post -> ID, 'argon_custom_css', true);
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

