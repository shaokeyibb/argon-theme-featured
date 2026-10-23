<?php
// Register every real block on the server, using dependencies extracted by wp-scripts.
function argon_init_gutenberg_blocks() {
    $dir = get_template_directory() . '/gutenberg/build/';
    $url = get_template_directory_uri() . '/gutenberg/build/';
    if (!is_readable($dir . 'blocks.asset.php')) return;
    $asset = require $dir . 'blocks.asset.php';
    wp_register_script('argon-blocks', $url . 'blocks.js', $asset['dependencies'], $asset['version'], true);
    wp_register_style('argon-blocks-editor', $url . 'blocks.css', array(), $asset['version']);
    wp_set_script_translations('argon-blocks', 'argon', get_template_directory() . '/languages');
    foreach (glob(get_template_directory() . '/gutenberg/metadata/*/block.json') as $metadata) {
        register_block_type($metadata);
    }
}
add_action('init', 'argon_init_gutenberg_blocks');
function argon_add_gutenberg_category($block_categories, $editor_context) {
	if (!empty($editor_context->post)){
		array_push(
			$block_categories,
			array(
				'slug'  => 'argon',
				'title' => 'Argon',
				'icon'  => null,
			)
		);
	}
	return $block_categories;
}
add_filter('block_categories_all', 'argon_add_gutenberg_category', 10, 2);
function argon_admin_i18n_info(){
	echo "<script>var argon_language = '" . argon_get_locate() . "';</script>";
}
add_filter('admin_head', 'argon_admin_i18n_info');
//主题文章短代码解析
function shortcode_content_preprocess($attr, $content = ""){
	if (($attr['nested'] ?? 'true') !== 'false'){
		return do_shortcode((string) $content);
	}else{
		return $content;
	}	
}
add_shortcode('br','shortcode_br');
function shortcode_br($attr,$content=""){
	return "</br>";
}
add_shortcode('label','shortcode_label');
function shortcode_label($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<span class='badge";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'indigo';
	switch ($color){
		case 'green':
			$out .= " badge-success";
			break;
		case 'red':
			$out .= " badge-danger";
			break;
		case 'orange':
			$out .= " badge-warning";
			break;
		case 'blue':
			$out .= " badge-info";
			break;
		case 'indigo':
		default:
			$out .= " badge-primary";
			break;
	}
	$shape = isset( $attr['shape'] ) ? $attr['shape'] : 'square';
	if ($shape=="round"){
		$out .= " badge-pill";
	}
	$out .= "'>" . $content . "</span>";
	return $out;
}
add_shortcode('progressbar','shortcode_progressbar');
function shortcode_progressbar($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<div class='progress-wrapper'><div class='progress-info'>";
	if ($content != ""){
		$out .= "<div class='progress-label'><span>" . $content . "</span></div>";
	}
	$progress = isset($attr['progress']) && is_numeric($attr['progress']) ? max(0, min(100, (float) $attr['progress'])) : 100;
	$out .= "<div class='progress-percentage'><span>" . $progress . "%</span></div>";
	$out .= "</div><div class='progress'><div class='progress-bar";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'indigo';
	switch ($color){
		case 'indigo':
			$out .= " bg-primary";
			break;
		case 'green':
			$out .= " bg-success";
			break;
		case 'red':
			$out .= " bg-danger";
			break;
		case 'orange':
			$out .= " bg-warning";
			break;
		case 'blue':
			$out .= " bg-info";
			break;
		default:
			$out .= " bg-primary";
			break;
	}
	$out .= "' style='width: " . $progress . "%;'></div></div></div>";
	return $out;
}
add_shortcode('checkbox','shortcode_checkbox');
function shortcode_checkbox($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$checked = isset( $attr['checked'] ) ? $attr['checked'] : 'false';
	$inline = $attr['inline'] ?? 'false';
	$out = "<div class='shortcode-todo custom-control custom-checkbox";
	if ($inline == 'true'){
		$out .= " inline";
	}
	$out .= "'>
				<input class='custom-control-input' type='checkbox'" . ($checked == 'true' ? ' checked' : '') . ">
				<label class='custom-control-label'>
					<span>" . $content . "</span>
				</label>
			</div>";
	return $out;
}
add_shortcode('alert','shortcode_alert');
function shortcode_alert($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<div class='alert";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'indigo';
	switch ($color){
		case 'indigo':
			$out .= " alert-primary";
			break;
		case 'green':
			$out .= " alert-success";
			break;
		case 'red':
			$out .= " alert-danger";
			break;
		case 'orange':
			$out .= " alert-warning";
			break;
		case 'blue':
			$out .= " alert-info";
			break;
		case 'black':
			$out .= " alert-default";
			break;
		default:
			$out .= " alert-primary";
			break;
	}
	$out .= "'>";
	if (isset($attr['icon'])){
		$out .= "<span class='alert-inner--icon'><i class='fa fa-" . esc_attr(sanitize_html_class($attr['icon'])) . "'></i></span>";
	}
	$out .= "<span class='alert-inner--text'>";
	if (isset($attr['title'])){
		$out .= "<strong>" . esc_html($attr['title']) . "</strong> ";
	}
	$out .= $content . "</span></div>";
	return $out;
}
add_shortcode('admonition','shortcode_admonition');
function shortcode_admonition($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<div class='admonition shadow-sm";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'indigo';
	switch ($color){
		case 'indigo':
			$out .= " admonition-primary";
			break;
		case 'green':
			$out .= " admonition-success";
			break;
		case 'red':
			$out .= " admonition-danger";
			break;
		case 'orange':
			$out .= " admonition-warning";
			break;
		case 'blue':
			$out .= " admonition-info";
			break;
		case 'black':
			$out .= " admonition-default";
			break;
		case 'grey':
			$out .= " admonition-grey";
			break;
		default:
			$out .= " admonition-primary";
			break;
	}
	$out .= "'>";
	if (isset($attr['title'])){
		$out .= "<div class='admonition-title'>";
		if (isset($attr['icon'])){
			$out .= "<i class='fa fa-" . esc_attr(sanitize_html_class($attr['icon'])) . "'></i> ";
		}
		$out .= esc_html($attr['title']) . "</div>";
	}
	if ($content != ''){
		$out .= "<div class='admonition-body'>" . $content . "</div>";
	}
	$out .= "</div>";
	return $out;
}
add_shortcode('collapse','shortcode_collapse_block');
add_shortcode('fold','shortcode_collapse_block');
function shortcode_collapse_block($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$collapsed = isset( $attr['collapsed'] ) ? $attr['collapsed'] : 'true';
	$show_border_left = isset( $attr['showleftborder'] ) ? $attr['showleftborder'] : 'false';
	$out = "<div " ;
	$out .= " class='collapse-block shadow-sm";
	$color = isset( $attr['color'] ) ? $attr['color'] : 'none';
	$title = isset( $attr['title'] ) ? $attr['title'] : '';
	switch ($color){
		case 'indigo':
			$out .= " collapse-block-primary";
			break;
		case 'green':
			$out .= " collapse-block-success";
			break;
		case 'red':
			$out .= " collapse-block-danger";
			break;
		case 'orange':
			$out .= " collapse-block-warning";
			break;
		case 'blue':
			$out .= " collapse-block-info";
			break;
		case 'black':
			$out .= " collapse-block-default";
			break;
		case 'grey':
			$out .= " collapse-block-grey";
			break;
		case 'none':
		default:
			$out .= " collapse-block-transparent";
			break;
	}
	if ($collapsed == 'true'){
		$out .= " collapsed";
	}
	if ($show_border_left != 'true'){
		$out .= " hide-border-left";
	}
	$out .= "'>";

	$out .= "<div class='collapse-block-title'>";
	if (isset($attr['icon'])){
		$out .= "<i class='fa fa-" . esc_attr(sanitize_html_class($attr['icon'])) . "'></i> ";
	}
	$out .= "<span class='collapse-block-title-inner'>" . esc_html($title) . "</span><i class='collapse-icon fa fa-angle-down'></i></div>";

	$out .= "<div class='collapse-block-body'";
	if ($collapsed != 'false'){
		$out .= " style='display:none;'";
	}
	$out .= ">" . $content . "</div>";
	$out .= "</div>";
	return $out;
}
add_shortcode('friendlinks','shortcode_friend_link');
function shortcode_friend_link($attr,$content=""){
	$sort = isset( $attr['sort'] ) ? $attr['sort'] : 'name';
	$order = isset( $attr['order'] ) ? $attr['order'] : 'ASC';
	$friendlinks = get_bookmarks( array(
		'orderby' => $sort ,
		'order'   => $order
	));
	$style = isset( $attr['style'] ) ? $attr['style'] : '1';
	switch ($style) {
		case '1':
			$class = "friend-links-style1";
			break;
		case '1-square':
			$class = "friend-links-style1 friend-links-style1-square";
			break;
		case '2':
			$class = "friend-links-style2";
			break;
		case '2-big':
			$class = "friend-links-style2 friend-links-style2-big";
			break;
		default:
			$class = "friend-links-style1";
			break;
	}
	$out = "<div class='friend-links " . $class . "'><div class='row'>";
	foreach ($friendlinks as $friendlink){
		$out .= "
			<div class='link mb-2 col-lg-6 col-md-6'>
				<div class='card shadow-sm friend-link-container" . ($friendlink -> link_image == "" ? " no-avatar" : "") . "'>";
		if ($friendlink -> link_image != ''){
			$out .= "
					<img src='" . esc_url($friendlink->link_image) . "' class='friend-link-avatar bg-gradient-secondary'>";
		}
		$rel = "";
		if ($friendlink -> link_rel != ''){
			$rel = "' rel='" . esc_attr($friendlink->link_rel);
		}
		$out .= "	<div class='friend-link-content'>
						<div class='friend-link-title title text-primary'>
							<a target='_blank' href='" . esc_url($friendlink -> link_url) . $rel . "'>" . esc_html($friendlink -> link_name) . "</a>
						</div>
						<div class='friend-link-description'>" . esc_html($friendlink -> link_description) . "</div>";
		$out .= "		<div class='friend-link-links'>";
		foreach (explode("\n", $friendlink -> link_notes) as $line){
			$item = explode("|", trim($line));
			if(count($item) < 2 || stripos($item[0], "fa-") !== 0){
				continue;
			}
			$out .= "<a href='" . esc_url($item[1]) . "' target='_blank'><i class='fa " . sanitize_html_class($item[0]) . "'></i></a>";
		}
		$out .= "<a href='" . esc_url($friendlink -> link_url) . "' target='_blank' style='float:right; margin-right: 10px;'><i class='fa fa-angle-right' style='font-weight: bold;'></i></a>";
		$out .= "
						</div>
					</div>
				</div>
			</div>";
	}
	$out .= "</div></div>";
	return $out;
}
add_shortcode('sfriendlinks','shortcode_friend_link_simple');
function shortcode_friend_link_simple($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$content = trim(strip_tags($content));
	$entries = explode("\n" , $content);

	$shuffle = isset( $attr['shuffle'] ) ? $attr['shuffle'] : 'false';
	if ($shuffle == "true"){
		mt_srand();
		$group_start = 0;
		foreach ($entries as $index => $value){
			$now = array_pad(explode("|", $value), 5, "");
			if ($now[0] == 'category'){
				for ($i = $index - 1; $i >= $group_start; $i--){
					$tar = mt_rand($group_start , $i);
					$tmp = $entries[$tar];
					$entries[$tar] = $entries[$i];
					$entries[$i] = $tmp;
				}
				$group_start = $index + 1;
			}
		}
		for ($i = count($entries) - 1; $i >= $group_start; $i--){
			$tar = mt_rand($group_start , $i);
			$tmp = $entries[$tar];
			$entries[$tar] = $entries[$i];
			$entries[$i] = $tmp;
		}
	}

	$row_tag_open = False;
	$out = "<div class='friend-links-simple'>";
	foreach($entries as $index => $value){
		$now = array_pad(explode("|", $value), 5, "");
		if ($now[0] == 'category'){
			if ($row_tag_open == True){
				$row_tag_open = False;
				$out .= "</div>";
			}
			$out .= "<div class='friend-category-title text-black'>" . esc_html($now[1]) . "</div>";
		}
		if ($now[0] == 'link'){
			if ($row_tag_open == False){
				$row_tag_open = True;
				$out .= "<div class='row'>";
			}
			$out .= "
			<div class='link mb-2 col-lg-4 col-md-6'>
				<div class='card shadow-sm'>
					<div class='d-flex'>
						<div class='friend-link-avatar'>
							<a target='_blank' href='" . esc_url($now[1]) . "'>";
			if (!ctype_space($now[4]) && $now[4] != '' && isset($now[4])){
				$out .= "<img src='" . esc_url($now[4]) . "' class='icon bg-gradient-secondary rounded-circle text-white' style='pointer-events: none;'>
						</img>";
			}else{
				$out .= "<div class='icon icon-shape bg-gradient-primary rounded-circle text-white'>" . esc_html(mb_substr($now[2], 0, 1)) . "
						</div>";
			}

			$out .= "		</a>
						</div>
						<div class='pl-3'>
							<div class='friend-link-title title text-primary'><a target='_blank' href='" . esc_url($now[1]) . "'>" . esc_html($now[2]) . "</a>
						</div>";
			if (!ctype_space($now[3]) && $now[3] != ''  && isset($now[3])){
				$out .= "<p class='friend-link-description'>" . esc_html($now[3]) . "</p>";
			}else{
				/*$out .= "<p class='friend-link-description'>&nbsp;</p>";*/
			}
			$out .= "		<a target='_blank' href='" . esc_url($now[1]) . "' class='text-primary opacity-8'>前往</a>
						</div>
					</div>
				</div>
			</div>";
		}
	}
	if ($row_tag_open == True){
		$row_tag_open = False;
		$out .= "</div>";
	}
	$out .= "</div>";
	return $out;
}
add_shortcode('timeline','shortcode_timeline');
function shortcode_timeline($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$content = trim(strip_tags($content));
	$entries = explode("\n" , $content);
	$out = "<div class='argon-timeline'>";
	foreach($entries as $index => $value){
		$now = array_pad(explode("|", $value), 5, "");
		$now[0] = str_replace("/", "<br>", esc_html($now[0]));
		$out .= "<div class='argon-timeline-node'>
					<div class='argon-timeline-time'>" . $now[0] . "</div>
					<div class='argon-timeline-card card bg-gradient-secondary shadow-sm'>";
		if ($now[1] != ''){
			$out .= "	<div class='argon-timeline-title'>" . esc_html($now[1]) . "</div>";
		}
		$out .= "		<div class='argon-timeline-content'>";
		foreach($now as $index => $value){
			if ($index < 2){
				continue;
			}
			if ($index > 2){
				$out .= "</br>";
			}
			$out .= esc_html($value);
		}
		$out .= "		</div>
					</div>
				</div>";
	}
	$out .= "</div>";
	return $out;
}
add_shortcode('hidden','shortcode_hidden');
add_shortcode('spoiler','shortcode_hidden');
function shortcode_hidden($attr,$content=""){
	$content = shortcode_content_preprocess($attr, $content);
	$out = "<span class='argon-hidden-text";
	$tip = isset( $attr['tip'] ) ? $attr['tip'] : '';
	$type = isset( $attr['type'] ) ? $attr['type'] : 'blur';
	if ($type == "background"){
		$out .= " argon-hidden-text-background";
	}else{
		$out .= " argon-hidden-text-blur";
	}
	$out .= "'";
	if ($tip != ''){
		$out .= " title='" . esc_attr($tip) ."'";
	}
	$out .= ">" . $content . "</span>";
	return $out;
}
add_shortcode('github','shortcode_github');
function shortcode_github($attr, $content = ''){
	$author = $attr['author'] ?? '';
	$project = $attr['project'] ?? '';
	if (!preg_match('/^[a-z0-9-]+$/iD', $author) || !preg_match('/^[a-z0-9_.-]+$/iD', $project)){
		return '';
	}
	$getdata = ($attr['getdata'] ?? '') === 'backend' ? 'backend' : 'frontend';
	$size = ($attr['size'] ?? '') === 'mini' ? 'mini' : 'full';
	$description = $homepage = $stars = $forks = '';
	$repo_path = rawurlencode($author) . '/' . rawurlencode($project);
	if ($getdata === 'backend'){
		$response = wp_safe_remote_get('https://api.github.com/repos/' . $repo_path, array(
			'timeout' => 5, 'redirection' => 2, 'limit_response_size' => 262144,
			'headers' => array('User-Agent' => 'ArgonTheme')
		));
		$data = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);
		if (!is_array($data) || wp_remote_retrieve_response_code($response) !== 200){
			$getdata = 'frontend';
		}else{
			$description = is_string($data['description'] ?? null) ? $data['description'] : '';
			$homepage = esc_url_raw($data['homepage'] ?? '', array('http', 'https'));
			$stars = (int) ($data['stargazers_count'] ?? 0);
			$forks = (int) ($data['forks_count'] ?? 0);
		}
	}
	$out = "<div class='github-info-card github-info-card-" . $size . " card shadow-sm' data-author='" . esc_attr($author) . "' data-project='" . esc_attr($project) . "' githubinfo-card-id='" . wp_unique_id('github-') . "' data-getdata='" . $getdata . "' data-description='" . esc_attr($description) . "' data-homepage='" . esc_attr($homepage) . "' data-stars='" . esc_attr($stars) . "' data-forks='" . esc_attr($forks) . "'>";
	$out .= "<div class='github-info-card-header'><a href='https://github.com/' rel='nofollow noopener' target='_blank' title='GitHub' no-pjax><span><i class='fa-brands fa-github'></i>" . ($size === 'mini' ? '' : ' GitHub') . "</span></a></div>";
	$out .= "<div class='github-info-card-body'><div class='github-info-card-name-a'><a href='https://github.com/" . $repo_path . "' target='_blank' rel='noopener' no-pjax><span class='github-info-card-name'>" . esc_html($author . '/' . $project) . "</span></a></div><div class='github-info-card-description'></div></div>";
	$out .= "<div class='github-info-card-bottom'><span class='github-info-card-meta github-info-card-meta-stars'><i class='fa fa-star'></i> <span class='github-info-card-stars'></span></span><span class='github-info-card-meta github-info-card-meta-forks'><i class='fa fa-code-fork'></i> <span class='github-info-card-forks'></span></span></div></div>";
	return $out;
}
add_shortcode('video','shortcode_video');
function shortcode_video($attr,$content=""){
	$url = isset( $attr['mp4'] ) ? $attr['mp4'] : '';
	$url = isset( $attr['url'] ) ? $attr['url'] : $url;
	$width = isset( $attr['width'] ) ? $attr['width'] : '';
	$height = isset( $attr['height'] ) ? $attr['height'] : '';
	$autoplay = isset( $attr['autoplay'] ) ? $attr['autoplay'] : 'false';
	$out = "<video";
	if ($width != ''){
		$out .= " width='" . esc_attr($width) . "'";
	}
	if ($height != ''){
		$out .= " height='" . esc_attr($height) . "'";
	}
	if ($autoplay == 'true'){
		$out .= " autoplay";
	}
	$out .= " controls>";
	$out .= "<source src='" . esc_url($url) . "'>";
	$out .= "</video>";
	return $out;
}
add_shortcode('hide_reading_time','shortcode_hide_reading_time');
function shortcode_hide_reading_time($attr,$content=""){
	return "";
}
add_shortcode('post_time','shortcode_post_time');
function shortcode_post_time($attr,$content=""){
	$format = isset( $attr['format'] ) ? $attr['format'] : 'Y-n-d G:i:s';
	return esc_html(get_the_time($format));
}
add_shortcode('post_modified_time','shortcode_post_modified_time');
function shortcode_post_modified_time($attr,$content=""){
	$format = isset( $attr['format'] ) ? $attr['format'] : 'Y-n-d G:i:s';
	return esc_html(get_the_modified_time($format));
}
add_shortcode('noshortcode','shortcode_noshortcode');
function shortcode_noshortcode($attr,$content=""){
	return $content;
}
//Reference Footnote
add_shortcode('ref','shortcode_ref');
$post_references = array();
$post_reference_keys_first_index = array();
$post_reference_contents_first_index = array();
function argon_get_ref_html($content, $index, $subIndex){
	$index++;
	return "<sup class='reference' id='ref_" . $index . "_" . $subIndex . "' data-content='" . esc_attr($content) . "' tabindex='0'><a class='reference-link' href='#ref_" . $index . "'>[" . $index . "]</a></sup>";
}
function shortcode_ref($attr,$content=""){
	global $post_references;
	global $post_reference_keys_first_index;
	global $post_reference_contents_first_index;
	$content = preg_replace(
		'/<p>(.*?)<\/p>/is',
		'</br>$1',
		$content
	);
	$content = wp_kses($content, array(
		'a' => array(
			'href' => array(),
			'title' => array(),
			'target' => array()
		),
		'br' => array(),
		'em' => array(),
		'strong' => array(),
		'b' => array(),
		'sup' => array(),
		'sub' => array(),
		'small' => array()
	));
	if (isset($attr['id'])){
		if (isset($post_reference_keys_first_index[$attr['id']])){
			$post_references[$post_reference_keys_first_index[$attr['id']]]['count']++;
		}else{
			array_push($post_references, array('content' => $content, 'count' => 1));
			$post_reference_keys_first_index[$attr['id']] = count($post_references) - 1;
		}
		$index = $post_reference_keys_first_index[$attr['id']];
		return argon_get_ref_html($post_references[$index]['content'], $index, $post_references[$index]['count']);
	}else{
		if (isset($post_reference_contents_first_index[$content])){
			$post_references[$post_reference_contents_first_index[$content]]['count']++;
			$index = $post_reference_contents_first_index[$content];
			return argon_get_ref_html($post_references[$index]['content'], $index, $post_references[$index]['count']);
		}else{
			array_push($post_references, array('content' => $content, 'count' => 1));
			$post_reference_contents_first_index[$content] = count($post_references) - 1;
			$index = count($post_references) - 1;
			return argon_get_ref_html($post_references[$index]['content'], $index, $post_references[$index]['count']);
		}
	}
}

