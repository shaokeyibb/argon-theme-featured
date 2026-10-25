<?php
if (!defined('ARGON_MEDIA_TESTING')) exit;
$results = array();
function media_assert($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function media_test(string $name, callable $callback): void {
	try { $callback(); $GLOBALS['results'][$name] = 'passed'; }
	catch (Throwable $error) { $GLOBALS['results'][$name] = get_class($error) . ': ' . $error->getMessage(); }
}
set_error_handler(static function($severity, $message, $file, $line) {
	if (str_contains($file, '/themes/argon-theme/') && (error_reporting() & $severity)) throw new ErrorException($message, 0, $severity, $file, $line);
	return false;
});
require __DIR__ . '/media-fixture.php';
$attachment_id = $media_fixture['attachment']; $url = $media_fixture['url']; $meta = $media_fixture['meta'];
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(array('page_id'=>$media_fixture['post']));
$GLOBALS['wp_query']->the_post();
// A frontend body context, without executing redirects or output-buffer handlers in CLI.
$GLOBALS['wp_actions']['template_redirect'] = 1;
remove_filter('wp_content_img_tag','webp_uploads_filter_image_tag',10);
add_filter('wp_content_img_tag','webp_uploads_wrap_image_in_picture',10,3);

media_test('real plugins generate modern files and dominant color metadata', function() use ($meta) {
	media_assert(isset($meta['sources']['image/webp']['file']), 'WebP full source not generated');
	media_assert(isset($meta['dominant_color']), 'Placeholder color not generated');
});
media_test('Markdown images reach core and both official image plugins', function() use ($attachment_id,$url) {
	$html=apply_filters('the_content','<p><img src="'.$url.'" alt="Markdown 中文"></p>');
	foreach (array('wp-image-'.$attachment_id,'width="1200"','height="675"','srcset=','sizes=','data-dominant-color=','<picture','<source type="image/webp"','argon-image-inset') as $expected) media_assert(str_contains($html,$expected), 'Missing '.$expected);
	media_assert(substr_count($html,'<picture')===1, 'Duplicate picture wrapper');
	media_assert(!str_contains($html,'data-original=') && !str_contains($html,'src="data:'), 'Native source hidden');
});
media_test('URL-only first images and featured attachments use the same hooks', function() use ($media_fixture) {
	foreach (array($media_fixture['post'],$media_fixture['featured']) as $post_id) {
		$html=argon_get_post_thumbnail_image($post_id,'large');
		foreach(array('<picture','<source type="image/webp"','srcset=','sizes=','data-dominant-color=') as $expected) media_assert(str_contains($html,$expected),'Missing '.$expected);
	}
});
media_test('first image discovery preserves order across Markdown and native blocks', function() use ($attachment_id,$url) {
	$post_id=wp_insert_post(array('post_title'=>'Mixed first image','post_content'=>'<p><img src="https://example.org/first.jpg"></p><img class="wp-image-'.$attachment_id.'" src="'.$url.'">','post_status'=>'draft'));
	media_assert(argon_get_first_image_of_article($post_id)==='https://example.org/first.jpg','Later attachment replaced the first image');
});
media_test('every thumbnail layout preserves picture sources and real src', function() use ($media_fixture) {
	$GLOBALS['post']=get_post($media_fixture['featured']);
	foreach(array('template-parts/entry/header-1.php','template-parts/entry/header-3.php','template-parts/preview/content-preview-2.php') as $part) {
		ob_start(); include get_template_directory().'/'.$part; $html=ob_get_clean();
		$tags=new WP_HTML_Tag_Processor($html); media_assert($tags->next_tag('SOURCE') && is_string($tags->get_attribute('srcset')),'Source candidates lost in '.$part);
		media_assert(!str_contains($html,'data-original=') && !str_contains($html,'opacity: 0;'),'Legacy lazy loading in '.$part);
	}
});
media_test('modern replacement works with picture mode disabled', function() use ($url) {
	update_option('webp_uploads_use_picture_element','0');
	remove_filter('wp_content_img_tag','webp_uploads_wrap_image_in_picture',10); add_filter('wp_content_img_tag','webp_uploads_filter_image_tag',10,3);
	try {
		$html=apply_filters('the_content','<img src="'.$url.'" alt="Plain replacement">');
		$tag=new WP_HTML_Tag_Processor($html);$tag->next_tag('IMG');
		media_assert(str_ends_with($tag->get_attribute('src'),'.webp') && !str_contains($html,'<picture'),'Plain image not converted');
	} finally {
		update_option('webp_uploads_use_picture_element','1'); remove_filter('wp_content_img_tag','webp_uploads_filter_image_tag',10); add_filter('wp_content_img_tag','webp_uploads_wrap_image_in_picture',10,3);
	}
});
media_test('existing picture, external images and script text are untouched', function() use ($url) {
	$html='<picture><source srcset="https://example.com/art.webp"><img src="'.$url.'"></picture><img src="https://example.com/external.jpg"><script>const text="<img src=\"'.$url.'\">";</script>';
	media_assert(argon_prepare_content_images($html)===$html,'Authored markup changed');
});
media_test('explicit image attributes and core block identities survive', function() use ($attachment_id,$url) {
	$html='<img src="'.$url.'" class="wp-image-'.$attachment_id.'" width="300" height="169" sizes="300px" loading="eager" fetchpriority="high" data-wp-interactive="core/image">';
	media_assert(argon_prepare_content_images($html)===$html,'Existing core image changed');
	$html='<img src="'.$url.'" width="300" height="169" loading="eager" fetchpriority="high">';
	$result=argon_prepare_content_images($html);
	foreach(array('width="300"','height="169"','loading="eager"','fetchpriority="high"') as $attribute) media_assert(str_contains($result,$attribute),'Explicit attribute lost');
	media_assert(!str_contains($result,'argon-image-inset'),'Explicitly sized image inset');
	foreach (array('', ' class="wp-image-'.$attachment_id.'"') as $identity) {
		$html='<img src="'.$url.'"'.$identity.' height="300">';
		$result=argon_prepare_content_images($html);
		media_assert(!str_contains($result,'argon-image-inset') && str_contains($result,'height="300"'),'Authored height-only image inset');
	}
	$html='<p><img src="'.$url.'" class="wp-image-'.$attachment_id.'" alt="Existing attachment"></p>';
	$result=argon_prepare_content_images($html);
	media_assert(str_contains($result,'argon-image-inset'),'Existing attachment without authored width lost inset');
	media_assert(argon_prepare_content_images($result)===$result,'Layout hint is not idempotent');
});
media_test('external thumbnail attributes are escaped exactly once', function() {
	$url='https://example.com/image.jpg?a=1&b=2';
	$html=argon_get_image_from_url($url,'full',array('alt'=>'A "quote" & image','loading'=>'eager','fetchpriority'=>'high'));
	$tag=new WP_HTML_Tag_Processor($html);$tag->next_tag('IMG');
	media_assert($tag->get_attribute('src')===$url && $tag->get_attribute('alt')==='A "quote" & image','Attribute encoding changed');
	media_assert($tag->get_attribute('loading')==='eager' && $tag->get_attribute('fetchpriority')==='high','Native loading hint lost');
});
media_test('original and registered resized URLs resolve without filename guessing', function() use ($attachment_id,$url,$meta) {
	$directory=dirname($url).'/';
	media_assert(argon_image_attachment_id($url.'?ver=1#image')===$attachment_id,'Query URL not resolved');
	media_assert(argon_image_attachment_id(wp_parse_url($url,PHP_URL_PATH))===$attachment_id,'Root relative URL not resolved');
	foreach($meta['sizes'] as $size) {
		media_assert(argon_image_attachment_id($directory.$size['file'])===$attachment_id,'Registered subsize not resolved');
		foreach($size['sources']??array() as $source) media_assert(argon_image_attachment_id($directory.$source['file'])===$attachment_id,'Modern subsize not resolved');
	}
	media_assert(argon_image_attachment_id(str_replace('.png','-321x123.png',$url))===0,'Invented subsize matched');
	media_assert(argon_image_attachment_id(str_replace('http://','http://unrelated.example/',$url))===0,'External source matched');
});
media_test('repeated URL resolution uses the object cache', function() use ($attachment_id,$url) {
	argon_image_attachment_id($url); global $wpdb; $before=$wpdb->num_queries;
	for($i=0;$i<10;$i++) media_assert(argon_image_attachment_id($url)===$attachment_id,'Cached identity missing');
	media_assert($wpdb->num_queries===$before,'Repeated attachment queries');
});
media_test('user lazy-loading opt-out survives plugin wrapping', function() use ($media_fixture) {
	update_option('argon_enable_lazyload','false');
	try { media_assert(!str_contains(argon_get_post_thumbnail_image($media_fixture['post'],'large'),'loading="lazy"'),'Lazy opt-out ignored'); }
	finally { update_option('argon_enable_lazyload','true'); }
});
media_test('native responsive images still work without image optimization filters', function() use ($url) {
	remove_filter('wp_content_img_tag','webp_uploads_wrap_image_in_picture',10);
	remove_filter('wp_content_img_tag','dominant_color_img_tag_add_dominant_color',20);
	try {
		$html=apply_filters('the_content','<img src="'.$url.'" alt="Core image">');
		media_assert(str_contains($html,'srcset=') && str_contains($html,'width="1200"') && !str_contains($html,'<picture'),'Native fallback lost');
	} finally {
		add_filter('wp_content_img_tag','webp_uploads_wrap_image_in_picture',10,3);
		add_filter('wp_content_img_tag','dominant_color_img_tag_add_dominant_color',20,3);
	}
});
media_test('banner media uses picture sources with native eager loading', function() use ($media_fixture) {
	$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query(array('page_id'=>$media_fixture['featured'])); $GLOBALS['wp_query']->the_post();
	update_option('argon_show_thumbnail_in_banner_in_content_page','true');
	$html=argon_get_banner_image();
	foreach(array('<picture','srcset=','argon-banner-image','loading="eager"','sizes="100vw"') as $expected) media_assert(str_contains($html,$expected),'Missing '.$expected);
	update_option('argon_show_thumbnail_in_banner_in_content_page','false');
});
restore_error_handler();
$failed=array_filter($results,static fn($result)=>$result!=='passed');
$report=array('php'=>PHP_VERSION,'wordpress'=>$GLOBALS['wp_version'],'passed'=>count($results)-count($failed),'failed'=>count($failed),'results'=>$results);
$report_dir = dirname(__DIR__) . '/temp';
if (!wp_mkdir_p($report_dir) || file_put_contents($report_dir . '/media-report-' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.json', wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
	throw new RuntimeException('Could not write media report');
}
echo wp_json_encode($report);
if($failed) throw new RuntimeException('Media regressions: '.wp_json_encode($failed));
