(function () {
    'use strict';
    var config = window.argonHtmlEditor;
    if (!config) return;
    var wp = window.wp || {};
    var translate = wp.i18n ? function (text) { return wp.i18n.__(text, 'argon'); } : function (text) { return text; };
    function encode(value) {
        var bytes = new TextEncoder().encode(value);
        if (bytes.length > config.maxBytes) throw new Error(translate('HTML 文档不能超过 1 MiB。'));
        return btoa(Array.from(bytes, function (byte) { return String.fromCharCode(byte); }).join(''));
    }
    function decode(value) {
        try { return new TextDecoder('utf-8', { fatal: true }).decode(Uint8Array.from(atob(value), function (character) { return character.charCodeAt(0); })); }
        catch (_) { return ''; }
    }
    function postId() {
        var editor = wp.data && wp.data.select('core/editor');
        var id = editor && editor.getCurrentPostId ? editor.getCurrentPostId() : 0;
        return id || config.postId || Number(document.getElementById('post_ID') && document.getElementById('post_ID').value);
    }
    function selectFile(callback, fail) {
        var picker = wp.media({ title: translate('选择 HTML 文档'), library: { type: 'text/html' }, button: { text: translate('使用此文档') }, multiple: false });
        picker.on('select', function () {
            var file = picker.state().get('selection').first().toJSON();
            if (!file.argonHtmlDocument) { fail(translate('请选择通过媒体库上传的受保护 HTML 文档。')); return; }
            callback(file);
        });
        picker.open();
    }
    async function preview(html, fileId) {
        var body = new URLSearchParams({ action: 'argon_html_editor_preview', _wpnonce: config.nonce, post_id: String(postId()), html: html, file_id: String(fileId || 0) });
        var response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body });
        var result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data && result.data.message || translate('无法预览文档。'));
        return result.data;
    }
    function frameProperties(data, height) {
        return { className: 'argon-html-frame', src: data.url, title: translate('HTML 文档安全预览'), sandbox: data.sandbox,
            referrerPolicy: 'no-referrer', allow: "camera 'none'; microphone 'none'; geolocation 'none'; payment 'none'; fullscreen 'none'; clipboard-read 'none'; clipboard-write 'none'",
            'data-argon-html-instance': data.instance, 'data-height': height || 'viewport', height: 640 };
    }
    function refreshFrames() { if (window.argonHtmlInitialize) window.argonHtmlInitialize(); }
    function replaceDeclaration(content, declaration) {
        var pattern = /^[ \t]*(`{3,}|~{3,})[^\r\n]*\r?\n[\s\S]*?^[ \t]*\1[ \t]*(?:\r?\n|$)|`+[^`\r\n]*`+|<pre\b[^>]*>[\s\S]*?<\/pre\s*>|<code\b[^>]*>[\s\S]*?<\/code\s*>|<!--[\s\S]*?-->|\[\[argon_html\b[\s\S]*?\]\]|(?<declaration>\[argon_html\b(?:[^\]"']|"[^"]*"|'[^']*')*\](?:[\s\S]*?\[\/argon_html\])?)/gmi;
        var documents = Array.from(content.matchAll(pattern)).filter(function (match) { return match.groups && match.groups.declaration; });
        if (documents.length > 1) throw new Error(translate('文章中已有多个 HTML 声明，请保留一个。'));
        if (!documents.length) return content.trimEnd() + '\n\n' + declaration + '\n';
        var document = documents[0];
        return content.slice(0, document.index) + declaration + content.slice(document.index + document[0].length);
    }
    function findDocumentBlock(blocks) {
        for (var block of blocks) {
            if (block.name === 'argon/html-document') return block;
            var child = findDocumentBlock(block.innerBlocks || []);
            if (child) return child;
        }
        return null;
    }

    if (wp.blocks && wp.element && wp.components && (wp.blockEditor || wp.editor)) {
        var element = wp.element.createElement;
        var components = wp.components;
        var blockEditor = wp.blockEditor || wp.editor;
        wp.blocks.registerBlockType('argon/html-document', {
            apiVersion: 2, title: translate('Argon HTML 文档'), icon: 'media-code', category: 'argon',
            description: translate('替换文章正文；其他区块作为摘要、搜索、RSS 和文本阅读使用的替代正文。'),
            supports: { html: false, multiple: false },
            attributes: { source: { type: 'string', default: '' }, fileId: { type: 'number', default: 0 }, height: { type: 'string', default: 'auto' }, hideHeader: { type: 'boolean', default: false } },
            edit: function (properties) {
                var attributes = properties.attributes;
                var setAttributes = properties.setAttributes;
                var state = wp.element.useState(null);
                var previewData = state[0];
                var setPreview = state[1];
                var notice = wp.element.useState('');
                var busy = wp.element.useState(false);
                wp.element.useEffect(function () { refreshFrames(); }, [previewData]);
                function error(message) { notice[1](message); }
                var blockProps = blockEditor.useBlockProps ? blockEditor.useBlockProps({ className: 'argon-html-block' }) : { className: 'argon-html-block' };
                if (!config.canAuthor) return element('div', blockProps, translate('修改 HTML 文档需要 unfiltered_html 权限。可以编辑其他区块中的替代正文。'));
                return element('div', blockProps,
                    element('strong', null, translate('HTML 文档文章')),
                    element('p', null, translate('此文档替换前台正文，其他区块作为替代正文。源码仅在沙箱中运行。')),
                    element('div', { className: 'argon-html-actions' },
                        element(components.Button, { variant: 'secondary', onClick: function () { selectFile(function (file) { setAttributes({ fileId: file.id, source: '' }); setPreview(null); error(''); }, error); } }, translate('从媒体库选择 HTML')),
                        attributes.fileId ? element(components.Button, { variant: 'tertiary', onClick: function () { setAttributes({ fileId: 0 }); setPreview(null); } }, translate('切换为内联文档')) : null),
                    attributes.fileId ? element('p', null, translate('媒体库附件 ID：') + attributes.fileId) : element(components.TextareaControl, {
                        label: translate('完整 HTML 源码'), value: decode(attributes.source), rows: 14,
                        onChange: function (value) { try { setAttributes({ source: encode(value), fileId: 0 }); setPreview(null); error(''); } catch (exception) { error(exception.message); } }
                    }),
                    element(components.SelectControl, { label: translate('文档高度'), value: attributes.height,
                        options: [{ label: translate('自动高度'), value: 'auto' }, { label: translate('视口高度（适合仪表盘）'), value: 'viewport' }, { label: '800 px', value: '800' }],
                        onChange: function (value) { setAttributes({ height: value }); setPreview(null); } }),
                    element(components.ToggleControl, { label: translate('隐藏主题文章标题和元信息'), checked: attributes.hideHeader, onChange: function (value) { setAttributes({ hideHeader: value }); } }),
                    element(components.Button, { variant: 'primary', isBusy: busy[0], disabled: busy[0], onClick: async function () {
                        busy[1](true); error('');
                        try { setPreview(await preview(decode(attributes.source), attributes.fileId)); } catch (exception) { error(exception.message); }
                        finally { busy[1](false); }
                    } }, translate('安全预览')),
                    notice[0] ? element('p', { role: 'status' }, notice[0]) : null,
                    previewData ? element('iframe', frameProperties(previewData, 'viewport')) : null);
            },
            save: function () { return null; }
        });
    }

    function classicTools() {
        var tools = document.getElementById('argon-html-classic-tools');
        if (!tools) return;
        var source = tools.querySelector('textarea');
        var fileLabel = tools.querySelector('[data-argon-html-file]');
        var fileId = Number(fileLabel.textContent) || 0;
        var status = tools.querySelector('[data-argon-html-status]');
        var height = tools.querySelector('[data-argon-html-height]');
        var hideHeader = tools.querySelector('[data-argon-html-hide-header]');
        function error(message) { status.textContent = message; }
        tools.querySelector('[data-argon-html-select]').addEventListener('click', function () {
            selectFile(function (file) { fileId = file.id; fileLabel.textContent = file.filename + ' (ID: ' + file.id + ')'; source.value = ''; error(''); }, error);
        });
        source.addEventListener('input', function () { fileId = 0; fileLabel.textContent = ''; });
        tools.querySelector('[data-argon-html-preview]').addEventListener('click', async function () {
            error(translate('正在准备安全预览…'));
            try {
                var data = await preview(source.value, fileId);
                var frame = document.createElement('iframe');
                var properties = frameProperties(data, 'viewport');
                Object.keys(properties).forEach(function (key) { frame.setAttribute(key === 'className' ? 'class' : key, properties[key]); });
                tools.querySelector('[data-argon-html-preview-container]').replaceChildren(frame);
                refreshFrames(); error('');
            } catch (exception) { error(exception.message); }
        });
        tools.querySelector('[data-argon-html-insert]').addEventListener('click', function () {
            try {
                if (document.body.classList.contains('block-editor-page') && wp.data && wp.blocks && wp.data.select('core/block-editor')) {
                    var attributes = {
                        source: fileId ? '' : encode(source.value), fileId: fileId, height: height.value, hideHeader: hideHeader.checked
                    };
                    var existing = findDocumentBlock(wp.data.select('core/block-editor').getBlocks());
                    if (existing) wp.data.dispatch('core/block-editor').updateBlockAttributes(existing.clientId, attributes);
                    else wp.data.dispatch('core/block-editor').insertBlocks(wp.blocks.createBlock('argon/html-document', attributes));
                    error(translate('已插入 HTML 文档区块。请保留一个文档声明，并保存文章。'));
                    return;
                }
                var declaration = '[argon_html height="' + height.value + '" hide_header="' + String(hideHeader.checked) + '"';
                declaration += fileId ? ' file_id="' + fileId + '"]' : ' encoding="base64"]' + encode(source.value) + '[/argon_html]';
                var markdownEditor = window.githuber_md_editor;
                var textarea = document.getElementById('content');
                var content = markdownEditor && markdownEditor.getMarkdown ? markdownEditor.getMarkdown() : textarea.value;
                content = replaceDeclaration(content, declaration);
                if (markdownEditor && markdownEditor.setMarkdown) markdownEditor.setMarkdown(content);
                else {
                    if (window.switchEditors && window.tinymce && window.tinymce.get('content') && !window.tinymce.get('content').isHidden()) window.switchEditors.go('content', 'html');
                    textarea.value = content;
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                }
                error(translate('文档声明已插入。其他内容将作为替代正文，请保存文章。'));
            } catch (exception) { error(exception.message); }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', classicTools);
    else classicTools();
})();
