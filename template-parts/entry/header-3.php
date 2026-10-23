<?php
/**
 * 展示文章的头部，包括特色图片、标题（外部模板）、元数据（外部模板）
 * 布局 3 , 特色图片在标题上方
 * Template part for displaying post title, meta, thumbnail
 *
 */
?>
<?php
    if (argon_has_post_thumbnail()){
        echo "<header class='post-header post-header-with-thumbnail'>";

        echo argon_get_post_thumbnail_image(0, 'full');

        echo "</header>";
    }
?>

<header class="post-header text-center">
    <?php
        do_action( 'argon_entry_title' );
        do_action( 'argon_entry_meta' );
    ?>
</header>


