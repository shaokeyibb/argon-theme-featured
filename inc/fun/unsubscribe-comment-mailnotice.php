<?php
	if (!defined('ABSPATH')) exit;
	$id = filter_var(argon_request_string($_GET, 'comment'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
	$token = argon_request_string($_GET, 'token');
	$stored_token = $id === false ? '' : get_comment_meta($id, 'mailnotice_unsubscribe_key', true);
	if ($id === false) {
		$page_title = __('参数错误', 'argon');
		$title = "<i class='fa fa-close' style='color: #f5365c;margin-right: 12px;'></i>" . __("错误", 'argon');
		$info = __("提供的参数错误", 'argon');
	}
	else if (get_comment($id) == null){
		$page_title = __('评论不存在', 'argon');
		$title = "<i class='fa fa-close' style='color: #f5365c;margin-right: 12px;'></i>" . __("错误", 'argon');
		$info = __("评论 #", 'argon') . $id . __(" 不存在", 'argon');
	}
	else if (get_comment_meta($id, "enable_mailnotice", true) != "true"){
		$page_title = __('无需退订', 'argon');
		$title = "<i class='fa fa-info-circle' style='color: #11cdef;margin-right: 12px;'></i>" . __("无需退订", 'argon');
		$info = __("评论 #", 'argon') . $id . __(" 的邮件通知已被退订或没有开启邮件通知", 'argon');
	}
	else if (strlen($token) !== 32 || !is_string($stored_token) || strlen($stored_token) !== 32 || !hash_equals($stored_token, $token)){
		$page_title = __('退订失败', 'argon');
		$title = "<i class='fa fa-close' style='color: #f5365c;margin-right: 12px;'></i>" . __("退订失败", 'argon');
		$info = __("Token 不正确", 'argon');
	}
	else{
		update_comment_meta($id, "enable_mailnotice", "false");
		$page_title = __('退订成功', 'argon');
		$title = "<i class='fa fa-check' style='color: #2dce89;margin-right: 12px;'></i>" . __("退订成功", 'argon');
		$info = __("您已成功退订评论 #", 'argon') . $id . __(" 的邮件通知<br>该评论下有新回复时您将不会再收到通知", 'argon');
	}
add_filter('pre_get_document_title', static fn() => $page_title);
get_header();
?>
<div class="page-information-card-container"></div>
<?php get_sidebar(); ?>
<div id="primary" class="content-area">
<main id="main" class="site-main" role="main">
  <section class="card shadow p-5" style="max-width: 700px; margin: 3rem auto;">
    <h1><?php echo wp_kses_post($title); ?></h1>
    <p><?php echo wp_kses_post($info); ?></p>
    <a class="btn btn-primary" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('返回首页', 'argon'); ?></a>
  </section>
<?php get_footer(); ?>
