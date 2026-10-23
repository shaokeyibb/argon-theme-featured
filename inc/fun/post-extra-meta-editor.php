<?php
//编辑文章界面新增 Meta 编辑模块
function argon_meta_box_1(){
	wp_nonce_field("argon_meta_box_nonce_action", "argon_meta_box_nonce");
	global $post;
	?>
		<h4><?php _e("显示字数和预计阅读时间", 'argon');?></h4>
		<?php $argon_meta_hide_readingtime = get_post_meta($post->ID, "argon_hide_readingtime", true);?>
		<select name="argon_meta_hide_readingtime" id="argon_meta_hide_readingtime">
			<option value="false" <?php if ($argon_meta_hide_readingtime=='false'){echo 'selected';} ?>><?php _e("跟随全局设置", 'argon');?></option>
			<option value="true" <?php if ($argon_meta_hide_readingtime=='true'){echo 'selected';} ?>><?php _e("不显示", 'argon');?></option>
		</select>
		<p style="margin-top: 15px;"><?php _e("是否显示字数和预计阅读时间 Meta 信息", 'argon');?></p>
		<h4><?php _e("Meta 中隐藏发布时间和分类", 'argon');?></h4>
		<?php $argon_meta_simple = get_post_meta($post->ID, "argon_meta_simple", true);?>
		<select name="argon_meta_simple" id="argon_meta_simple">
			<option value="false" <?php if ($argon_meta_simple=='false'){echo 'selected';} ?>><?php _e("不隐藏", 'argon');?></option>
			<option value="true" <?php if ($argon_meta_simple=='true'){echo 'selected';} ?>><?php _e("隐藏", 'argon');?></option>
		</select>
		<p style="margin-top: 15px;"><?php _e("适合特定的页面，例如友链页面。开启后文章 Meta 的第一行只显示阅读数和评论数。", 'argon');?></p>
		<h4><?php _e("使用文章中第一张图作为头图", 'argon');?></h4>
		<?php $argon_first_image_as_thumbnail = get_post_meta($post->ID, "argon_first_image_as_thumbnail", true);?>
		<select name="argon_first_image_as_thumbnail" id="argon_first_image_as_thumbnail">
			<option value="default" <?php if ($argon_first_image_as_thumbnail=='default'){echo 'selected';} ?>><?php _e("跟随全局设置", 'argon');?></option>
			<option value="true" <?php if ($argon_first_image_as_thumbnail=='true'){echo 'selected';} ?>><?php _e("使用", 'argon');?></option>
			<option value="false" <?php if ($argon_first_image_as_thumbnail=='false'){echo 'selected';} ?>><?php _e("不使用", 'argon');?></option>
		</select>
		<h4><?php _e("显示文章过时信息", 'argon');?></h4>
		<?php $argon_show_post_outdated_info = get_post_meta($post->ID, "argon_show_post_outdated_info", true);?>
		<div style="display: flex;">
			<select name="argon_show_post_outdated_info" id="argon_show_post_outdated_info">
				<option value="default" <?php if ($argon_show_post_outdated_info=='default'){echo 'selected';} ?>><?php _e("跟随全局设置", 'argon');?></option>
				<option value="always" <?php if ($argon_show_post_outdated_info=='always'){echo 'selected';} ?>><?php _e("一直显示", 'argon');?></option>
				<option value="never" <?php if ($argon_show_post_outdated_info=='never'){echo 'selected';} ?>><?php _e("永不显示", 'argon');?></option>
			</select>
			<button id="apply_show_post_outdated_info" type="button" class="components-button is-primary" style="height: 22px; display: none;"><?php _e("应用", 'argon');?></button>
		</div>
		<p style="margin-top: 15px;"><?php _e("单独控制该文章的过时信息显示。", 'argon');?></p>
		<h4><?php _e("文末附加内容", 'argon');?></h4>
		<?php $argon_after_post = get_post_meta($post->ID, "argon_after_post", true);?>
		<textarea name="argon_after_post" id="argon_after_post" rows="3" cols="30" style="width:100%;"><?php echo esc_textarea($argon_after_post); ?></textarea>
		<p style="margin-top: 15px;"><?php _e("给该文章设置单独的文末附加内容，留空则跟随全局，设为 <code>--none--</code> 则不显示。", 'argon');?></p>
		<h4><?php _e("自定义 CSS", 'argon');?></h4>
		<?php $argon_custom_css = get_post_meta($post->ID, "argon_custom_css", true);?>
		<textarea name="argon_custom_css" id="argon_custom_css" rows="5" cols="30" style="width:100%;"><?php echo esc_textarea($argon_custom_css); ?></textarea>
		<p style="margin-top: 15px;"><?php _e("给该文章添加单独的 CSS", 'argon');?></p>
		<h4><?php _e("外链点击统计", 'argon');?></h4>
		<?php $argon_enable_external_link_clicks = get_post_meta($post->ID, "argon_enable_external_link_clicks", true);?>
		<select name="argon_enable_external_link_clicks" id="argon_enable_external_link_clicks">
			<option value="default" <?php if ($argon_enable_external_link_clicks=='default' || $argon_enable_external_link_clicks==''){echo 'selected';} ?>><?php _e("跟随全局设置", 'argon');?></option>
			<option value="true" <?php if ($argon_enable_external_link_clicks=='true'){echo 'selected';} ?>><?php _e("启用", 'argon');?></option>
			<option value="false" <?php if ($argon_enable_external_link_clicks=='false'){echo 'selected';} ?>><?php _e("不启用", 'argon');?></option>
		</select>
		<p style="margin-top: 15px;"><?php _e("控制该文章是否显示外链点击量角标并记录点击。", 'argon');?></p>

        <?php if(get_option('argon_ai_post_summary', false) == 'true'){ ?>
            <h4><?php _e("启用 AI 文章摘要", 'argon');?></h4>
	        <?php $argon_ai_post_summary = get_post_meta($post->ID, "argon_ai_post_summary", true);?>
            <select name="argon_ai_post_summary" id="argon_ai_post_summary">
                <option value="true" <?php if ($argon_ai_post_summary=='true'){echo 'selected';} ?>><?php _e("启用", 'argon');?></option>
                <option value="false" <?php if ($argon_ai_post_summary=='false'){echo 'selected';} ?>><?php _e("不启用", 'argon');?></option>
            </select>
            <p style="margin-top: 15px;"><?php _e("当且仅当该选项和全局选项同时启用时才会有效。使用 ChatGPT 自动生成文章摘要。这将替换您主页的文章摘要，并在文章页面头部显示一个摘要卡片。", 'argon');?></p>
            <h4><?php _e("更新文章时不重复生成摘要", 'argon');?></h4>
	        <?php $argon_ai_no_update_post_summary = get_post_meta($post->ID, "argon_ai_no_update_post_summary", true);?>
            <div style="display: flex;">
                <select name="argon_ai_no_update_post_summary" id="argon_ai_no_update_post_summary">
                    <option value="default" <?php if ($argon_ai_no_update_post_summary=='default'){echo 'selected';} ?>><?php _e("跟随全局设置", 'argon');?></option>
                    <option value="true" <?php if ($argon_ai_no_update_post_summary=='true'){echo 'selected';} ?>><?php _e("不更新", 'argon');?></option>
                    <option value="false" <?php if ($argon_ai_no_update_post_summary=='false'){echo 'selected';} ?>><?php _e("更新", 'argon');?></option>
                </select>
                <button id="apply_ai_no_update_post_summary" type="button" class="components-button is-primary" style="height: 22px; display: none;"><?php _e("应用", 'argon');?></button>
            </div>
            <p style="margin-top: 15px;"><?php _e('设置本项为"不更新"以阻止摘要在更新文章时重新生成，避免产生高额的 API 调用开销。', 'argon');;?></p>
            <h4><?php _e("额外 Prompt", 'argon');?></h4>
	        <?php $argon_ai_extra_prompt_mode = get_post_meta($post->ID, "argon_ai_extra_prompt_mode", true);?>
            <div style="display: flex;">
                <select style="margin-bottom: 1px" name="argon_ai_extra_prompt_mode" id="argon_ai_extra_prompt_mode">
                    <option value="default" <?php if ($argon_ai_extra_prompt_mode=='default'){echo 'selected';} ?>><?php _e("跟随全局设置", 'argon');?></option>
                    <option value="replace" <?php if ($argon_ai_extra_prompt_mode=='replace'){echo 'selected';} ?>><?php _e("替换全局设置", 'argon');?></option>
                    <option value="append" <?php if ($argon_ai_extra_prompt_mode=='append'){echo 'selected';} ?>><?php _e("附加在全局设置后", 'argon');?></option>
                    <option value="none" <?php if ($argon_ai_extra_prompt_mode=='none'){echo 'selected';} ?>><?php _e("不使用", 'argon');?></option>
                </select>
                <button id="apply_ai_extra_prompt_mode" type="button" class="components-button is-primary" style="height: 22px; display: none;"><?php _e("应用", 'argon');?></button>
            </div>
	        <?php $argon_ai_extra_prompt = get_post_meta($post->ID, "argon_ai_extra_prompt", true);?>
            <textarea name="argon_ai_extra_prompt" id="argon_ai_extra_prompt" rows="5" cols="30" style="width:100%;"><?php echo esc_textarea($argon_ai_extra_prompt); ?></textarea>
            <p style="margin-top: 15px;"><?php _e('发送给 ChatGPT 的额外 Prompt，将被以"system"的角色插入在文章信息后。', 'argon');?></p>
            <h4><?php _e( "重新生成 AI 文章摘要", 'argon' ); ?>
                <button id="regenerate_ai_post_summary" type="button" class="components-button is-primary"
                        style="height: 22px"><?php _e( "重新生成", 'argon' ); ?></button>
            </h4>
            <script>
                let mode = document.getElementById('argon_ai_extra_prompt_mode');
                let prompts = document.getElementById('argon_ai_extra_prompt');
                mode.addEventListener('change', () => {
                    if (mode.value === 'none' || mode.value === 'default') {
                        prompts.style.display = 'none'
                    } else {
                        prompts.style.display = ''
                    }
                })
                mode.dispatchEvent(new Event('change'))
            </script>
        <?php } ?>
        <h4><?php _e("启用 Discourse 嵌入式评论", 'argon');?></h4>
        <?php $argon_enable_discourse_comments = get_post_meta($post->ID, "argon_enable_discourse_comments", true);?>
        <select name="argon_enable_discourse_comments" id="argon_enable_discourse_comments">
            <option value="true" <?php if ($argon_enable_discourse_comments=='true'){echo 'selected';} ?>><?php _e("启用", 'argon');?></option>
            <option value="false" <?php if ($argon_enable_discourse_comments!='true'){echo 'selected';} ?>><?php _e("不启用", 'argon');?></option>
        </select>
        <p style="margin-top: 15px;"><?php _e("当且仅当该选项和全局选项同时启用时才会有效。这将在您的基础评论区下方显示一个 Discourse 嵌入式评论区。", 'argon');?></p>
        <h4><?php _e(" 自定义 Discourse 主题 ID", 'argon');?></h4>
        <?php $argon_discourse_comments_topic_id = get_post_meta($post->ID, "argon_discourse_comments_topic_id", true);?>
        <input type="text" class="regular-text" name="argon_discourse_comments_topic_id" id="argon_discourse_comments_topic_id" value="<?php echo esc_attr($argon_discourse_comments_topic_id); ?>">
        <p style="margin-top: 15px;"><?php _e("输入你想关联到的 Discourse 主题 ID，不输入则会在 Discourse 创建一个新的主题以绑定到此文章上。", 'argon');?></p>

	<?php
}
function argon_add_meta_boxes($post_type, $post){
	if ($post instanceof WP_Post && use_block_editor_for_post($post)) return;
	add_meta_box('argon_meta_box_1', __("文章设置", 'argon'), 'argon_meta_box_1', array('post', 'page'), 'side', 'low');
}
add_action('add_meta_boxes', 'argon_add_meta_boxes', 10, 2);
function argon_authorize_raw_post_meta($allowed, $meta_key, $post_id, $user_id){
	return user_can($user_id, 'edit_post', $post_id) && user_can($user_id, 'unfiltered_html');
}
function argon_register_sensitive_post_meta(){
	foreach (array('argon_after_post', 'argon_custom_css') as $meta_key){
		// Apply the same rule to WordPress's own custom-field and REST handlers.
		register_meta('post', $meta_key, array(
			'type' => 'string', 'single' => true,
			'auth_callback' => 'argon_authorize_raw_post_meta', 'show_in_rest' => false
		));
	}
}
add_action('init', 'argon_register_sensitive_post_meta');
// Only settings exposed by this meta box may be changed through its save handlers.
function argon_post_meta_allowed_values(){
	return array(
		'argon_hide_readingtime' => array('false', 'true'),
		'argon_meta_simple' => array('false', 'true'),
		'argon_first_image_as_thumbnail' => array('default', 'true', 'false'),
		'argon_show_post_outdated_info' => array('default', 'always', 'never'),
		'argon_after_post' => null,
		'argon_custom_css' => null,
		'argon_enable_external_link_clicks' => array('default', 'true', 'false'),
		'argon_ai_post_summary' => array('true', 'false'),
		'argon_ai_no_update_post_summary' => array('default', 'true', 'false'),
		'argon_ai_extra_prompt_mode' => array('default', 'replace', 'append', 'none'),
		'argon_ai_extra_prompt' => null,
		'argon_enable_discourse_comments' => array('true', 'false'),
		'argon_discourse_comments_topic_id' => null
	);
}
function argon_is_valid_post_meta_value($meta_key, $meta_value){
	$allowed_values = argon_post_meta_allowed_values();
	if (!is_string($meta_key) || !is_string($meta_value) || !array_key_exists($meta_key, $allowed_values)){
		return false;
	}
	if ($meta_key === 'argon_discourse_comments_topic_id'){
		return $meta_value === '' || preg_match('/^[1-9][0-9]*$/D', $meta_value) === 1;
	}
	return $allowed_values[$meta_key] === null || in_array($meta_value, $allowed_values[$meta_key], true);
}
function argon_can_edit_post_meta($post_id, $meta_key){
	if (!current_user_can('edit_post_meta', $post_id, $meta_key)){
		return false;
	}
	// These fields are rendered as raw HTML/CSS on the public post.
	if (in_array($meta_key, array('argon_after_post', 'argon_custom_css'), true)){
		return current_user_can('unfiltered_html');
	}
	return true;
}
function argon_save_meta_data($post_id){
	if (!isset($_POST['argon_meta_box_nonce']) || !is_string($_POST['argon_meta_box_nonce'])){
		return $post_id;
	}
	$nonce = wp_unslash($_POST['argon_meta_box_nonce']);
	if (!wp_verify_nonce($nonce, 'argon_meta_box_nonce_action')){
		return $post_id;
	}
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
		return $post_id;
	}
	$post = get_post($post_id);
	if (!$post || !in_array($post->post_type, array('post', 'page'), true) || !current_user_can('edit_post', $post_id)){
		return $post_id;
	}
	foreach (argon_post_meta_allowed_values() as $meta_key => $allowed_values){
		$field_name = $meta_key === 'argon_hide_readingtime' ? 'argon_meta_hide_readingtime' : $meta_key;
		if (!isset($_POST[$field_name]) || !is_string($_POST[$field_name])){
			continue;
		}
		if (!argon_can_edit_post_meta($post_id, $meta_key) || !argon_is_valid_post_meta_value($meta_key, wp_unslash($_POST[$field_name]))){
			continue;
		}
		// WordPress removes request slashes when storing metadata.
		update_post_meta($post_id, $meta_key, $_POST[$field_name]);
	}
}
add_action('save_post', 'argon_save_meta_data');
function argon_get_ajax_editable_post_id(){
	if (!is_user_logged_in()){
		wp_send_json(array('status' => 'failed', 'message' => 'forbidden'), 403);
	}
	if (!isset($_POST['argon_meta_box_nonce']) || !is_string($_POST['argon_meta_box_nonce']) || !wp_verify_nonce(wp_unslash($_POST['argon_meta_box_nonce']), 'argon_meta_box_nonce_action')){
		wp_send_json(array('status' => 'failed', 'message' => 'invalid_nonce'), 403);
	}
	if (!isset($_POST['post_id']) || !is_string($_POST['post_id'])){
		wp_send_json(array('status' => 'failed', 'message' => 'invalid_request'), 400);
	}
	$post_id = filter_var($_POST['post_id'], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
	if ($post_id === false){
		wp_send_json(array('status' => 'failed', 'message' => 'invalid_request'), 400);
	}
	$post = get_post($post_id);
	if (!$post || !in_array($post->post_type, array('post', 'page'), true)){
		wp_send_json(array('status' => 'failed', 'message' => 'invalid_request'), 400);
	}
	if (!current_user_can('edit_post', $post_id)){
		wp_send_json(array('status' => 'failed', 'message' => 'forbidden'), 403);
	}
	return $post_id;
}
function update_post_meta_ajax(){
	$post_id = argon_get_ajax_editable_post_id();
	if (!isset($_POST['meta_key'], $_POST['meta_value']) || !is_string($_POST['meta_key']) || !is_string($_POST['meta_value'])){
		wp_send_json(array('status' => 'failed', 'message' => 'invalid_request'), 400);
	}
	$meta_key = wp_unslash($_POST['meta_key']);
	$meta_value = wp_unslash($_POST['meta_value']);
	if (!array_key_exists($meta_key, argon_post_meta_allowed_values()) || !argon_can_edit_post_meta($post_id, $meta_key)){
		wp_send_json(array('status' => 'failed', 'message' => 'forbidden'), 403);
	}
	if (!argon_is_valid_post_meta_value($meta_key, $meta_value)){
		wp_send_json(array('status' => 'failed', 'message' => 'invalid_meta_value'), 400);
	}
	if (get_post_meta($post_id, $meta_key, true) === $meta_value){
		wp_send_json(array('status' => 'success'));
	}
	$result = update_post_meta($post_id, $meta_key, wp_slash($meta_value));
	wp_send_json(array('status' => $result ? 'success' : 'failed'), $result ? 200 : 500);
}
add_action('wp_ajax_update_post_meta_ajax' , 'update_post_meta_ajax');
function regenerate_ai_post_summary(): void {
	$post_id = argon_get_ajax_editable_post_id();
	if (!current_user_can('edit_post_meta', $post_id, 'argon_ai_summary')){
		wp_send_json(array('status' => 'failed', 'message' => 'forbidden'), 403);
	}

	$result = argon_update_ai_post_meta($post_id);
	wp_send_json(array('status' => $result ? 'success' : 'failed'), $result ? 200 : 500);
}
add_action('wp_ajax_regenerate_ai_post_summary', 'regenerate_ai_post_summary');
