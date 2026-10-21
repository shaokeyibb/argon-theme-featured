const $ = window.$;

const renderGithubInfo = ($card, description, homepage, stars, forks) => {
	const $description = $(".github-info-card-description", $card).text(description || "");
	if (typeof homepage === "string" && /^https?:\/\//i.test(homepage)) {
		$description.append(" ", $("<a>").attr({
			href: homepage, target: "_blank", rel: "noopener noreferrer", "no-pjax": ""
		}).text(homepage));
	}
	$(".github-info-card-stars", $card).text(stars ?? "-");
	$(".github-info-card-forks", $card).text(forks ?? "-");
};
export const getGithubInfoCardContent = () => {
	$(".github-info-card").each(function(){
		(function($this){
			if ($this.attr("data-getdata") == "backend"){
				renderGithubInfo($this, $this.attr("data-description"), $this.attr("data-homepage"), $this.attr("data-stars"), $this.attr("data-forks"));
				return;
			}
			$(".github-info-card-description" , $this).html("Loading...");
			$(".github-info-card-stars" , $this).html("-");
			$(".github-info-card-forks" , $this).html("-");
			const author = encodeURIComponent($this.attr("data-author"));
			const project = encodeURIComponent($this.attr("data-project"));
			$.ajax({
				url : "https://api.github.com/repos/" + author + "/" + project,
				type : "GET",
				dataType : "json",
				success : function(result){
					renderGithubInfo($this, result.description, result.homepage, result.stargazers_count, result.forks_count);
				},
				error : function(xhr){
					if (xhr.status == 404){
						$(".github-info-card-description" , $this).html(__("找不到该 Repo"));
					}else{
						$(".github-info-card-description" , $this).html(__("获取 Repo 信息失败"));
					}
				}
			});
		})($(this));
	});
}
document.addEventListener('DOMContentLoaded', function() {
	getGithubInfoCardContent();
});
