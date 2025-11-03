<article class="post card bg-white shadow-sm border-0 <?php if (get_option('argon_enable_into_article_animation') == 'true'){echo 'post-preview';} ?> post-preview-layout-2" id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="post-header <?php if (argon_has_post_thumbnail()){echo " post-header-with-thumbnail";}?>">
		<?php
			if (argon_has_post_thumbnail()){
				// 使用新的函数获取完整的 <img> 标签，支持 WebP 等现代图片格式
				$thumbnail_attr = array('class' => 'post-thumbnail');
				
				if (get_option('argon_enable_lazyload') != 'false'){
					// 如果启用了 lazyload，先获取完整的图片标签
					$thumbnail_attr['class'] = 'post-thumbnail lazyload';
					$thumbnail_attr['style'] = 'opacity: 0;';
					$thumbnail_html = argon_get_post_thumbnail_image(0, 'large', $thumbnail_attr);
					
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
					echo argon_get_post_thumbnail_image(0, 'large', $thumbnail_attr);
				}
			}
		?>
	</header>

	<div class="post-content-container">
	<?php 
		do_action( 'argon_entry_title' );
		do_action( 'argon_entry_excerpt' );
		do_action( 'argon_entry_meta' );
		do_action( 'argon_entry_tags' );
	?>	
	</div>
</article>