<footer id="footer" class="site-footer card shadow-sm border-0">
	<?php
	echo get_option( 'argon_footer_html' );
	?>
    <div>Theme <a href="https://github.com/shaokeyibb/argon-theme-featured"
                  target="_blank"><strong>Argon Featured</strong></a><?php if ( get_option( 'argon_hide_footer_author' ) != 'true' ) {
			echo " Original Created By solstice23, Enhanced By HikariLan";
		} ?>
    </div>
    <div><?php if (isset($_SERVER['HTTP_X_PROVIDED_BY']) && is_string($_SERVER['HTTP_X_PROVIDED_BY']))
			echo "您本次会话的 CDN 提供商为 " . esc_html(wp_unslash($_SERVER['HTTP_X_PROVIDED_BY'])); ?></div>
</footer>
</main>
</div>
</div>
</div>
</div>
<?php echo get_option('argon_custom_html_foot'); ?>
<?php wp_footer(); ?>
</body>
</html>
