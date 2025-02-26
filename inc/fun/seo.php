<?php
//页面 Description Meta
function get_seo_description(){
	global $post;
	if (is_single() || is_page()){
		if ( $post->post_excerpt != "" ) {
			return preg_replace( '/ \[&hellip;]$/', '&hellip;', $post->post_excerpt );
		}
		if ( ! post_password_required() && get_option( 'argon_ai_post_summary', false ) && get_post_meta( get_post()->ID, "argon_ai_post_summary", true ) !== false && get_option( 'argon_ai_show_post_summary_in_home', true ) && get_post_meta( get_post()->ID, "argon_ai_summary", true ) != "") {
			return mb_substr( get_post_meta( get_post()->ID, "argon_ai_summary", true ), 0, 160 ) . "...";
		}
		if ( get_the_excerpt() != "" ) {
			return preg_replace( '/ \[&hellip;]$/', '&hellip;', get_the_excerpt() );
		}
		if ( ! post_password_required() ) {
			return htmlspecialchars( mb_substr( str_replace( "\n", '', strip_tags( $post->post_content ) ), 0, 160 ) ) . "...";
		} else {
			return __( "这是一个加密页面，需要密码来查看", 'argon' );
		}
	}else{
		return get_option('argon_seo_description');
	}
}
//页面 Keywords
function get_seo_keywords(){
	if (is_single()){
		global $post;
		$tags = get_the_tags('', ',', '', $post -> ID);
		if ($tags != null){
			$res = "";
			foreach ($tags as $tag){
				if ($res != ""){
					$res .= ",";
				}
				$res .= $tag -> name;
			}
			return $res;
		}
	}
	if (is_category()){
		return single_cat_title('', false);
	}
	if (is_tag()){
		return single_tag_title('', false);
	}
	if (is_author()){
		return get_the_author();
	}
	if (is_post_type_archive()){
		return post_type_archive_title('', false);
	}
	if (is_tax()){
		return single_term_title('', false);
	}
	return get_option('argon_seo_keywords');
}
