<article class="post card bg-white shadow-sm border-0 <?php if (get_option('argon_enable_into_article_animation') == 'true'){echo 'post-preview';} ?> post-preview-layout-2" id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="post-header <?php if (argon_has_post_thumbnail()){echo " post-header-with-thumbnail";}?>">
		<?php
			if (argon_has_post_thumbnail()){
				echo argon_get_post_thumbnail_image(0, 'large');
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
