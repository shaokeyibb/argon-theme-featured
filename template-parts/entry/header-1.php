<?php
/**
 * 展示文章的头部，包括特色图片、标题（外部模板）、元数据（外部模板）
 * 布局 1 , 特色图片在标题下层
 * Template part for displaying post title, meta, thumbnail 
 *
 */
?>

<?php
    $has_post_thumbnail = argon_has_post_thumbnail();
?>

<header class="post-header text-center<?php if ($has_post_thumbnail){echo " post-header-with-thumbnail";}?>">
    <?php
        if ($has_post_thumbnail){
            // 使用新的函数获取完整的 <img> 标签，支持 WebP 等现代图片格式
            $thumbnail_attr = array('class' => 'post-thumbnail');
            
            if (get_option('argon_enable_lazyload') != 'false'){
                // 如果启用了 lazyload，先获取完整的图片标签
                $thumbnail_attr['class'] = 'post-thumbnail lazyload';
                $thumbnail_attr['style'] = 'opacity: 0;';
                $thumbnail_html = argon_get_post_thumbnail_image(0, 'full', $thumbnail_attr);
                
                // 将 src 替换为占位符，原 src 移到 data-original
                $thumbnail_html = preg_replace_callback(
                    '/<img(.*)src=["\']([^"\']+)["\'](.*)>/i',
                    function($matches) {
                        return '<img' . $matches[1] . 
                               'src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAABBJREFUeNpi+P//PwNAgAEACPwC/tuiTRYAAAAASUVORK5CYII=" ' .
                               'data-original="' . $matches[2] . '"' . $matches[3] . '>';
                    },
                    $thumbnail_html
                );
                // 移除 srcset 属性（lazyload 会重新加载）
                $thumbnail_html = preg_replace('/\s*srcset=["\'][^"\']*["\']/', '', $thumbnail_html);
                echo $thumbnail_html;
            }else{
                echo argon_get_post_thumbnail_image(0, 'full', $thumbnail_attr);
            }
            
            echo "<div class='post-header-text-container'>";
        }

        do_action( 'argon_entry_title' );
        do_action( 'argon_entry_meta' );

        if ($has_post_thumbnail){
            echo "</div>";
        }
    ?>
</header>


