<?php
/** Article sharing: plain links work without third-party SDKs or JavaScript. */
$share_title = html_entity_decode(wp_strip_all_tags(get_the_title()), ENT_QUOTES, get_bloginfo('charset'));
$share_url = get_permalink();
$share_text = $share_title . ' ' . $share_url;
$share_mode = get_option('argon_show_sharebtn');
$share_destinations = array(
    'weibo' => array(__('分享到微博', 'argon'), 'weibo', 'https://service.weibo.com/share/share.php', array('url' => $share_url, 'title' => $share_title), 'domestic'),
    'x' => array(__('分享到 X', 'argon'), 'x-twitter', 'https://x.com/intent/tweet', array('url' => $share_url, 'text' => $share_title), 'abroad'),
    'telegram' => array(__('分享到 Telegram', 'argon'), 'telegram', 'https://t.me/share/url', array('url' => $share_url, 'text' => $share_title), 'abroad'),
    'whatsapp' => array(__('分享到 WhatsApp', 'argon'), 'whatsapp', 'https://wa.me/', array('text' => $share_text), 'abroad'),
    'facebook' => array(__('分享到 Facebook', 'argon'), 'facebook-f', 'https://www.facebook.com/sharer/sharer.php', array('u' => $share_url), 'abroad'),
    'reddit' => array(__('分享到 Reddit', 'argon'), 'reddit-alien', 'https://www.reddit.com/submit', array('url' => $share_url, 'title' => $share_title), 'abroad'),
    'bluesky' => array(__('分享到 Bluesky', 'argon'), 'bluesky', 'https://bsky.app/intent/compose', array('text' => $share_text), 'abroad'),
);
?>
<div id="share_container">
    <div id="share" role="group" aria-label="<?php esc_attr_e('分享', 'argon'); ?>">
        <button type="button" class="btn btn-icon share-action share-wechat" id="share_qrcode" title="<?php esc_attr_e('微信 / 二维码', 'argon'); ?>" aria-label="<?php esc_attr_e('微信 / 二维码', 'argon'); ?>"><i class="fa-brands fa-weixin" aria-hidden="true"></i></button>
        <?php foreach ($share_destinations as $platform => [$label, $icon, $base, $params, $group]) {
            if (($share_mode === 'domestic' && $group !== 'domestic') || ($share_mode === 'abroad' && $group !== 'abroad')) continue;
            $destination = $base . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        ?>
        <a class="btn btn-icon share-action no-pjax share-<?php echo esc_attr($platform); ?>" data-share-platform="<?php echo esc_attr($platform); ?>" href="<?php echo esc_url($destination); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($label); ?>" aria-label="<?php echo esc_attr($label); ?>"><i class="fa-brands fa-<?php echo esc_attr($icon); ?>" aria-hidden="true"></i></a>
        <?php } ?>
        <button type="button" class="btn btn-icon btn-default share-action" id="share_native" title="<?php esc_attr_e('系统分享', 'argon'); ?>" aria-label="<?php esc_attr_e('系统分享', 'argon'); ?>" hidden><i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i></button>
        <button type="button" class="btn btn-icon btn-default share-action" id="share_copy_link" title="<?php esc_attr_e('复制链接', 'argon'); ?>" aria-label="<?php esc_attr_e('复制链接', 'argon'); ?>"><i class="fa-solid fa-link" aria-hidden="true"></i></button>
    </div>
    <button type="button" id="share_show" class="btn btn-icon btn-primary" aria-label="<?php esc_attr_e('分享', 'argon'); ?>" title="<?php esc_attr_e('分享', 'argon'); ?>" aria-expanded="false" aria-controls="share"><i class="fa-solid fa-share-nodes" aria-hidden="true"></i></button>
</div>
<script>
var shareInfo = <?php echo argon_json(array('title' => $share_title, 'url' => $share_url)); ?>;
</script>