//TinyMce 按钮
function argon_tinymce_extra_buttons(){
	if(!current_user_can('edit_posts') && !current_user_can('edit_pages')){
		return;
	}
	if(get_user_option('rich_editing') == 'true'){
		add_filter('mce_external_plugins', 'argon_tinymce_add_plugin');
		add_filter('mce_buttons', 'argon_tinymce_register_button');
		add_editor_style($GLOBALS['assets_path'] . "/assets/tinymce_assets/tinymce_editor_codeblock.css");
	}
}
add_action('init', 'argon_tinymce_extra_buttons');
function argon_tinymce_register_button($buttons){
	array_push($buttons, "|", "codeblock");
	array_push($buttons, "|", "label");
	array_push($buttons, "", "checkbox");
	array_push($buttons, "", "progressbar");
	array_push($buttons, "", "alert");
	array_push($buttons, "", "admonition");
	array_push($buttons, "", "collapse");
	array_push($buttons, "", "timeline");
	array_push($buttons, "", "github");
	array_push($buttons, "", "video");
	array_push($buttons, "", "hiddentext");
	return $buttons;
}
function argon_tinymce_add_plugin($plugins){
	$plugins['codeblock'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['label'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['checkbox'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['progressbar'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['alert'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['admonition'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['collapse'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['timeline'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['github'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['video'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	$plugins['hiddentext'] = get_bloginfo('template_url') . '/assets/tinymce_assets/tinymce_btns.js';
	return $plugins;
}
