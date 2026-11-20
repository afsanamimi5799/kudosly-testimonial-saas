(function () {
    'use strict';

    /**
     * The Kudosly widget loader.
     *
     * The script finds every placeholder on the page and inserts an iframe
     * that renders the widget. It deliberately does not inject a stylesheet
     * and does not touch anything else on the page, so it can never break
     * the styles of the website that embeds it.
     */
    var PLACEHOLDER_SELECTOR = '[data-kudosly-widget]';

    function embedUrlFor(placeholder) {
        var widgetId = placeholder.getAttribute('data-kudosly-widget');

        if (!widgetId) {
            return null;
        }

        var script = document.currentScript;
        var base = script && script.src ? script.src : window.location.href;

        try {
            var url = new URL(base, window.location.href);
            url.pathname = url.pathname.replace(/\/widgets\/embed\.js$/, '/embed/' + encodeURIComponent(widgetId));
            url.search = '';
            url.hash = '';

            return url.toString();
        } catch (error) {
            return null;
        }
    }

    function build(widgetId, placeholder) {
        var iframe = document.createElement('iframe');

        iframe.setAttribute('src', '/embed/' + encodeURIComponent(widgetId));
        iframe.setAttribute('title', 'Customer Testimonials');
        iframe.setAttribute('loading', 'lazy');
        iframe.setAttribute('allowtransparency', 'true');
        iframe.setAttribute('scrolling', 'no');
        iframe.setAttribute('style', 'width:100%;border:0;overflow:hidden;display:block;');

        placeholder.appendChild(iframe);

        return iframe;
    }

    function render(placeholder) {
        var widgetId = placeholder.getAttribute('data-kudosly-widget');

        if (!widgetId || placeholder.getAttribute('data-kudosly-loaded') === 'true') {
            return;
        }

        placeholder.setAttribute('data-kudosly-loaded', 'true');

        var iframe = build(widgetId, placeholder);

        // The widget document reports its own height so that the iframe can
        // grow with the content instead of clipping it.
        window.addEventListener('message', function (event) {
            if (!event.data || event.data.type !== 'kudosly-widget-resize') {
                return;
            }

            if (event.data.widgetId !== widgetId) {
                return;
            }

            if (typeof event.data.height !== 'number') {
                return;
            }

            iframe.style.height = event.data.height + 'px';
        });
    }

    function init() {
        var placeholders = Array.prototype.slice.call(document.querySelectorAll(PLACEHOLDER_SELECTOR));

        placeholders.forEach(render);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
