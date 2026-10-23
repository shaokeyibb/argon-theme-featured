<?php get_header(); ?>
<div class="page-information-card-container"></div>
<?php get_sidebar(); ?>
<div id="primary" class="content-area">
<main id="main" class="site-main" role="main">
<article class="card shadow-sm border-0"><div class="card-body text-center">
    <h1>404</h1>
    <p><?php esc_html_e('页面不存在或已被移动。', 'argon'); ?></p>
    <?php get_search_form(); ?>
    <p><a class="btn btn-primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('回到首页', 'argon'); ?></a></p>
    <?php echo apply_filters('argon_404page_extra_html', ''); ?>
</div></article>
<?php get_footer(); ?>
