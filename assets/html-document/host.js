(function () {
    'use strict';
    var states = new Map();
    function viewportHeight() { return Math.max(320, Math.min(1200, Math.round(window.innerHeight * 0.85))); }
    function send(frame, type, values) {
        // Sandboxed documents have an opaque origin. Only non-sensitive presentation data is sent.
        if (frame.contentWindow) frame.contentWindow.postMessage(Object.assign({ type: 'argon-html:' + type, instance: frame.dataset.argonHtmlInstance }, values), '*');
    }
    // Columns can resize without a window resize event. Remeasure after the
    // actual iframe box changes, including heights applied by the host.
    var frameObserver = window.ResizeObserver ? new ResizeObserver(function (entries) {
        entries.forEach(function (entry) { send(entry.target, 'measure', {}); });
    }) : null;
    function theme(frame) {
        var primary = getComputedStyle(document.documentElement).getPropertyValue('--themecolor').trim() || '#5e72e4';
        var state = states.get(frame);
        if (!state) return;
        send(frame, 'theme', { dark: document.documentElement.classList.contains('darkmode'), primary: primary, viewportHeight: window.innerHeight,
            autoHeight: state.mode === 'auto' && !state.viewportFallback });
    }
    function layout(frame, state, message) {
        var now = Date.now();
        state.lastMessage = now;
        if (state.mode === 'auto') {
            var height = Math.max(160, Math.min(60000, Math.ceil(message.height)));
            // Width changes are normal responsive reflow, not a height feedback loop.
            var widthChanged = message.width !== state.lastWidth;
            if (widthChanged) state.changes = [];
            state.lastWidth = message.width;
            state.changes = state.changes.filter(function (change) { return now - change.time < 2500; });
            if (height > state.lastHeight + 2) state.changes.push({ time: now, delta: height - state.lastHeight });
            var looping = (state.viewportFallback === 'loop' && !widthChanged && height >= state.lastHeight)
                || (state.changes.length > 8 && Math.max.apply(null, state.changes.map(function (change) { return change.delta; }))
                    - Math.min.apply(null, state.changes.map(function (change) { return change.delta; })) < 3);
            // A temporary oversized layout must not permanently disable automatic sizing.
            var wasFallback = Boolean(state.viewportFallback);
            state.viewportFallback = looping ? 'loop' : message.height > 60000 ? 'limit' : false;
            if (wasFallback !== Boolean(state.viewportFallback)) send(frame, 'sizing', { autoHeight: !state.viewportFallback });
            if (state.viewportFallback) {
                height = viewportHeight();
                state.changes = [];
            }
            if (height !== state.lastHeight) frame.style.height = height + 'px';
            state.lastHeight = height;
        }
        state.headings = Array.isArray(message.headings) ? message.headings.slice(0, 200).filter(function (heading) {
            return heading && typeof heading.text === 'string' && heading.text.length <= 200 && Number.isInteger(heading.level)
                && heading.level >= 1 && heading.level <= 6 && Number.isFinite(heading.top) && heading.top >= -100 && heading.top <= 60000;
        }) : [];
        catalog(frame, state);
    }
    function initialize() {
        states.forEach(function (state, frame) {
            if (!frame.isConnected) {
                window.clearTimeout(state.layoutTimer);
                if (frameObserver) frameObserver.unobserve(frame);
                states.delete(frame);
            }
        });
        document.querySelectorAll('iframe.argon-html-frame').forEach(function (frame) {
            if (states.has(frame)) {
                var existing = states.get(frame);
                if (existing.catalogContainer !== document.getElementById('leftbar_catalog')) catalog(frame, existing);
                return;
            }
            var mode = frame.dataset.height || 'auto';
            states.set(frame, { mode: mode, headings: [], changes: [], lastHeight: 640 });
            frame.style.height = (mode === 'auto' ? 640 : mode === 'viewport' ? viewportHeight() : Math.max(200, Math.min(2000, Number(mode) || 640))) + 'px';
            frame.addEventListener('load', function () { theme(frame); });
            if (frameObserver) frameObserver.observe(frame);
            theme(frame);
        });
    }
    function catalog(frame, state) {
        if (!frame.closest('#post_content')) return;
        var container = document.getElementById('leftbar_catalog');
        if (!container) return;
        state.catalogContainer = container;
        var list = document.createElement('ul');
        state.headings.forEach(function (heading, index) {
            var item = document.createElement('li');
            item.className = 'index-item';
            item.style.marginLeft = Math.max(0, heading.level - 1) * 10 + 'px';
            var link = document.createElement('a');
            link.className = 'index-link';
            link.href = '#argon-html-heading-' + index;
            link.setAttribute('no-pjax', '');
            link.textContent = heading.text;
            link.addEventListener('click', function (event) {
                event.preventDefault();
                state.pendingPositionUntil = Date.now() + 1500;
                send(frame, 'heading', { index: index, internal: state.mode !== 'auto' || Boolean(state.viewportFallback) });
            });
            item.appendChild(link);
            list.appendChild(item);
        });
        container.replaceChildren(list);
        if (!state.headings.length) container.textContent = '—';
    }
    window.addEventListener('message', function (event) {
        var message = event.data;
        if (!message || typeof message !== 'object' || typeof message.instance !== 'string' || event.origin !== 'null') return;
        var frame = Array.from(states.keys()).find(function (candidate) {
            return candidate.isConnected && event.source === candidate.contentWindow && message.instance === candidate.dataset.argonHtmlInstance;
        });
        if (!frame) return;
        var state = states.get(frame);
        if (message.type === 'argon-html:ready') theme(frame);
        if (message.type === 'argon-html:position' && Number.isFinite(message.top) && state.pendingPositionUntil > Date.now()) {
            state.pendingPositionUntil = 0;
            var top = state.mode === 'auto' && !state.viewportFallback ? Math.max(0, Math.min(60000, message.top)) : 0;
            window.scrollTo({ top: frame.getBoundingClientRect().top + window.scrollY + top - 90, behavior: 'smooth' });
        }
        if (message.type !== 'argon-html:layout' || !Number.isFinite(message.height)) return;
        // Coalesce bursts without dropping the final layout after a resize.
        state.pendingLayout = message;
        if (state.layoutTimer) return;
        state.layoutTimer = window.setTimeout(function () {
            state.layoutTimer = 0;
            if (frame.isConnected) layout(frame, state, state.pendingLayout);
        }, Math.max(0, 70 - (Date.now() - (state.lastMessage || 0))));
    });
    window.addEventListener('resize', function () {
        states.forEach(function (state, frame) {
            if (state.mode === 'viewport' || state.viewportFallback) {
                state.lastHeight = viewportHeight();
                frame.style.height = state.lastHeight + 'px';
            }
            theme(frame);
        });
    });
    new MutationObserver(function () { states.forEach(function (_, frame) { theme(frame); }); })
        .observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'style'] });
    window.argonHtmlInitialize = initialize;
    // Support blocks mounted dynamically by plugins without a navigation-library dependency.
    var pendingInitialization = false;
    new MutationObserver(function (records) {
        var changed = records.some(function (record) {
            return Array.from(record.addedNodes).concat(Array.from(record.removedNodes)).some(function (node) {
                return node.nodeType === 1 && (node.matches('iframe.argon-html-frame,#leftbar_catalog')
                    || node.querySelector('iframe.argon-html-frame,#leftbar_catalog'));
            });
        });
        if (!changed || pendingInitialization) return;
        pendingInitialization = true;
        requestAnimationFrame(function () { pendingInitialization = false; initialize(); });
    }).observe(document.body, { childList: true, subtree: true });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
