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

    /**
     * The smallest and the tallest height an iframe is allowed to take.
     * A height outside this range is not applied, so a stray or hostile
     * message cannot collapse the widget or blow it out of the page.
     */
    var MIN_HEIGHT = 80;
    var MAX_HEIGHT = 4000;

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

    /**
     * The origin the widget is served from.
     *
     * The resize message always comes from the widget itself, so the origin
     * of the widget document is the only origin that may speak to us. The
     * host page may be any website in the world; it never needs to be known
     * here.
     */
    function widgetOrigin(iframe) {
        try {
            return new URL(iframe.getAttribute('src'), window.location.href).origin;
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
        var origin = widgetOrigin(iframe);

        // The widget document reports its own height so that the iframe can
        // grow with the content instead of clipping it. The message is only
        // acted on when it comes from the widget itself, from this very
        // iframe, and when it carries a height that is safe to use.
        window.addEventListener('message', function (event) {
            if (!origin || event.origin !== origin) {
                return;
            }

            if (event.source !== iframe.contentWindow) {
                return;
            }

            if (!event.data || typeof event.data !== 'object' || Array.isArray(event.data)) {
                return;
            }

            if (event.data.type !== 'kudosly-widget-resize') {
                return;
            }

            if (typeof event.data.widgetId !== 'string' || event.data.widgetId !== widgetId) {
                return;
            }

            var height = event.data.height;

            if (typeof height !== 'number' || !isFinite(height)) {
                return;
            }

            if (height < MIN_HEIGHT || height > MAX_HEIGHT) {
                return;
            }

            iframe.style.height = height + 'px';
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
