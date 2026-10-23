(function () {
    'use strict';
    var config = window.argonHtmlBridge;
    if (!config || window.parent === window) return;
    var headings = [];
    var scheduled = false;
    var lastLayout = '';
    function send(type, data) {
        window.parent.postMessage(Object.assign({ type: 'argon-html:' + type, instance: config.instance }, data), config.parentOrigin);
    }
    function report() {
        scheduled = false;
        if (!document.body) return;
        headings = Array.prototype.slice.call(document.querySelectorAll('h1,h2,h3,h4,h5,h6')).filter(function (heading) {
            return heading.getClientRects().length && heading.textContent.trim();
        }).slice(0, 200);
        // body.scrollHeight can be clamped to the iframe viewport, preventing shrinkage or causing
        // scrollbar-driven growth. Measure content bounds instead, including positioned descendants.
        var bodyBox = document.body.getBoundingClientRect();
        var height = Math.max(document.body.offsetHeight, bodyBox.bottom + window.scrollY);
        var elements = document.body.querySelectorAll('*');
        for (var i = 0; i < Math.min(elements.length, 3000); i++) {
            var node = elements[i];
            if (!node.getClientRects().length || getComputedStyle(node).position === 'fixed') continue;
            var box = node.getBoundingClientRect();
            height = Math.max(height, box.bottom + window.scrollY + (parseFloat(getComputedStyle(node).marginBottom) || 0));
        }
        height = Math.ceil(height) + Math.max(0, window.innerHeight - document.documentElement.clientHeight);
        var items = headings.map(function (heading) {
            return { text: heading.textContent.trim().slice(0, 200), level: Number(heading.tagName.slice(1)), top: Math.round(heading.getBoundingClientRect().top + window.scrollY) };
        });
        var layout = JSON.stringify({ height: height, headings: items });
        if (layout === lastLayout) return;
        lastLayout = layout;
        send('layout', { height: height, headings: items });
    }
    function schedule() {
        if (scheduled) return;
        scheduled = true;
        window.setTimeout(report, 100);
    }
    window.addEventListener('message', function (event) {
        var message = event.data;
        if (event.source !== window.parent || event.origin !== config.parentOrigin || !message || message.instance !== config.instance) return;
        if (message.type === 'argon-html:theme') {
            var root = document.documentElement;
            root.dataset.argonTheme = message.dark ? 'dark' : 'light';
            root.classList.toggle('argon-dark', Boolean(message.dark));
            if (/^#[0-9a-f]{3,8}$/i.test(message.primary || '')) root.style.setProperty('--argon-primary-color', message.primary);
            if (Number.isFinite(message.viewportHeight)) root.style.setProperty('--argon-viewport-height', Math.max(200, Math.min(4000, message.viewportHeight)) + 'px');
            lastLayout = '';
            schedule();
        }
        if (message.type === 'argon-html:heading' && Number.isInteger(message.index) && headings[message.index]) {
            var heading = headings[message.index];
            if (message.internal) heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
            send('position', { top: Math.round(heading.getBoundingClientRect().top + window.scrollY) });
        }
    });
    function start() {
        if (window.ResizeObserver) {
            var observer = new ResizeObserver(schedule);
            observer.observe(document.body);
        }
        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true, characterData: true,
            attributes: true, attributeFilter: ['class', 'style', 'hidden', 'open', 'aria-hidden'] });
        document.addEventListener('load', schedule, true);
        window.addEventListener('resize', schedule);
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
        report();
        send('ready', {});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
