var $ = window.$;
var argonClickedExternalLinks = {};
var argonExternalLinkClickPageId = Date.now().toString(36) + Math.random().toString(36).slice(2);

function argonFormatClickCount(count) {
	if (count >= 1000) {
		return (count / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
	}
	return count.toString();
}

function argonGetExternalLinkClickClientId() {
	var key = 'argon_external_link_clicks_client_id';
	try {
		var clientId = window.localStorage.getItem(key);
		if (!clientId) {
			clientId = Date.now().toString(36) + Math.random().toString(36).slice(2);
			window.localStorage.setItem(key, clientId);
		}
		return clientId;
	} catch (e) {
		return '';
	}
}

function argonGetExternalLinkClickKey(postId, url) {
	return postId + '|' + url;
}

function argonSendExternalLinkClick(postId, url) {
	var nonce = window.argonConfig.external_link_clicks_nonce || '';
	var ajaxUrl = window.argonConfig.wp_path + 'wp-admin/admin-ajax.php';
	var clientId = argonGetExternalLinkClickClientId();
	var body = 'action=argon_click_external_link'
		+ '&post_id=' + encodeURIComponent(postId)
		+ '&url=' + encodeURIComponent(url)
		+ '&client_id=' + encodeURIComponent(clientId)
		+ '&page_id=' + encodeURIComponent(argonExternalLinkClickPageId)
		+ '&_wpnonce=' + encodeURIComponent(nonce);

	if (navigator.sendBeacon) {
		var blob = new Blob([body], { type: 'application/x-www-form-urlencoded' });
		navigator.sendBeacon(ajaxUrl, blob);
	} else {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', ajaxUrl, false);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.send(body);
	}
}

$(document).on('click', '.external-link-click-wrapper a', function (e) {
	if (!window.argonConfig || !window.argonConfig.post_id) return;

	var postId = window.argonConfig.post_id;
	var url = $(this).attr('href');
	if (!url || url.charAt(0) === '#') return;

	var clickKey = argonGetExternalLinkClickKey(postId, url);
	if (argonClickedExternalLinks[clickKey]) return;
	argonClickedExternalLinks[clickKey] = true;

	argonSendExternalLinkClick(postId, url);

	var $wrapper = $(this).closest('.external-link-click-wrapper');
	var $badge = $wrapper.find('.external-link-click-badge');
	if ($badge.length) {
		var current = parseInt($badge.data('count'), 10) || 0;
		var next = current + 1;
		$badge.data('count', next).text(argonFormatClickCount(next));
	} else {
		$wrapper.append('<span class="external-link-click-badge" data-count="1">1</span>');
	}
});
