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
$media_fixture['archive_posts']=array();
for($n=1;$n<=3;$n++) {
	$post_id=wp_insert_post(wp_slash(array('post_title'=>'Cover '.$n,'post_status'=>'publish','post_content'=>'<p><img src="'.esc_url($media_fixture['url']).'" alt="First image"></p><p>Archive cover remains visible after loading.</p>','post_date'=>date('Y-m-d H:i:s',time()-$n))));
	if($n>1) set_post_thumbnail($post_id,$media_fixture['attachment']);
	$media_fixture['archive_posts'][]=$post_id;
}
$fixture_dir = dirname(__DIR__) . '/temp';
if (!wp_mkdir_p($fixture_dir) || file_put_contents($fixture_dir . '/media-browser-fixture.json', wp_json_encode($media_fixture)) === false) {
	throw new RuntimeException('Could not write media browser fixture data');
}
flush_rewrite_rules();
