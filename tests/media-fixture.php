<?php
/** Shared local fixture using real WordPress image generation and plugin hooks. */
if (!defined('ARGON_MEDIA_TESTING')) exit;
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
update_option('perflab_modern_image_format', 'webp');
update_option('perflab_generate_webp_and_jpeg', '1');
update_option('webp_uploads_use_picture_element', '1');
update_option('argon_enable_lazyload', 'true');
update_option('argon_first_image_as_thumbnail_by_default', 'true');
$image = imagecreatetruecolor(1200, 675);
for ($x = 0; $x < 1200; $x++) {
	for ($y = 0; $y < 675; $y += 3) {
		$color = imagecolorallocate($image, ($x + $y) % 256, (int) ($x / 5) % 256, (int) ($y / 3) % 256);
		imageline($image, $x, $y, $x, min($y + 2, 674), $color);
	}
}
ob_start(); imagepng($image); $bytes = ob_get_clean();
$upload = wp_upload_bits('argon-media-fixture.png', null, $bytes);
if ($upload['error']) throw new RuntimeException($upload['error']);
$attachment = wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Media fixture','post_status'=>'inherit','guid'=>$upload['url']),$upload['file']);
// These synthetic pixels can compress unusually well as PNG. Keep both formats
// so compatibility assertions do not depend on the image encoder's size heuristic.
add_filter('webp_uploads_discard_larger_generated_images', '__return_false');
wp_update_attachment_metadata($attachment, wp_generate_attachment_metadata($attachment, $upload['file']));
remove_filter('webp_uploads_discard_larger_generated_images', '__return_false');
$url = wp_get_attachment_url($attachment);
$content = '<h2>Markdown media</h2><p><img id="markdown-media" src="' . esc_url($url) . '" alt="Markdown image"></p><p><a href="' . esc_url($url) . '"><img id="linked-media" src="' . esc_url($url) . '" alt="Linked image"></a></p>';
$content .= '<!-- wp:image {"id":' . $attachment . ',"sizeSlug":"large","lightbox":{"enabled":true}} --><figure class="wp-block-image size-large"><img src="' . esc_url($url) . '" alt="Native image" class="wp-image-' . $attachment . '" width="1200" height="675" /></figure><!-- /wp:image -->';
$content .= '<p><img id="external-media" src="https://example.com/external.jpg" alt="External image"></p>';
$content .= '<p><img id="legacy-media" class="post-thumbnail lazyload" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-original="'.esc_url($url).'" style="opacity: 0;" loading="eager" fetchpriority="high" alt="Legacy thumbnail"></p>';
$post_id = wp_insert_post(wp_slash(array('post_type'=>'page','post_title'=>'Media compatibility','post_status'=>'publish','post_content'=>$content)));
$featured_id = wp_insert_post(wp_slash(array('post_type'=>'page','post_title'=>'Featured media','post_status'=>'publish','post_content'=>'<p>Featured image</p>')));
set_post_thumbnail($featured_id,$attachment);
$media_fixture = array('attachment'=>$attachment,'post'=>$post_id,'featured'=>$featured_id,'url'=>$url,'meta'=>wp_get_attachment_metadata($attachment));
