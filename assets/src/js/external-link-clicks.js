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

function argonBuildExternalLinkClickBody(postId, url) {
	var nonce = window.argonConfig.external_link_clicks_nonce || '';
	var clientId = argonGetExternalLinkClickClientId();
	return 'action=argon_click_external_link'
		+ '&post_id=' + encodeURIComponent(postId)
		+ '&url=' + encodeURIComponent(url)
		+ '&client_id=' + encodeURIComponent(clientId)
		+ '&page_id=' + encodeURIComponent(argonExternalLinkClickPageId)
		+ '&_wpnonce=' + encodeURIComponent(nonce);
}

function argonSendExternalLinkClick(postId, url, callback) {
	var ajaxUrl = window.argonConfig.wp_path + 'wp-admin/admin-ajax.php';
	var body = argonBuildExternalLinkClickBody(postId, url);

	if (window.fetch) {
		fetch(ajaxUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: body,
			keepalive: true,
			credentials: 'same-origin'
		}).then(function (response) {
			return response.json();
		}).then(callback).catch(function () {});
	} else {
		var xhr = new XMLHttpRequest();
		xhr.open('POST', ajaxUrl, false);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.send(body);
		if (xhr.responseText) {
			try {
				callback(JSON.parse(xhr.responseText));
			} catch (e) {}
		}
	}
}

function argonUpdateExternalLinkClickBadge($wrapper, count) {
	var $badge = $wrapper.find('.external-link-click-badge');
	if ($badge.length) {
		$badge.data('count', count).text(argonFormatClickCount(count));
	} else {
		$wrapper.append('<span class="external-link-click-badge" data-count="' + count + '">' + argonFormatClickCount(count) + '</span>');
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

	var $wrapper = $(this).closest('.external-link-click-wrapper');
	argonSendExternalLinkClick(postId, url, function (response) {
		if (!response || response.status !== 'success') return;
		var count = parseInt(response.count, 10);
		if (isNaN(count)) return;
		argonUpdateExternalLinkClickBadge($wrapper, count);
	});
});
