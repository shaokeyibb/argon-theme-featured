<?php
/**
 * 展示文章的头部，包括特色图片、标题（外部模板）、元数据（外部模板）
 * 布局 0 , 特色图片显示在 banner 时的 post entry header
 * Template part for displaying post title, meta, thumbnail
 *
 */

?>
<header class="post-header text-center">
    <?php
        do_action( 'argon_entry_title' );
        do_action( 'argon_entry_meta' );
    ?>
</header>
