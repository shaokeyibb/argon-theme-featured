<?php
if (!defined('ARGON_MEDIA_TESTING')) exit;
wp_set_current_user(1);
update_option('argon_disable_googlefont','true');
update_option('argon_ai_post_summary','false');
update_option('argon_article_list_layout','2');
update_option('argon_enable_lightbox','true');
update_option('argon_show_thumbnail_in_banner_in_content_page','true');
update_option('permalink_structure','/%postname%/');
require __DIR__.'/media-fixture.php';
$markdown = "[collapse title=\"Markdown container\"]\n## Markdown heading\n\n**Bold text** and [example](https://example.test/)\n\n- First item\n- Second item\n\n[admonition title=\"Nested container\"]\n*Nested emphasis*\n[/admonition]\n\n```js\nconst example = '[label]code sample[/label]';\n```\n\n![Nested media](" . $media_fixture['url'] . ")\n[/collapse]";
$controller = new \Githuber\Controller\Markdown();
$media_fixture['markdown_shortcodes'] = wp_insert_post(wp_slash(array('post_type'=>'page','post_name'=>'markdown-shortcodes','post_title'=>'Markdown shortcodes','post_status'=>'publish',
	'post_content'=>$controller->transform($markdown,array('unslash'=>false)),'post_content_filtered'=>$markdown,
	'meta_input'=>array('_is_githuber_markdown'=>'1','_is_githuber_markdown_enabled'=>'yes'))));
$media_fixture['archive_posts']=array();
for($n=1;$n<=3;$n++) {
	$post_id=wp_insert_post(wp_slash(array('post_title'=>'Cover '.$n,'post_status'=>'publish','post_content'=>'<p><img src="'.esc_url($media_fixture['url']).'" alt="First image"></p><p>Archive cover remains visible after loading.</p>','post_date'=>date('Y-m-d H:i:s',time()-$n))));
	if($n>1) set_post_thumbnail($post_id,$media_fixture['attachment']);
	$media_fixture['archive_posts'][]=$post_id;
}
// Exercise real picture generation with portrait media in both content paths.
$portrait = imagecreatetruecolor(600, 1800);
for ($y = 0; $y < 1800; $y += 100) {
	imagefilledrectangle($portrait, 0, $y, 599, $y + 99, imagecolorallocate($portrait, 40 + $y % 180, 100, 170));
}
ob_start(); imagepng($portrait); $portrait_bytes = ob_get_clean();
$portrait_upload = wp_upload_bits('argon-portrait-fixture.png', null, $portrait_bytes);
if ($portrait_upload['error']) throw new RuntimeException($portrait_upload['error']);
$portrait_id = wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Portrait fixture','post_status'=>'inherit','guid'=>$portrait_upload['url']), $portrait_upload['file']);
add_filter('webp_uploads_discard_larger_generated_images', '__return_false');
wp_update_attachment_metadata($portrait_id, wp_generate_attachment_metadata($portrait_id, $portrait_upload['file']));
remove_filter('webp_uploads_discard_larger_generated_images', '__return_false');
$portrait_url = esc_url(wp_get_attachment_url($portrait_id));
$portrait_content = '<p><img id="portrait-plain" src="'.$portrait_url.'" alt="Portrait without dimensions"></p>'
	. '<p><a href="'.$portrait_url.'"><img id="portrait-linked" class="wp-image-'.$portrait_id.'" src="'.$portrait_url.'" alt="Existing attachment identity"></a></p>'
	. '<p><picture><source srcset="'.$portrait_url.'"><img id="portrait-authored-picture" src="'.$portrait_url.'" alt="Authored picture"></picture></p>'
	. '<p><img id="portrait-sized" src="'.$portrait_url.'" width="100" height="300" alt="Author sized image"></p>'
	. '<p><img id="portrait-inline-sized" src="'.$portrait_url.'" style="width:300px;height:900px" alt="Author inline size"></p>'
	. '<!-- wp:image {"id":'.$portrait_id.',"lightbox":{"enabled":true}} --><figure class="wp-block-image"><img id="portrait-block" src="'.$portrait_url.'" width="600" height="1800" class="wp-image-'.$portrait_id.'" alt="Native image block"></figure><!-- /wp:image -->';
foreach (array('page', 'shuoshuo') as $type) {
	$id = wp_insert_post(wp_slash(array('post_type'=>$type,'post_title'=>'Portrait '.$type,'post_status'=>'publish','post_content'=>$portrait_content,'post_date'=>date('Y-m-d H:i:s',time()-60))));
	$media_fixture['portrait_'.$type] = get_permalink($id);
}
update_option('argon_home_show_shuoshuo', 'true');
$landscape_url = esc_url($media_fixture['url']);
$layout_content = '<h2>Single long image</h2><p><img id="layout-single" src="'.$portrait_url.'" alt="Single long image"></p>'
	. '<h2>Consecutive mixed images</h2><p><img id="layout-grid-1" src="'.$landscape_url.'" alt="Landscape one"></p>'
	. '<p><a href="'.$portrait_url.'"><img id="layout-grid-2" src="'.$portrait_url.'" alt="Long image two"></a></p>'
	. '<p><picture><source srcset="'.$landscape_url.'"><img id="layout-grid-3" src="'.$landscape_url.'" alt="Landscape three"></picture><br> <img id="layout-grid-4" src="'.$landscape_url.'" alt="Landscape four"></p>'
	. '<p id="layout-text">Text between image groups must stay here.</p>'
	. '<p><a id="layout-external-link" href="https://example.test/article"><img id="layout-external" src="'.$landscape_url.'" alt="An image linking to an article"></a></p>'
	. '<p id="layout-caption"><img id="layout-captioned" src="'.$landscape_url.'" alt="Captioned image"> A caption in the same paragraph.</p>'
	. '<p><img id="layout-sized" src="'.$portrait_url.'" width="100" height="300" alt="Authored size"></p>'
	. '<p><img id="layout-height" src="'.$portrait_url.'" height="300" alt="Authored height"></p>'
	. '<p><img id="layout-inline" src="'.$portrait_url.'" style="width:100px;height:300px" alt="Authored inline size"></p>'
	. '<!-- wp:image {"id":'.$portrait_id.'} --><figure class="wp-block-image"><img id="layout-block" src="'.$portrait_url.'" width="600" height="1800" class="wp-image-'.$portrait_id.'" alt="Native block"></figure><!-- /wp:image -->'
	. '<div class="layout-shortcode"><p><img id="layout-shortcode-image" src="'.$portrait_url.'" alt="Custom container"></p></div>'
	. '<h2>Delayed image</h2><p><img id="layout-delayed" src="/layout-delayed.svg" loading="lazy" alt="Delayed long image"></p>';
$media_fixture['layout_page'] = get_permalink(wp_insert_post(wp_slash(array('post_type'=>'page','post_title'=>'Image layout','post_status'=>'publish','post_content'=>$layout_content))));
$fixture_dir = dirname(__DIR__) . '/temp';
if (!wp_mkdir_p($fixture_dir) || file_put_contents($fixture_dir . '/media-browser-fixture.json', wp_json_encode($media_fixture)) === false) {
	throw new RuntimeException('Could not write media browser fixture data');
}
flush_rewrite_rules();
